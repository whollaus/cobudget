<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Calendar aggregation of already authorized rows, using integer cents only. */
class AnnualOverviewService {
	public function availableYears(iterable $dates, \DateTimeZone $timezone): array {
		$years = [];
		foreach ($dates as $timestamp) {
			$year = (int)(new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('Y');
			if ($year >= 1970 && $year <= 9999) {
				$years[$year] = true;
			}
		}
		$years = array_keys($years);
		rsort($years, SORT_NUMERIC);
		return $years;
	}

	/**
	 * One payment can carry several labels and tags. Those rows repeat its amount.
	 * Year totals use $totals, where each qualifying payment is already counted once.
	 * Rows marked counts_toward_total=false must not change the year totals.
	 *
	 * @param iterable<array<string, mixed>> $entries flag aggregates used for label rows
	 * @param iterable<array<string, mixed>> $hashtags
	 * @param array<string, array{column: string, name: string}> $labels enabled labels only
	 * @param iterable<array<string, mixed>> $totals payments with an enabled label or a tag
	 * @return list<array<string, mixed>>
	 */
	public function expandLabelsAndTags(iterable $entries, iterable $hashtags, array $labels, iterable $totals = []): array {
		$rows = [];
		foreach ($totals as $total) {
			$rows[] = [
				'date' => (int)$total['date'],
				'type' => (string)$total['type'],
				'amount_cents' => (int)$total['amount_cents'],
				'payment_count' => (int)($total['payment_count'] ?? 1),
				'assign_group' => false,
			];
		}
		foreach ($entries as $entry) {
			$base = [
				'date' => (int)$entry['date'],
				'type' => (string)$entry['type'],
				'amount_cents' => (int)$entry['amount_cents'],
				'payment_count' => (int)($entry['payment_count'] ?? 1),
			];
			foreach ($labels as $key => $label) {
				if (!$this->flagEnabled($entry[$label['column']] ?? false)) {
					continue;
				}
				$rows[] = $base + [
					'counts_toward_total' => false,
					'group_id' => 'label:' . $key,
					'group_name' => (string)$label['name'],
					'group_kind' => 'label',
					'group_filter' => (string)$key,
				];
			}
		}
		foreach ($hashtags as $hashtag) {
			$id = (int)($hashtag['group_id'] ?? 0);
			$name = trim((string)($hashtag['group_name'] ?? ''));
			if ($id <= 0 || $name === '') {
				continue;
			}
			if (!str_starts_with($name, '#')) {
				$name = '#' . $name;
			}
			$rows[] = [
				'date' => (int)$hashtag['date'],
				'type' => (string)$hashtag['type'],
				'amount_cents' => (int)$hashtag['amount_cents'],
				'payment_count' => (int)($hashtag['payment_count'] ?? 1),
				'counts_toward_total' => false,
				'group_id' => 'tag:' . $id,
				'group_name' => $name,
				'group_kind' => 'hashtag',
				'group_filter' => (string)$id,
			];
		}

		return $rows;
	}

	public function isoWeekCount(int $year, \DateTimeZone $timezone): int {
		$probe = (new \DateTimeImmutable('now', $timezone))->setISODate($year, 53, 1);
		return $probe->format('o') === (string)$year ? 53 : 52;
	}

	public function calendarDayCount(int $year, \DateTimeZone $timezone): int {
		$last = (new \DateTimeImmutable('now', $timezone))->setDate($year, 12, 31);
		return (int)$last->format('z') + 1;
	}

	/**
	 * Dates RecurringEntriesJob would store for one series.
	 * The first date is the saved next occurrence. Later dates use its 09:00
	 * server-time step, including the native month overflow. Dates at or
	 * before $after stay out. The walk stops at $until and after the series end.
	 *
	 * @return list<int>
	 */
	public function recurrenceDates(string $interval, int $multiplier, int $next, ?int $end, int $after, int $until): array {
		if ($next <= 0 || $after >= $until) {
			return [];
		}
		if (!in_array($interval, ['day', 'week', 'month'], true)) {
			$interval = 'month';
		}
		$multiplier = max(1, $multiplier);
		$run = $this->recurrenceInstant($next);
		if ($end !== null && $run > $end) {
			return [];
		}
		$dates = [];
		for ($guard = 0; $guard < 4000; $guard++) {
			if ($run > $until) {
				break;
			}
			if ($run > $after) {
				$dates[] = $run;
			}
			$following = $this->recurrenceStep($run, $interval, $multiplier);
			if ($following <= $run || ($end !== null && $following > $end)) {
				break;
			}
			$run = $following;
		}

		return $dates;
	}

	private function recurrenceInstant(int $timestamp): int {
		$date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));

		return $date->setTime(9, 0, 0)->getTimestamp();
	}

	private function recurrenceStep(int $timestamp, string $interval, int $multiplier): int {
		$date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(9, 0, 0);
		$step = $interval === 'day' ? '+' . $multiplier . ' day' : ($interval === 'week' ? '+' . $multiplier . ' week' : '+' . $multiplier . ' month');
		$next = $date->modify($step);

		return $next->setTime(9, 0, 0)->getTimestamp();
	}

	public function build(iterable $entries, int $year, \DateTimeZone $timezone, string $grouping, int $month = 0, bool $byQuarter = false, bool $byWeek = false, bool $byDay = false): array {
		$daily = $month >= 1 && $month <= 12;
		$quarterly = !$daily && $byQuarter;
		$weekly = !$daily && !$quarterly && $byWeek;
		$dailyYear = !$daily && !$quarterly && !$weekly && $byDay;
		$buckets = $daily ? (int)(new \DateTimeImmutable('now', $timezone))->setDate($year, $month, 1)->format('t') : ($quarterly ? 4 : ($weekly ? $this->isoWeekCount($year, $timezone) : ($dailyYear ? $this->calendarDayCount($year, $timezone) : 12)));
		$groups = ['income' => [], 'expense' => []];
		$totals = ['income' => $this->emptyRow($buckets), 'expense' => $this->emptyRow($buckets)];
		foreach ($entries as $entry) {
			$type = (string)$entry['type'];
			if (!isset($groups[$type])) {
				continue;
			}
			$date = (new \DateTimeImmutable('@' . $entry['date']))->setTimezone($timezone);
			if ($weekly) {
				// Columns follow the ISO week-year, so late December and early January can leave the calendar year.
				if ((int)$date->format('o') !== $year) {
					continue;
				}
				$bucket = (int)$date->format('W') - 1;
			} elseif ($dailyYear) {
				if ((int)$date->format('Y') !== $year) {
					continue;
				}
				$bucket = (int)$date->format('z');
			} else {
				$monthNumber = (int)$date->format('n');
				if ((int)$date->format('Y') !== $year || ($daily && $monthNumber !== $month)) {
					continue;
				}
				$bucket = $daily ? (int)$date->format('j') - 1 : ($quarterly ? intdiv($monthNumber - 1, 3) : $monthNumber - 1);
			}
			if ($bucket < 0 || $bucket >= $buckets) {
				continue;
			}
			$cents = (int)$entry['amount_cents'];
			$count = (int)($entry['payment_count'] ?? 1);
			if (!array_key_exists('counts_toward_total', $entry) || $entry['counts_toward_total']) {
				$this->add($totals[$type], $bucket, $cents, $count);
			}
			if (array_key_exists('assign_group', $entry) && !$entry['assign_group']) {
				continue;
			}
			$id = $grouping === 'labelsAndTags' ? (string)($entry['group_id'] ?? '') : (int)($entry['group_id'] ?? 0);
			if ($grouping === 'labelsAndTags' && $id === '') {
				continue;
			}
			$parentId = $grouping === 'categories' ? (int)($entry['parent_id'] ?? 0) : 0;
			$rootId = $parentId ?: $id;
			if (!isset($groups[$type][$rootId])) {
				$groups[$type][$rootId] = $this->emptyRow($buckets) + [
					'id' => $rootId,
					'name' => (string)($parentId ? $entry['parent_name'] : ($entry['group_name'] ?? '')),
					'children' => [],
				];
				if ($grouping === 'labelsAndTags') {
					$groups[$type][$rootId]['kind'] = (string)($entry['group_kind'] ?? '');
					$groups[$type][$rootId]['filter'] = (string)($entry['group_filter'] ?? '');
				}
			}
			$root = &$groups[$type][$rootId];
			$this->add($root, $bucket, $cents, $count);
			if (!isset($root['children'][$id])) {
				$root['children'][$id] = $this->emptyRow($buckets) + [
					'id' => $id, 'name' => (string)($entry['group_name'] ?? ''), 'direct' => $id === $rootId,
				];
			}
			$this->add($root['children'][$id], $bucket, $cents, $count);
			unset($root);
		}
		foreach ($groups as &$rows) {
			foreach ($rows as &$row) {
				$hasChildren = count($row['children']) > 1 || !isset($row['children'][$row['id']]);
				$row['children'] = $grouping === 'categories' && $hasChildren ? array_values($row['children']) : [];
			}
			unset($row);
			$rows = array_values($rows);
		}
		unset($rows);
		$totals['balance'] = $this->emptyRow($buckets);
		foreach (range(0, $buckets - 1) as $bucket) {
			$this->add($totals['balance'], $bucket, $totals['income']['months'][$bucket] - $totals['expense']['months'][$bucket], $totals['income']['counts'][$bucket] + $totals['expense']['counts'][$bucket]);
		}
		return ['groups' => $groups, 'totals' => $totals];
	}

	/** Compare authorized rows only; an empty bucket is zero after history begins. */
	public function compare(array $report, array $entries, int $year, \DateTimeImmutable $now, string $grouping, string $mode, int $month = 0, bool $byQuarter = false, bool $byWeek = false, bool $byDay = false): array {
		$timezone = $now->getTimezone();
		$daily = $month >= 1 && $month <= 12;
		$quarterly = !$daily && $byQuarter;
		$weekly = !$daily && !$quarterly && $byWeek;
		$dailyYear = !$daily && !$quarterly && !$weekly && $byDay;
		if ($weekly) {
			$anchor = $now->setISODate($year, 1, 1)->setTime(0, 0);
			$bucketCount = $this->isoWeekCount($year, $timezone);
		} elseif ($dailyYear) {
			$anchor = $now->setDate($year, 1, 1)->setTime(0, 0);
			$bucketCount = $this->calendarDayCount($year, $timezone);
		} else {
			$anchor = $daily ? $now->setDate($year, $month, 1)->setTime(0, 0) : $now->setDate($year, 1, 1)->setTime(0, 0);
			$bucketCount = $daily ? (int)$anchor->format('t') : ($quarterly ? 4 : 12);
		}
		$flatten = static function(array $data): array {
			$result = [];
			foreach ($data['totals'] as $type => $row) $result['total-' . $type] = $row;
			foreach ($data['groups'] as $type => $rows) {
				foreach ($rows as $row) {
					$result[$type . '-' . $row['id']] = $row;
					foreach ($row['children'] as $child) $result[$type . '-' . $row['id'] . '-' . $child['id']] = $child;
					// A direct category still needs a key when it has no children in this period.
					if (!$row['children']) $result[$type . '-' . $row['id'] . '-' . $row['id']] = $row;
				}
			}
			return $result;
		};
		$comparisons = [];
		for ($index = 0; $index < $bucketCount; $index++) {
			if ($daily || $dailyYear) {
				$currentStart = $anchor->modify('+' . $index . ' days');
				if ($currentStart > $now) continue;
				$currentEnd = $currentStart->setTime(23, 59, 59);
				$partial = $currentEnd > $now;
				if ($partial) $currentEnd = $now;
				if ($mode === 'year') {
					$day = (int)$currentStart->format('j');
					$monthNumber = (int)$currentStart->format('n');
					$previousStart = $currentStart->setDate((int)$currentStart->format('Y') - 1, $monthNumber, $day)->setTime(0, 0);
					// February 29 has no matching day in a non-leap year.
					if ((int)$previousStart->format('n') !== $monthNumber || (int)$previousStart->format('j') !== $day) continue;
				} else {
					$previousStart = $currentStart->modify('-1 day');
				}
				$previousEnd = $partial
					? $previousStart->setTime((int)$now->format('H'), (int)$now->format('i'), (int)$now->format('s'))
					: $previousStart->setTime(23, 59, 59);
			} elseif ($quarterly) {
				$currentStart = $anchor->modify('+' . ($index * 3) . ' months');
				if ($currentStart > $now) {
					continue;
				}
				$currentEnd = $currentStart->modify('+3 months')->modify('-1 second');
				$previousStart = $currentStart->modify($mode === 'month' ? '-3 months' : '-1 year');
				$previousEnd = $previousStart->modify('+3 months')->modify('-1 second');
				if ($currentEnd > $now) {
					$elapsed = $now->getTimestamp() - $currentStart->getTimestamp();
					$currentEnd = $now;
					$previousEnd = $previousStart->setTimestamp($previousStart->getTimestamp() + $elapsed);
				}
			} elseif ($weekly) {
				$currentStart = $anchor->modify('+' . $index . ' weeks');
				if ($currentStart > $now) {
					continue;
				}
				$currentEnd = $currentStart->modify('+1 week')->modify('-1 second');
				if ($mode === 'year') {
					$previousStart = $currentStart->setISODate($year - 1, $index + 1, 1)->setTime(0, 0);
					// A 53rd week has no match when the previous week-year stops at 52.
					if ((int)$previousStart->format('W') !== $index + 1 || (int)$previousStart->format('o') !== $year - 1) {
						continue;
					}
				} else {
					$previousStart = $currentStart->modify('-1 week');
				}
				$previousEnd = $previousStart->modify('+1 week')->modify('-1 second');
				if ($currentEnd > $now) {
					$elapsed = $now->getTimestamp() - $currentStart->getTimestamp();
					$currentEnd = $now;
					$previousEnd = $previousStart->setTimestamp($previousStart->getTimestamp() + $elapsed);
				}
			} else {
				$currentStart = $anchor->modify('+' . $index . ' months');
				if ($currentStart > $now) continue;
				$currentEnd = $currentStart->modify('+1 month')->modify('-1 second');
				$previousStart = $currentStart->modify($mode === 'month' ? '-1 month' : '-1 year');
				$previousEnd = $previousStart->modify('+1 month')->modify('-1 second');
				if ($currentEnd > $now) {
					$day = min((int)$now->format('j'), (int)$previousEnd->format('j'));
					$currentEnd = $now;
					$previousEnd = $previousStart->setDate((int)$previousStart->format('Y'), (int)$previousStart->format('n'), $day)->setTime(23, 59, 59);
				}
			}
			$windows = ['current' => [], 'previous' => [], 'history' => []];
			foreach ($entries as $entry) {
				$date = (int)$entry['date'];
				foreach (['current' => [$currentStart, $currentEnd], 'previous' => [$previousStart, $previousEnd], 'history' => [null, $previousEnd]] as $key => [$from, $to]) {
					if (($from === null || $date >= $from->getTimestamp()) && $date <= $to->getTimestamp()) {
						// Reuse the same exact-cent grouping for each comparison window.
						$windows[$key][] = array_replace($entry, ['date' => $anchor->getTimestamp()]);
					}
				}
			}
			$current = $flatten($this->build($windows['current'], $year, $timezone, $grouping, $daily ? $month : 0, false, $weekly, $dailyYear));
			$previous = $flatten($this->build($windows['previous'], $year, $timezone, $grouping, $daily ? $month : 0, false, $weekly, $dailyYear));
			$history = $flatten($this->build($windows['history'], $year, $timezone, $grouping, $daily ? $month : 0, false, $weekly, $dailyYear));
			foreach ($flatten($report) as $key => $_row) {
				$available = $mode === 'month' ? ($history[$key]['totalCount'] ?? 0) : ($previous[$key]['totalCount'] ?? 0);
				if (!$available) continue;
				$value = $current[$key]['total'] ?? 0;
				$baseline = $previous[$key]['total'] ?? 0;
				$delta = $value - $baseline;
				$percent = $baseline !== 0 ? 100 * $delta / abs($baseline) : null;
				$comparisons[$key][$index] = [
					'current' => $value, 'previous' => $baseline, 'delta' => $delta, 'percent' => $percent,
					'direction' => $baseline !== 0 && abs($delta) * 100 > abs($baseline) * 5 ? ($delta > 0 ? 'up' : 'down') : null,
					'currentFrom' => $currentStart->format('Y-m-d'), 'currentTo' => $currentEnd->format('Y-m-d'),
					'previousFrom' => $previousStart->format('Y-m-d'), 'previousTo' => $previousEnd->format('Y-m-d'),
				];
			}
		}
		$attach = static function(array &$row, string $key) use ($comparisons, $bucketCount): void {
			$row['comparisons'] = array_replace(array_fill(0, $bucketCount, null), $comparisons[$key] ?? []);
		};
		foreach ($report['totals'] as $type => &$row) $attach($row, 'total-' . $type);
		unset($row);
		foreach ($report['groups'] as $type => &$rows) {
			foreach ($rows as &$row) {
				$attach($row, $type . '-' . $row['id']);
				foreach ($row['children'] as &$child) $attach($child, $type . '-' . $row['id'] . '-' . $child['id']);
				unset($child);
			}
			unset($row);
		}
		unset($rows);
		return $report;
	}

	private function flagEnabled(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1';
	}

	private function emptyRow(int $buckets = 12): array {
		return ['months' => array_fill(0, $buckets, 0), 'total' => 0, 'counts' => array_fill(0, $buckets, 0), 'totalCount' => 0];
	}

	private function add(array &$row, int $month, int $cents, int $count): void {
		$row['months'][$month] += $cents;
		$row['total'] += $cents;
		$row['counts'][$month] += $count;
		$row['totalCount'] += $count;
	}
}

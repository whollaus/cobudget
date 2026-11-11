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
	 * The first date is the saved next occurrence. Later dates use its 06:00
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
		if ($end !== null) {
			$endDate = (new \DateTimeImmutable('@' . $end))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
			if ($endDate->format('H:i:s') === '00:00:00') $end = $endDate->setTime(23, 59, 59)->getTimestamp();
		}
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

		return $date->setTime(6, 0, 0)->getTimestamp();
	}

	private function recurrenceStep(int $timestamp, string $interval, int $multiplier): int {
		$date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(6, 0, 0);
		$step = $interval === 'day' ? '+' . $multiplier . ' day' : ($interval === 'week' ? '+' . $multiplier . ' week' : '+' . $multiplier . ' month');
		$next = $date->modify($step);

		return $next->setTime(6, 0, 0)->getTimestamp();
	}

	/** Calendar buckets are clipped to the inclusive selected dates, including partial weeks. */
	public function periods(string $dateFrom, string $dateTo, \DateTimeZone $timezone, string $breakdown, string $periodYears = '', string $periodMonths = ''): array {
		if ($periodYears !== '' || $periodMonths !== '') {
			return $this->selectedCalendarPeriods($dateFrom, $dateTo, $timezone, $breakdown, $periodYears, $periodMonths);
		}
		$from = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom, $timezone);
		$to = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo, $timezone);
		if (!$from || !$to || $from->format('Y-m-d') !== $dateFrom || $to->format('Y-m-d') !== $dateTo || $dateFrom < '1970-01-01' || $dateTo > '9999-12-31' || $from > $to) {
			throw new \InvalidArgumentException('Invalid report dates');
		}
		$to = $to->setTime(23, 59, 59);
		$cursor = match ($breakdown) {
			'day' => $from,
			'week' => $from->modify('monday this week'),
			'quarter' => $from->setDate((int)$from->format('Y'), intdiv((int)$from->format('n') - 1, 3) * 3 + 1, 1),
			default => $from->modify('first day of this month'),
		};
		$step = match ($breakdown) { 'day' => '+1 day', 'week' => '+1 week', 'quarter' => '+3 months', default => '+1 month' };
		$periods = [];
		while ($cursor <= $to) {
			if (count($periods) >= 3660) throw new \InvalidArgumentException('Too many report columns');
			$next = $cursor->modify($step);
			$periods[] = ['dateFrom' => max($from->getTimestamp(), $cursor->getTimestamp()), 'dateTo' => min($to->getTimestamp(), $next->getTimestamp() - 1)];
			$cursor = $next;
		}
		return $periods;
	}

	/** Build only selected calendar segments; no gap can become an aggregate bucket. */
	private function selectedCalendarPeriods(string $dateFrom, string $dateTo, \DateTimeZone $timezone, string $breakdown, string $years, string $months): array {
		$parse = static function(string $text, int $min, int $max, int $limit): array {
			$values = [];
			foreach (explode(',', $text) as $token) {
				if (!ctype_digit($token) || (int)$token < $min || (int)$token > $max) throw new \InvalidArgumentException('Invalid calendar selection');
				$values[(int)$token] = true;
			}
			if (count($values) > $limit) throw new \InvalidArgumentException('Too many calendar choices');
			$values = array_keys($values); sort($values, SORT_NUMERIC); return $values;
		};
		$years = $parse($years, 1970, 9999, 50);
		$months = $months === '' ? range(1, 12) : $parse($months, 1, 12, 12);
		$segments = [];
		$calendar = new \DateTimeImmutable('2000-01-01', $timezone);
		foreach ($years as $year) foreach ($months as $month) {
			$start = $calendar->setDate($year, $month, 1);
			$next = $start->modify('+1 month');
			$last = count($segments) - 1;
			if ($last >= 0 && $segments[$last][1] == $start) $segments[$last][1] = $next;
			else $segments[] = [$start, $next];
		}
		// Validate explicit clipping dates independently of the selection.
		foreach ([$dateFrom, $dateTo] as $date) {
			$parsed = $date === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
			if ($date !== '' && (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || $date < '1970-01-01' || $date > '9999-12-31' || !$parsed || $parsed->format('Y-m-d') !== $date)) throw new \InvalidArgumentException('Invalid report dates');
		}
		$periods = [];
		foreach ($segments as [$start, $next]) {
			$from = max($start->format('Y-m-d'), $dateFrom ?: '1970-01-01');
			$to = min($next->modify('-1 day')->format('Y-m-d'), $dateTo ?: '9999-12-31');
			if ($from > $to) continue;
			array_push($periods, ...$this->periods($from, $to, $timezone, $breakdown));
			if (count($periods) > 3660) throw new \InvalidArgumentException('Too many report columns');
		}
		if (!$periods) throw new \InvalidArgumentException('Empty calendar selection');
		return $periods;
	}

	public function periodIndex(int $timestamp, array $periods): int {
		$low = 0;
		$high = count($periods) - 1;
		while ($low <= $high) {
			$middle = intdiv($low + $high, 2);
			if ($timestamp < $periods[$middle]['dateFrom']) $high = $middle - 1;
			elseif ($timestamp > $periods[$middle]['dateTo']) $low = $middle + 1;
			else return $middle;
		}
		return -1;
	}

	public function build(iterable $entries, int $year, \DateTimeZone $timezone, string $grouping, int $month = 0, bool $byQuarter = false, bool $byWeek = false, bool $byDay = false, array $periods = []): array {
		$daily = $month >= 1 && $month <= 12;
		$quarterly = !$daily && $byQuarter;
		$weekly = !$daily && !$quarterly && $byWeek;
		$dailyYear = !$daily && !$quarterly && !$weekly && $byDay;
		$buckets = $daily ? (int)(new \DateTimeImmutable('now', $timezone))->setDate($year, $month, 1)->format('t') : ($quarterly ? 4 : ($weekly ? $this->isoWeekCount($year, $timezone) : ($dailyYear ? $this->calendarDayCount($year, $timezone) : 12)));
		if ($periods) $buckets = count($periods);
		$groups = ['income' => [], 'expense' => []];
		$totals = ['income' => $this->emptyRow($buckets), 'expense' => $this->emptyRow($buckets)];
		foreach ($entries as $entry) {
			$type = (string)$entry['type'];
			if (!isset($groups[$type])) {
				continue;
			}
			$date = (new \DateTimeImmutable('@' . $entry['date']))->setTimezone($timezone);
			if ($periods) {
				$bucket = $this->periodIndex((int)$entry['date'], $periods);
			} elseif ($weekly) {
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
			$lookup = in_array($grouping, ['categories', 'paymentPartners'], true);
			$key = $lookup ? ReportLookupService::key($grouping, (int)$id, (string)($entry['group_name'] ?? ''), $parentId ? (string)($entry['parent_name'] ?? '') : '') : (string)$id;
			$rootKey = $parentId ? ReportLookupService::key($grouping, $parentId, (string)($entry['parent_name'] ?? '')) : $key;
			if (!isset($groups[$type][$rootKey])) {
				$groups[$type][$rootKey] = $this->emptyRow($buckets) + [
					'id' => $rootId, 'reportKey' => $rootKey, 'areas' => [],
					'name' => (string)($parentId ? $entry['parent_name'] : ($entry['group_name'] ?? '')),
					'icon' => $grouping === 'categories' ? (string)($entry[$parentId ? 'parent_icon' : 'group_icon'] ?? 'Shape') : null,
					'children' => [],
				];
				if ($grouping === 'labelsAndTags') {
					$groups[$type][$rootKey]['kind'] = (string)($entry['group_kind'] ?? '');
					$groups[$type][$rootKey]['filter'] = (string)($entry['group_filter'] ?? '');
				}
			}
			$root = &$groups[$type][$rootKey];
			$this->add($root, $bucket, $cents, $count);
			if (!isset($root['children'][$key])) {
				$root['children'][$key] = $this->emptyRow($buckets) + [
					'icon' => $grouping === 'categories' ? (string)($entry['group_icon'] ?? 'Shape') : null,
					'id' => $id, 'reportKey' => $key, 'name' => (string)($entry['group_name'] ?? ''), 'direct' => $key === $rootKey, 'areas' => [],
				];
			}
			$this->add($root['children'][$key], $bucket, $cents, $count);
			if ($lookup && array_key_exists('project_id', $entry)) {
				$this->addArea($root, $entry, $bucket, $buckets, $cents, $count);
				$this->addArea($root['children'][$key], $entry, $bucket, $buckets, $cents, $count);
			}
			unset($root);
		}
		foreach ($groups as &$rows) {
			foreach ($rows as &$row) {
				$row['areas'] = array_values($row['areas']);
				foreach ($row['children'] as &$child) $child['areas'] = array_values($child['areas']);
				unset($child);
				$hasChildren = count($row['children']) > 1 || !isset($row['children'][$row['reportKey']]);
				$row['children'] = $grouping === 'categories' && $hasChildren ? array_values($row['children']) : [];
				foreach ($row['areas'] as &$area) $area['includesSubcategories'] = $row['children'] !== [];
				unset($area);
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
		$flatten = fn(array $data): array => $this->comparisonRows($data);
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
			$this->attachComparisons($rows, $type, $attach);
		}
		unset($rows);
		return $report;
	}

	/** Compare the same clipped calendar slice, never the full edge month or week. */
	public function comparePeriods(array $report, array $entries, array $periods, \DateTimeImmutable $now, string $grouping, string $mode, string $breakdown): array {
		$flatten = fn(array $data): array => $this->comparisonRows($data);
		$comparisons = [];
		$keys = array_keys($flatten($report));
		$anchor = $now->setDate(2000, 1, 1)->setTime(0, 0);
		foreach ($periods as $index => $period) {
			$from = $now->setTimestamp($period['dateFrom']);
			if ($from > $now) continue;
			$to = $now->setTimestamp(min($period['dateTo'], $now->getTimestamp()));
			$previousFrom = $this->shiftComparisonDate($from, $mode, $breakdown);
			$previousTo = $this->shiftComparisonDate($to, $mode, $breakdown);
			if ($previousFrom === null || $previousTo === null) continue;
			$windows = ['current' => [], 'previous' => [], 'history' => []];
			foreach ($entries as $entry) {
				$date = (int)$entry['date'];
				foreach (['current' => [$from, $to], 'previous' => [$previousFrom, $previousTo], 'history' => [null, $previousTo]] as $key => [$start, $end]) {
					if (($start === null || $date >= $start->getTimestamp()) && $date <= $end->getTimestamp()) {
						$windows[$key][] = array_replace($entry, ['date' => $anchor->getTimestamp()]);
					}
				}
			}
			$values = [];
			foreach ($windows as $key => $rows) $values[$key] = $flatten($this->build($rows, 2000, $now->getTimezone(), $grouping));
			foreach ($keys as $key) {
				if (!($values[$mode === 'month' ? 'history' : 'previous'][$key]['totalCount'] ?? 0)) continue;
				$value = $values['current'][$key]['total'] ?? 0;
				$baseline = $values['previous'][$key]['total'] ?? 0;
				$delta = $value - $baseline;
				$comparisons[$key][$index] = [
					'current' => $value, 'previous' => $baseline, 'delta' => $delta,
					'percent' => $baseline !== 0 ? 100 * $delta / abs($baseline) : null,
					'direction' => $baseline !== 0 && abs($delta) * 100 > abs($baseline) * 5 ? ($delta > 0 ? 'up' : 'down') : null,
					'currentFrom' => $from->format('Y-m-d'), 'currentTo' => $to->format('Y-m-d'),
					'previousFrom' => $previousFrom->format('Y-m-d'), 'previousTo' => $previousTo->format('Y-m-d'),
				];
			}
		}
		$attach = static function(array &$row, string $key) use ($comparisons, $periods): void {
			$row['comparisons'] = array_replace(array_fill(0, count($periods), null), $comparisons[$key] ?? []);
		};
		foreach ($report['totals'] as $type => &$row) $attach($row, 'total-' . $type);
		unset($row);
		foreach ($report['groups'] as $type => &$rows) {
			$this->attachComparisons($rows, $type, $attach);
		}
		unset($rows);
		return $report;
	}

	private function addArea(array &$row, array $entry, int $bucket, int $buckets, int $cents, int $count): void {
		$id = (int)($entry['project_id'] ?? 0);
		if (!isset($row['areas'][$id])) $row['areas'][$id] = $this->emptyRow($buckets) + [
			'id' => $row['id'], 'reportKey' => $row['reportKey'] . ':area:' . $id,
			'areaId' => $id, 'name' => (string)($entry['project_name'] ?? ''),
			'direct' => $row['direct'] ?? false,
		];
		$this->add($row['areas'][$id], $bucket, $cents, $count);
	}

	private function comparisonRows(array $data): array {
		$result = [];
		foreach ($data['totals'] as $type => $row) $result['total-' . $type] = $row;
		$walk = function(array $rows, string $type) use (&$walk, &$result): void {
			foreach ($rows as $row) {
				$key = $type . '-' . ($row['reportKey'] ?? $row['id']);
				$result[$key . (!empty($row['direct']) ? '-direct' : '')] = $row;
				if (empty($row['children']) && empty($row['direct']) && empty($row['includesSubcategories'])) $result[$key . '-direct'] = $row;
				$walk($row['children'] ?? [], $type);
				$walk($row['areas'] ?? [], $type);
			}
		};
		foreach ($data['groups'] as $type => $rows) $walk($rows, $type);
		return $result;
	}

	private function attachComparisons(array &$rows, string $type, callable $attach): void {
		foreach ($rows as &$row) {
			$attach($row, $type . '-' . ($row['reportKey'] ?? $row['id']) . (!empty($row['direct']) ? '-direct' : ''));
			foreach (['children', 'areas'] as $field) if (isset($row[$field])) $this->attachComparisons($row[$field], $type, $attach);
		}
		unset($row);
	}

	private function shiftComparisonDate(\DateTimeImmutable $date, string $mode, string $breakdown): ?\DateTimeImmutable {
		if ($mode === 'month' && in_array($breakdown, ['week', 'day'], true)) return $date->modify($breakdown === 'week' ? '-1 week' : '-1 day');
		if ($mode === 'year' && $breakdown === 'week') {
			$year = (int)$date->format('o') - 1;
			$week = (int)$date->format('W');
			$shifted = $date->setISODate($year, $week, (int)$date->format('N'));
			return (int)$shifted->format('o') === $year && (int)$shifted->format('W') === $week ? $shifted : null;
		}
		$month = $date->modify('first day of this month')->modify($mode === 'year' ? '-12 months' : ($breakdown === 'quarter' ? '-3 months' : '-1 month'));
		$day = min((int)$date->format('j'), (int)$month->format('t'));
		if ($breakdown === 'day' && $day !== (int)$date->format('j')) return null;
		return $month->setDate((int)$month->format('Y'), (int)$month->format('n'), $day);
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

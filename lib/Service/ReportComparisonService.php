<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Comparisons for one report subject; accepts only rows already scoped by the controller. */
class ReportComparisonService {
	public function build(array $entries, array $periods, \DateTimeImmutable $now, int $cutoff, string $grouping, string $type, string $id, bool $direct, ?int $historyStart, string $breakdown, string $averageUnit = ''): array {
		$from = $now->setTimestamp($periods[0]['dateFrom']);
		$end = $now->setTimestamp($periods[count($periods) - 1]['dateTo']);
		$to = $now->setTimestamp(min($end->getTimestamp(), $cutoff));
		$unit = $this->unit($from, $end);
		$values = [];
		foreach ($entries as $entry) {
			if (!in_array($entry['type'], ['income', 'expense'], true) || ($type !== 'all' && $entry['type'] !== $type)) continue;
			if ($id === 'total') {
				if (isset($entry['counts_toward_total']) && !$entry['counts_toward_total']) continue;
			} else {
				if (isset($entry['assign_group']) && !$entry['assign_group']) continue;
				$matches = (string)($entry['group_id'] ?? 0) === $id;
				if ($grouping === 'categories' && !$direct && $id !== '0') $matches = $matches || (string)($entry['parent_id'] ?? '') === $id;
				if (!$matches) continue;
			}
			$date = (int)$entry['date'];
			$values[$date] = ($values[$date] ?? 0) + (int)$entry['amount_cents'] * ($type === 'all' && $entry['type'] === 'expense' ? -1 : 1);
		}
		ksort($values, SORT_NUMERIC);
		// Prefix sums keep reference curves bounded by rows + periods, not their product.
		$dates = array_keys($values); $prefix = [0];
		foreach ($values as $cents) $prefix[] = $prefix[count($prefix) - 1] + $cents;
		$indexBefore = static function(int $timestamp) use ($dates): int {
			$low = 0; $high = count($dates);
			while ($low < $high) { $middle = intdiv($low + $high, 2); if ($dates[$middle] < $timestamp) $low = $middle + 1; else $high = $middle; }
			return $low;
		};
		$sum = static fn(\DateTimeImmutable $start, \DateTimeImmutable $stop): int => $stop < $start ? 0 : $prefix[$indexBefore($stop->getTimestamp() + 1)] - $prefix[$indexBefore($start->getTimestamp())];
		$current = $sum($from, $to);
		$history = $historyStart === null ? null : $now->setTimestamp($historyStart)->setTime(0, 0);
		if ($history !== null && $unit === 'month') $history = $history->modify('first day of this month');
		$partial = $to < $end;
		$base = ['unit' => $unit, 'current' => $current, 'currentFrom' => $from->format('Y-m-d'), 'currentTo' => $to->format('Y-m-d'), 'partial' => $partial];
		$result = [];
		$modes = $unit === 'year' ? ['previous'] : ['previous', 'year'];
		foreach ($modes as $mode) {
			[$previousFrom, $previousEnd] = $this->previous($from, $end, $unit, $mode);
			$previousTo = $partial ? $this->elapsedEnd($from, $to, $previousFrom, $previousEnd, $unit) : $previousEnd;
			$available = $history !== null && $previousFrom !== null && $previousTo !== null && $from <= $to && $previousFrom >= $history && $previousTo <= $now;
			$referencePeriods = $previousFrom !== null && $previousFrom->format('Y-m-d') >= '1970-01-01' && $previousEnd !== null ? (new AnnualOverviewService())->periods($previousFrom->format('Y-m-d'), $previousEnd->format('Y-m-d'), $now->getTimezone(), $breakdown) : [];
			$series = [];
			foreach ($referencePeriods as $period) {
				$start = $now->setTimestamp($period['dateFrom']);
				$stop = $now->setTimestamp(min($period['dateTo'], $previousTo?->getTimestamp() ?? 0));
				$series[] = $available && $start <= $stop ? $sum($start, $stop) : null;
			}
			$result[] = $this->comparison($base + ['id' => $mode, 'kind' => 'period', 'previousFrom' => $previousFrom?->format('Y-m-d'), 'previousTo' => $previousTo?->format('Y-m-d'), 'values' => $series, 'periods' => $referencePeriods], $available ? $sum($previousFrom, $previousTo) : null);
		}
		$averageUnit = $averageUnit !== '' ? $averageUnit : ($unit === 'range' ? match ($breakdown) { 'year' => 'month', 'month' => 'day', default => $breakdown } : $unit);
		return $unit === 'year' ? $result : array_merge($result, $this->previousAverages($sum, $periods, $from, $now, $cutoff, $history, $averageUnit));
	}

	private function previousAverages(callable $sum, array $periods, \DateTimeImmutable $from, \DateTimeImmutable $now, int $cutoff, ?\DateTimeImmutable $history, string $unit): array {
		$references = match ($unit) { 'day' => ['week', 'month', 'quarter'], 'week' => ['month', 'quarter'], 'month' => ['quarter'], default => [] };
		$result = [];
		foreach ($references as $reference) {
			$anchor = match ($reference) {
				'week' => $from->modify('-' . ((int)$from->format('N') - 1) . ' days')->setTime(0, 0),
				'month' => $from->modify('first day of this month')->setTime(0, 0),
				'quarter' => $from->setDate((int)$from->format('Y'), intdiv((int)$from->format('n') - 1, 3) * 3 + 1, 1)->setTime(0, 0),
			};
			$start = $anchor->modify(str_replace('+', '-', $this->step($reference)));
			$end = $anchor->modify('-1 second');
			// Include empty buckets inside known history; never label a partial or unknown source as complete.
			if ($history === null || $end < $history || $end > $now || $end->getTimestamp() > $cutoff || $start->format('Y-m-d') < '1970-01-01') continue;
			$days = (int)$start->diff($anchor)->format('%a');
			$count = match ($unit) { 'day' => $days, 'week' => $days / 7, 'month' => 3 };
			$amount = (int)round($sum($start, $end) / $count, 0, PHP_ROUND_HALF_UP);
			$result[] = [
				'id' => 'average-previous-' . $reference, 'kind' => 'reference-average', 'available' => true,
				'unit' => $unit, 'fullAverage' => $amount, 'count' => $count,
				'referenceUnit' => $reference, 'referenceFrom' => $start->format('Y-m-d'), 'referenceTo' => $end->format('Y-m-d'),
				'referenceYear' => (int)$start->format($reference === 'week' ? 'o' : 'Y'),
				'referenceNumber' => match ($reference) { 'quarter' => intdiv((int)$start->format('n') - 1, 3) + 1, 'week' => (int)$start->format('W'), default => (int)$start->format('n') },
				'periods' => $periods,
				'values' => array_map(static fn(array $period): ?int => $period['dateFrom'] <= $cutoff ? $amount : null, $periods),
			];
		}
		return $result;
	}

	private function comparison(array $value, ?int $previous): array {
		$delta = $previous === null ? null : $value['current'] - $previous;
		return $value + ['available' => $previous !== null, 'previous' => $previous, 'delta' => $delta, 'percent' => $previous ? 100 * $delta / abs($previous) : null, 'direction' => $previous && abs($delta) * 100 > abs($previous) * 5 ? ($delta > 0 ? 'up' : 'down') : null];
	}

	private function unit(\DateTimeImmutable $from, \DateTimeImmutable $to): string {
		$next = $to->modify('+1 second');
		if ($from->format('m-d H:i:s') === '01-01 00:00:00' && $next == $from->modify('+1 year')) return 'year';
		if ($from->format('d H:i:s') === '01 00:00:00' && $next == $from->modify('+1 month')) return 'month';
		if ($from->format('d H:i:s') === '01 00:00:00' && ((int)$from->format('n') - 1) % 3 === 0 && $next == $from->modify('+3 months')) return 'quarter';
		if ($from->format('N H:i:s') === '1 00:00:00' && $next == $from->modify('+1 week')) return 'week';
		if ($from->format('H:i:s') === '00:00:00' && $next == $from->modify('+1 day')) return 'day';
		return 'range';
	}

	private function step(string $unit): string {
		return match ($unit) { 'year' => '+1 year', 'month' => '+1 month', 'quarter' => '+3 months', 'week' => '+1 week', default => '+1 day' };
	}

	private function previous(\DateTimeImmutable $from, \DateTimeImmutable $end, string $unit, string $mode): array {
		if ($mode === 'previous' && $unit === 'range') {
			$days = (int)$from->diff($end->setTime(0, 0))->format('%a') + 1;
			return [$from->modify('-' . $days . ' days'), $from->modify('-1 second')];
		}
		if ($mode === 'year') {
			if ($unit === 'week') {
				$start = $from->setISODate((int)$from->format('o') - 1, (int)$from->format('W'), 1);
				if ($start->format('W') !== $from->format('W')) return [null, null];
			} else {
				$start = $from->modify('-1 year');
				if ($start->format('m-d') !== $from->format('m-d')) return [null, null];
			}
		} else $start = $from->modify(str_replace('+', '-', $this->step($unit)));
		if ($unit === 'range') {
			$stop = $end->modify('-1 year');
			return $stop->format('m-d') === $end->format('m-d') ? [$start, $stop] : [null, null];
		}
		return [$start, $start->modify($this->step($unit))->modify('-1 second')];
	}

	private function elapsedEnd(\DateTimeImmutable $from, \DateTimeImmutable $to, ?\DateTimeImmutable $reference, ?\DateTimeImmutable $end, string $unit): ?\DateTimeImmutable {
		if ($reference === null || $end === null) return null;
		if ($unit === 'year') {
			$month = $reference->setDate((int)$reference->format('Y'), (int)$to->format('n'), 1);
			$day = min((int)$to->format('j'), (int)$month->format('t'));
			return $month->setDate((int)$month->format('Y'), (int)$month->format('n'), $day)->setTime((int)$to->format('H'), (int)$to->format('i'), (int)$to->format('s'));
		}
		$days = (int)$from->diff($to->setTime(0, 0))->format('%a');
		return min($end, $reference->modify('+' . $days . ' days')->setTime((int)$to->format('H'), (int)$to->format('i'), (int)$to->format('s')));
	}

}

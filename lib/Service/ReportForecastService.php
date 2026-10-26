<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Read-only forecast of an authorized report subject, in integer cents. */
class ReportForecastService {
	public function build(iterable $rows, int $from, int $to, \DateTimeImmutable $now, string $grouping, string $type, string $id, bool $direct, array $labels, array $periods = []): array {
		$timestamp = $now->getTimestamp();
		if ($to < $timestamp) return ['status' => 'past'];
		$start = $now->setTimestamp($from)->setTime(0, 0);
		$last = $now->setTimestamp($to)->setTime(0, 0);
		$today = $now->setTime(0, 0);
		$future = $from > $timestamp;
		$elapsedDays = $future ? 0 : (int)$start->diff($today)->days + 1;
		$remainingDays = $future ? (int)$start->diff($last)->days + 1 : (int)$today->diff($last)->days;
		$actual = ['incomeCents' => 0, 'expenseCents' => 0];
		$planned = $actual;
		$variable = [];
		$plannedByCategory = [];
		$variableCount = 0;
		$historyStart = $now->modify('-5 years')->getTimestamp();
		$payments = (new ReportBreakdownService())->payments($rows, min($from, $historyStart), $to, $grouping, $type, $id, $direct, $labels);
		$periods = $periods ?: [['dateFrom' => $from, 'dateTo' => $to]];
		$chart = ['periods' => $periods];
		foreach (['actual', 'planned', 'estimated'] as $component) {
			$chart[$component] = ['income' => array_fill(0, count($periods), 0), 'expense' => array_fill(0, count($periods), 0)];
		}
		$add = function(string $component, array $payment) use (&$chart, $periods): void {
			$timestamp = (int)$payment['date'];
			$left = 0; $right = count($periods) - 1;
			while ($left <= $right) {
				$middle = intdiv($left + $right, 2);
				if ($timestamp < $periods[$middle]['dateFrom']) $right = $middle - 1;
				elseif ($timestamp > $periods[$middle]['dateTo']) $left = $middle + 1;
				else { $chart[$component][$payment['type']][$middle] += (int)$payment['amount_cents']; return; }
			}
		};
		foreach ($payments as $payment) {
			if ((int)$payment['date'] < $from) continue;
			$cents = (int)$payment['amount_cents'];
			$key = $payment['type'] . 'Cents';
			$category = (int)($payment['category_id'] ?? 0);
			if ((int)$payment['date'] > $timestamp) {
				$planned[$key] += $cents;
				$add('planned', $payment);
				if ($payment['type'] === 'expense') $plannedByCategory[$category] = ($plannedByCategory[$category] ?? 0) + $cents;
			} else {
				$actual[$key] += $cents;
				$add('actual', $payment);
				if ($payment['type'] === 'expense' && !$this->fixed($payment)) {
					$variable[$category] = ($variable[$category] ?? 0) + $cents;
					$variableCount++;
				}
			}
		}
		$available = !$future && $remainingDays > 0 && $variableCount > 0;
		$estimated = 0;
		if ($available) {
			foreach ($variable as $category => $cents) {
				// Known future spending in this category already covers part of its
				// expected remainder. It cannot reduce another category's estimate.
				$expected = (int)round($cents * $remainingDays / $elapsedDays);
				$estimated += max(0, $expected - ($plannedByCategory[$category] ?? 0));
			}
		}
		$expenseAvailable = $available;
		$income = !$future && $type !== 'expense' ? (new ReportIncomePatternService())->project($payments, $now, $to) : ['patterns' => 0, 'payments' => []];
		$estimatedIncome = 0;
		foreach ($income['payments'] as $payment) {
			if ((int)$payment['date'] < $from) continue;
			$estimatedIncome += (int)$payment['amount_cents'];
			$add('estimated', $payment);
		}
		// Distribute only the additional expense estimate, preserving every cent.
		// Prefix allocation over local days handles clipped weeks, leap days and DST.
		if ($expenseAvailable) {
			$first = $today->modify('+1 day');
			$base = intdiv($estimated, $remainingDays); $residual = $estimated % $remainingDays;
			foreach ($periods as $index => $period) {
				$bucketStart = max($first, $now->setTimestamp($period['dateFrom'])->setTime(0, 0));
				$bucketEnd = min($last, $now->setTimestamp($period['dateTo'])->setTime(0, 0));
				if ($bucketStart > $bucketEnd) continue;
				$before = (int)$first->diff($bucketStart)->days;
				$after = (int)$first->diff($bucketEnd)->days + 1;
				$chart['estimated']['expense'][$index] = $base * ($after - $before) + min($residual, $after) - min($residual, $before);
			}
		}
		foreach (['actual', 'planned', 'estimated'] as $component) {
			$chart[$component]['balance'] = array_map(static fn(int $income, int $expense): int => $income - $expense, $chart[$component]['income'], $chart[$component]['expense']);
		}
		$available = $expenseAvailable || $income['patterns'] > 0;
		$forecast = ['incomeCents' => $actual['incomeCents'] + $planned['incomeCents'] + $estimatedIncome, 'expenseCents' => $actual['expenseCents'] + $planned['expenseCents'] + $estimated];
		$balance = static fn(array $values): array => $values + ['balanceCents' => $values['incomeCents'] - $values['expenseCents']];
		return [
			'status' => $future ? 'future' : 'current',
			'dateFrom' => $from, 'dateTo' => $to, 'asOf' => $timestamp,
			'elapsedDays' => $elapsedDays, 'remainingDays' => $remainingDays,
			'variableCount' => $variableCount, 'estimateAvailable' => $available,
			'expenseEstimateAvailable' => $expenseAvailable, 'incomePatternCount' => $income['patterns'], 'chart' => $chart,
			'actual' => $balance($actual), 'planned' => $balance($planned),
			'estimated' => $available ? $balance(['incomeCents' => $estimatedIncome, 'expenseCents' => $estimated]) : null,
			'forecast' => $balance($forecast),
		];
	}

	private function fixed(array $payment): bool {
		if (in_array($payment['is_fixed_cost'] ?? false, [true, 1, '1', 't', 'true'], true)) return true;
		return ReportIncomePatternService::recurring($payment);
	}
}

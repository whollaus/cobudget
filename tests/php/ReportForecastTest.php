<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\ReportForecastService;

require_once dirname(__DIR__, 2) . '/lib/Service/ReportBreakdownService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportIncomePatternService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportForecastService.php';

$zone = new DateTimeZone('Europe/Vienna');
$date = static fn(string $value): DateTimeImmutable => new DateTimeImmutable($value, $zone);
$payment = static fn(int|string $id, string $when, int $cents, array $extra = []): array => array_replace([
	'id' => $id, 'date' => $date($when)->getTimestamp(), 'amount_cents' => $cents, 'type' => 'expense', 'category_id' => 1,
], $extra);
$build = static fn(array $rows, array $extra = []): array => (new ReportForecastService())->build(...array_replace([
	'rows' => $rows, 'from' => $date('2026-10-01')->getTimestamp(), 'to' => $date('2026-10-31 23:59:59')->getTimestamp(),
	'now' => $date('2026-10-10 12:00'), 'grouping' => 'categories', 'type' => 'all', 'id' => 'total', 'direct' => false,
	'labels' => ['important' => ['column' => 'is_important', 'name' => 'Important']],
], $extra));

return [
	'Income forecasts require four matching recent positions and respect plans, changes and interrupted patterns' => function(TestRunner $t) use ($build, $payment): void {
		$salary = static fn(array $extra = []): array => array_replace(['type' => 'income', 'partner_id' => 10, 'project_id' => 4], $extra);
		$rows = [];
		foreach (['2026-06-25', '2026-07-25', '2026-08-25', '2026-09-25'] as $index => $when) $rows[] = $payment($index + 1, $when, 300001, $salary());
		$result = $build($rows);
		$t->assertSame(1, $result['incomePatternCount'], 'Four earlier months provide a basis outside the opened month');
		$t->assertSame(0, $result['actual']['incomeCents'], 'Income history never enters actual amounts of the selected month');
		$t->assertSame(300001, $result['estimated']['incomeCents'], 'An eligible position is projected once at its next monthly date');
		$t->assertSame(300001, $result['forecast']['incomeCents'], 'Expected income includes the additional recognized amount');
		$t->assertSame(300001, array_sum($result['chart']['estimated']['income']), 'Chart and income box share exactly the same cents');
		$t->assertSame(null, $build(array_slice($rows, 0, 3))['estimated'], 'Three equal payments are not enough');
		foreach ([['partner_id' => 11], ['category_id' => 2], ['project_id' => 5], ['amount_cents' => 299999]] as $change) {
			$changed = $rows; $changed[3] = array_replace($changed[3], $change);
			$t->assertSame(0, $build($changed)['incomePatternCount'], 'Do not mix different identities or exact-cent amounts');
		}
		$planned = [...$rows, $payment(8, '2026-10-24', 310001, $salary())];
		$t->assertSame(0, $build($planned)['estimated']['incomeCents'], 'A known future payment, even at a changed amount/date, supersedes the inferred occurrence');
		$t->assertSame(310001, $build($planned)['forecast']['incomeCents'], 'The known amount takes precedence without double counting');
		$changed = [...$rows, $payment(9, '2026-10-01', 310001, $salary())];
		$t->assertSame(0, $build($changed)['incomePatternCount'], 'A newer changed amount stops an old pattern');
		$t->assertSame(0, $build([...array_slice($rows, 0, 3), $payment(4, '2026-09-02', 300001, $salary())])['incomePatternCount'], 'Irregular dates are not extrapolated');
		$stale = array_map(static fn(array $row): array => array_replace($row, ['date' => $row['date'] - 365 * 86400]), $rows);
		$t->assertSame(0, $build($stale)['incomePatternCount'], 'Long-interrupted monthly income is not resurrected');
		$recurring = $rows; $recurring[3]['source_recurrence_series_id'] = 8;
		$t->assertSame(0, $build($recurring)['incomePatternCount'], 'Linked personal series use known recurrence plans rather than a second estimate');
	},
	'Income patterns handle weekly payments, month ends, leap years and genuine zero income' => function(TestRunner $t) use ($build, $payment, $date): void {
		$rows = [];
		foreach (['2026-09-17', '2026-09-24', '2026-10-01', '2026-10-08'] as $index => $when) $rows[] = $payment($index + 1, $when, 10001, ['type' => 'income']);
		$weekly = $build($rows);
		$t->assertSame(30003, $weekly['estimated']['incomeCents'], 'The next three weekly occurrences are placed inside the selected month');
		$t->assertSame(50005, $weekly['forecast']['incomeCents'], 'Only the two October actuals enter the selected-period total');
		$monthEnds = [];
		foreach (['2023-10-31', '2023-11-30', '2023-12-31', '2024-01-31'] as $index => $when) $monthEnds[] = $payment($index + 1, $when, 101, ['type' => 'income']);
		$leap = $build($monthEnds, ['from' => $date('2024-02-01')->getTimestamp(), 'to' => $date('2024-02-29 23:59:59')->getTimestamp(), 'now' => $date('2024-02-10')]);
		$t->assertSame(101, $leap['estimated']['incomeCents'], 'A month-end salary clamps to leap-day February rather than overflowing into March');
		$zeroRows = array_map(static fn(array $row): array => array_replace($row, ['amount_cents' => 0]), $rows);
		$t->assertSame(1, $build($zeroRows)['incomePatternCount'], 'Zero-valued observed positions retain a valid pattern');
		$t->assertSame(0, $build($zeroRows)['estimated']['incomeCents'], 'Valid zero income remains zero');
		$duplicates = array_map(static fn(array $row): array => array_replace($row, ['date' => $date('2026-10-01')->getTimestamp()]), $rows);
		$t->assertSame(0, $build($duplicates)['incomePatternCount'], 'Four same-day entries do not imply a recurring rhythm');
	},
	'Forecast charts reconcile all components across clipped buckets, calendar days and exact-cent remainder allocation' => function(TestRunner $t) use ($build, $payment, $date): void {
		$periods = [];
		for ($day = 1; $day <= 31; $day++) {
			$start = $date(sprintf('2026-10-%02d', $day));
			$periods[] = ['dateFrom' => $start->getTimestamp(), 'dateTo' => $start->modify('+1 day')->getTimestamp() - 1];
		}
		$result = $build([$payment(1, '2026-10-03', 101), $payment(2, '2026-10-20', 50)], ['periods' => $periods]);
		$t->assertSame(162, $result['estimated']['expenseCents'], 'The category remainder is rounded only once: 101/10*21 minus 50');
		$t->assertSame(162, array_sum($result['chart']['estimated']['expense']), 'Allocation never loses a residual cent');
		$t->assertSame(array_fill(0, 10, 0), array_slice($result['chart']['estimated']['expense'], 0, 10), 'Estimates do not leak into today or earlier days');
		foreach (['actual', 'planned', 'estimated'] as $component) {
			foreach (['income', 'expense', 'balance'] as $type) {
				$t->assertSame($result[$component][$type . 'Cents'], array_sum($result['chart'][$component][$type]), 'All chart buckets reconcile to ' . $component . ':' . $type);
			}
		}
		$t->assertSame($result['forecast']['expenseCents'], array_sum($result['chart']['actual']['expense']) + array_sum($result['chart']['planned']['expense']) + array_sum($result['chart']['estimated']['expense']), 'The final chart sum equals the period-end box and table');
	},
	'Period forecasts keep actual, known and additional spending separate without multiplying salary or fixed costs' => function(TestRunner $t) use ($build, $payment): void {
		$rows = [
			$payment(1, '2026-10-01', 300000, ['type' => 'income']),
			$payment(2, '2026-10-01', 80000, ['is_fixed_cost' => 't']),
			$payment(3, '2026-10-03', 10001),
			$payment(4, '2026-10-02', 5000, ['recurrence_series_id' => 8]),
			$payment(5, '2026-10-02', 4000, ['source_recurrence_interval' => 'month']),
			$payment(6, '2026-10-20', 6000),
			$payment(7, '2026-10-20', 2500, ['category_id' => 2]),
			$payment('projected:8:1', '2026-10-30', 90000, ['type' => 'income']),
		];
		$result = $build($rows);
		$t->assertSame(10, $result['elapsedDays'], 'Empty days and the current calendar day belong to the denominator');
		$t->assertSame(21, $result['remainingDays'], 'Only calendar days after today remain');
		$t->assertSame(['incomeCents' => 300000, 'expenseCents' => 99001, 'balanceCents' => 200999], $result['actual'], 'Saved actual amounts stay unchanged');
		$t->assertSame(['incomeCents' => 90000, 'expenseCents' => 8500, 'balanceCents' => 81500], $result['planned'], 'Future saved and recurring amounts are known plans');
		$t->assertSame(1, $result['variableCount'], 'Fixed costs and historical/personal recurring rows are not extrapolated');
		$t->assertSame(15002, $result['estimated']['expenseCents'], 'Round the category remainder in cents, then offset known spending in that category only');
		$t->assertSame(['incomeCents' => 390000, 'expenseCents' => 122503, 'balanceCents' => 267497], $result['forecast'], 'Income is never multiplied by elapsed time and the final decomposition reconciles');
		$rows[] = $payment(9, '2026-10-25', 999999, ['category_id' => 1]);
		$t->assertSame(0, $build($rows)['estimated']['expenseCents'], 'Known plans above expected consumption do not produce negative estimates');
	},
	'Forecast subjects and overlapping tags retain one exact payment population' => function(TestRunner $t) use ($build, $payment): void {
		$rows = [
			$payment(1, '2026-10-03', 1000, ['category_id' => 2, 'parent_id' => 1, 'hashtag_id' => 5, 'hashtag_name' => 'Trip', 'is_important' => 1]),
			$payment(1, '2026-10-03', 1000, ['category_id' => 2, 'parent_id' => 1, 'hashtag_id' => 6, 'hashtag_name' => 'Other', 'is_important' => 1]),
			$payment(2, '2026-10-04', 2000, ['partner_id' => 7]),
			$payment(3, '2026-10-20', 100, ['category_id' => 2, 'parent_id' => 1, 'hashtags' => [['id' => 5, 'name' => 'Trip']]]),
		];
		foreach ([['categories', '1', false, 3000, 100], ['categories', '1', true, 2000, 0], ['categories', '2', false, 1000, 100], ['paymentPartners', '7', false, 2000, 0], ['labelsAndTags', 'total', false, 1000, 100], ['labelsAndTags', 'tag:5', false, 1000, 100], ['labelsAndTags', 'label:important', false, 1000, 0]] as [$grouping, $id, $direct, $actual, $planned]) {
			$result = $build($rows, compact('grouping', 'id', 'direct'));
			$t->assertSame($actual, $result['actual']['expenseCents'], 'Unique actual cents for ' . $grouping . ':' . $id);
			$t->assertSame($planned, $result['planned']['expenseCents'], 'Unique planned cents for ' . $grouping . ':' . $id);
		}
	},
	'Forecasts distinguish missing estimates, valid zero spending, future plans and finished periods' => function(TestRunner $t) use ($build, $payment, $date): void {
		$empty = $build([]);
		$t->assertSame(false, $empty['estimateAvailable'], 'No variable payment means no consumption baseline');
		$t->assertSame(null, $empty['estimated'], 'Unknown spending is not returned as a zero estimate');
		$fixed = $build([$payment(1, '2026-10-01', 999, ['is_fixed_cost' => true])]);
		$t->assertSame(null, $fixed['estimated'], 'A fixed-only population has no variable-spending estimate');
		$zero = $build([$payment(1, '2026-10-01', 0)]);
		$t->assertSame(true, $zero['estimateAvailable'], 'An observed zero is different from absent data');
		$t->assertSame(0, $zero['estimated']['expenseCents'], 'A genuine zero baseline stays zero');
		$future = $build([$payment(1, '2026-10-20', 500)], ['now' => $date('2026-09-30 23:59:59')]);
		$t->assertSame('future', $future['status'], 'A completely future range shows plans only');
		$t->assertSame(null, $future['estimated'], 'No pace is invented before a period begins');
		$t->assertSame(500, $future['forecast']['expenseCents'], 'Known future amounts remain useful');
		$t->assertSame(31, $future['remainingDays'], 'A future month includes all its calendar days');
		$t->assertSame(['status' => 'past'], $build([], ['now' => $date('2026-11-01')]), 'Finished ranges have no forecast');
		$lastDay = $build([$payment(1, '2026-10-01', 500)], ['now' => $date('2026-10-31 12:00')]);
		$t->assertSame(0, $lastDay['remainingDays'], 'The current day is not counted a second time');
		$t->assertSame(null, $lastDay['estimated'], 'No additional whole days remain at the end');
	},
	'Forecast day counts are stable across daylight saving, leap days, weeks and custom trips' => function(TestRunner $t) use ($build, $payment, $date): void {
		foreach ([['2026-03-27', '2026-04-05', '2026-03-31 12:00', 5, 5], ['2026-10-23', '2026-11-01', '2026-10-27 12:00', 5, 5], ['2024-02-26', '2024-03-03', '2024-02-29 12:00', 4, 3], ['2026-10-19', '2026-10-25', '2026-10-21 12:00', 3, 4]] as [$from, $to, $now, $elapsed, $remaining]) {
			$result = $build([$payment(1, $from, 1001)], ['from' => $date($from)->getTimestamp(), 'to' => $date($to . ' 23:59:59')->getTimestamp(), 'now' => $date($now)]);
			$t->assertSame($elapsed, $result['elapsedDays'], 'Elapsed days use the user’s calendar, not 86400-second intervals');
			$t->assertSame($remaining, $result['remainingDays'], 'Remaining days retain leap days and both DST boundaries');
			$t->assertSame((int)round(1001 * $remaining / $elapsed), $result['estimated']['expenseCents'], 'Sparse payments still give a transparent pace including empty days');
		}
	},
	'Forecast cents exclude out-of-range data and respect expense or income subjects' => function(TestRunner $t) use ($build, $payment): void {
		$rows = [$payment(1, '2026-09-30', 999999), $payment(2, '2026-11-01', 888888), $payment(3, '2026-10-02', 101), $payment(4, '2026-10-02', 200, ['type' => 'income'])];
		$expense = $build($rows, ['type' => 'expense']);
		$t->assertSame(101, $expense['actual']['expenseCents'], 'Exact inclusive dates exclude neighbouring periods');
		$t->assertSame(0, $expense['actual']['incomeCents'], 'Expense details cannot import income');
		$income = $build($rows, ['type' => 'income']);
		$t->assertSame(200, $income['forecast']['incomeCents'], 'An income detail has known income only');
		$t->assertSame(0, $income['forecast']['expenseCents'], 'Income details cannot import expenses');
		$t->assertSame(null, $income['estimated'], 'Income is never statistically extrapolated');
	},
];

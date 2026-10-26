<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;
use OCA\CoBudget\Service\ReportComparisonService;

require_once dirname(__DIR__, 2) . '/lib/Service/AnnualOverviewService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportComparisonService.php';

$zone = new DateTimeZone('Europe/Vienna');
$date = static fn(string $value): DateTimeImmutable => new DateTimeImmutable($value, $zone);
$entry = static fn(string $day, int $amount, array $extra = []): array => $extra + ['date' => $date($day)->getTimestamp(), 'type' => 'expense', 'amount_cents' => $amount, 'group_id' => 1];
$compare = static function(array $entries, string $from, string $to, string $now = '2026-11-10 12:00', array $options = []) use ($date, $zone): array {
	$periods = (new AnnualOverviewService())->periods($from, $to, $zone, $options['breakdown'] ?? 'day');
	$end = $date($to . ' 23:59:59')->getTimestamp();
	$rows = (new ReportComparisonService())->build($entries, $periods, $date($now), $options['cutoff'] ?? min($end, $date($now)->getTimestamp()), $options['grouping'] ?? 'categories', $options['type'] ?? 'expense', $options['id'] ?? 'total', $options['direct'] ?? false, array_key_exists('history', $options) ? $options['history'] : $date('2025-01-01')->getTimestamp(), $options['breakdown'] ?? 'day', $options['averageUnit'] ?? '');
	return array_column($rows, null, 'id');
};

return [
	'Month detail totals combine previous periods with the monthly mean of the previous quarter' => function(TestRunner $t) use ($compare, $entry): void {
		$entries = [$entry('2025-10-10', 800), $entry('2026-01-10', 100), $entry('2026-09-10', 900), $entry('2026-10-10', 1000), $entry('2026-11-01', 99000), $entry('2026-12-01', 99000)];
		$values = $compare($entries, '2026-10-01', '2026-10-31');
		$t->assertSame(['previous', 'year', 'average-previous-quarter'], array_keys($values), 'The API includes a monthly benchmark for the full previous quarter');
		foreach (array_filter($values, static fn(array $value): bool => $value['kind'] === 'period') as $value) { $t->assertSame(1000, $value['current'], 'Every period row uses the same detail total'); $t->assertSame($value['previous'], array_sum($value['values']), 'Curve and metric have the same end value'); }
		$t->assertSame(300, $values['average-previous-quarter']['fullAverage'], 'Quarter total divided by all three months, including empty months');
		$t->assertSame(900, $values['previous']['previous'], 'Expense totals compare September');
		$t->assertSame(800, $values['year']['previous'], 'Expense totals compare October last year');
	},
	'All report subjects preserve parent, direct, unassigned, label and tag semantics' => function(TestRunner $t) use ($compare, $entry): void {
		$entries = [$entry('2026-09-01', 101), $entry('2026-09-02', 202, ['group_id' => 2, 'parent_id' => 1]), $entry('2026-09-03', 303, ['group_id' => 0]), $entry('2026-09-04', 800, ['type' => 'income'])];
		foreach ([['total', false, 606], ['1', false, 303], ['1', true, 101], ['2', false, 202], ['0', false, 303]] as [$id, $direct, $expected]) {
			$t->assertSame($expected, $compare($entries, '2026-10-01', '2026-10-31', options: compact('id', 'direct'))['previous']['previous'], 'Resolve exactly the selected subject ' . $id);
		}
		$t->assertSame(800, $compare($entries, '2026-10-01', '2026-10-31', options: ['type' => 'income'])['previous']['previous'], 'Income totals supported');
		$t->assertSame(194, $compare($entries, '2026-10-01', '2026-10-31', options: ['type' => 'all'])['previous']['previous'], 'Balance uses income minus expenses');
		$labels = [$entry('2026-09-01', 101, ['assign_group' => false]), $entry('2026-09-01', 101, ['counts_toward_total' => false, 'group_id' => 'label:important']), $entry('2026-09-01', 101, ['counts_toward_total' => false, 'group_id' => 'tag:9'])];
		foreach (['total', 'label:important', 'tag:9'] as $id) $t->assertSame(101, $compare($labels, '2026-10-01', '2026-10-31', options: ['grouping' => 'labelsAndTags', 'id' => $id])['previous']['previous'], 'Labels and tags never multiply totals');
	},
	'Reference gaps are zero only inside known scope history and zero has no percentage' => function(TestRunner $t) use ($compare, $entry, $date): void {
		$zero = $compare([$entry('2026-10-01', 101)], '2026-10-01', '2026-10-31');
		$t->assertSame(0, $zero['previous']['previous'], 'An empty month in existing history is zero');
		$t->assertSame(null, $zero['previous']['percent'], 'No division by zero');
		$t->assertSame(101, $zero['previous']['delta'], 'Difference still meaningful');
		$unknown = $compare([], '2026-10-01', '2026-10-31', options: ['history' => $date('2026-10-02')->getTimestamp()]);
		$t->assertSame(false, $unknown['previous']['available'], 'Do not invent zero before history starts');
		$empty = $compare([], '2026-10-01', '2026-10-31', options: ['history' => null]);
		foreach ($empty as $value) $t->assertSame(false, $value['available'], 'No data is explicit for an empty scope');
	},
	'Month lengths and leap days retain genuine reference dates and exact curve totals' => function(TestRunner $t) use ($compare, $entry): void {
		$short = $compare([$entry('2026-01-31', 101)], '2026-02-01', '2026-02-28');
		$t->assertSame(31, count($short['previous']['values']), 'The reference retains its 31st day');
		$t->assertSame(101, $short['previous']['values'][30], 'The extra day is not discarded or moved');
		$long = $compare([$entry('2026-09-30', 101)], '2026-10-01', '2026-10-31');
		$t->assertSame(30, count($long['previous']['values']), 'There is no invented September 31');
		$leap = $compare([], '2024-02-01', '2024-02-29', options: ['history' => strtotime('2020-01-01')]);
		$t->assertSame('2023-02-28', $leap['year']['previousTo'], 'Last year uses the real February end');
		$day = $compare([], '2024-02-29', '2024-02-29', options: ['history' => strtotime('2020-01-01')]);
		$t->assertSame(false, $day['year']['available'], 'A single leap day has no invented annual counterpart');
	},
	'Open and full periods retain matching reference dates and saved future amounts' => function(TestRunner $t) use ($compare, $entry): void {
		$result = $compare([$entry('2026-01-10', 3100), $entry('2026-02-19 11:00', 3100), $entry('2026-02-19 13:00', 90000), $entry('2026-03-10', 1200), $entry('2026-03-25', 8000)], '2026-03-01', '2026-03-31', '2026-03-19 12:00');
		$t->assertSame(3100, $result['previous']['previous'], 'The previous reference also stops at noon');
		$t->assertSame('2026-02-19', $result['previous']['previousTo'], 'Actual cutoff is visible');
		$t->assertSame(1200, $result['previous']['current'], 'Future payments stay out while payments up to today is active');
		$full = $compare([$entry('2026-02-19 11:00', 3100), $entry('2026-02-19 13:00', 90000), $entry('2026-03-10', 1200), $entry('2026-03-25', 8000)], '2026-03-01', '2026-03-31', '2026-03-19 12:00', ['cutoff' => strtotime('2026-03-31 23:59:59 Europe/Vienna')]);
		$t->assertSame(9200, $full['previous']['current'], 'The full selected period includes its known future payments');
		$t->assertSame(93100, $full['previous']['previous'], 'A full month uses its full reference month');
		$t->assertSame('2026-02-28', $full['previous']['previousTo'], 'Full reference dates remain precise');

	},
	'Whole years and free ranges offer suitable references without duplicate year options' => function(TestRunner $t) use ($compare, $entry, $date): void {
		$year = $compare([$entry('2024-04-01', 100), $entry('2025-05-01', 300)], '2026-01-01', '2026-12-31', options: ['history' => $date('2024-01-01')->getTimestamp(), 'breakdown' => 'year']);
		$t->assertSame(['previous'], array_keys($year), 'Year details have a single prior-year reference, without extra historical means');
		$trip = $compare([$entry('2026-03-20', 100)], '2026-03-25', '2026-04-03');
		$t->assertSame(['previous', 'year', 'average-previous-week', 'average-previous-month', 'average-previous-quarter'], array_keys($trip), 'A trip retains its direct comparisons and offers daily means of larger periods');
		$t->assertSame('2026-03-15', $trip['previous']['previousFrom'], 'Previous ten calendar days');
		$t->assertSame('2026-03-24', $trip['previous']['previousTo'], 'Periods do not overlap across DST');
	},
	'Period references distinguish known empty history from real zero-value balance payments' => function(TestRunner $t) use ($compare, $entry, $date): void {
		$rows = [$entry('2024-04-01', 99999, ['group_id' => 2]), $entry('2026-08-10', 32400)];
		$result = $compare($rows, '2026-01-01', '2026-12-31', '2026-10-22 12:00', ['id' => '1', 'history' => $date('2024-01-01')->getTimestamp(), 'breakdown' => 'year']);
		$t->assertSame(['previous'], array_keys($result), 'Historical data from any category cannot create additional average variants');
		$t->assertSame(0, $result['previous']['previous'], 'A known empty previous period stays a valid zero baseline');
		$zero = $compare([$entry('2025-03-01', 0)], '2026-01-01', '2026-12-31', options: ['history' => $date('2025-01-01')->getTimestamp(), 'breakdown' => 'year']);
		$t->assertSame(true, $zero['previous']['available'], 'A real zero-value reference remains available');
		$t->assertSame(0, $zero['previous']['previous'], 'Zero is not absence');
		$balance = $compare([$entry('2025-04-01', 100), $entry('2025-04-01', 100, ['type' => 'income'])], '2026-01-01', '2026-12-31', options: ['type' => 'all', 'history' => $date('2025-01-01')->getTimestamp(), 'breakdown' => 'year']);
		$t->assertSame(true, $balance['previous']['available'], 'Equal income and expense form a valid zero balance reference');
		$t->assertSame(0, $balance['previous']['previous'], 'Balance preserves cancelling amounts');
	},
	'Weekly details compare with seven-day means from the complete previous month and quarter' => function(TestRunner $t) use ($compare, $entry, $date): void {
		$rows = [$entry('2026-07-10', 62000), $entry('2026-09-10', 30000), $entry('2026-10-20', 1234)];
		$result = $compare($rows, '2026-10-19', '2026-10-25', '2026-10-22 12:00');
		foreach (['month', 'quarter'] as $reference) {
			$value = $result['average-previous-' . $reference];
			$t->assertSame('week', $value['unit'], 'The opened week determines the mean even in a daily chart');
			$t->assertSame(7000, $value['fullAverage'], 'A week is seven days, not one quarter of a month');
			$t->assertSame([7000, 7000, 7000, 7000, null, null, null], $value['values'], 'The full weekly benchmark stays constant through the cutoff');
			$t->assertSame(2026, $value['referenceYear'], 'Reference labels use the evaluated year');
		}
		$t->assertSame('2026-09-01', $result['average-previous-month']['referenceFrom'], 'Previous month starts at its calendar boundary');
		$t->assertSame('2026-09-30', $result['average-previous-month']['referenceTo'], 'The full month is used even for an ongoing week');
		$t->assertSame('2026-07-01', $result['average-previous-quarter']['referenceFrom'], 'Previous quarter includes all three months');
		$t->assertSame('2026-09-30', $result['average-previous-quarter']['referenceTo'], 'Quarter end remains precise');
		$clipped = $compare($rows, '2026-10-01', '2026-10-04', options: ['averageUnit' => 'week']);
		$t->assertSame(7000, $clipped['average-previous-month']['fullAverage'], 'A clipped weekly column retains its original comparison unit');
		$sparse = $compare([$entry('2026-09-10', 30000)], '2026-10-19', '2026-10-25', options: ['history' => $date('2026-09-10')->getTimestamp()]);
		$t->assertSame(7000, $sparse['average-previous-month']['fullAverage'], 'History beginning inside the month still includes its empty days');
	},
	'Daily benchmarks identify the previous ISO week and use the selected calendar context across years' => function(TestRunner $t) use ($compare, $entry): void {
		$rows = [$entry('2026-07-10', 6200), $entry('2026-09-10', 3000), $entry('2026-10-12', 700)];
		$result = $compare($rows, '2026-10-20', '2026-10-20');
		foreach (['week', 'month', 'quarter'] as $reference) $t->assertSame(100, $result['average-previous-' . $reference]['fullAverage'], 'Daily means use every calendar day in their own reference');
		$t->assertSame(42, $result['average-previous-week']['referenceNumber'], 'Previous ISO week number is explicit');
		$t->assertSame('2026-10-12', $result['average-previous-week']['referenceFrom'], 'Previous week begins on Monday');
		$past = $compare([$entry('2020-12-21', 700)], '2021-01-01', '2021-01-01', options: ['history' => strtotime('2020-01-01 Europe/Vienna')]);
		$t->assertSame(2020, $past['average-previous-week']['referenceYear'], 'Week references use their ISO year rather than the current year');
		$t->assertSame('2020-12-21', $past['average-previous-week']['referenceFrom'], 'A year boundary keeps the previous calendar week');
		$t->assertSame('2020-12-01', $past['average-previous-month']['referenceFrom'], 'Previous month crosses the year boundary');
		$t->assertSame(4, $past['average-previous-quarter']['referenceNumber'], 'Previous quarter is Q4 of the previous year');
	},
	'Weekly reference means use calendar days through leap February and both daylight-saving transitions' => function(TestRunner $t) use ($compare, $entry): void {
		foreach ([['2024-02-10', 2900, '2024-03-04', '2024-03-10'], ['2026-03-10', 3100, '2026-04-06', '2026-04-12'], ['2026-10-10', 3100, '2026-11-02', '2026-11-08']] as [$payment, $amount, $from, $to]) {
			$values = $compare([$entry($payment, $amount)], $from, $to, options: ['history' => strtotime('2020-01-01 Europe/Vienna')]);
			$t->assertSame(700, $values['average-previous-month']['fullAverage'], 'The seven-day amount ignores month length and 23/25-hour days');
		}
	},
	'Previous-quarter means preserve exact subject scope, signed rounding and label deduplication' => function(TestRunner $t) use ($compare, $entry): void {
		$rows = [$entry('2026-09-10', 101), $entry('2026-09-11', 202, ['group_id' => 2, 'parent_id' => 1]), $entry('2026-09-12', 300, ['type' => 'income'])];
		foreach ([['1', false, 101], ['1', true, 34], ['2', false, 67], ['0', false, 0]] as [$id, $direct, $expected]) {
			$value = $compare($rows, '2026-10-01', '2026-10-31', options: compact('id', 'direct'))['average-previous-quarter'];
			$t->assertSame($expected, $value['fullAverage'], 'Only the selected parent, direct, child or unassigned subject enters the mean');
		}
		$t->assertSame(-1, $compare($rows, '2026-10-01', '2026-10-31', options: ['type' => 'all'])['average-previous-quarter']['fullAverage'], 'Balance is income minus expenses before averaging');
		$t->assertSame(-34, $compare([$entry('2026-09-10', 101)], '2026-10-01', '2026-10-31', options: ['type' => 'all'])['average-previous-quarter']['fullAverage'], 'Negative fractional cents round symmetrically');
		$labels = [$entry('2026-09-01', 303, ['assign_group' => false]), $entry('2026-09-01', 303, ['counts_toward_total' => false, 'group_id' => 'label:important']), $entry('2026-09-01', 303, ['counts_toward_total' => false, 'group_id' => 'tag:9'])];
		foreach (['total', 'label:important', 'tag:9'] as $id) $t->assertSame(101, $compare($labels, '2026-10-01', '2026-10-31', options: ['grouping' => 'labelsAndTags', 'id' => $id])['average-previous-quarter']['fullAverage'], 'Overlapping labels do not inflate their annual or prior-quarter reference');
	},
	'Prior-period averages omit unknown and unfinished sources but keep genuine empty periods' => function(TestRunner $t) use ($compare, $entry, $date): void {
		$unknown = $compare([], '2026-10-19', '2026-10-25', options: ['history' => $date('2026-10-01')->getTimestamp()]);
		$t->assertSame(false, isset($unknown['average-previous-month']), 'Do not invent a month before known scope history');
		$empty = $compare([], '2026-10-19', '2026-10-25');
		$t->assertSame(0, $empty['average-previous-month']['fullAverage'], 'An empty known month remains a usable zero');
		$t->assertSame(true, $empty['average-previous-month']['available'], 'Zero is not missing data');
		$future = $compare([$entry('2026-10-10', 3100)], '2026-11-02', '2026-11-08', '2026-10-20 12:00', ['cutoff' => $date('2026-11-08 23:59:59')->getTimestamp()]);
		$t->assertSame(false, isset($future['average-previous-month']), 'An unfinished October is not presented as a complete previous month');
		$t->assertSame(true, isset($future['average-previous-quarter']), 'The already completed previous quarter remains available');
	},
];

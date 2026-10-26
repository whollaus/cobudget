<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;

require_once dirname(__DIR__, 2) . '/lib/Service/AnnualOverviewService.php';

return [
	'Report ranges keep exact vacation totals across every breakdown and DST' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$entry = static fn(string $date, int $cents): array => ['date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'type' => 'expense', 'amount_cents' => $cents, 'group_id' => 1, 'group_name' => 'Holiday'];
		$rows = [$entry('2026-03-24 23:59:59', 99999), $entry('2026-03-25 00:00:00', 101), $entry('2026-03-29 03:01', 202), $entry('2026-04-03 23:59:59', 303), $entry('2026-04-04 00:00:00', 99999)];
		foreach (['day' => 10, 'week' => 2, 'year' => 2, 'quarter' => 2] as $breakdown => $count) {
			$periods = $service->periods('2026-03-25', '2026-04-03', $zone, $breakdown);
			$t->assertSame($count, count($periods), 'Only intersecting calendar buckets are displayed');
			$t->assertSame(strtotime('2026-03-25 Europe/Vienna'), $periods[0]['dateFrom'], 'First bucket starts exactly on the chosen date');
			$t->assertSame(strtotime('2026-04-04 Europe/Vienna') - 1, $periods[$count - 1]['dateTo'], 'Final date is inclusive');
			$report = $service->build($rows, 2026, $zone, 'categories', periods: $periods);
			$t->assertSame(606, $report['totals']['expense']['total'], 'Changing breakdown never includes surrounding payments');
			$t->assertSame(-606, $report['totals']['balance']['total'], 'Balance remains in exact cents');
			$t->assertSame(3, $report['totals']['expense']['totalCount'], 'All three selected payments count exactly once');
		}
		$days = $service->periods('2026-03-29', '2026-03-29', $zone, 'day');
		$t->assertSame(23 * 3600, $days[0]['dateTo'] - $days[0]['dateFrom'] + 1, 'DST days use local midnight boundaries');
		$t->assertSame(29, count($service->periods('2024-02-01', '2024-02-29', $zone, 'day')), 'Leap day is included');
	},
	'Report date validation rejects invalid, inverted and excessive column ranges' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('UTC');
		foreach ([['2026-02-30', '2026-03-01'], ['2026-04-02', '2026-04-01'], ['', '2026-04-01'], ['1969-12-31', '1970-01-01'], ['2000-01-01', '2026-01-01']] as [$from, $to]) {
			$rejected = false;
			try { $service->periods($from, $to, $zone, 'day'); } catch (InvalidArgumentException) { $rejected = true; }
			$t->assertTrue($rejected, 'Malformed or excessive daily ranges must be rejected');
		}
		$t->assertSame(313, count($service->periods('2000-01-01', '2026-01-01', $zone, 'year')), 'Long periods can still use months');
	},
	'Clipped report comparisons use matching slices and elapsed dates' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 12:00', $zone);
		$entry = static fn(string $date, int $cents): array => ['date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'type' => 'expense', 'amount_cents' => $cents, 'group_id' => 1, 'group_name' => 'Holiday'];
		$rows = [$entry('2026-02-05', 90000), $entry('2026-02-12', 100), $entry('2026-02-19 13:00', 90000), $entry('2026-03-12', 200)];
		$periods = $service->periods('2026-03-10', '2026-03-25', $zone, 'year');
		$report = $service->build($rows, 2026, $zone, 'categories', periods: $periods);
		$compared = $service->comparePeriods($report, $rows, $periods, $now, 'categories', 'month', 'year');
		$change = $compared['totals']['expense']['comparisons'][0];
		$t->assertSame(100, $change['previous'], 'Previous month excludes dates outside the clipped elapsed slice');
		$t->assertSame(200, $change['current'], 'Current slice is restricted to selected dates');
		$t->assertSame('2026-02-10', $change['previousFrom'], 'Clipped start shifts to the same day');
		$t->assertSame('2026-02-19', $change['previousTo'], 'Ongoing slice uses the same elapsed date and time');
		$t->assertSame(100, $change['percent'], 'Exact cents produce the expected change');
	},
];

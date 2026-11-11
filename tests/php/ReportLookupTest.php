<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;
use OCA\CoBudget\Service\ReportLookupService;
use OCA\CoBudget\Service\ReportScopeService;
use OCA\CoBudget\Service\ReportComparisonService;

return [
	'Lookup report groups normalize names, preserve hierarchy and reconcile each area in exact cents' => function(TestRunner $t): void {
		$base = ['date' => strtotime('2026-01-15 UTC'), 'type' => 'expense', 'group_id' => 1, 'group_name' => ' Strom ', 'group_icon' => 'Flash', 'amount_cents' => 101, 'project_id' => 10, 'project_name' => 'Haus'];
		$entries = [$base, array_replace($base, ['group_id' => 2, 'group_name' => 'STROM', 'amount_cents' => 202, 'project_id' => 20, 'project_name' => 'Wohnung']), array_replace($base, ['amount_cents' => 3, 'project_id' => null, 'project_name' => ''])];
		$service = new AnnualOverviewService();
		foreach (['categories', 'paymentPartners'] as $dimension) {
			$report = $service->build($entries, 2026, new DateTimeZone('UTC'), $dimension);
			$t->assertSame(1, count($report['groups']['expense']), 'Different IDs with equal normalized names form one row');
			$row = $report['groups']['expense'][0];
			$t->assertSame($dimension === 'categories' ? 'Flash' : null, $row['icon'], 'Category icons survive name grouping without adding icons to partners');
			$t->assertSame(306, $row['total'], 'No rounding of cents');
			$t->assertSame([101, 202, 3], array_column($row['areas'], 'total'), 'Origin comes from each payment, including one ID used in several areas');
			$t->assertSame([10, 20, 0], array_column($row['areas'], 'areaId'), 'Without area has an explicit stable identity');
			foreach ($row['areas'] as $area) {
				$matching = array_filter($entries, static fn(array $entry): bool => ReportLookupService::matchesAggregate($entry, $dimension, $area['reportKey'], false));
				$t->assertSame($area['total'], array_sum(array_column($matching, 'amount_cents')), 'Area drill-down reconciles with its displayed total');
			}
			$reversed = $service->build(array_reverse($entries), 2026, new DateTimeZone('UTC'), $dimension);
			$t->assertSame($row['reportKey'], $reversed['groups']['expense'][0]['reportKey'], 'Detail identities do not depend on which master ID is encountered first');
		}
		$entries[] = array_replace($base, ['group_id' => 3, 'parent_id' => 4, 'parent_name' => 'Wohnen']);
		$entries[] = array_replace($base, ['group_id' => 5, 'parent_id' => 6, 'parent_name' => 'Betrieb']);
		$entries[] = array_replace($base, ['type' => 'income']);
		$report = $service->build($entries, 2026, new DateTimeZone('UTC'), 'categories');
		$t->assertSame(3, count($report['groups']['expense']), 'Equal child names under different parents stay separate from each other and a main category');
		$t->assertSame(101, $report['totals']['income']['total'], 'Income stays separate from expenses');
		$t->assertSame(508, $report['totals']['expense']['total'], 'Parent totals and area subtotals never add to financial totals');
	},
	'Lookup group comparisons survive changing master IDs and keep area references scoped' => function(TestRunner $t): void {
		$key = ReportLookupService::key('categories', 1, 'Strom');
		$entries = [
			['date' => strtotime('2025-01-15 UTC'), 'type' => 'expense', 'group_id' => 1, 'group_name' => 'Strom', 'amount_cents' => 101, 'project_id' => 10],
			['date' => strtotime('2026-01-15 UTC'), 'type' => 'expense', 'group_id' => 2, 'group_name' => 'strom', 'amount_cents' => 202, 'project_id' => 10],
			['date' => strtotime('2026-01-15 UTC'), 'type' => 'expense', 'group_id' => 3, 'group_name' => 'Strom', 'amount_cents' => 303, 'project_id' => 20],
		];
		$service = new AnnualOverviewService(); $zone = new DateTimeZone('UTC'); $now = new DateTimeImmutable('2026-02-01 UTC');
		$report = $service->compare($service->build($entries, 2026, $zone, 'categories'), $entries, 2026, $now, 'categories', 'year');
		$t->assertSame(101, $report['groups']['expense'][0]['comparisons'][0]['previous'], 'Previous year matches the normalized title');
		$t->assertSame(202, $report['groups']['expense'][0]['areas'][0]['comparisons'][0]['current'], 'Area reference excludes other areas');
		$periods = $service->periods('2026-01-01', '2026-01-31', $zone, 'month');
		$references = (new ReportComparisonService())->build($entries, $periods, $now, $now->getTimestamp(), 'categories', 'expense', $key . ':area:10', false, strtotime('2025-01-01 UTC'), 'month');
		$t->assertSame(202, $references[0]['current'], 'Detailed comparison uses the same area subject');
		$hierarchy = $entries;
		$hierarchy[] = ['date' => strtotime('2025-01-16 UTC'), 'type' => 'expense', 'group_id' => 7, 'group_name' => 'Grundgebühr', 'parent_id' => 1, 'parent_name' => 'Strom', 'amount_cents' => 999, 'project_id' => 10];
		$hierarchy[] = ['date' => strtotime('2026-01-16 UTC'), 'type' => 'expense', 'group_id' => 8, 'group_name' => 'Grundgebühr', 'parent_id' => 2, 'parent_name' => 'strom', 'amount_cents' => 888, 'project_id' => 10];
		$hierarchyReport = $service->compare($service->build($hierarchy, 2026, $zone, 'categories'), $hierarchy, 2026, $now, 'categories', 'year');
		$direct = array_values(array_filter($hierarchyReport['groups']['expense'][0]['children'], static fn(array $child): bool => $child['direct']))[0];
		$t->assertSame(101, $direct['areas'][0]['comparisons'][0]['previous'], 'Direct area comparisons never include subcategory amounts from the same area');
		$t->assertSame(202, $direct['areas'][0]['comparisons'][0]['current'], 'Direct current area amount excludes its children');

		$filter = (new ReportScopeService())->parse(json_encode([['dimension' => 'categories', 'id' => $key . ':area:10']]));
		$t->assertTrue((new ReportScopeService())->matches(['category_id' => 8, 'category_name' => 'STROM', 'project_id' => 10], $filter, []), 'Projected payments use the same name and area predicate');
		$t->assertFalse((new ReportScopeService())->matches(['category_id' => 8, 'category_name' => 'STROM', 'project_id' => 20], $filter, []), 'Other areas stay excluded');
	},
];

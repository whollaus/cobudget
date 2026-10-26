<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\ReportBreakdownService;

require_once dirname(__DIR__, 2) . '/lib/Service/ReportBreakdownService.php';

$labels = ['important' => ['column' => 'is_important', 'name' => 'Important'], 'insurance' => ['column' => 'is_insurance', 'name' => 'Insurance']];
$payment = static fn(int|string $id, int $cents, array $extra = []): array => array_replace([
	'id' => $id, 'date' => 100, 'amount_cents' => $cents, 'type' => 'expense', 'category_id' => 10, 'category_name' => 'Home', 'partner_id' => 20, 'partner_name' => 'Shop',
], $extra);
$build = static fn(array $rows, string $grouping = 'categories', string $id = 'total', bool $direct = false, string $type = 'expense'): array => (new ReportBreakdownService())->build($rows, 50, 150, $grouping, $type, $id, $direct, $labels);

return [
	'Composition reassembles tag joins and keeps exact totals separate from overlapping rows' => function(TestRunner $t) use ($payment, $build): void {
		$rows = [
			$payment(1, 101, ['hashtag_id' => 5, 'hashtag_name' => 'Alpen', 'is_important' => 1, 'is_insurance' => 't']),
			$payment(1, 101, ['hashtag_id' => 6, 'hashtag_name' => 'Urlaub', 'is_important' => 1, 'is_insurance' => 't']),
			$payment(2, 202, ['category_id' => 11, 'category_name' => 'Rent', 'parent_id' => 10, 'parent_name' => 'Home', 'partner_id' => 21, 'partner_name' => 'Shop']),
			$payment(3, 0, ['category_id' => null, 'partner_id' => null, 'category_name' => '', 'partner_name' => '']),
			$payment(4, 800, ['type' => 'income']),
		];
		$report = $build($rows, type: 'all');
		$t->assertSame(['amountCents' => 303, 'count' => 3], $report['totals']['expense'], 'Joined tags must not multiply cents or payment counts');
		$t->assertSame(['amountCents' => 800, 'count' => 1], $report['totals']['income'], 'Balance details retain separate denominators for income and expenses');
		$t->assertSame(3, count($report['groups']['paymentPartners']['expense']), 'Partners with equal names and unassigned payments keep distinct identities');
		$t->assertSame(303, array_sum(array_column($report['groups']['categories']['expense'], 'amountCents')), 'Flat direct category assignments never duplicate parent and child amounts');
		$t->assertSame('Home', $report['groups']['categories']['expense'][1]['parentName'], 'Subcategory rows retain their context');
		$t->assertSame(true, $report['overlaps']['hashtags']['expense'], 'Only overlapping dimensions need the explanation');
		$t->assertSame(true, $report['overlaps']['labels']['expense'], 'Enabled co-occurring labels overlap');
		$t->assertSame(202, $report['groups']['hashtags']['expense'][2]['amountCents'], 'Untagged payments stay visible without changing the unique total');
	},
	'Composition keeps parent, direct, unassigned, label and tag detail subjects exact' => function(TestRunner $t) use ($payment, $build): void {
		$rows = [
			$payment(1, 101, ['hashtag_id' => 5, 'hashtag_name' => 'Trip', 'is_important' => 1]),
			$payment(1, 101, ['hashtag_id' => 6, 'hashtag_name' => 'Other', 'is_important' => 1]),
			$payment(2, 202, ['category_id' => 11, 'parent_id' => 10, 'hashtag_id' => 5, 'hashtag_name' => 'Trip']),
			$payment(3, 303, ['category_id' => null, 'partner_id' => null]),
			$payment(4, 404, ['is_fixed_cost' => 1]),
		];
		foreach ([['categories', '10', false, 707], ['categories', '10', true, 505], ['categories', '11', false, 202], ['categories', '0', false, 303], ['paymentPartners', '0', false, 303], ['labelsAndTags', 'label:important', false, 101], ['labelsAndTags', 'tag:5', false, 303], ['labelsAndTags', 'total', false, 303]] as [$grouping, $id, $direct, $expected]) {
			$t->assertSame($expected, $build($rows, $grouping, $id, $direct)['totals']['expense']['amountCents'], 'Subject ' . $grouping . ':' . $id . ' retains its original scope');
		}
		$t->assertSame([], $build($rows, 'labelsAndTags', 'label:fixedCost')['groups']['categories']['expense'], 'Disabled labels are not selectable by a forged detail link');
		$tagged = $build($rows, 'labelsAndTags', 'tag:5');
		$t->assertSame([5, 6], array_column($tagged['groups']['hashtags']['expense'], 'id'), 'Selecting a tag does not erase other tags carried by the same payments');
	},
	'Composition clips all saved and projected payments to the inclusive opened interval' => function(TestRunner $t) use ($payment, $build): void {
		$rows = [$payment(1, 1, ['date' => 50]), $payment(2, 2, ['date' => 150]), $payment(3, 1000, ['date' => 49]), $payment(4, 1000, ['date' => 151]),
			$payment('projected:1:100', 4, ['hashtags' => [['id' => 5, 'name' => 'Trip'], ['id' => 6, 'name' => 'Other']]]),
			$payment('projected:1:120', 4), $payment('projected:1:180', 9000, ['date' => 180])];
		$t->assertSame(['amountCents' => 11, 'count' => 4], $build($rows)['totals']['expense'], 'Include edge instants, separate recurring occurrences and exclude dates before/after the opened range');
		$t->assertSame(4, $build($rows, 'labelsAndTags', 'tag:5')['totals']['expense']['amountCents'], 'Projected tags use the same subject matcher as saved tags');
		$t->assertSame(['amountCents' => 0, 'count' => 0], $build([])['totals']['expense'], 'An empty detail still has a valid composition');
	},
];

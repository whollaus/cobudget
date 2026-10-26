<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\ReportScopeService;

require_once dirname(__DIR__, 2) . '/lib/Service/ReportScopeService.php';

return [
	'Report drill-down predicates intersect categories, partners, labels and tags' => function(TestRunner $t): void {
		$service = new ReportScopeService();
		$labels = ['important' => ['column' => 'is_important'], 'insurance' => ['column' => 'is_insurance']];
		$payment = ['type' => 'expense', 'category_id' => 11, 'parent_id' => 10, 'partner_id' => 20, 'project_id' => 30, 'is_important' => 1, 'is_insurance' => 1, 'hashtags' => [['id' => 40], ['id' => 41]]];
		$filters = $service->parse(json_encode([
			['dimension' => 'type', 'id' => 'expense'], ['dimension' => 'categories', 'id' => '10', 'direct' => false],
			['dimension' => 'paymentPartners', 'id' => '20'], ['dimension' => 'labels', 'id' => 'important'],
			['dimension' => 'labels', 'id' => 'insurance'], ['dimension' => 'hashtags', 'id' => '40'], ['dimension' => 'hashtags', 'id' => '41'],
		]));
		$t->assertTrue($service->matches($payment, $filters, $labels), 'A category parent includes its child and all other choices must match');
		foreach ([['partner_id' => 21], ['category_id' => 12, 'parent_id' => 9], ['type' => 'income'], ['is_insurance' => 0], ['hashtags' => [['id' => 40]]]] as $change) {
			$t->assertFalse($service->matches(array_replace($payment, $change), $filters, $labels), 'Dropping any predicate must remove the payment');
		}
		$direct = $service->parse('[{"dimension":"categories","id":"10","direct":true}]');
		$t->assertFalse($service->matches($payment, $direct, $labels), 'Direct-category drill-down never adds its subcategories');
		$t->assertTrue($service->matches(array_replace($payment, ['category_id' => 10]), $direct, $labels), 'A direct assignment matches');
	},
	'Report drill-down retains unassigned choices and enabled-label semantics' => function(TestRunner $t): void {
		$service = new ReportScopeService();
		$labels = ['important' => ['column' => 'is_important']];
		$empty = ['type' => 'expense', 'is_insurance' => 1];
		$filters = $service->parse('[{"dimension":"categories","id":"0"},{"dimension":"paymentPartners","id":"0"},{"dimension":"labels","id":""},{"dimension":"hashtags","id":"0"},{"dimension":"projects","id":"0"}]');
		$t->assertTrue($service->matches($empty, $filters, $labels), 'Missing dimensions stay selectable; disabled labels do not count');
		foreach ([['category_id' => 10], ['partner_id' => 20], ['is_important' => 't'], ['hashtags' => [['id' => 40]]], ['project_id' => 30]] as $change) {
			$t->assertFalse($service->matches($empty + $change, $filters, $labels), 'An assigned value cannot enter its unassigned row');
		}
		$t->assertFalse($service->matches($empty, $service->parse('[{"dimension":"labels","id":"insurance"}]'), $labels), 'Disabled positive labels fail closed');
	},
	'Report drill-down rejects malformed and unbounded identities' => function(TestRunner $t): void {
		$service = new ReportScopeService();
		$t->assertSame([], $service->parse(''), 'Existing reports need no detail predicates');
		foreach (['null', '{}', '{', '[null]', '[{"dimension":"sql","id":"1"}]', '[{"dimension":"categories","id":"-1"}]', '[{"dimension":"labels","id":"notEnabled"}]', '[{"dimension":"hashtags","id":"9007199254740992"}]', '[{"dimension":"categories","id":"1","direct":"false"}]', json_encode(array_fill(0, 9, ['dimension' => 'type', 'id' => 'expense']))] as $invalid) {
			$rejected = false;
			try { $service->parse($invalid); } catch (InvalidArgumentException $error) { $rejected = true; }
			$t->assertTrue($rejected, 'Invalid identities must not become an unrestricted report');
		}
	},
];

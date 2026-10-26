<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;
use OCA\CoBudget\Service\AreaOverviewService;

require_once dirname(__DIR__, 2) . '/lib/Service/AnnualOverviewService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/AreaOverviewService.php';

return [
	'Area comparisons retain clipped buckets, private payments and independently signed balances' => function(TestRunner $t): void {
		$zone = new DateTimeZone('Europe/Vienna');
		$periods = (new AnnualOverviewService())->periods('2026-03-25', '2026-04-03', $zone, 'week');
		$entry = static fn(string $date, int $id, int $cents, string $type = 'expense', int $count = 1): array => ['date' => strtotime($date . ' Europe/Vienna'), 'group_id' => $id, 'amount_cents' => $cents, 'payment_count' => $count, 'type' => $type];
		$areas = [2 => ['name' => 'Area 2', 'member_count' => 2, 'is_archived' => 1], 10 => ['name' => 'Area 10', 'member_count' => 1]];
		$rows = [$entry('2026-03-24', 2, 99999), $entry('2026-03-25', 2, 101, count: 2), $entry('2026-03-29', 0, 202), $entry('2026-04-03 23:59', 10, 303), $entry('2026-04-04', 2, 99999), $entry('2026-03-25', 2, 101, 'income'), $entry('2026-03-25', 10, 0)];
		$result = (new AreaOverviewService())->build($rows, $periods, $zone, $areas, 'area');
		$byId = array_column($result['rows'], null, 'id');
		$t->assertSame(606, $result['totals']['expense']['total'], 'Each payment belongs to exactly one area or the private row');
		$t->assertSame(5, $result['totals']['expense']['totalCount'], 'SQL payment counts survive grouping, including real zero-value payments');
		$t->assertSame(202, $byId[0]['expense']['total'], 'Private payments retain their own row');
		$t->assertSame([101, 0], $byId[2]['expense']['months'], 'Amounts stay inside the exact opened interval across DST');
		$t->assertSame([0, 0], $byId[2]['balance']['months'], 'Equal income and expense form a true zero balance');
		$t->assertSame(3, $byId[2]['balance']['totalCount'], 'Zero balances retain their underlying payment count');
		$t->assertSame(-505, $result['totals']['balance']['total'], 'Overall balance keeps its sign');
		$t->assertSame(true, $byId[2]['archived'], 'Historical archived areas remain available');
		$t->assertSame('shared', $byId[2]['entryScope'], 'Full shared amounts open the shared detail perspective');
		$t->assertSame('personal', $byId[10]['entryScope'], 'Solo areas open their personal rows');
		$t->assertSame('personal', $byId[0]['entryScope'], 'Unassigned amounts never switch to another person’s rows');
		$personal = (new AreaOverviewService())->build($rows, $periods, $zone, $areas, 'personal');
		foreach ($personal['rows'] as $row) $t->assertSame('personal', $row['entryScope'], 'Default comparisons open stored personal amounts');
	},
];

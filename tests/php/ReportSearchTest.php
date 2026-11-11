<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Controller\EntryController;

require_once dirname(__DIR__, 2) . '/lib/Controller/EntryController.php';

return [
	'Payment multi-ID constraints bind OR choices, optional category children and intersect dimensions' => function(TestRunner $t): void {
		$controller = (new ReflectionClass(EntryController::class))->newInstanceWithoutConstructor();
		$query = new class {
			public array $parameters = [];
			public array $clauses = [];
			public function expr(): self { return $this; }
			public function createNamedParameter(array $value, int $type): string { $this->parameters[] = $value; return ':ids' . count($this->parameters); }
			public function in(string $column, string $parameter): string { return $column . ' IN ' . $parameter; }
			public function isNull(string $column): string { return $column . ' IS NULL'; }
			public function orX(string ...$parts): string { return '(' . implode(' OR ', $parts) . ')'; }
			public function andWhere(string $clause): void { $this->clauses[] = $clause; }
		};
		$apply = new ReflectionMethod($controller, 'applyAnnualIdChoice');
		$apply->invoke($controller, $query, [0, 2, 3], 'e.category_id', 'c.id', 'c.parent_category_id');
		$t->assertSame('(c.id IS NULL OR e.category_id IN :ids1 OR c.parent_category_id IN :ids2)', $query->clauses[0], 'None, parents and direct children are alternatives');
		$apply->invoke($controller, $query, [4, 5], 'e.payment_partner_id', 'p.id');
		$apply->invoke($controller, $query, [6, 7], 'e.project_id', 'e.project_id');
		$t->assertSame([[2, 3], [2, 3], [4, 5], [6, 7]], $query->parameters, 'All IDs are bound independently of authorization');
		$t->assertSame(3, count($query->clauses), 'Different dimensions intersect through separate AND predicates');
		$apply->invoke($controller, $query, [], 'e.project_id', 'e.project_id');
		$t->assertSame(3, count($query->clauses), 'Cleared choices add no restriction');
		$apply->invoke($controller, $query, [0, 6, 7], 'e.project_id', 'e.project_id');
		$t->assertSame('(e.project_id IS NULL OR e.project_id IN :ids5)', $query->clauses[3], 'Unassigned and named areas form one OR predicate, retaining surrounding authorization constraints');
		$t->assertSame([6, 7], $query->parameters[4], 'The unassigned sentinel is not treated as a persisted project ID');
		foreach (['annualOverview', 'reportBreakdown', 'reportForecast', 'areaOverview'] as $method) {
			$t->assertContains('$this->reportProjectIds = $this->parseAnnualIdList($projects, true);', $t->methodBody('lib/Controller/EntryController.php', $method), 'Report surfaces initialize the same bounded area selection');
		}
		$t->assertContains('$this->applyAnnualIdChoice($qb, $this->reportProjectIds', $t->methodBody('lib/Controller/EntryController.php', 'buildVisibleEntriesQuery'), 'Report choices narrow authorized queries, including recurrence heads and reference periods');
		$parse = new ReflectionMethod($controller, 'parseAnnualIdList');
		$t->assertSame([6, 7], $parse->invoke($controller, '6,7,6,0,-1,invalid', false), 'Area lists normalize duplicates and reject zero/invalid IDs');
	},

	'Report search shares the bound payment search predicate, including literal zero' => function(TestRunner $t): void {
		$controller = (new ReflectionClass(EntryController::class))->newInstanceWithoutConstructor();
		$makeQuery = static fn() => new class {
			public array $parameters = [];
			public array $clauses = [];
			public function expr(): self { return $this; }
			public function createNamedParameter(string $value): string { $this->parameters[] = $value; return ':search' . count($this->parameters); }
			public function iLike(string $column, string $parameter): string { return $column . ' ILIKE ' . $parameter; }
			public function orX(string ...$parts): string { return '(' . implode(' OR ', $parts) . ')'; }
			public function andWhere(string $clause): void { $this->clauses[] = $clause; }
		};
		foreach (['Strom', '0', "x' OR 1=1 --", 'Müller & Söhne'] as $search) {
			$payment = $makeQuery(); $report = $makeQuery();
			(new ReflectionMethod($controller, 'applyEntrySearch'))->invoke($controller, $payment, $search);
			(new ReflectionMethod($controller, 'applyAnnualPaymentNarrowing'))->invoke($controller, $report, ['search' => $search]);
			$t->assertSame($payment->clauses, $report->clauses, 'Report and payment list search have the same fields and AND/OR structure');
			$t->assertSame(['%' . $search . '%'], $report->parameters, 'Search values are bound, never interpolated into SQL');
			$t->assertSame(1, count($report->clauses), 'Search is effective even without other report filters');
			$t->assertContains('c.code ILIKE', $report->clauses[0], 'Category numbers remain searchable');
			$t->assertContains('p.name ILIKE', $report->clauses[0], 'Payment partner names remain searchable');
		}
		$empty = $makeQuery();
		(new ReflectionMethod($controller, 'applyAnnualPaymentNarrowing'))->invoke($controller, $empty, ['search' => '']);
		$t->assertSame([], $empty->clauses, 'Clearing search removes its predicate');
	},
];

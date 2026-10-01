<?php

declare(strict_types=1);

namespace OCP {
	if (!interface_exists(IDBConnection::class, false)) {
		interface IDBConnection {}
	}
}

namespace OCP\AppFramework {
	if (!class_exists(Controller::class, false)) {
		class Controller {
			public object $request;
		}
	}
}

namespace CoBudget\Tests {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\EntryController;
	use OCP\IDBConnection;

	require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceAwareTrait.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/EntryController.php';

	// Only the database boundary is replaced: aggregation and grouping run the
	// real controller methods, including cent normalization and personal shares.
	final class DashboardTimezoneDatabase implements IDBConnection {
		public function __construct(private array $rows) {}

		public function getQueryBuilder(): object {
			return new class($this->rows) {
				public function __construct(private array $rows) {}
				public function __call(string $name, array $arguments): mixed {
					return $name === 'createNamedParameter' ? $arguments[0] : $this;
				}
				public function executeQuery(): object {
					return new class($this->rows) {
						private int $index = 0;
						public function __construct(private array $rows) {}
						public function fetch(): array|false {
							return $this->rows[$this->index++] ?? false;
						}
						public function closeCursor(): void {}
					};
				}
			};
		}
	}

	function dashboardTimezoneController(array $rows, string $browserTimezone): EntryController {
		$class = new \ReflectionClass(EntryController::class);
		$controller = $class->newInstanceWithoutConstructor();
		$class->getProperty('db')->setValue($controller, new DashboardTimezoneDatabase($rows));
		$class->getProperty('userId')->setValue($controller, 'test-user');
		$controller->request = new class($browserTimezone) {
			public function __construct(private string $timezone) {}
			public function getHeader(string $name): string {
				return $name === 'X-CoBudget-Timezone' ? $this->timezone : '';
			}
		};
		return $controller;
	}

	function dashboardTimezoneAggregate(EntryController $controller, bool $future = false): array {
		$method = new \ReflectionMethod($controller, 'fetchEntryAggregateData');
		$values = [
			'workspaceId' => 1, 'search' => '', 'type' => 'all',
			'sortBy' => 'date', 'sortDir' => 'desc', 'isFuture' => $future,
			'projectShareBasisPoints' => [],
		];
		$arguments = array_map(static fn(\ReflectionParameter $parameter): mixed => $values[$parameter->getName()] ?? null, $method->getParameters());
		return $method->invokeArgs($controller, $arguments);
	}

	return [
		'Dashboard counts local October income in month cards and table totals on a UTC server' => function(TestRunner $t): void {
			$originalTimezone = date_default_timezone_get();
			try {
				date_default_timezone_set('UTC');
				$rows = [
					['id' => 1, 'type' => 'income', 'amount_cents' => 150000, 'date' => strtotime('2026-10-01 09:00:00 UTC')],
					['id' => 2, 'type' => 'expense', 'amount_cents' => 20000, 'date' => strtotime('2026-10-01 09:00:00 UTC')],
					['id' => 3, 'type' => 'income', 'amount_cents' => 2500, 'date' => strtotime('2026-10-01 00:00:00 Europe/Vienna')],
				];
				$controller = dashboardTimezoneController($rows, 'Europe/Vienna');
				$aggregate = dashboardTimezoneAggregate($controller);
				$t->assertSame(1525.0, (float)$aggregate['metrics']['income'], 'Year income includes the sale');
				$t->assertSame(1325.0, (float)$aggregate['metrics']['balance'], 'Year balance includes the sale');
				$t->assertSame(1525.0, (float)($aggregate['monthlyMetrics']['2026-10']['income'] ?? 0), 'October income must include all three displayed October payments');
				$t->assertSame(1325.0, (float)$aggregate['monthlyMetrics']['2026-10']['balance'], 'October card balance must include the sale');
				$groups = (new \ReflectionMethod($controller, 'buildDateGroupsFromAggregate'))->invoke($controller, $aggregate, 0, 3, 'date');
				$t->assertSame(1325.0, (float)$groups['summaries']['month-2026-10']['balance'], 'October table footer must include the sale');
				$t->assertSame(3, $groups['summaries']['month-2026-10']['count'], 'All displayed October rows belong to the October total');
				$t->assertSame('UTC', date_default_timezone_get(), 'Calendar calculations must not change PHP timezone for cron or other apps');
			} finally {
				date_default_timezone_set($originalTimezone);
			}
		},
		'Dashboard grouping respects east and west year boundaries and recurring dates' => function(TestRunner $t): void {
			$originalTimezone = date_default_timezone_get();
			try {
				date_default_timezone_set('UTC');
				foreach ([
					['Europe/Vienna', '2026-12-31 23:30:00 UTC', '2027', '2027-01'],
					['America/Los_Angeles', '2027-01-01 00:30:00 UTC', '2026', '2026-12'],
				] as [$zone, $instant, $year, $month]) {
					$timestamp = strtotime($instant);
					$rows = [['id' => 1, 'type' => 'income', 'amount_cents' => 2500, 'date' => $timestamp]];
					$aggregate = dashboardTimezoneAggregate(dashboardTimezoneController($rows, $zone));
					$t->assertSame(2500, $aggregate['dateGroupSummaries']['year-' . $year]['income'] ?? 0, $zone . ' groups income into its displayed year');
					$t->assertSame(25.0, (float)($aggregate['monthlyMetrics'][$month]['income'] ?? 0), $zone . ' groups income into its displayed month');
					$rows[0]['date'] = strtotime('2020-06-15 UTC');
					$rows[0]['recurrence_next_date'] = $timestamp;
					$future = dashboardTimezoneAggregate(dashboardTimezoneController($rows, $zone), true);
					$t->assertSame(2500, $future['dateGroupSummaries']['month-' . $month]['income'] ?? 0, 'Future groups use the local next recurrence date rather than the source date');
				}
			} finally {
				date_default_timezone_set($originalTimezone);
			}
		},
		'Dashboard timezone fallback and monthly averages preserve calendar boundaries' => function(TestRunner $t): void {
			$originalTimezone = date_default_timezone_get();
			try {
				date_default_timezone_set('Europe/Vienna');
				$timestamp = strtotime('2026-09-30 22:30:00 UTC');
				foreach (['', 'invalid/timezone', "UTC\0bad", '+99:00'] as $header) {
					$controller = dashboardTimezoneController([], $header);
					$keys = (new \ReflectionMethod($controller, 'dateGroupKeys'))->invoke($controller, $timestamp);
					$t->assertSame(['year-2026', 'month-2026-10'], $keys, 'Missing or invalid timezone must use the server timezone');
				}
				date_default_timezone_set('UTC');
				$controller = dashboardTimezoneController([], 'Europe/Vienna');
				$months = (new \ReflectionMethod($controller, 'monthSpanInclusive'))->invoke(
					$controller, strtotime('2026-10-01 00:00:00 Europe/Vienna'), strtotime('2026-11-01 00:00:00 Europe/Vienna')
				);
				$t->assertSame(2, $months, 'Month span stays correct across midnight and daylight-saving changes');
				$aggregate = ['monthlyMetrics' => ['2020-09' => ['income' => 50], '2020-10' => ['income' => 100]]];
				$average = (new \ReflectionMethod($controller, 'calculateAverageDashboardMetricsFromAggregate'))->invoke($controller, $aggregate);
				$t->assertSame(75.0, $average['income'], 'Monthly average uses the same local month boundaries as the aggregate');
			} finally {
				date_default_timezone_set($originalTimezone);
			}
		},
	];
}

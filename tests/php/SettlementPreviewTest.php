<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Controller\ProjectController;
use OCP\IDBConnection;

require_once dirname(__DIR__, 2) . '/lib/Controller/ProjectController.php';

/** Runs the controller's real aggregate and settlement selection against an in-memory database. */
final class SettlementPreviewDatabase implements IDBConnection {
	public \PDO $pdo;
	public function __construct() {
		$this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$this->pdo->exec('CREATE TABLE cobudget_entries (id INTEGER PRIMARY KEY, project_id INTEGER, workspace_id INTEGER, entry_kind TEXT, is_settled INTEGER, amount_cents INTEGER, amount REAL, type TEXT, date INTEGER)');
	}
	public function getQueryBuilder(): object {
		return new class($this->pdo) {
			private array $columns = [], $conditions = [], $parameters = [];
			private string $table = '';
			public function __construct(private \PDO $pdo) {}
			public function select(string $column): self { $this->columns = [$column]; return $this; }
			public function selectAlias(string $column, string $alias): self { $this->columns[] = "$column AS $alias"; return $this; }
			public function createFunction(string $sql): string { return $sql; }
			public function from(string $table): self { $this->table = $table; return $this; }
			public function expr(): self { return $this; }
			public function eq(string $left, string $right): string { return "$left = $right"; }
			public function createNamedParameter(mixed $value, int $type = \PDO::PARAM_STR): string {
				$name = ':p' . count($this->parameters);
				$this->parameters[$name] = [$value, $type];
				return $name;
			}
			public function where(string $condition): self { $this->conditions = [$condition]; return $this; }
			public function andWhere(string $condition): self { $this->conditions[] = $condition; return $this; }
			public function executeQuery(): \PDOStatement {
				$query = $this->pdo->prepare('SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table . ' WHERE ' . implode(' AND ', $this->conditions));
				foreach ($this->parameters as $name => [$value, $type]) $query->bindValue($name, $value, $type);
				$query->execute();
				return $query;
			}
		};
	}
}

return [
	'Settlement preview covers exactly the open shared source payments in the area workspace' => function(TestRunner $t): void {
		$db = new SettlementPreviewDatabase();
		$insert = $db->pdo->prepare('INSERT INTO cobudget_entries VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
		$rows = [
			[1, 4, 7, 'shared', 0, 10001, 999.99, 'expense', 1],
			[2, 4, 7, 'shared', 0, 2002, 888.88, 'income', time() + 86400 * 30],
			[3, 4, 7, 'shared', 1, 999999, 0, 'expense', 1],
			[4, 4, 7, 'personal', 0, 999999, 0, 'expense', 1],
			[5, 5, 7, 'shared', 0, 999999, 0, 'expense', 1],
			[6, 4, 8, 'shared', 0, 999999, 0, 'expense', 1],
		];
		foreach ($rows as $row) $insert->execute($row);
		// More than a page of payments, including one-cent amounts.
		for ($id = 10; $id < 40; $id++) $insert->execute([$id, 4, 7, 'shared', 0, 1, 9.99, 'expense', 1]);
		$class = new \ReflectionClass(ProjectController::class);
		$controller = $class->newInstanceWithoutConstructor();
		$class->getProperty('db')->setValue($controller, $db);
		$summary = $class->getMethod('openSettlementSummary')->invoke($controller, 4, 7);
		$ids = $class->getMethod('unsettledProjectEntryIds')->invoke($controller, 4, 7);
		$t->assertSame(32, $summary['entryCount'], 'All pages and future-dated payments are included, unrelated rows excluded');
		$t->assertSame(count($ids), $summary['entryCount'], 'Preview count matches the actual settlement selection');
		$t->assertSame(12033, $summary['totalCents'], 'Sum uses integer cents, adds income and expenses, and excludes personal projections');
		$t->assertSame(['entryCount' => 0, 'totalCents' => 0], $class->getMethod('openSettlementSummary')->invoke($controller, 99, 7), 'An empty area has a zero count and total');
	},
];

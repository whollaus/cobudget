<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Controller\WorkspaceAwareTrait;

require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceAwareTrait.php';

/** Minimal query-builder boundary executing the trait's SQL against SQLite. */
final class WorkspaceAssignmentDatabase {
	public \PDO $pdo;
	public array $writes = [];

	public function __construct() {
		$this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$this->pdo->exec('CREATE TABLE cobudget_workspaces (id INTEGER PRIMARY KEY, user_id TEXT, is_default INTEGER)');
		$this->pdo->exec("INSERT INTO cobudget_workspaces VALUES (7, 'user-a', 1), (8, 'user-a', 0), (9, 'user-b', 1)");
		foreach (self::tables() as $table => $ownerColumn) {
			$this->pdo->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY, $ownerColumn TEXT, workspace_id INTEGER, is_global INTEGER DEFAULT 0)");
			$this->pdo->exec("INSERT INTO $table VALUES (1, 'user-a', 7, 0), (2, 'user-a', 8, 0), (3, 'user-b', NULL, 0)");
			if (in_array($table, ['cobudget_categories', 'cobudget_payment_partners'], true)) {
				$this->pdo->exec("INSERT INTO $table VALUES (4, 'user-a', NULL, 1)");
			}
		}
	}

	public static function tables(): array {
		return [
			'cobudget_projects' => 'owner_id',
			'cobudget_entries' => 'user_id',
			'cobudget_categories' => 'user_id',
			'cobudget_payment_partners' => 'user_id',
		];
	}

	public function getQueryBuilder(): WorkspaceAssignmentQuery {
		return new WorkspaceAssignmentQuery($this);
	}

	public function snapshot(): array {
		$rows = [];
		foreach (self::tables() as $table => $_) {
			$rows[$table] = $this->pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
		}
		return $rows;
	}
}

final class WorkspaceAssignmentQuery {
	private string $table = '';
	private string $column = '*';
	private array $conditions = [];
	private array $sets = [];
	private array $parameters = [];
	private ?int $limit = null;

	public function __construct(private WorkspaceAssignmentDatabase $db) {
	}

	public function select(string $column): self {
		$this->column = $column;
		return $this;
	}

	public function from(string $table): self {
		$this->table = $table;
		return $this;
	}

	public function update(string $table): self {
		return $this->from($table);
	}

	public function set(string $column, string $value): self {
		$this->sets[] = "$column = $value";
		return $this;
	}

	public function where(string $condition): self {
		$this->conditions = [$condition];
		return $this;
	}

	public function andWhere(string $condition): self {
		$this->conditions[] = $condition;
		return $this;
	}

	public function expr(): self {
		return $this;
	}

	public function eq(string $column, string $value): string {
		return "$column = $value";
	}

	public function isNull(string $column): string {
		return "$column IS NULL";
	}

	public function setMaxResults(int $limit): self {
		$this->limit = $limit;
		return $this;
	}

	public function createNamedParameter(mixed $value, int $type = \PDO::PARAM_STR): string {
		$name = ':p' . count($this->parameters);
		$this->parameters[$name] = [$value, $type];
		return $name;
	}

	public function executeQuery(): \PDOStatement {
		$sql = "SELECT {$this->column} FROM {$this->table}";
		return $this->execute($sql, $this->limit === null ? '' : ' LIMIT ' . $this->limit);
	}

	public function executeStatement(): int {
		$this->db->writes[] = $this->table;
		return $this->execute("UPDATE {$this->table} SET " . implode(', ', $this->sets))->rowCount();
	}

	private function execute(string $sql, string $suffix = ''): \PDOStatement {
		if ($this->conditions !== []) {
			$sql .= ' WHERE ' . implode(' AND ', $this->conditions);
		}
		$statement = $this->db->pdo->prepare($sql . $suffix);
		foreach ($this->parameters as $name => [$value, $type]) {
			$statement->bindValue($name, $value, $type);
		}
		$statement->execute();
		return $statement;
	}
}

final class WorkspaceAssignmentProbe {
	use WorkspaceAwareTrait;

	public function __construct(private WorkspaceAssignmentDatabase $db, private string $userId = 'user-a') {
	}

	public function resolve(): ?int {
		return $this->getWorkspaceId();
	}
}

return [
	'Reading an established default workspace issues no write statements' => function(TestRunner $t): void {
		$db = new WorkspaceAssignmentDatabase();
		$before = $db->snapshot();
		$t->assertSame(7, (new WorkspaceAssignmentProbe($db))->resolve(), 'Default workspace should still resolve');
		$t->assertSame([], $db->writes, 'Already scoped, foreign and global rows must not trigger even empty UPDATEs');
		$t->assertSame($before, $db->snapshot(), 'Workspace reads must preserve all data');
	},

	'Legacy workspace assignment preserves ownership and becomes read-only afterwards' => function(TestRunner $t): void {
		$db = new WorkspaceAssignmentDatabase();
		foreach (WorkspaceAssignmentDatabase::tables() as $table => $_) {
			$db->pdo->exec("UPDATE $table SET workspace_id = NULL WHERE id = 1");
		}
		$expected = $db->snapshot();
		foreach ($expected as &$rows) {
			$rows[0]['workspace_id'] = 7;
		}
		unset($rows);

		$t->assertSame(7, (new WorkspaceAssignmentProbe($db))->resolve(), 'Legacy data should use the default workspace');
		$t->assertSame(array_keys(WorkspaceAssignmentDatabase::tables()), $db->writes, 'Every table with eligible legacy rows should be repaired');
		$t->assertSame($expected, $db->snapshot(), 'Only owned unscoped non-global rows should be assigned');

		$db->writes = [];
		$t->assertSame(7, (new WorkspaceAssignmentProbe($db))->resolve(), 'A later request should still resolve the same workspace');
		$t->assertSame([], $db->writes, 'Later requests must not repeat backfill UPDATEs');
		$t->assertSame($expected, $db->snapshot(), 'A repeated lookup must preserve repaired and unrelated rows');
	},

	'Workspace backfill writes only tables that need assignment' => function(TestRunner $t): void {
		$db = new WorkspaceAssignmentDatabase();
		$db->pdo->exec('UPDATE cobudget_projects SET workspace_id = NULL WHERE id = 1');
		$expected = $db->snapshot();
		$expected['cobudget_projects'][0]['workspace_id'] = 7;

		$t->assertSame(7, (new WorkspaceAssignmentProbe($db))->resolve(), 'Partial legacy data should resolve normally');
		$t->assertSame(['cobudget_projects'], $db->writes, 'Clean tables must not be marked dirty during partial repair');
		$t->assertSame($expected, $db->snapshot(), 'Partial repair must preserve the other tables');
	},
];

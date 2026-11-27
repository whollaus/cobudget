<?php

declare(strict_types=1);

namespace OCP {
	if (!interface_exists(IDBConnection::class, false)) {
		interface IDBConnection {}
	}
	if (!interface_exists(IConfig::class, false)) {
		interface IConfig {}
	}
	if (!interface_exists(IL10N::class, false)) {
		interface IL10N {
			public function t(string $text, array $parameters = []): string;
		}
	}
}

namespace OCP\DB\QueryBuilder {
	if (!interface_exists(IQueryBuilder::class, false)) {
		interface IQueryBuilder {
			public const PARAM_INT = \PDO::PARAM_INT;
			public const PARAM_STR = \PDO::PARAM_STR;
			public const PARAM_BOOL = \PDO::PARAM_BOOL;
			public const PARAM_NULL = \PDO::PARAM_NULL;
			public const PARAM_INT_ARRAY = 101;
			public const PARAM_STR_ARRAY = 102;
		}
	}
}

namespace CoBudget\Tests\ApiToken {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Service\ApiTokenService;
	use OCP\IConfig;
	use OCP\IDBConnection;
	use OCP\IL10N;

	require_once dirname(__DIR__, 2) . '/lib/Service/ApiTokenService.php';

	final class ApiTokenTestL10n implements IL10N {
		public function t(string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf($text, $parameters);
		}
	}

	final class ApiTokenTestConfig implements IConfig {
		/** @var array<string, string> */
		public array $appValues = [];
		/** @var array<string, string> */
		public array $userValues = [];

		public function setAppValue(string $appName, string $key, string $value): void {
			$this->appValues[$appName . ':' . $key] = $value;
		}

		public function setUserValue(string $userId, string $appName, string $key, string $value): void {
			$this->userValues[$userId . ':' . $appName . ':' . $key] = $value;
		}

		public function getAppValue(string $appName, string $key, string $default = ''): string {
			return $this->appValues[$appName . ':' . $key] ?? $default;
		}

		public function getUserValue(string $userId, string $appName, string $key, string $default = ''): string {
			return $this->userValues[$userId . ':' . $appName . ':' . $key] ?? $default;
		}
	}

	final class ApiTokenTestResult {
		/** @var list<array<string, mixed>> */
		private array $rows;
		private int $index = 0;

		public function __construct(array $rows) {
			$this->rows = $rows;
		}

		public function fetch(): array|false {
			return $this->rows[$this->index++] ?? false;
		}

		public function fetchOne(): mixed {
			$row = $this->fetch();
			if ($row === false) {
				return false;
			}
			return reset($row);
		}

		/** @return list<array<string, mixed>> */
		public function fetchAll(): array {
			return $this->rows;
		}

		public function closeCursor(): void {
		}
	}

	final class ApiTokenTestExpr {
		public function eq(string $col, string $val): string {
			return $col . ' = ' . $val;
		}

		public function neq(string $col, string $val): string {
			return $col . ' != ' . $val;
		}

		public function isNotNull(string $col): string {
			return $col . ' IS NOT NULL';
		}
	}

	final class ApiTokenTestQueryBuilder {
		private string $mode = 'select';
		private string $table = '';
		private string $alias = '';
		/** @var list<string> */
		private array $columns = ['*'];
		/** @var list<array{alias: string, joinTable: string, joinAlias: string, on: string}> */
		private array $joins = [];
		/** @var list<string> */
		private array $wheres = [];
		/** @var list<string> */
		private array $orders = [];
		private ?int $limit = null;
		/** @var array<string, string> */
		private array $insertValues = [];
		/** @var array<string, string> */
		private array $setValues = [];
		/** @var array<string, array{value: mixed, type: int}> */
		private array $params = [];

		public function __construct(private ApiTokenTestDb $db) {
		}

		public function expr(): ApiTokenTestExpr {
			return new ApiTokenTestExpr();
		}

		public function createFunction(string $fn): string {
			return $fn;
		}

		public function createNamedParameter(mixed $value, int $type = \PDO::PARAM_STR): string {
			$key = ':p' . count($this->params);
			$this->params[$key] = ['value' => $value, 'type' => $type];
			return $key;
		}

		public function select(string ...$columns): self {
			$this->mode = 'select';
			$this->columns = $columns;
			return $this;
		}

		public function from(string $table, string $alias = ''): self {
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function innerJoin(string $fromAlias, string $joinTable, string $joinAlias, string $on): self {
			$this->joins[] = ['alias' => $fromAlias, 'joinTable' => $joinTable, 'joinAlias' => $joinAlias, 'on' => $on];
			return $this;
		}

		public function insert(string $table): self {
			$this->mode = 'insert';
			$this->table = $table;
			return $this;
		}

		public function values(array $values): self {
			$this->insertValues = $values;
			return $this;
		}

		public function update(string $table): self {
			$this->mode = 'update';
			$this->table = $table;
			return $this;
		}

		public function set(string $col, string $val): self {
			$this->setValues[$col] = $val;
			return $this;
		}

		public function delete(string $table): self {
			$this->mode = 'delete';
			$this->table = $table;
			return $this;
		}

		public function where(string $expr): self {
			$this->wheres = [$expr];
			return $this;
		}

		public function andWhere(string $expr): self {
			$this->wheres[] = $expr;
			return $this;
		}

		public function orderBy(string $col, string $dir = 'ASC'): self {
			$this->orders = [$col . ' ' . $dir];
			return $this;
		}

		public function addOrderBy(string $col, string $dir = 'ASC'): self {
			$this->orders[] = $col . ' ' . $dir;
			return $this;
		}

		public function setMaxResults(int $limit): self {
			$this->limit = $limit;
			return $this;
		}

		public function executeQuery(): ApiTokenTestResult {
			$sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table . ($this->alias !== '' ? ' ' . $this->alias : '');
			foreach ($this->joins as $join) {
				$sql .= ' INNER JOIN ' . $join['joinTable'] . ' ' . $join['joinAlias'] . ' ON ' . $join['on'];
			}
			if ($this->wheres !== []) {
				$sql .= ' WHERE ' . implode(' AND ', $this->wheres);
			}
			if ($this->orders !== []) {
				$sql .= ' ORDER BY ' . implode(', ', $this->orders);
			}
			if ($this->limit !== null) {
				$sql .= ' LIMIT ' . $this->limit;
			}
			return new ApiTokenTestResult($this->run($sql, static fn(\PDOStatement $stmt) => $stmt->fetchAll(\PDO::FETCH_ASSOC)));
		}

		public function executeStatement(): int {
			return $this->run($this->getWriteSql(), static fn(\PDOStatement $stmt) => $stmt->rowCount());
		}

		private function getWriteSql(): string {
			if ($this->mode === 'insert') {
				$cols = array_keys($this->insertValues);
				$vals = array_values($this->insertValues);
				return 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
			} elseif ($this->mode === 'update') {
				$sets = [];
				foreach ($this->setValues as $col => $val) {
					$sets[] = $col . ' = ' . $val;
				}
				$sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);
			} else {
				$sql = 'DELETE FROM ' . $this->table;
			}
			if ($this->wheres !== []) {
				$sql .= ' WHERE ' . implode(' AND ', $this->wheres);
			}
			return $sql;
		}

		private function run(string $sql, callable $consume): mixed {
			$stmt = $this->db->pdo->prepare($sql);
			foreach ($this->params as $key => $meta) {
				$val = $meta['value'];
				$type = $meta['type'];
				if ($type === \PDO::PARAM_BOOL) {
					$val = $val ? 1 : 0;
					$type = \PDO::PARAM_INT;
				}
				$stmt->bindValue($key, $val, $type);
			}
			try {
				$stmt->execute();
				return $consume($stmt);
			} finally {
				// Release SQLite's statement even when a competing write raises SQLITE_BUSY.
				$stmt->closeCursor();
			}
		}
	}

	final class ApiTokenTestDb implements IDBConnection {
		public \PDO $pdo;

		public function __construct(string $dsn = 'sqlite::memory:', bool $seed = true) {
			$this->pdo = new \PDO($dsn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 0]);
			if (!$seed) return;
			$this->pdo->exec('CREATE TABLE cobudget_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, user_id TEXT NOT NULL, is_default INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, owner_id TEXT NOT NULL, created_at INTEGER NOT NULL DEFAULT 0, color TEXT, is_archived INTEGER NOT NULL DEFAULT 0, workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_members (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, user_id TEXT NOT NULL, share_basis_points INTEGER NOT NULL DEFAULT 0, personal_workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT NOT NULL, workspace_id INTEGER NOT NULL, name TEXT NOT NULL, token_prefix TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, scopes_json TEXT NOT NULL, allow_personal INTEGER NOT NULL DEFAULT 1, project_mode TEXT NOT NULL DEFAULT "none", project_ids_json TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, expires_at INTEGER, last_used_at INTEGER, created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0)');

			$this->pdo->exec("INSERT INTO cobudget_workspaces (id, name, user_id, is_default, created_at) VALUES (1, 'Basis', 'alice', 1, 100), (2, 'Business', 'alice', 0, 101), (3, 'Basis', 'bob', 1, 102)");
			$this->pdo->exec("INSERT INTO cobudget_projects (id, name, owner_id, color, is_archived, workspace_id) VALUES (10, 'Household', 'alice', '#0082c9', 0, 1), (11, 'Archived area', 'alice', '#999999', 1, 1), (12, 'Shared from Bob', 'bob', '#2b8a3e', 0, 3), (13, 'Alice Business', 'alice', '#7950f2', 0, 2)");
			$this->pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points, personal_workspace_id) VALUES (10, 'alice', 10000, 1), (11, 'alice', 10000, 1), (12, 'bob', 5000, 3), (12, 'alice', 5000, 1), (13, 'alice', 10000, 2)");
		}

		public function getDatabaseProvider(): string {
			return 'sqlite';
		}

		public function getQueryBuilder(): ApiTokenTestQueryBuilder {
			return new ApiTokenTestQueryBuilder($this);
		}

		public function beginTransaction(): void {
			$this->pdo->beginTransaction();
		}

		public function commit(): void {
			$this->pdo->commit();
		}

		public function rollBack(): void {
			$this->pdo->rollBack();
		}

		public function inTransaction(): bool {
			return $this->pdo->inTransaction();
		}

		public function lastInsertId(string $sequenceName = ''): string|false {
			return $this->pdo->lastInsertId();
		}
	}

	$fixture = static function (): array {
		$db = new ApiTokenTestDb();
		$config = new ApiTokenTestConfig();
		$config->setAppValue('cobudget', ApiTokenService::APP_SETTING_ENABLED, 'yes');
		$config->setUserValue('alice', 'cobudget', 'enable_api_access', 'yes');
		$service = new ApiTokenService($db, $config, new ApiTokenTestL10n());
		return [$db, $config, $service];
	};

	$expectInvalid = static function (TestRunner $t, callable $fn, string $expectedNeedle): void {
		try {
			$fn();
		} catch (\InvalidArgumentException|\OutOfBoundsException|\DomainException|\UnexpectedValueException $e) {
			$t->assertContains($expectedNeedle, $e->getMessage(), 'Exception message should explain the validation failure');
			return;
		}
		throw new \RuntimeException('Expected exception containing: ' . $expectedNeedle);
	};

	return [
		'Global admin setting defaults off, blocks user activation and key mutations, and permits revoking existing keys' => static function (TestRunner $t) use ($expectInvalid): void {
			$db = new ApiTokenTestDb();
			$config = new ApiTokenTestConfig();
			$service = new ApiTokenService($db, $config, new ApiTokenTestL10n());
			$t->assertFalse($service->isGloballyEnabled(), 'Global admin API setting defaults to disabled');
			$t->assertFalse($service->isEnabledForUser('alice'), 'User API access is disabled while admin setting is unset');

			$expectInvalid($t, fn() => $service->setAccessEnabled('alice', true), 'disabled by the administrator');
			$t->assertFalse($service->isUserAccessEnabled('alice'), 'User preference remains off when admin setting is disabled');
			$expectInvalid($t, fn() => $service->create('alice', 'Blocked Key', 1, ['entries:read'], true, 'none'), 'disabled by the administrator');

			$service->setGloballyEnabled(true);
			$t->assertTrue($service->isGloballyEnabled(), 'Admin can enable API access globally');
			$service->setAccessEnabled('alice', true);
			$key = $service->create('alice', 'Admin Toggled Key', 1, ['entries:read'], true, 'none')['token'];
			$catalogEnabled = $service->catalogForUser('alice');
			$t->assertTrue($catalogEnabled['admin_enabled'], 'Catalog exposes admin_enabled = true');
			$t->assertTrue($catalogEnabled['enabled'], 'Catalog exposes enabled = true');

			$service->setGloballyEnabled(false);
			$t->assertFalse($service->isGloballyEnabled(), 'Admin can disable API access globally again');
			$t->assertFalse($service->isEnabledForUser('alice'), 'Effective user API access turns off immediately on admin disable');
			$catalogDisabled = $service->catalogForUser('alice');
			$t->assertFalse($catalogDisabled['admin_enabled'], 'Catalog exposes admin_enabled = false');
			$t->assertFalse($catalogDisabled['enabled'], 'Catalog exposes enabled = false when admin disabled');
			$t->assertNull($service->resolveAuthContextFromRow($db->pdo->query('SELECT * FROM cobudget_api_tokens WHERE id = ' . (int)$key['id'])->fetch(\PDO::FETCH_ASSOC), time()), 'Auth context resolution rejects active key when admin disabled API');

			$expectInvalid($t, fn() => $service->setAccessEnabled('alice', true), 'disabled by the administrator');
			$expectInvalid($t, fn() => $service->create('alice', 'Second Key', 1, ['entries:read'], true, 'none'), 'disabled by the administrator');

			$paused = $service->update('alice', $key['id'], $key['name'], $key['scopes'], true, 'none', [], null, false, null, $key['revision']);
			$t->assertFalse($paused['is_active'], 'User can still pause existing key while admin setting is off');
			$expectInvalid($t, fn() => $service->update('alice', $key['id'], $key['name'], $key['scopes'], true, 'none', [], null, true, null, $paused['revision']), 'disabled by the administrator');

			$service->setAccessEnabled('alice', false);
			$t->assertFalse($service->isUserAccessEnabled('alice'), 'Disabling personal access stays permitted while admin setting is off');
			$service->delete('alice', $key['id']);
			$t->assertSame([], $service->catalogForUser('alice')['tokens'], 'User can still delete existing key while admin setting is off');
		},
		'API access must be enabled before issuing or activating keys' => static function (TestRunner $t) use ($fixture, $expectInvalid): void {
			[, $config, $service] = $fixture();
			unset($config->userValues['alice:cobudget:enable_api_access']);
			$t->assertFalse($service->isEnabledForUser('alice'), 'API defaults off');
			$create = fn() => $service->create('alice', 'Automation', 1, ['entries:read'], true, 'none');
			$expectInvalid($t, $create, 'API access is disabled');
			$service->setAccessEnabled('alice', true);
			$key = $create()['token'];
			$service->setAccessEnabled('alice', false);
			$paused = $service->update('alice', $key['id'], $key['name'], $key['scopes'], true, 'none', [], null, false, null, $key['revision']);
			$t->assertFalse($paused['is_active'], 'A disabled account can still narrow access');
			$activate = fn() => $service->update('alice', $key['id'], $key['name'], $key['scopes'], true, 'none', [], null, true, null, $paused['revision']);
			$expectInvalid($t, $activate, 'API access is disabled');
			$service->delete('alice', $key['id']);
			$t->assertSame([], $service->catalogForUser('alice')['tokens'], 'A disabled account can still revoke a key');
		},
		'Two connections serialize area cleanup and settings edits for legacy and matrix keys' => static function (TestRunner $t) use ($expectInvalid): void {
			$path = tempnam(sys_get_temp_dir(), 'cobudget-keys-');
			try {
				$first = new ApiTokenTestDb('sqlite:' . $path);
				$second = new ApiTokenTestDb('sqlite:' . $path, false);
				$config = new ApiTokenTestConfig();
				$config->setAppValue('cobudget', ApiTokenService::APP_SETTING_ENABLED, 'yes');
				$config->setUserValue('alice', 'cobudget', 'enable_api_access', 'yes');
				$settings = new ApiTokenService($first, $config, new ApiTokenTestL10n());
				$cleanup = new ApiTokenService($second, $config, new ApiTokenTestL10n());
				$expectLocked = static function (callable $operation) use ($t): void {
					try {
						$operation();
					} catch (\PDOException $e) {
						$t->assertSame(5, $e->errorInfo[1], 'SQLite reports a competing writer');
						return;
					}
					throw new \RuntimeException('Concurrent key mutation was admitted');
				};
				foreach ([false, true] as $matrix) {
					$grants = $matrix ? ['10' => ['read'], '12' => ['write']] : null;
					$key = $settings->create('alice', 'Key', 1, ['entries:read', 'entries:write'], false, 'selected', [10, 12], null, $grants)['token'];
					$reduced = $matrix ? ['10' => ['read'], '12' => ['read']] : null;
					$edit = fn(bool $active, string $revision) => $settings->update(
						'alice', $key['id'], 'Key', ['entries:read'], false, 'selected', [10, 12], null, $active, $reduced, $revision
					);
					$first->beginTransaction();
					$paused = $edit(false, $key['revision']);
					$expectLocked(fn() => $cleanup->removeProjectReferences([10], 'alice'));
					$first->commit();
					$second->beginTransaction();
					$cleanup->removeProjectReferences([10], 'alice');
					$expectLocked(fn() => $edit(true, $paused['revision']));
					$second->commit();
					$expectInvalid($t, fn() => $edit(true, $paused['revision']), 'API key has changed');
					$current = $settings->catalogForUser('alice')['tokens'][0];
					$t->assertFalse($current['is_active'], 'Area cleanup preserves a committed pause');
					$t->assertSame([12], $current['project_ids'], 'A stale edit cannot put the removed target back');
					$t->assertSame(['entries:read'], $current['scopes'], 'Cleanup preserves a concurrent reduction of rights');
					$settings->delete('alice', $key['id']);
				}
			} finally {
				unlink($path);
			}
		},
		'ApiTokenService generates hashed tokens, stores prefix only, and lists workspace-scoped areas' => static function (TestRunner $t) use ($fixture): void {
			[$db, , $service] = $fixture();
			$created = $service->create('alice', '  Home   Shortcut  ', 1, ['entries:create', 'entries:read'], true, 'selected', [12], time() + 3600);
			$t->assertTrue(str_starts_with($created['plain_token'], 'cb_'), 'Plain token must start with cb_');
			$t->assertSame(67, strlen($created['plain_token']), 'Plain token must contain cb_ plus 64 hex chars');
			$t->assertSame('Home Shortcut', $created['token']['name'], 'Name whitespace should normalize cleanly');
			$t->assertSame(substr($created['plain_token'], 0, 11), $created['token']['token_prefix'], 'Prefix should show cb_ + first 8 hex chars');
			$t->assertSame(['entries:read', 'entries:create'], $created['token']['scopes'], 'Scopes should use canonical ordering');
			$t->assertSame([12], $created['token']['project_ids'], 'Shared area mapped via personal_workspace_id should be selectable');
			$t->assertFalse(array_key_exists('token_hash', $created['token']), 'API response must never expose token_hash');

			$storedRow = $db->pdo->query('SELECT token_hash, token_prefix FROM cobudget_api_tokens WHERE id = ' . (int)$created['token']['id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame(hash('sha256', $created['plain_token']), $storedRow['token_hash'], 'Database must store SHA-256 hash of plain token');
			$t->assertTrue($storedRow['token_hash'] !== $created['plain_token'], 'Database must never store plaintext token');

			$catalog = $service->catalogForUser('alice');
			$t->assertTrue($catalog['enabled'], 'API access is enabled for the key owner');
			$t->assertSame(2, count($catalog['workspaces']), 'Alice should see her two workspaces');
			$t->assertSame([11, 10, 12], array_column($catalog['areas_by_workspace']['1'], 'id'), 'Workspace 1 should include personal and shared areas mapped to workspace 1');
			$t->assertSame([13], array_column($catalog['areas_by_workspace']['2'], 'id'), 'Workspace 2 should isolate its own areas');
		},
		'ApiTokenService enforces workspace ownership, area restrictions, reports:read rule, and expiry' => static function (TestRunner $t) use ($fixture, $expectInvalid): void {
			[, $config, $service] = $fixture();
			$expectInvalid($t, fn () => $service->create('alice', 'Foreign WS', 3, ['entries:read'], true, 'none'), 'Workspace not found');
			$expectInvalid($t, fn () => $service->create('alice', 'Wrong Area WS', 1, ['entries:read'], true, 'selected', [13]), 'Area not found');
			$expectInvalid($t, fn () => $service->create('alice', 'Archived Area', 1, ['entries:read'], true, 'selected', [11]), 'Area not found');
			$expectInvalid($t, fn () => $service->create('alice', 'Empty Target', 1, ['entries:read'], false, 'none'), 'personal payments or at least one area');
			$expectInvalid($t, fn () => $service->create('alice', 'Partial Reports 1', 1, ['reports:read'], true, 'none'), 'personal payments and all areas');
			$expectInvalid($t, fn () => $service->create('alice', 'Partial Reports 2', 1, ['reports:read'], false, 'all'), 'personal payments and all areas');
			$expectInvalid($t, fn () => $service->create('alice', 'Partial Reports 3', 1, ['reports:read'], true, 'selected', [10]), 'personal payments and all areas');
			$expectInvalid($t, fn () => $service->create('alice', 'Past Expiry', 1, ['entries:read'], true, 'none', [], time() - 10), 'future');
			$expectInvalid($t, fn () => $service->create('alice', 'Unknown Scope', 1, ['admin:all'], true, 'none'), 'Invalid permission');

			$selectedFullReport = $service->create('alice', 'Selected Full Reports', 1, ['reports:read'], true, 'selected', [10, 12]);
			$t->assertSame(['reports:read'], $selectedFullReport['token']['scopes'], 'reports:read succeeds when personal and all active areas in the workspace are selected');

			$config->userValues['alice:cobudget:enable_projects'] = 'no';
			$noAreasReport = $service->create('alice', 'Personal Only Reports', 1, ['reports:read'], true, 'none');
			$t->assertSame(['reports:read'], $noAreasReport['token']['scopes'], 'reports:read succeeds without area selection when areas feature is disabled');
			unset($config->userValues['alice:cobudget:enable_projects']);

			$validReport = $service->create('alice', 'Full Reports', 1, ['reports:read'], true, 'all');
			$t->assertSame(['reports:read'], $validReport['token']['scopes'], 'reports:read succeeds when personal and all areas are permitted');

			$expectInvalid($t, fn () => $service->create('alice', 'full reports', 2, ['settings:read'], true, 'none'), 'already exists');
		},
		'ApiTokenService updates cleanly, blocks cross-user mutations, and narrows scopes on area removal' => static function (TestRunner $t) use ($fixture, $expectInvalid): void {
			[$db, , $service] = $fixture();
			$areaOnly = $service->create('alice', 'Area Only', 1, ['entries:create'], false, 'selected', [10]);
			$tokenId = (int)$areaOnly['token']['id'];

			$expectInvalid($t, fn () => $service->update('bob', $tokenId, 'Hijack', ['entries:read'], true, 'none'), 'API key not found');
			$expectInvalid($t, fn () => $service->delete('bob', $tokenId), 'API key not found');

			$updated = $service->update('alice', $tokenId, 'Area Updated', ['entries:read', 'entries:create'], false, 'selected', [10, 12], null, false);
			$t->assertSame('Area Updated', $updated['name'], 'Updated token name should persist');
			$t->assertSame(false, $updated['is_active'], 'Updated active flag should persist');
			$t->assertSame([10, 12], $updated['project_ids'], 'Updated project IDs should persist');

			// Re-enable and remove one area, then the last area -> must drop to none + paused.
			$service->update('alice', $tokenId, 'Area Updated', ['entries:read'], false, 'selected', [10, 12], null, true);
			$service->removeProjectReferences([10]);
			$afterFirst = $service->catalogForUser('alice')['tokens'][0];
			$t->assertSame('selected', $afterFirst['project_mode'], 'Project mode stays selected while one area remains');
			$t->assertSame([12], $afterFirst['project_ids'], 'Removed area ID should be pruned');
			$t->assertTrue($afterFirst['is_active'], 'Token with remaining selected area stays active');

			$service->removeProjectReferences([12], 'alice');
			$afterLast = $service->catalogForUser('alice')['tokens'][0];
			$t->assertSame('none', $afterLast['project_mode'], 'Project mode drops to none when last selected area is removed');
			$t->assertSame([], $afterLast['project_ids'], 'No project IDs remain after last area removal');
			$t->assertFalse($afterLast['is_active'], 'Token with no remaining target must pause automatically instead of widening access');

			// Pausing an existing reports:read key succeeds even after a new active area is added to the workspace,
			// while reactivating it still enforces full active area coverage.
			$reportsToken = $service->create('alice', 'Reports Toggle', 1, ['reports:read'], true, 'selected', [10, 12]);
			$db->pdo->exec("INSERT INTO cobudget_projects (id, name, owner_id, color, is_archived, workspace_id) VALUES (14, 'Extra Area', 'alice', '#222222', 0, 1)");
			$db->pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points, personal_workspace_id) VALUES (14, 'alice', 10000, 1)");
			$pausedReports = $service->update('alice', (int)$reportsToken['token']['id'], 'Reports Toggle', ['reports:read'], true, 'selected', [10, 12], null, false);
			$t->assertFalse($pausedReports['is_active'], 'Deactivating an existing key via inline toggle succeeds without widening or adding new areas');
			$expectInvalid(
				$t,
				fn () => $service->update('alice', (int)$reportsToken['token']['id'], 'Reports Toggle', ['reports:read'], true, 'selected', [10, 12], null, true),
				'personal payments and all areas',
			);

			$service->delete('alice', (int)$reportsToken['token']['id']);
			$service->delete('alice', $tokenId);
			$t->assertSame([], $service->catalogForUser('alice')['tokens'], 'Deleted key disappears from catalog');
		},
		'ApiTokenService supports per-area matrix permissions, reports:read Read requirement, and area cleanup' => static function (TestRunner $t) use ($fixture, $expectInvalid): void {
			[, , $service] = $fixture();
			$matrixCreated = $service->create(
				'alice',
				'Granular Matrix',
				1,
				['settings:read'],
				false,
				'none',
				[],
				null,
				[
					'personal' => ['read', 'create'],
					'10' => ['read'],
				],
			);
			$t->assertSame('matrix', $matrixCreated['token']['project_mode'], 'Matrix tokens persist project_mode = matrix');
			$t->assertSame(true, $matrixCreated['token']['allow_personal'], 'Matrix enables allow_personal when personal has actions');
			$t->assertSame([10], $matrixCreated['token']['project_ids'], 'Matrix tracks permitted active area IDs');
			$t->assertSame(['entries:read', 'entries:create', 'settings:read'], $matrixCreated['token']['scopes'], 'Matrix derives entry scopes cleanly alongside global scopes');
			$t->assertSame(['personal' => ['read', 'create'], '10' => ['read']], (array)$matrixCreated['token']['target_permissions'], 'Matrix permissions roundtrip verbatim');

			// Missing Read on active area 12 blocks reports:read even when personal + area 10 have Read
			$expectInvalid(
				$t,
				fn () => $service->create('alice', 'Partial Matrix Reports', 1, ['reports:read'], false, 'none', [], null, ['personal' => ['read'], '10' => ['read'], '12' => ['create']]),
				'personal payments and all areas',
			);

			// Once Read is enabled for personal and every active area (10 and 12), reports:read succeeds without needing archived 11
			$reportsMatrix = $service->create(
				'alice',
				'Full Read Matrix Reports',
				1,
				['reports:read'],
				false,
				'none',
				[],
				null,
				['personal' => ['read'], '10' => ['read'], '12' => ['read']],
			);
			$t->assertSame(['entries:read', 'reports:read'], $reportsMatrix['token']['scopes'], 'reports:read unlocks when Read is enabled for personal and all active areas');

			// Updating matrix to remove all personal actions and leave only area 10 -> removeProjectReferences([10]) empties and pauses key
			$areaOnlyMatrix = $service->update(
				'alice',
				(int)$matrixCreated['token']['id'],
				'Granular Matrix',
				[],
				false,
				'none',
				[],
				null,
				true,
				['10' => ['write']],
			);
			$t->assertSame(false, $areaOnlyMatrix['allow_personal'], 'Updating matrix clears allow_personal when personal is unchecked');
			$t->assertSame(['entries:write'], $areaOnlyMatrix['scopes'], 'Updating matrix derives updated entry scopes');

			$service->removeProjectReferences([10], 'alice');
			$tokensAfterPrune = $service->catalogForUser('alice')['tokens'];
			$pruned = null;
			foreach ($tokensAfterPrune as $item) {
				if ((int)$item['id'] === (int)$matrixCreated['token']['id']) {
					$pruned = $item;
					break;
				}
			}
			$t->assertTrue($pruned !== null, 'Matrix token stays visible after area removal');
			$t->assertSame([], $pruned['project_ids'], 'Removed area is pruned from matrix project_ids');
			$t->assertSame([], (array)$pruned['target_permissions'], 'Removed area is pruned from matrix target_permissions');
			$t->assertFalse($pruned['is_active'], 'Matrix token with zero remaining permissions pauses automatically');

			// Control characters and invalid UTF-8 in token names must be rejected
			$expectInvalid($t, fn () => $service->create('alice', "Line\nBreak Key", 1, ['entries:read'], true, 'none', [], null), 'invalid characters');
			$expectInvalid($t, fn () => $service->create('alice', "Bell\x07Key", 1, ['entries:read'], true, 'none', [], null), 'invalid characters');
		},
		'ApiTokenService checks stored hashes and emits secret-free audit logs' => static function (TestRunner $t): void {
			if (!interface_exists(\Psr\Log\LoggerInterface::class)) {
				eval('namespace Psr\Log; interface LoggerInterface {}');
			}
			$logger = new class implements \Psr\Log\LoggerInterface {
				/** @var list<array{level: string, message: string, context: array}> */
				public array $entries = [];
				public function emergency(\Stringable|string $message, array $context = []): void { $this->log('emergency', $message, $context); }
				public function alert(\Stringable|string $message, array $context = []): void { $this->log('alert', $message, $context); }
				public function critical(\Stringable|string $message, array $context = []): void { $this->log('critical', $message, $context); }
				public function error(\Stringable|string $message, array $context = []): void { $this->log('error', $message, $context); }
				public function warning(\Stringable|string $message, array $context = []): void { $this->log('warning', $message, $context); }
				public function notice(\Stringable|string $message, array $context = []): void { $this->log('notice', $message, $context); }
				public function info(\Stringable|string $message, array $context = []): void { $this->log('info', $message, $context); }
				public function debug(\Stringable|string $message, array $context = []): void { $this->log('debug', $message, $context); }
				public function log($level, \Stringable|string $message, array $context = []): void {
					$this->entries[] = ['level' => (string)$level, 'message' => (string)$message, 'context' => $context];
				}
			};

			$db = new ApiTokenTestDb();
			$config = new ApiTokenTestConfig();
			$service = new ApiTokenService($db, $config, new ApiTokenTestL10n(), null, $logger);

			$service->setGloballyEnabled(true, 'admin');
			$service->setAccessEnabled('alice', true);
			$created = $service->create('alice', 'Audit Key', 1, ['entries:read'], true, 'none');
			$plain = $created['plain_token'];
			$hash = hash('sha256', $plain);
			$t->assertTrue($service->tokenHashExists($hash), 'Active token hash exists in cobudget_api_tokens');
			$t->assertFalse($service->tokenHashExists(str_repeat('f', 64)), 'Unknown 64-hex hash is reported absent');
			$t->assertFalse($service->tokenHashExists('not-a-hash'), 'Malformed hash input is rejected safely');

			$paused = $service->update('alice', (int)$created['token']['id'], 'Audit Key', ['entries:read'], true, 'none', [], null, false, null, $created['token']['revision']);
			$t->assertTrue($service->tokenHashExists($hash), 'Paused token hash still exists so inactive keys do not brute-force lock IPs');

			$service->delete('alice', (int)$paused['id']);
			$t->assertFalse($service->tokenHashExists($hash), 'Deleted token hash no longer exists');

			$events = array_map(static fn(array $e): string => (string)($e['context']['event'] ?? ''), $logger->entries);
			$t->assertSame([
				'admin.api_access.changed',
				'user.api_access.changed',
				'api_token.created',
				'api_token.updated',
				'api_token.deleted',
			], $events, 'Lifecycle operations emit structured audit events');

			$serializedLogs = json_encode($logger->entries, JSON_THROW_ON_ERROR);
			$t->assertFalse(str_contains($serializedLogs, $plain), 'Audit logs must never contain plaintext API keys');
			$t->assertFalse(str_contains($serializedLogs, $hash), 'Audit logs must never contain full SHA-256 token hashes');
		},
	];
}

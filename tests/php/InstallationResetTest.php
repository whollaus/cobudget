<?php

declare(strict_types=1);

namespace OCP {
	if (!interface_exists(IDBConnection::class, false)) {
		interface IDBConnection {}
	}
	if (!interface_exists(IAppConfig::class, false)) {
		interface IAppConfig {}
	}
}

namespace OCP\DB\QueryBuilder {
	if (!interface_exists(IQueryBuilder::class, false)) {
		interface IQueryBuilder {}
	}
}

namespace CoBudget\Tests {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Service\InstallationResetService;
	use OCP\DB\QueryBuilder\IQueryBuilder;
	use OCP\IAppConfig;
	use OCP\IConfig;
	use OCP\IDBConnection;

	require_once __DIR__ . '/BackgroundJobServiceTest.php';
	require_once dirname(__DIR__, 2) . '/scripts/development/InstallationResetService.php';

	/** Executes the reset's SQL and transactional DDL against an isolated SQLite database. */
	final class InstallationResetDatabase implements IDBConnection {
		public \PDO $pdo;
		public string $provider = 'sqlite';
		public ?string $failDrop = null;
		public array $trace = [];
		public array $seed = [];
		public int $lockedQueries = 0;

		public function __construct(public string $prefix = 'test_') {
			$this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
			preg_match_all("/createTable\('([^']+)'\)/", file_get_contents(dirname(__DIR__, 2) . '/lib/Migration/Version000001Date20260713000000.php'), $matches);
			foreach (array_merge(array_unique($matches[1]), ['cobudget_templates', 'cobudgetish_entries', 'other_cobudget_entries']) as $table) {
				$name = $prefix . $table;
				$this->seed("CREATE TABLE $name (id INTEGER PRIMARY KEY, note TEXT)");
				$this->seed("INSERT INTO $name VALUES (1, 'alice'), (2, 'bob')");
			}
			$this->seed('CREATE TABLE separate_cobudget_entries (id INTEGER PRIMARY KEY, note TEXT)');
			$this->seed("INSERT INTO separate_cobudget_entries VALUES (1, 'other Nextcloud prefix')");
			$this->seed("CREATE TABLE {$prefix}jobs (id TEXT PRIMARY KEY, class TEXT, reserved_at INTEGER, argument TEXT)");
			$jobs = ['RecurringEntriesJob', 'RemindersJob', 'BackupJob', 'BudgetSnapshotJob', 'LegacyJob'];
			foreach ($jobs as $index => $job) {
				$this->seed("INSERT INTO {$prefix}jobs VALUES (?, ?, 0, ?)", [(string)(100 + $index), 'OCA\\CoBudget\\Cron\\' . $job, $index === 4 ? '[]' : 'null']);
			}
			$this->seed("INSERT INTO {$prefix}jobs VALUES ('201', ?, 0, 'null'), ('202', ?, 123, 'null')", ['OCA\\CoBudgetExtra\\Cron\\Job', 'OCA\\Other\\Cron\\Job']);
			$this->seed("CREATE TABLE {$prefix}appconfig (appid TEXT, configkey TEXT, configvalue TEXT)");
			$this->seed("INSERT INTO {$prefix}appconfig VALUES ('cobudget', 'enabled', 'no'), ('cobudget', 'installed_version', '0.4.0'), ('other', 'installed_version', '1'), ('cobudget_extra', 'enabled', 'yes')");
			$this->seed("CREATE TABLE {$prefix}preferences (userid TEXT, appid TEXT, configkey TEXT, configvalue TEXT)");
			$this->seed("INSERT INTO {$prefix}preferences VALUES ('alice', 'cobudget', 'setting', '1'), ('bob', 'cobudget', 'setting', '2'), ('alice', 'other', 'setting', '3')");
			$this->seed("CREATE TABLE {$prefix}migrations (app TEXT, version TEXT)");
			$this->seed("INSERT INTO {$prefix}migrations VALUES ('cobudget', '000001'), ('cobudget', '000002'), ('other', '000001')");
		}

		private function seed(string $sql, array $params = []): void {
			$this->seed[] = ['sql' => $sql, 'params' => $params];
			$this->pdo->prepare($sql)->execute($params);
		}

		public function getQueryBuilder(): InstallationResetQuery { return new InstallationResetQuery($this); }
		public function getDatabaseProvider(): string { return $this->provider; }
		public function escapeLikeParameter(string $value): string { return addcslashes($value, '\\_%'); }
		public function inTransaction(): bool { return $this->pdo->inTransaction(); }
		public function beginTransaction(): void { $this->trace[] = ['sql' => 'BEGIN', 'params' => []]; $this->pdo->beginTransaction(); }
		public function commit(): void { $this->trace[] = ['sql' => 'COMMIT', 'params' => []]; $this->pdo->commit(); }
		public function rollBack(): void { $this->trace[] = ['sql' => 'ROLLBACK', 'params' => []]; $this->pdo->rollBack(); }

		public function createSchema(): object {
			$names = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
			return new class($names) {
				public function __construct(private array $names) {}
				public function getTables(): array {
					return array_map(static fn(string $name): object => new class($name) {
						public function __construct(private string $name) {}
						public function getName(): string { return $this->name; }
					}, $this->names);
				}
			};
		}

		public function dropTable(string $table): void {
			if ($table === $this->failDrop) {
				throw new \RuntimeException('Injected DDL failure');
			}
			$this->execute('DROP TABLE "' . $this->prefix . $table . '"');
		}

		public function execute(string $sql, array $params = [], bool $lock = false): \PDOStatement {
			$this->trace[] = ['sql' => $sql . ($lock ? ' FOR UPDATE' : ''), 'params' => $params];
			$this->lockedQueries += (int)$lock;
			$result = $this->pdo->prepare($sql);
			$result->execute($params);
			return $result;
		}

		public function snapshot(): array {
			$rows = [];
			foreach ($this->createSchema()->getTables() as $table) {
				$name = $table->getName();
				$rows[$name] = $this->pdo->query('SELECT * FROM "' . $name . '"')->fetchAll(\PDO::FETCH_ASSOC);
			}
			return $rows;
		}
	}

	final class InstallationResetQuery implements IQueryBuilder {
		private string $table = '';
		private array $columns = ['*'];
		private string $where = '';
		private bool $delete = false;
		private bool $lock = false;
		private array $params = [];
		public function __construct(private InstallationResetDatabase $db) {}
		public function from(string $table): self { $this->table = $table; return $this; }
		public function select(string ...$columns): self { $this->columns = $columns; return $this; }
		public function delete(string $table): self { $this->delete = true; return $this->from($table); }
		public function where(string $where): self { $this->where = $where; return $this; }
		public function expr(): self { return $this; }
		public function eq(string $column, string $value): string { return '"' . $column . '" = ' . $value; }
		public function like(string $column, string $value): string { return '"' . $column . '" LIKE ' . $value . " ESCAPE '\\'"; }
		public function createFunction(string $value): string { return $value; }
		public function forUpdate(): self { $this->lock = true; return $this; }
		public function createNamedParameter(mixed $value): string {
			$name = ':p' . count($this->params);
			$this->params[$name] = $value;
			return $name;
		}
		public function executeQuery(): \PDOStatement { return $this->execute(); }
		public function executeStatement(): int { return $this->execute()->rowCount(); }
		private function execute(): \PDOStatement {
			$sql = $this->delete ? 'DELETE FROM ' : 'SELECT ' . implode(', ', $this->columns) . ' FROM ';
			$sql .= '"' . $this->db->prefix . $this->table . '"';
			if ($this->where !== '') { $sql .= ' WHERE ' . $this->where; }
			return $this->db->execute($sql, $this->params, $this->lock);
		}
	}

	final class InstallationResetConfig implements IConfig, IAppConfig {
		public bool $maintenance = true;
		public bool $failConfigDelete = false;
		public array $deletedApps = [];
		public function __construct(private InstallationResetDatabase $db) {}
		public function getSystemValue(string $key, mixed $default = null): mixed { return $key === 'dbtableprefix' ? $this->db->prefix : $default; }
		public function getSystemValueBool(string $key, bool $default = false): bool { return $key === 'maintenance' ? $this->maintenance : $default; }
		public function getAppValue(string $appName, string $key, string $default = ''): string { return $this->getValueString($appName, $key, $default); }
		public function getValueString(string $app, string $key, string $default = ''): string {
			$query = $this->db->pdo->prepare('SELECT configvalue FROM ' . $this->db->prefix . 'appconfig WHERE appid = ? AND configkey = ?');
			$query->execute([$app, $key]);
			$value = $query->fetchColumn();
			return $value === false ? $default : (string)$value;
		}
		public function deleteApp(string $app): void {
			if ($this->failConfigDelete) { throw new \RuntimeException('Injected app-config failure'); }
			$this->deletedApps[] = $app;
			$this->db->execute('DELETE FROM ' . $this->db->prefix . 'appconfig WHERE appid = ?', [$app]);
		}
	}

	$fixture = static function(): array {
		$db = new InstallationResetDatabase();
		$config = new InstallationResetConfig($db);
		return [$db, $config, new InstallationResetService($db, $config, $config)];
	};
	$expectFailure = static function(TestRunner $t, callable $operation, string $message): void {
		try { $operation(); } catch (\RuntimeException|\InvalidArgumentException $e) {
			$t->assertContains($message, $e->getMessage(), 'Reset should explain why it was refused');
			return;
		}
		throw new \RuntimeException('Reset should have refused this operation');
	};

	return [
		'Installation reset preview finds all current and legacy app tables without writes' => function(TestRunner $t) use ($fixture): void {
			[$db, $config, $service] = $fixture();
			$before = $db->snapshot();
			$report = $service->preview();
			$t->assertSame(18, count($report['tables']), 'All 17 current tables and the legacy templates table should be included');
			$t->assertSame(['preferences' => 2, 'migrations' => 2, 'appconfig' => 2, 'jobs' => 5], $report['metadata'], 'Preview should count only CoBudget metadata and jobs');
			$t->assertSame($before, $db->snapshot(), 'Preview must not mutate any table or data');
			$t->assertSame([], $config->deletedApps, 'Preview must not clear app configuration');
			$t->assertFalse($db->inTransaction(), 'Preview must not leave a transaction open');
		},
		'Installation reset requires confirmation, maintenance mode and a disabled app' => function(TestRunner $t) use ($fixture, $expectFailure): void {
			[$db, $config, $service] = $fixture();
			$before = $db->snapshot();
			$expectFailure($t, fn() => $service->reset('RESET-COBUDGET'), 'exakte Bestätigung');
			$config->maintenance = false;
			$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'Wartungsmodus');
			$config->maintenance = true;
			foreach (['yes', '["test-group"]'] as $enabled) {
				$db->pdo->prepare("UPDATE test_appconfig SET configvalue = ? WHERE appid = 'cobudget' AND configkey = 'enabled'")->execute([$enabled]);
				$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'deaktivieren');
			}
			$db->pdo->exec("UPDATE test_appconfig SET configvalue = 'no' WHERE appid = 'cobudget' AND configkey = 'enabled'");
			$t->assertSame($before, $db->snapshot(), 'Refused reset must preserve all data');
			$t->assertSame([], $db->trace, 'Preconditions must fail before any transaction or DDL');
		},
		'Installation reset refuses active jobs and platforms without transactional DDL' => function(TestRunner $t) use ($fixture, $expectFailure): void {
			[$db, $config, $service] = $fixture();
			$db->pdo->exec("UPDATE test_jobs SET reserved_at = 123 WHERE id = '100'");
			$before = $db->snapshot();
			$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'noch reserviert');
			$t->assertSame($before, $db->snapshot(), 'Reserved jobs must not be removed or modified');
			$t->assertFalse($db->inTransaction(), 'Reservation check must release its transaction');
			$db->provider = 'mysql';
			$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'PostgreSQL und SQLite');
			$t->assertSame($before, $db->snapshot(), 'Unsupported platforms must remain untouched');
		},
		'Installation reset removes only its installation state and can be repeated' => function(TestRunner $t) use ($fixture): void {
			[$db, $config, $service] = $fixture();
			$expected = $db->snapshot();
			foreach ($expected as $table => &$rows) {
				if (str_starts_with($table, 'test_cobudget_')) { unset($expected[$table]); continue; }
				$rows = array_values(array_filter($rows, static fn(array $row): bool =>
					($row['appid'] ?? $row['app'] ?? '') !== 'cobudget' && !str_starts_with($row['class'] ?? '', 'OCA\\CoBudget\\')));
			}
			unset($rows);
			$service->reset(InstallationResetService::CONFIRMATION);
			$t->assertSame($expected, $db->snapshot(), 'All CoBudget tables and metadata should disappear while unrelated rows and similarly named tables remain');
			$t->assertSame(['cobudget'], $config->deletedApps, 'App config should be deleted through the cache-aware Nextcloud API');
			$t->assertTrue($config->maintenance, 'Only the operator should end maintenance mode');
			$report = $service->reset(InstallationResetService::CONFIRMATION);
			$t->assertSame([], $report['tables'], 'A repeated reset should find no remaining CoBudget tables');
			$t->assertSame(0, array_sum($report['metadata']), 'A repeated reset should find no remaining CoBudget metadata');
			$t->assertSame($expected, $db->snapshot(), 'A repeated reset must preserve the rest of Nextcloud');
		},
		'Installation reset rolls back earlier drops and deletions on failure' => function(TestRunner $t) use ($fixture, $expectFailure): void {
			foreach (['ddl', 'config'] as $failure) {
				[$db, $config, $service] = $fixture();
				$before = $db->snapshot();
				if ($failure === 'ddl') { $db->failDrop = 'cobudget_entries'; } else { $config->failConfigDelete = true; }
				$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'Injected');
				$t->assertSame($before, $db->snapshot(), 'Failure must restore tables, rows and queue registrations');
				$t->assertFalse($db->inTransaction(), 'Failure must release the transaction');
			}
		},
		'Installation reset locks PostgreSQL queue rows and refuses nested transactions' => function(TestRunner $t) use ($fixture, $expectFailure): void {
			[$db, $config, $service] = $fixture();
			$db->provider = 'postgres';
			$db->beginTransaction();
			$expectFailure($t, fn() => $service->reset(InstallationResetService::CONFIRMATION), 'eigene Datenbanktransaktion');
			$t->assertTrue($db->inTransaction(), 'An outer transaction must remain under its owner control');
			$db->rollBack();
			$service->reset(InstallationResetService::CONFIRMATION);
			$t->assertSame(1, $db->lockedQueries, 'PostgreSQL queue rows must be locked before checking reservations and deleting');
		},
	];
}

<?php

declare(strict_types=1);

namespace {
	$ocpFiles = [
		'OCP\App\IAppManager' => static function (): void {
			if (!interface_exists('OCP\App\IAppManager')) {
				eval('namespace OCP\App; interface IAppManager { public function isEnabledForUser(string $appId, ?\OCP\IUser $user = null): bool; }');
			}
		},
		'OCP\IUser' => static function (): void {
			if (!interface_exists('OCP\IUser')) {
				eval('namespace OCP; interface IUser { public function getUID(): string; public function getDisplayName(): string; }');
			}
		},
		'OCP\IUserManager' => static function (): void {
			if (!interface_exists('OCP\IUserManager')) {
				eval('namespace OCP; interface IUserManager { public function get(string $uid): ?IUser; }');
			}
		},
		'OCP\IL10N' => static function (): void {
			if (!interface_exists('OCP\IL10N')) {
				eval('namespace OCP; interface IL10N {}');
			}
		},
		'OCP\L10N\IFactory' => static function (): void {
			if (!interface_exists('OCP\L10N\IFactory')) {
				eval('namespace OCP\L10N; interface IFactory {}');
			}
		},
		'OCP\IConfig' => static function (): void {
			if (!interface_exists('OCP\IConfig')) {
				eval('namespace OCP; interface IConfig { public function getAppValue(string $appName, string $key, string $default = ""): string; }');
			}
		},
		'OCP\IDBConnection' => static function (): void {
			if (!interface_exists('OCP\IDBConnection')) {
				eval('namespace OCP; interface IDBConnection {}');
			}
		},
		'OCP\DB\QueryBuilder\IQueryBuilder' => static function (): void {
			if (!interface_exists('OCP\DB\QueryBuilder\IQueryBuilder')) {
				eval('namespace OCP\DB\QueryBuilder; interface IQueryBuilder { public const PARAM_INT = \PDO::PARAM_INT; public const PARAM_STR = \PDO::PARAM_STR; public const PARAM_BOOL = \PDO::PARAM_BOOL; public const PARAM_NULL = \PDO::PARAM_NULL; public const PARAM_INT_ARRAY = 101; public const PARAM_STR_ARRAY = 102; }');
			}
		},
		'OCP\IRequest' => static function (): void {
			if (!interface_exists('OCP\IRequest')) {
				eval('namespace OCP; interface IRequest { public function getHeader(string $name): string; }');
			}
		},
		'OCP\ISession' => static function (): void {
			if (!interface_exists('OCP\ISession')) {
				eval('namespace OCP; interface ISession { public function get(string $key); }');
			}
		},
		'OCP\Security\Bruteforce\MaxDelayReached' => static function (): void {
			if (!class_exists('OCP\Security\Bruteforce\MaxDelayReached')) {
				eval('namespace OCP\Security\Bruteforce; class MaxDelayReached extends \Exception {}');
			}
		},
		'OCP\Security\Bruteforce\IThrottler' => static function (): void {
			if (!interface_exists('OCP\Security\Bruteforce\IThrottler')) {
				eval('namespace OCP\Security\Bruteforce; interface IThrottler { public function registerAttempt(string $action, string $ip, array $metadata = []): void; public function getDelay(string $ip, string $action = ""): int; public function resetDelay(string $ip, string $action): void; public function sleepDelayOrThrowOnMax(string $ip, string $action = "", int $maxDelay = 30): int; }');
			}
		},
		'OCP\Security\RateLimiting\IRateLimitExceededException' => static function (): void {
			if (!interface_exists('OCP\Security\RateLimiting\IRateLimitExceededException')) {
				eval('namespace OCP\Security\RateLimiting; interface IRateLimitExceededException extends \Throwable {}');
			}
		},
		'OCP\Security\RateLimiting\ILimiter' => static function (): void {
			if (!interface_exists('OCP\Security\RateLimiting\ILimiter')) {
				eval('namespace OCP\Security\RateLimiting; interface ILimiter {}');
			}
		},
		'OCP\AppFramework\Http' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http')) {
				eval('namespace OCP\AppFramework; class Http { public const STATUS_OK = 200; public const STATUS_CREATED = 201; public const STATUS_BAD_REQUEST = 400; public const STATUS_UNAUTHORIZED = 401; public const STATUS_FORBIDDEN = 403; public const STATUS_NOT_FOUND = 404; public const STATUS_CONFLICT = 409; public const STATUS_PAYLOAD_TOO_LARGE = 413; public const STATUS_UNSUPPORTED_MEDIA_TYPE = 415; public const STATUS_TOO_MANY_REQUESTS = 429; public const STATUS_INTERNAL_SERVER_ERROR = 500; public const STATUS_SERVICE_UNAVAILABLE = 503; }');
			}
		},
		'OCP\AppFramework\Http\Response' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\Response')) {
				eval('namespace OCP\AppFramework\Http; class Response { private int $status = 200; private array $headers = []; public function setStatus(int $status): void { $this->status = $status; } public function getStatus(): int { return $this->status; } public function addHeader(string $name, string $value): void { $this->headers[$name] = $value; } public function getHeaders(): array { return $this->headers; } }');
			}
		},
		'OCP\AppFramework\Http\JSONResponse' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\JSONResponse')) {
				eval('namespace OCP\AppFramework\Http; class JSONResponse extends Response { public function __construct(private array $data = [], int $statusCode = Http::STATUS_OK, array $headers = []) { $this->setStatus($statusCode); foreach ($headers as $k => $v) { $this->addHeader((string)$k, (string)$v); } } public function getData(): array { return $this->data; } public function setData(array $data): void { $this->data = $data; } }');
			}
		},
		'OCP\AppFramework\Controller' => static function (): void {
			if (!class_exists('OCP\AppFramework\Controller')) {
				eval('namespace OCP\AppFramework; class Controller { public mixed $request; public function __construct(public string $appName = "", ?\OCP\IRequest $request = null) { $this->request = $request; } }');
			}
		},
		'OCP\AppFramework\Middleware' => static function (): void {
			if (!class_exists('OCP\AppFramework\Middleware')) {
				eval('namespace OCP\AppFramework; abstract class Middleware {}');
			}
		},
		'OCP\AppFramework\Http\Attribute\PublicPage' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\Attribute\PublicPage')) {
				eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] final class PublicPage {}');
			}
		},
		'OCP\AppFramework\Http\Attribute\NoCSRFRequired' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\Attribute\NoCSRFRequired')) {
				eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {}');
			}
		},
		'OCP\AppFramework\Http\Attribute\NoSameSiteCookieRequired' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\Attribute\NoSameSiteCookieRequired')) {
				eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] final class NoSameSiteCookieRequired {}');
			}
		},
		'OCP\AppFramework\Http\Attribute\NoTwoFactorRequired' => static function (): void {
			if (!class_exists('OCP\AppFramework\Http\Attribute\NoTwoFactorRequired')) {
				eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] final class NoTwoFactorRequired {}');
			}
		},
		'OCP\Files\IRootFolder' => static function (): void {
			if (!interface_exists('OCP\Files\IRootFolder')) {
				eval('namespace OCP\Files; interface IRootFolder {}');
			}
		},
		'OCP\EventDispatcher\IEventDispatcher' => static function (): void {
			if (!interface_exists('OCP\EventDispatcher\IEventDispatcher')) {
				eval('namespace OCP\EventDispatcher; interface IEventDispatcher {}');
			}
		},
		'OCP\Notification\IManager' => static function (): void {
			if (!interface_exists('OCP\Notification\IManager')) {
				eval('namespace OCP\Notification; interface IManager {}');
			}
		},
		'Psr\Log\LoggerInterface' => static function (): void {
			if (!interface_exists('Psr\Log\LoggerInterface')) {
				eval('namespace Psr\Log; interface LoggerInterface {}');
			}
		},
	];
	foreach ($ocpFiles as $loader) {
		$loader();
	}
}

namespace CoBudget\Tests {
	$root = dirname(__DIR__, 2);
	require_once $root . '/lib/Exception/ApiV1HttpException.php';
	require_once $root . '/lib/Exception/AppBusyException.php';
	require_once $root . '/lib/Service/AppDataLock.php';
	require_once $root . '/lib/Service/ApiTokenService.php';
	require_once $root . '/lib/Service/ParticipantService.php';
	require_once $root . '/lib/Service/EntryShareCalculator.php';
	require_once $root . '/lib/Service/EntryShareService.php';
	require_once $root . '/lib/Service/EntryAttachmentProjectionService.php';
	require_once $root . '/lib/Service/EntryProjectionService.php';
	require_once $root . '/lib/Service/HashtagService.php';
	require_once $root . '/lib/Service/CalendarReminderService.php';
	require_once $root . '/lib/Service/DataChangeService.php';
	require_once $root . '/lib/Service/ProjectNotificationService.php';
	require_once $root . '/lib/Service/ApiV1AuthService.php';
	require_once $root . '/lib/Service/ApiV1EntriesService.php';
	require_once $root . '/lib/Service/ApiV1ReportsService.php';
	require_once $root . '/lib/Service/ApiV1SettingsService.php';
	require_once $root . '/lib/Controller/ApiV1Controller.php';
	require_once $root . '/lib/Middleware/AppDataLockMiddleware.php';

	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\ApiV1Controller;
	use OCA\CoBudget\Exception\ApiV1HttpException;
	use OCA\CoBudget\Exception\AppBusyException;
	use OCA\CoBudget\Middleware\AppDataLockMiddleware;
	use OCA\CoBudget\Service\ApiTokenService;
	use OCA\CoBudget\Service\ApiV1AuthService;
	use OCA\CoBudget\Service\ApiV1EntriesService;
	use OCA\CoBudget\Service\ApiV1ReportsService;
	use OCA\CoBudget\Service\ApiV1SettingsService;
	use OCA\CoBudget\Service\AppDataLock;
	use OCA\CoBudget\Service\CalendarReminderService;
	use OCA\CoBudget\Service\DataChangeService;
	use OCA\CoBudget\Service\EntryAttachmentProjectionService;
	use OCA\CoBudget\Service\EntryProjectionService;
	use OCA\CoBudget\Service\EntryShareService;
	use OCA\CoBudget\Service\HashtagService;
	use OCA\CoBudget\Service\ParticipantService;
	use OCA\CoBudget\Service\ProjectNotificationService;
	use OCP\App\IAppManager;
	use OCP\AppFramework\Http;
	use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
	use OCP\AppFramework\Http\Attribute\NoSameSiteCookieRequired;
	use OCP\AppFramework\Http\Attribute\NoTwoFactorRequired;
	use OCP\AppFramework\Http\Attribute\PublicPage;
	use OCP\AppFramework\Http\JSONResponse;
	use OCP\DB\QueryBuilder\IQueryBuilder;
	use OCP\EventDispatcher\IEventDispatcher;
	use OCP\Files\IRootFolder;
	use OCP\IConfig;
	use OCP\IDBConnection;
	use OCP\IL10N;
	use OCP\IRequest;
	use OCP\ISession;
	use OCP\IUser;
	use OCP\IUserManager;
	use OCP\L10N\IFactory as IL10NFactory;
	use OCP\Notification\IManager as INotificationManager;
	use OCP\Security\Bruteforce\IThrottler;
	use OCP\Security\Bruteforce\MaxDelayReached;
	use OCP\Security\RateLimiting\IRateLimitExceededException;
	use OCP\Security\RateLimiting\ILimiter;
	use Psr\Log\LoggerInterface;

	final class ApiV1TestResult {
		/** @var list<array<string, mixed>> */
		private array $rows;
		private int $index = 0;

		public function __construct(\PDOStatement $stmt) {
			$this->rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

		public function fetch(): array|false {
			return $this->rows[$this->index++] ?? false;
		}

		public function fetchOne(): mixed {
			$row = $this->fetch();
			return $row === false ? false : reset($row);
		}

		/** @return list<array<string, mixed>> */
		public function fetchAll(): array {
			return $this->rows;
		}

		public function closeCursor(): void {
		}
	}

	final class ApiV1TestExpr {
		public function eq(string $col, string $val): string { return $col . ' = ' . $val; }
		public function neq(string $col, string $val): string { return $col . ' != ' . $val; }
		public function lt(string $col, string $val): string { return $col . ' < ' . $val; }
		public function lte(string $col, string $val): string { return $col . ' <= ' . $val; }
		public function gt(string $col, string $val): string { return $col . ' > ' . $val; }
		public function gte(string $col, string $val): string { return $col . ' >= ' . $val; }
		public function like(string $col, string $val): string { return $col . ' LIKE ' . $val . " ESCAPE '\\'"; }
		public function isNull(string $col): string { return $col . ' IS NULL'; }
		public function isNotNull(string $col): string { return $col . ' IS NOT NULL'; }
		public function lower(string $col): string { return 'LOWER(' . $col . ')'; }
		public function literal(mixed $val): string { return is_int($val) ? (string)$val : "'" . str_replace("'", "''", (string)$val) . "'"; }
		public function in(string $col, string $vals): string { return $vals === '()' ? '1 = 0' : $col . ' IN ' . $vals; }
		public function notIn(string $col, string $vals): string { return $vals === '()' ? '1 = 1' : $col . ' NOT IN ' . $vals; }
		public function andX(string ...$parts): string {
			$parts = array_values(array_filter($parts, static fn(string $p): bool => $p !== ''));
			return $parts === [] ? '1 = 1' : '(' . implode(' AND ', $parts) . ')';
		}
		public function orX(string ...$parts): string {
			$parts = array_values(array_filter($parts, static fn(string $p): bool => $p !== ''));
			return $parts === [] ? '1 = 0' : '(' . implode(' OR ', $parts) . ')';
		}
	}

	final class ApiV1TestFunc {
		public function count(string $col = '*'): string { return 'COUNT(' . $col . ')'; }
		public function sum(string $col): string { return 'SUM(' . $col . ')'; }
		public function lower(string $col): string { return 'LOWER(' . $col . ')'; }
		public function coalesce(string ...$args): string { return 'COALESCE(' . implode(', ', $args) . ')'; }
	}

	final class ApiV1TestQueryBuilder implements IQueryBuilder {
		private string $mode = 'select';
		private string $table = '';
		private string $alias = '';
		/** @var list<string> */
		private array $columns = ['*'];
		/** @var list<array{type: string, joinTable: string, joinAlias: string, on: string}> */
		private array $joins = [];
		/** @var list<string> */
		private array $wheres = [];
		/** @var list<string> */
		private array $groups = [];
		/** @var list<string> */
		private array $orders = [];
		private ?int $limit = null;
		private ?int $offset = null;
		/** @var array<string, string> */
		private array $insertValues = [];
		/** @var array<string, string> */
		private array $setValues = [];
		/** @var array<string, array{value: mixed, type: int}> */
		private array $params = [];

		public function __construct(private ApiV1TestDb $db) {
		}

		public function expr(): ApiV1TestExpr { return new ApiV1TestExpr(); }
		public function func(): ApiV1TestFunc { return new ApiV1TestFunc(); }
		public function createFunction(string $fn): string { return $fn; }

		public function createNamedParameter(mixed $value, int $type = \PDO::PARAM_STR): string {
			if ($type === IQueryBuilder::PARAM_INT_ARRAY || $type === IQueryBuilder::PARAM_STR_ARRAY) {
				if (!is_array($value) || $value === []) {
					return '()';
				}
				$placeholders = [];
				$elemType = $type === IQueryBuilder::PARAM_INT_ARRAY ? \PDO::PARAM_INT : \PDO::PARAM_STR;
				foreach ($value as $item) {
					$key = ':p' . count($this->params);
					$this->params[$key] = ['value' => $item, 'type' => $elemType];
					$placeholders[] = $key;
				}
				return '(' . implode(', ', $placeholders) . ')';
			}
			$key = ':p' . count($this->params);
			$this->params[$key] = ['value' => $value, 'type' => $type];
			return $key;
		}

		public function select(string ...$columns): self {
			$this->mode = 'select';
			$this->columns = $columns;
			return $this;
		}

		public function selectAlias(string $expr, string $alias): self {
			$this->mode = 'select';
			$this->columns = [$expr . ' AS ' . $alias];
			return $this;
		}

		public function addSelect(string ...$columns): self {
			array_push($this->columns, ...$columns);
			return $this;
		}

		public function from(string $table, string $alias = ''): self {
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function innerJoin(string $fromAlias, string $joinTable, string $joinAlias, string $on): self {
			$this->joins[] = ['type' => 'INNER JOIN', 'joinTable' => $joinTable, 'joinAlias' => $joinAlias, 'on' => $on];
			return $this;
		}

		public function leftJoin(string $fromAlias, string $joinTable, string $joinAlias, string $on): self {
			$this->joins[] = ['type' => 'LEFT JOIN', 'joinTable' => $joinTable, 'joinAlias' => $joinAlias, 'on' => $on];
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

		public function update(string $table, string $alias = ''): self {
			$this->mode = 'update';
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function set(string $col, string $val): self {
			$this->setValues[$col] = $val;
			return $this;
		}

		public function delete(string $table, string $alias = ''): self {
			$this->mode = 'delete';
			$this->table = $table;
			$this->alias = $alias;
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

		public function orWhere(string $expr): self {
			if ($this->wheres === []) {
				$this->wheres = [$expr];
			} else {
				$prev = implode(' AND ', $this->wheres);
				$this->wheres = ['((' . $prev . ') OR (' . $expr . '))'];
			}
			return $this;
		}

		public function groupBy(string ...$cols): self {
			$this->groups = $cols;
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

		public function setFirstResult(int $offset): self {
			$this->offset = $offset;
			return $this;
		}

		public function setMaxResults(int $limit): self {
			$this->limit = $limit;
			return $this;
		}

		public function executeQuery(): ApiV1TestResult {
			$sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table . ($this->alias !== '' ? ' ' . $this->alias : '');
			foreach ($this->joins as $join) {
				$sql .= ' ' . $join['type'] . ' ' . $join['joinTable'] . ' ' . $join['joinAlias'] . ' ON ' . $join['on'];
			}
			if ($this->wheres !== []) {
				$sql .= ' WHERE ' . implode(' AND ', $this->wheres);
			}
			if ($this->groups !== []) {
				$sql .= ' GROUP BY ' . implode(', ', $this->groups);
			}
			if ($this->orders !== []) {
				$sql .= ' ORDER BY ' . implode(', ', $this->orders);
			}
			if ($this->limit !== null) {
				$sql .= ' LIMIT ' . $this->limit;
			} elseif ($this->offset !== null && $this->offset > 0) {
				$sql .= ' LIMIT -1';
			}
			if ($this->offset !== null) {
				$sql .= ' OFFSET ' . $this->offset;
			}
			return new ApiV1TestResult($this->run($sql));
		}

		public function executeStatement(): int {
			if ($this->mode === 'insert') {
				$cols = array_keys($this->insertValues);
				$vals = array_values($this->insertValues);
				$sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
				return $this->run($sql)->rowCount();
			}
			if ($this->mode === 'update') {
				$sets = [];
				foreach ($this->setValues as $col => $val) {
					$sets[] = $col . ' = ' . $val;
				}
				$sql = 'UPDATE ' . $this->table . ($this->alias !== '' ? ' ' . $this->alias : '') . ' SET ' . implode(', ', $sets);
				if ($this->wheres !== []) {
					$sql .= ' WHERE ' . implode(' AND ', $this->wheres);
				}
				return $this->run($sql)->rowCount();
			}
			$sql = 'DELETE FROM ' . $this->table . ($this->alias !== '' ? ' ' . $this->alias : '');
			if ($this->wheres !== []) {
				$sql .= ' WHERE ' . implode(' AND ', $this->wheres);
			}
			return $this->run($sql)->rowCount();
		}

		private function run(string $sql): \PDOStatement {
			$stmt = $this->db->pdo->prepare($sql);
			foreach ($this->params as $key => $meta) {
				$val = $meta['value'];
				$type = $meta['type'];
				if ($type === \PDO::PARAM_BOOL) {
					$val = $val ? 1 : 0;
					$type = \PDO::PARAM_INT;
				} elseif ($type === \PDO::PARAM_NULL || $val === null) {
					$val = null;
					$type = \PDO::PARAM_NULL;
				}
				$stmt->bindValue($key, $val, $type);
			}
			$stmt->execute();
			return $stmt;
		}
	}

	final class ApiV1TestDb implements IDBConnection {
		public \PDO $pdo;

		public ?\Closure $beforeNextTransaction = null;

		public function __construct(string $dsn = 'sqlite::memory:', bool $initialize = true) {
			$this->pdo = new \PDO($dsn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
			if (!$initialize) {
				return;
			}
			$this->pdo->exec('CREATE TABLE cobudget_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, user_id TEXT NOT NULL, is_default INTEGER NOT NULL DEFAULT 0, is_hidden INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, owner_id TEXT NOT NULL, created_at INTEGER NOT NULL DEFAULT 0, color TEXT, is_archived INTEGER NOT NULL DEFAULT 0, workspace_id INTEGER, hidden_category_ids TEXT, hidden_payment_partner_ids TEXT)');
			$this->pdo->exec('CREATE TABLE cobudget_members (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, user_id TEXT NOT NULL, share_basis_points INTEGER NOT NULL DEFAULT 0, expense_rounding_units INTEGER NOT NULL DEFAULT 0, income_rounding_units INTEGER NOT NULL DEFAULT 0, personal_workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT NOT NULL, workspace_id INTEGER NOT NULL, name TEXT NOT NULL, token_prefix TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, scopes_json TEXT NOT NULL, allow_personal INTEGER NOT NULL DEFAULT 1, project_mode TEXT NOT NULL DEFAULT "none", project_ids_json TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, expires_at INTEGER, last_used_at INTEGER, created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, code TEXT, parent_category_id INTEGER, is_global INTEGER NOT NULL DEFAULT 0, user_id TEXT, workspace_id INTEGER, icon TEXT DEFAULT "Tag", type TEXT NOT NULL DEFAULT "expense", project_id INTEGER, is_hidden INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_payment_partners (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, number TEXT, is_global INTEGER NOT NULL DEFAULT 0, user_id TEXT, workspace_id INTEGER, icon TEXT DEFAULT "Store", type TEXT NOT NULL DEFAULT "expense", project_id INTEGER, is_hidden INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, import_key TEXT, user_id TEXT NOT NULL, created_by TEXT, project_id INTEGER, entry_kind TEXT NOT NULL DEFAULT "personal", source_entry_id INTEGER, is_locked INTEGER NOT NULL DEFAULT 0, allocation_basis_points INTEGER, type TEXT NOT NULL, amount TEXT, amount_cents INTEGER NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT "EUR", date INTEGER NOT NULL, category_id INTEGER, description TEXT DEFAULT "", payment_partner_id INTEGER, split_mode TEXT DEFAULT "project_shares", split_user_id TEXT, is_settled INTEGER NOT NULL DEFAULT 0, settled_at INTEGER, settlement_id INTEGER, recurrence_interval TEXT, recurrence_multiplier INTEGER, recurrence_next_date INTEGER, recurrence_end_date INTEGER, is_subscription INTEGER NOT NULL DEFAULT 0, is_fixed_cost INTEGER NOT NULL DEFAULT 0, is_child_related INTEGER NOT NULL DEFAULT 0, is_important INTEGER NOT NULL DEFAULT 0, needs_review INTEGER NOT NULL DEFAULT 0, is_tax_relevant INTEGER NOT NULL DEFAULT 0, is_insurance INTEGER NOT NULL DEFAULT 0, reminder_date INTEGER, planned_notified INTEGER NOT NULL DEFAULT 0, reminder_notified INTEGER NOT NULL DEFAULT 0, reminder_text TEXT, recurrence_parent_id INTEGER, recurrence_series_id INTEGER, workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER NOT NULL, user_id TEXT NOT NULL, share_basis_points INTEGER NOT NULL, amount_cents INTEGER NOT NULL, personal_entry_id INTEGER, rounding_bucket TEXT, rounding_residual_units INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_history (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER NOT NULL, workspace_id INTEGER, project_id INTEGER, changed_by TEXT NOT NULL, changed_by_display_name TEXT NOT NULL, changed_at INTEGER NOT NULL, change_group TEXT NOT NULL, field TEXT NOT NULL, old_value TEXT, new_value TEXT, old_display TEXT, new_display TEXT)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER NOT NULL, owner_user_id TEXT NOT NULL, file_path TEXT NOT NULL, file_id INTEGER, source_attachment_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_hashtags (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER NOT NULL, normalized_name TEXT NOT NULL, display_name TEXT NOT NULL, created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_hashtags (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER NOT NULL, hashtag_id INTEGER NOT NULL, workspace_id INTEGER NOT NULL, created_at INTEGER NOT NULL DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_budget_goals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT NOT NULL, workspace_id INTEGER NOT NULL, name TEXT NOT NULL, amount_cents INTEGER NOT NULL DEFAULT 0, period TEXT NOT NULL DEFAULT "month", mode TEXT NOT NULL DEFAULT "flexible", criteria_json TEXT NOT NULL DEFAULT "{}")');
			$this->pdo->exec('CREATE TABLE cobudget_deleted_users (id INTEGER PRIMARY KEY AUTOINCREMENT, tombstone_id TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, created_at INTEGER NOT NULL DEFAULT 0)');

			// Seed alice (ws 1, ws 2), bob (ws 3), solo area 10 (alice), archived area 11 (alice), shared area 12 (alice + bob, ws 3), unauthorized/other-ws area 13 (alice, ws 2), shrunk solo area 14 (bob created or alice transferred: workspace_id = 3 on project row while alice owns/mapped to ws 1!)
			$this->pdo->exec("INSERT INTO cobudget_workspaces (id, name, user_id, is_default, created_at) VALUES (1, 'Basis', 'alice', 1, 100), (2, 'Business', 'alice', 0, 101), (3, 'Basis', 'bob', 1, 102)");
			$this->pdo->exec("INSERT INTO cobudget_projects (id, name, owner_id, color, is_archived, workspace_id) VALUES (10, 'Household', 'alice', '#0082c9', 0, 1), (11, 'Archived area', 'alice', '#999999', 1, 1), (12, 'Shared Home', 'bob', '#2b8a3e', 0, 3), (13, 'Alice Business', 'alice', '#7950f2', 0, 2), (14, 'Shrunk Solo', 'bob', '#e67700', 0, 3)");
			$this->pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points, personal_workspace_id) VALUES (10, 'alice', 10000, 1), (11, 'alice', 10000, 1), (12, 'bob', 5000, 3), (12, 'alice', 5000, 1), (13, 'alice', 10000, 2), (14, 'alice', 10000, 1)");
			$this->pdo->exec("INSERT INTO cobudget_categories (id, name, is_global, user_id, workspace_id, icon, type, project_id, is_hidden) VALUES (1, 'Lebensmittel', 1, NULL, NULL, 'Cart', 'expense', NULL, 0), (2, 'Versteckt', 1, NULL, NULL, 'Lock', 'expense', NULL, 1), (3, 'Gehalt', 0, 'alice', 1, 'Cash', 'income', NULL, 0), (4, 'Haushalt Kat', 0, 'alice', 1, 'Home', 'expense', 10, 0), (5, 'Shared Kat', 0, 'bob', 3, 'Users', 'expense', 12, 0), (6, 'Archived Kat', 0, 'alice', 1, 'Archive', 'expense', 11, 0), (7, 'Other User Personal Kat', 0, 'bob', 1, 'Lock', 'expense', NULL, 0)");
			$this->pdo->exec("INSERT INTO cobudget_payment_partners (id, name, is_global, user_id, workspace_id, icon, type, project_id, is_hidden) VALUES (1, 'Supermarkt', 1, NULL, NULL, 'Store', 'expense', NULL, 0), (2, 'Personal Dealer', 0, 'alice', 1, 'User', 'expense', NULL, 0), (3, 'Shared Dealer', 0, 'bob', 3, 'Users', 'expense', 12, 0), (4, 'Other User Dealer', 0, 'bob', 1, 'Lock', 'expense', NULL, 0)");
		}

		public function getDatabaseProvider(): string { return 'sqlite'; }
		public function getQueryBuilder(): ApiV1TestQueryBuilder { return new ApiV1TestQueryBuilder($this); }
		public function beginTransaction(): void {
			$before = $this->beforeNextTransaction;
			$this->beforeNextTransaction = null;
			if ($before !== null) {
				$before();
			}
			$this->pdo->beginTransaction();
		}
		public function commit(): void { $this->pdo->commit(); }
		public function rollBack(): void { $this->pdo->rollBack(); }
		public function inTransaction(): bool { return $this->pdo->inTransaction(); }
		public function lastInsertId(string $sequenceName = ''): string|false { return $this->pdo->lastInsertId(); }
	}

	final class ApiV1TestConfig implements IConfig {
		/** @var array<string, string> */
		public array $appValues = [
			'enable_api_access' => 'yes',
		];
		/** @var array<string, array<string, string>> */
		public array $userValues = [
			'alice' => [
				'enable_api_access' => 'yes',
				'enable_projects' => 'yes',
				'enable_budget_goals' => 'yes',
				'enable_reminders' => 'yes',
				'currency' => 'EUR',
				'entries_per_page' => '100',
				'default_start_page' => 'personal',
				'default_calendar_view' => 'month',
				'webhook_url' => 'https://secret.example.invalid/hook',
			],
			'bob' => [
				'enable_api_access' => 'no',
				'enable_projects' => 'yes',
				'currency' => 'USD',
			],
		];

		public function getUserValue(string $userId, string $appName, string $key, mixed $default = null): mixed {
			return $this->userValues[$userId][$key] ?? $default;
		}
		public function setUserValue(string $userId, string $appName, string $key, string $value): void {
			$this->userValues[$userId][$key] = $value;
		}
		public function deleteUserValue(string $userId, string $appName, string $key): void {
			unset($this->userValues[$userId][$key]);
		}
		public function getAppValue(string $appName, string $key, string $default = ''): string {
			return $this->appValues[$key] ?? $default;
		}
		public function setAppValue(string $appName, string $key, string $value): void {
			$this->appValues[$key] = $value;
		}
	}

	final class ApiV1TestL10n implements IL10N {
		public function t(string $text, array $parameters = []): string {
			foreach ($parameters as $i => $p) {
				$text = str_replace('%s', (string)$p, $text);
			}
			return $text;
		}
	}

	final class ApiV1TestL10nFactory implements IL10NFactory {
		public function get(string $app, ?string $lang = null, ?string $user = null): IL10N {
			return new ApiV1TestL10n();
		}
		public function getUserLanguage(?IUser $user = null): string {
			return 'en';
		}
	}

	final class ApiV1TestUser implements IUser {
		public function __construct(
			private string $uid,
			private string $displayName,
			private bool $enabled = true,
		) {}
		public function getUID(): string { return $this->uid; }
		public function getDisplayName(): string { return $this->displayName; }
		public function isEnabled(): bool { return $this->enabled; }
	}

	final class ApiV1TestUserManager implements IUserManager {
		/** @var array<string, ApiV1TestUser> */
		public array $users = [];
		public function __construct() {
			$this->users['alice'] = new ApiV1TestUser('alice', 'Alice Example', true);
			$this->users['bob'] = new ApiV1TestUser('bob', 'Bob Example', true);
		}
		public function get(string $uid): ?IUser {
			return $this->users[$uid] ?? null;
		}
	}

	final class ApiV1TestAppManager implements IAppManager {
		public bool $enabled = true;
		public function isEnabledForUser(string $appId, ?IUser $user = null): bool {
			return $this->enabled;
		}
	}

	final class ApiV1TestThrottler implements IThrottler {
		public int $attempts = 0;
		public bool $throwMaxDelay = false;
		public int $delaySeconds = 0;
		public function registerAttempt(string $action, string $ip, array $metadata = []): void { $this->attempts++; }
		public function getDelay(string $ip, string $action = ''): int { return $this->delaySeconds; }
		public function resetDelay(string $ip, string $action): void { $this->delaySeconds = 0; }
		public function sleepDelayOrThrowOnMax(string $ip, string $action = '', int $maxDelay = 30): int {
			if ($this->throwMaxDelay) {
				throw new MaxDelayReached('Max delay reached');
			}
			return $this->delaySeconds;
		}
	}

	final class ApiV1TestRateLimitException extends \RuntimeException implements IRateLimitExceededException {}

	final class ApiV1TestLimiter implements ILimiter {
		public ?string $failPrefix = null;
		/** @var list<array{identifier: string, limit: int, period: int, ip: ?string}> */
		public array $checks = [];
		public function registerAnonRequest(string $identifier, int $limit, int $period, ?string $ip = null): void {
			$this->checks[] = ['identifier' => $identifier, 'limit' => $limit, 'period' => $period, 'ip' => $ip];
			if ($this->failPrefix !== null && str_starts_with($identifier, $this->failPrefix)) {
				throw new ApiV1TestRateLimitException('Rate limit exceeded');
			}
		}
	}

	final class ApiV1TestRequest implements IRequest {
		public array $headers = [];
		public array $server = [];
		public array $cookies = [];
		public array $post = [];
		public array $urlParams = [];
		public string $method = 'GET';
		public string $remoteAddress = '127.0.0.1';
		/** @var array<string, mixed> */
		private array $getParams = [];

		/** @param array<string, mixed> $params */
		public function setGet(array $params): void {
			$this->getParams = $params;
		}

		public function __get(string $name): mixed {
			if ($name === 'get') {
				if (strtoupper($this->method) !== 'GET') {
					throw new \LogicException(sprintf('get cannot be accessed in a %s request.', $this->method));
				}
				return $this->getParams;
			}
			if ($name === 'cookies') {
				return $this->cookies;
			}
			return null;
		}

		public function getHeader(string $name): string {
			$target = strtolower($name);
			foreach ($this->headers as $k => $v) {
				if (strtolower((string)$k) === $target) {
					return (string)$v;
				}
			}
			return '';
		}
		public function getMethod(): string { return $this->method; }
		public function getRemoteAddress(): string { return $this->remoteAddress; }
	}

	final class ApiV1TestAuthService extends ApiV1AuthService {
		public string $rawBody = '';
		public function authenticateFixture(string $bucket, ?int $now = null): array {
			return $this->authenticate($this->admitCredential($bucket, $now), $now);
		}
		protected function openRawInputStream(): mixed {
			$stream = fopen('php://temp', 'r+b');
			if ($stream === false) {
				return false;
			}
			fwrite($stream, $this->rawBody);
			rewind($stream);
			return $stream;
		}
	}

	final class ApiV1TestLogger implements LoggerInterface {
		/** @var list<array{level: string, message: string, context: array}> */
		public array $logs = [];
		public function emergency(\Stringable|string $message, array $context = []): void { $this->log('emergency', $message, $context); }
		public function alert(\Stringable|string $message, array $context = []): void { $this->log('alert', $message, $context); }
		public function critical(\Stringable|string $message, array $context = []): void { $this->log('critical', $message, $context); }
		public function error(\Stringable|string $message, array $context = []): void { $this->log('error', $message, $context); }
		public function warning(\Stringable|string $message, array $context = []): void { $this->log('warning', $message, $context); }
		public function notice(\Stringable|string $message, array $context = []): void { $this->log('notice', $message, $context); }
		public function info(\Stringable|string $message, array $context = []): void { $this->log('info', $message, $context); }
		public function debug(\Stringable|string $message, array $context = []): void { $this->log('debug', $message, $context); }
		public function log($level, \Stringable|string $message, array $context = []): void {
			$this->logs[] = ['level' => (string)$level, 'message' => (string)$message, 'context' => $context];
		}
	}

	final class ApiV1TestRootFolder implements IRootFolder {
		/** @var list<string> */
		public array $deletedPaths = [];
		public function getUserFolder(string $userId): mixed {
			return new class($this->deletedPaths) {
				public function __construct(private array &$deletedPaths) {}
				public function get(string $path): object {
					return new class($path, $this->deletedPaths) {
						public function __construct(private string $path, private array &$deletedPaths) {}
						public function delete(): void { $this->deletedPaths[] = $this->path; }
					};
				}
			};
		}
	}

	final class ApiV1TestDispatcher implements IEventDispatcher {
		/** @var list<object> */
		public array $events = [];
		public function dispatchTyped(object $event): void { $this->events[] = $event; }
	}

	final class ApiV1TestNotificationManager implements INotificationManager {
		public function createNotification(): mixed {
			return new class {
				public function setApp(string $app): self { return $this; }
				public function setUser(string $user): self { return $this; }
				public function setDateTime(\DateTimeInterface $dt): self { return $this; }
				public function setObject(string $type, string $id): self { return $this; }
				public function setSubject(string $subject, array $params = []): self { return $this; }
				public function setMessage(string $message, array $params = []): self { return $this; }
				public function setLink(string $link): self { return $this; }
			};
		}
		public function notify(mixed $notification): void {}
	}

	final class ApiV1TestSession implements ISession {
		/** @var array<string, mixed> */
		public array $values = [];
		public function get(string $key): mixed {
			return $this->values[$key] ?? null;
		}
	}

	$buildFixture = static function (string $dsn = 'sqlite::memory:'): array {
		$db = new ApiV1TestDb($dsn);
		$config = new ApiV1TestConfig();
		$l10nFactory = new ApiV1TestL10nFactory();
		$tokenService = new ApiTokenService($db, $config, $l10nFactory->get('cobudget'));
		$request = new ApiV1TestRequest();
		$session = new ApiV1TestSession();
		$userManager = new ApiV1TestUserManager();
		$appManager = new ApiV1TestAppManager();
		$throttler = new ApiV1TestThrottler();
		$limiter = new ApiV1TestLimiter();
		$logger = new ApiV1TestLogger();
		$rootFolder = new ApiV1TestRootFolder();

		$authService = new ApiV1TestAuthService(
			$request,
			$userManager,
			$appManager,
			$l10nFactory,
			$throttler,
			$limiter,
			$tokenService,
			$logger,
			$session,
		);
		$participants = new ParticipantService($db, $userManager);
		$shares = new EntryShareService($db);
		$hashtags = new HashtagService($db);
		$attachments = new EntryAttachmentProjectionService($db, $rootFolder, $config, $participants);
		$projections = new EntryProjectionService($db, $shares, $hashtags, $attachments);
		$calendarReminders = new class extends CalendarReminderService {
			public function __construct() {}
			public function syncUser(string $userId): void {}
		};
		$dataChanges = new DataChangeService($db, $config, $logger, $calendarReminders);
		$notifications = new class extends ProjectNotificationService {
			public function __construct() {}
			public function notifyEntryCreated(
				int $projectId,
				int $workspaceId,
				int $entryId,
				string $actorUserId,
				string $entryUserId,
				string $type,
				int $amountCents,
				string $currency,
				string $description = '',
			): void {}
		};

		$entriesService = new ApiV1EntriesService(
			$db,
			$config,
			$hashtags,
			$shares,
			$projections,
			$participants,
			$dataChanges,
			$notifications,
			$rootFolder,
			$logger,
		);
		$reportsService = new ApiV1ReportsService($db, $config);
		$settingsService = new ApiV1SettingsService($config, null, $dataChanges, $logger);
		$appDataLock = new class extends AppDataLock {
			public int $enterCount = 0;
			public int $leaveCount = 0;
			public bool $throwBusy = false;
			public ?\Closure $onEnter = null;
			public function __construct() {}
			public function enter(bool $exclusive = false): void {
				$this->enterCount++;
				if ($this->throwBusy) {
					$this->enterCount--;
					throw new AppBusyException();
				}
				if ($this->onEnter !== null) {
					($this->onEnter)();
				}
			}
			public function leave(): void {
				$this->leaveCount++;
			}
		};
		$controller = new ApiV1Controller(
			'cobudget',
			$request,
			$authService,
			$entriesService,
			$reportsService,
			$settingsService,
			$appDataLock,
			$logger,
		);

		return [
			'db' => $db,
			'config' => $config,
			'tokenService' => $tokenService,
			'request' => $request,
			'session' => $session,
			'userManager' => $userManager,
			'appManager' => $appManager,
			'throttler' => $throttler,
			'limiter' => $limiter,
			'logger' => $logger,
			'rootFolder' => $rootFolder,
			'authService' => $authService,
			'entriesService' => $entriesService,
			'reportsService' => $reportsService,
			'settingsService' => $settingsService,
			'appDataLock' => $appDataLock,
			'controller' => $controller,
			'shares' => $shares,
			'projections' => $projections,
		];
	};

	$expectHttp = static function (TestRunner $t, callable $fn, int $expectedStatus): ApiV1HttpException {
		try {
			$fn();
		} catch (ApiV1HttpException $e) {
			$t->assertSame($expectedStatus, $e->getStatusCode(), 'Expected HTTP ' . $expectedStatus . ', got ' . $e->getStatusCode() . ': ' . $e->getMessage());
			return $e;
		}
		throw new \RuntimeException('Expected ApiV1HttpException with status ' . $expectedStatus);
	};

	return [
		'ApiV1Controller actions take zero parameters and enforce stateless public attributes without CORS' => static function (TestRunner $t): void {
			$ref = new \ReflectionClass(ApiV1Controller::class);
			$methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
			$actionNames = [];
			foreach ($methods as $method) {
				if ($method->getName() === '__construct' || $method->getDeclaringClass()->getName() !== ApiV1Controller::class) {
					continue;
				}
				$actionNames[] = $method->getName();
				$t->assertSame(0, $method->getNumberOfParameters(), $method->getName() . ' must take zero PHP parameters');
				$attrs = array_map(static fn(\ReflectionAttribute $a): string => $a->getName(), $method->getAttributes());
				$t->assertTrue(in_array(PublicPage::class, $attrs, true), $method->getName() . ' requires PublicPage');
				$t->assertTrue(in_array(NoCSRFRequired::class, $attrs, true), $method->getName() . ' requires NoCSRFRequired');
				$t->assertTrue(in_array(NoSameSiteCookieRequired::class, $attrs, true), $method->getName() . ' requires NoSameSiteCookieRequired');
				$t->assertTrue(in_array(NoTwoFactorRequired::class, $attrs, true), $method->getName() . ' requires NoTwoFactorRequired');
				foreach ($attrs as $attr) {
					$t->assertFalse(str_ends_with($attr, 'CORS'), $method->getName() . ' must never expose CORS');
				}
			}
			$t->assertSame(14, count($actionNames), 'All 14 v1 endpoints must be exposed cleanly');
		},

		'ApiV1AuthService enforces token extraction, brute-force vs missing-header rules, rate limiting, and body limits' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$f = $buildFixture();
			/** @var ApiTokenService $tokenService */
			$tokenService = $f['tokenService'];
			/** @var ApiV1TestRequest $request */
			$request = $f['request'];
			/** @var ApiV1TestAuthService $authService */
			$authService = $f['authService'];
			/** @var ApiV1TestThrottler $throttler */
			$throttler = $f['throttler'];
			/** @var ApiV1TestLimiter $limiter */
			$limiter = $f['limiter'];

			$created = $tokenService->create('alice', 'Desktop Sync', 1, ['entries:read'], true, 'none', [], 2000000000);
			$plain = $created['plain_token'];

			// 1. Missing headers -> 401 without registering brute-force attempt
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_UNAUTHORIZED);
			$t->assertSame(0, $throttler->attempts, 'Missing auth header must not trigger brute-force attempt');

			// 2. Supplied invalid credentials / Basic auth -> 401 AND registers attempt
			$request->headers['Authorization'] = 'Basic ' . base64_encode('alice:secret');
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_UNAUTHORIZED);
			$t->assertSame(1, $throttler->attempts, 'Basic auth attempt on API v1 must be throttled');

			$request->headers['Authorization'] = 'Bearer cb_' . str_repeat('0', 64);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_UNAUTHORIZED);
			$t->assertSame(2, $throttler->attempts, 'Unknown cb_ token must register brute-force attempt');

			// 3. Dual conflicting auth headers -> 401 + throttled
			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$request->headers['X-CoBudget-Token'] = 'cb_' . str_repeat('a', 64);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_UNAUTHORIZED);
			$t->assertSame(3, $throttler->attempts, 'Conflicting dual headers must fail closed and register attempt');
			unset($request->headers['X-CoBudget-Token']);

			// 4. Token leaked in query string (both key name and parameter value) -> 400
			$request->setGet(['token' => $plain]);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
			$request->setGet([$plain => '']);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
			$request->setGet([]);

			// 4b. Oversized X-CoBudget-Timezone rejected with 400; 64-bit URL/query IDs rejected with 400
			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$request->headers['X-CoBudget-Timezone'] = str_repeat('Europe/Berlin_', 6);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
			unset($request->headers['X-CoBudget-Timezone']);

			$request->urlParams = ['id' => '9999999999'];
			$expectHttp($t, static fn() => $authService->requirePositiveUrlId('id'), Http::STATUS_BAD_REQUEST);
			$request->urlParams = [];
			$request->setGet(['category_id' => '9999999999']);
			$expectHttp($t, static fn() => $authService->queryInt('category_id', 1, ApiV1AuthService::MAX_DB_ID), Http::STATUS_BAD_REQUEST);
			$request->setGet([]);

			// 4c. POST, PATCH, and DELETE never trigger OC\Request::__get('get') LogicException and still reject leaked query tokens
			foreach (['POST', 'PATCH', 'DELETE'] as $writeMethod) {
				$request->method = $writeMethod;
				$request->server = ['QUERY_STRING' => ''];
				$authWriteMethod = $authService->authenticateFixture('read', 1000000);
				$t->assertSame('alice', $authWriteMethod['user_id'], $writeMethod . ' authentication succeeds without accessing Request::get');

				$request->server = ['QUERY_STRING' => 'token=' . $plain];
				$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
				$request->server = ['QUERY_STRING' => 'api_key=secret'];
				$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
				$request->server = ['QUERY_STRING' => $plain];
				$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
			}
			$request->method = 'GET';
			$request->server = [];

			// 5. X-Workspace-Id mismatch -> 403
			$request->headers['X-Workspace-Id'] = '2';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_FORBIDDEN);
			$request->headers['X-Workspace-Id'] = '1';

			// 6. Rate limiting pre-auth and post-auth
			$limiter->failPrefix = 'cobudget_api_v1_ip';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_TOO_MANY_REQUESTS);
			$limiter->failPrefix = 'cobudget_api_v1_token:';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_TOO_MANY_REQUESTS);
			$limiter->failPrefix = null;

			// 7. Successful auth + last_used_at 60s throttle
			$ctx = $authService->authenticateFixture('read', 1000000);
			$t->assertSame('alice', $ctx['user_id'], 'Auth context must belong to alice');
			$t->assertSame(1, $ctx['workspace_id'], 'Auth context must bind workspace_id');
			$row1 = $f['db']->pdo->query('SELECT last_used_at FROM cobudget_api_tokens WHERE id = ' . (int)$ctx['token_id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame('1000000', (string)$row1['last_used_at'], 'Initial last_used_at should be recorded');
			$authService->authenticateFixture('read', 1000030);
			$row2 = $f['db']->pdo->query('SELECT last_used_at FROM cobudget_api_tokens WHERE id = ' . (int)$ctx['token_id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame('1000000', (string)$row2['last_used_at'], 'last_used_at should be throttled within 60 seconds');
			$authService->authenticateFixture('read', 1000065);
			$row3 = $f['db']->pdo->query('SELECT last_used_at FROM cobudget_api_tokens WHERE id = ' . (int)$ctx['token_id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame('1000065', (string)$row3['last_used_at'], 'last_used_at should update once 60 seconds elapsed');

			// 8. Disabled global admin setting / enable_api_access / disabled user / disabled app -> identical 401 with zero leakage
			$request->headers['Authorization'] = 'Bearer cb_' . str_repeat('0', 64);
			$unknownRes = $f['controller']->me();
			$request->headers['Authorization'] = 'Bearer ' . $plain;

			unset($f['config']->appValues['enable_api_access']);
			$t->assertFalse($tokenService->isGloballyEnabled(), 'Unset global admin API setting defaults to disabled');
			$limiter->failPrefix = 'cobudget_api_v1_token:';
			$attemptsBeforeDefaultOff = $throttler->attempts;
			$defaultOffRes = $f['controller']->me();
			$t->assertSame(Http::STATUS_UNAUTHORIZED, $defaultOffRes->getStatus(), 'Default-off admin API setting returns 401');
			$t->assertSame($unknownRes->getData(), $defaultOffRes->getData(), 'Default-off admin setting error payload matches unknown token');
			$t->assertSame($unknownRes->getHeaders(), $defaultOffRes->getHeaders(), 'Default-off admin setting headers match unknown token');
			$t->assertSame($attemptsBeforeDefaultOff + 1, $throttler->attempts, 'Admin-disabled API request records brute-force attempt identically');

			$f['config']->appValues['enable_api_access'] = 'no';
			$adminDisabledRes = $f['controller']->me();
			$t->assertSame(Http::STATUS_UNAUTHORIZED, $adminDisabledRes->getStatus(), 'Explicitly disabled admin API setting returns 401');
			$t->assertSame($unknownRes->getData(), $adminDisabledRes->getData(), 'Explicitly disabled admin setting error payload reveals no settings state');
			$t->assertSame($unknownRes->getHeaders(), $adminDisabledRes->getHeaders(), 'Explicitly disabled admin setting headers reveal no settings state');
			$lastUsedUnchanged = $f['db']->pdo->query('SELECT last_used_at FROM cobudget_api_tokens WHERE id = ' . (int)$ctx['token_id'])->fetchColumn();
			$t->assertSame('1000065', (string)$lastUsedUnchanged, 'Admin-disabled request must not touch token last_used_at');
			$limiter->failPrefix = null;

			unset($request->headers['Authorization']);
			$unauthWhileAdminDisabled = $f['controller']->me();
			$t->assertSame(Http::STATUS_UNAUTHORIZED, $unauthWhileAdminDisabled->getStatus(), 'Unauthenticated request still gets 401');
			$t->assertSame('Bearer realm="CoBudget API"', $unauthWhileAdminDisabled->getHeaders()['WWW-Authenticate'] ?? '', 'Unauthenticated probe keeps WWW-Authenticate challenge');
			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$f['config']->appValues['enable_api_access'] = 'yes';

			$f['config']->userValues['alice']['enable_api_access'] = 'no';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$f['config']->userValues['alice']['enable_api_access'] = 'yes';
			$f['appManager']->enabled = false;
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$f['appManager']->enabled = true;
			$f['userManager']->users['alice'] = new ApiV1TestUser('alice', 'Alice', false);
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$f['userManager']->users['alice'] = new ApiV1TestUser('alice', 'Alice', true);

			// 9. Brute-force MaxDelayReached -> 429 on invalid/forbidden credentials, while valid 256-bit keys resist IP-subnet lockout
			$throttler->throwMaxDelay = true;
			$attemptsBeforeLockout = $throttler->attempts;
			$request->headers['Authorization'] = 'Bearer cb_' . str_repeat('9', 64);
			$err429 = $expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_TOO_MANY_REQUESTS);
			$t->assertSame('300', $err429->getHeaders()['Retry-After'] ?? '', 'MaxDelayReached should set Retry-After header');
			$t->assertSame($attemptsBeforeLockout, $throttler->attempts, 'Blocked IP must fail before recording new brute-force rows');

			$request->headers['XAUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_TOO_MANY_REQUESTS);
			$t->assertSame($attemptsBeforeLockout, $throttler->attempts, 'XAUTHORIZATION from blocked IP must not insert more brute-force rows');
			unset($request->headers['XAUTHORIZATION']);

			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$ctxUnderIpLockout = $authService->authenticateFixture('read', 1000100);
			$t->assertSame('alice', $ctxUnderIpLockout['user_id'], 'Valid active 256-bit key authenticates despite shared-IP brute-force lockout');
			$throttler->throwMaxDelay = false;

			// 9b. Paused/expired known keys return 401 without incrementing IP brute-force counter
			$pausedTok = $tokenService->create('alice', 'Paused Cron Key', 1, ['entries:read'], true, 'none', [], null);
			$tokenService->update('alice', (int)$pausedTok['token']['id'], 'Paused Cron Key', ['entries:read'], true, 'none', [], null, false, null, $pausedTok['token']['revision']);
			$attemptsBeforePaused = $throttler->attempts;
			$request->headers['Authorization'] = 'Bearer ' . $pausedTok['plain_token'];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$t->assertSame($attemptsBeforePaused, $throttler->attempts, 'Paused stored key returns 401 without tripping IP brute-force lockout');

			// 9c. Authenticated web sessions and login cookies are rejected, while Nextcloud's anonymous session/SameSite cookies from HTTP client cookie jars pass
			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$request->headers['Cookie'] = 'nc_sameSiteCookielax=true; nc_session_id=ambient-session';
			$attemptsBeforeCookie = $throttler->attempts;
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$t->assertSame($attemptsBeforeCookie, $throttler->attempts, 'Rejected browser login cookie does not brute-force-lock client IP');
			unset($request->headers['Cookie']);

			$request->cookies = ['nc_token' => 'remember-me-secret'];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$request->cookies = [];

			$request->headers['Cookie'] = 'oc_sessionPassphrase=anon-passphrase; occf07cf51g9=anon-sid; __Host-nc_sameSiteCookielax=true; __Host-nc_sameSiteCookiestrict=true';
			$f['session']->values['user_id'] = 'bob';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$f['session']->values = [];

			$request->server = ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer ' . $plain];
			$t->assertSame('alice', $authService->authenticateFixture('read', 1000100)['user_id'], 'Anonymous Nextcloud session cookies from HTTP client cookie jars and FastCGI Bearer pass');
			unset($request->headers['Cookie']);

			$request->server = ['REMOTE_USER' => 'alice'];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$request->server = ['REDIRECT_HTTP_AUTHORIZATION' => 'Basic YWxpY2U6cGFzcw=='];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$request->server = [];

			// 9d. PATCH /settings keeps notify_* writable for settings:write tokens and emits an audit log
			$settingsTok = $tokenService->create('alice', 'Trusted Settings Key', 1, ['settings:write'], true, 'none', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $settingsTok['plain_token'];
			$settingsAuth = $authService->authenticateFixture('write', 1000100);
			$f['logger']->logs = [];
			$patchRes = $f['settingsService']->patchSettings($settingsAuth, ['notify_reminders' => false, 'enable_reminders' => false]);
			$t->assertSame(['notify_reminders', 'enable_reminders'], $patchRes['updated'], 'settings:write allows updating notify_reminders and enable_reminders');
			$t->assertSame('no', $f['config']->getUserValue('alice', 'cobudget', 'notify_reminders'), 'notify_reminders persists via settings:write');
			$auditEvents = array_values(array_filter($f['logger']->logs, static fn(array $entry): bool => ($entry['context']['event'] ?? '') === 'api_v1.settings.updated'));
			$t->assertSame(1, count($auditEvents), 'PATCH /settings writes a structured audit log entry');
			$f['config']->setUserValue('alice', 'cobudget', 'notify_reminders', 'yes');
			$f['config']->setUserValue('alice', 'cobudget', 'enable_reminders', 'yes');
			$request->headers['Authorization'] = 'Bearer ' . $plain;

			// 10. Body parsing security limits
			$request->method = 'POST';
			$request->headers['Content-Type'] = 'text/plain';
			$authService->rawBody = '{"amount":10}';
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_UNSUPPORTED_MEDIA_TYPE);

			$request->headers['Content-Type'] = 'application/json; charset=utf-8';
			$authService->rawBody = str_repeat('a', 65537);
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_PAYLOAD_TOO_LARGE);

			$request->headers['Authorization'] = 'Bearer ' . $plain;
			$request->headers['Content-Type'] = 'application/json; charset=utf-8';
			$authService->rawBody = str_repeat('a', 65537);
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_PAYLOAD_TOO_LARGE);

			$authService->rawBody = '[1, 2, 3]';
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_BAD_REQUEST);

			$authService->rawBody = '[]';
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_BAD_REQUEST);

			// 10b. X-Workspace-Id integer overflow returns 400 rather than casting to PHP_INT_MAX
			$request->headers['X-Workspace-Id'] = '999999999999999999999';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000000), Http::STATUS_BAD_REQUEST);
			unset($request->headers['X-Workspace-Id']);

			// 10c. Non-default workspace token is blocked while enable_workspaces is disabled, and allowed once enabled
			$ws2Tok = $tokenService->create('alice', 'Workspace 2 Sync', 2, ['entries:read'], true, 'none', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $ws2Tok['plain_token'];
			$f['config']->userValues['alice']['enable_workspaces'] = 'no';
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_UNAUTHORIZED);
			$f['config']->userValues['alice']['enable_workspaces'] = 'yes';
			$ws2Ctx = $authService->authenticateFixture('read', 1000100);
			$t->assertSame(2, $ws2Ctx['workspace_id'], 'Non-default workspace token authenticates once enable_workspaces is enabled');
			$request->headers['Authorization'] = 'Bearer ' . $plain;

			$authService->rawBody = '{"amount": 10}';
			$request->headers['Content-Length'] = '999';
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_BAD_REQUEST);
			$request->headers['Content-Length'] = '999999999999999999999';
			$expectHttp($t, static fn() => $authService->readJsonObjectBody(), Http::STATUS_BAD_REQUEST);
			unset($request->headers['Content-Length']);

			$request->method = 'GET';
			$request->headers['Content-Length'] = '999999999999999999999';
			$expectHttp($t, static fn() => $authService->requireEmptyBody(), Http::STATUS_BAD_REQUEST);
			unset($request->headers['Content-Length']);
			$authService->rawBody = '{';
			$expectHttp($t, static fn() => $authService->requireEmptyBody(), Http::STATUS_BAD_REQUEST);

			// 10d. Query string length and nesting depth caps protect against query abuse
			$authService->rawBody = '';
			$request->server = ['QUERY_STRING' => 'search=' . str_repeat('a', ApiV1AuthService::MAX_QUERY_STRING_BYTES + 1)];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), 414);

			$deepQuery = 'a' . str_repeat('[0]', ApiV1AuthService::MAX_QUERY_ARRAY_DEPTH + 1) . '=1';
			$request->server = ['QUERY_STRING' => $deepQuery];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_BAD_REQUEST);

			$tooManyBrackets = str_repeat('b[]=', ApiV1AuthService::MAX_QUERY_BRACKET_COUNT + 1);
			$request->server = ['QUERY_STRING' => $tooManyBrackets];
			$expectHttp($t, static fn() => $authService->authenticateFixture('read', 1000100), Http::STATUS_BAD_REQUEST);
			$request->server = [];

			// 11. Security response headers clear X-User-Id
			$resp = $authService->jsonResponse(['ok' => true]);
			$t->assertSame('', $resp->getHeaders()['X-User-Id'] ?? null, 'JSONResponse must explicitly clear X-User-Id');
			$t->assertSame('no-store, private', $resp->getHeaders()['Cache-Control'] ?? null, 'JSONResponse must prevent caching');
		},

		'ApiV1Controller & AppDataLockMiddleware protect URL {id} routing and convert AppBusyException to 503 JSON' => static function (TestRunner $t) use ($buildFixture): void {
			$f = $buildFixture();
			/** @var ApiTokenService $tokenService */
			$tokenService = $f['tokenService'];
			/** @var ApiV1TestRequest $request */
			$request = $f['request'];
			/** @var ApiV1TestAuthService $authService */
			$authService = $f['authService'];
			/** @var ApiV1Controller $controller */
			$controller = $f['controller'];
			/** @var \PDO $pdo */
			$pdo = $f['db']->pdo;

			$pdo->exec("INSERT INTO cobudget_entries (id, user_id, created_by, project_id, entry_kind, type, amount, amount_cents, currency, date, description, workspace_id) VALUES (101, 'alice', 'alice', NULL, 'personal', 'expense', '10.00', 1000, 'EUR', 1700000000, 'Target 101', 1), (999, 'alice', 'alice', NULL, 'personal', 'expense', '50.00', 5000, 'EUR', 1700000000, 'Victim 999', 1)");

			$matrix = ['personal' => ['read', 'write', 'create', 'delete']];
			$created = $tokenService->create('alice', 'Matrix Key', 1, [], false, 'none', [], null, $matrix);
			$request->headers['Authorization'] = 'Bearer ' . $created['plain_token'];
			$request->headers['Content-Type'] = 'application/json';
			$request->method = 'PATCH';
			$request->urlParams = ['id' => '101'];
			// Client tries to smuggle id=999 in JSON body AND unknown key check -> reject unknown key 'id' cleanly OR verify 101 untouched
			$authService->rawBody = '{"id":999,"description":"Tampered"}';
			$res = $controller->entriesUpdate();
			$t->assertSame(Http::STATUS_BAD_REQUEST, $res->getStatus(), 'Unknown body key "id" must be rejected');

			// Valid PATCH only changes URL id=101, leaving 999 untouched
			$authService->rawBody = '{"description":"Updated 101"}';
			$resOk = $controller->entriesUpdate();
			$t->assertSame(Http::STATUS_OK, $resOk->getStatus(), 'Valid PATCH on 101 must succeed');
			$desc999 = $pdo->query('SELECT description FROM cobudget_entries WHERE id = 999')->fetchColumn();
			$t->assertSame('Victim 999', $desc999, 'Entry 999 must never be modified');

			// Middleware skips lock acquisition pre-auth for ApiV1Controller; Controller acquires post-auth and handles AppBusyException
			$appDataLock = $f['appDataLock'];
			$middleware = new AppDataLockMiddleware($appDataLock, new ApiV1TestL10n());

			$savedAuth = $request->headers['Authorization'];
			unset($request->headers['Authorization']);
			$request->method = 'GET';
			$authService->rawBody = '';
			$entersBefore = $appDataLock->enterCount;
			$middleware->beforeController($controller, 'me');
			$unauthRes = $controller->me();
			$t->assertSame(Http::STATUS_UNAUTHORIZED, $unauthRes->getStatus(), 'Missing token returns 401');
			$t->assertSame($entersBefore, $appDataLock->enterCount, 'Unauthenticated API v1 requests must never acquire AppDataLock');

			$request->headers['Authorization'] = $savedAuth;
			$authRes = $controller->me();
			$t->assertSame(Http::STATUS_OK, $authRes->getStatus(), 'Authenticated API v1 request succeeds');
			$t->assertSame($entersBefore + 1, $appDataLock->enterCount, 'Authenticated API v1 request acquires AppDataLock post-auth');
			$t->assertSame($appDataLock->enterCount, $appDataLock->leaveCount, 'AppDataLock is released after authenticated API v1 request');

			$appDataLock->throwBusy = true;
			$busyControllerRes = $controller->me();
			$t->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $busyControllerRes->getStatus(), 'Controller AppBusyException returns HTTP 503');
			$t->assertSame('cobudget_busy', $busyControllerRes->getData()['code'] ?? null, 'Controller 503 includes cobudget_busy code');
			$t->assertSame('5', $busyControllerRes->getHeaders()['Retry-After'] ?? null, 'Controller 503 includes Retry-After: 5');
			$t->assertSame('', $busyControllerRes->getHeaders()['X-User-Id'] ?? null, 'Controller 503 clears X-User-Id');
			$appDataLock->throwBusy = false;

			$f['limiter']->failPrefix = 'cobudget_api_v1_token:';
			$beforeLimit = $appDataLock->enterCount;
			$t->assertSame(429, $controller->me()->getStatus(), 'Per-key quota applies before authorization work');
			$t->assertSame($beforeLimit, $appDataLock->enterCount, 'Over-quota requests do not enter the lock');
			$f['limiter']->failPrefix = null;

			$appDataLock->onEnter = static fn() => $pdo->exec('DELETE FROM cobudget_api_tokens');
			$t->assertSame(401, $controller->me()->getStatus(), 'A restore between admission and the lock invalidates the in-flight credential');
			$t->assertSame($appDataLock->enterCount, $appDataLock->leaveCount, 'Failed revalidation releases the app lock');

			$handled = $middleware->afterException($controller, 'entriesUpdate', new AppBusyException());
			$t->assertTrue($handled instanceof JSONResponse, 'AppBusyException on ApiV1Controller must become JSONResponse');
			$t->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $handled->getStatus(), 'AppBusyException must return HTTP 503');
			$t->assertSame('5', $handled->getHeaders()['Retry-After'] ?? null, '503 response must include Retry-After: 5');
			$t->assertSame('', $handled->getHeaders()['X-User-Id'] ?? null, '503 middleware response must clear X-User-Id');
		},

		'ApiV1EntriesService isolates personal/area matrix targets, handles shrunk solo areas, released #501 entries, and shared history/projections' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$f = $buildFixture();
			/** @var ApiTokenService $tokenService */
			$tokenService = $f['tokenService'];
			/** @var ApiV1TestAuthService $authService */
			$authService = $f['authService'];
			/** @var ApiV1EntriesService $entries */
			$entries = $f['entriesService'];
			/** @var ApiV1TestRequest $request */
			$request = $f['request'];
			/** @var \PDO $pdo */
			$pdo = $f['db']->pdo;

			// Seed entries:
			// 1: personal entry in ws 1
			// 2: solo area 10 entry in ws 1
			// 3: shared area 12 source entry in ws 3 (alice is 50% member -> locked projection #4 in alice ws 1)
			// 4: locked personal projection in ws 1 (source=3)
			// 5: released settled personal projection in ws 1 (source=null, is_settled=1, is_locked=0)
			// 6: archived area 11 entry in ws 1
			// 7: alice ws 2 entry
			$pdo->exec("INSERT INTO cobudget_entries (id, user_id, created_by, project_id, entry_kind, source_entry_id, is_locked, type, amount, amount_cents, currency, date, description, workspace_id, is_settled, settlement_id, settled_at) VALUES
				(1, 'alice', 'alice', NULL, 'personal', NULL, 0, 'expense', '12.50', 1250, 'EUR', 1700000100, 'Personal coffee #food', 1, 0, NULL, NULL),
				(2, 'alice', 'alice', 10, 'personal', NULL, 0, 'expense', '40.00', 4000, 'EUR', 1700000200, 'Household soap', 1, 0, NULL, NULL),
				(3, 'bob', 'bob', 12, 'shared', NULL, 0, 'expense', '100.00', 10000, 'EUR', 1700000300, 'Shared rent', 3, 0, NULL, NULL),
				(4, 'alice', 'bob', 12, 'personal', 3, 1, 'expense', '50.00', 5000, 'EUR', 1700000300, 'Shared rent', 1, 0, NULL, NULL),
				(5, 'alice', 'bob', 12, 'personal', NULL, 0, 'expense', '25.00', 2500, 'EUR', 1700000400, 'Settled personal copy', 1, 0, 501, 1700000400),
				(6, 'alice', 'alice', 11, 'personal', NULL, 0, 'expense', '99.00', 9900, 'EUR', 1700000500, 'Archived hidden', 1, 0, NULL, NULL),
				(7, 'alice', 'alice', 13, 'personal', NULL, 0, 'expense', '88.00', 8800, 'EUR', 1700000600, 'Business ws2 hidden', 2, 0, NULL, NULL)");

			// Key allows personal r/w/c/d, area 10 r/w/c/d, area 14 (shrunk solo where project.workspace_id=3 while alice ws=1) r/w/c, NO area 12
			$matrix = [
				'personal' => ['read', 'write', 'create', 'delete'],
				'10' => ['read', 'write', 'create', 'delete'],
				'14' => ['read', 'write', 'create'],
			];
			$created = $tokenService->create('alice', 'Scoped Key', 1, [], false, 'none', [], null, $matrix);
			$request->headers['Authorization'] = 'Bearer ' . $created['plain_token'];
			$auth = $authService->authenticateFixture('read', 1700001000);

			// Canonical GET /entries: includes 2, 1; omits 3, 4, 5 (no access to area 12), 6 (archived), 7 (other ws)
			$canonical = $entries->listEntries($auth, []);
			$ids = array_column($canonical['entries'], 'id');
			$t->assertSame([2, 1], $ids, 'Canonical entries must only expose authorized active non-locked non-released entries');

			// scope=personal with project_id=0 filters strictly to payments without an area (no area 10, no unauthorized area 12)
			$personalOnly = $entries->listEntries($auth, ['scope' => 'personal', 'project_id' => '0']);
			$t->assertSame([1], array_column($personalOnly['entries'], 'id'), 'scope=personal with project_id=0 must return only personal entry #1');
			$expectHttp($t, static fn() => $entries->listEntries($auth, ['project_id' => 'personal']), Http::STATUS_BAD_REQUEST);

			// Locked projection #4 and unauthorized #5 cannot be accessed by Scoped Key -> uniform 404
			$expectHttp($t, static fn() => $entries->getEntry($auth, 4), Http::STATUS_NOT_FOUND);
			$expectHttp($t, static fn() => $entries->updateEntry($auth, 4, ['description' => 'Hack']), Http::STATUS_NOT_FOUND);
			$expectHttp($t, static fn() => $entries->deleteEntry($auth, 4), Http::STATUS_NOT_FOUND);
			$expectHttp($t, static fn() => $entries->getEntry($auth, 5), Http::STATUS_NOT_FOUND);

			// Shrunk solo area 14 (project workspace_id = 3 != token workspace_id = 1): create writes workspace_id=1 because single solo member mapped to ws 1
			$created14 = $entries->createEntry($auth, [
				'project_id' => 14,
				'type' => 'expense',
				'amount' => '19.99',
				'date' => 1738368000,
				'description' => 'Shrunk solo #solo',
			]);
			$t->assertSame(14, $created14['project_id'], 'Entry in shrunk solo area 14 should be created');
			$stored14 = $pdo->query('SELECT entry_kind, workspace_id FROM cobudget_entries WHERE id = ' . (int)$created14['id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame('personal', $stored14['entry_kind'], 'Solo area must store entry_kind=personal');
			$t->assertSame('1', (string)$stored14['workspace_id'], 'Shrunk solo area entry must live in member personal_workspace_id');

			// Moving project_id on PATCH -> 409 Conflict
			$expectHttp($t, static fn() => $entries->updateEntry($auth, (int)$created14['id'], ['project_id' => 10]), Http::STATUS_CONFLICT);

			// Unauthorized category/partner from another user/area -> 400 without existence oracle leakage
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'category_id' => 7,
			]), Http::STATUS_BAD_REQUEST);

			// Partial recurrence tuple rejected; canonical recurrence + yearly normalization round-trips cleanly
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'recurrence_interval' => 'month',
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'recurrence_interval' => 'yearly',
				'recurrence_multiplier' => 100,
				'recurrence_next_date' => 1730000100,
			]), Http::STATUS_BAD_REQUEST);
			$yearlyEntry = $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 1200,
				'date' => 1700000100,
				'recurrence_interval' => 'yearly',
				'recurrence_next_date' => 1731536100,
				'description' => 'Insurance',
			]);
			$t->assertSame('month', $yearlyEntry['recurrence_interval'], 'Yearly alias must store canonical DB interval "month"');
			$t->assertSame(12, $yearlyEntry['recurrence_multiplier'], 'Yearly alias must store canonical 12-month multiplier');
			$patchedRecurring = $entries->updateEntry($auth, (int)$yearlyEntry['id'], [
				'description' => 'Updated insurance',
				'user_id' => null,
				'split_mode' => null,
				'split_user_id' => null,
			]);
			$t->assertSame('month', $patchedRecurring['recurrence_interval'], 'PATCH on canonical recurring entry must preserve interval');
			$t->assertSame(12, $patchedRecurring['recurrence_multiplier'], 'PATCH without interval change must preserve 12x multiplier');
			$reAppliedYearly = $entries->updateEntry($auth, (int)$yearlyEntry['id'], [
				'recurrence_interval' => 'yearly',
			]);
			$t->assertSame(12, $reAppliedYearly['recurrence_multiplier'], 'Re-sending yearly without multiplier must not compound 12 into 144');

			// Array/object type confusion, NUL/control chars, and oversized DB IDs reject cleanly with 400
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'user_id' => ['alice'],
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entries->updateEntry($auth, (int)$yearlyEntry['id'], [
				'split_user_id' => ['alice'],
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'description' => "Bad\x00Null",
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entries->createEntry($auth, [
				'type' => 'expense',
				'amount' => 10,
				'date' => 1700000100,
				'category_id' => 9999999999,
			]), Http::STATUS_BAD_REQUEST);
			$entries->deleteEntry($auth, (int)$yearlyEntry['id']);

			// Key with write access on shared area 12 syncs locked projection AND history on both source & projection, exposes released #5 in scope=personal and allows PATCH on #5 while keeping #4 locked (404)
			$matrixShared = ['12' => ['read', 'write', 'create', 'delete']];
			$sharedTok = $tokenService->create('alice', 'Shared Key', 1, [], false, 'none', [], null, $matrixShared);
			$request->headers['Authorization'] = 'Bearer ' . $sharedTok['plain_token'];
			$authShared = $authService->authenticateFixture('write', 1700002000);
			$sharedCanonical = $entries->listEntries($authShared, []);
			$t->assertSame([3], array_column($sharedCanonical['entries'], 'id'), 'Canonical shared area read must return source #3 only (omitting #4/#5)');
			$sharedPersonal = $entries->listEntries($authShared, ['scope' => 'personal']);
			$t->assertSame([5, 4], array_column($sharedPersonal['entries'], 'id'), 'scope=personal on authorized area 12 exposes personal projection #4 and released #5');
			$t->assertTrue($sharedPersonal['entries'][0]['is_released_personal'] === true, 'Entry #5 must report is_released_personal=true');
			$expectHttp($t, static fn() => $entries->getEntry($authShared, 4), Http::STATUS_NOT_FOUND);
			$patched5 = $entries->updateEntry($authShared, 5, ['description' => 'Adjusted personal copy']);
			$t->assertSame('Adjusted personal copy', $patched5['description'], 'Released settled personal #5 must be editable by authorized key');
			$updatedShared = $entries->updateEntry($authShared, 3, ['amount_cents' => 12000, 'description' => 'Shared rent updated']);
			$t->assertSame(12000, $updatedShared['amount_cents'], 'Shared source entry should update to 12000 cents');
			$projAmount = $pdo->query('SELECT amount_cents FROM cobudget_entries WHERE id = 4')->fetchColumn();
			$t->assertSame('6000', (string)$projAmount, 'Locked personal projection #4 must re-sync to 50% (6000 cents)');
			$histCount = (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entry_history WHERE entry_id IN (3, 4)')->fetchColumn();
			$t->assertTrue($histCount >= 2, 'Shared PATCH must record entry history for both shared source and projection');

			// Deleting personal entry cleans up hashtags cleanly
			$request->headers['Authorization'] = 'Bearer ' . $created['plain_token'];
			$auth = $authService->authenticateFixture('write', 1700003000);
			$delResult = $entries->deleteEntry($auth, 1);
			$t->assertSame(['status' => 'success'], $delResult, 'Delete should confirm deletion');
			$left = (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entries WHERE id = 1')->fetchColumn();
			$t->assertSame(0, $left, 'Deleted entry row must be removed');
		},

		'Mutations and account metadata honor independent read, create and edit permissions' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$f = $buildFixture();
			$authorize = static function (string $name, array $targets, array $scopes = []) use ($f): array {
				$issued = $f['tokenService']->create('alice', $name, 1, $scopes, false, 'none', [], null, $targets);
				$f['request']->headers['Authorization'] = 'Bearer ' . $issued['plain_token'];
				return $f['authService']->authenticateFixture('write');
			};
			$entries = $f['entriesService'];
			$writer = $authorize('Editor', ['personal' => ['write'], 10 => ['create', 'read']]);
			$owner = $authorize('Reader and creator', ['personal' => ['read', 'write', 'create']], ['settings:read']);
			$settings = $authorize('Preferences', [], ['settings:write']);
			$id = $entries->createEntry($owner, ['amount_cents' => 1000, 'description' => 'Private'])['id'];

			$t->assertSame(['id' => $id], $entries->updateEntry($writer, $id, ['description' => 'Updated']), 'Reading another target must not broaden the write response');
			$t->assertSame('Updated', $entries->getEntry($owner, $id)['description'], 'Write-only change persists');
			$t->assertFalse(isset($entries->me($writer)['currency']), 'Currency requires settings:read');
			$t->assertFalse(isset($entries->me($settings)['features']), 'Settings write does not include metadata reads');
			$t->assertTrue(isset($entries->me($owner)['features'], $entries->me($owner)['currency']), 'Settings reader retains feature and currency information');
			$expectHttp($t, static fn() => $entries->getEntry($settings, $id), Http::STATUS_NOT_FOUND);
			$expectHttp($t, static fn() => $entries->getEntry($settings, $id + 100), Http::STATUS_NOT_FOUND);

			$series = ['recurrence_interval' => 'week', 'recurrence_multiplier' => 2, 'recurrence_next_date' => 1800000000, 'recurrence_end_date' => 1820000000];
			$expectHttp($t, static fn() => $entries->updateEntry($writer, $id, $series), Http::STATUS_FORBIDDEN);
			$entries->updateEntry($owner, $id, $series);
			foreach ([['recurrence_multiplier' => 1], ['recurrence_end_date' => null], ['recurrence_next_date' => 1790000000]] as $expansion) {
				$expectHttp($t, static fn() => $entries->updateEntry($writer, $id, $expansion), Http::STATUS_FORBIDDEN);
			}
			$entries->updateEntry($writer, $id, ['recurrence_end_date' => 1810000000]);
			$entries->updateEntry($writer, $id, ['recurrence_interval' => null]);
			$t->assertSame(null, $entries->getEntry($owner, $id)['recurrence_interval'], 'Write-only integrations may shorten or stop existing series');
		},

		'Deletion and settlement serialize on the same area across SQLite connections' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$dsn = 'sqlite:file:cobudget-api-' . bin2hex(random_bytes(8)) . '?mode=memory&cache=shared';
			$f = $buildFixture($dsn);
			$other = new ApiV1TestDb($dsn, false);
			$otherShares = new EntryShareService($other);
			$issued = $f['tokenService']->create('alice', 'Shared payments', 1, [], false, 'none', [], null, [12 => ['create', 'delete']]);
			$f['request']->headers['Authorization'] = 'Bearer ' . $issued['plain_token'];
			$auth = $f['authService']->authenticateFixture('write');
			$entries = $f['entriesService'];
			$id = $entries->createEntry($auth, ['project_id' => 12, 'amount_cents' => 100])['id'];

			$f['db']->beforeNextTransaction = static function () use ($other, $otherShares, $id): void {
				$other->beginTransaction();
				$otherShares->lockProject(12);
				$other->pdo->exec('UPDATE cobudget_entries SET is_settled = 1 WHERE id = ' . $id);
				$other->commit();
			};
			$expectHttp($t, static fn() => $entries->deleteEntry($auth, $id), Http::STATUS_CONFLICT);
			$t->assertSame(1, (int)$other->pdo->query('SELECT is_settled FROM cobudget_entries WHERE id = ' . $id)->fetchColumn(), 'Already settled source survives the competing deletion');

			$other->beginTransaction();
			$otherShares->lockProject(12);
			$f['db']->beginTransaction();
			try {
				$blocked = false;
				try {
					$f['shares']->lockProject(12);
				} catch (\PDOException $error) {
					$blocked = str_contains($error->getMessage(), 'locked');
				}
				$t->assertTrue($blocked, 'Second connection cannot modify this area while the settlement holds the project lock');
			} finally {
				$f['db']->rollBack();
				$other->rollBack();
			}

			$other->pdo->exec("DELETE FROM cobudget_members WHERE project_id = 12 AND user_id = 'alice'");
			$expectHttp($t, static fn() => $entries->createEntry($auth, ['project_id' => 12, 'amount_cents' => 100]), Http::STATUS_NOT_FOUND);
		},

		'ApiV1ReportsService prevents archived-area leaks and empty-rules budget wildcard bypass' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$f = $buildFixture();
			/** @var ApiTokenService $tokenService */
			$tokenService = $f['tokenService'];
			/** @var ApiV1TestAuthService $authService */
			$authService = $f['authService'];
			/** @var ApiV1ReportsService $reports */
			$reports = $f['reportsService'];
			/** @var ApiV1TestRequest $request */
			$request = $f['request'];
			/** @var \PDO $pdo */
			$pdo = $f['db']->pdo;

			$year = (int)date('Y');
			$now = time();
			// Personal expense (2000 cents) vs archived area 11 expense (99000 cents)
			$pdo->exec("INSERT INTO cobudget_entries (id, user_id, created_by, project_id, entry_kind, type, amount, amount_cents, currency, date, category_id, description, workspace_id) VALUES
				(10, 'alice', 'alice', NULL, 'personal', 'expense', '20.00', 2000, 'EUR', $now, 1, 'Groceries', 1),
				(11, 'alice', 'alice', 11, 'personal', 'expense', '990.00', 99000, 'EUR', $now, 6, 'Archived Leak', 1)");
			// Budget goal pointing ONLY to archived category 6 -> must be skipped, NEVER become wildcard matching Groceries!
			$pdo->exec("INSERT INTO cobudget_budget_goals (id, user_id, workspace_id, name, amount_cents, period, mode, criteria_json) VALUES
				(1, 'alice', 1, 'Archived Only Goal', 10000, 'month', 'flexible', '{\"rules\":[{\"categoryId\":6}]}'),
				(2, 'alice', 1, 'Active Food Goal', 10000, 'month', 'flexible', '{\"rules\":[{\"categoryId\":1}]}')");

			// Key with personal read only lacks reports:read unless all active workspace targets have read; first verify missing reports:read -> 403
			$noReports = $tokenService->create('alice', 'No Reports', 1, ['entries:read'], true, 'none', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $noReports['plain_token'];
			$authNo = $authService->authenticateFixture('read', $now);
			$expectHttp($t, static fn() => $reports->summary($authNo, []), Http::STATUS_FORBIDDEN);

			// Full active access (personal + area 10, 12, 14) with entries:read + reports:read
			$full = $tokenService->create('alice', 'Full Reports', 1, ['entries:read', 'reports:read'], true, 'all', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $full['plain_token'];
			$authFull = $authService->authenticateFixture('read', $now);
			$summary = $reports->summary($authFull, [
				'period' => 'current-year',
				'date_from' => null,
				'date_to' => null,
				'include_future' => false,
			]);
			$t->assertSame(2000, $summary['totals']['expense_cents'], 'Reports must exclude archived area 11 expenses');

			$budgets = $reports->budgets($authFull);
			$goalNames = array_column($budgets['goals'], 'name');
			$t->assertSame(['Active Food Goal'], $goalNames, 'Goal whose only rules belong to an archived area must be skipped instead of turning into a wildcard');

			$insert = $pdo->prepare("INSERT INTO cobudget_entries (user_id, workspace_id, type, amount_cents, date, category_id, is_important) VALUES ('alice', 1, 'expense', 3, ?, 1, 1)");
			for ($i = 0; $i < 513; $i++) {
				$insert->execute([$now]);
			}
			$overlap = $pdo->prepare("INSERT INTO cobudget_budget_goals (user_id, workspace_id, name, criteria_json) VALUES ('alice', 1, ?, ?)");
			$rules = json_encode(['rules' => [['tag' => 'important'], ['projectId' => 0, 'categoryId' => 1]]], JSON_THROW_ON_ERROR);
			$overlap->execute(['Overlap A', $rules]);
			$overlap->execute(['Overlap B', $rules]);
			$annual = $reports->annual($authFull, ['year' => (int)(new \DateTimeImmutable('@' . $now))->setTimezone($authFull['timezone'])->format('Y'), 'include_future' => true]);
			$t->assertSame(514, $annual['totals']['entry_count'], 'Report includes each authorized payment across page boundaries');
			$t->assertSame(3539, $annual['totals']['expense_cents'], 'Report preserves cent totals across pages');
			$goals = $reports->budgets($authFull)['goals'];
			$t->assertSame(['Active Food Goal', 'Overlap A', 'Overlap B'], array_column($goals, 'name'), 'Equivalent goals remain individually visible');
			foreach ($goals as $goal) {
				$t->assertSame(3539, $goal['evaluation']['spent_cents'], 'Overlapping rules count each payment once in each matching goal');
			}

			// Custom summary range exceeding 10 years must be rejected with 400
			$expectHttp($t, static fn() => $reports->summary($authFull, [
				'period' => 'custom',
				'date_from' => 1,
				'date_to' => ApiV1EntriesService::MAX_ENTRY_TIMESTAMP,
				'include_future' => false,
			]), Http::STATUS_BAD_REQUEST);
		},

		'ApiV1SettingsService strictly allowlists settings keys and keeps PATCH write-only' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			$f = $buildFixture();
			/** @var ApiTokenService $tokenService */
			$tokenService = $f['tokenService'];
			/** @var ApiV1TestAuthService $authService */
			$authService = $f['authService'];
			/** @var ApiV1SettingsService $settings */
			$settings = $f['settingsService'];
			/** @var ApiV1TestRequest $request */
			$request = $f['request'];

			$tok = $tokenService->create('alice', 'Settings Key', 1, ['settings:read', 'settings:write'], true, 'none', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $tok['plain_token'];
			$auth = $authService->authenticateFixture('write', 1700000000);

			$read = $settings->getSettings($auth);
			$t->assertFalse(array_key_exists('enable_api_access', $read), 'enable_api_access must never be exposed');
			$t->assertFalse(array_key_exists('api_access_admin_enabled', $read), 'api_access_admin_enabled must never be exposed');
			$t->assertFalse(array_key_exists('webhook_url', $read), 'webhook_url must never be exposed');
			$t->assertSame('EUR', $read['currency'] ?? null, 'Allowlisted currency setting should be returned');

			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['enable_api_access' => 'no']), Http::STATUS_FORBIDDEN);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['api_access_admin_enabled' => false]), Http::STATUS_FORBIDDEN);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['enable_projects' => false]), Http::STATUS_FORBIDDEN);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['webhook_url' => 'https://evil']), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['bogus_key' => '1']), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['default_start_page' => 'project:11']), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['entries_per_page' => 999]), Http::STATUS_BAD_REQUEST);

			// Reject start page pointing to an active area outside the token's granted targets (prevent area enumeration)
			$expectHttp($t, static fn() => $settings->patchSettings($auth, ['default_start_page' => 'project:10']), Http::STATUS_BAD_REQUEST);

			$patched = $settings->patchSettings($auth, ['entries_per_page' => 250, 'default_start_page' => 'personal']);
			$t->assertSame(['status' => 'success', 'updated' => ['entries_per_page', 'default_start_page']], $patched, 'PATCH settings must return only status and updated key names');
			$t->assertFalse(array_key_exists('settings', $patched), 'Write-only settings PATCH must not leak unreadable settings');

			// Token with read access to area 10 can set default_start_page to project:10
			$areaSettingsTok = $tokenService->create('alice', 'Area Start Key', 1, ['settings:write'], false, 'none', [], null, ['10' => ['read']]);
			$request->headers['Authorization'] = 'Bearer ' . $areaSettingsTok['plain_token'];
			$authAreaSettings = $authService->authenticateFixture('write', 1700000000);
			$patchedArea = $settings->patchSettings($authAreaSettings, ['default_start_page' => 'project:10']);
			$t->assertSame(['status' => 'success', 'updated' => ['default_start_page']], $patchedArea, 'Token with area access can set project:10 as default_start_page');

			// Write-only token (settings:write without settings:read) can PATCH but gets 403 on GET
			$writeOnly = $tokenService->create('alice', 'Settings WriteOnly', 1, ['settings:write'], true, 'none', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $writeOnly['plain_token'];
			$authWo = $authService->authenticateFixture('write', 1700000000);
			$expectHttp($t, static fn() => $settings->getSettings($authWo), Http::STATUS_FORBIDDEN);
			$resWo = $settings->patchSettings($authWo, ['theme_mode' => 'dark']);
			$t->assertSame(['status' => 'success', 'updated' => ['theme_mode']], $resWo, 'settings:write key can patch without settings:read');
		},
		'API v1 entries support JSON-import date, signed amount, lookup names, and label fields' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			['config' => $config, 'request' => $request, 'tokenService' => $tokenService, 'authService' => $authService, 'entriesService' => $entriesService] = $buildFixture();
			$tok = $tokenService->create('alice', 'JSON Parity', 1, ['entries:read', 'entries:create', 'entries:write'], true, 'all', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $tok['plain_token'];
			$authCreate = $authService->authenticateFixture('create', 1700000000);
			$authWrite = $authService->authenticateFixture('write', 1700000000);

			$createdExpense = $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20',
				'amount' => -12.50,
				'category' => 'lebensmittel',
				'payment_partner' => 'SUPERMARKT',
				'description' => 'Wocheneinkauf #essen',
				'important' => 1,
				'fixed_cost' => true,
				'review' => 0,
			]);
			$expectedTs = (new \DateTimeImmutable('2026-11-20 12:00:00', $authCreate['timezone']))->getTimestamp();
			$t->assertSame('expense', $createdExpense['type'], 'Negative amount without type infers expense');
			$t->assertSame(1250, $createdExpense['amount_cents'], 'Negative amount -12.50 stores 1250 cents');
			$t->assertSame(12.5, $createdExpense['amount'], 'Amount formats to 12.5');
			$t->assertSame($expectedTs, $createdExpense['date'], 'YYYY-MM-DD resolves to local noon timestamp');
			$t->assertSame(1, $createdExpense['category_id'], 'Case-insensitive category name resolves category ID');
			$t->assertSame(1, $createdExpense['payment_partner_id'], 'Case-insensitive payment_partner resolves partner ID');
			$t->assertTrue($createdExpense['is_important'], 'important=1 sets is_important');
			$t->assertTrue($createdExpense['is_fixed_cost'], 'fixed_cost=true sets is_fixed_cost');
			$t->assertFalse($createdExpense['needs_review'], 'review=0 keeps needs_review false');

			$createdIncome = $entriesService->createEntry($authCreate, [
				'date' => '2026-11-21',
				'amount' => 1850,
				'category' => 'Gehalt',
				'tax_relevant' => 1,
			]);
			$t->assertSame('income', $createdIncome['type'], 'Positive amount without type infers income');
			$t->assertSame(185000, $createdIncome['amount_cents'], 'Positive amount 1850 stores 185000 cents');
			$t->assertSame(3, $createdIncome['category_id'], 'Income category name resolves income category ID');
			$t->assertTrue($createdIncome['is_tax_relevant'], 'tax_relevant=1 sets is_tax_relevant');

			$patched = $entriesService->updateEntry($authWrite, (int)$createdExpense['id'], [
				'date' => '2026-11-22',
				'amount' => -19.99,
				'category' => null,
				'subscription' => 1,
				'important' => 0,
			]);
			$expectedPatchedTs = (new \DateTimeImmutable('2026-11-22 12:00:00', $authWrite['timezone']))->getTimestamp();
			$t->assertSame($expectedPatchedTs, $patched['date'], 'PATCH accepts YYYY-MM-DD date');
			$t->assertSame(1999, $patched['amount_cents'], 'PATCH updates signed amount to 1999 cents');
			$t->assertSame(null, $patched['category_id'], 'PATCH category=null clears category_id');
			$t->assertTrue($patched['is_subscription'], 'PATCH subscription=1 sets is_subscription');
			$t->assertFalse($patched['is_important'], 'PATCH important=0 clears is_important');

			// Validation & conflict edge cases
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-26', 'amount' => -5.00, 'category' => 'Lebensmittel', 'category_id' => 1,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-26', 'amount' => -5.00, 'payment_partner' => 'Supermarkt', 'payment_partner_id' => 1,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-26', 'amount' => -5.00, 'category' => 'Versteckt',
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-26', 'amount' => -5.00, 'category' => 'Unknown Category',
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-26', 'type' => 'income', 'amount' => -5.00,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'important' => 1, 'is_important' => false,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-02-30', 'amount' => -5.00,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'important' => 'yes',
			]), Http::STATUS_BAD_REQUEST);

			$incomeWithExpenseFlag = $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => 100.00, 'fixed_cost' => 1,
			]);
			$t->assertFalse($incomeWithExpenseFlag['is_fixed_cost'], 'Expense-only fixed_cost label is ignored on income');

			$config->userValues['alice']['enable_insurance'] = 'no';
			$disabledLabelEntry = $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'insurance' => 1,
			]);
			$t->assertFalse($disabledLabelEntry['is_insurance'], 'Disabled insurance label is forced to false');
		},
		'API v1 project_id accepts 0 or positive area ID and rejects string personal' => static function (TestRunner $t) use ($buildFixture, $expectHttp): void {
			['request' => $request, 'tokenService' => $tokenService, 'authService' => $authService, 'entriesService' => $entriesService, 'controller' => $controller] = $buildFixture();
			$tok = $tokenService->create('alice', 'ProjectId Check', 1, ['entries:read', 'entries:create', 'entries:write'], true, 'all', [], null);
			$request->headers['Authorization'] = 'Bearer ' . $tok['plain_token'];
			$authCreate = $authService->authenticateFixture('create', 1700000000);
			$authWrite = $authService->authenticateFixture('write', 1700000000);

			// POST /entries with project_id=0 creates a payment without an area; PATCH with project_id=0 preserves it
			$createdPersonal = $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20',
				'amount' => -10.00,
				'project_id' => 0,
			]);
			$t->assertSame(null, $createdPersonal['project_id'], 'project_id=0 creates a payment without an area');
			$patchedPersonal = $entriesService->updateEntry($authWrite, (int)$createdPersonal['id'], [
				'project_id' => 0,
				'description' => 'Still personal',
			]);
			$t->assertSame(null, $patchedPersonal['project_id'], 'PATCH project_id=0 keeps payment without an area');

			// String "personal" or negative ints must be rejected with 400
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'project_id' => 'personal',
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->createEntry($authCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'project_id' => -1,
			]), Http::STATUS_BAD_REQUEST);
			$expectHttp($t, static fn() => $entriesService->updateEntry($authWrite, (int)$createdPersonal['id'], [
				'project_id' => 'personal',
			]), Http::STATUS_BAD_REQUEST);

			// Controller lookup endpoints accept project_id=0 and reject project_id=personal with 400
			$request->setGet(['project_id' => '0']);
			$t->assertSame(Http::STATUS_OK, $controller->categories()->getStatus(), 'GET /categories?project_id=0 returns 200');
			$t->assertSame(Http::STATUS_OK, $controller->paymentPartners()->getStatus(), 'GET /payment-partners?project_id=0 returns 200');
			$request->setGet(['project_id' => 'personal']);
			$t->assertSame(Http::STATUS_BAD_REQUEST, $controller->categories()->getStatus(), 'GET /categories?project_id=personal returns 400');
			$t->assertSame(Http::STATUS_BAD_REQUEST, $controller->paymentPartners()->getStatus(), 'GET /payment-partners?project_id=personal returns 400');
			$request->setGet([]);

			// Controller POST /entries with real POST method + QUERY_STRING succeeds end-to-end (regression for get in POST LogicException)
			$f2 = $buildFixture();
			$pdo2 = $f2['db']->pdo;
			$pdo2->exec("INSERT INTO cobudget_payment_partners (id, name, is_global, user_id, workspace_id, icon, type, project_id, is_hidden) VALUES (99, 'Supermarkt & Bäcker', 0, 'alice', 1, 'Store', 'expense', NULL, 0)");
			$postTok = $f2['tokenService']->create('alice', 'Post Entry Key', 1, ['entries:create'], true, 'none', [], null);
			$f2['request']->method = 'POST';
			$f2['request']->server = ['QUERY_STRING' => ''];
			$f2['request']->headers['Authorization'] = 'Bearer ' . $postTok['plain_token'];
			$f2['request']->headers['Content-Type'] = 'application/json';
			$f2['authService']->rawBody = json_encode([
				'date' => '2026-11-26',
				'amount' => -12.5,
				'project_id' => 0,
				'category' => 'Lebensmittel',
				'payment_partner' => 'Supermarkt & Bäcker',
				'description' => 'Wocheneinkauf #Haushalt Test API',
			], JSON_THROW_ON_ERROR);
			$postRes = $f2['controller']->entriesCreate();
			$t->assertSame(Http::STATUS_CREATED, $postRes->getStatus(), 'POST /entries returns 201 when Request::get throws on non-GET');
			$postData = $postRes->getData();
			$t->assertSame(['id'], array_keys($postData['entry']), 'Create-only key receives the new entry ID');
			$stored = $pdo2->query('SELECT project_id, amount_cents, type FROM cobudget_entries WHERE id = ' . (int)$postData['entry']['id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame(null, $stored['project_id'], 'POST /entries project_id=0 creates unassigned entry');
			$t->assertSame(1250, (int)$stored['amount_cents'], 'POST /entries stores amount_cents 1250');
			$t->assertSame('expense', $stored['type'], 'POST /entries classifies negative amount as expense');

			// Area-only key without personal create permission rejects project_id=0 with 403
			$areaOnly = $tokenService->create('alice', 'Area Only', 1, ['entries:create'], false, 'selected', [10], null);
			$request->headers['Authorization'] = 'Bearer ' . $areaOnly['plain_token'];
			$authAreaCreate = $authService->authenticateFixture('create', 1700000000);
			$expectHttp($t, static fn() => $entriesService->createEntry($authAreaCreate, [
				'date' => '2026-11-20', 'amount' => -5.00, 'project_id' => 0,
			]), Http::STATUS_FORBIDDEN);
		},
	];
}

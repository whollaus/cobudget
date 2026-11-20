<?php

declare(strict_types=1);

namespace OCP\AppFramework {
	if (!class_exists(Controller::class, false)) {
		class Controller {
			public mixed $request;

			public function __construct(
				public string $appName = '',
				?\OCP\IRequest $request = null,
			) {
				$this->request = $request;
			}
		}
	}

	if (!class_exists(Http::class, false)) {
		final class Http {
			public const STATUS_OK = 200;
			public const STATUS_CREATED = 201;
			public const STATUS_BAD_REQUEST = 400;
			public const STATUS_UNAUTHORIZED = 401;
			public const STATUS_FORBIDDEN = 403;
			public const STATUS_NOT_FOUND = 404;
			public const STATUS_CONFLICT = 409;
			public const STATUS_INTERNAL_SERVER_ERROR = 500;
		}
	}
}

namespace OCP\AppFramework\Http {
	if (!class_exists(Response::class, false)) {
		class Response {
		}
	}
	if (!class_exists(DataResponse::class, false)) {
		class DataResponse extends Response {
			public function __construct(
				private mixed $data = [],
				private int $status = 200,
			) {
			}

			public function getData(): mixed {
				return $this->data;
			}

			public function getStatus(): int {
				return $this->status;
			}
		}
	}
	if (!class_exists(DataDownloadResponse::class, false)) {
		class DataDownloadResponse extends Response {
		}
	}
	if (!class_exists(FileDisplayResponse::class, false)) {
		class FileDisplayResponse extends Response {
		}
	}
}

namespace OCP\AppFramework\Http\Attribute {
	if (!class_exists(NoAdminRequired::class, false)) {
		#[\Attribute]
		final class NoAdminRequired {
		}
	}
	if (!class_exists(NoCSRFRequired::class, false)) {
		#[\Attribute]
		final class NoCSRFRequired {
		}
	}
	if (!class_exists(UserRateLimit::class, false)) {
		#[\Attribute]
		final class UserRateLimit {
			public function __construct(public int $limit = 0, public int $period = 0) {
			}
		}
	}
}

namespace OCP {
	if (!interface_exists(IRequest::class, false)) {
		interface IRequest {
		}
	}
	if (!interface_exists(IDBConnection::class, false)) {
		interface IDBConnection {
		}
	}
	if (!interface_exists(IUser::class, false)) {
		interface IUser {
		}
	}
	if (!interface_exists(IUserSession::class, false)) {
		interface IUserSession {
		}
	}
	if (!interface_exists(IUserManager::class, false)) {
		interface IUserManager {
		}
	}
	if (!interface_exists(IConfig::class, false)) {
		interface IConfig {
			public function getAppValue(string $appName, string $key, string $default = ''): string;
		}
	}
	if (!interface_exists(IL10N::class, false)) {
		interface IL10N {
		}
	}
	if (!interface_exists(IURLGenerator::class, false)) {
		interface IURLGenerator {
			/** @param array<string, mixed> $arguments */
			public function linkToRouteAbsolute(string $routeName, array $arguments = []): string;
		}
	}
}

namespace OCP\L10N {
	if (!interface_exists(IFactory::class, false)) {
		interface IFactory {
			/** @return object */
			public function get(string $appId, string $languageCode);
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

namespace OCP\Files {
	if (!interface_exists(IRootFolder::class, false)) {
		interface IRootFolder {
		}
	}
	if (!interface_exists(Folder::class, false)) {
		interface Folder {
		}
	}
	if (!interface_exists(File::class, false)) {
		interface File {
		}
	}
}

namespace OCP\Lock {
	if (!interface_exists(ILockingProvider::class, false)) {
		interface ILockingProvider {
			public const LOCK_SHARED = 1;
			public const LOCK_EXCLUSIVE = 2;
		}
	}
}

namespace OCP\Notification {
	if (!interface_exists(IManager::class, false)) {
		interface IManager {
		}
	}
}

namespace OCP\Mail {
	if (!interface_exists(IMailer::class, false)) {
		interface IMailer {
		}
	}
}

namespace Psr\Log {
	if (!interface_exists(LoggerInterface::class, false)) {
		interface LoggerInterface {
		}
	}
}

namespace CoBudget\Tests {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\EntryController;
	use OCA\CoBudget\Service\CalendarReminderService;
	use OCA\CoBudget\Service\DataChangeService;
	use OCA\CoBudget\Service\EntryAttachmentProjectionService;
	use OCA\CoBudget\Service\EntryProjectionService;
	use OCA\CoBudget\Service\EntryShareService;
	use OCA\CoBudget\Service\HashtagService;
	use OCA\CoBudget\Service\ParticipantService;
	use OCA\CoBudget\Service\ProjectNotificationService;
	use OCA\CoBudget\Service\ReminderService;
	use OCP\AppFramework\Http;
	use OCP\AppFramework\Http\DataResponse;
	use OCP\DB\QueryBuilder\IQueryBuilder;
	use OCP\Files\IRootFolder;
	use OCP\IConfig;
	use OCP\IDBConnection;
	use OCP\IL10N;
	use OCP\IRequest;
	use OCP\IUser;
	use OCP\IUserManager;
	use OCP\IUserSession;
	use OCP\Lock\ILockingProvider;
	use Psr\Log\LoggerInterface;

	require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceAwareTrait.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/HashtagService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/EntryShareCalculator.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/EntryShareService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/EntryAttachmentProjectionService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/EntryProjectionService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/ParticipantService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/CalendarReminderService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/DataChangeService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/ProjectNotificationService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/ReminderService.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/PaymentImportJson.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/EntryController.php';

	final class BulkImportTestResult {
		public function __construct(private \PDOStatement $statement) {
		}

		public function fetch(): array|false {
			return $this->statement->fetch(\PDO::FETCH_ASSOC);
		}

		public function fetchAll(): array {
			return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
		}

		public function fetchOne(): mixed {
			return $this->statement->fetchColumn(0);
		}

		public function closeCursor(): void {
			$this->statement->closeCursor();
		}
	}

	final class BulkImportTestFunc {
		public function count(string $expression = '*'): string {
			return 'COUNT(' . $expression . ')';
		}

		public function lower(string $expression): string {
			return 'LOWER(' . $expression . ')';
		}
	}

	final class BulkImportTestQueryBuilder implements IQueryBuilder {
		private string $mode = 'select';
		private string $table = '';
		private ?string $alias = null;
		private array $select = [];
		private array $joins = [];
		private array $where = [];
		private array $groupBy = [];
		private array $orderBy = [];
		private array $values = [];
		private array $sets = [];
		private array $params = [];
		private ?int $limit = null;

		public function __construct(private BulkImportTestDatabase $db) {
		}

		public function select(string ...$columns): self {
			$this->mode = 'select';
			$this->select = $columns === [] ? ['*'] : $columns;
			return $this;
		}

		public function selectAlias(string $expression, string $alias): self {
			$this->mode = 'select';
			$this->select = [$expression . ' AS ' . $alias];
			return $this;
		}

		public function addSelect(string ...$columns): self {
			array_push($this->select, ...$columns);
			return $this;
		}

		public function from(string $table, ?string $alias = null): self {
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function insert(string $table): self {
			$this->mode = 'insert';
			$this->table = $table;
			return $this;
		}

		public function values(array $values): self {
			$this->values = $values;
			return $this;
		}

		public function update(string $table, ?string $alias = null): self {
			$this->mode = 'update';
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function set(string $column, string $value): self {
			$this->sets[] = $column . ' = ' . $value;
			return $this;
		}

		public function delete(string $table, ?string $alias = null): self {
			$this->mode = 'delete';
			$this->table = $table;
			$this->alias = $alias;
			return $this;
		}

		public function innerJoin(string $fromAlias, string $table, string $alias, string $condition): self {
			$this->joins[] = "INNER JOIN {$table} {$alias} ON {$condition}";
			return $this;
		}

		public function leftJoin(string $fromAlias, string $table, string $alias, string $condition): self {
			$this->joins[] = "LEFT JOIN {$table} {$alias} ON {$condition}";
			return $this;
		}

		public function where(string $condition): self {
			$this->where = [$condition];
			return $this;
		}

		public function andWhere(string $condition): self {
			$this->where[] = $condition;
			return $this;
		}

		public function orWhere(string $condition): self {
			if ($this->where === []) {
				$this->where = [$condition];
			} else {
				$prev = implode(' AND ', $this->where);
				$this->where = ['((' . $prev . ') OR (' . $condition . '))'];
			}
			return $this;
		}

		public function groupBy(string $column): self {
			$this->groupBy = [$column];
			return $this;
		}

		public function addGroupBy(string $column): self {
			$this->groupBy[] = $column;
			return $this;
		}

		public function orderBy(string $column, ?string $direction = 'ASC'): self {
			$this->orderBy = [$column . ' ' . ($direction ?? 'ASC')];
			return $this;
		}

		public function addOrderBy(string $column, ?string $direction = 'ASC'): self {
			$this->orderBy[] = $column . ' ' . ($direction ?? 'ASC');
			return $this;
		}

		public function setMaxResults(int $limit): self {
			$this->limit = $limit;
			return $this;
		}

		public function forUpdate(): self {
			return $this;
		}

		public function expr(): self {
			return $this;
		}

		public function func(): BulkImportTestFunc {
			return new BulkImportTestFunc();
		}

		public function createFunction(string $sql): string {
			return $sql;
		}

		public function andX(string ...$parts): string {
			return '(' . implode(' AND ', $parts) . ')';
		}

		public function orX(string ...$parts): string {
			return '(' . implode(' OR ', $parts) . ')';
		}

		public function eq(string $column, string $value): string {
			return $column . ' = ' . $value;
		}

		public function neq(string $column, string $value): string {
			return $column . ' != ' . $value;
		}

		public function lt(string $column, string $value): string {
			return $column . ' < ' . $value;
		}

		public function lte(string $column, string $value): string {
			return $column . ' <= ' . $value;
		}

		public function gt(string $column, string $value): string {
			return $column . ' > ' . $value;
		}

		public function gte(string $column, string $value): string {
			return $column . ' >= ' . $value;
		}

		public function like(string $column, string $value): string {
			return $column . ' LIKE ' . $value;
		}

		public function in(string $column, string $placeholder): string {
			if ($placeholder === '__EMPTY_ARRAY__') {
				return '1 = 0';
			}
			return $column . ' IN (' . $placeholder . ')';
		}

		public function notIn(string $column, string $placeholder): string {
			if ($placeholder === '__EMPTY_ARRAY__') {
				return '1 = 1';
			}
			return $column . ' NOT IN (' . $placeholder . ')';
		}

		public function isNull(string $column): string {
			return $column . ' IS NULL';
		}

		public function isNotNull(string $column): string {
			return $column . ' IS NOT NULL';
		}

		public function createNamedParameter(mixed $value, int $type = \PDO::PARAM_STR): string {
			if ($type === IQueryBuilder::PARAM_INT_ARRAY || $type === IQueryBuilder::PARAM_STR_ARRAY) {
				$items = is_array($value) ? array_values($value) : [$value];
				if ($items === []) {
					return '__EMPTY_ARRAY__';
				}
				$names = [];
				$itemType = $type === IQueryBuilder::PARAM_INT_ARRAY ? \PDO::PARAM_INT : \PDO::PARAM_STR;
				foreach ($items as $item) {
					$name = ':p' . count($this->params);
					$this->params[$name] = [$itemType === \PDO::PARAM_INT ? (int)$item : (string)$item, $itemType];
					$names[] = $name;
				}
				return implode(', ', $names);
			}

			$name = ':p' . count($this->params);
			if ($type === \PDO::PARAM_BOOL) {
				$this->params[$name] = [$value ? 1 : 0, \PDO::PARAM_INT];
			} elseif ($type === \PDO::PARAM_NULL || $value === null) {
				$this->params[$name] = [null, \PDO::PARAM_NULL];
			} else {
				$this->params[$name] = [$value, $type];
			}
			return $name;
		}

		public function getSQL(): string {
			return $this->buildSQL();
		}

		public function executeQuery(): BulkImportTestResult {
			return new BulkImportTestResult($this->prepareAndExecute());
		}

		public function executeStatement(): int {
			return $this->prepareAndExecute()->rowCount();
		}

		private function prepareAndExecute(): \PDOStatement {
			$sql = $this->buildSQL();
			$statement = $this->db->pdo->prepare($sql);
			foreach ($this->params as $name => [$value, $type]) {
				$statement->bindValue($name, $value, $type);
			}
			$statement->execute();
			return $statement;
		}

		private function buildSQL(): string {
			if ($this->mode === 'insert') {
				$columns = array_keys($this->values);
				$placeholders = array_values($this->values);
				return 'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
			}

			if ($this->mode === 'update') {
				$sql = 'UPDATE ' . $this->table . ($this->alias !== null ? ' ' . $this->alias : '') . ' SET ' . implode(', ', $this->sets);
				if ($this->where !== []) {
					$sql .= ' WHERE ' . implode(' AND ', $this->where);
				}
				return $sql;
			}

			if ($this->mode === 'delete') {
				$sql = 'DELETE FROM ' . $this->table . ($this->alias !== null ? ' ' . $this->alias : '');
				if ($this->where !== []) {
					$sql .= ' WHERE ' . implode(' AND ', $this->where);
				}
				return $sql;
			}

			$columns = $this->select === [] ? ['*'] : $this->select;
			$sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $this->table . ($this->alias !== null ? ' ' . $this->alias : '');
			if ($this->joins !== []) {
				$sql .= ' ' . implode(' ', $this->joins);
			}
			if ($this->where !== []) {
				$sql .= ' WHERE ' . implode(' AND ', $this->where);
			}
			if ($this->groupBy !== []) {
				$sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
			}
			if ($this->orderBy !== []) {
				$sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
			}
			if ($this->limit !== null) {
				$sql .= ' LIMIT ' . $this->limit;
			}
			return $sql;
		}
	}

	final class BulkImportTestDatabase implements IDBConnection {
		public \PDO $pdo;

		public function __construct() {
			$this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
			$this->pdo->exec('CREATE TABLE cobudget_workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT, name TEXT, is_default INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0, created_at INTEGER DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, owner_id TEXT, workspace_id INTEGER, is_archived INTEGER DEFAULT 0, created_at INTEGER DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_members (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, user_id TEXT, share_basis_points INTEGER DEFAULT 0, expense_rounding_units INTEGER DEFAULT 0, income_rounding_units INTEGER DEFAULT 0, personal_workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, type TEXT, is_global INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0, user_id TEXT, workspace_id INTEGER, project_id INTEGER, icon TEXT, code TEXT, parent_category_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_payment_partners (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, type TEXT, is_global INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0, user_id TEXT, workspace_id INTEGER, project_id INTEGER, number TEXT, salutation TEXT, title TEXT, company_name TEXT, additional TEXT, vat_id TEXT, first_name TEXT, last_name TEXT, street TEXT, postal_code TEXT, city TEXT, country TEXT, email TEXT, phone TEXT, mobile TEXT, fax TEXT, web TEXT, account_holder TEXT, iban TEXT, bic TEXT, bank TEXT, bank_code TEXT, account_number TEXT, address_note TEXT, note TEXT)');
			$this->pdo->exec('CREATE TABLE cobudget_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, import_key TEXT UNIQUE, user_id TEXT, created_by TEXT, project_id INTEGER, entry_kind TEXT, source_entry_id INTEGER, is_locked INTEGER DEFAULT 0, allocation_basis_points INTEGER, type TEXT, amount TEXT, amount_cents INTEGER, currency TEXT, date INTEGER, category_id INTEGER, description TEXT, payment_partner_id INTEGER, split_mode TEXT, split_user_id TEXT, is_settled INTEGER DEFAULT 0, settled_at INTEGER, settlement_id INTEGER, recurrence_interval TEXT, recurrence_multiplier INTEGER, recurrence_next_date INTEGER, recurrence_end_date INTEGER, is_subscription INTEGER DEFAULT 0, is_fixed_cost INTEGER DEFAULT 0, is_child_related INTEGER DEFAULT 0, is_important INTEGER DEFAULT 0, needs_review INTEGER DEFAULT 0, is_tax_relevant INTEGER DEFAULT 0, is_insurance INTEGER DEFAULT 0, reminder_date INTEGER, planned_notified INTEGER DEFAULT 1, reminder_notified INTEGER DEFAULT 0, reminder_text TEXT, recurrence_parent_id INTEGER, recurrence_series_id INTEGER, workspace_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_shares (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, user_id TEXT, share_basis_points INTEGER, amount_cents INTEGER, personal_entry_id INTEGER, rounding_bucket TEXT, rounding_residual_units INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, source_attachment_id INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_hashtags (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, normalized_name TEXT, display_name TEXT, created_at INTEGER, updated_at INTEGER)');
			$this->pdo->exec('CREATE TABLE cobudget_entry_hashtags (id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER, hashtag_id INTEGER, workspace_id INTEGER, created_at INTEGER DEFAULT 0)');
			$this->pdo->exec('CREATE TABLE cobudget_deleted_users (tombstone_id TEXT PRIMARY KEY, display_name TEXT)');
		}

		public function getQueryBuilder(): BulkImportTestQueryBuilder {
			return new BulkImportTestQueryBuilder($this);
		}

		public function beginTransaction(): void {
			$this->pdo->beginTransaction();
		}

		public function commit(): void {
			$this->pdo->commit();
		}

		public function rollBack(): void {
			if ($this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			}
		}

		public function inTransaction(): bool {
			return $this->pdo->inTransaction();
		}

		public function lastInsertId(?string $sequenceName = null): string|int {
			return $this->pdo->lastInsertId();
		}
	}

	final class BulkImportTestConfig implements IConfig {
		/** @var array<string, array<string, string>> */
		public array $userValues = [];

		public function getAppValue(string $appName, string $key, string $default = ''): string {
			return $default;
		}

		public function getUserValue(string $userId, string $appName, string $key, string $default = ''): string {
			return $this->userValues[$userId][$key] ?? $default;
		}

		public function setUserValue(string $userId, string $appName, string $key, string $value): void {
			$this->userValues[$userId][$key] = $value;
		}
	}

	final class BulkImportTestUser implements IUser {
		public function __construct(private string $uid, private string $displayName = '') {
		}

		public function getUID(): string {
			return $this->uid;
		}

		public function getDisplayName(): string {
			return $this->displayName !== '' ? $this->displayName : $this->uid;
		}
	}

	final class BulkImportTestUserSession implements IUserSession {
		public function __construct(private ?IUser $user) {
		}

		public function getUser(): ?IUser {
			return $this->user;
		}
	}

	final class BulkImportTestUserManager implements IUserManager {
		/** @var array<string, IUser> */
		public array $users = [];

		public function get(string $uid): ?IUser {
			return $this->users[$uid] ?? null;
		}
	}

	final class BulkImportTestRequest implements IRequest {
		public function __construct(private array $headers = [], public mixed $put = null) {
		}

		public function getHeader(string $name): string {
			return $this->headers[$name] ?? '';
		}
	}

	final class BulkImportTestRootFolder implements IRootFolder {
	}

	final class BulkImportTestLogger implements LoggerInterface {
		public array $errors = [];

		public function error(string $message, array $context = []): void {
			$this->errors[] = $message;
		}

		public function warning(string $message, array $context = []): void {
		}

		public function debug(string $message, array $context = []): void {
		}
	}

	final class BulkImportTestL10n implements IL10N {
		public function t(string $text, array $parameters = []): string {
			if ($parameters === []) {
				return $text;
			}
			$formatted = vsprintf($text, $parameters);
			return $formatted === false ? $text : $formatted;
		}
	}

	final class BulkImportTestLock implements ILockingProvider {
		public function acquireLock(string $path, int $type): void {
		}

		public function releaseLock(string $path, int $type): void {
		}
	}

	final class BulkImportTestCalendarReminders extends CalendarReminderService {
		public function __construct() {
		}

		public function syncUser(string $userId): void {
		}
	}

	final class BulkImportTestNotificationSpy extends ProjectNotificationService {
		public array $calls = [];

		public function __construct() {
		}

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
		): void {
			$this->calls[] = [$projectId, $workspaceId, $entryId, $actorUserId, $entryUserId, $type, $amountCents, $currency, $description];
		}
	}

	final class BulkImportTestReminderService extends ReminderService {
		public function __construct() {
		}
	}

	function makeBulkImportHarness(?string $activeUserId = 'alice'): array {
		$db = new BulkImportTestDatabase();
		$db->pdo->exec("INSERT INTO cobudget_workspaces (id, user_id, name, is_default, is_hidden, created_at) VALUES (1, 'alice', 'Basis', 1, 0, 1000), (2, 'bob', 'Basis', 1, 0, 1000), (3, 'alice', 'Second', 0, 0, 1001)");
		$db->pdo->exec("INSERT INTO cobudget_projects (id, name, owner_id, workspace_id, is_archived, created_at) VALUES (10, 'Household', 'alice', 1, 0, 1000), (11, 'ArchivedArea', 'alice', 1, 1, 1000), (12, 'FormerArea', 'alice', 1, 0, 1000)");
		$db->pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points, personal_workspace_id) VALUES (10, 'alice', 6000, 1), (10, 'bob', 4000, 2), (11, 'alice', 10000, 1), (12, 'alice', 5000, 1), (12, 'former:carol', 5000, NULL)");
		$db->pdo->exec("INSERT INTO cobudget_categories (id, name, type, is_global, is_hidden, user_id, workspace_id, project_id, icon) VALUES
			(1, 'Groceries', 'expense', 1, 0, NULL, NULL, NULL, 'Cart'),
			(2, 'HiddenGlobal', 'expense', 1, 1, NULL, NULL, NULL, 'EyeOff'),
			(3, 'Salary', 'income', 1, 0, NULL, NULL, NULL, 'Cash'),
			(4, 'Local Café', 'expense', 0, 0, 'alice', 1, NULL, 'Coffee'),
			(5, 'OtherWsCat', 'expense', 0, 0, 'alice', 3, NULL, 'Tag'),
			(6, 'SharedRent', 'expense', 0, 0, 'alice', 1, 10, 'Home'),
			(7, 'AlicePersonalRent', 'expense', 0, 0, 'alice', 1, NULL, 'Home')");
		$db->pdo->exec("INSERT INTO cobudget_payment_partners (id, name, type, is_global, is_hidden, user_id, workspace_id, project_id) VALUES
			(1, 'Global Market', 'expense', 1, 0, NULL, NULL, NULL),
			(2, 'Employer Corp', 'income', 1, 0, NULL, NULL, NULL),
			(3, 'Local Bakery', 'expense', 0, 0, 'alice', 1, NULL),
			(4, 'Landlord GmbH', 'expense', 0, 0, 'alice', 1, 10)");

		$config = new BulkImportTestConfig();
		$config->userValues['alice'] = [
			'active_workspace_id' => '1',
			'currency' => 'EUR',
			'timezone' => 'Europe/Berlin',
			'enable_projects' => 'yes',
			'enable_incomes' => 'yes',
			'enable_future_payments' => 'yes',
			'enable_important_payments' => 'yes',
			'enable_review_payments' => 'yes',
			'enable_fixed_costs' => 'yes',
			'enable_child_related' => 'yes',
			'enable_subscriptions' => 'yes',
			'enable_tax_relevant' => 'yes',
			'enable_insurance' => 'no',
		];
		$config->userValues['bob'] = [
			'active_workspace_id' => '2',
			'currency' => 'EUR',
			'timezone' => 'Europe/Berlin',
			'enable_projects' => 'yes',
			'enable_incomes' => 'yes',
			'enable_future_payments' => 'yes',
			'enable_important_payments' => 'yes',
			'enable_review_payments' => 'yes',
			'enable_fixed_costs' => 'yes',
			'enable_child_related' => 'yes',
			'enable_subscriptions' => 'yes',
			'enable_tax_relevant' => 'yes',
			'enable_insurance' => 'yes',
		];

		$userManager = new BulkImportTestUserManager();
		$userManager->users['alice'] = new BulkImportTestUser('alice', 'Alice');
		$userManager->users['bob'] = new BulkImportTestUser('bob', 'Bob');
		$userManager->users['carol'] = new BulkImportTestUser('carol', 'Carol');
		$session = new BulkImportTestUserSession($activeUserId !== null ? ($userManager->users[$activeUserId] ?? null) : null);

		$hashtags = new HashtagService($db);
		$shares = new EntryShareService($db);
		$participants = new ParticipantService($db, $userManager);
		$rootFolder = new BulkImportTestRootFolder();
		$attachments = new EntryAttachmentProjectionService($db, $rootFolder, $config, $participants);
		$projection = new EntryProjectionService($db, $shares, $hashtags, $attachments);
		$logger = new BulkImportTestLogger();
		$dataChange = new DataChangeService($db, $config, $logger, new BulkImportTestCalendarReminders());
		$notifier = new BulkImportTestNotificationSpy();

		$controller = new EntryController(
			'cobudget',
			new BulkImportTestRequest(),
			$db,
			$session,
			$config,
			$hashtags,
			$shares,
			$projection,
			$attachments,
			$dataChange,
			$notifier,
			$participants,
			$rootFolder,
			new BulkImportTestL10n(),
			new BulkImportTestReminderService(),
		);

		return [$controller, $db, $config, $notifier];
	}

	return [
		'Bulk import route and EntryController contract are registered cleanly' => function (TestRunner $t): void {
			$routes = require dirname(__DIR__, 2) . '/appinfo/routes.php';
			$found = null;
			foreach ($routes['routes'] ?? [] as $route) {
				if (($route['name'] ?? '') === 'entry#importPacket') {
					$found = $route;
					break;
				}
			}
			$t->assertTrue($found !== null, 'routes.php must register entry#importPacket');
			$t->assertSame('/api/entries/import', $found['url'] ?? '', 'Bulk import route URL must match frontend endpoint');
			$t->assertSame('PUT', $found['verb'] ?? '', 'Bulk import route must use PUT');

			$body = $t->methodBody('lib/Controller/EntryController.php', 'bulkImport');
			$t->assertContains('$this->authErrorResponse()', $body, 'Bulk import must require an authenticated user');
			$t->assertNotContains('notifyEntryCreated', $body, 'Bulk import must suppress entry creation notifications');
			$t->assertContains("'planned_notified' => \$qb->createNamedParameter(true, \\PDO::PARAM_BOOL)", $body, 'Imported payments must suppress delayed planned-payment notifications');
		},

		'Bulk import parser helpers validate dates, amounts, strings and label flags strictly' => function (TestRunner $t): void {
			[$controller] = makeBulkImportHarness('alice');
			$tz = new \DateTimeZone('Europe/Berlin');

			$parseTs = new \ReflectionMethod($controller, 'parseBulkImportTimestamp');
			$t->assertSame(null, $parseTs->invoke($controller, '18.11.2026', $tz, 1795000000, '2026-11-18'), 'German date format must be rejected');
			$t->assertSame(null, $parseTs->invoke($controller, '2026-02-29', $tz, 1795000000, '2026-11-18'), 'Impossible calendar date must be rejected');
			$t->assertSame(null, $parseTs->invoke($controller, '1969-12-31', $tz, 1795000000, '2026-11-18'), 'Pre-epoch date must be rejected');
			$t->assertSame(null, $parseTs->invoke($controller, '12001-01-01', $tz, 1795000000, '2026-11-18'), 'Year must have four digits');
			$pastNoon = (new \DateTimeImmutable('2026-05-01 12:00:00', $tz))->getTimestamp();
			$t->assertSame($pastNoon, $parseTs->invoke($controller, '2026-05-01', $tz, 1795000000, '2026-11-18'), 'Valid dates must map to 12:00 in the user timezone');
			$earlyMorning = (new \DateTimeImmutable('2026-11-18 07:30:00', $tz))->getTimestamp();
			$t->assertSame($earlyMorning, $parseTs->invoke($controller, '2026-11-18', $tz, $earlyMorning, '2026-11-18'), 'Today before noon must clamp to now so it is not treated as planned future');

			$parseAmount = new \ReflectionMethod($controller, 'parseBulkImportAmountCents');
			$t->assertSame(-4590, $parseAmount->invoke($controller, -45.9), 'Negative float parses to exact negative cents');
			$t->assertSame(120050, $parseAmount->invoke($controller, '1200.5'), 'Positive one-decimal string pads fraction right');
			$t->assertSame(10000, $parseAmount->invoke($controller, 100), 'Integer parses cleanly to cents');
			$t->assertSame(null, $parseAmount->invoke($controller, '12,34'), 'Comma decimal separator must be rejected');
			$t->assertSame(null, $parseAmount->invoke($controller, '0.00'), 'Zero amount must be rejected');
			$t->assertSame(null, $parseAmount->invoke($controller, '1.234'), 'More than two decimals must be rejected');
			$t->assertSame(null, $parseAmount->invoke($controller, '100000000.00'), 'Amounts >= 100000000 must fail overflow guard');
			$t->assertSame(null, $parseAmount->invoke($controller, INF), 'Infinite float must be rejected');

			$parseLookup = new \ReflectionMethod($controller, 'parseBulkImportLookupName');
			$valid = null;
			$t->assertSame('', $parseLookup->invokeArgs($controller, [null, &$valid]), 'Null lookup maps to empty');
			$t->assertTrue($valid === true, 'Null lookup is valid');
			$t->assertSame('Fresh Farm', $parseLookup->invokeArgs($controller, ['  Fresh   Farm  ', &$valid]), 'Whitespace is normalized');
			$t->assertSame('', $parseLookup->invokeArgs($controller, ["bad\tname", &$valid]), 'Tab control chars rejected in lookup name');
			$t->assertTrue($valid === false, 'Tab control char marks lookup invalid');
			$t->assertSame('', $parseLookup->invokeArgs($controller, [str_repeat('a', 129), &$valid]), 'Over 128 chars rejected');
			$t->assertTrue($valid === false, 'Length >128 marks lookup invalid');
			$t->assertSame('', $parseLookup->invokeArgs($controller, ["bad\nname", &$valid]), 'Control chars rejected in lookup name');
			$t->assertTrue($valid === false, 'Control char marks lookup invalid');
			$t->assertSame('', $parseLookup->invokeArgs($controller, ["bad\xFFname", &$valid]), 'Invalid UTF-8 bytes rejected in lookup name');
			$t->assertTrue($valid === false, 'Invalid UTF-8 bytes mark lookup invalid');
			$t->assertSame('', $parseLookup->invokeArgs($controller, ["bad\u{FFFD}name", &$valid]), 'Replacement char U+FFFD rejected in lookup name');
			$t->assertTrue($valid === false, 'Replacement char marks lookup invalid');

			$parseDesc = new \ReflectionMethod($controller, 'parseBulkImportDescription');
			$t->assertSame('Line1\nLine2', $parseDesc->invokeArgs($controller, [' Line1\nLine2 ', &$valid]), 'Description trims outer whitespace');
			$t->assertTrue($valid === true, 'Description <=512 chars is valid');
			$parseDesc->invokeArgs($controller, [str_repeat('x', 513), &$valid]);
			$t->assertTrue($valid === false, 'Description >512 chars is rejected');
			$parseDesc->invokeArgs($controller, ["bad\xFFdesc", &$valid]);
			$t->assertTrue($valid === false, 'Invalid UTF-8 bytes rejected in description');
			$parseDesc->invokeArgs($controller, ["bad\u{FFFD}desc", &$valid]);
			$t->assertTrue($valid === false, 'Replacement char U+FFFD rejected in description');

			$parseFlag = new \ReflectionMethod($controller, 'parseBulkImportLabelFlag');
			$t->assertSame(true, $parseFlag->invoke($controller, 1), 'Integer 1 parses true');
			$t->assertSame(true, $parseFlag->invoke($controller, '1'), 'String 1 parses true');
			$t->assertSame(false, $parseFlag->invoke($controller, 0), 'Integer 0 parses false');
			$t->assertSame(false, $parseFlag->invoke($controller, false), 'Boolean false parses false');
			$t->assertSame(null, $parseFlag->invoke($controller, 2), 'Non-0/1 integer parses null');
			$t->assertSame(null, $parseFlag->invoke($controller, 'yes'), 'String yes parses null');
		},

		'Bulk import enforces authentication, limits, area state, and atomic validation' => function (TestRunner $t): void {
			[$anon] = makeBulkImportHarness(null);
			$unauth = $anon->bulkImport(null, [['date' => '2026-10-01', 'amount' => -10]], true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_UNAUTHORIZED, $unauth->getStatus(), 'Anonymous requests must be rejected with 401');

			[$alice, $db] = makeBulkImportHarness('alice');
			$empty = $alice->bulkImport(null, [], true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_BAD_REQUEST, $empty->getStatus(), 'Empty payments list must return 400');

			$tooMany = array_fill(0, 5001, ['date' => '2026-10-01', 'amount' => -1]);
			$limitResp = $alice->bulkImport(null, $tooMany, true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_BAD_REQUEST, $limitResp->getStatus(), 'More than 5000 payments must return 400');

			$badProject = $alice->bulkImport(-5, [['date' => '2026-10-01', 'amount' => -10]], true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_BAD_REQUEST, $badProject->getStatus(), 'Non-positive projectId returns 400');

			$archived = $alice->bulkImport(11, [['date' => '2026-10-01', 'amount' => -10]], true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_CONFLICT, $archived->getStatus(), 'Archived area rejects bulk import with 409');

			$former = $alice->bulkImport(12, [['date' => '2026-10-01', 'amount' => -10]], true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_CONFLICT, $former->getStatus(), 'Area with former member rejects bulk import with 409');

			$invalidMixed = $alice->bulkImport(null, [
				['date' => '2026-10-01', 'amount' => -12.34, 'category' => 'BrandNewCat'],
				['date' => '2026-10-02', 'amount' => '0.00', 'unknown_key' => 1, 'important' => 2],
			], false, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_BAD_REQUEST, $invalidMixed->getStatus(), 'Invalid rows abort whole batch with 400');
			$t->assertTrue(count($invalidMixed->getData()['errors'] ?? []) >= 1, 'Validation errors are reported');
			$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Invalid batch inserts zero entries');
			$t->assertSame(7, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_categories')->fetchColumn(), 'Invalid batch inserts zero categories');
		},

		'Import endpoint bounds the body, packet size and absolute positions before any writes' => function (TestRunner $t): void {
			[$alice, $db, $config] = makeBulkImportHarness();
			$t->assertSame(403, $alice->importPacket()->getStatus(), 'Manual import requires opt-in before reading the request body');
			$config->setUserValue('alice', 'cobudget', 'enable_payment_imports', 'yes');
			$row = ['date' => '2026-10-01', 'amount' => -1];
			$packet = ['importId' => hash('sha256', 'limits'), 'offset' => 4950, 'dryRun' => true, 'payments' => array_fill(0, 50, $row)];
			$request = static function (array $payload, bool $withLength = true) use ($alice) {
				$body = json_encode($payload, JSON_THROW_ON_ERROR);
				$stream = fopen('php://memory', 'r+');
				fwrite($stream, $body);
				rewind($stream);
				$alice->request = new BulkImportTestRequest(['Content-Type' => 'application/octet-stream', 'Content-Length' => $withLength ? (string)strlen($body) : ''], $stream);
				return $alice->importPacket();
			};

			$preview = $request($packet);
			$t->assertSame(200, $preview->getStatus(), 'Last complete packet fits');
			$t->assertSame(200, $request($packet, false)->getStatus(), 'Bounded stream works when a proxy omits the length');
			$t->assertSame(413, $request(array_merge($packet, ['padding' => str_repeat(' ', 196608)]), false)->getStatus(), 'Stream limit works without a declared length');
			$t->assertSame(5000, $preview->getData()['rows'][49]['rowNumber'], 'Global position at the boundary');
			$t->assertSame(400, $request(array_replace($packet, ['offset' => 4951]))->getStatus(), 'Reject past the file boundary');
			$t->assertSame(400, $request(array_replace($packet, ['offset' => 0, 'payments' => array_fill(0, 51, $row)]))->getStatus(), 'Reject over 50 rows');
			$t->assertSame(400, $request(array_replace($packet, ['dryRun' => false]))->getStatus(), 'Commit needs the preview target');
			$alice->request = new BulkImportTestRequest(['Content-Type' => 'application/octet-stream', 'Content-Length' => '196609']);
			$t->assertSame(413, $alice->importPacket()->getStatus(), 'Oversized packet is rejected before reading the body');
			$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'All tests leave the database empty');
		},

		'Preview and commit reject fractional cents before writing any payment in the packet' => function (TestRunner $t): void {
			[$alice, $db] = makeBulkImportHarness();
			$valid = ['date' => '2026-01-01', 'amount' => -1.23];
			foreach ([-1.2300001, -1.2299999, -99999999.99000002] as $amount) {
				$rows = [$valid, ['date' => '2026-01-01', 'amount' => $amount]];
				$id = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
				foreach ([true, false] as $preview) {
					$t->assertSame(400, $alice->bulkImport(null, $rows, $preview, $id)->getStatus(), 'Fractional cents invalidate the whole packet');
				}
			}
			$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Even the valid first row remains unbooked');
			$id = hash('sha256', 'exact cents');
			$t->assertSame(200, $alice->bulkImport(null, [$valid], false, $id)->getStatus(), 'Exact cents remain valid');
			$t->assertSame(123, (int)$db->pdo->query('SELECT amount_cents FROM cobudget_entries')->fetchColumn(), 'Exact amount stored');
		},

		'Bulk import previews and stores the configured or locale-derived payment currency' => function (TestRunner $t): void {
			foreach ([
				['de_CH', '', '', 'CHF'],
				['de_CH', '', 'USD', 'USD'],
				['', 'en-GB', '', 'GBP'],
				['', '', '', 'EUR'],
			] as [$locale, $language, $currency, $expected]) {
				[$alice, $db, $config] = makeBulkImportHarness();
				$config->setUserValue('alice', 'core', 'locale', $locale);
				$config->setUserValue('alice', 'core', 'lang', $language);
				$config->setUserValue('alice', 'cobudget', 'currency', $currency);
				$rows = [['date' => '2026-01-01', 'amount' => -12.50]];
				$id = hash('sha256', 'payment currency');
				$preview = $alice->bulkImport(10, $rows, true, $id);
				$t->assertSame(200, $preview->getStatus(), 'Shared import preview succeeds');
				$t->assertSame($expected, $preview->getData()['summary']['currency'], 'Preview uses the account currency');
				$commit = $alice->bulkImport(10, $rows, false, $id, 0, $preview->getData()['scope']);
				$t->assertSame(200, $commit->getStatus(), 'Shared import succeeds');
				$t->assertSame([$expected, $expected, $expected], $db->pdo->query('SELECT currency FROM cobudget_entries ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN), 'Original and both personal allocations use that currency');
			}
		},

		'Bulk import checks the signed database timestamp at local noon before writing' => function (TestRunner $t): void {
			foreach ([
				['UTC', '2038-01-18', '2038-01-19'],
				['Pacific/Kiritimati', '2038-01-19', '2038-01-20'],
			] as [$zone, $last, $firstInvalid]) {
				[$alice, $db] = makeBulkImportHarness();
				$alice->request = new BulkImportTestRequest(['X-CoBudget-Timezone' => $zone]);
				$id = hash('sha256', 'date boundary');
				$row = ['date' => $last, 'amount' => -1];
				$preview = $alice->bulkImport(null, [$row], true, $id);
				$t->assertSame(200, $preview->getStatus(), 'Last representable local noon is valid in ' . $zone);
				$scope = $preview->getData()['scope'];
				foreach ([true, false] as $dryRun) {
					$rows = [$row, ['date' => $firstInvalid, 'amount' => -1]];
					$response = $alice->bulkImport(null, $rows, $dryRun, $id, 50, $scope);
					$t->assertSame(400, $response->getStatus(), 'Reject first unrepresentable noon in ' . $zone);
					$t->assertSame(["Row 52: The date $firstInvalid is too late to store as a payment."], $response->getData()['errors'], 'Report the original file row and offending date');
				}
				$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Invalid packet writes nothing');
				$t->assertSame(200, $alice->bulkImport(null, [$row], false, $id, 0, $scope)->getStatus(), 'Boundary date can be saved');
				$expected = (new \DateTimeImmutable($last . ' 12:00:00', new \DateTimeZone($zone)))->getTimestamp();
				$t->assertSame($expected, (int)$db->pdo->query('SELECT date FROM cobudget_entries')->fetchColumn(), 'Stored date preserves local noon');
			}
		},

		'Resuming overlapping packets preserves distinct positions and area allocations' => function (TestRunner $t): void {
			[$alice, $db] = makeBulkImportHarness();
			$id = hash('sha256', 'repeated payment in two positions');
			$row = ['date' => '2026-10-01', 'amount' => -12.35];
			$rows = [$row, $row];
			$scope = $alice->bulkImport(10, $rows, true, $id)->getData()['scope'];
			$first = $alice->bulkImport(10, [$row], false, $id, 0, $scope);
			$t->assertSame(1, $first->getData()['importedCount'], 'First payment committed before disconnect');
			$remaining = $alice->bulkImport(10, $rows, true, $id, 0, $scope)->getData();
			$t->assertSame([2], array_column($remaining['rows'], 'rowNumber'), 'Preview preserves original position after interruption');
			$resumed = $alice->bulkImport(10, $rows, false, $id, 0, $scope)->getData();
			$t->assertSame(1, $resumed['skippedCount'], 'Overlapping first payment skipped');
			$t->assertSame(1, $resumed['importedCount'], 'Identical second payment kept as a separate original');
			$t->assertSame(0, $alice->bulkImport(10, $rows, false, $id, 0, $scope)->getData()['importedCount'], 'Full replay writes nothing');
			$t->assertSame(6, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Two originals and four personal allocations');
			$t->assertSame(409, $alice->bulkImport(null, $rows, false, $id, 0, $scope)->getStatus(), 'Previous preview cannot write to another target');
			$t->assertSame(0, $alice->bulkImport(null, $rows, true, $id)->getData()['skippedCount'], 'Same file can be used in a different target');

			$db->pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points) VALUES (10, 'charlie', 0)");
			$full = array_fill(0, 50, $row);
			$t->assertSame(33, $alice->bulkImport(10, $full, true, $id)->getData()['commitLimit'], 'Three members lower the commit limit to 33 originals');
			$t->assertSame(409, $alice->bulkImport(10, $full, false, $id, 0, $scope)->getStatus(), 'Server enforces the new limit after membership changes');
		},

		'Personal dry-run and commit reuse global/personal lookups, create missing once, sync hashtags, and skip notifications' => function (TestRunner $t): void {
			[$alice, $db, $config, $notifier] = makeBulkImportHarness('alice');
			$payload = [
				[
					'date' => '2026-10-10',
					'amount' => -45.90,
					'category' => '  groceries ',
					'payment_partner' => 'local   bakery',
					'description' => 'Weekly bread #Food #Bakery',
					'important' => 1,
					'insurance' => 1,
				],
				[
					'date' => '2026-10-11',
					'amount' => -19.00,
					'category' => 'Brand New Hobby',
					'payment_partner' => 'New Gear Shop',
					'description' => 'Board game #Hobby',
					'subscription' => 1,
				],
				[
					'date' => '2026-10-12',
					'amount' => -5.50,
					'category' => 'brand new hobby',
					'payment_partner' => 'HiddenGlobal',
					'description' => 'Dice #Hobby',
				],
				[
					'date' => '2026-10-15',
					'amount' => 2500.00,
					'category' => 'Salary',
					'payment_partner' => 'Employer Corp',
					'description' => 'October salary',
					'fixed_cost' => 1,
					'subscription' => 1,
				],
			];

			$previewResp = $alice->bulkImport(null, $payload, true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_OK, $previewResp->getStatus(), 'Dry-run personal import returns 200');
			$preview = $previewResp->getData();
			$t->assertSame('preview', $preview['status'], 'Dry-run status is preview');
			$t->assertSame(4, $preview['summary']['totalCount'], 'Preview summary counts all rows');
			$t->assertSame(3, $preview['summary']['expenseCount'], 'Preview counts 3 expenses');
			$t->assertSame(70.4, (float)$preview['summary']['expenseTotal'], 'Preview sums expenses accurately');
			$t->assertSame(1, $preview['summary']['incomeCount'], 'Preview counts 1 income');
			$t->assertSame(2500.0, (float)$preview['summary']['incomeTotal'], 'Preview sums income accurately');
			$t->assertSame(1, count($preview['newCategories']), 'Deduplicated new category reported once');
			$t->assertSame('Brand New Hobby', $preview['newCategories'][0]['name'], 'Preserves first-seen casing for new category');
			$t->assertSame(2, count($preview['newPaymentPartners']), 'Deduplicated new payment partners reported cleanly');
			$t->assertSame('Groceries', $preview['rows'][0]['categoryName'], 'Global category match resolves canonical stored name');
			$t->assertSame(false, $preview['rows'][0]['isNewCategory'], 'Matched global category is not marked new');
			$t->assertSame('Local Bakery', $preview['rows'][0]['paymentPartnerName'], 'Personal partner match resolves canonical stored name');
			$t->assertSame(true, $preview['rows'][1]['isNewCategory'], 'Missing category marks row cell as new');
			$t->assertSame(true, $preview['rows'][2]['isNewCategory'], 'Second occurrence of new category in preview also highlights as new');
			$t->assertSame(false, $preview['rows'][0]['is_insurance'], 'Disabled label setting forces flag false in preview');
			$t->assertSame(false, $preview['rows'][3]['is_fixed_cost'], 'Expense-only fixed_cost label stays false on income');
			$t->assertSame(false, $preview['rows'][3]['is_subscription'], 'Expense-only subscription label stays false on income');
			$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Dry-run writes zero entries');

			$commitResp = $alice->bulkImport(null, $payload, false, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_OK, $commitResp->getStatus(), 'Commit personal import returns 200');
			$commit = $commitResp->getData();
			$t->assertSame('success', $commit['status'], 'Commit status is success');
			$t->assertSame(4, $commit['importedCount'], '4 entries imported');
			$t->assertSame(1, $commit['createdCategoriesCount'], '1 personal category created');
			$t->assertSame(2, $commit['createdPaymentPartnersCount'], '2 personal partners created');
			$t->assertSame([], $notifier->calls, 'Personal import triggers zero notifications');

			$replay = $alice->bulkImport(null, $payload, false, hash('sha256', 'fixture'));
			$t->assertSame(0, $replay->getData()['importedCount'], 'Replaying a committed packet creates no rows');
			$t->assertSame(4, $replay->getData()['skippedCount'], 'All original positions are acknowledged');

			$entries = $db->pdo->query('SELECT * FROM cobudget_entries ORDER BY id ASC')->fetchAll(\PDO::FETCH_ASSOC);
			$t->assertSame(4, count($entries), '4 rows stored in cobudget_entries');
			$t->assertSame(1, (int)$entries[0]['category_id'], 'Matched global category id reused');
			$t->assertSame(3, (int)$entries[0]['payment_partner_id'], 'Matched personal partner id reused');
			$t->assertSame(1, (int)$entries[0]['planned_notified'], 'planned_notified set to true');
			$t->assertSame(0, (int)$entries[0]['is_insurance'], 'Disabled label persisted as 0');
			$t->assertSame((int)$entries[1]['category_id'], (int)$entries[2]['category_id'], 'Duplicate new category shares newly created category row');
			$createdCat = $db->pdo->query('SELECT * FROM cobudget_categories WHERE id = ' . (int)$entries[1]['category_id'])->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame('alice', $createdCat['user_id'], 'Created category owned by importing user');
			$t->assertSame(1, (int)$createdCat['workspace_id'], 'Created category scoped to active workspace');
			$t->assertSame(null, $createdCat['project_id'], 'Personal created category has null project_id');

			$tagCount = (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entry_hashtags')->fetchColumn();
			$t->assertSame(4, $tagCount, 'Hashtags synced for imported descriptions (#food, #bakery, #hobby x2)');
			$uniqueHashtagsCount = (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_hashtags')->fetchColumn();
			$t->assertSame(3, $uniqueHashtagsCount, 'Repeated #Hobby tag across imported entries creates a single hashtag row');
			$t->assertTrue(($config->userValues['alice']['data_revision'] ?? '') !== '', 'Data change revision advanced after commit');
		},

		'Area bulk import blocks non-admin lookup creation and commits shared entries with projections without notifying' => function (TestRunner $t): void {
			[$bob, $db, $_bobConfig, $bobNotifier] = makeBulkImportHarness('bob');
			$needsCreation = [[
				'date' => '2026-10-18',
				'amount' => -22.50,
				'category' => 'MissingAreaCategory',
				'payment_partner' => 'MissingAreaPartner',
				'description' => 'Hardware',
			]];
			$dryBlocked = $bob->bulkImport(10, $needsCreation, true, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_FORBIDDEN, $dryBlocked->getStatus(), 'Non-admin dry-run fails with 403 when new category or partner is needed');
			$t->assertContains('MissingAreaCategory', (string)($dryBlocked->getData()['error'] ?? ''), '403 message lists missing category');
			$t->assertContains('MissingAreaPartner', (string)($dryBlocked->getData()['error'] ?? ''), '403 message lists missing partner');

			$commitBlocked = $bob->bulkImport(10, $needsCreation, false, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_FORBIDDEN, $commitBlocked->getStatus(), 'Non-admin commit fails with 403 and creates nothing');
			$t->assertSame(0, (int)$db->pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(), 'Blocked import inserts zero entries');

			// Non-admin succeeds when matching global or existing area lookups
			$memberAllowed = [[
				'date' => '2026-10-19',
				'amount' => -100.00,
				'category' => 'SharedRent',
				'payment_partner' => 'Landlord GmbH',
				'description' => 'Utilities #Household',
			]];
			$memberResp = $bob->bulkImport(10, $memberAllowed, false, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_OK, $memberResp->getStatus(), 'Non-admin succeeds using existing area lookups');
			$t->assertSame(0, $memberResp->getData()['createdCategoriesCount'], 'Zero categories created by non-admin');
			$t->assertSame([], $bobNotifier->calls, 'Area bulk import suppresses notifications');

			$source = $db->pdo->query("SELECT * FROM cobudget_entries WHERE entry_kind = 'shared' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
			$t->assertTrue($source !== false, 'Shared source entry persisted');
			$t->assertSame('bob', $source['user_id'], 'Payer is importing user');
			$t->assertSame('bob', $source['created_by'], 'Creator is importing user');
			$t->assertSame(10, (int)$source['project_id'], 'Entry assigned to area');
			$t->assertSame('project_shares', $source['split_mode'], 'Area import uses default project shares split');
			$t->assertSame(1, (int)$source['planned_notified'], 'Shared source entry suppresses delayed planned notifications');

			$projections = $db->pdo->query("SELECT * FROM cobudget_entries WHERE source_entry_id = " . (int)$source['id'] . ' ORDER BY user_id ASC')->fetchAll(\PDO::FETCH_ASSOC);
			$t->assertSame(2, count($projections), 'Locked personal projections created for both members');
			$t->assertSame('alice', $projections[0]['user_id'], 'First projection belongs to Alice');
			$t->assertSame(6000, (int)$projections[0]['amount_cents'], 'Alice receives 60% projection share');
			$t->assertSame(1, (int)$projections[0]['is_locked'], 'Alice projection is locked');
			$t->assertSame('bob', $projections[1]['user_id'], 'Second projection belongs to Bob');
			$t->assertSame(4000, (int)$projections[1]['amount_cents'], 'Bob receives 40% projection share');

			// Area admin can create new area-scoped category & payment partner
			[$alice, $dbAdmin, $_aliceConfig, $aliceNotifier] = makeBulkImportHarness('alice');
			$adminResp = $alice->bulkImport(10, [
				[
					'date' => '2026-10-20',
					'amount' => -50.00,
					'category' => 'GardenSupplies',
					'payment_partner' => 'GardenCenter',
					'description' => 'Soil #Garden',
				],
				[
					'date' => '2026-10-21',
					'amount' => -30.00,
					'category' => 'GardenSupplies',
					'payment_partner' => 'GardenCenter',
					'description' => 'Seeds #garden',
				],
			], false, hash('sha256', 'fixture'));
			$t->assertSame(Http::STATUS_OK, $adminResp->getStatus(), 'Area admin creates new area category and partner');
			$t->assertSame(2, $adminResp->getData()['importedCount'], 'Admin batch imports both shared entries');
			$t->assertSame(1, $adminResp->getData()['createdCategoriesCount'], 'Admin creates 1 area category');
			$t->assertSame(1, $adminResp->getData()['createdPaymentPartnersCount'], 'Admin creates 1 area partner');
			$t->assertSame([], $aliceNotifier->calls, 'Admin area import suppresses notifications');

			$gardenHashtags = $dbAdmin->pdo->query("SELECT id, workspace_id, display_name FROM cobudget_hashtags WHERE normalized_name = 'garden' ORDER BY workspace_id ASC")->fetchAll(\PDO::FETCH_ASSOC);
			$t->assertSame(2, count($gardenHashtags), 'Grouped shared import creates one #garden row in Alice workspace (1) and one in Bob workspace (2)');
			$gardenLinks = (int)$dbAdmin->pdo->query("SELECT COUNT(*) FROM cobudget_entry_hashtags eh INNER JOIN cobudget_hashtags h ON eh.hashtag_id = h.id WHERE h.normalized_name = 'garden'")->fetchColumn();
			$t->assertSame(6, $gardenLinks, 'Grouped shared + personal projection hashtag sync links 2 shared entries plus Alice and Bob personal projections');

			$newCat = $dbAdmin->pdo->query("SELECT * FROM cobudget_categories WHERE name = 'GardenSupplies'")->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame(10, (int)$newCat['project_id'], 'Admin-created category is scoped to area');
			$t->assertSame(1, (int)$newCat['workspace_id'], 'Admin-created category uses canonical area workspace');
			$newPartner = $dbAdmin->pdo->query("SELECT * FROM cobudget_payment_partners WHERE name = 'GardenCenter'")->fetch(\PDO::FETCH_ASSOC);
			$t->assertSame(10, (int)$newPartner['project_id'], 'Admin-created payment partner is scoped to area');
		},
	];
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Exception\ApiV1HttpException;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Stateless service implementing external API v1 lookups and payment CRUD
 * with strict per-target matrix isolation, projection/history integrity,
 * and zero session dependencies.
 */
final class ApiV1EntriesService {
	public const MAX_PAGE_SIZE = 250;
	public const MAX_PAGE_OFFSET = 1_000_000;
	public const MAX_DB_ID = 2_147_483_647;
	public const MAX_ENTRY_TIMESTAMP = 2_147_483_647;
	public const MAX_AMOUNT_CENTS = 9_999_999_999;
	public const MAX_RECURRENCE_MULTIPLIER = 1000;

	/** Canonical DB values and REST aliases mapped to `[canonical_interval, multiplier_factor]`. */
	private const RECURRENCE_INTERVAL_ALIASES = [
		'day' => ['day', 1],
		'daily' => ['day', 1],
		'week' => ['week', 1],
		'weekly' => ['week', 1],
		'month' => ['month', 1],
		'monthly' => ['month', 1],
		'year' => ['month', 12],
		'yearly' => ['month', 12],
	];
	private const ALLOWED_CREATE_KEYS = [
		'type',
		'amount',
		'amount_cents',
		'currency',
		'date',
		'description',
		'project_id',
		'category_id',
		'category',
		'payment_partner_id',
		'payment_partner',
		'user_id',
		'split_mode',
		'split_user_id',
		'important',
		'review',
		'fixed_cost',
		'children',
		'subscription',
		'tax_relevant',
		'insurance',
		'is_subscription',
		'is_fixed_cost',
		'is_child_related',
		'is_important',
		'needs_review',
		'is_tax_relevant',
		'is_insurance',
		'recurrence_interval',
		'recurrence_multiplier',
		'recurrence_next_date',
		'recurrence_end_date',
		'reminder_date',
		'reminder_text',
	];
	/** @var array<string, array{col: string, legacy: string, setting: string, expense_only: bool}> */
	private const LABEL_FIELD_MAP = [
		'important' => ['col' => 'is_important', 'legacy' => 'is_important', 'setting' => 'enable_important_payments', 'expense_only' => false],
		'review' => ['col' => 'needs_review', 'legacy' => 'needs_review', 'setting' => 'enable_review_payments', 'expense_only' => false],
		'fixed_cost' => ['col' => 'is_fixed_cost', 'legacy' => 'is_fixed_cost', 'setting' => 'enable_fixed_costs', 'expense_only' => true],
		'children' => ['col' => 'is_child_related', 'legacy' => 'is_child_related', 'setting' => 'enable_child_related', 'expense_only' => false],
		'subscription' => ['col' => 'is_subscription', 'legacy' => 'is_subscription', 'setting' => 'enable_subscriptions', 'expense_only' => true],
		'tax_relevant' => ['col' => 'is_tax_relevant', 'legacy' => 'is_tax_relevant', 'setting' => 'enable_tax_relevant', 'expense_only' => false],
		'insurance' => ['col' => 'is_insurance', 'legacy' => 'is_insurance', 'setting' => 'enable_insurance', 'expense_only' => false],
	];
	private const ENTRY_HISTORY_FIELDS = [
		'type',
		'amount_cents',
		'currency',
		'date',
		'category_id',
		'payment_partner_id',
		'description',
		'project_id',
		'user_id',
		'split_mode',
		'split_user_id',
		'recurrence_interval',
		'recurrence_multiplier',
		'recurrence_next_date',
		'recurrence_end_date',
		'is_subscription',
		'is_fixed_cost',
		'is_child_related',
		'is_important',
		'needs_review',
		'is_tax_relevant',
		'is_insurance',
		'reminder_date',
		'reminder_text',
	];
	private const ENTRY_HISTORY_BOOLEAN_FIELDS = [
		'is_subscription',
		'is_fixed_cost',
		'is_child_related',
		'is_important',
		'needs_review',
		'is_tax_relevant',
		'is_insurance',
	];
	private const ENTRY_HISTORY_INTEGER_FIELDS = [
		'amount_cents',
		'date',
		'category_id',
		'payment_partner_id',
		'project_id',
		'recurrence_multiplier',
		'recurrence_next_date',
		'recurrence_end_date',
		'reminder_date',
	];
	private const CURRENCY_BY_COUNTRY = [
		'AT' => 'EUR', 'BE' => 'EUR', 'CH' => 'CHF', 'DE' => 'EUR', 'DK' => 'DKK',
		'ES' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'GB' => 'GBP', 'IE' => 'EUR',
		'IT' => 'EUR', 'LI' => 'CHF', 'LU' => 'EUR', 'NL' => 'EUR', 'NO' => 'NOK',
		'PL' => 'PLN', 'PT' => 'EUR', 'SE' => 'SEK', 'US' => 'USD',
	];
	private const CURRENCY_BY_LANGUAGE = [
		'de' => 'EUR', 'es' => 'EUR', 'fr' => 'EUR', 'it' => 'EUR', 'nl' => 'EUR', 'pt' => 'EUR',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private HashtagService $hashtagService,
		private EntryShareService $entryShareService,
		private EntryProjectionService $entryProjectionService,
		private ParticipantService $participantService,
		private DataChangeService $dataChangeService,
		private ProjectNotificationService $projectNotificationService,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/** @return array<string, mixed> */
	public function me(array $auth): array {
		$userId = (string)$auth['user_id'];
		$workspaceId = (int)$auth['workspace_id'];
		$user = $auth['user'];

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'is_default')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$workspaceRow = $result->fetch() ?: ['id' => $workspaceId, 'name' => '', 'is_default' => true];
		$result->closeCursor();

		$metadata = [
			'token' => [
				'id' => (int)$auth['token_id'],
				'name' => (string)$auth['token_name'],
				'token_prefix' => (string)$auth['token_prefix'],
				'workspace_id' => $workspaceId,
				'scopes' => $auth['scopes'],
				'target_permissions' => (object)$auth['target_permissions'],
				'can_use_reports' => (bool)$auth['can_use_reports'],
				'expires_at' => $auth['expires_at'],
				'created_at' => (int)$auth['created_at'],
			],
			'user' => [
				'id' => $userId,
				'display_name' => $user !== null && trim((string)$user->getDisplayName()) !== ''
					? (string)$user->getDisplayName()
					: $userId,
			],
			'workspace' => [
				'id' => (int)$workspaceRow['id'],
				'name' => (string)($workspaceRow['name'] ?? ''),
				'is_default' => $this->dbBool($workspaceRow['is_default'] ?? false),
			],
			'areas' => $this->listAreas($auth),
		];

		if (in_array('settings:read', $auth['scopes'], true)) {
			$metadata['currency'] = $this->resolveAccountCurrency($userId);
			$metadata['features'] = [
				'projects' => (bool)$auth['projects_enabled'],
				'shared_projects' => $this->config->getUserValue($userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes',
				'incomes' => $this->config->getUserValue($userId, 'cobudget', 'enable_incomes', 'yes') === 'yes',
				'future_payments' => $this->config->getUserValue($userId, 'cobudget', 'enable_future_payments', 'yes') === 'yes',
				'reminders' => $this->config->getUserValue($userId, 'cobudget', 'enable_reminders', 'yes') === 'yes',
				'budget_goals' => $this->config->getUserValue($userId, 'cobudget', 'enable_budget_goals', 'yes') === 'yes',
				'workspaces' => $this->config->getUserValue($userId, 'cobudget', 'enable_workspaces', 'no') === 'yes',
			];
		}

		return $metadata;
	}

	/** @return list<array<string, mixed>> */
	public function listAreas(array $auth): array {
		$items = [];
		foreach ($auth['active_areas_by_id'] as $areaId => $area) {
			$actions = $auth['target_permissions'][(string)$areaId] ?? [];
			if ($actions === []) {
				continue;
			}
			$item = [
				'id' => (int)$area['id'],
				'name' => (string)$area['name'],
				'color' => (string)$area['color'],
				'is_shared' => (bool)$area['is_shared'],
				'has_former_member' => (bool)$area['has_former_member'],
				'permissions' => $actions,
			];
			if ($area['is_shared'] && (in_array('create', $actions, true) || in_array('write', $actions, true))) {
				$item['members'] = $this->loadActiveMembersForArea((int)$area['id']);
			}
			$items[] = $item;
		}
		return $items;
	}

	/** @return list<array<string, mixed>> */
	public function listCategories(array $auth, ?int $projectId): array {
		$scope = $this->resolveLookupScope($auth, $projectId);
		$hiddenLookup = array_fill_keys($this->hiddenIdsForLookup($auth, $projectId, 'categories'), true);

		$qb = $this->db->getQueryBuilder();
		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($scope['lookup_workspace_id'], \PDO::PARAM_INT)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter((string)$auth['user_id'])),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($scope['lookup_workspace_id'], \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);

		$qb->select('id', 'name', 'icon', 'type', 'parent_category_id', 'code', 'project_id', 'is_global')
			->from('cobudget_categories')
			->where($qb->expr()->orX($globalScope, $localScope))
			->orderBy('name', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$items = [];
		foreach ($rows as $row) {
			$id = (int)$row['id'];
			if (isset($hiddenLookup[$id])) {
				continue;
			}
			$items[] = [
				'id' => $id,
				'name' => (string)$row['name'],
				'icon' => (string)($row['icon'] ?: 'Shape'),
				'type' => (string)$row['type'],
				'parent_category_id' => $row['parent_category_id'] !== null ? (int)$row['parent_category_id'] : null,
				'code' => $row['code'] !== null ? (string)$row['code'] : null,
				'project_id' => $row['project_id'] !== null ? (int)$row['project_id'] : null,
				'is_global' => $this->dbBool($row['is_global'] ?? false),
			];
		}

		return $items;
	}

	/** @return list<array<string, mixed>> */
	public function listPaymentPartners(array $auth, ?int $projectId): array {
		$scope = $this->resolveLookupScope($auth, $projectId);
		$hiddenLookup = array_fill_keys($this->hiddenIdsForLookup($auth, $projectId, 'payment_partners'), true);

		$qb = $this->db->getQueryBuilder();
		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($scope['lookup_workspace_id'], \PDO::PARAM_INT)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter((string)$auth['user_id'])),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($scope['lookup_workspace_id'], \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);

		$qb->select('id', 'name', 'type', 'project_id', 'is_global')
			->from('cobudget_payment_partners')
			->where($qb->expr()->orX($globalScope, $localScope))
			->orderBy('name', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$items = [];
		foreach ($rows as $row) {
			$id = (int)$row['id'];
			if (isset($hiddenLookup[$id])) {
				continue;
			}
			$items[] = [
				'id' => $id,
				'name' => (string)$row['name'],
				'type' => (string)$row['type'],
				'project_id' => $row['project_id'] !== null ? (int)$row['project_id'] : null,
				'is_global' => $this->dbBool($row['is_global'] ?? false),
			];
		}

		return $items;
	}

	/**
	 * @param array{
	 *     project_id: ?string,
	 *     scope: string,
	 *     type: string,
	 *     date_from: ?int,
	 *     date_to: ?int,
	 *     category_id: ?int,
	 *     payment_partner_id: ?int,
	 *     search: string,
	 *     include_future: bool,
	 *     limit: int,
	 *     offset: int,
	 *     sort_by: string,
	 *     sort_dir: string
	 * } $filters
	 * @return array{entries: list<array<string, mixed>>, total: int, limit: int, offset: int}
	 */
	public function listEntries(array $auth, array $filters): array {
		$scope = $filters['scope'] ?? 'canonical';
		if (!in_array($scope, ['canonical', 'personal'], true)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid scope parameter. Expected "canonical" or "personal".');
		}

		$type = $filters['type'] ?? 'all';
		if (!in_array($type, ['all', 'expense', 'income'], true)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid type parameter.');
		}

		$sortBy = $filters['sort_by'] ?? 'date';
		if (!in_array($sortBy, ['date', 'amount', 'id'], true)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid sort_by parameter.');
		}

		$sortDir = strtolower((string)($filters['sort_dir'] ?? 'desc'));
		if (!in_array($sortDir, ['asc', 'desc'], true)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid sort_dir parameter.');
		}

		$limit = max(1, min(self::MAX_PAGE_SIZE, (int)($filters['limit'] ?? 50)));
		$offset = max(0, min(self::MAX_PAGE_OFFSET, (int)($filters['offset'] ?? 0)));

		$requestedPersonal = false;
		$requestedAreaId = null;
		$rawProjectFilter = $filters['project_id'] ?? null;
		if ($rawProjectFilter !== null && trim($rawProjectFilter) !== '') {
			$trimmed = trim($rawProjectFilter);
			if (!ctype_digit($trimmed)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid project_id filter.');
			}
			$aid = filter_var($trimmed, FILTER_VALIDATE_INT, [
				'options' => ['min_range' => 0, 'max_range' => self::MAX_DB_ID],
			]);
			if ($aid === false) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid project_id filter.');
			}
			if ($aid === 0) {
				if (!$this->hasTargetAction($auth, 'personal', 'read')) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key does not have read permission for personal payments.');
				}
				$requestedPersonal = true;
			} else {
				if (!isset($auth['active_areas_by_id'][$aid]) || !$this->hasTargetAction($auth, (string)$aid, 'read')) {
					throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Area not found.');
				}
				$requestedAreaId = $aid;
			}
		}

		$includePersonal = $requestedAreaId === null && $this->hasTargetAction($auth, 'personal', 'read');
		$readAreaIds = [];
		if (!$requestedPersonal) {
			foreach ($auth['active_areas_by_id'] as $aid => $area) {
				if ($requestedAreaId !== null && (int)$aid !== $requestedAreaId) {
					continue;
				}
				if ($this->hasTargetAction($auth, (string)$aid, 'read')) {
					$readAreaIds[] = (int)$aid;
				}
			}
		}

		if (!$includePersonal && $readAreaIds === []) {
			return [
				'entries' => [],
				'total' => 0,
				'limit' => $limit,
				'offset' => $offset,
			];
		}

		$countQb = $this->db->getQueryBuilder();
		$countQb->select($countQb->createFunction('COUNT(*) AS total'))
			->from('cobudget_entries', 'e');
		$this->applyEntryReadScope($countQb, $auth, $scope, $includePersonal, $readAreaIds);
		$this->applyEntryListFilters($countQb, $filters, $type);
		$countResult = $countQb->executeQuery();
		$total = (int)($countResult->fetchOne() ?: 0);
		$countResult->closeCursor();

		if ($total === 0) {
			return [
				'entries' => [],
				'total' => 0,
				'limit' => $limit,
				'offset' => $offset,
			];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.*',
			'c.name AS category_name',
			'c.icon AS category_icon',
			'p.name AS payment_partner_name',
			'pr.name AS project_name',
			'pr.owner_id AS project_owner_id'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->leftJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'));
		$this->applyEntryReadScope($qb, $auth, $scope, $includePersonal, $readAreaIds);
		$this->applyEntryListFilters($qb, $filters, $type);

		$orderColumn = match ($sortBy) {
			'amount' => 'e.amount_cents',
			'id' => 'e.id',
			default => 'e.date',
		};
		$orderDir = strtoupper($sortDir);
		$qb->orderBy($orderColumn, $orderDir);
		if ($orderColumn !== 'e.id') {
			$qb->addOrderBy('e.id', $orderDir);
		}
		$qb->setMaxResults($limit)->setFirstResult($offset);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return [
			'entries' => $this->formatEntriesForApi($auth, $rows),
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	/** @return array<string, mixed> */
	public function getEntry(array $auth, int $entryId): array {
		$row = $this->loadAuthorizedVisibleEntry($auth, $entryId, 'read');
		$formatted = $this->formatEntriesForApi($auth, [$row]);
		return $formatted[0];
	}

	/** @return array<string, mixed> */
	public function createEntry(array $auth, array $payload): array {
		$this->assertAllowedPayloadKeys($payload, self::ALLOWED_CREATE_KEYS);
		$userId = (string)$auth['user_id'];
		$userWorkspaceId = (int)$auth['workspace_id'];
		/** @var \DateTimeZone $timezone */
		$timezone = $auth['timezone'];
		/** @var IL10N $l10n */
		$l10n = $auth['l10n'];

		[$type, $amountCents] = $this->resolveAmountAndType($payload, $userId, null, true);

		$now = time();
		if (array_key_exists('date', $payload)) {
			$parsedDate = $this->parseDateOrTimestamp($payload['date'], 'date', $timezone, $now);
			$date = $parsedDate['timestamp'];
			$isFutureDate = $parsedDate['is_future'];
		} else {
			$date = $now;
			$isFutureDate = false;
		}
		if ($isFutureDate && $this->config->getUserValue($userId, 'cobudget', 'enable_future_payments', 'yes') !== 'yes') {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Future payments are disabled in CoBudget settings.');
		}

		if (array_key_exists('currency', $payload)) {
			$currency = $this->validateIsoCurrency($payload['currency']);
		} else {
			$currency = $this->resolveAccountCurrency($userId);
		}

		$description = '';
		if (array_key_exists('description', $payload) && $payload['description'] !== null) {
			$description = $this->validateCleanUtf8Text($payload['description'], 'description', 512, true);
		}

		$projectId = $this->parseOptionalProjectIdField($payload);
		$target = $this->authorizeMutationTarget($auth, $projectId, 'create');
		if ($target['entry_kind'] === 'shared' && $target['has_former_member']) {
			throw new ApiV1HttpException(
				Http::STATUS_CONFLICT,
				'This area contains a former member. Settle existing payments and remove the former member before creating new payments.'
			);
		}

		$categoryId = $this->resolveCategoryIdFromPayload($auth, $payload, $projectId, $target['lookup_workspace_id'], $type, null, false);
		$paymentPartnerId = $this->resolvePaymentPartnerIdFromPayload($auth, $payload, $projectId, $target['lookup_workspace_id'], $type, null, false);

		[$entryUserId, $splitMode, $splitUserId] = $this->validatePayerAndSplitForCreate(
			$auth,
			$target['entry_kind'],
			$projectId,
			$payload
		);

		$flags = $this->resolveLabelFlags($payload, $userId, $type, [
			'is_subscription' => false,
			'is_fixed_cost' => false,
			'is_child_related' => false,
			'is_important' => false,
			'needs_review' => false,
			'is_tax_relevant' => false,
			'is_insurance' => false,
		]);

		$recurrence = $this->validateRecurrenceTuple([
			'recurrence_interval' => array_key_exists('recurrence_interval', $payload) ? $payload['recurrence_interval'] : null,
			'recurrence_multiplier' => array_key_exists('recurrence_multiplier', $payload) ? $payload['recurrence_multiplier'] : null,
			'recurrence_next_date' => array_key_exists('recurrence_next_date', $payload) ? $payload['recurrence_next_date'] : null,
			'recurrence_end_date' => array_key_exists('recurrence_end_date', $payload) ? $payload['recurrence_end_date'] : null,
		], $timezone, $now);

		$remindersEnabled = $this->config->getUserValue($userId, 'cobudget', 'enable_reminders', 'yes') === 'yes';
		$reminderDate = null;
		$reminderText = null;
		if (array_key_exists('reminder_date', $payload) && $payload['reminder_date'] !== null) {
			if (!$remindersEnabled) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Reminders are disabled in CoBudget settings.');
			}
			$reminderDate = $this->parseDateOrTimestamp($payload['reminder_date'], 'reminder_date', $timezone, $now)['timestamp'];
		}
		if (array_key_exists('reminder_text', $payload) && $payload['reminder_text'] !== null) {
			if (!$remindersEnabled) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Reminders are disabled in CoBudget settings.');
			}
			$reminderText = $this->validateCleanUtf8Text($payload['reminder_text'], 'reminder_text', 255, false);
		}

		$insertWorkspaceId = $target['entry_kind'] === 'shared'
			? $target['area_workspace_id']
			: $userWorkspaceId;
		$amountStr = $this->centsToAmountString($amountCents);

		$this->db->beginTransaction();
		try {
			$this->lockMutationArea($auth, $projectId);
			if ($target['entry_kind'] === 'shared') {
				$this->validatePayerAndSplitForCreate($auth, 'shared', $projectId, $payload);
			}
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entries')
				->values([
					'user_id' => $qb->createNamedParameter($entryUserId),
					'created_by' => $qb->createNamedParameter($userId),
					'project_id' => $qb->createNamedParameter($projectId, $projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'entry_kind' => $qb->createNamedParameter($target['entry_kind']),
					'source_entry_id' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'is_locked' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'allocation_basis_points' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'type' => $qb->createNamedParameter($type),
					'amount' => $qb->createNamedParameter($amountStr),
					'amount_cents' => $qb->createNamedParameter($amountCents, \PDO::PARAM_INT),
					'currency' => $qb->createNamedParameter($currency),
					'date' => $qb->createNamedParameter($date, \PDO::PARAM_INT),
					'category_id' => $qb->createNamedParameter($categoryId, $categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'description' => $qb->createNamedParameter($description),
					'payment_partner_id' => $qb->createNamedParameter($paymentPartnerId, $paymentPartnerId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'split_mode' => $qb->createNamedParameter($splitMode),
					'split_user_id' => $qb->createNamedParameter($splitUserId, $splitUserId === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'is_settled' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'recurrence_interval' => $qb->createNamedParameter($recurrence['interval'], $recurrence['interval'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'recurrence_multiplier' => $qb->createNamedParameter($recurrence['multiplier'], $recurrence['multiplier'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'recurrence_next_date' => $qb->createNamedParameter($recurrence['next_date'], $recurrence['next_date'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'recurrence_end_date' => $qb->createNamedParameter($recurrence['end_date'], $recurrence['end_date'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'is_subscription' => $qb->createNamedParameter($flags['is_subscription'], \PDO::PARAM_BOOL),
					'is_fixed_cost' => $qb->createNamedParameter($flags['is_fixed_cost'], \PDO::PARAM_BOOL),
					'is_child_related' => $qb->createNamedParameter($flags['is_child_related'], \PDO::PARAM_BOOL),
					'is_important' => $qb->createNamedParameter($flags['is_important'], \PDO::PARAM_BOOL),
					'needs_review' => $qb->createNamedParameter($flags['needs_review'], \PDO::PARAM_BOOL),
					'is_tax_relevant' => $qb->createNamedParameter($flags['is_tax_relevant'], \PDO::PARAM_BOOL),
					'is_insurance' => $qb->createNamedParameter($flags['is_insurance'], \PDO::PARAM_BOOL),
					'reminder_date' => $qb->createNamedParameter($reminderDate, $reminderDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'planned_notified' => $qb->createNamedParameter($date <= $now, \PDO::PARAM_BOOL),
					'reminder_notified' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'reminder_text' => $qb->createNamedParameter($reminderText, $reminderText === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'recurrence_parent_id' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'recurrence_series_id' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'workspace_id' => $qb->createNamedParameter($insertWorkspaceId, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$entryId = (int)$this->db->lastInsertId('*PREFIX*cobudget_entries');
			if ($recurrence['interval'] !== null) {
				$this->setEntryRecurrenceSeriesId($entryId, $entryId, $insertWorkspaceId);
			}
			$this->hashtagService->syncEntryHashtags($entryId, $insertWorkspaceId, $description);
			if ($target['entry_kind'] === 'shared') {
				$this->entryProjectionService->syncSharedEntry($entryId);
			}

			$response = $this->mutationResponse($auth, $entryId, $projectId);
			$this->db->commit();
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		if ($target['entry_kind'] === 'shared') {
			try {
				$this->projectNotificationService->notifyEntryCreated(
					(int)$projectId,
					$insertWorkspaceId,
					$entryId,
					$userId,
					$entryUserId,
					$type,
					$amountCents,
					$currency,
					$description
				);
			} catch (\Throwable $notifyError) {
				$this->logger->warning('Failed to notify shared area after API v1 payment create: ' . $notifyError->getMessage(), [
					'app' => 'cobudget',
					'entryId' => $entryId,
				]);
			}
		}

		$this->dataChangeService->publishForEntry([
			'entry_kind' => $target['entry_kind'],
			'project_id' => $projectId,
			'user_id' => $entryUserId,
		], ['entries', 'projects', 'analytics', 'budgets']);

		$this->auditLog($auth, 'entry.create', $entryId);
		return $response;
	}

	/** @return array<string, mixed> */
	public function updateEntry(array $auth, int $entryId, array $payload): array {
		$entry = $this->loadAuthorizedVisibleEntry($auth, $entryId, 'write');
		$this->assertAllowedPayloadKeys($payload, self::ALLOWED_CREATE_KEYS);

		if ($this->dbBool($entry['is_settled'] ?? false)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Settled payments cannot be edited.');
		}

		$userId = (string)$auth['user_id'];
		$existingProjectId = $entry['project_id'] !== null && $entry['project_id'] !== '' ? (int)$entry['project_id'] : null;
		if (array_key_exists('project_id', $payload)) {
			$requestedProjectId = $this->parseOptionalProjectIdField($payload);
			if ($requestedProjectId !== $existingProjectId) {
				throw new ApiV1HttpException(Http::STATUS_CONFLICT, 'The area of an existing payment cannot be changed.');
			}
		}

		$existingKind = (string)($entry['entry_kind'] ?? ($existingProjectId === null ? 'personal' : 'shared'));
		$isReleasedPersonal = $existingKind === 'personal'
			&& !$this->dbBool($entry['is_locked'] ?? false)
			&& (!empty($entry['settlement_id']) || !empty($entry['settled_at']));
		$target = $this->authorizeMutationTarget($auth, $existingProjectId, 'write');
		if ($isReleasedPersonal) {
			$target['entry_kind'] = 'personal';
		}

		/** @var \DateTimeZone $timezone */
		$timezone = $auth['timezone'];
		$existingType = (string)($entry['type'] ?? 'expense');
		$existingCents = (int)($entry['amount_cents'] ?? 0);
		if ($existingCents <= 0) {
			$existingCents = $this->parseStrictDecimalCents((string)($entry['amount'] ?? '0'));
		}
		[$type, $amountCents] = $this->resolveAmountAndType($payload, $userId, $existingType, false, $existingCents);

		$now = time();
		$existingDate = (int)$entry['date'];
		$date = $existingDate;
		if (array_key_exists('date', $payload)) {
			$parsedDate = $this->parseDateOrTimestamp($payload['date'], 'date', $timezone, $now);
			$date = $parsedDate['timestamp'];
			if ($parsedDate['is_future'] && $date !== $existingDate
				&& $this->config->getUserValue($userId, 'cobudget', 'enable_future_payments', 'yes') !== 'yes') {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Future payments are disabled in CoBudget settings.');
			}
		}

		$currency = (string)($entry['currency'] ?? 'EUR');
		if (array_key_exists('currency', $payload)) {
			$currency = $this->validateIsoCurrency($payload['currency']);
		}

		$description = (string)($entry['description'] ?? '');
		if (array_key_exists('description', $payload)) {
			if ($payload['description'] === null) {
				$description = '';
			} else {
				$description = $this->validateCleanUtf8Text($payload['description'], 'description', 512, true);
			}
		}

		$existingCategoryId = $entry['category_id'] !== null && $entry['category_id'] !== '' ? (int)$entry['category_id'] : null;
		$categoryId = $this->resolveCategoryIdFromPayload($auth, $payload, $existingProjectId, $target['lookup_workspace_id'], $type, $existingCategoryId, true);

		$existingPartnerId = $entry['payment_partner_id'] !== null && $entry['payment_partner_id'] !== '' ? (int)$entry['payment_partner_id'] : null;
		$paymentPartnerId = $this->resolvePaymentPartnerIdFromPayload($auth, $payload, $existingProjectId, $target['lookup_workspace_id'], $type, $existingPartnerId, true);

		[$entryUserId, $splitMode, $splitUserId] = $this->validatePayerAndSplitForUpdate(
			$auth,
			$existingKind,
			$isReleasedPersonal,
			$existingProjectId,
			$entry,
			$payload
		);

		$flags = $this->resolveLabelFlags($payload, $userId, $type, [
			'is_subscription' => $this->dbBool($entry['is_subscription'] ?? false),
			'is_fixed_cost' => $this->dbBool($entry['is_fixed_cost'] ?? false),
			'is_child_related' => $this->dbBool($entry['is_child_related'] ?? false),
			'is_important' => $this->dbBool($entry['is_important'] ?? false),
			'needs_review' => $this->dbBool($entry['needs_review'] ?? false),
			'is_tax_relevant' => $this->dbBool($entry['is_tax_relevant'] ?? false),
			'is_insurance' => $this->dbBool($entry['is_insurance'] ?? false),
		]);

		$clearingRecurrenceInterval = array_key_exists('recurrence_interval', $payload) && $payload['recurrence_interval'] === null;
		$rawRequestedInterval = array_key_exists('recurrence_interval', $payload)
			? $payload['recurrence_interval']
			: ($entry['recurrence_interval'] ?? null);
		$switchingToScaledAlias = array_key_exists('recurrence_interval', $payload)
			&& is_string($rawRequestedInterval)
			&& in_array($rawRequestedInterval, ['year', 'yearly'], true);
		$recurrenceInput = [
			'recurrence_interval' => $rawRequestedInterval,
			'recurrence_multiplier' => array_key_exists('recurrence_multiplier', $payload)
				? $payload['recurrence_multiplier']
				: ($clearingRecurrenceInterval ? null : ($switchingToScaledAlias ? 1 : ($entry['recurrence_multiplier'] !== null ? (int)$entry['recurrence_multiplier'] : null))),
			'recurrence_next_date' => array_key_exists('recurrence_next_date', $payload)
				? $payload['recurrence_next_date']
				: ($clearingRecurrenceInterval ? null : ($entry['recurrence_next_date'] !== null ? (int)$entry['recurrence_next_date'] : null)),
			'recurrence_end_date' => array_key_exists('recurrence_end_date', $payload)
				? $payload['recurrence_end_date']
				: ($clearingRecurrenceInterval ? null : ($entry['recurrence_end_date'] !== null ? (int)$entry['recurrence_end_date'] : null)),
		];
		$recurrence = $this->validateRecurrenceTuple($recurrenceInput, $timezone, $now);
		if (!$this->hasTargetAction($auth, (string)($existingProjectId ?? 'personal'), 'create')
			&& $this->expandsRecurrence($entry, $recurrence)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Starting or extending recurring payments requires create permission for this target.');
		}

		$remindersEnabled = $this->config->getUserValue($userId, 'cobudget', 'enable_reminders', 'yes') === 'yes';
		$reminderDate = $entry['reminder_date'] !== null ? (int)$entry['reminder_date'] : null;
		$reminderText = $entry['reminder_text'] !== null ? (string)$entry['reminder_text'] : null;
		$reminderNotified = $this->dbBool($entry['reminder_notified'] ?? false);

		if (array_key_exists('reminder_date', $payload) || array_key_exists('reminder_text', $payload)) {
			if (!$remindersEnabled) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Reminders are disabled in CoBudget settings.');
			}
			if (array_key_exists('reminder_date', $payload)) {
				if ($payload['reminder_date'] === null) {
					$reminderDate = null;
				} else {
					$parsedReminder = $this->parseDateOrTimestamp($payload['reminder_date'], 'reminder_date', $timezone, $now)['timestamp'];
					if ($parsedReminder !== $reminderDate) {
						$reminderNotified = false;
					}
					$reminderDate = $parsedReminder;
				}
			}
			if (array_key_exists('reminder_text', $payload)) {
				if ($payload['reminder_text'] === null) {
					$reminderText = null;
				} else {
					$reminderText = $this->validateCleanUtf8Text($payload['reminder_text'], 'reminder_text', 255, false);
				}
			}
		}

		$currentWorkspaceId = (int)$entry['workspace_id'];
		$amountStr = $this->centsToAmountString($amountCents);
		$plannedNotified = ($date > $now && $date !== $existingDate && empty($entry['planned_delivery']))
			? false
			: $this->dbBool($entry['planned_notified'] ?? true);

		$updatedEntry = $entry;
		$updatedEntry['user_id'] = $entryUserId;
		$updatedEntry['type'] = $type;
		$updatedEntry['amount'] = $amountStr;
		$updatedEntry['amount_cents'] = $amountCents;
		$updatedEntry['currency'] = $currency;
		$updatedEntry['date'] = $date;
		$updatedEntry['category_id'] = $categoryId;
		$updatedEntry['description'] = $description;
		$updatedEntry['payment_partner_id'] = $paymentPartnerId;
		$updatedEntry['split_mode'] = $splitMode;
		$updatedEntry['split_user_id'] = $splitUserId;
		$updatedEntry['recurrence_interval'] = $recurrence['interval'];
		$updatedEntry['recurrence_multiplier'] = $recurrence['multiplier'];
		$updatedEntry['recurrence_next_date'] = $recurrence['next_date'];
		$updatedEntry['recurrence_end_date'] = $recurrence['end_date'];
		$updatedEntry['is_subscription'] = $flags['is_subscription'];
		$updatedEntry['is_fixed_cost'] = $flags['is_fixed_cost'];
		$updatedEntry['is_child_related'] = $flags['is_child_related'];
		$updatedEntry['is_important'] = $flags['is_important'];
		$updatedEntry['needs_review'] = $flags['needs_review'];
		$updatedEntry['is_tax_relevant'] = $flags['is_tax_relevant'];
		$updatedEntry['is_insurance'] = $flags['is_insurance'];
		$updatedEntry['reminder_date'] = $reminderDate;
		$updatedEntry['reminder_notified'] = $reminderNotified;
		$updatedEntry['reminder_text'] = $reminderText;

		/** @var IL10N $l10n */
		$l10n = $auth['l10n'];
		$this->db->beginTransaction();
		try {
			$this->lockMutationArea($auth, $existingProjectId);
			$this->lockUnchangedPayment($auth, $entry, 'write');
			if ($existingKind === 'shared') {
				$this->validatePayerAndSplitForUpdate($auth, 'shared', false, $existingProjectId, $entry, $payload);
			}
			$projectionsBefore = $existingKind === 'shared'
				? $this->entryProjectionService->personalEntriesForSource($entryId)
				: [];
			$historyContext = $this->newHistoryContext($userId);

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entries')
				->set('user_id', $qb->createNamedParameter($entryUserId))
				->set('type', $qb->createNamedParameter($type))
				->set('amount', $qb->createNamedParameter($amountStr))
				->set('amount_cents', $qb->createNamedParameter($amountCents, \PDO::PARAM_INT))
				->set('currency', $qb->createNamedParameter($currency))
				->set('date', $qb->createNamedParameter($date, \PDO::PARAM_INT))
				->set('category_id', $qb->createNamedParameter($categoryId, $categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('description', $qb->createNamedParameter($description))
				->set('payment_partner_id', $qb->createNamedParameter($paymentPartnerId, $paymentPartnerId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('split_mode', $qb->createNamedParameter($splitMode))
				->set('split_user_id', $qb->createNamedParameter($splitUserId, $splitUserId === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR))
				->set('recurrence_interval', $qb->createNamedParameter($recurrence['interval'], $recurrence['interval'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR))
				->set('recurrence_multiplier', $qb->createNamedParameter($recurrence['multiplier'], $recurrence['multiplier'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('recurrence_next_date', $qb->createNamedParameter($recurrence['next_date'], $recurrence['next_date'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('recurrence_end_date', $qb->createNamedParameter($recurrence['end_date'], $recurrence['end_date'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('is_subscription', $qb->createNamedParameter($flags['is_subscription'], \PDO::PARAM_BOOL))
				->set('is_fixed_cost', $qb->createNamedParameter($flags['is_fixed_cost'], \PDO::PARAM_BOOL))
				->set('is_child_related', $qb->createNamedParameter($flags['is_child_related'], \PDO::PARAM_BOOL))
				->set('is_important', $qb->createNamedParameter($flags['is_important'], \PDO::PARAM_BOOL))
				->set('needs_review', $qb->createNamedParameter($flags['needs_review'], \PDO::PARAM_BOOL))
				->set('is_tax_relevant', $qb->createNamedParameter($flags['is_tax_relevant'], \PDO::PARAM_BOOL))
				->set('is_insurance', $qb->createNamedParameter($flags['is_insurance'], \PDO::PARAM_BOOL))
				->set('planned_notified', $qb->createNamedParameter($plannedNotified, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($currentWorkspaceId, \PDO::PARAM_INT)));
			if ($remindersEnabled) {
				$qb
					->set('reminder_date', $qb->createNamedParameter($reminderDate, $reminderDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
					->set('reminder_notified', $qb->createNamedParameter($reminderNotified, \PDO::PARAM_BOOL))
					->set('reminder_text', $qb->createNamedParameter($reminderText, $reminderText === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR));
			}
			$qb->executeStatement();

			$this->recordEntryHistory($entryId, $currentWorkspaceId, $existingProjectId, $entry, $updatedEntry, $historyContext, $l10n);
			$this->hashtagService->syncEntryHashtags($entryId, $currentWorkspaceId, $description);

			if ($recurrence['interval'] !== null) {
				$seriesId = empty($entry['recurrence_series_id']) ? $entryId : (int)$entry['recurrence_series_id'];
				$this->setEntryRecurrenceSeriesId($entryId, $seriesId, $currentWorkspaceId);
			}

			if ($existingKind === 'shared') {
				$this->entryProjectionService->syncSharedEntry($entryId);
				$projectionsAfter = $this->entryProjectionService->personalEntriesForSource($entryId);
				$this->recordPersonalProjectionHistories($projectionsBefore, $projectionsAfter, $historyContext, $l10n);
			}

			$response = $this->mutationResponse($auth, $entryId, $existingProjectId);
			$this->db->commit();
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		$this->dataChangeService->publishForEntryChange($entry, $updatedEntry, ['entries', 'projects', 'analytics', 'budgets']);
		$this->auditLog($auth, 'entry.update', $entryId);

		return $response;
	}

	/** @return array{status: string} */
	public function deleteEntry(array $auth, int $entryId): array {
		$entry = $this->loadAuthorizedVisibleEntry($auth, $entryId, 'delete');
		if ($this->dbBool($entry['is_settled'] ?? false)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Settled payments cannot be deleted.');
		}

		$userId = (string)$auth['user_id'];
		$entryKind = (string)($entry['entry_kind'] ?? 'personal');
		$projectId = $entry['project_id'] !== null && $entry['project_id'] !== '' ? (int)$entry['project_id'] : null;

		if ($entryKind === 'shared') {
			$area = $projectId !== null ? ($auth['active_areas_by_id'][$projectId] ?? null) : null;
			$creatorId = trim((string)($entry['created_by'] ?? ''));
			if ($creatorId === '') {
				$creatorId = trim((string)($entry['user_id'] ?? ''));
			}
			$ownerId = $area !== null ? trim((string)$area['owner_id']) : '';
			if ($creatorId !== $userId && $ownerId !== $userId) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Only the payment creator or area admin can delete this shared payment.');
			}
		} elseif ((string)($entry['user_id'] ?? '') !== $userId) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
		}

		$attachments = [];
		$this->db->beginTransaction();
		try {
			$this->lockMutationArea($auth, $projectId);
			$this->lockUnchangedPayment($auth, $entry, 'delete');
			$entryIds = $this->entryProjectionService->prepareEntryDeletion([$entryId]);
			$attachments = $this->fetchEntryAttachmentsForEntries($entryIds);
			$this->deleteEntryAttachmentRowsForEntries($entryIds);
			$this->hashtagService->deleteHashtagsForEntries($entryIds);
			$this->deleteEntryHistoryForEntries($entryIds);
			$this->entryShareService->deleteForEntries($entryIds);
			$deleted = $this->deleteEntryRowsByIds($entryIds);
			if ($deleted !== count($entryIds)) {
				throw new \RuntimeException('Payment could not be deleted.');
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		$this->deleteEntryAttachmentFilesAfterCommit($attachments);
		$this->dataChangeService->publishForEntry($entry, ['entries', 'projects', 'analytics', 'budgets']);
		$this->auditLog($auth, 'entry.delete', $entryId);

		return ['status' => 'success'];
	}

	private function lockMutationArea(array $auth, ?int $projectId): void {
		if ($projectId === null) {
			return;
		}
		$this->entryShareService->lockProject($projectId);
		$area = $auth['active_areas_by_id'][$projectId];
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.owner_id', 'p.workspace_id', 'p.is_archived', 'm.user_id', 'm.personal_workspace_id')
			->from('cobudget_projects', 'p')
			->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
			->where($qb->expr()->eq('p.id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$members = $result->fetchAll();
		$result->closeCursor();

		$memberWorkspaces = array_column($members, 'personal_workspace_id', 'user_id');
		$current = $members[0] ?? null;
		if ($current === null || $this->dbBool($current['is_archived'])
			|| (int)($memberWorkspaces[$auth['user_id']] ?? 0) !== (int)$auth['workspace_id']) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Area not found.');
		}
		$hasFormerMember = false;
		foreach ($members as $member) {
			if (ParticipantService::isReservedFormerId((string)$member['user_id'])) {
				$hasFormerMember = true;
				break;
			}
		}
		if ($current['owner_id'] !== $area['owner_id'] || (int)$current['workspace_id'] !== (int)$area['workspace_id']
			|| count($members) !== (int)$area['member_count'] || $hasFormerMember !== $area['has_former_member']) {
			throw new ApiV1HttpException(Http::STATUS_CONFLICT, 'Area membership changed. Retry the request.');
		}
	}

	private function lockUnchangedPayment(array $auth, array $entry, string $action): void {
		$id = (int)$entry['id'];
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('id', $qb->createFunction('id'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter((int)$entry['workspace_id'], \PDO::PARAM_INT)));
		$qb->executeStatement();
		$current = $this->loadAuthorizedVisibleEntry($auth, $id, $action);
		if ($current !== $entry) {
			throw new ApiV1HttpException(Http::STATUS_CONFLICT, 'Payment changed. Retry the request.');
		}
	}

	/** @return array<string, mixed> */
	private function mutationResponse(array $auth, int $entryId, ?int $projectId): array {
		if (!$this->hasTargetAction($auth, (string)($projectId ?? 'personal'), 'read')) {
			return ['id' => $entryId];
		}

		return $this->getEntry($auth, $entryId);
	}

	private function hasTargetAction(array $auth, string $targetKey, string $action): bool {
		return in_array($action, $auth['target_permissions'][$targetKey] ?? [], true);
	}

	private function hasAnyTargetAction(array $auth, string $targetKey): bool {
		return ($auth['target_permissions'][$targetKey] ?? []) !== [];
	}

	/** @return array{lookup_workspace_id: int} */
	private function resolveLookupScope(array $auth, ?int $projectId): array {
		if ($projectId === null) {
			if (!$this->hasAnyTargetAction($auth, 'personal')) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key is not authorized for personal lookups.');
			}
			return ['lookup_workspace_id' => (int)$auth['workspace_id']];
		}

		if (!isset($auth['active_areas_by_id'][$projectId]) || !$this->hasAnyTargetAction($auth, (string)$projectId)) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Area not found.');
		}

		return ['lookup_workspace_id' => (int)$auth['active_areas_by_id'][$projectId]['workspace_id']];
	}

	/** @return list<int> */
	private function hiddenIdsForLookup(array $auth, ?int $projectId, string $kind): array {
		if ($projectId !== null) {
			$area = $auth['active_areas_by_id'][$projectId] ?? null;
			if ($area === null) {
				return [];
			}
			return $kind === 'categories'
				? $area['hidden_category_ids']
				: $area['hidden_payment_partner_ids'];
		}

		$prefKey = $kind === 'categories' ? 'hidden_categories' : 'hidden_payment_partners';
		$raw = $this->config->getUserValue((string)$auth['user_id'], 'cobudget', $prefKey, '[]');
		return $this->decodeIntList($raw);
	}

	/** @return array{entry_kind: string, area_workspace_id: int, lookup_workspace_id: int, has_former_member: bool} */
	private function authorizeMutationTarget(array $auth, ?int $projectId, string $requiredAction): array {
		if ($projectId === null) {
			if (!$this->hasTargetAction($auth, 'personal', $requiredAction)) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key lacks permission for personal payments.');
			}
			return [
				'entry_kind' => 'personal',
				'area_workspace_id' => (int)$auth['workspace_id'],
				'lookup_workspace_id' => (int)$auth['workspace_id'],
				'has_former_member' => false,
			];
		}

		$area = $auth['active_areas_by_id'][$projectId] ?? null;
		if ($area === null || !$this->hasAnyTargetAction($auth, (string)$projectId)) {
			// Return 404 when the area does not exist, is archived, or is not granted to this key.
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Area not found.');
		}
		if (!$this->hasTargetAction($auth, (string)$projectId, $requiredAction)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key lacks required permission for this area.');
		}

		return [
			'entry_kind' => $area['is_shared'] ? 'shared' : 'personal',
			'area_workspace_id' => (int)$area['workspace_id'],
			'lookup_workspace_id' => (int)$area['workspace_id'],
			'has_former_member' => (bool)$area['has_former_member'],
		];
	}

	/** @return array<string, mixed> */
	private function loadAuthorizedVisibleEntry(array $auth, int $entryId, string $requiredAction): array {
		$row = $this->loadEntryWithLookupsById($entryId);
		if ($row === null) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
		}

		$userId = (string)$auth['user_id'];
		$workspaceId = (int)$auth['workspace_id'];
		$projectId = $row['project_id'] !== null && $row['project_id'] !== '' ? (int)$row['project_id'] : null;
		$entryKind = (string)($row['entry_kind'] ?? ($projectId === null ? 'personal' : 'shared'));
		$isLocked = $this->dbBool($row['is_locked'] ?? false);

		// Locked personal projections are never exposed directly on canonical entry endpoints.
		if ($isLocked) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
		}

		if ($projectId === null) {
			if (!$this->hasAnyTargetAction($auth, 'personal')
				|| $entryKind !== 'personal' || (string)$row['user_id'] !== $userId || (int)$row['workspace_id'] !== $workspaceId) {
				throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
			}
			if (!$this->hasTargetAction($auth, 'personal', $requiredAction)) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key lacks permission for personal payments.');
			}
			return $row;
		}

		$area = $auth['active_areas_by_id'][$projectId] ?? null;
		if ($area === null || !$this->hasAnyTargetAction($auth, (string)$projectId)) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
		}

		$isAuthorizedRow = false;
		if ($entryKind === 'personal') {
			// Covers solo-area personal entries AND released settled personal entries (#501).
			$isAuthorizedRow = (string)$row['user_id'] === $userId && (int)$row['workspace_id'] === $workspaceId;
		} elseif ($entryKind === 'shared') {
			$isAuthorizedRow = (int)$row['workspace_id'] === (int)$area['workspace_id'];
		}

		if (!$isAuthorizedRow) {
			throw new ApiV1HttpException(Http::STATUS_NOT_FOUND, 'Payment not found.');
		}

		if (!$this->hasTargetAction($auth, (string)$projectId, $requiredAction)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key lacks required permission for this area.');
		}

		return $row;
	}

	/** @return ?array<string, mixed> */
	private function loadEntryWithLookupsById(int $entryId): ?array {
		if ($entryId <= 0) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.*',
			'c.name AS category_name',
			'c.icon AS category_icon',
			'p.name AS payment_partner_name',
			'pr.name AS project_name',
			'pr.owner_id AS project_owner_id'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->leftJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'))
			->where($qb->expr()->eq('e.id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return is_array($row) ? $row : null;
	}

	private function applyEntryReadScope(IQueryBuilder $qb, array $auth, string $scope, bool $includePersonal, array $readAreaIds): void {
		$userId = (string)$auth['user_id'];
		$workspaceId = (int)$auth['workspace_id'];

		if ($scope === 'personal') {
			$qb->andWhere($qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('personal')))
				->andWhere($qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));

			$targetPredicates = [];
			if ($includePersonal) {
				$targetPredicates[] = $qb->expr()->isNull('e.project_id');
			}
			if ($readAreaIds !== []) {
				$targetPredicates[] = $qb->expr()->in('e.project_id', $qb->createNamedParameter($readAreaIds, IQueryBuilder::PARAM_INT_ARRAY));
			}
			$qb->andWhere(count($targetPredicates) === 1 ? $targetPredicates[0] : $qb->expr()->orX(...$targetPredicates));
			return;
		}

		$predicates = [];
		if ($includePersonal) {
			$predicates[] = $qb->expr()->andX(
				$qb->expr()->isNull('e.project_id'),
				$qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('personal')),
				$qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			);
		}

		$soloAreaIds = [];
		foreach ($readAreaIds as $aid) {
			$area = $auth['active_areas_by_id'][$aid];
			if ($area['is_shared']) {
				$predicates[] = $qb->expr()->andX(
					$qb->expr()->eq('e.project_id', $qb->createNamedParameter($aid, \PDO::PARAM_INT)),
					$qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('shared')),
					$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter((int)$area['workspace_id'], \PDO::PARAM_INT))
				);
			} else {
				$soloAreaIds[] = $aid;
			}
		}

		if ($soloAreaIds !== []) {
			$predicates[] = $qb->expr()->andX(
				$qb->expr()->in('e.project_id', $qb->createNamedParameter($soloAreaIds, IQueryBuilder::PARAM_INT_ARRAY)),
				$qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('personal')),
				$qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			);
		}

		$qb->andWhere(count($predicates) === 1 ? $predicates[0] : $qb->expr()->orX(...$predicates));
	}

	private function applyEntryListFilters(IQueryBuilder $qb, array $filters, string $type): void {
		if ($type !== 'all') {
			$qb->andWhere($qb->expr()->eq('e.type', $qb->createNamedParameter($type)));
		}
		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter((int)$filters['date_from'], \PDO::PARAM_INT)));
		}
		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter((int)$filters['date_to'], \PDO::PARAM_INT)));
		}
		if (empty($filters['include_future'])) {
			$qb->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)));
		}
		if (!empty($filters['category_id'])) {
			$qb->andWhere($qb->expr()->eq('e.category_id', $qb->createNamedParameter((int)$filters['category_id'], \PDO::PARAM_INT)));
		}
		if (!empty($filters['payment_partner_id'])) {
			$qb->andWhere($qb->expr()->eq('e.payment_partner_id', $qb->createNamedParameter((int)$filters['payment_partner_id'], \PDO::PARAM_INT)));
		}
		$searchRaw = (string)($filters['search'] ?? '');
		if (trim($searchRaw) !== '') {
			$search = $this->validateCleanUtf8Text($searchRaw, 'search', 200, false, false);
			$escaped = '%' . $this->db->escapeLikeParameter($search) . '%';
			$qb->andWhere($qb->expr()->iLike('e.description', $qb->createNamedParameter($escaped)));
		}
	}

	/** @return list<array<string, mixed>> */
	private function formatEntriesForApi(array $auth, array $rows): array {
		if ($rows === []) {
			return [];
		}

		$userId = (string)$auth['user_id'];
		$rows = $this->entryShareService->attachPersonalShares($rows, $userId);
		$rows = $this->hashtagService->attachHashtagsToEntries($rows);

		$shareBasisPointsCache = [];
		$formatted = [];
		foreach ($rows as $row) {
			$projectId = $row['project_id'] !== null && $row['project_id'] !== '' ? (int)$row['project_id'] : null;
			$entryKind = (string)($row['entry_kind'] ?? ($projectId === null ? 'personal' : 'shared'));
			$isLocked = $this->dbBool($row['is_locked'] ?? false);
			$amountCents = (int)($row['amount_cents'] ?? 0);
			$splitMode = (string)($row['split_mode'] ?? 'project_shares');
			$splitUserId = $row['split_user_id'] !== null && $row['split_user_id'] !== '' ? (string)$row['split_user_id'] : null;

			$ownShareCents = $amountCents;
			if ($entryKind === 'shared' && $projectId !== null) {
				if (array_key_exists('snapshot_share_cents', $row)) {
					$ownShareCents = max(0, (int)$row['snapshot_share_cents']);
				} elseif ($splitMode === 'single_user') {
					$ownShareCents = ($splitUserId ?? (string)$row['user_id']) === $userId ? $amountCents : 0;
				} else {
					if (!array_key_exists($projectId, $shareBasisPointsCache)) {
						$shareBasisPointsCache[$projectId] = $this->loadMemberShareBasisPoints($projectId, $userId);
					}
					$ownShareCents = (int)round($amountCents * $shareBasisPointsCache[$projectId] / 10000);
				}
			}

			$settlementId = $row['settlement_id'] !== null && $row['settlement_id'] !== '' ? (int)$row['settlement_id'] : null;
			$isReleasedPersonal = $entryKind === 'personal' && !$isLocked && ($settlementId !== null || !empty($row['settled_at']));
			$hashtags = [];
			foreach ((array)($row['hashtags'] ?? []) as $tag) {
				if (is_array($tag) && isset($tag['name'])) {
					$hashtags[] = (string)$tag['name'];
				} elseif (is_string($tag)) {
					$hashtags[] = $tag;
				}
			}

			$formatted[] = [
				'id' => (int)$row['id'],
				'workspace_id' => (int)$row['workspace_id'],
				'project_id' => $projectId,
				'project_name' => $row['project_name'] !== null ? (string)$row['project_name'] : null,
				'entry_kind' => $entryKind,
				'is_locked' => $isLocked,
				'is_released_personal' => $isReleasedPersonal,
				'source_entry_id' => $isLocked && $row['source_entry_id'] !== null ? (int)$row['source_entry_id'] : null,
				'settlement_id' => $settlementId,
				'settled_at' => $row['settled_at'] !== null ? (int)$row['settled_at'] : null,
				'type' => (string)$row['type'],
				'amount_cents' => $amountCents,
				'amount' => $this->centsToAmountFloat($amountCents),
				'own_share_cents' => $ownShareCents,
				'currency' => (string)$row['currency'],
				'date' => (int)$row['date'],
				'description' => (string)($row['description'] ?? ''),
				'category_id' => $row['category_id'] !== null && $row['category_id'] !== '' ? (int)$row['category_id'] : null,
				'category_name' => $row['category_name'] !== null ? (string)$row['category_name'] : null,
				'payment_partner_id' => $row['payment_partner_id'] !== null && $row['payment_partner_id'] !== '' ? (int)$row['payment_partner_id'] : null,
				'payment_partner_name' => $row['payment_partner_name'] !== null ? (string)$row['payment_partner_name'] : null,
				'user_id' => (string)$row['user_id'],
				'user_display_name' => $this->participantService->displayName((string)$row['user_id']),
				'created_by' => $row['created_by'] !== null ? (string)$row['created_by'] : null,
				'split_mode' => $splitMode,
				'split_user_id' => $splitUserId,
				'is_settled' => $this->dbBool($row['is_settled'] ?? false),
				'recurrence_interval' => $row['recurrence_interval'] !== null ? (string)$row['recurrence_interval'] : null,
				'recurrence_multiplier' => $row['recurrence_multiplier'] !== null ? (int)$row['recurrence_multiplier'] : null,
				'recurrence_next_date' => $row['recurrence_next_date'] !== null ? (int)$row['recurrence_next_date'] : null,
				'recurrence_end_date' => $row['recurrence_end_date'] !== null ? (int)$row['recurrence_end_date'] : null,
				'is_subscription' => $this->dbBool($row['is_subscription'] ?? false),
				'is_fixed_cost' => $this->dbBool($row['is_fixed_cost'] ?? false),
				'is_child_related' => $this->dbBool($row['is_child_related'] ?? false),
				'is_important' => $this->dbBool($row['is_important'] ?? false),
				'needs_review' => $this->dbBool($row['needs_review'] ?? false),
				'is_tax_relevant' => $this->dbBool($row['is_tax_relevant'] ?? false),
				'is_insurance' => $this->dbBool($row['is_insurance'] ?? false),
				'reminder_date' => $row['reminder_date'] !== null ? (int)$row['reminder_date'] : null,
				'reminder_text' => $row['reminder_text'] !== null ? (string)$row['reminder_text'] : null,
				'hashtags' => $hashtags,
			];
		}

		return $formatted;
	}

	/** @return list<array{id: string, display_name: string, share_basis_points: int}> */
	private function loadActiveMembersForArea(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$members = [];
		foreach ($rows as $row) {
			$uid = trim((string)($row['user_id'] ?? ''));
			if ($uid === '' || ParticipantService::isReservedFormerId($uid)) {
				continue;
			}
			$participant = $this->participantService->participant($uid);
			if (!$participant['isActive'] || $participant['isFormer']) {
				continue;
			}
			$members[] = [
				'id' => $uid,
				'display_name' => $participant['displayName'],
				'share_basis_points' => (int)($row['share_basis_points'] ?? 0),
			];
		}
		return $members;
	}

	private function loadMemberShareBasisPoints(int $projectId, string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$val = $result->fetchOne();
		$result->closeCursor();
		return $val !== false && $val !== null ? max(0, (int)$val) : 0;
	}

	private function isActiveMemberOfArea(int $projectId, string $memberUserId): bool {
		$memberUserId = trim($memberUserId);
		if ($memberUserId === '' || ParticipantService::isReservedFormerId($memberUserId)) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($memberUserId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		if (!$exists) {
			return false;
		}
		return $this->participantService->isActive($memberUserId);
	}

	/** @return array{0: string, 1: string, 2: ?string} */
	private function validatePayerAndSplitForCreate(array $auth, string $entryKind, ?int $projectId, array $payload): array {
		$authUserId = (string)$auth['user_id'];
		if ($entryKind === 'personal') {
			if (array_key_exists('user_id', $payload) && $payload['user_id'] !== null) {
				if (!is_string($payload['user_id'])) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "user_id" must be a string or null.');
				}
				if (trim($payload['user_id']) !== $authUserId) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Personal payments belong strictly to the authenticated user.');
				}
			}
			if (array_key_exists('split_mode', $payload) && $payload['split_mode'] !== null) {
				if (!is_string($payload['split_mode']) || $payload['split_mode'] !== 'single_user') {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Personal payments require split_mode "single_user".');
				}
			}
			if (array_key_exists('split_user_id', $payload) && $payload['split_user_id'] !== null) {
				if (!is_string($payload['split_user_id'])) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" must be a string or null.');
				}
				if (trim($payload['split_user_id']) !== $authUserId) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Personal payments cannot be split with another user.');
				}
			}
			return [$authUserId, 'single_user', $authUserId];
		}

		$payer = $authUserId;
		if (array_key_exists('user_id', $payload) && $payload['user_id'] !== null) {
			if (!is_string($payload['user_id'])) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "user_id" must be a string.');
			}
			$candidate = trim($payload['user_id']);
			if (!$this->isActiveMemberOfArea((int)$projectId, $candidate)) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Selected payer is not an active member of this area.');
			}
			$payer = $candidate;
		}

		$splitMode = 'project_shares';
		if (array_key_exists('split_mode', $payload) && $payload['split_mode'] !== null) {
			if (!is_string($payload['split_mode']) || !in_array($payload['split_mode'], ['project_shares', 'single_user'], true)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid split_mode.');
			}
			$splitMode = $payload['split_mode'];
		}

		$splitUserId = null;
		if ($splitMode === 'single_user') {
			if (!array_key_exists('split_user_id', $payload) || !is_string($payload['split_user_id']) || trim($payload['split_user_id']) === '') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" is required when split_mode is "single_user".');
			}
			$candidateSplit = trim($payload['split_user_id']);
			if (!$this->isActiveMemberOfArea((int)$projectId, $candidateSplit)) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Selected split user is not an active member of this area.');
			}
			$splitUserId = $candidateSplit;
		} elseif (array_key_exists('split_user_id', $payload) && $payload['split_user_id'] !== null) {
			if (!is_string($payload['split_user_id']) || trim($payload['split_user_id']) !== '') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" must be null when split_mode is "project_shares".');
			}
		}

		return [$payer, $splitMode, $splitUserId];
	}

	/** @return array{0: string, 1: string, 2: ?string} */
	private function validatePayerAndSplitForUpdate(
		array $auth,
		string $existingKind,
		bool $isReleasedPersonal,
		?int $projectId,
		array $existing,
		array $payload
	): array {
		$authUserId = (string)$auth['user_id'];
		$existingUserId = (string)($existing['user_id'] ?? $authUserId);
		$existingSplitMode = (string)($existing['split_mode'] ?? 'single_user');
		$existingSplitUser = $existing['split_user_id'] !== null && $existing['split_user_id'] !== '' ? (string)$existing['split_user_id'] : null;

		if ($existingKind === 'personal' || $isReleasedPersonal) {
			if (array_key_exists('user_id', $payload) && $payload['user_id'] !== null) {
				if (!is_string($payload['user_id'])) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "user_id" must be a string or null.');
				}
				if (trim($payload['user_id']) !== $existingUserId) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Personal payments belong strictly to their owner.');
				}
			}
			if (array_key_exists('split_mode', $payload) && $payload['split_mode'] !== null) {
				if (!is_string($payload['split_mode']) || $payload['split_mode'] !== 'single_user') {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Personal payments require split_mode "single_user".');
				}
			}
			if (array_key_exists('split_user_id', $payload) && $payload['split_user_id'] !== null) {
				if (!is_string($payload['split_user_id'])) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" must be a string or null.');
				}
				if (trim($payload['split_user_id']) !== $existingUserId) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Personal payments cannot be split with another user.');
				}
			}
			return [$existingUserId, 'single_user', $isReleasedPersonal ? $existingUserId : ($existingSplitUser ?? $existingUserId)];
		}

		$payer = $existingUserId;
		if (array_key_exists('user_id', $payload)) {
			if (!is_string($payload['user_id']) || trim($payload['user_id']) === '') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "user_id" must be a non-empty string.');
			}
			$candidate = trim($payload['user_id']);
			if ($candidate !== $existingUserId && !$this->isActiveMemberOfArea((int)$projectId, $candidate)) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Selected payer is not an active member of this area.');
			}
			$payer = $candidate;
		}

		$splitMode = $existingSplitMode;
		if (array_key_exists('split_mode', $payload)) {
			if (!is_string($payload['split_mode']) || !in_array($payload['split_mode'], ['project_shares', 'single_user'], true)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid split_mode.');
			}
			$splitMode = $payload['split_mode'];
		}

		$splitUserId = $splitMode === 'project_shares' ? null : $existingSplitUser;
		if ($splitMode === 'single_user') {
			if (array_key_exists('split_user_id', $payload)) {
				if (!is_string($payload['split_user_id']) || trim($payload['split_user_id']) === '') {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" is required when split_mode is "single_user".');
				}
				$candidateSplit = trim($payload['split_user_id']);
				if ($candidateSplit !== $existingSplitUser && !$this->isActiveMemberOfArea((int)$projectId, $candidateSplit)) {
					throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Selected split user is not an active member of this area.');
				}
				$splitUserId = $candidateSplit;
			} elseif ($splitUserId === null) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" is required when split_mode is "single_user".');
			}
		} elseif (array_key_exists('split_user_id', $payload) && $payload['split_user_id'] !== null) {
			if (!is_string($payload['split_user_id']) || trim($payload['split_user_id']) !== '') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "split_user_id" must be null when split_mode is "project_shares".');
			}
		}

		return [$payer, $splitMode, $splitUserId];
	}

	private function assertValidCategory(array $auth, ?int $categoryId, ?int $projectId, int $lookupWorkspaceId, string $type, bool $allowUnchanged = false): void {
		if ($categoryId === null) {
			return;
		}

		$hidden = array_fill_keys($this->hiddenIdsForLookup($auth, $projectId, 'categories'), true);
		if (!$allowUnchanged && isset($hidden[$categoryId])) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Selected category is hidden or unavailable in this scope.');
		}

		$qb = $this->db->getQueryBuilder();
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter((string)$auth['user_id'])),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);
		$globalScope = $qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL));
		if (!$allowUnchanged) {
			$globalScope = $qb->expr()->andX(
				$globalScope,
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			);
		}

		$qb->select('id', 'type')
			->from('cobudget_categories')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX($globalScope, $localScope))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!is_array($row) || (string)($row['type'] ?? '') !== $type) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid category_id for this payment type and scope.');
		}
	}

	private function assertValidPaymentPartner(array $auth, ?int $partnerId, ?int $projectId, int $lookupWorkspaceId, string $type, bool $allowUnchanged = false): void {
		if ($partnerId === null) {
			return;
		}

		$hidden = array_fill_keys($this->hiddenIdsForLookup($auth, $projectId, 'payment_partners'), true);
		if (!$allowUnchanged && isset($hidden[$partnerId])) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Selected payment partner is hidden or unavailable in this scope.');
		}

		$qb = $this->db->getQueryBuilder();
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter((string)$auth['user_id'])),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);
		$globalScope = $qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL));
		if (!$allowUnchanged) {
			$globalScope = $qb->expr()->andX(
				$globalScope,
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			);
		}

		$qb->select('id', 'type')
			->from('cobudget_payment_partners')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($partnerId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX($globalScope, $localScope))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!is_array($row) || (string)($row['type'] ?? '') !== $type) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid payment_partner_id for this payment type and scope.');
		}
	}

	/** @return array{interval: ?string, multiplier: ?int, next_date: ?int, end_date: ?int} */
	private function validateRecurrenceTuple(array $input, \DateTimeZone $timezone, int $now): array {
		$interval = $input['recurrence_interval'];
		$multiplier = $input['recurrence_multiplier'];
		$nextDateRaw = $input['recurrence_next_date'];
		$endDateRaw = $input['recurrence_end_date'];

		if ($interval === null) {
			if ($multiplier !== null || $nextDateRaw !== null || $endDateRaw !== null) {
				throw new ApiV1HttpException(
					Http::STATUS_BAD_REQUEST,
					'recurrence_multiplier, recurrence_next_date, and recurrence_end_date must be null when recurrence_interval is null.'
				);
			}
			return ['interval' => null, 'multiplier' => null, 'next_date' => null, 'end_date' => null];
		}

		if (!is_string($interval) || !isset(self::RECURRENCE_INTERVAL_ALIASES[$interval])) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid recurrence_interval.');
		}
		[$canonicalInterval, $multiplierFactor] = self::RECURRENCE_INTERVAL_ALIASES[$interval];

		$normalizedMultiplier = $multiplier ?? 1;
		if (!is_int($normalizedMultiplier) || $normalizedMultiplier < 1 || $normalizedMultiplier > self::MAX_RECURRENCE_MULTIPLIER) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid recurrence_multiplier (allowed: 1..1000).');
		}
		$scaledMultiplier = $normalizedMultiplier * $multiplierFactor;
		if ($scaledMultiplier < 1 || $scaledMultiplier > self::MAX_RECURRENCE_MULTIPLIER) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid recurrence_multiplier (exceeds maximum canonical multiplier 1000).');
		}

		if ($nextDateRaw === null) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "recurrence_next_date" is required.');
		}
		$nextDate = $this->parseDateOrTimestamp($nextDateRaw, 'recurrence_next_date', $timezone, $now)['timestamp'];

		$normalizedEnd = null;
		if ($endDateRaw !== null) {
			$normalizedEnd = $this->parseDateOrTimestamp($endDateRaw, 'recurrence_end_date', $timezone, $now)['timestamp'];
			if ($normalizedEnd < $nextDate) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "recurrence_end_date" must be >= recurrence_next_date.');
			}
		}

		return [
			'interval' => $canonicalInterval,
			'multiplier' => $scaledMultiplier,
			'next_date' => $nextDate,
			'end_date' => $normalizedEnd,
		];
	}

	private function expandsRecurrence(array $entry, array $recurrence): bool {
		if ($recurrence['interval'] === null) {
			return false;
		}
		if (empty($entry['recurrence_interval']) || $entry['recurrence_next_date'] === null) {
			return true;
		}

		// A different calendar interval can produce additional dates. Same-interval
		// reductions in frequency or duration, and stopping the series, remain edits.
		return $recurrence['interval'] !== $entry['recurrence_interval']
			|| $recurrence['multiplier'] < (int)$entry['recurrence_multiplier']
			|| $recurrence['next_date'] < (int)$entry['recurrence_next_date']
			|| ($entry['recurrence_end_date'] !== null && ($recurrence['end_date'] === null
				|| $recurrence['end_date'] > (int)$entry['recurrence_end_date']));
	}

	private function assertAllowedPayloadKeys(array $payload, array $allowedKeys): void {
		$lookup = array_flip($allowedKeys);
		foreach (array_keys($payload) as $key) {
			if (!is_string($key) || !isset($lookup[$key])) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Unknown JSON field "' . (string)$key . '".');
			}
		}
	}

	/** @return array{timestamp: int, is_future: bool} */
	private function parseDateOrTimestamp(mixed $raw, string $field, \DateTimeZone $timezone, int $now): array {
		if (is_int($raw)) {
			if ($raw <= 0 || $raw > self::MAX_ENTRY_TIMESTAMP) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" is out of allowed range.');
			}
			return ['timestamp' => $raw, 'is_future' => $raw > $now];
		}
		if (!is_string($raw) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches) !== 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" must be YYYY-MM-DD or a Unix timestamp integer.');
		}
		$year = (int)$matches[1];
		$month = (int)$matches[2];
		$day = (int)$matches[3];
		if ($year < 1970 || !checkdate($month, $day, $year)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" is not a valid calendar date.');
		}
		$todayStr = (new \DateTimeImmutable('@' . $now))->setTimezone($timezone)->format('Y-m-d');
		$noon = new \DateTimeImmutable($raw . ' 12:00:00', $timezone);
		$timestamp = $noon->getTimestamp();
		if ($raw === $todayStr && $timestamp > $now) {
			$timestamp = $now;
		}
		if ($timestamp <= 0 || $timestamp > self::MAX_ENTRY_TIMESTAMP) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" is out of allowed range.');
		}
		return ['timestamp' => $timestamp, 'is_future' => $raw > $todayStr || $timestamp > $now];
	}

	/** @return array{0: string, 1: int} */
	private function resolveAmountAndType(
		array $payload,
		string $userId,
		?string $existingType,
		bool $isCreate,
		int $existingCents = 0
	): array {
		$explicitType = null;
		if (array_key_exists('type', $payload)) {
			if (!is_string($payload['type']) || !in_array($payload['type'], ['expense', 'income'], true)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid type. Expected "expense" or "income".');
			}
			$explicitType = $payload['type'];
		}

		$hasCents = array_key_exists('amount_cents', $payload) && $payload['amount_cents'] !== null;
		$hasAmount = array_key_exists('amount', $payload) && $payload['amount'] !== null;
		if ($isCreate && !$hasCents && !$hasAmount) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Either "amount" or "amount_cents" is required.');
		}
		if (array_key_exists('amount', $payload) && $payload['amount'] === null && !$hasCents) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" cannot be null.');
		}
		if (array_key_exists('amount_cents', $payload) && $payload['amount_cents'] === null && !$hasAmount) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount_cents" cannot be null.');
		}

		if (!$hasCents && !$hasAmount) {
			$type = $explicitType ?? ($existingType ?? 'expense');
			$this->assertIncomeTrackingAllowed($userId, $type, $existingType);
			return [$type, $existingCents];
		}

		$fromCents = null;
		if ($hasCents) {
			if (!is_int($payload['amount_cents']) || $payload['amount_cents'] <= 0 || $payload['amount_cents'] > self::MAX_AMOUNT_CENTS) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount_cents" must be a positive integer.');
			}
			$fromCents = $payload['amount_cents'];
		}

		$signedAmountCents = null;
		if ($hasAmount) {
			$signedAmountCents = $this->parseSignedDecimalCents($payload['amount']);
		}

		if ($fromCents !== null && $signedAmountCents !== null && abs($signedAmountCents) !== $fromCents) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Fields "amount_cents" and "amount" do not match.');
		}

		if ($signedAmountCents !== null && $signedAmountCents < 0) {
			if ($explicitType === 'income') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Negative "amount" cannot be combined with type "income".');
			}
			$type = 'expense';
		} elseif ($signedAmountCents !== null && $signedAmountCents > 0) {
			$type = $explicitType ?? ($hasCents ? ($existingType ?? 'expense') : 'income');
		} else {
			$type = $explicitType ?? ($existingType ?? 'expense');
		}

		$this->assertIncomeTrackingAllowed($userId, $type, $existingType);
		return [$type, $fromCents ?? abs((int)$signedAmountCents)];
	}

	private function assertIncomeTrackingAllowed(string $userId, string $type, ?string $existingType): void {
		if ($type === 'income' && $existingType !== 'income'
			&& $this->config->getUserValue($userId, 'cobudget', 'enable_incomes', 'yes') !== 'yes') {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Income tracking is disabled in CoBudget settings.');
		}
	}

	private function resolveCategoryIdFromPayload(
		array $auth,
		array $payload,
		?int $projectId,
		int $lookupWorkspaceId,
		string $type,
		?int $existingCategoryId,
		bool $allowExistingUnchanged
	): ?int {
		if (array_key_exists('category', $payload) && array_key_exists('category_id', $payload)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Provide either "category" or "category_id", not both.');
		}
		if (array_key_exists('category', $payload)) {
			return $this->resolveLookupIdByName(
				$auth,
				'cobudget_categories',
				'categories',
				$payload['category'],
				$projectId,
				$lookupWorkspaceId,
				$type,
				'category'
			);
		}
		$hasId = array_key_exists('category_id', $payload);
		$categoryId = $hasId ? $this->parseOptionalPositiveIntField($payload, 'category_id') : $existingCategoryId;
		$allowUnchanged = $allowExistingUnchanged && (!$hasId || $categoryId === $existingCategoryId);
		$this->assertValidCategory($auth, $categoryId, $projectId, $lookupWorkspaceId, $type, $allowUnchanged);
		return $categoryId;
	}

	private function resolvePaymentPartnerIdFromPayload(
		array $auth,
		array $payload,
		?int $projectId,
		int $lookupWorkspaceId,
		string $type,
		?int $existingPartnerId,
		bool $allowExistingUnchanged
	): ?int {
		if (array_key_exists('payment_partner', $payload) && array_key_exists('payment_partner_id', $payload)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Provide either "payment_partner" or "payment_partner_id", not both.');
		}
		if (array_key_exists('payment_partner', $payload)) {
			return $this->resolveLookupIdByName(
				$auth,
				'cobudget_payment_partners',
				'payment_partners',
				$payload['payment_partner'],
				$projectId,
				$lookupWorkspaceId,
				$type,
				'payment_partner'
			);
		}
		$hasId = array_key_exists('payment_partner_id', $payload);
		$partnerId = $hasId ? $this->parseOptionalPositiveIntField($payload, 'payment_partner_id') : $existingPartnerId;
		$allowUnchanged = $allowExistingUnchanged && (!$hasId || $partnerId === $existingPartnerId);
		$this->assertValidPaymentPartner($auth, $partnerId, $projectId, $lookupWorkspaceId, $type, $allowUnchanged);
		return $partnerId;
	}

	private function resolveLookupIdByName(
		array $auth,
		string $table,
		string $hiddenKind,
		mixed $rawName,
		?int $projectId,
		int $lookupWorkspaceId,
		string $type,
		string $field
	): ?int {
		if ($rawName === null) {
			return null;
		}
		if (!is_string($rawName) || !mb_check_encoding($rawName, 'UTF-8') || str_contains($rawName, "\u{FFFD}") || preg_match('/[\x00-\x1F\x7F]/', $rawName) === 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" must be a valid UTF-8 string or null.');
		}
		$normalized = trim((string)preg_replace('/\s+/u', ' ', $rawName));
		if ($normalized === '') {
			return null;
		}
		if (mb_strlen($normalized, 'UTF-8') > 128) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" exceeds 128 characters.');
		}
		$needle = mb_strtolower($normalized, 'UTF-8');
		$hidden = array_fill_keys($this->hiddenIdsForLookup($auth, $projectId, $hiddenKind), true);

		$qb = $this->db->getQueryBuilder();
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter((string)$auth['user_id'])),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($lookupWorkspaceId, \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);
		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);

		$qb->select('id', 'name')
			->from($table)
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->orX($globalScope, $localScope))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$id = (int)$row['id'];
			if (isset($hidden[$id])) {
				continue;
			}
			$rowName = trim((string)preg_replace('/\s+/u', ' ', (string)($row['name'] ?? '')));
			if (mb_strtolower($rowName, 'UTF-8') === $needle) {
				return $id;
			}
		}

		throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Unknown "' . $field . '" for this payment type and scope.');
	}

	/**
	 * @param array<string, bool> $defaults
	 * @return array<string, bool>
	 */
	private function resolveLabelFlags(array $payload, string $userId, string $type, array $defaults): array {
		$flags = $defaults;
		foreach (self::LABEL_FIELD_MAP as $jsonKey => $meta) {
			$column = $meta['col'];
			$legacyKey = $meta['legacy'];
			$hasCanonical = array_key_exists($jsonKey, $payload);
			$hasLegacy = array_key_exists($legacyKey, $payload);

			if ($hasCanonical || $hasLegacy) {
				$canonicalVal = $hasCanonical ? $this->parseLabelFlagValue($payload[$jsonKey], $jsonKey) : null;
				$legacyVal = $hasLegacy ? $this->parseLabelFlagValue($payload[$legacyKey], $legacyKey) : null;
				if ($canonicalVal !== null && $legacyVal !== null && $canonicalVal !== $legacyVal) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Conflicting values for "' . $jsonKey . '" and "' . $legacyKey . '".');
				}
				$requested = $canonicalVal ?? $legacyVal;
				$enabled = $this->config->getUserValue($userId, 'cobudget', $meta['setting'], 'yes') === 'yes';
				$allowedForType = !$meta['expense_only'] || $type === 'expense';
				$flags[$column] = (bool)$requested && $enabled && $allowedForType;
			} elseif ($meta['expense_only'] && $type !== 'expense') {
				$flags[$column] = false;
			}
		}
		return $flags;
	}

	private function parseLabelFlagValue(mixed $value, string $field): bool {
		if (is_bool($value)) {
			return $value;
		}
		if ($value === 0) {
			return false;
		}
		if ($value === 1) {
			return true;
		}
		throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" must be 0, 1, or a boolean.');
	}

	private function parseSignedDecimalCents(mixed $raw): int {
		if (is_int($raw)) {
			$cents = $raw * 100;
			if ($cents === 0 || abs($cents) > self::MAX_AMOUNT_CENTS) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" is out of allowed range.');
			}
			return $cents;
		}
		if (is_float($raw)) {
			if (!is_finite($raw) || abs($raw) > (self::MAX_AMOUNT_CENTS / 100)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" is out of allowed range.');
			}
			$formatted = sprintf('%.2F', $raw);
			if (abs((float)$formatted - $raw) > 1e-9) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" supports at most 2 decimal places.');
			}
			$raw = $formatted;
		}
		if (!is_string($raw)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" must be a non-zero decimal number or string.');
		}
		$trimmed = trim($raw);
		if (preg_match('/^([+-]?)(\d{1,8})(?:\.(\d{1,2}))?$/D', $trimmed, $matches) !== 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid "amount" format. Use dot decimals with at most 2 decimal places.');
		}
		$sign = $matches[1] === '-' ? -1 : 1;
		$whole = (int)$matches[2];
		$frac = isset($matches[3]) ? (int)str_pad($matches[3], 2, '0', STR_PAD_RIGHT) : 0;
		$cents = ($whole * 100) + $frac;
		if ($cents <= 0 || $cents > self::MAX_AMOUNT_CENTS) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" is out of allowed range.');
		}
		return $sign * $cents;
	}

	private function parseStrictDecimalCents(mixed $raw): int {
		$signed = $this->parseSignedDecimalCents($raw);
		if ($signed <= 0) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "amount" must be positive.');
		}
		return $signed;
	}

	private function validateIsoCurrency(mixed $value): string {
		if (!is_string($value)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "currency" must be a 3-letter uppercase ISO code.');
		}
		$trimmed = trim($value);
		if (preg_match('/^[A-Z]{3}$/D', $trimmed) !== 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "currency" must be a 3-letter uppercase ISO code.');
		}
		return $trimmed;
	}

	private function parseOptionalProjectIdField(array $payload): ?int {
		if (!array_key_exists('project_id', $payload) || $payload['project_id'] === null) {
			return null;
		}
		if (!is_int($payload['project_id']) || $payload['project_id'] < 0 || $payload['project_id'] > self::MAX_DB_ID) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "project_id" must be 0, an active area ID, or null.');
		}
		return $payload['project_id'] === 0 ? null : $payload['project_id'];
	}

	private function parseOptionalPositiveIntField(array $payload, string $key): ?int {
		if (!array_key_exists($key, $payload) || $payload[$key] === null) {
			return null;
		}
		if (!is_int($payload[$key]) || $payload[$key] <= 0 || $payload[$key] > self::MAX_DB_ID) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $key . '" must be a positive 32-bit integer or null.');
		}
		return $payload[$key];
	}

	private function parseBoolField(array $payload, string $key, bool $default): bool {
		if (!array_key_exists($key, $payload)) {
			return $default;
		}
		if (!is_bool($payload[$key])) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $key . '" must be a boolean.');
		}
		return $payload[$key];
	}

	private function validateCleanUtf8Text(mixed $raw, string $field, int $maxLength, bool $allowNewlinesAndTabs, bool $enforceLength = true): string {
		if (!is_string($raw)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" must be a string.');
		}
		if (!mb_check_encoding($raw, 'UTF-8')) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" must be valid UTF-8.');
		}
		$controlPattern = $allowNewlinesAndTabs
			? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'
			: '/[\x00-\x1F\x7F]/';
		if (preg_match($controlPattern, $raw) === 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" contains disallowed control characters.');
		}
		$trimmed = trim($raw);
		if (mb_strlen($trimmed, 'UTF-8') > $maxLength) {
			if ($enforceLength) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Field "' . $field . '" exceeds ' . $maxLength . ' characters.');
			}
			$trimmed = mb_substr($trimmed, 0, $maxLength, 'UTF-8');
		}
		return $trimmed;
	}

	public static function resolveUserCurrency(IConfig $config, string $userId): string {
		$stored = trim($config->getUserValue($userId, 'cobudget', 'currency', ''));
		if ($stored !== '' && mb_strlen($stored, 'UTF-8') <= 10) {
			return $stored;
		}

		$locale = trim($config->getUserValue($userId, 'core', 'locale', ''));
		if ($locale === '') {
			$locale = trim($config->getUserValue($userId, 'core', 'lang', ''));
		}
		$parts = array_values(array_filter(explode('_', str_replace('-', '_', $locale)), static fn (string $part): bool => $part !== ''));
		$language = strtolower((string)($parts[0] ?? ''));
		$country = strtoupper((string)($parts[1] ?? ''));
		if ($country !== '' && isset(self::CURRENCY_BY_COUNTRY[$country])) {
			return self::CURRENCY_BY_COUNTRY[$country];
		}
		if ($language !== '' && isset(self::CURRENCY_BY_LANGUAGE[$language])) {
			return self::CURRENCY_BY_LANGUAGE[$language];
		}
		return 'EUR';
	}

	private function resolveAccountCurrency(string $userId): string {
		return self::resolveUserCurrency($this->config, $userId);
	}

	private function setEntryRecurrenceSeriesId(int $entryId, int $seriesId, int $workspaceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('recurrence_series_id', $qb->createNamedParameter($seriesId, \PDO::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	/** @return array{changed_by: string, changed_by_display_name: string, changed_at: int, change_group: string} */
	private function newHistoryContext(string $userId): array {
		try {
			$changeGroup = bin2hex(random_bytes(8));
		} catch (\Throwable) {
			$changeGroup = str_replace('.', '', uniqid('', true));
		}

		return [
			'changed_by' => $userId,
			'changed_by_display_name' => $this->participantService->displayName($userId),
			'changed_at' => time(),
			'change_group' => $changeGroup,
		];
	}

	private function recordPersonalProjectionHistories(array $before, array $after, array $context, IL10N $l10n): void {
		foreach ($after as $userId => $newEntry) {
			$oldEntry = $before[$userId] ?? null;
			if ($oldEntry === null) {
				$oldEntry = $newEntry;
				$oldEntry['amount'] = '0.00';
				$oldEntry['amount_cents'] = 0;
			}
			$this->recordEntryHistory(
				(int)$newEntry['id'],
				(int)$newEntry['workspace_id'],
				empty($newEntry['project_id']) ? null : (int)$newEntry['project_id'],
				$oldEntry,
				$newEntry,
				$context,
				$l10n
			);
		}
	}

	private function recordEntryHistory(
		int $entryId,
		int $workspaceId,
		?int $projectId,
		array $oldEntry,
		array $newEntry,
		array $context,
		IL10N $l10n
	): void {
		$changes = [];
		foreach (self::ENTRY_HISTORY_FIELDS as $field) {
			$oldValue = $this->historyComparableValue($field, $oldEntry[$field] ?? null);
			$newValue = $this->historyComparableValue($field, $newEntry[$field] ?? null);
			if ($oldValue === $newValue) {
				continue;
			}
			$changes[] = [
				'field' => $field,
				'old_value' => $oldValue,
				'new_value' => $newValue,
				'old_display' => $this->historyDisplayValue($field, $oldValue, $oldEntry, $l10n),
				'new_display' => $this->historyDisplayValue($field, $newValue, $newEntry, $l10n),
			];
		}

		foreach ($changes as $change) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_history')
				->values([
					'entry_id' => $qb->createNamedParameter($entryId, \PDO::PARAM_INT),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'project_id' => $qb->createNamedParameter($projectId, $projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'changed_by' => $qb->createNamedParameter($context['changed_by']),
					'changed_by_display_name' => $qb->createNamedParameter($context['changed_by_display_name']),
					'changed_at' => $qb->createNamedParameter($context['changed_at'], \PDO::PARAM_INT),
					'change_group' => $qb->createNamedParameter($context['change_group']),
					'field' => $qb->createNamedParameter($change['field']),
					'old_value' => $qb->createNamedParameter($change['old_value'], $change['old_value'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'new_value' => $qb->createNamedParameter($change['new_value'], $change['new_value'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'old_display' => $qb->createNamedParameter($change['old_display']),
					'new_display' => $qb->createNamedParameter($change['new_display']),
				]);
			$qb->executeStatement();
		}
	}

	private function historyComparableValue(string $field, mixed $value): ?string {
		if (in_array($field, self::ENTRY_HISTORY_BOOLEAN_FIELDS, true)) {
			return $this->dbBool($value) ? '1' : '0';
		}
		if ($value === null || $value === '') {
			return null;
		}
		if (in_array($field, self::ENTRY_HISTORY_INTEGER_FIELDS, true)) {
			$intValue = (int)$value;
			return $intValue > 0 || $field === 'amount_cents' || $field === 'recurrence_multiplier' ? (string)$intValue : null;
		}
		$trimmed = trim((string)$value);
		return $trimmed === '' ? null : $trimmed;
	}

	private function historyDisplayValue(string $field, ?string $value, array $entry, IL10N $l10n): string {
		if ($value === null || $value === '') {
			return '-';
		}
		if ($field === 'amount_cents') {
			$currency = trim((string)($entry['currency'] ?? 'EUR')) ?: 'EUR';
			return number_format(((int)$value) / 100, 2, ',', '.') . ' ' . $currency;
		}
		if ($field === 'type') {
			return $value === 'income' ? $l10n->t('Income') : $l10n->t('Expense');
		}
		if ($field === 'split_mode') {
			return $value === 'single_user'
				? $l10n->t('Assigned fully to the selected user')
				: $l10n->t('By area split');
		}
		if (in_array($field, self::ENTRY_HISTORY_BOOLEAN_FIELDS, true)) {
			return $value === '1' ? $l10n->t('Yes') : $l10n->t('No');
		}
		if (in_array($field, ['date', 'recurrence_end_date', 'recurrence_next_date', 'reminder_date'], true)) {
			return date($field === 'recurrence_next_date' || $field === 'reminder_date' ? 'd.m.Y H:i' : 'd.m.Y', (int)$value);
		}
		if ($field === 'category_id') {
			return $this->lookupName('cobudget_categories', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'payment_partner_id') {
			return $this->lookupName('cobudget_payment_partners', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'project_id') {
			return $this->lookupName('cobudget_projects', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'user_id' || $field === 'split_user_id') {
			return $this->participantService->displayName($value);
		}
		return $value;
	}

	private function lookupName(string $table, int $id): ?string {
		if ($id <= 0) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('name')->from($table)->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))->setMaxResults(1);
		$result = $qb->executeQuery();
		$name = $result->fetchOne();
		$result->closeCursor();
		return is_string($name) && $name !== '' ? $name : null;
	}

	/** @return list<array<string, mixed>> */
	private function fetchEntryAttachmentsForEntries(array $entryIds): array {
		$attachments = [];
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('owner_user_id', 'file_path', 'file_id')
				->from('cobudget_entry_attachments')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$attachments[] = $row;
			}
			$result->closeCursor();
		}
		return $attachments;
	}

	private function deleteEntryAttachmentRowsForEntries(array $entryIds): void {
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_entry_attachments')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	private function deleteEntryHistoryForEntries(array $entryIds): void {
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_entry_history')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	private function deleteEntryRowsByIds(array $entryIds): int {
		$deleted = 0;
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_entries')
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleted += $qb->executeStatement();
		}
		return $deleted;
	}

	private function deleteEntryAttachmentFilesAfterCommit(array $attachments): void {
		foreach ($attachments as $attachment) {
			$owner = trim((string)($attachment['owner_user_id'] ?? ''));
			if ($owner === '') {
				continue;
			}
			if ($this->config->getUserValue($owner, 'cobudget', 'delete_receipts_with_entry', 'no') !== 'yes') {
				continue;
			}
			try {
				ReceiptFile::delete($this->rootFolder, $attachment);
			} catch (\Throwable $e) {
				$this->logger->warning('Failed to remove receipt file after API v1 payment deletion: ' . $e->getMessage(), [
					'app' => 'cobudget',
				]);
			}
		}
	}

	private function auditLog(array $auth, string $action, int $entryId): void {
		try {
			$this->logger->info('CoBudget API v1 mutation', [
				'app' => 'cobudget',
				'action' => $action,
				'token_id' => (int)$auth['token_id'],
				'token_prefix' => (string)$auth['token_prefix'],
				'user_id' => (string)$auth['user_id'],
				'workspace_id' => (int)$auth['workspace_id'],
				'entry_id' => $entryId,
			]);
		} catch (\Throwable) {
			// Ignore logging failures after committed writes.
		}
	}

	private function centsToAmountString(int $cents): string {
		$negative = $cents < 0 ? '-' : '';
		$abs = abs($cents);
		return sprintf('%s%d.%02d', $negative, intdiv($abs, 100), $abs % 100);
	}

	private function centsToAmountFloat(int $cents): float {
		return round($cents / 100, 2);
	}

	/** @return list<int> */
	private function decodeIntList(mixed $json): array {
		if (!is_string($json) || trim($json) === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		$ids = [];
		foreach ($decoded as $val) {
			if (is_int($val) || (is_string($val) && ctype_digit($val))) {
				$id = (int)$val;
				if ($id > 0) {
					$ids[$id] = $id;
				}
			}
		}
		return array_values($ids);
	}

	private function dbBool(mixed $value): bool {
		return in_array($value, [true, 1, '1', 't', 'true'], true);
	}
}

<?php
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\EntryShareService;
use OCA\CoBudget\Service\EntryProjectionService;
use OCA\CoBudget\Service\ProjectNotificationService;
use OCA\CoBudget\Service\ParticipantService;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IConfig;
use OCP\IL10N;

class ProjectController extends Controller {
	use WorkspaceAwareTrait;

	private const MAX_PROJECT_MEMBERS = 100;

	private IDBConnection $db;
	private ?string $userId;
	private IUserManager $userManager;
	private IConfig $config;
	private EntryShareService $entryShareService;
	private EntryProjectionService $entryProjectionService;
	private ProjectNotificationService $projectNotificationService;
	private ParticipantService $participantService;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IConfig $config, EntryShareService $entryShareService, EntryProjectionService $entryProjectionService, ProjectNotificationService $projectNotificationService, ParticipantService $participantService, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->entryShareService = $entryShareService;
		$this->entryProjectionService = $entryProjectionService;
		$this->projectNotificationService = $projectNotificationService;
		$this->participantService = $participantService;
		$this->l10n = $l10n;
		$this->initWorkspace();
	}

	private function projectHasOpenSharedPayments(int $projectId, int $workspaceId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->setMaxResults(1);

		return $qb->executeQuery()->fetchOne() !== false;
	}

	private function projectHasPayments(int $projectId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		return $qb->executeQuery()->fetchOne() !== false;
	}

	private function projectHasLifecycleHistory(int $projectId): bool {
		foreach (['cobudget_settlements', 'cobudget_entry_history'] as $table) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from($table)
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
				->setMaxResults(1);
			if ($qb->executeQuery()->fetchOne() !== false) {
				return true;
			}
		}

		return false;
	}

	private function projectScopedCategoryIds(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_categories')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$ids = array_map('intval', array_column($result->fetchAll(), 'id'));
		$result->closeCursor();

		return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
	}

	private function budgetGoalsReferenceProject(int $projectId, int $workspaceId, array $categoryIds): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('criteria_json')
			->from('cobudget_budget_goals')
			->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		$categoryIds = array_fill_keys(array_map('intval', $categoryIds), true);

		foreach ($rows as $row) {
			$criteria = json_decode((string)($row['criteria_json'] ?? ''), true);
			if (!is_array($criteria)) {
				continue;
			}

			foreach (($criteria['rules'] ?? []) as $rule) {
				if (!is_array($rule)) {
					continue;
				}
				$ruleProjectId = (int)($rule['projectId'] ?? $rule['project_id'] ?? 0);
				$ruleCategoryId = (int)($rule['categoryId'] ?? $rule['category_id'] ?? 0);
				if ($ruleProjectId === $projectId || isset($categoryIds[$ruleCategoryId])) {
					return true;
				}
			}

			$legacyProjectIds = $criteria['projectIds'] ?? $criteria['project_ids'] ?? [];
			if (in_array($projectId, array_map('intval', is_array($legacyProjectIds) ? $legacyProjectIds : []), true)) {
				return true;
			}
			$legacyCategoryIds = $criteria['categoryIds'] ?? $criteria['category_ids'] ?? [];
			foreach (array_map('intval', is_array($legacyCategoryIds) ? $legacyCategoryIds : []) as $categoryId) {
				if (isset($categoryIds[$categoryId])) {
					return true;
				}
			}
		}

		return false;
	}

	private function memberManagementLockReason(int $projectId, int $workspaceId, int $memberCount): ?string {
		if ($memberCount <= 1) {
			return $this->projectHasPayments($projectId)
				? 'solo_payments'
				: null;
		}

		return $this->projectHasOpenSharedPayments($projectId, $workspaceId)
			? 'open_shared_payments'
			: null;
	}

	private function sharedProjectsEnabled(): bool {
		return $this->config->getUserValue($this->userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes';
	}

	private function userSearchAllowed(): bool {
		return $this->systemFlagEnabled('shareapi_allow_share_dialog_user_enumeration', true);
	}

	private function systemFlagEnabled(string $key, bool $default): bool {
		$value = $this->config->getSystemValue($key, $default);
		if (is_bool($value)) {
			return $value;
		}

		return !in_array(strtolower((string)$value), ['0', 'false', 'no', 'off'], true);
	}

	private function requireProjectOwner(int $id): ?DataResponse {
		if (!$this->projectOwnerInActiveWorkspace($id)) {
			return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function projectMembers(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		$rowsByUser = [];
		foreach ($rows as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			if ($userId !== '' && !isset($rowsByUser[$userId])) {
				$rowsByUser[$userId] = $row;
			}
		}
		$rows = array_values($rowsByUser);

		$shares = $this->memberShareBasisPoints($rows);
		$members = [];
		foreach ($rows as $row) {
			$userId = (string)$row['user_id'];
			$participant = $this->participantService->participant($userId);
			$shareBasisPoints = $shares[$userId] ?? 0;
			$members[] = [
				'id' => $userId,
				'user_id' => $userId,
				'displayName' => $participant['displayName'],
				'isFormer' => $participant['isFormer'],
				'is_former' => $participant['isFormer'],
				'isActive' => $participant['isActive'],
				'is_active' => $participant['isActive'],
				'share_basis_points' => $shareBasisPoints,
				'shareBasisPoints' => $shareBasisPoints,
				'sharePercent' => (int)round($shareBasisPoints / 100),
			];
		}

		return $members;
	}

	private function projectShareBasisPoints(array $members): array {
		return $this->memberShareBasisPoints($members);
	}

	private function projectMemberIds(array $members): array {
		$userIds = [];
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId !== '') {
				$userIds[] = $userId;
			}
		}

		return array_values(array_unique($userIds));
	}

	private function projectSharesAreEqual(array $members): bool {
		$userIds = $this->projectMemberIds($members);
		if ($userIds === []) {
			return true;
		}

		$expectedShares = $this->equalShareBasisPoints($userIds);
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId === '') {
				continue;
			}
			$shareBasisPoints = (int)($member['shareBasisPoints'] ?? $member['share_basis_points'] ?? 0);
			if ($shareBasisPoints !== ($expectedShares[$userId] ?? 0)) {
				return false;
			}
		}

		return true;
	}

	private function updateProjectShareBasisPoints(int $projectId, array $shares): void {
		foreach ($shares as $userId => $shareBasisPoints) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_members')
				->set('share_basis_points', $qb->createNamedParameter((int)$shareBasisPoints, \PDO::PARAM_INT))
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter((string)$userId)));
			$qb->executeStatement();
		}
	}

	private function normalizeProjectSharesToTotal(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$shares = $this->memberShareBasisPoints($rows);
		$this->updateProjectShareBasisPoints($projectId, $shares);
	}

	private function memberHasOpenPaymentInvolvement(int $projectId, int $workspaceId, string $userId, array $members): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'project_id', 'amount', 'amount_cents', 'type', 'split_mode', 'split_user_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		$entryShares = $this->entryShareService->sharesForEntries(array_column($entries, 'id'));
		$fallbackShares = $this->projectShareBasisPoints($members);
		foreach ($entries as $entry) {
			if ((string)($entry['user_id'] ?? '') === $userId) {
				return true;
			}

			if ((string)($entry['split_user_id'] ?? '') === $userId) {
				return true;
			}

			$entryId = (int)($entry['id'] ?? 0);
			if (isset($entryShares[$entryId])) {
				$allocation = $entryShares[$entryId][$userId] ?? null;
				if ($allocation !== null
					&& ((int)$allocation['amount_cents'] > 0 || (int)$allocation['share_basis_points'] > 0)) {
					return true;
				}
				continue;
			}

			$amountCents = $this->amountCentsFromRow($entry) ?? 0;
			if ($this->storedOrCalculatedShareCents($entry, $userId, $amountCents, $fallbackShares) > 0) {
				return true;
			}
		}

		return false;
	}

	private function calculatePersonalBalance(int $projectId, int $workspaceId, string $userId, array $members): float {
		$shares = $this->projectShareBasisPoints($members);
		if ($shares === []) {
			return 0.0;
		}

		$qbEntries = $this->db->getQueryBuilder();
		$qbEntries->select('id', 'user_id', 'project_id', 'amount', 'amount_cents', 'type', 'split_mode', 'split_user_id')
			->from('cobudget_entries')
			->where($qbEntries->expr()->eq('project_id', $qbEntries->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qbEntries->expr()->eq('workspace_id', $qbEntries->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qbEntries->expr()->eq('entry_kind', $qbEntries->createNamedParameter('shared')))
			->andWhere($qbEntries->expr()->eq('is_settled', $qbEntries->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$resultEntries = $qbEntries->executeQuery();
		$entries = $resultEntries->fetchAll();
		$resultEntries->closeCursor();
		$entries = $this->entryShareService->attachPersonalShares($entries, $userId);

		$balanceCents = 0;
		foreach ($entries as $entry) {
			$amountCents = $this->amountCentsFromRow($entry) ?? 0;
			$personalShareCents = $this->storedOrCalculatedShareCents($entry, $userId, $amountCents, $shares);
			$isEntryUser = ($entry['user_id'] ?? null) === $userId;
			$balanceCents += ($entry['type'] ?? '') === 'income'
				? $personalShareCents - ($isEntryUser ? $amountCents : 0)
				: ($isEntryUser ? $amountCents : 0) - $personalShareCents;
		}

		return round($balanceCents / 100, 2);
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 60, period: 60)]
	public function index(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found or no permission', Http::STATUS_FORBIDDEN);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('p.*')
				->from('cobudget_projects', 'p')
				->innerJoin('p', 'cobudget_members', 'm',
					$qb->expr()->eq('p.id', 'm.project_id'))
				->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)))
				->andWhere($qb->expr()->eq('m.personal_workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));

			$result = $qb->executeQuery();
			$projects = $result->fetchAll();
			$result->closeCursor();

			// Add member count, current-user share, and personal balance to each project.
			foreach ($projects as &$project) {
				$projectId = (int)$project['id'];
				$members = $this->projectMembers($projectId);
				$shares = $this->projectShareBasisPoints($members);
				$project['is_owner'] = (string)$project['owner_id'] === (string)$this->userId;
				$project['member_count'] = count($members);
				$project['is_shared'] = count($members) > 1;
				$project['my_share_basis_points'] = $shares[(string)$this->userId] ?? 10000;
				$project['personal_balance'] = $this->calculatePersonalBalance($projectId, (int)$project['workspace_id'], (string)$this->userId, $members);
			}

			return new DataResponse($projects);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 10, period: 60)]
	public function create(string $name = '', array $members = [], string $color = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validateRequiredName($name)) {
				return $validationError;
			}
			$color = $this->normalizeOptionalString($color, 32);

			$workspaceId = $this->getWorkspaceId();
			$members = $this->normalizeUserIdList($members);
			if (!$this->sharedProjectsEnabled()) {
				$members = [];
			}

			if (!in_array($this->userId, $members, true)) {
				$members[] = $this->userId;
			}
			if (count($members) > self::MAX_PROJECT_MEMBERS) {
				return $this->errorResponse('User could not be added.', Http::STATUS_BAD_REQUEST);
			}
			$otherMembers = array_values(array_filter(
				$members,
				fn (string $memberId): bool => $memberId !== (string)$this->userId
			));
			if ($otherMembers !== [] && !$this->userSearchAllowed()) {
				return $this->errorResponse('Adding members is disabled because Nextcloud user search is disabled.', Http::STATUS_FORBIDDEN);
			}

			foreach ($members as $memberId) {
				if (!$this->participantService->isActive($memberId)) {
					return $this->errorResponse('User could not be added.', Http::STATUS_BAD_REQUEST);
				}
			}

			$this->db->beginTransaction();

			try {
				$qb = $this->db->getQueryBuilder();
				$qb->insert('cobudget_projects')
					->values([
						'name' => $qb->createNamedParameter($name),
						'owner_id' => $qb->createNamedParameter($this->userId),
						'color' => $qb->createNamedParameter($color),
						'created_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
						'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					]);
				$qb->executeStatement();
				$projectId = (int)$this->db->lastInsertId('*PREFIX*cobudget_projects');

				$shares = $this->equalShareBasisPoints($members);
				foreach ($members as $memberId) {
					$personalWorkspaceId = $this->entryProjectionService->personalWorkspaceIdForMember($projectId, $memberId);
					$qbMem = $this->db->getQueryBuilder();
					$qbMem->insert('cobudget_members')
						->values([
							'project_id' => $qbMem->createNamedParameter($projectId, \PDO::PARAM_INT),
							'user_id' => $qbMem->createNamedParameter($memberId),
							'share_basis_points' => $qbMem->createNamedParameter($shares[$memberId] ?? 0, \PDO::PARAM_INT),
							'personal_workspace_id' => $qbMem->createNamedParameter($personalWorkspaceId, \PDO::PARAM_INT),
						]);
					$qbMem->executeStatement();
				}

				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse(['id' => $projectId, 'name' => $name, 'color' => $color, 'status' => 'success']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $name = '', string $color = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredName($name)) {
				return $validationError;
			}
			$color = $this->normalizeOptionalString($color, 32);

			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}
			$project = $this->projectOwnerForCurrentUser($id);
			if (!$project) {
				return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
			}
			$workspaceId = (int)$project['workspace_id'];

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_projects')
				->set('name', $qb->createNamedParameter($name))
				->set('color', $qb->createNamedParameter($color))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$qb->executeStatement();

			return new DataResponse(['id' => $id, 'name' => $name, 'color' => $color, 'status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

			$project = $this->projectOwnerForCurrentUser($id);
			if ($project === null) {
				return $this->errorResponse('Area not found.', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$project['workspace_id'];
			if ($this->projectHasPayments($id) || $this->projectHasLifecycleHistory($id)) {
				return $this->errorResponse('This area contains payments or settlement history and can only be archived.', Http::STATUS_CONFLICT);
			}
			$categoryIds = $this->projectScopedCategoryIds($id);
			if ($this->budgetGoalsReferenceProject($id, $workspaceId, $categoryIds)) {
				return $this->errorResponse('Remove this area from budget goals before permanently deleting it.', Http::STATUS_CONFLICT);
			}

			$this->db->beginTransaction();
			try {
				$this->deleteRowsByColumnValues('cobudget_templates', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_categories', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_payment_partners', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_members', 'project_id', [$id]);

				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_projects')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
				if ($qb->executeStatement() !== 1) {
					throw new \RuntimeException('Area could not be deleted.');
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse(['status' => 'success', 'deleted' => true]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function settlementIdsForProject(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_settlements')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));

		$result = $qb->executeQuery();
		$ids = array_map('intval', array_column($result->fetchAll(), 'id'));
		$result->closeCursor();

		return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
	}

	private function deleteRowsByColumnValues(string $table, string $column, array $values): void {
		$values = array_values(array_unique(array_map('intval', $values)));
		if ($values === []) {
			return;
		}

		foreach ($values as $value) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function archive(int $id): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				if ($ownerError = $this->requireProjectOwner($id)) {
					return $ownerError;
				}

				$project = $this->projectOwnerForCurrentUser($id);
				if (!$project) {
					return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
				}
				$workspaceId = (int)$project['workspace_id'];
			if ($this->projectHasOpenSharedPayments($id, $workspaceId)) {
				return $this->errorResponse('Settle the area before archiving it.', Http::STATUS_CONFLICT);
			}
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_projects')
				->set('is_archived', $qb->createNamedParameter(true, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function unarchive(int $id): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				if ($ownerError = $this->requireProjectOwner($id)) {
					return $ownerError;
				}

				$project = $this->projectOwnerForCurrentUser($id);
				if (!$project) {
					return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
				}
				$workspaceId = (int)$project['workspace_id'];
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_projects')
				->set('is_archived', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$project = $this->projectVisibleForCurrentUser($id);
			if (!$project) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}
			$workspaceId = (int)$project['workspace_id'];

			$project['is_owner'] = (string)$project['owner_id'] === (string)$this->userId;
			$members = $this->projectMembers($id);
			$project['members'] = $members;
			$shares = $this->projectShareBasisPoints($members);
			$project['member_count'] = count($members);
			$project['is_shared'] = count($members) > 1;
			$memberManagementLockReason = $this->memberManagementLockReason($id, $workspaceId, count($members));
			$project['member_management_locked'] = $memberManagementLockReason !== null;
			$project['member_management_lock_reason'] = $memberManagementLockReason;
			$project['my_share_basis_points'] = $shares[(string)$this->userId] ?? 10000;

			$balanceSnapshot = count($members) > 1
				? $this->calculateBalanceSnapshotCents($id, $workspaceId, $members)
				: [];
			$project['balances'] = $this->balanceSnapshotForResponse($balanceSnapshot);
			$project['repaymentTransfers'] = $this->transferRowsForResponse($this->calculateRepaymentTransfers($balanceSnapshot));
			$project['dashboard'] = $this->calculateProjectDashboard($id, $members, $workspaceId);

			return new DataResponse($project);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function settlements(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$project = $this->projectVisibleForCurrentUser($id);
			if (!$project) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}
			$workspaceId = (int)$project['workspace_id'];

			$project = $this->projectHeader($id, $workspaceId);
			if (!$project) {
				return new DataResponse(['error' => 'Project not found'], Http::STATUS_NOT_FOUND);
			}
			$project['members'] = $this->projectMembers($id);

			return new DataResponse([
				'project' => $project,
				'settlements' => $this->settlementHistory($id, $workspaceId, null, true),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 10, period: 60)]
	public function addMember(int $id, string $userId = ''): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				if ($validationError = $this->validateRequiredUserId($userId)) {
					return $validationError;
				}

			if (!$this->sharedProjectsEnabled()) {
				return new DataResponse(['error' => 'Gemeinsame Bereiche sind deaktiviert.'], Http::STATUS_BAD_REQUEST);
			}

			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}
			$project = $this->projectOwnerForCurrentUser($id);
			if ($project === null) {
				return $this->errorResponse('Area not found.', Http::STATUS_NOT_FOUND);
			}
			if ($this->projectHasFormerMember($id)) {
				return $this->errorResponse(
					$this->l10n->t('Remove the former member before adding members or changing the split.'),
					Http::STATUS_CONFLICT
				);
			}
			if ($this->projectHasOpenSharedPayments($id, (int)$project['workspace_id'])) {
				return $this->errorResponse('Settle the area before changing its members.', Http::STATUS_CONFLICT);
			}
			if ($this->projectHasPayments($id)) {
				return $this->errorResponse('Move or delete the personal payments in this area before adding another member.', Http::STATUS_CONFLICT);
			}

			if (!$this->userSearchAllowed()) {
				return $this->errorResponse('Adding members is disabled because Nextcloud user search is disabled.', Http::STATUS_FORBIDDEN);
			}

			if (!$this->participantService->isActive($userId)) {
				return $this->errorResponse('User could not be added.', Http::STATUS_BAD_REQUEST);
			}

			// Check if the user is already a member
			$qbCheck = $this->db->getQueryBuilder();
			$qbCheck->select('user_id')
				->from('cobudget_members')
				->where($qbCheck->expr()->eq('project_id', $qbCheck->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qbCheck->expr()->eq('user_id', $qbCheck->createNamedParameter($userId)));
			$resultCheck = $qbCheck->executeQuery();
			$alreadyMember = $resultCheck->fetch();
			$resultCheck->closeCursor();

			if ($alreadyMember) {
				return new DataResponse(['error' => 'User is already a member'], Http::STATUS_CONFLICT);
			}

			$existingMembers = $this->projectMembers($id);
			if (count($existingMembers) >= self::MAX_PROJECT_MEMBERS) {
				return $this->errorResponse('User could not be added.', Http::STATUS_BAD_REQUEST);
			}
			$rebalanceEqually = $this->projectSharesAreEqual($existingMembers);

			$this->db->beginTransaction();
			try {
				$personalWorkspaceId = $this->entryProjectionService->personalWorkspaceIdForMember($id, $userId);
				$qbInsert = $this->db->getQueryBuilder();
				$qbInsert->insert('cobudget_members')
					->values([
						'project_id' => $qbInsert->createNamedParameter($id, \PDO::PARAM_INT),
						'user_id' => $qbInsert->createNamedParameter($userId),
						'share_basis_points' => $qbInsert->createNamedParameter(0, \PDO::PARAM_INT),
						'personal_workspace_id' => $qbInsert->createNamedParameter($personalWorkspaceId, \PDO::PARAM_INT),
					]);
				$qbInsert->executeStatement();

				if ($rebalanceEqually) {
					$userIds = $this->projectMemberIds($existingMembers);
					$userIds[] = $userId;
					$this->updateProjectShareBasisPoints($id, $this->equalShareBasisPoints($userIds));
				}
				$this->entryShareService->resetRoundingBalancesForProject($id);

				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			$userObj = $this->userManager->get($userId);
			$shares = $this->projectShareBasisPoints($this->projectMembers($id));
			return new DataResponse([
				'status' => 'success',
				'member' => [
						'id' => $userId,
						'displayName' => $userObj ? $userObj->getDisplayName() : $userId,
						'shareBasisPoints' => $shares[$userId] ?? 0,
						'sharePercent' => (int)round(($shares[$userId] ?? 0) / 100),
					]
				]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeMember(int $id, string $userId = ''): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				if ($validationError = $this->validateRequiredUserId($userId)) {
					return $validationError;
				}

			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

			$project = $this->projectOwnerForCurrentUser($id);
			if (!$project) {
				return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
			}

			// Cannot remove the owner
			if ($userId === $project['owner_id']) {
				return new DataResponse(['error' => 'Cannot remove the project owner'], Http::STATUS_BAD_REQUEST);
			}
			$this->db->beginTransaction();
			try {
				$members = $this->projectMembers($id);
				if (!in_array($userId, $this->projectMemberIds($members), true)) {
					$this->db->rollBack();
					return $this->errorResponse('Area member not found.', Http::STATUS_NOT_FOUND);
				}

				$workspaceId = (int)$project['workspace_id'];
				if ($this->projectHasOpenSharedPayments($id, $workspaceId)) {
					$this->db->rollBack();
					return $this->errorResponse(
						$this->l10n->t('Settle the area before changing its members.'),
						Http::STATUS_CONFLICT
					);
				}
				$this->entryProjectionService->detachSettledMember($id, $userId);

				$qbDel = $this->db->getQueryBuilder();
				$qbDel->delete('cobudget_members')
					->where($qbDel->expr()->eq('project_id', $qbDel->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qbDel->expr()->eq('user_id', $qbDel->createNamedParameter($userId)));
				$qbDel->executeStatement();
				$this->normalizeProjectSharesToTotal($id);
				$this->entryShareService->resetRoundingBalancesForProject($id);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse(['status' => 'success']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateShares(int $id, array $shares = []): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}
			$project = $this->projectOwnerForCurrentUser($id);
			if ($project === null) {
				return $this->errorResponse('Area not found.', Http::STATUS_NOT_FOUND);
			}
			if ($this->projectHasFormerMember($id)) {
				return $this->errorResponse(
					$this->l10n->t('Remove the former member before adding members or changing the split.'),
					Http::STATUS_CONFLICT
				);
			}
			if ($this->projectHasOpenSharedPayments($id, (int)$project['workspace_id'])) {
				return $this->errorResponse('Settle the area before changing its default split.', Http::STATUS_CONFLICT);
			}

			$members = $this->projectMembers($id);
			$memberIds = array_map(static fn(array $member): string => (string)$member['id'], $members);
			$memberLookup = array_fill_keys($memberIds, true);
			$normalizedShares = [];

			foreach ($shares as $share) {
				$userId = trim((string)($share['userId'] ?? $share['id'] ?? $share['user_id'] ?? ''));
				$shareBasisPoints = (int)($share['shareBasisPoints'] ?? $share['share_basis_points'] ?? -1);
				if ($userId === '' || !isset($memberLookup[$userId])) {
					return $this->errorResponse('Invalid area member', Http::STATUS_BAD_REQUEST);
				}
				if ($shareBasisPoints < 0 || $shareBasisPoints > 10000) {
					return $this->errorResponse('Invalid share', Http::STATUS_BAD_REQUEST);
				}
				if ($shareBasisPoints % 100 !== 0) {
					return $this->errorResponse('Please use whole percentages.', Http::STATUS_BAD_REQUEST);
				}

				$normalizedShares[$userId] = $shareBasisPoints;
				}

			if (count($normalizedShares) !== count($memberIds)) {
				return $this->errorResponse('Please enter a share for each area member.', Http::STATUS_BAD_REQUEST);
			}

			if (array_sum($normalizedShares) !== 10000) {
				return $this->errorResponse('The split must add up to exactly 100%.', Http::STATUS_BAD_REQUEST);
			}

			$this->db->beginTransaction();
			try {
				foreach ($normalizedShares as $userId => $shareBasisPoints) {
					$qb = $this->db->getQueryBuilder();
					$qb->update('cobudget_members')
						->set('share_basis_points', $qb->createNamedParameter($shareBasisPoints, \PDO::PARAM_INT))
						->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
						->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
					$qb->executeStatement();
				}
				$this->entryShareService->resetRoundingBalancesForProject($id);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse([
				'status' => 'success',
				'members' => $this->projectMembers($id),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function transferOwnership(int $id, string $userId = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

			$userId = trim($userId);
			$members = $this->projectMembers($id);
			if ($userId === '' || !in_array($userId, $this->projectMemberIds($members), true)) {
				return $this->errorResponse('The new owner must be an area member.', Http::STATUS_BAD_REQUEST);
			}
			if (!$this->participantService->isActive($userId)) {
				return $this->errorResponse('A former or inactive member cannot own an area.', Http::STATUS_BAD_REQUEST);
			}

			$this->db->beginTransaction();
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_projects')
					->set('owner_id', $qb->createNamedParameter($userId))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('owner_id', $qb->createNamedParameter((string)$this->userId)));
				if ($qb->executeStatement() !== 1) {
					throw new \RuntimeException('Area ownership changed concurrently.');
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse([
				'status' => 'success',
				'owner_id' => $userId,
				'members' => $this->projectMembers($id),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function settle(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

			$project = $this->projectOwnerForCurrentUser($id);
			if (!$project) {
				return $this->errorResponse('Nur der Ersteller des Bereichs darf diese Aktion ausführen.', Http::STATUS_FORBIDDEN);
			}
			$workspaceId = (int)$project['workspace_id'];
			$members = $this->projectMembers($id);
			if (count($members) <= 1) {
				return $this->errorResponse('An area with one member does not need to be settled.', Http::STATUS_BAD_REQUEST);
			}
			$balanceSnapshot = $this->calculateBalanceSnapshotCents($id, $workspaceId, $members);
			$transfers = $this->calculateRepaymentTransfers($balanceSnapshot);
			$entryIds = $this->unsettledProjectEntryIds($id, $workspaceId);
			if ($entryIds === []) {
				return $this->errorResponse('There are no open payments to settle.', Http::STATUS_BAD_REQUEST);
			}

			$createdAt = time();
			$currency = $this->config->getUserValue((string)$this->userId, 'cobudget', 'currency', 'EUR');
			$settlementNotifications = $this->projectNotificationService->prepareSettlementNotifications($id, $workspaceId, (string)$this->userId);
			$this->db->beginTransaction();
			try {
				$qbSettlement = $this->db->getQueryBuilder();
				$qbSettlement->insert('cobudget_settlements')
					->values([
						'project_id' => $qbSettlement->createNamedParameter($id, \PDO::PARAM_INT),
						'workspace_id' => $qbSettlement->createNamedParameter($workspaceId, \PDO::PARAM_INT),
						'created_by' => $qbSettlement->createNamedParameter((string)$this->userId),
						'created_at' => $qbSettlement->createNamedParameter($createdAt, \PDO::PARAM_INT),
						'currency' => $qbSettlement->createNamedParameter($currency),
					]);
				$qbSettlement->executeStatement();
				$settlementId = (int)$this->db->lastInsertId('*PREFIX*cobudget_settlements');

				foreach ($balanceSnapshot as $balance) {
					$qbBalance = $this->db->getQueryBuilder();
					$qbBalance->insert('cobudget_settlement_balances')
						->values([
							'settlement_id' => $qbBalance->createNamedParameter($settlementId, \PDO::PARAM_INT),
							'user_id' => $qbBalance->createNamedParameter($balance['userId']),
							'display_name' => $qbBalance->createNamedParameter($balance['displayName']),
							'paid_cents' => $qbBalance->createNamedParameter($balance['paidCents'], \PDO::PARAM_INT),
							'share_cents' => $qbBalance->createNamedParameter($balance['fairShareCents'], \PDO::PARAM_INT),
							'received_cents' => $qbBalance->createNamedParameter($balance['receivedCents'], \PDO::PARAM_INT),
							'income_share_cents' => $qbBalance->createNamedParameter($balance['incomeShareCents'], \PDO::PARAM_INT),
							'balance_cents' => $qbBalance->createNamedParameter($balance['balanceCents'], \PDO::PARAM_INT),
							'share_basis_points' => $qbBalance->createNamedParameter($balance['shareBasisPoints'], \PDO::PARAM_INT),
						]);
					$qbBalance->executeStatement();
				}

				foreach ($transfers as $transfer) {
					$qbTransfer = $this->db->getQueryBuilder();
					$qbTransfer->insert('cobudget_settlement_transfers')
						->values([
							'settlement_id' => $qbTransfer->createNamedParameter($settlementId, \PDO::PARAM_INT),
							'from_user_id' => $qbTransfer->createNamedParameter($transfer['fromUserId']),
							'from_display_name' => $qbTransfer->createNamedParameter($transfer['fromDisplayName']),
							'to_user_id' => $qbTransfer->createNamedParameter($transfer['toUserId']),
							'to_display_name' => $qbTransfer->createNamedParameter($transfer['toDisplayName']),
							'amount_cents' => $qbTransfer->createNamedParameter($transfer['amountCents'], \PDO::PARAM_INT),
						]);
					$qbTransfer->executeStatement();
				}

				$this->entryProjectionService->unlockForSettlement($entryIds, $settlementId, $createdAt);

				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_entries')
					->set('is_settled', $qb->createNamedParameter(true, \PDO::PARAM_BOOL))
					->set('settled_at', $qb->createNamedParameter($createdAt, \PDO::PARAM_INT))
					->set('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT))
					->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
					->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
				$qb->executeStatement();

				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
			$this->projectNotificationService->sendPreparedNotifications($settlementNotifications);

			return new DataResponse([
				'status' => 'success',
				'settlement' => [
					'id' => $settlementId,
					'createdAt' => $createdAt,
					'entryCount' => count($entryIds),
					'balances' => $this->balanceSnapshotForResponse($balanceSnapshot),
					'transfers' => $this->transferRowsForResponse($transfers),
				],
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * Calculate balances for a project.
	 * Each member's fair share follows the configured area split unless an entry is assigned to one user only.
	 * Balance = expenses paid - expense share + income share - income received
	 * Positive = they are owed money, Negative = they owe money
	 */
	private function calculateBalances(int $projectId, array $members): array {
		$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
		if ($workspaceId === null) {
			return [];
		}

		return $this->balanceSnapshotForResponse(
			$this->calculateBalanceSnapshotCents($projectId, $workspaceId, $members)
		);
	}

	private function calculateBalanceSnapshotCents(int $projectId, int $workspaceId, array $members): array {
		if ($members === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'project_id', 'amount', 'amount_cents', 'type', 'split_mode', 'split_user_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();
		$entryShares = $this->entryShareService->sharesForEntries(array_column($entries, 'id'));

		$paid = [];
		$fairShare = [];
		$received = [];
		$incomeShare = [];
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId === '') {
				continue;
			}
			$paid[$userId] = 0;
			$fairShare[$userId] = 0;
			$received[$userId] = 0;
			$incomeShare[$userId] = 0;
		}
		$shares = $this->projectShareBasisPoints($members);

		foreach ($entries as $entry) {
			$amountCents = $this->amountCentsFromRow($entry) ?? 0;
			$entryUserId = (string)($entry['user_id'] ?? '');
			$isIncome = ($entry['type'] ?? '') === 'income';
			if ($isIncome && isset($received[$entryUserId])) {
				$received[$entryUserId] += $amountCents;
			} elseif (!$isIncome && isset($paid[$entryUserId])) {
				$paid[$entryUserId] += $amountCents;
			}

			$entryId = (int)($entry['id'] ?? 0);
			if (isset($entryShares[$entryId])) {
				foreach ($entryShares[$entryId] as $userId => $allocation) {
					if ($isIncome && isset($incomeShare[$userId])) {
						$incomeShare[$userId] += (int)$allocation['amount_cents'];
					} elseif (!$isIncome && isset($fairShare[$userId])) {
						$fairShare[$userId] += (int)$allocation['amount_cents'];
					}
				}
				continue;
			}

			if ($this->normalizeSplitMode($entry['split_mode'] ?? null) === 'single_user') {
				$splitTargetUserId = $this->entrySplitTargetUserId($entry);
				if ($isIncome && isset($incomeShare[$splitTargetUserId])) {
					$incomeShare[$splitTargetUserId] += $amountCents;
				} elseif (!$isIncome && isset($fairShare[$splitTargetUserId])) {
					$fairShare[$splitTargetUserId] += $amountCents;
				}
				continue;
			}

			foreach ($this->distributeAmountCents($amountCents, $shares) as $userId => $shareCents) {
				if ($isIncome && isset($incomeShare[$userId])) {
					$incomeShare[$userId] += $shareCents;
				} elseif (!$isIncome && isset($fairShare[$userId])) {
					$fairShare[$userId] += $shareCents;
				}
			}
		}

		$balances = [];
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId === '') {
				continue;
			}

			$memberPaidCents = (int)($paid[$userId] ?? 0);
			$memberShareCents = (int)($fairShare[$userId] ?? 0);
			$memberReceivedCents = (int)($received[$userId] ?? 0);
			$memberIncomeShareCents = (int)($incomeShare[$userId] ?? 0);
			$shareBasisPoints = (int)($member['shareBasisPoints'] ?? ($member['share_basis_points'] ?? 0));
			$balances[] = [
				'userId' => $userId,
				'displayName' => (string)($member['displayName'] ?? $userId),
				'shareBasisPoints' => $shareBasisPoints,
				'sharePercent' => (int)round($shareBasisPoints / 100),
				'paidCents' => $memberPaidCents,
				'fairShareCents' => $memberShareCents,
				'receivedCents' => $memberReceivedCents,
				'incomeShareCents' => $memberIncomeShareCents,
				'balanceCents' => $memberPaidCents - $memberShareCents + $memberIncomeShareCents - $memberReceivedCents,
			];
		}

		return $balances;
	}

	private function balanceSnapshotForResponse(array $balances): array {
		return array_map(static function (array $balance): array {
			$paidCents = (int)($balance['paidCents'] ?? 0);
			$fairShareCents = (int)($balance['fairShareCents'] ?? ($balance['shareCents'] ?? 0));
			$receivedCents = (int)($balance['receivedCents'] ?? 0);
			$incomeShareCents = (int)($balance['incomeShareCents'] ?? 0);
			$balanceCents = (int)($balance['balanceCents'] ?? 0);

			return [
				'userId' => (string)$balance['userId'],
				'displayName' => (string)$balance['displayName'],
				'shareBasisPoints' => (int)($balance['shareBasisPoints'] ?? 0),
				'sharePercent' => (int)($balance['sharePercent'] ?? round(((int)($balance['shareBasisPoints'] ?? 0)) / 100)),
				'paidCents' => $paidCents,
				'fairShareCents' => $fairShareCents,
				'receivedCents' => $receivedCents,
				'incomeShareCents' => $incomeShareCents,
				'balanceCents' => $balanceCents,
				'paid' => round($paidCents / 100, 2),
				'fairShare' => round($fairShareCents / 100, 2),
				'received' => round($receivedCents / 100, 2),
				'incomeShare' => round($incomeShareCents / 100, 2),
				'balance' => round($balanceCents / 100, 2),
			];
		}, $balances);
	}

	private function calculateRepaymentTransfers(array $balances): array {
		$creditors = [];
		$debtors = [];
		foreach ($balances as $balance) {
			$balanceCents = (int)($balance['balanceCents'] ?? 0);
			if ($balanceCents > 0) {
				$balance['remainingCents'] = $balanceCents;
				$creditors[] = $balance;
			} elseif ($balanceCents < 0) {
				$balance['remainingCents'] = abs($balanceCents);
				$debtors[] = $balance;
			}
		}

		usort($creditors, static fn(array $a, array $b): int => (int)$b['remainingCents'] <=> (int)$a['remainingCents']);
		usort($debtors, static fn(array $a, array $b): int => (int)$b['remainingCents'] <=> (int)$a['remainingCents']);

		$transfers = [];
		$debtorIndex = 0;
		$creditorIndex = 0;
		while (isset($debtors[$debtorIndex], $creditors[$creditorIndex])) {
			$amountCents = min((int)$debtors[$debtorIndex]['remainingCents'], (int)$creditors[$creditorIndex]['remainingCents']);
			if ($amountCents > 0) {
				$transfers[] = [
					'fromUserId' => (string)$debtors[$debtorIndex]['userId'],
					'fromDisplayName' => (string)$debtors[$debtorIndex]['displayName'],
					'toUserId' => (string)$creditors[$creditorIndex]['userId'],
					'toDisplayName' => (string)$creditors[$creditorIndex]['displayName'],
					'amountCents' => $amountCents,
				];
			}

			$debtors[$debtorIndex]['remainingCents'] -= $amountCents;
			$creditors[$creditorIndex]['remainingCents'] -= $amountCents;
			if ((int)$debtors[$debtorIndex]['remainingCents'] <= 0) {
				$debtorIndex++;
			}
			if ((int)$creditors[$creditorIndex]['remainingCents'] <= 0) {
				$creditorIndex++;
			}
		}

		return $transfers;
	}

	private function transferRowsForResponse(array $transfers): array {
		return array_map(static function (array $transfer): array {
			$amountCents = (int)($transfer['amountCents'] ?? 0);
			return [
				'fromUserId' => (string)$transfer['fromUserId'],
				'fromDisplayName' => (string)$transfer['fromDisplayName'],
				'toUserId' => (string)$transfer['toUserId'],
				'toDisplayName' => (string)$transfer['toDisplayName'],
				'amountCents' => $amountCents,
				'amount' => round($amountCents / 100, 2),
			];
		}, $transfers);
	}

	private function unsettledProjectEntryIds(int $projectId, int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();

		return $ids;
	}

	private function projectHeader(int $projectId, int $workspaceId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'color', 'owner_id', 'is_archived')
			->from('cobudget_projects')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$project = $result->fetch();
		$result->closeCursor();

		return $project ?: null;
	}

	private function settlementHistory(int $projectId, int $workspaceId, ?int $limit = 10, bool $includeEntries = false): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_settlements')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		$result = $qb->executeQuery();
		$settlements = $result->fetchAll();
		$result->closeCursor();

		$history = [];
		foreach ($settlements as $settlement) {
			$settlementId = (int)$settlement['id'];
			$item = [
				'id' => $settlementId,
				'createdAt' => (int)$settlement['created_at'],
				'createdBy' => (string)$settlement['created_by'],
				'createdByDisplayName' => $this->displayNameForUser((string)$settlement['created_by']),
				'currency' => (string)$settlement['currency'],
				'entryCount' => $this->settlementEntryCount($settlementId),
				'balances' => $this->loadSettlementBalances($settlementId),
				'transfers' => $this->loadSettlementTransfers($settlementId),
			];
			if ($includeEntries) {
				$item['entries'] = $this->loadSettlementEntries($settlementId, $projectId, $workspaceId);
			}
			$history[] = $item;
		}

		return $history;
	}

	private function loadSettlementBalances(int $settlementId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_settlement_balances')
			->where($qb->expr()->eq('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$balances = [];
		foreach ($rows as $row) {
			$shareBasisPoints = (int)$row['share_basis_points'];
			$balances[] = [
				'userId' => (string)$row['user_id'],
				'displayName' => (string)$row['display_name'],
				'shareBasisPoints' => $shareBasisPoints,
				'sharePercent' => (int)round($shareBasisPoints / 100),
				'paidCents' => (int)$row['paid_cents'],
				'fairShareCents' => (int)$row['share_cents'],
				'receivedCents' => (int)($row['received_cents'] ?? 0),
				'incomeShareCents' => (int)($row['income_share_cents'] ?? 0),
				'balanceCents' => (int)$row['balance_cents'],
			];
		}

		return $this->balanceSnapshotForResponse($balances);
	}

	private function loadSettlementTransfers(int $settlementId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_settlement_transfers')
			->where($qb->expr()->eq('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$transfers = [];
		foreach ($rows as $row) {
			$transfers[] = [
				'fromUserId' => (string)$row['from_user_id'],
				'fromDisplayName' => (string)$row['from_display_name'],
				'toUserId' => (string)$row['to_user_id'],
				'toDisplayName' => (string)$row['to_display_name'],
				'amountCents' => (int)$row['amount_cents'],
			];
		}

		return $this->transferRowsForResponse($transfers);
	}

	private function settlementEntryCount(int $settlementId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')));
		$result = $qb->executeQuery();
		$count = count($result->fetchAll());
		$result->closeCursor();

		return $count;
	}

	private function loadSettlementEntries(int $settlementId, int $projectId, int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*', 'c.name AS category_name', 'c.icon AS category_icon', 'p.name AS paymentPartner')
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->where($qb->expr()->eq('e.settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('shared')))
			->orderBy('e.date', 'DESC')
			->addOrderBy('e.id', 'DESC');
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		$entries = $this->entryShareService->attachPersonalShares($entries, (string)$this->userId);
		$entries = array_map(fn(array $entry): array => $this->normalizeProjectEntryRow($entry), $entries);
		return $this->attachEntryAttachmentCounts($entries, $workspaceId);
	}

	private function attachEntryAttachmentCounts(array $entries, int $workspaceId): array {
		if ($entries === []) {
			return $entries;
		}

		$ids = array_values(array_filter(array_map(static fn(array $entry): int => (int)($entry['id'] ?? 0), $entries)));
		if ($ids === []) {
			return $entries;
		}

		$qb = $this->db->getQueryBuilder();
		$idFilter = $qb->expr()->orX();
		foreach ($ids as $entryId) {
			$idFilter->add($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		}

		$qb->select('entry_id')
			->selectAlias($qb->func()->count('*'), 'attachment_count')
			->from('cobudget_entry_attachments')
			->where($idFilter)
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->groupBy('entry_id');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$counts = [];
		foreach ($rows as $row) {
			$counts[(int)$row['entry_id']] = (int)$row['attachment_count'];
		}

		return array_map(static function(array $entry) use ($counts): array {
			$entry['attachments_count'] = $counts[(int)($entry['id'] ?? 0)] ?? 0;
			return $entry;
		}, $entries);
	}

	private function normalizeProjectEntryRow(array $entry): array {
		$entry = $this->normalizeAmountRow($entry);
		foreach (['is_subscription', 'is_fixed_cost', 'is_child_related', 'is_important', 'needs_review', 'is_tax_relevant', 'is_settled', 'reminder_notified'] as $key) {
			if (array_key_exists($key, $entry)) {
				$entry[$key] = $this->projectDashboardBool($entry[$key]);
			}
		}

		if (!empty($entry['user_id'])) {
			$userId = (string)$entry['user_id'];
			$entry['user_display_name'] = $this->displayNameForUser($userId);
		}

		return $entry;
	}

	private function displayNameForUser(string $userId): string {
		return $this->participantService->displayName($userId);
	}

	private function calculateProjectDashboard(int $projectId, array $members, int $workspaceId): array {
		$metrics = $this->emptyProjectDashboard();
		$now = time();
		$shares = $this->projectShareBasisPoints($members);
		$currentUserId = (string)$this->userId;
		$isSharedArea = count($members) > 1;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'project_id', 'type', 'amount', 'amount_cents', 'split_mode', 'split_user_id', 'is_settled', 'is_subscription', 'is_fixed_cost', 'is_child_related', 'is_important', 'needs_review', 'is_tax_relevant')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($now, \PDO::PARAM_INT)));
		if ($isSharedArea) {
			$qb->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')));
		} else {
			$qb->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('personal')))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($currentUserId)));
		}

		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();
		$entries = $this->entryShareService->attachPersonalShares($entries, $currentUserId);

		foreach ($entries as $entry) {
			$type = (string)($entry['type'] ?? '');
			if ($type !== 'income' && $type !== 'expense') {
				continue;
			}

			$amountCents = (float)($this->amountCentsFromRow($entry) ?? 0);
			$personalCents = $isSharedArea
				? $this->storedOrCalculatedShareCents($entry, $currentUserId, (int)$amountCents, $shares)
				: (int)$amountCents;
			$isActive = !$isSharedArea || !$this->projectDashboardBool($entry['is_settled'] ?? false);

			if ($type === 'income') {
				$this->addProjectDashboardMetric($metrics, 'income', $amountCents, $personalCents, $isActive);
				$this->addProjectDashboardMetric($metrics, 'balance', $amountCents, $personalCents, $isActive);
				$signedAmountCents = $amountCents;
				$signedPersonalCents = $personalCents;
			} else {
				$this->addProjectDashboardMetric($metrics, 'expense', $amountCents, $personalCents, $isActive);
				$this->addProjectDashboardMetric($metrics, 'balance', -$amountCents, -$personalCents, $isActive);
				$signedAmountCents = -$amountCents;
				$signedPersonalCents = -$personalCents;

				if ($this->projectDashboardBool($entry['is_subscription'] ?? false)) {
					$this->addProjectDashboardMetric($metrics, 'subscriptions', $amountCents, $personalCents, $isActive);
				}
				if ($this->projectDashboardBool($entry['is_fixed_cost'] ?? false)) {
					$this->addProjectDashboardMetric($metrics, 'fixedCosts', $amountCents, $personalCents, $isActive);
				}
			}

			if ($this->projectDashboardBool($entry['is_important'] ?? false)) {
				$this->addProjectDashboardMetric($metrics, 'important', $signedAmountCents, $signedPersonalCents, $isActive);
			}
			if ($this->projectDashboardBool($entry['needs_review'] ?? false)) {
				$this->addProjectDashboardMetric($metrics, 'review', $signedAmountCents, $signedPersonalCents, $isActive);
			}
			if ($this->projectDashboardBool($entry['is_child_related'] ?? false)) {
				$this->addProjectDashboardMetric($metrics, 'childRelated', $signedAmountCents, $signedPersonalCents, $isActive);
			}
			if ($this->projectDashboardBool($entry['is_tax_relevant'] ?? false)) {
				$this->addProjectDashboardMetric($metrics, 'taxRelevant', $signedAmountCents, $signedPersonalCents, $isActive);
			}
		}

		return $this->normalizeProjectDashboard($metrics);
	}

	private function emptyProjectDashboard(): array {
		$metrics = [];
		foreach (['income', 'expense', 'balance', 'important', 'review', 'fixedCosts', 'childRelated', 'subscriptions', 'taxRelevant'] as $key) {
			$metrics[$key] = [
				'activeTotalCents' => 0.0,
				'activePersonalCents' => 0.0,
				'allTotalCents' => 0.0,
				'allPersonalCents' => 0.0,
				'activeCount' => 0,
				'allCount' => 0,
			];
		}

		return $metrics;
	}

	private function addProjectDashboardMetric(array &$metrics, string $key, float $totalCents, float $personalCents, bool $isActive): void {
		$metrics[$key]['allTotalCents'] += $totalCents;
		$metrics[$key]['allPersonalCents'] += $personalCents;
		$metrics[$key]['allCount']++;

		if ($isActive) {
			$metrics[$key]['activeTotalCents'] += $totalCents;
			$metrics[$key]['activePersonalCents'] += $personalCents;
			$metrics[$key]['activeCount']++;
		}
	}

	private function normalizeProjectDashboard(array $metrics): array {
		$normalized = [];
		foreach ($metrics as $key => $metric) {
			$normalized[$key] = [
				'activeTotal' => round($metric['activeTotalCents'] / 100, 2),
				'activePersonal' => round($metric['activePersonalCents'] / 100, 2),
				'allTotal' => round($metric['allTotalCents'] / 100, 2),
				'allPersonal' => round($metric['allPersonalCents'] / 100, 2),
				'activeCount' => (int)$metric['activeCount'],
				'allCount' => (int)$metric['allCount'],
			];
		}

		return $normalized;
	}

	private function storedOrCalculatedShareCents(array $entry, string $userId, int $amountCents, array $fallbackShares): int {
		if (array_key_exists('snapshot_share_cents', $entry)) {
			return max(0, (int)$entry['snapshot_share_cents']);
		}

		return $this->entryShareCentsForUser($entry, $userId, $amountCents, $fallbackShares);
	}

	private function projectDashboardBool($value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}

<?php
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\ProjectNotificationService;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IL10N;

class ProjectController extends Controller {
	use WorkspaceAwareTrait;

	private IDBConnection $db;
	private ?string $userId;
	private IUserManager $userManager;
	private IConfig $config;
	private ProjectNotificationService $projectNotificationService;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IConfig $config, ProjectNotificationService $projectNotificationService, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->projectNotificationService = $projectNotificationService;
		$this->l10n = $l10n;
		$this->initWorkspace();
	}

	private function sharedProjectsEnabled(): bool {
		return $this->config->getUserValue($this->userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes';
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

		$shares = $this->memberShareBasisPoints($rows);
		$members = [];
		foreach ($rows as $row) {
			$userId = (string)$row['user_id'];
			$userObj = $this->userManager->get($userId);
			$shareBasisPoints = $shares[$userId] ?? 0;
			$members[] = [
				'id' => $userId,
				'user_id' => $userId,
				'displayName' => $userObj ? $userObj->getDisplayName() : $userId,
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

	private function calculatePersonalBalance(int $projectId, int $workspaceId, string $userId, array $members): float {
		$shares = $this->projectShareBasisPoints($members);
		if ($shares === []) {
			return 0.0;
		}

		$qbEntries = $this->db->getQueryBuilder();
		$qbEntries->select('user_id', 'amount', 'amount_cents', 'type', 'split_mode')
			->from('cobudget_entries')
			->where($qbEntries->expr()->eq('project_id', $qbEntries->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qbEntries->expr()->eq('workspace_id', $qbEntries->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qbEntries->expr()->eq('is_settled', $qbEntries->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$resultEntries = $qbEntries->executeQuery();
		$entries = $resultEntries->fetchAll();
		$resultEntries->closeCursor();

		$paidByUserCents = 0;
		$shareForUserCents = 0;
		foreach ($entries as $entry) {
			if (($entry['type'] ?? '') !== 'expense') {
				continue;
			}

			$amountCents = $this->amountCentsFromRow($entry) ?? 0;
			if (($entry['user_id'] ?? null) === $userId) {
				$paidByUserCents += $amountCents;
			}
			$shareForUserCents += $this->entryShareCentsForUser($entry, $userId, $amountCents, $shares);
		}

		return round(($paidByUserCents - $shareForUserCents) / 100, 2);
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();

			$qb = $this->db->getQueryBuilder();
			$qb->select('p.*')
				->from('cobudget_projects', 'p')
				->innerJoin('p', 'cobudget_members', 'm',
					$qb->expr()->eq('p.id', 'm.project_id'))
				->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)))
				->andWhere($qb->expr()->eq('p.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));

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
				$project['my_share_basis_points'] = $shares[(string)$this->userId] ?? 10000;
				$project['personal_balance'] = $this->calculatePersonalBalance($projectId, $workspaceId, (string)$this->userId, $members);
			}

			return new DataResponse($projects);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
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

			foreach ($members as $memberId) {
				if ($this->userManager->get($memberId) === null) {
					return new DataResponse(['error' => 'User not found: ' . $memberId], Http::STATUS_BAD_REQUEST);
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
					$qbMem = $this->db->getQueryBuilder();
					$qbMem->insert('cobudget_members')
						->values([
							'project_id' => $qbMem->createNamedParameter($projectId, \PDO::PARAM_INT),
							'user_id' => $qbMem->createNamedParameter($memberId),
							'share_basis_points' => $qbMem->createNamedParameter($shares[$memberId] ?? 0, \PDO::PARAM_INT),
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

			$workspaceId = $this->getWorkspaceId();
			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

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

			$workspaceId = $this->getWorkspaceId();

			// Check for entries
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')->from('cobudget_entries')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$hasEntries = $result->fetch();
			$result->closeCursor();

			if ($hasEntries) {
				return $this->errorResponse('Area cannot be deleted because it still has expenses.', Http::STATUS_BAD_REQUEST);
			}

			$this->db->beginTransaction();
			try {
				$settlementIds = $this->settlementIdsForProject($id);

				$this->deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', $settlementIds);
				$this->deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $settlementIds);
				$this->deleteRowsByColumnValues('cobudget_settlements', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_categories', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_payment_partners', 'project_id', [$id]);
				$this->deleteRowsByColumnValues('cobudget_templates', 'project_id', [$id]);

				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_members')
					->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
				$qb->executeStatement();

				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_projects')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
				$qb->executeStatement();

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

			$workspaceId = $this->getWorkspaceId();
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

			$workspaceId = $this->getWorkspaceId();
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

			$workspaceId = $this->getWorkspaceId();
			if (!$this->projectMemberInActiveWorkspace($id)) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}

			// Get project details
			$qbProj = $this->db->getQueryBuilder();
			$qbProj->select('*')
				->from('cobudget_projects')
				->where($qbProj->expr()->eq('id', $qbProj->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qbProj->expr()->eq('workspace_id', $qbProj->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$resultProj = $qbProj->executeQuery();
			$project = $resultProj->fetch();
			$resultProj->closeCursor();

			if (!$project) {
				return new DataResponse(['error' => 'Project not found'], Http::STATUS_NOT_FOUND);
			}

			$project['is_owner'] = (string)$project['owner_id'] === (string)$this->userId;
			$members = $this->projectMembers($id);
			$project['members'] = $members;
			$shares = $this->projectShareBasisPoints($members);
			$project['member_count'] = count($members);
			$project['my_share_basis_points'] = $shares[(string)$this->userId] ?? 10000;

			$balanceSnapshot = $this->calculateBalanceSnapshotCents($id, $workspaceId, $members);
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

			$workspaceId = $this->getWorkspaceId();
			if (!$this->projectMemberInActiveWorkspace($id)) {
				return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
			}

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

			if ($this->userManager->get($userId) === null) {
				return new DataResponse(['error' => 'User not found'], Http::STATUS_BAD_REQUEST);
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
			$rebalanceEqually = $this->projectSharesAreEqual($existingMembers);

			$this->db->beginTransaction();
			try {
				$qbInsert = $this->db->getQueryBuilder();
				$qbInsert->insert('cobudget_members')
					->values([
						'project_id' => $qbInsert->createNamedParameter($id, \PDO::PARAM_INT),
						'user_id' => $qbInsert->createNamedParameter($userId),
						'share_basis_points' => $qbInsert->createNamedParameter(0, \PDO::PARAM_INT),
					]);
				$qbInsert->executeStatement();

				if ($rebalanceEqually) {
					$userIds = $this->projectMemberIds($existingMembers);
					$userIds[] = $userId;
					$this->updateProjectShareBasisPoints($id, $this->equalShareBasisPoints($userIds));
				}

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

			$workspaceId = $this->getWorkspaceId();
			if ($ownerError = $this->requireProjectOwner($id)) {
				return $ownerError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('owner_id')
				->from('cobudget_projects')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$result = $qb->executeQuery();
			$project = $result->fetch();
			$result->closeCursor();

			// Cannot remove the owner
			if ($userId === $project['owner_id']) {
				return new DataResponse(['error' => 'Cannot remove the project owner'], Http::STATUS_BAD_REQUEST);
			}

			$this->db->beginTransaction();
			try {
				$qbDel = $this->db->getQueryBuilder();
				$qbDel->delete('cobudget_members')
					->where($qbDel->expr()->eq('project_id', $qbDel->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qbDel->expr()->eq('user_id', $qbDel->createNamedParameter($userId)));
				$qbDel->executeStatement();
				$this->normalizeProjectSharesToTotal($id);
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

			$workspaceId = $this->getWorkspaceId();
			$members = $this->projectMembers($id);
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

				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_entries')
					->set('is_settled', $qb->createNamedParameter(true, \PDO::PARAM_BOOL))
					->set('settled_at', $qb->createNamedParameter($createdAt, \PDO::PARAM_INT))
					->set('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT))
					->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
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
	 * Balance = what they paid - their fair share
	 * Positive = they are owed money, Negative = they owe money
	 */
	private function calculateBalances(int $projectId, array $members): array {
		return $this->balanceSnapshotForResponse(
			$this->calculateBalanceSnapshotCents($projectId, $this->getWorkspaceId(), $members)
		);
	}

	private function calculateBalanceSnapshotCents(int $projectId, int $workspaceId, array $members): array {
		if ($members === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'amount', 'amount_cents', 'type', 'split_mode')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		$paid = [];
		$fairShare = [];
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId === '') {
				continue;
			}
			$paid[$userId] = 0;
			$fairShare[$userId] = 0;
		}
		$shares = $this->projectShareBasisPoints($members);

		foreach ($entries as $entry) {
			if (($entry['type'] ?? '') !== 'expense') {
				continue;
			}

			$amountCents = $this->amountCentsFromRow($entry) ?? 0;
			$entryUserId = (string)($entry['user_id'] ?? '');
			if (isset($paid[$entryUserId])) {
				$paid[$entryUserId] += $amountCents;
			}

			if ($this->normalizeSplitMode($entry['split_mode'] ?? null) === 'single_user') {
				if (isset($fairShare[$entryUserId])) {
					$fairShare[$entryUserId] += $amountCents;
				}
				continue;
			}

			foreach ($this->distributeAmountCents($amountCents, $shares) as $userId => $shareCents) {
				if (isset($fairShare[$userId])) {
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
			$shareBasisPoints = (int)($member['shareBasisPoints'] ?? ($member['share_basis_points'] ?? 0));
			$balances[] = [
				'userId' => $userId,
				'displayName' => (string)($member['displayName'] ?? $userId),
				'shareBasisPoints' => $shareBasisPoints,
				'sharePercent' => (int)round($shareBasisPoints / 100),
				'paidCents' => $memberPaidCents,
				'fairShareCents' => $memberShareCents,
				'balanceCents' => $memberPaidCents - $memberShareCents,
			];
		}

		return $balances;
	}

	private function balanceSnapshotForResponse(array $balances): array {
		return array_map(static function (array $balance): array {
			$paidCents = (int)($balance['paidCents'] ?? 0);
			$fairShareCents = (int)($balance['fairShareCents'] ?? ($balance['shareCents'] ?? 0));
			$balanceCents = (int)($balance['balanceCents'] ?? 0);

			return [
				'userId' => (string)$balance['userId'],
				'displayName' => (string)$balance['displayName'],
				'shareBasisPoints' => (int)($balance['shareBasisPoints'] ?? 0),
				'sharePercent' => (int)($balance['sharePercent'] ?? round(((int)($balance['shareBasisPoints'] ?? 0)) / 100)),
				'paidCents' => $paidCents,
				'fairShareCents' => $fairShareCents,
				'balanceCents' => $balanceCents,
				'paid' => round($paidCents / 100, 2),
				'fairShare' => round($fairShareCents / 100, 2),
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
			->where($qb->expr()->eq('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)));
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
			->orderBy('e.date', 'DESC')
			->addOrderBy('e.id', 'DESC');
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

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
		$user = $this->userManager->get($userId);
		return $user ? $user->getDisplayName() : $userId;
	}

	private function calculateProjectDashboard(int $projectId, array $members, int $workspaceId): array {
		$metrics = $this->emptyProjectDashboard();
		$now = time();
		$shares = $this->projectShareBasisPoints($members);
		$currentUserId = (string)$this->userId;

		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'type', 'amount', 'amount_cents', 'split_mode', 'is_settled', 'is_subscription', 'is_fixed_cost', 'is_child_related', 'is_important', 'needs_review', 'is_tax_relevant')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($now, \PDO::PARAM_INT)));

		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		foreach ($entries as $entry) {
			$type = (string)($entry['type'] ?? '');
			if ($type !== 'income' && $type !== 'expense') {
				continue;
			}

			$amountCents = (float)($this->amountCentsFromRow($entry) ?? 0);
			$personalCents = $this->entryShareCentsForUser($entry, $currentUserId, (int)$amountCents, $shares);
			$isActive = !$this->projectDashboardBool($entry['is_settled'] ?? false);

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

	private function projectDashboardBool($value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}

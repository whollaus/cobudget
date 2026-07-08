<?php

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\HashtagService;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IUserSession;

class WorkspaceController extends Controller {
	use WorkspaceAwareTrait;

	private IDBConnection $db;
	private ?string $userId;
	private IConfig $config;
	private HashtagService $hashtagService;

	public function __construct(
		string $appName,
		IRequest $request,
		IDBConnection $db,
		IUserSession $userSession,
		IConfig $config,
		HashtagService $hashtagService
	) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->hashtagService = $hashtagService;
		$this->initWorkspace();
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$this->getWorkspaceId();
			return new DataResponse($this->getWorkspaceRows());
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $name = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validateRequiredName($name, 'Name cannot be empty')) {
				return $validationError;
			}

			if ($this->workspaceNameExists($name)) {
				return new DataResponse(['error' => 'Ein Workspace mit diesem Namen existiert bereits.'], Http::STATUS_CONFLICT);
			}

			$now = time();
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_workspaces')
			   ->values([
				   'name' => $qb->createNamedParameter($name),
				   'user_id' => $qb->createNamedParameter($this->userId),
				   'is_default' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
				   'created_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT)
			   ]);
			$qb->executeStatement();
			
			$id = $this->findNewestWorkspaceId($name, $now);
			$workspace = $id > 0 ? $this->findWorkspaceById($id) : null;

			return new DataResponse([
				'status' => 'success',
				'workspace' => $workspace ?: [
					'id' => $id,
					'name' => $name,
					'user_id' => $this->userId,
					'is_default' => false,
					'created_at' => $now
				],
				'workspaces' => $this->getWorkspaceRows()
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $name = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredName($name, 'Name cannot be empty')) {
				return $validationError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'is_default')
			   ->from('cobudget_workspaces')
			   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
			$existing = $qb->executeQuery()->fetch();

			if (!$existing) {
				return new DataResponse(['error' => 'Workspace not found'], Http::STATUS_NOT_FOUND);
			}

			if ($this->workspaceNameExists($name, $id)) {
				return new DataResponse(['error' => 'Ein Workspace mit diesem Namen existiert bereits.'], Http::STATUS_CONFLICT);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_workspaces')
			   ->set('name', $qb->createNamedParameter($name))
			   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
			$qb->executeStatement();

			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
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

			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'is_default')
			   ->from('cobudget_workspaces')
			   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
			$existing = $qb->executeQuery()->fetch();

			if (!$existing) {
				return new DataResponse(['error' => 'Workspace not found'], Http::STATUS_NOT_FOUND);
			}

			if ($existing['is_default']) {
				return new DataResponse(['error' => 'Cannot delete the default workspace'], Http::STATUS_BAD_REQUEST);
			}

			$this->db->beginTransaction();
			try {
				$projectIds = $this->ownedProjectIdsInWorkspace($id);
				if ($this->workspaceDeleteHasSharedProjectData($projectIds)) {
					$this->db->rollBack();
					return new DataResponse(['error' => 'Workspace contains shared areas and cannot be deleted automatically.'], Http::STATUS_CONFLICT);
				}
				$entryIds = $this->entryIdsForWorkspaceDelete($id, $projectIds);
				$settlementIds = $this->idsByColumnValues('cobudget_settlements', 'project_id', $projectIds);

				$this->deleteRowsByColumnValues('cobudget_entry_attachments', 'entry_id', $entryIds);
				$this->deleteRowsByWorkspaceAndUser('cobudget_entry_attachments', 'owner_user_id', $id);
				$this->hashtagService->deleteHashtagsForEntries($entryIds);
				$this->hashtagService->deleteWorkspaceHashtags($id);
				$this->deleteRowsByColumnValues('cobudget_entry_history', 'entry_id', $entryIds);
				$this->deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', $settlementIds);
				$this->deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $settlementIds);
				$this->deleteRowsByIds('cobudget_entries', $entryIds);
				$this->deleteRowsByIds('cobudget_settlements', $settlementIds);

				foreach (['cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates'] as $table) {
					$this->deleteRowsByColumnValues($table, 'project_id', $projectIds);
				}

				foreach (['cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates', 'cobudget_budget_goals', 'cobudget_budget_snapshots'] as $table) {
					$this->deleteRowsByWorkspaceAndUser($table, 'user_id', $id);
				}

				$this->deleteRowsByColumnValues('cobudget_members', 'project_id', $projectIds);
				$this->deleteRowsByIds('cobudget_projects', $projectIds);

				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_workspaces')
				   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
				$qb->executeStatement();

				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			$this->saveHiddenWorkspaceIds(array_values(array_filter(
				$this->hiddenWorkspaceIds(),
				static fn(int $hiddenId): bool => $hiddenId !== $id
			)));

			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function ownedProjectIdsInWorkspace(int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_projects')
			->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner_id', $qb->createNamedParameter($this->userId)));

		return $this->ids($qb->executeQuery()->fetchAll());
	}

	private function workspaceDeleteHasSharedProjectData(array $projectIds): bool {
		$projectIds = $this->normalizeIds($projectIds);
		if ($projectIds === []) {
			return false;
		}

		return $this->projectIdsWithOtherMembers($projectIds) !== []
			|| $this->projectIdsWithOtherUserEntries($projectIds) !== [];
	}

	private function projectIdsWithOtherMembers(array $projectIds): array {
		return $this->projectIdsWithOtherUserRows('cobudget_members', $projectIds);
	}

	private function projectIdsWithOtherUserEntries(array $projectIds): array {
		return $this->projectIdsWithOtherUserRows('cobudget_entries', $projectIds);
	}

	private function projectIdsWithOtherUserRows(string $table, array $projectIds): array {
		$projectIds = $this->normalizeIds($projectIds);
		if ($projectIds === []) {
			return [];
		}

		$sharedProjectIds = [];
		foreach (array_chunk($projectIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectAlias('project_id', 'id')
				->from($table)
				->where($qb->expr()->in('project_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->neq('user_id', $qb->createNamedParameter($this->userId)))
				->groupBy('project_id');
			$sharedProjectIds = array_merge($sharedProjectIds, $this->ids($qb->executeQuery()->fetchAll()));
		}

		return $this->normalizeIds($sharedProjectIds);
	}

	private function entryIdsForWorkspaceDelete(int $workspaceId, array $projectIds): array {
		$projectIds = $this->normalizeIds($projectIds);

		$qb = $this->db->getQueryBuilder();
		$personalWorkspaceScope = $qb->expr()->andX(
			$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
			$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId))
		);

		$scopes = [$personalWorkspaceScope];
		if ($projectIds !== []) {
			$scopes[] = $qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY));
		}

		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->orX(...$scopes));

		return $this->ids($qb->executeQuery()->fetchAll());
	}

	private function idsByColumnValues(string $table, string $column, array $values): array {
		$values = $this->normalizeIds($values);
		if ($values === []) {
			return [];
		}

		$ids = [];
		foreach (array_chunk($values, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$ids = array_merge($ids, $this->ids($qb->executeQuery()->fetchAll()));
		}

		return $this->normalizeIds($ids);
	}

	private function deleteRowsByIds(string $table, array $ids): int {
		return $this->deleteRowsByColumnValues($table, 'id', $ids);
	}

	private function deleteRowsByColumnValues(string $table, string $column, array $values): int {
		$values = $this->normalizeIds($values);
		$deleted = 0;
		foreach (array_chunk($values, 500) as $chunk) {
			if ($chunk === []) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleted += $qb->executeStatement();
		}

		return $deleted;
	}

	private function deleteRowsByWorkspaceAndUser(string $table, string $userColumn, int $workspaceId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)
			->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq($userColumn, $qb->createNamedParameter($this->userId)));

		return $qb->executeStatement();
	}

	private function ids(array $rows): array {
		$ids = [];
		foreach ($rows as $row) {
			if (isset($row['id'])) {
				$ids[] = (int)$row['id'];
			}
		}

		return $this->normalizeIds($ids);
	}

	private function normalizeIds(array $ids): array {
		return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
	}

	/**
	 * @NoAdminRequired
	 */
	public function hide(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if (!$this->workspaceExistsForUser($id)) {
				return new DataResponse(['error' => 'Workspace not found'], Http::STATUS_NOT_FOUND);
			}

			$hiddenIds = $this->hiddenWorkspaceIds();
			if (!in_array($id, $hiddenIds, true)) {
				$hiddenIds[] = $id;
				$this->saveHiddenWorkspaceIds($hiddenIds);
			}

			return new DataResponse(['status' => 'success', 'workspaces' => $this->getWorkspaceRows()]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function unhide(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if (!$this->workspaceExistsForUser($id)) {
				return new DataResponse(['error' => 'Workspace not found'], Http::STATUS_NOT_FOUND);
			}

			$this->saveHiddenWorkspaceIds(array_values(array_filter(
				$this->hiddenWorkspaceIds(),
				static fn(int $hiddenId): bool => $hiddenId !== $id
			)));

			return new DataResponse(['status' => 'success', 'workspaces' => $this->getWorkspaceRows()]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function findNewestWorkspaceId(string $name, int $createdAt): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)))
		   ->andWhere($qb->expr()->eq('created_at', $qb->createNamedParameter($createdAt, \PDO::PARAM_INT)))
		   ->orderBy('id', 'DESC')
		   ->setMaxResults(1);
		$result = $qb->executeQuery()->fetch();

		return $result ? (int)$result['id'] : 0;
	}

	private function findWorkspaceById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ? $this->normalizeWorkspaceRow($row) : null;
	}

	private function workspaceExistsForUser(int $id): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->setMaxResults(1);

		return $qb->executeQuery()->fetch() !== false;
	}

	private function getWorkspaceRows(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->orderBy('id', 'ASC');

		$workspaces = $qb->executeQuery()->fetchAll();

		return array_values(array_map(function(array $workspace): array {
			return $this->normalizeWorkspaceRow($workspace);
		}, $workspaces));
	}

	private function normalizeWorkspaceRow(array $workspace): array {
		$hiddenIds = $this->hiddenWorkspaceIds();
		$workspace['id'] = (int)$workspace['id'];
		$workspace['is_default'] = (bool)$workspace['is_default'];
		$workspace['is_hidden'] = in_array($workspace['id'], $hiddenIds, true);
		$workspace['created_at'] = (int)$workspace['created_at'];

		return $workspace;
	}

	private function hiddenWorkspaceIds(): array {
		$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_workspaces', '[]');
		$hiddenIds = json_decode($hiddenJson, true);
		if (!is_array($hiddenIds)) {
			return [];
		}

		return array_values(array_unique(array_filter(array_map('intval', $hiddenIds), static fn(int $id): bool => $id > 0)));
	}

	private function saveHiddenWorkspaceIds(array $hiddenIds): void {
		$hiddenIds = array_values(array_unique(array_filter(array_map('intval', $hiddenIds), static fn(int $id): bool => $id > 0)));
		$this->config->setUserValue($this->userId, 'cobudget', 'hidden_workspaces', json_encode($hiddenIds));
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\Config\IUserConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Manages personal API keys, their granular workspace/area/action scopes,
 * and lifecycle cleanup across resets, deletions and restores.
 */
class ApiTokenService {
	public const TABLE = 'cobudget_api_tokens';
	public const APP_SETTING_ENABLED = 'enable_api_access';
	public const SETTING_ENABLED = 'enable_api_access';
	public const MAX_TOKENS_PER_USER = 50;
	public const MAX_NAME_LENGTH = 128;
	public const MAX_EXPIRES_AT = 4_102_444_800;
	public const TOKEN_PREFIX_STRING = 'cb_';
	public const VALID_SCOPES = [
		'entries:read',
		'entries:create',
		'entries:write',
		'entries:delete',
		'reports:read',
		'settings:read',
		'settings:write',
	];
	public const ENTRY_SCOPES = [
		'entries:read',
		'entries:create',
		'entries:write',
		'entries:delete',
	];
	public const VALID_TARGET_ACTIONS = [
		'read',
		'write',
		'create',
		'delete',
	];
	public const GLOBAL_SCOPES = [
		'reports:read',
		'settings:read',
		'settings:write',
	];
	public const VALID_PROJECT_MODES = ['none', 'selected', 'all', 'matrix'];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IL10N $l10n,
		private ?IUserConfig $userConfig = null,
		private ?LoggerInterface $logger = null,
	) {
	}

	public function isGloballyEnabled(): bool {
		return $this->config->getAppValue('cobudget', self::APP_SETTING_ENABLED, 'no') === 'yes';
	}

	public function setGloballyEnabled(bool $enabled, ?string $actorUserId = null): void {
		$this->config->setAppValue('cobudget', self::APP_SETTING_ENABLED, $enabled ? 'yes' : 'no');
		$this->auditLog('CoBudget global API access setting changed', [
			'event' => 'admin.api_access.changed',
			'actor_user_id' => $actorUserId,
			'enabled' => $enabled,
		]);
	}

	public function isUserAccessEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, 'cobudget', self::SETTING_ENABLED, 'no') === 'yes';
	}

	public function isEnabledForUser(string $userId): bool {
		return $this->isGloballyEnabled() && $this->isUserAccessEnabled($userId);
	}

	/** Issuance, permission changes, master switches and cleanup share an account lock.
	 * A self-assignment takes a write lock on SQLite as well as on row-locking servers.
	 * The workspace exists before the first key is inserted, so creation is covered too.
	 */
	private function lockAccountWorkspaces(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_workspaces')
			->set('is_default', 'is_default')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->executeStatement();
	}

	public function setAccessEnabled(string $userId, bool $enabled): void {
		$this->db->beginTransaction();
		try {
			$this->lockAccountWorkspaces($userId);
			if ($enabled && !$this->isGloballyEnabled()) {
				throw new \DomainException($this->l10n->t('API access has been disabled by the administrator.'));
			}
			$this->userConfig?->clearCache($userId);
			$this->config->setUserValue($userId, 'cobudget', self::SETTING_ENABLED, $enabled ? 'yes' : 'no');
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$this->userConfig?->clearCache($userId);
			throw $e;
		}
		$this->auditLog('CoBudget personal API access setting changed', [
			'event' => 'user.api_access.changed',
			'user_id' => $userId,
			'enabled' => $enabled,
		]);
	}

	private function requireEnabled(string $userId): void {
		// The controller may already have loaded this account's preferences before taking the lock.
		$this->userConfig?->clearCache($userId);
		if (!$this->isGloballyEnabled()) {
			throw new \DomainException($this->l10n->t('API access has been disabled by the administrator.'));
		}
		if (!$this->isUserAccessEnabled($userId)) {
			throw new \DomainException($this->l10n->t('API access is disabled.'));
		}
	}

	/**
	 * @return array{
	 *     admin_enabled: bool,
	 *     enabled: bool,
	 *     workspaces: list<array{id: int, name: string, is_default: bool}>,
	 *     areas_by_workspace: array<string, list<array{id: int, name: string, color: string, is_archived: bool}>>,
	 *     tokens: list<array<string, mixed>>
	 * }
	 */
	public function catalogForUser(string $userId): array {
		$workspaces = $this->loadWorkspacesForUser($userId);
		$areasByWorkspace = $this->loadAreasByWorkspaceForUser($userId);
		$validAreasByWorkspace = [];
		foreach ($areasByWorkspace as $wsId => $areas) {
			foreach ($areas as $area) {
				$validAreasByWorkspace[(int)$wsId][(int)$area['id']] = true;
			}
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'user_id',
			'workspace_id',
			'name',
			'token_prefix',
			'scopes_json',
			'allow_personal',
			'project_mode',
			'project_ids_json',
			'is_active',
			'expires_at',
			'last_used_at',
			'created_at',
			'updated_at'
		)
			->from(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$now = time();
		$tokens = [];
		foreach ($rows as $row) {
			$workspaceId = (int)$row['workspace_id'];
			$validAreaSet = $validAreasByWorkspace[$workspaceId] ?? [];
			$tokens[] = $this->formatTokenRow($row, $validAreaSet, $now);
		}

		$serializedAreas = [];
		foreach ($workspaces as $workspace) {
			$wsKey = (string)$workspace['id'];
			$serializedAreas[$wsKey] = $areasByWorkspace[$workspace['id']] ?? [];
		}

		return [
			'admin_enabled' => $this->isGloballyEnabled(),
			'enabled' => $this->isEnabledForUser($userId),
			'workspaces' => $workspaces,
			'areas_by_workspace' => $serializedAreas,
			'tokens' => $tokens,
		];
	}

	/**
	 * @param list<string> $scopes
	 * @param list<int> $projectIds
	 * @param ?array<string|int, mixed> $targetPermissions
	 * @return array{token: array<string, mixed>, plain_token: string}
	 */
	public function create(
		string $userId,
		string $name,
		int $workspaceId,
		array $scopes,
		bool $allowPersonal,
		string $projectMode,
		array $projectIds = [],
		?int $expiresAt = null,
		?array $targetPermissions = null,
	): array {
		$ownTransaction = !$this->db->inTransaction();
		if ($ownTransaction) {
			$this->db->beginTransaction();
		}
		try {
			$this->lockAccountWorkspaces($userId);
			$this->requireEnabled($userId);

			$now = time();
			$normalized = $this->validateAndNormalizePayload(
				$userId,
				$name,
				$workspaceId,
				$scopes,
				$allowPersonal,
				$projectMode,
				$projectIds,
				$expiresAt,
				true,
				null,
				null,
				[],
				$now,
				$targetPermissions,
				[]
			);

			$plainHex = bin2hex(random_bytes(32));
			$plainToken = self::TOKEN_PREFIX_STRING . $plainHex;
			$tokenPrefix = self::TOKEN_PREFIX_STRING . substr($plainHex, 0, 8);
			$tokenHash = hash('sha256', $plainToken);

			if ($this->countUserTokens($userId) >= self::MAX_TOKENS_PER_USER) {
				throw new \InvalidArgumentException($this->l10n->t('Maximum number of API keys reached.'));
			}
			$this->assertUniqueName($userId, $normalized['name']);

			$qb = $this->db->getQueryBuilder();
			$qb->insert(self::TABLE)
				->values([
					'user_id' => $qb->createNamedParameter($userId),
					'workspace_id' => $qb->createNamedParameter($normalized['workspace_id'], \PDO::PARAM_INT),
					'name' => $qb->createNamedParameter($normalized['name']),
					'token_prefix' => $qb->createNamedParameter($tokenPrefix),
					'token_hash' => $qb->createNamedParameter($tokenHash),
					'scopes_json' => $qb->createNamedParameter(json_encode($normalized['scopes'], JSON_THROW_ON_ERROR)),
					'allow_personal' => $qb->createNamedParameter($normalized['allow_personal'], \PDO::PARAM_BOOL),
					'project_mode' => $qb->createNamedParameter($normalized['project_mode']),
					'project_ids_json' => $qb->createNamedParameter($normalized['project_ids_encoded']),
					'is_active' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
					'expires_at' => $qb->createNamedParameter($normalized['expires_at'], $normalized['expires_at'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'last_used_at' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'created_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();
			$id = (int)$this->db->lastInsertId('*PREFIX*' . self::TABLE);

			if ($ownTransaction) {
				$this->db->commit();
			}
		} catch (\Throwable $e) {
			if ($ownTransaction && $this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		$row = $this->findTokenForUser($userId, $id);
		if ($row === null) {
			throw new \RuntimeException('Created API key could not be loaded.');
		}

		$this->auditLog('CoBudget API key created', [
			'event' => 'api_token.created',
			'user_id' => $userId,
			'token_id' => $id,
			'token_prefix' => $tokenPrefix,
			'workspace_id' => $normalized['workspace_id'],
			'scopes' => $normalized['scopes'],
			'expires_at' => $normalized['expires_at'],
		]);

		return [
			'token' => $this->formatTokenRow($row, $this->loadValidAreaIdSetForWorkspace($userId, $normalized['workspace_id']), $now),
			'plain_token' => $plainToken,
		];
	}

	/**
	 * @param list<string> $scopes
	 * @param list<int> $projectIds
	 * @param ?array<string|int, mixed> $targetPermissions
	 * @return array<string, mixed>
	 */
	public function update(
		string $userId,
		int $id,
		string $name,
		array $scopes,
		bool $allowPersonal,
		string $projectMode,
		array $projectIds = [],
		?int $expiresAt = null,
		bool $isActive = true,
		?array $targetPermissions = null,
		?string $expectedRevision = null,
	): array {
		$ownTransaction = !$this->db->inTransaction();
		if ($ownTransaction) {
			$this->db->beginTransaction();
		}
		try {
			$this->lockAccountWorkspaces($userId);

			$existing = $this->findTokenForUser($userId, $id, forUpdate: true);
			if ($existing === null) {
				throw new \OutOfBoundsException($this->l10n->t('API key not found.'));
			}

			if ($expectedRevision !== null && !hash_equals($this->revision($existing), $expectedRevision)) {
				throw new \UnexpectedValueException($this->l10n->t('The API key has changed. Reload the key list before saving.'));
			}
			if ($isActive) {
				$this->requireEnabled($userId);
			}

			$workspaceId = (int)$existing['workspace_id'];
			$existingExpiresAt = $existing['expires_at'] !== null ? (int)$existing['expires_at'] : null;
			$existingMode = (string)($existing['project_mode'] ?? 'none');
			$existingMatrix = $existingMode === 'matrix'
				? $this->decodeMatrix($existing['project_ids_json'] ?? '{}')
				: [];
			$existingProjectIds = $existingMode === 'matrix'
				? $this->normalizePositiveIds(array_keys($existingMatrix))
				: $this->decodeIntList($existing['project_ids_json'] ?? '[]');
			$now = time();

			$normalized = $this->validateAndNormalizePayload(
				$userId,
				$name,
				$workspaceId,
				$scopes,
				$allowPersonal,
				$projectMode,
				$projectIds,
				$expiresAt,
				false,
				$existingExpiresAt,
				$isActive,
				$existingProjectIds,
				$now,
				$targetPermissions,
				$existingMatrix
			);

			$this->assertUniqueName($userId, $normalized['name'], $id);

			$qb = $this->db->getQueryBuilder();
			$qb->update(self::TABLE)
				->set('name', $qb->createNamedParameter($normalized['name']))
				->set('scopes_json', $qb->createNamedParameter(json_encode($normalized['scopes'], JSON_THROW_ON_ERROR)))
				->set('allow_personal', $qb->createNamedParameter($normalized['allow_personal'], \PDO::PARAM_BOOL))
				->set('project_mode', $qb->createNamedParameter($normalized['project_mode']))
				->set('project_ids_json', $qb->createNamedParameter($normalized['project_ids_encoded']))
				->set('is_active', $qb->createNamedParameter($normalized['is_active'], \PDO::PARAM_BOOL))
				->set('expires_at', $qb->createNamedParameter($normalized['expires_at'], $normalized['expires_at'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('updated_at', $qb->createNamedParameter($now, \PDO::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
			$qb->executeStatement();

			if ($ownTransaction) {
				$this->db->commit();
			}
		} catch (\Throwable $e) {
			if ($ownTransaction && $this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		$updated = $this->findTokenForUser($userId, $id);
		if ($updated === null) {
			throw new \OutOfBoundsException($this->l10n->t('API key not found.'));
		}

		$this->auditLog('CoBudget API key updated', [
			'event' => 'api_token.updated',
			'user_id' => $userId,
			'token_id' => $id,
			'token_prefix' => (string)($existing['token_prefix'] ?? ''),
			'workspace_id' => $workspaceId,
			'scopes' => $normalized['scopes'],
			'is_active' => $normalized['is_active'],
			'expires_at' => $normalized['expires_at'],
		]);

		$validAreaIds = $this->loadValidAreaIdSetForWorkspace($userId, $workspaceId);
		return $this->formatTokenRow($updated, $validAreaIds, $now);
	}

	public function delete(string $userId, int $id): void {
		$ownTransaction = !$this->db->inTransaction();
		if ($ownTransaction) {
			$this->db->beginTransaction();
		}
		try {
			$this->lockAccountWorkspaces($userId);
			$existing = $this->findTokenForUser($userId, $id, forUpdate: true);
			if ($existing === null) {
				throw new \OutOfBoundsException($this->l10n->t('API key not found.'));
			}

			$qb = $this->db->getQueryBuilder();
			$qb->delete(self::TABLE)
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
			$deleted = $qb->executeStatement();

			if ($deleted !== 1) {
				throw new \OutOfBoundsException($this->l10n->t('API key not found.'));
			}

			if ($ownTransaction) {
				$this->db->commit();
			}
		} catch (\Throwable $e) {
			if ($ownTransaction && $this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		$this->auditLog('CoBudget API key deleted', [
			'event' => 'api_token.deleted',
			'user_id' => $userId,
			'token_id' => $id,
			'token_prefix' => (string)($existing['token_prefix'] ?? ''),
			'workspace_id' => (int)($existing['workspace_id'] ?? 0),
		]);
	}

	public function deleteForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}

	public function deleteForWorkspace(int $workspaceId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Remove deleted or left area IDs from tokens configured with `selected` areas.
	 * Never widens a token's scope: if no area remains, drops to `none` and pauses
	 * the token whenever personal access is disabled or entry scopes would have no target.
	 *
	 * @param list<int> $projectIds
	 */
	public function removeProjectReferences(array $projectIds, ?string $onlyUserId = null): void {
		$removedIds = $this->normalizePositiveIds($projectIds);
		if ($removedIds === []) {
			return;
		}

		$ownTransaction = !$this->db->inTransaction();
		if ($ownTransaction) {
			$this->db->beginTransaction();
		}
		try {
			$owners = $onlyUserId === null ? $this->projectMemberUserIds($removedIds) : [$onlyUserId];
			foreach ($owners as $owner) {
				$this->pruneAccountProjectReferences($owner, $removedIds);
			}

			if ($ownTransaction) {
				$this->db->commit();
			}
		} catch (\Throwable $e) {
			if ($ownTransaction && $this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}
	}

	/** @return list<string> */
	private function projectMemberUserIds(array $removedIds): array {
		// The caller removes membership rows after this operation, in the same transaction.
		$owners = [];
		foreach ($removedIds as $projectId) {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select('user_id')->from('cobudget_members')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
				->executeQuery();
			$owners = array_merge($owners, array_column($result->fetchAll(), 'user_id'));
			$result->closeCursor();
		}
		$owners = array_unique($owners);
		sort($owners, SORT_STRING);
		return $owners;
	}

	private function pruneAccountProjectReferences(string $userId, array $removedIds): void {
		$removedLookup = array_fill_keys($removedIds, true);
		$this->lockAccountWorkspaces($userId);
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'project_mode', 'project_ids_json', 'allow_personal', 'scopes_json', 'is_active')
			->from(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		// The caller may already have established a transaction snapshot before acquiring our lock.
		if ($this->db->getDatabaseProvider() !== 'sqlite') {
			$qb->forUpdate();
		}
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$now = time();
		foreach ($rows as $row) {
			$mode = (string)($row['project_mode'] ?? 'none');
			if ($mode === 'matrix') {
				$currentMatrix = $this->decodeMatrix($row['project_ids_json'] ?? '{}');
				$changed = false;
				foreach ($removedIds as $removedId) {
					$key = (string)$removedId;
					if (isset($currentMatrix[$key])) {
						unset($currentMatrix[$key]);
						$changed = true;
					}
				}
				if (!$changed) {
					continue;
				}

				$allowPersonal = isset($currentMatrix['personal']);
				$globalScopes = array_values(array_intersect(
					$this->decodeScopes($row['scopes_json'] ?? '[]'),
					self::GLOBAL_SCOPES
				));
				$newScopes = $this->combineScopesFromMatrix($currentMatrix, $globalScopes);
				$newIsActive = $this->boolFlag($row['is_active'] ?? true);
				if ($newScopes === [] || ($currentMatrix === [] && in_array('reports:read', $newScopes, true))) {
					$newIsActive = false;
				}

				$update = $this->db->getQueryBuilder();
				$update->update(self::TABLE)
					->set('allow_personal', $update->createNamedParameter($allowPersonal, \PDO::PARAM_BOOL))
					->set('scopes_json', $update->createNamedParameter(json_encode($newScopes, JSON_THROW_ON_ERROR)))
					->set('project_ids_json', $update->createNamedParameter(json_encode((object)$currentMatrix, JSON_THROW_ON_ERROR)))
					->set('is_active', $update->createNamedParameter($newIsActive, \PDO::PARAM_BOOL))
					->set('updated_at', $update->createNamedParameter($now, \PDO::PARAM_INT))
					->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \PDO::PARAM_INT)))
					->andWhere($update->expr()->eq('user_id', $update->createNamedParameter($userId)));
				$update->executeStatement();
				continue;
			}
			if ($mode !== 'selected') {
				continue;
			}

			$currentIds = $this->decodeIntList($row['project_ids_json'] ?? '[]');
			$remainingIds = array_values(array_filter(
				$currentIds,
				static fn (int $id): bool => !isset($removedLookup[$id])
			));
			if (count($remainingIds) === count($currentIds)) {
				continue;
			}

			$newMode = $remainingIds === [] ? 'none' : 'selected';
			$allowPersonal = $this->boolFlag($row['allow_personal'] ?? false);
			$scopes = $this->decodeScopes($row['scopes_json'] ?? '[]');
			$hasEntryScope = array_intersect($scopes, self::ENTRY_SCOPES) !== [];
			$newIsActive = $this->boolFlag($row['is_active'] ?? true);
			if ($newMode === 'none' && (!$allowPersonal || $hasEntryScope)) {
				$newIsActive = false;
			}

			$update = $this->db->getQueryBuilder();
			$update->update(self::TABLE)
				->set('project_mode', $update->createNamedParameter($newMode))
				->set('project_ids_json', $update->createNamedParameter(json_encode($remainingIds, JSON_THROW_ON_ERROR)))
				->set('is_active', $update->createNamedParameter($newIsActive, \PDO::PARAM_BOOL))
				->set('updated_at', $update->createNamedParameter($now, \PDO::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \PDO::PARAM_INT)))
				->andWhere($update->expr()->eq('user_id', $update->createNamedParameter($userId)));
			$update->executeStatement();
		}
	}

	/**
	 * @param list<string> $scopes
	 * @param list<int> $projectIds
	 * @param list<int> $existingProjectIds
	 * @param ?array<string|int, mixed> $targetPermissions
	 * @param array<string, list<string>> $existingMatrix
	 * @return array{
	 *     name: string,
	 *     workspace_id: int,
	 *     scopes: list<string>,
	 *     allow_personal: bool,
	 *     project_mode: string,
	 *     project_ids: list<int>,
	 *     project_ids_encoded: string,
	 *     expires_at: ?int,
	 *     is_active: bool
	 * }
	 */
	private function validateAndNormalizePayload(
		string $userId,
		string $name,
		int $workspaceId,
		array $scopes,
		bool $allowPersonal,
		string $projectMode,
		array $projectIds,
		?int $expiresAt,
		bool $isCreate,
		?int $existingExpiresAt,
		?bool $isActive,
		array $existingProjectIds,
		int $now,
		?array $targetPermissions = null,
		array $existingMatrix = [],
	): array {
		if (!mb_check_encoding($name, 'UTF-8') || str_contains($name, "\u{FFFD}") || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
			throw new \InvalidArgumentException($this->l10n->t('Name contains invalid characters.'));
		}
		$normalizedName = trim((string)preg_replace('/\s+/u', ' ', $name));
		if ($normalizedName === '') {
			throw new \InvalidArgumentException($this->l10n->t('Name is required'));
		}
		if (mb_strlen($normalizedName, 'UTF-8') > self::MAX_NAME_LENGTH) {
			throw new \InvalidArgumentException($this->l10n->t('Name must not exceed 128 characters.'));
		}

		if ($workspaceId <= 0 || !$this->workspaceBelongsToUser($userId, $workspaceId)) {
			throw new \InvalidArgumentException($this->l10n->t('Workspace not found or no permission'));
		}

		$normalizedInputScopes = [];
		$seenScopes = [];
		foreach ($scopes as $scope) {
			if (!is_string($scope)) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid permission selected.'));
			}
			$scope = trim($scope);
			if (!in_array($scope, self::VALID_SCOPES, true)) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid permission selected.'));
			}
			$seenScopes[$scope] = true;
		}
		foreach (self::VALID_SCOPES as $validScope) {
			if (isset($seenScopes[$validScope])) {
				$normalizedInputScopes[] = $validScope;
			}
		}

		$areas = $this->loadAreasForWorkspace($userId, $workspaceId);
		$areasEnabled = $this->config->getUserValue($userId, 'cobudget', 'enable_projects', 'yes') === 'yes';
		$activeAreaIds = [];
		if ($areasEnabled) {
			foreach ($areas as $area) {
				if (!$area['is_archived']) {
					$activeAreaIds[] = (int)$area['id'];
				}
			}
		}

		$effectiveIsActive = $isActive ?? true;
		$normalizedProjectIds = [];
		$normalizedScopes = [];
		$normalizedAllowPersonal = $allowPersonal;
		$normalizedProjectMode = trim($projectMode);
		$projectIdsEncoded = '[]';

		if ($targetPermissions !== null) {
			$normalizedMatrix = [];
			foreach ($targetPermissions as $targetKey => $actions) {
				$targetKeyStr = (string)$targetKey;
				$normalizedActions = $this->normalizeTargetActions($actions);
				if ($normalizedActions === []) {
					continue;
				}
				if ($targetKeyStr === 'personal') {
					$normalizedMatrix['personal'] = $normalizedActions;
					continue;
				}
				if (!ctype_digit($targetKeyStr) || (int)$targetKeyStr <= 0) {
					throw new \InvalidArgumentException($this->l10n->t('Area not found or not in the selected workspace.'));
				}
				$areaId = (int)$targetKeyStr;
				if (!isset($areas[$areaId])) {
					throw new \InvalidArgumentException($this->l10n->t('Area not found or not in the selected workspace.'));
				}
				if ($areas[$areaId]['is_archived'] && !isset($existingMatrix[(string)$areaId])) {
					throw new \InvalidArgumentException($this->l10n->t('Area not found or not in the selected workspace.'));
				}
				$normalizedMatrix[(string)$areaId] = $normalizedActions;
				$normalizedProjectIds[] = $areaId;
			}

			$globalScopes = array_values(array_intersect($normalizedInputScopes, self::GLOBAL_SCOPES));
			$normalizedScopes = $this->combineScopesFromMatrix($normalizedMatrix, $globalScopes);
			if ($normalizedScopes === []) {
				throw new \InvalidArgumentException($this->l10n->t('Select at least one permission.'));
			}

			$hasPersonalRead = in_array('read', $normalizedMatrix['personal'] ?? [], true);
			$coversAllActiveAreasRead = true;
			foreach ($activeAreaIds as $activeAreaId) {
				if (!in_array('read', $normalizedMatrix[(string)$activeAreaId] ?? [], true)) {
					$coversAllActiveAreasRead = false;
					break;
				}
			}
			if ($effectiveIsActive && in_array('reports:read', $normalizedScopes, true) && (!$hasPersonalRead || !$coversAllActiveAreasRead)) {
				throw new \InvalidArgumentException($this->l10n->t('Reports and budget goals require access to personal payments and all areas in the workspace.'));
			}

			$normalizedAllowPersonal = isset($normalizedMatrix['personal']);
			$normalizedProjectMode = 'matrix';
			$projectIdsEncoded = json_encode((object)$normalizedMatrix, JSON_THROW_ON_ERROR);
		} else {
			$normalizedScopes = $normalizedInputScopes;
			if ($normalizedScopes === []) {
				throw new \InvalidArgumentException($this->l10n->t('Select at least one permission.'));
			}

			if (!in_array($normalizedProjectMode, ['none', 'selected', 'all'], true)) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid area access mode.'));
			}

			if ($normalizedProjectMode === 'selected') {
				$normalizedProjectIds = $this->normalizePositiveIds($projectIds);
				if ($normalizedProjectIds === []) {
					throw new \InvalidArgumentException($this->l10n->t('Select at least one area.'));
				}

				$existingLookup = array_fill_keys($existingProjectIds, true);
				foreach ($normalizedProjectIds as $areaId) {
					if (!isset($areas[$areaId])) {
						throw new \InvalidArgumentException($this->l10n->t('Area not found or not in the selected workspace.'));
					}
					if ($areas[$areaId]['is_archived'] && !isset($existingLookup[$areaId])) {
						throw new \InvalidArgumentException($this->l10n->t('Area not found or not in the selected workspace.'));
					}
				}
			}

			if ($effectiveIsActive && array_intersect($normalizedScopes, self::ENTRY_SCOPES) !== [] && !$normalizedAllowPersonal && $normalizedProjectMode === 'none') {
				throw new \InvalidArgumentException($this->l10n->t('Select personal payments or at least one area.'));
			}

			$coversAllActiveAreas = $normalizedProjectMode === 'all' || array_diff($activeAreaIds, $normalizedProjectIds) === [];
			if ($effectiveIsActive && in_array('reports:read', $normalizedScopes, true) && (!$normalizedAllowPersonal || !$coversAllActiveAreas)) {
				throw new \InvalidArgumentException($this->l10n->t('Reports and budget goals require access to personal payments and all areas in the workspace.'));
			}

			$projectIdsEncoded = json_encode($normalizedProjectIds, JSON_THROW_ON_ERROR);
		}

		$normalizedExpiresAt = null;
		if ($expiresAt !== null) {
			if ($expiresAt <= 0 || $expiresAt > self::MAX_EXPIRES_AT) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid expiration date.'));
			}
			if (($isCreate || $expiresAt !== $existingExpiresAt) && $expiresAt <= $now) {
				throw new \InvalidArgumentException($this->l10n->t('Choose an expiration date in the future.'));
			}
			$normalizedExpiresAt = $expiresAt;
		}

		return [
			'name' => $normalizedName,
			'workspace_id' => $workspaceId,
			'scopes' => $normalizedScopes,
			'allow_personal' => $normalizedAllowPersonal,
			'project_mode' => $normalizedProjectMode,
			'project_ids' => $normalizedProjectIds,
			'project_ids_encoded' => $projectIdsEncoded,
			'expires_at' => $normalizedExpiresAt,
			'is_active' => $isActive ?? true,
		];
	}

	private function countUserTokens(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS token_count'))
			->from(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?: 0);
		$result->closeCursor();
		return $count;
	}

	private function assertUniqueName(string $userId, string $name, ?int $excludeId = null): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name')
			->from(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		if ($excludeId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, \PDO::PARAM_INT)));
		}
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$target = mb_strtolower($name, 'UTF-8');
		foreach ($rows as $row) {
			if (mb_strtolower((string)($row['name'] ?? ''), 'UTF-8') === $target) {
				throw new \InvalidArgumentException($this->l10n->t('An API key with this name already exists.'));
			}
		}
	}

	private function workspaceBelongsToUser(string $userId, int $workspaceId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	private function isDefaultWorkspaceForUser(string $userId, int $workspaceId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('is_default')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$isDefault = $result->fetchOne();
		$result->closeCursor();
		return $this->boolFlag($isDefault);
	}

	/** @return list<array{id: int, name: string, is_default: bool}> */
	private function loadWorkspacesForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'is_default')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('is_default', 'DESC')
			->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(fn (array $row): array => [
			'id' => (int)$row['id'],
			'name' => (string)$row['name'],
			'is_default' => $this->boolFlag($row['is_default'] ?? false),
		], $rows);
	}

	public function tokenHashExists(string $tokenHash): bool {
		$tokenHash = strtolower(trim($tokenHash));
		if (preg_match('/^[0-9a-f]{64}$/D', $tokenHash) !== 1) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('token_hash')
			->from(self::TABLE)
			->where($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$storedHash = $result->fetchOne();
		$result->closeCursor();
		return is_string($storedHash) && hash_equals($storedHash, $tokenHash);
	}

	/** @return ?array<string, mixed> */
	public function findActiveTokenRowByHash(string $tokenHash, int $now, bool $credentialOnly): ?array {
		$tokenHash = strtolower(trim($tokenHash));
		if (preg_match('/^[0-9a-f]{64}$/D', $tokenHash) !== 1) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'user_id',
			'workspace_id',
			'name',
			'token_prefix',
			'token_hash',
			'scopes_json',
			'allow_personal',
			'project_mode',
			'project_ids_json',
			'is_active',
			'expires_at',
			'last_used_at',
			'created_at',
			'updated_at'
		)
			->from(self::TABLE)
			->where($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('expires_at'),
				$qb->expr()->gt('expires_at', $qb->createNamedParameter($now, \PDO::PARAM_INT))
			))
			->setMaxResults(1);
		if ($credentialOnly) {
			$qb->select('id', 'token_hash');
		}

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return is_array($row) ? $row : null;
	}

	/**
	 * Resolves live token authorization context strictly against active workspace,
	 * `enable_api_access`, `enable_projects`, and non-archived areas.
	 *
	 * @param array<string, mixed> $row
	 * @return ?array{
	 *     token_id: int,
	 *     token_prefix: string,
	 *     token_name: string,
	 *     user_id: string,
	 *     workspace_id: int,
	 *     scopes: list<string>,
	 *     target_permissions: array<string, list<string>>,
	 *     active_areas_by_id: array<int, array<string, mixed>>,
	 *     projects_enabled: bool,
	 *     can_use_reports: bool,
	 *     expires_at: ?int,
	 *     last_used_at: ?int,
	 *     created_at: int
	 * }
	 */
	public function resolveAuthContextFromRow(array $row, int $now): ?array {
		if (!$this->boolFlag($row['is_active'] ?? false)) {
			return null;
		}

		$expiresAt = $row['expires_at'] !== null ? (int)$row['expires_at'] : null;
		if ($expiresAt !== null && $expiresAt <= $now) {
			return null;
		}

		$userId = trim((string)($row['user_id'] ?? ''));
		$workspaceId = (int)($row['workspace_id'] ?? 0);
		$tokenId = (int)($row['id'] ?? 0);
		if ($tokenId <= 0 || $userId === '' || $workspaceId <= 0) {
			return null;
		}

		if (!$this->isEnabledForUser($userId)) {
			return null;
		}

		if (!$this->workspaceBelongsToUser($userId, $workspaceId)) {
			return null;
		}

		$workspacesEnabled = $this->config->getUserValue($userId, 'cobudget', 'enable_workspaces', 'no') === 'yes';
		if (!$workspacesEnabled && !$this->isDefaultWorkspaceForUser($userId, $workspaceId)) {
			return null;
		}

		$projectsEnabled = $this->config->getUserValue($userId, 'cobudget', 'enable_projects', 'yes') === 'yes';
		$activeAreasById = $projectsEnabled
			? $this->loadActiveAreaMetadataForWorkspace($userId, $workspaceId)
			: [];
		$activeAreaIdSet = array_fill_keys(array_keys($activeAreasById), true);

		$formatted = $this->formatTokenRow($row, $activeAreaIdSet, $now);
		$targetPermissions = [];
		foreach ((array)$formatted['target_permissions'] as $key => $actions) {
			if (!is_array($actions) || $actions === []) {
				continue;
			}
			$keyStr = (string)$key;
			if ($keyStr === 'personal' || isset($activeAreasById[(int)$keyStr])) {
				$targetPermissions[$keyStr] = array_values(array_intersect(self::VALID_TARGET_ACTIONS, $actions));
			}
		}

		$scopes = array_values(array_intersect(self::VALID_SCOPES, (array)$formatted['scopes']));
		$hasReportsScope = in_array('reports:read', $scopes, true);
		$canReadPersonal = in_array('read', $targetPermissions['personal'] ?? [], true);
		$canReadAllActiveAreas = true;
		foreach (array_keys($activeAreasById) as $areaId) {
			if (!in_array('read', $targetPermissions[(string)$areaId] ?? [], true)) {
				$canReadAllActiveAreas = false;
				break;
			}
		}

		return [
			'token_id' => $tokenId,
			'token_prefix' => (string)$formatted['token_prefix'],
			'token_name' => (string)$formatted['name'],
			'user_id' => $userId,
			'workspace_id' => $workspaceId,
			'scopes' => $scopes,
			'target_permissions' => $targetPermissions,
			'active_areas_by_id' => $activeAreasById,
			'projects_enabled' => $projectsEnabled,
			'can_use_reports' => $hasReportsScope && $canReadPersonal && $canReadAllActiveAreas,
			'expires_at' => $expiresAt,
			'last_used_at' => $formatted['last_used_at'],
			'created_at' => (int)$formatted['created_at'],
		];
	}

	/**
	 * Throttled atomic update of `last_used_at` (at most once per 60s per token)
	 * so concurrent read requests minimize write lock contention.
	 */
	public function touchLastUsed(int $tokenId, int $now): void {
		if ($tokenId <= 0) {
			return;
		}
		try {
			$cutoff = $now - 60;
			$qb = $this->db->getQueryBuilder();
			$qb->update(self::TABLE)
				->set('last_used_at', $qb->createNamedParameter($now, \PDO::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($tokenId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('last_used_at'),
					$qb->expr()->lte('last_used_at', $qb->createNamedParameter($cutoff, \PDO::PARAM_INT))
				));
			$qb->executeStatement();
		} catch (\Throwable) {
			// Do not fail valid API requests due to transient SQLite write contention.
		}
	}

	/**
	 * @return array<int, array{
	 *     id: int,
	 *     name: string,
	 *     owner_id: string,
	 *     workspace_id: int,
	 *     color: string,
	 *     hidden_category_ids: list<int>,
	 *     hidden_payment_partner_ids: list<int>,
	 *     member_count: int,
	 *     is_shared: bool,
	 *     has_former_member: bool
	 * }>
	 */
	private function loadActiveAreaMetadataForWorkspace(string $userId, int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'p.id',
			'p.name',
			'p.owner_id',
			'p.workspace_id',
			'p.color',
			'p.hidden_category_ids',
			'p.hidden_payment_partner_ids'
		)
			->from('cobudget_projects', 'p')
			->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('m.personal_workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.is_archived', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->orderBy('p.name', 'ASC')
			->addOrderBy('p.id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$areas = [];
		$areaIds = [];
		foreach ($rows as $row) {
			$areaId = (int)($row['id'] ?? 0);
			if ($areaId <= 0 || isset($areas[$areaId])) {
				continue;
			}
			$areaIds[] = $areaId;
			$areas[$areaId] = [
				'id' => $areaId,
				'name' => (string)($row['name'] ?? ''),
				'owner_id' => (string)($row['owner_id'] ?? ''),
				'workspace_id' => (int)($row['workspace_id'] ?? 0),
				'color' => (string)($row['color'] ?: '#0082c9'),
				'hidden_category_ids' => $this->decodeIntList($row['hidden_category_ids'] ?? '[]'),
				'hidden_payment_partner_ids' => $this->decodeIntList($row['hidden_payment_partner_ids'] ?? '[]'),
				'member_count' => 1,
				'is_shared' => false,
				'has_former_member' => false,
			];
		}

		if ($areaIds === []) {
			return [];
		}

		foreach (array_chunk($areaIds, 500) as $chunk) {
			$mqb = $this->db->getQueryBuilder();
			$mqb->select('project_id', 'user_id')
				->from('cobudget_members')
				->where($mqb->expr()->in('project_id', $mqb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$mResult = $mqb->executeQuery();
			$members = $mResult->fetchAll();
			$mResult->closeCursor();

			$counts = [];
			$hasFormer = [];
			foreach ($members as $memberRow) {
				$pid = (int)($memberRow['project_id'] ?? 0);
				if (!isset($areas[$pid])) {
					continue;
				}
				$counts[$pid] = ($counts[$pid] ?? 0) + 1;
				if (str_starts_with(trim((string)($memberRow['user_id'] ?? '')), 'former:')) {
					$hasFormer[$pid] = true;
				}
			}
			foreach ($counts as $pid => $count) {
				$areas[$pid]['member_count'] = $count;
				$areas[$pid]['is_shared'] = $count > 1;
				$areas[$pid]['has_former_member'] = isset($hasFormer[$pid]);
			}
		}

		return $areas;
	}

	/** @return array<int, list<array{id: int, name: string, color: string, is_archived: bool}>> */
	private function loadAreasByWorkspaceForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.id', 'p.name', 'p.color', 'p.is_archived', 'm.personal_workspace_id')
			->from('cobudget_projects', 'p')
			->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNotNull('m.personal_workspace_id'))
			->orderBy('p.name', 'ASC')
			->addOrderBy('p.id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$byWorkspace = [];
		$seen = [];
		foreach ($rows as $row) {
			$wsId = (int)$row['personal_workspace_id'];
			$areaId = (int)$row['id'];
			if ($wsId <= 0 || $areaId <= 0 || isset($seen[$wsId][$areaId])) {
				continue;
			}
			$seen[$wsId][$areaId] = true;
			$byWorkspace[$wsId][] = [
				'id' => $areaId,
				'name' => (string)$row['name'],
				'color' => (string)($row['color'] ?: '#0082c9'),
				'is_archived' => $this->boolFlag($row['is_archived'] ?? false),
			];
		}

		return $byWorkspace;
	}

	/** @return array<int, array{id: int, name: string, color: string, is_archived: bool}> */
	private function loadAreasForWorkspace(string $userId, int $workspaceId): array {
		$byWorkspace = $this->loadAreasByWorkspaceForUser($userId);
		$areas = [];
		foreach ($byWorkspace[$workspaceId] ?? [] as $area) {
			$areas[$area['id']] = $area;
		}
		return $areas;
	}

	/** @return array<int, true> */
	private function loadValidAreaIdSetForWorkspace(string $userId, int $workspaceId): array {
		return array_fill_keys(array_keys($this->loadAreasForWorkspace($userId, $workspaceId)), true);
	}

	/** @return ?array<string, mixed> */
	private function findTokenForUser(string $userId, int $id, bool $forUpdate = false): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'user_id',
			'workspace_id',
			'name',
			'token_prefix',
			'scopes_json',
			'allow_personal',
			'project_mode',
			'project_ids_json',
			'is_active',
			'expires_at',
			'last_used_at',
			'created_at',
			'updated_at'
		)
			->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		if ($forUpdate && $this->db->getDatabaseProvider() !== 'sqlite') {
			$qb->forUpdate();
		}
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return is_array($row) ? $row : null;
	}

	/** A stale settings form cannot restore grants that the owner or lifecycle cleanup removed. */
	private function revision(array $row): string {
		return hash('sha256', json_encode([
			(int)$row['id'], (string)$row['name'], (string)$row['project_mode'], (string)$row['project_ids_json'],
			(string)$row['scopes_json'], $this->boolFlag($row['allow_personal']), $this->boolFlag($row['is_active']),
			$row['expires_at'] === null ? null : (int)$row['expires_at'],
		], JSON_THROW_ON_ERROR));
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<int, true> $validAreaSet
	 * @return array<string, mixed>
	 */
	private function formatTokenRow(array $row, array $validAreaSet, int $now): array {
		$projectMode = (string)($row['project_mode'] ?? 'none');
		if (!in_array($projectMode, self::VALID_PROJECT_MODES, true)) {
			$projectMode = 'none';
		}

		$allowPersonal = $this->boolFlag($row['allow_personal'] ?? false);
		$scopes = $this->decodeScopes($row['scopes_json'] ?? '[]');
		$projectIds = [];
		$targetPermissions = [];

		if ($projectMode === 'matrix') {
			$rawMatrix = $this->decodeMatrix($row['project_ids_json'] ?? '{}');
			foreach ($rawMatrix as $key => $actions) {
				if ($key === 'personal') {
					$targetPermissions['personal'] = $actions;
					continue;
				}
				$areaId = (int)$key;
				if (isset($validAreaSet[$areaId])) {
					$projectIds[] = $areaId;
					$targetPermissions[(string)$areaId] = $actions;
				}
			}
		} else {
			if ($projectMode === 'selected') {
				foreach ($this->decodeIntList($row['project_ids_json'] ?? '[]') as $areaId) {
					if (isset($validAreaSet[$areaId])) {
						$projectIds[] = $areaId;
					}
				}
			}

			$actionMap = [
				'entries:read' => 'read',
				'entries:write' => 'write',
				'entries:create' => 'create',
				'entries:delete' => 'delete',
			];
			$legacyActions = [];
			foreach ($actionMap as $scope => $action) {
				if (in_array($scope, $scopes, true)) {
					$legacyActions[] = $action;
				}
			}
			if ($legacyActions !== []) {
				if ($allowPersonal) {
					$targetPermissions['personal'] = $legacyActions;
				}
				if ($projectMode === 'selected') {
					foreach ($projectIds as $areaId) {
						$targetPermissions[(string)$areaId] = $legacyActions;
					}
				} elseif ($projectMode === 'all') {
					foreach (array_keys($validAreaSet) as $areaId) {
						$targetPermissions[(string)$areaId] = $legacyActions;
					}
				}
			}
		}

		$expiresAt = $row['expires_at'] !== null ? (int)$row['expires_at'] : null;
		$lastUsedAt = $row['last_used_at'] !== null ? (int)$row['last_used_at'] : null;

		return [
			'id' => (int)$row['id'],
			'workspace_id' => (int)$row['workspace_id'],
			'name' => (string)$row['name'],
			'revision' => $this->revision($row),
			'token_prefix' => (string)$row['token_prefix'],
			'scopes' => $scopes,
			'allow_personal' => $allowPersonal,
			'project_mode' => $projectMode,
			'project_ids' => $projectIds,
			'target_permissions' => (object)$targetPermissions,
			'is_active' => $this->boolFlag($row['is_active'] ?? false),
			'is_expired' => $expiresAt !== null && $expiresAt <= $now,
			'expires_at' => $expiresAt,
			'last_used_at' => $lastUsedAt,
			'created_at' => (int)($row['created_at'] ?? 0),
			'updated_at' => (int)($row['updated_at'] ?? 0),
		];
	}

	/** @return list<string> */
	private function normalizeTargetActions(mixed $actions): array {
		if (!is_array($actions)) {
			throw new \InvalidArgumentException($this->l10n->t('Invalid permission selected.'));
		}
		$seen = [];
		foreach ($actions as $action) {
			if (!is_string($action)) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid permission selected.'));
			}
			$action = trim($action);
			if (!in_array($action, self::VALID_TARGET_ACTIONS, true)) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid permission selected.'));
			}
			$seen[$action] = true;
		}
		$ordered = [];
		foreach (self::VALID_TARGET_ACTIONS as $validAction) {
			if (isset($seen[$validAction])) {
				$ordered[] = $validAction;
			}
		}
		return $ordered;
	}

	/** @return array<string, list<string>> */
	private function decodeMatrix(mixed $json): array {
		if (!is_string($json) || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		$matrix = [];
		foreach ($decoded as $targetKey => $actions) {
			$keyStr = (string)$targetKey;
			if ($keyStr !== 'personal' && (!ctype_digit($keyStr) || (int)$keyStr <= 0)) {
				continue;
			}
			if (!is_array($actions)) {
				continue;
			}
			$seen = [];
			foreach ($actions as $action) {
				if (is_string($action) && in_array($action, self::VALID_TARGET_ACTIONS, true)) {
					$seen[$action] = true;
				}
			}
			$ordered = [];
			foreach (self::VALID_TARGET_ACTIONS as $validAction) {
				if (isset($seen[$validAction])) {
					$ordered[] = $validAction;
				}
			}
			if ($ordered !== []) {
				$matrix[$keyStr] = $ordered;
			}
		}
		return $matrix;
	}

	/**
	 * @param array<string, list<string>> $matrix
	 * @param list<string> $globalScopes
	 * @return list<string>
	 */
	private function combineScopesFromMatrix(array $matrix, array $globalScopes): array {
		$seen = [];
		$actionToScope = [
			'read' => 'entries:read',
			'create' => 'entries:create',
			'write' => 'entries:write',
			'delete' => 'entries:delete',
		];
		foreach ($matrix as $actions) {
			foreach ($actions as $action) {
				if (isset($actionToScope[$action])) {
					$seen[$actionToScope[$action]] = true;
				}
			}
		}
		foreach ($globalScopes as $scope) {
			if (in_array($scope, self::GLOBAL_SCOPES, true)) {
				$seen[$scope] = true;
			}
		}
		$ordered = [];
		foreach (self::VALID_SCOPES as $validScope) {
			if (isset($seen[$validScope])) {
				$ordered[] = $validScope;
			}
		}
		return $ordered;
	}

	/** @return list<string> */
	private function decodeScopes(mixed $json): array {
		if (!is_string($json) || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		$lookup = [];
		foreach ($decoded as $scope) {
			if (is_string($scope) && in_array($scope, self::VALID_SCOPES, true)) {
				$lookup[$scope] = true;
			}
		}
		$ordered = [];
		foreach (self::VALID_SCOPES as $validScope) {
			if (isset($lookup[$validScope])) {
				$ordered[] = $validScope;
			}
		}
		return $ordered;
	}

	/** @return list<int> */
	private function decodeIntList(mixed $json): array {
		if (!is_string($json) || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		return $this->normalizePositiveIds($decoded);
	}

	/** @return list<int> */
	private function normalizePositiveIds(array $values): array {
		$ids = [];
		foreach ($values as $value) {
			if (is_int($value) || (is_string($value) && ctype_digit($value))) {
				$id = (int)$value;
				if ($id > 0) {
					$ids[$id] = $id;
				}
			}
		}
		return array_values($ids);
	}

	private function boolFlag(mixed $value): bool {
		return in_array($value, [true, 1, '1', 't', 'true'], true);
	}

	/** @param array<string, mixed> $context */
	private function auditLog(string $message, array $context): void {
		try {
			$this->logger?->info($message, ['app' => 'cobudget', ...$context]);
		} catch (\Throwable) {
			// Audit logging must never break committed API key changes.
		}
	}
}

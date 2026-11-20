<?php
declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\AutoImportRules;
use OCA\CoBudget\Service\AutoImportRunner;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Lock\LockedException;

class AutoImportController extends Controller {
	use WorkspaceAwareTrait;

	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private IDBConnection $db,
		private IConfig $config,
		private AutoImportRules $rules,
		private IL10N $l10n,
		private AutoImportRunner $runner,
	) {
		parent::__construct($appName, $request);
		$this->userId = $userSession->getUser()?->getUID();
		$this->initWorkspace();
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function index(bool $includeActivity = false): DataResponse {
		return $this->inWorkspace(function (int $workspaceId) use ($includeActivity): DataResponse {
			$rules = $this->rules->forWorkspace($this->userId, $workspaceId);
			return new DataResponse($includeActivity ? [
				'rules' => $rules,
				'activeElsewhere' => AutoImportRules::hasActiveImports($this->db, $this->userId, $workspaceId),
			] : $rules);
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 12, period: 60)]
	public function runNow(int $id): DataResponse {
		return $this->inWorkspace(function (int $workspaceId) use ($id): DataResponse {
			// Reject unrelated IDs before contending with a running job. Recheck the current row under its lock.
			$this->rules->forOwner($id, $this->userId, $workspaceId);
			$outcome = $this->runner->runRule($id, beforeRun: function (?array $rule) use ($workspaceId): void {
				$this->rules->assertOwner($rule, $this->userId, $workspaceId);
				if (!$rule['enabled'] || !$rule['folder_id']) {
					throw new \DomainException('Select a folder and enable the import first.', Http::STATUS_CONFLICT);
				}
				if ($rule['project_id'] !== null) $this->validateProject($rule['project_id']);
			});
			if ($outcome === 'locking_disabled') {
				throw new \DomainException('Starting an import requires Nextcloud file locking.', Http::STATUS_SERVICE_UNAVAILABLE);
			}
			return new DataResponse(['outcome' => $outcome, 'rule' => $this->rules->forOwner($id, $this->userId, $workspaceId)]);
		});
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function create(string $folder, int $intervalMinutes, string $notifyMode, bool $enabled, string $timezone, ?int $projectId = null, ?int $selectedFolderId = null): DataResponse {
		return $this->save(null, $projectId, $folder, $intervalMinutes, $notifyMode, $enabled, $timezone, $selectedFolderId);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function update(int $id, string $folder, int $intervalMinutes, string $notifyMode, bool $enabled, string $timezone, ?int $projectId = null, ?int $selectedFolderId = null): DataResponse {
		return $this->save($id, $projectId, $folder, $intervalMinutes, $notifyMode, $enabled, $timezone, $selectedFolderId);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function destroy(int $id): DataResponse {
		return $this->inWorkspace(function (int $workspaceId) use ($id): DataResponse {
			$this->rules->remove($id, $this->userId, $workspaceId);
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		});
	}

	private function save(?int $id, ?int $projectId, string $folder, int $interval, string $mode, bool $enabled, string $timezone, ?int $selectedFolderId = null): DataResponse {
		return $this->inWorkspace(function(int $workspaceId) use ($id, $projectId, $folder, $interval, $mode, $enabled, $timezone, $selectedFolderId): DataResponse {
			$row = $this->rules->save($id, $this->userId, $workspaceId, $projectId, $folder, $interval, $mode, $enabled, $timezone, $this->validateProject(...), $selectedFolderId);
			return new DataResponse($row, $id === null ? Http::STATUS_CREATED : Http::STATUS_OK);
		});
	}

	private function validateProject(int $projectId): void {
		if ($this->config->getUserValue($this->userId, 'cobudget', 'enable_projects', 'yes') !== 'yes') {
			throw new \DomainException('Areas are disabled.', Http::STATUS_FORBIDDEN);
		}
		$project = $this->projectVisibleForCurrentUser($projectId);
		if ($project === null) {
			throw new \DomainException('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
		}
		if (in_array($project['is_archived'], [true, 1, '1', 't'], true)) {
			throw new \DomainException('Archived areas cannot accept new payments.', Http::STATUS_CONFLICT);
		}
		if ($this->projectHasFormerMember($projectId)) {
			throw new \DomainException('This area contains a former member. Settle existing payments and remove the former member before creating new payments.', Http::STATUS_CONFLICT);
		}
	}

	private function inWorkspace(callable $action): DataResponse {
		if ($error = $this->authErrorResponse()) return $error;
		if ($this->config->getUserValue($this->userId, 'cobudget', 'enable_payment_imports', 'no') !== 'yes') {
			return $this->errorResponse('Enable payment imports in Settings to use this feature.', Http::STATUS_FORBIDDEN);
		}
		try {
			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) return $this->errorResponse('Invalid workspace id', Http::STATUS_BAD_REQUEST);
			return $action($workspaceId);
		} catch (\DomainException $e) {
			return $this->errorResponse($e->getMessage(), $e->getCode());
		} catch (LockedException) {
			return $this->errorResponse('This automatic import is running. Try again shortly.', Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}
}

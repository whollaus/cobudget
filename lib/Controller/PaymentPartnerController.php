<?php
namespace OCA\CoBudget\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IGroupManager;
use OCP\AppFramework\Http;

class PaymentPartnerController extends Controller {
	use WorkspaceAwareTrait;

	private const DEFAULT_PAYMENT_PARTNERS_SEEDED_KEY = 'default_payment_partners_seeded';
	private const DEFAULT_GLOBAL_PAYMENT_PARTNERS = [
		['type' => 'income', 'name' => 'Employer'],
		['type' => 'income', 'name' => 'Family and friends'],
		['type' => 'expense', 'name' => 'Supermarket and bakery'],
		['type' => 'expense', 'name' => 'Landlord and property management'],
		['type' => 'expense', 'name' => 'Online shops'],
		['type' => 'expense', 'name' => 'Pharmacy and doctor'],
	];

	private IDBConnection $db;
	private ?string $userId;
	private IConfig $config;
	private IL10N $l10n;
	private IGroupManager $groupManager;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IConfig $config, IL10N $l10n, IGroupManager $groupManager) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->initWorkspace();
	}

	private function requireProjectOwnerForScopedMutation(?int $projectId): ?DataResponse {
		if ($projectId !== null && !$this->projectOwnerInActiveWorkspace($projectId)) {
			return $this->errorResponse('Nur der Ersteller des Bereichs darf Bereich-Einstellungen ändern.', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function requireAdmin(): ?DataResponse {
		if ($this->userId === null) {
			return $this->errorResponse('Authentication required', Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->groupManager->isAdmin($this->userId)) {
			return $this->errorResponse('Administrator permissions required', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function ensureDefaultGlobalPaymentPartners(): void {
		if ($this->config->getAppValue('cobudget', self::DEFAULT_PAYMENT_PARTNERS_SEEDED_KEY, 'no') === 'yes') {
			return;
		}

		$this->db->beginTransaction();
		try {
			foreach (self::DEFAULT_GLOBAL_PAYMENT_PARTNERS as $paymentPartner) {
				$this->seedGlobalPaymentPartner($paymentPartner['name'], $paymentPartner['type']);
			}
			$this->db->commit();
			$this->config->setAppValue('cobudget', self::DEFAULT_PAYMENT_PARTNERS_SEEDED_KEY, 'yes');
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function seedGlobalPaymentPartner(string $name, string $type): void {
		$name = $this->l10n->t($name);
		if ($this->findGlobalNameMatches('cobudget_payment_partners', $name, $type) !== []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_payment_partners')
			->values([
				'name' => $qb->createNamedParameter($name),
				'type' => $qb->createNamedParameter($type),
				'is_global' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
				'is_hidden' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
			]);
		$qb->executeStatement();
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$this->ensureDefaultGlobalPaymentPartners();
			$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
			}
			$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_payment_partners', '[]');
			$hiddenIds = json_decode($hiddenJson, true) ?: [];

			$qb = $this->db->getQueryBuilder();
			$globalScope = $qb->expr()->andX(
				$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			);
			$localScope = $projectId !== null
				? $qb->expr()->andX(
					$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
					$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
				)
				: $qb->expr()->andX(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)),
					$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
					$qb->expr()->isNull('project_id')
				);
			$qb->select('*')
				->from('cobudget_payment_partners')
				->where(
					$qb->expr()->orX(
						$globalScope,
						$localScope
					)
				);

			$result = $qb->executeQuery();
			$paymentPartners = $result->fetchAll();
			$result->closeCursor();

			// Filter out hidden paymentPartners
			$filtered = array_filter($paymentPartners, function($paymentPartner) use ($hiddenIds) {
				return !in_array((int)$paymentPartner['id'], $hiddenIds);
			});

			return new DataResponse($this->addRecentUsageCounts(array_values($filtered), 'payment_partner_id', $workspaceId));
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function settingsData(?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$this->ensureDefaultGlobalPaymentPartners();
			$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
			}
			$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_payment_partners', '[]');
			$hiddenIds = json_decode($hiddenJson, true) ?: [];

			$qb = $this->db->getQueryBuilder();
			if ($projectId !== null) {
				$qb->select('*')
					->from('cobudget_payment_partners')
					->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			} else {
				$globalScope = $qb->expr()->andX(
					$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
					$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				);
			$qb->select('*')
				->from('cobudget_payment_partners')
				->where(
					$qb->expr()->orX(
						$globalScope,
							$qb->expr()->andX(
								$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)),
								$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
								$qb->expr()->isNull('project_id')
							)
					)
				);
			}

			$result = $qb->executeQuery();
			$paymentPartners = $result->fetchAll();
			$result->closeCursor();

			$qbUsed = $this->db->getQueryBuilder();
			$qbUsed->select('payment_partner_id')
				->from('cobudget_entries')
				->where($qbUsed->expr()->isNotNull('payment_partner_id'))
				->andWhere($qbUsed->expr()->eq('workspace_id', $qbUsed->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->groupBy('payment_partner_id');
			if ($projectId !== null) {
				$qbUsed
					->andWhere($qbUsed->expr()->eq('entry_kind', $qbUsed->createNamedParameter('shared')))
					->andWhere($qbUsed->expr()->eq('project_id', $qbUsed->createNamedParameter($projectId, \PDO::PARAM_INT)));
			} else {
				$qbUsed
					->andWhere($qbUsed->expr()->eq('entry_kind', $qbUsed->createNamedParameter('personal')))
					->andWhere($qbUsed->expr()->eq('user_id', $qbUsed->createNamedParameter($this->userId)));
			}
			$usedEntries = $qbUsed->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);

			$qbUsedTpl = $this->db->getQueryBuilder();
			$qbUsedTpl->select('payment_partner_id')
				->from('cobudget_templates')
				->where($qbUsedTpl->expr()->isNotNull('payment_partner_id'))
				->andWhere($qbUsedTpl->expr()->eq('workspace_id', $qbUsedTpl->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->groupBy('payment_partner_id');
			if ($projectId !== null) {
				$qbUsedTpl->andWhere($qbUsedTpl->expr()->eq('project_id', $qbUsedTpl->createNamedParameter($projectId, \PDO::PARAM_INT)));
			} else {
				$qbUsedTpl->andWhere($qbUsedTpl->expr()->eq('user_id', $qbUsedTpl->createNamedParameter($this->userId)));
			}
			$usedTemplates = $qbUsedTpl->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
			
			$usedPaymentPartnerIds = array_unique(array_merge($usedEntries, $usedTemplates));

			foreach ($paymentPartners as &$paymentPartner) {
				$paymentPartner['is_hidden'] = in_array((int)$paymentPartner['id'], $hiddenIds);
				$paymentPartner['in_use'] = in_array((int)$paymentPartner['id'], $usedPaymentPartnerIds);
			}

			return new DataResponse($paymentPartners);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $name = '', string $type = 'expense', ?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

				if ($validationError = $this->validateTypedNamePayload($name, $type)) {
					return $validationError;
				}

				if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
					return $ownerError;
				}
				$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
				if ($workspaceId === null) {
					return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
				}

				if ($existingPaymentPartner = $this->findVisibleScopedNameMatch('cobudget_payment_partners', $name, $workspaceId, null, $projectId, $type)) {
					return new DataResponse([
						'id' => (int)$existingPaymentPartner['id'],
						'name' => $existingPaymentPartner['name'],
						'is_global' => (bool)$existingPaymentPartner['is_global'],
						'type' => $type,
						'project_id' => $existingPaymentPartner['project_id'] === null ? null : (int)$existingPaymentPartner['project_id']
					]);
				}

			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_payment_partners')
				->values([
					'name' => $qb->createNamedParameter($name),
					'type' => $qb->createNamedParameter($type),
					'is_global' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'user_id' => $qb->createNamedParameter($this->userId),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'project_id' => $projectId === null
						? $qb->createNamedParameter(null, \PDO::PARAM_NULL)
						: $qb->createNamedParameter($projectId, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_payment_partners');
			return new DataResponse(['id' => $id, 'name' => $name, 'type' => $type, 'is_global' => false, 'project_id' => $projectId]);
		} catch (\Exception $e) {
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

				if ($validationError = $this->validateRequiredName($name)) {
					return $validationError;
				}

			$paymentPartner = $this->editablePaymentPartnerInActiveWorkspace($id);

			if (!$paymentPartner) {
				return $this->errorResponse('Payment partner not found or not editable', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$paymentPartner['workspace_id'];

				$projectId = $paymentPartner['project_id'] === null || $paymentPartner['project_id'] === '' ? null : (int)$paymentPartner['project_id'];
				if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
					return $ownerError;
				}

				if ($this->findVisibleScopedNameMatch('cobudget_payment_partners', $name, $workspaceId, $id, $projectId, $paymentPartner['type'] ?? 'expense') !== null) {
					return $this->errorResponse('A payment partner with this name already exists.', Http::STATUS_CONFLICT);
				}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_payment_partners')
				->set('name', $qb->createNamedParameter($name))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			if ($projectId === null) {
				$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
					->andWhere($qb->expr()->isNull('project_id'));
			} else {
				$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$qb->executeStatement();

			return new DataResponse([
				'id' => $id,
				'name' => $name,
				'type' => $paymentPartner['type'] ?? 'expense',
				'is_global' => false,
				'project_id' => $projectId
			]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
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

				if (!$this->paymentPartnerAvailableInActiveWorkspace($id)) {
					return $this->errorResponse('Payment partner not found', Http::STATUS_NOT_FOUND);
				}

			$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_payment_partners', '[]');
			$hiddenIds = json_decode($hiddenJson, true) ?: [];
			if (!in_array($id, $hiddenIds)) {
				$hiddenIds[] = $id;
				$this->config->setUserValue($this->userId, 'cobudget', 'hidden_payment_partners', json_encode($hiddenIds));
			}
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
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

				if (!$this->paymentPartnerAvailableInActiveWorkspace($id)) {
					return $this->errorResponse('Payment partner not found', Http::STATUS_NOT_FOUND);
				}

			$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_payment_partners', '[]');
			$hiddenIds = json_decode($hiddenJson, true) ?: [];
			$hiddenIds = array_values(array_filter($hiddenIds, fn($hid) => $hid !== $id));
			$this->config->setUserValue($this->userId, 'cobudget', 'hidden_payment_partners', json_encode($hiddenIds));
			return new DataResponse(['status' => 'success']);
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

				$paymentPartner = $this->editablePaymentPartnerInActiveWorkspace($id);
				if (!$paymentPartner) {
					return $this->errorResponse('Payment partner not found or not deletable', Http::STATUS_NOT_FOUND);
				}

			$workspaceId = (int)$paymentPartner['workspace_id'];
			$projectId = $paymentPartner['project_id'] === null || $paymentPartner['project_id'] === '' ? null : (int)$paymentPartner['project_id'];
			if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
				return $ownerError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('cobudget_entries')
				->where($qb->expr()->eq('payment_partner_id', $qb->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseEntries = $qb->executeQuery()->fetch();

			$qb2 = $this->db->getQueryBuilder();
			$qb2->select('id')
				->from('cobudget_templates')
				->where($qb2->expr()->eq('payment_partner_id', $qb2->createNamedParameter($id)))
				->andWhere($qb2->expr()->eq('workspace_id', $qb2->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->setMaxResults(1);
			if ($projectId === null) {
				$qb2->andWhere($qb2->expr()->eq('user_id', $qb2->createNamedParameter($this->userId)))
					->andWhere($qb2->expr()->isNull('project_id'));
			} else {
				$qb2->andWhere($qb2->expr()->eq('project_id', $qb2->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$inUseTemplates = $qb2->executeQuery()->fetch();

			if ($inUseEntries !== false || $inUseTemplates !== false) {
				return $this->errorResponse('Payment partner is still in use and cannot be deleted. Please use the hide function instead.', Http::STATUS_CONFLICT);
			}

			$qb3 = $this->db->getQueryBuilder();
			$qb3->delete('cobudget_payment_partners')
				->where($qb3->expr()->eq('id', $qb3->createNamedParameter($id)))
				->andWhere($qb3->expr()->eq('workspace_id', $qb3->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->andWhere($qb3->expr()->eq('is_global', $qb3->createNamedParameter(false, \PDO::PARAM_BOOL)));
			if ($projectId === null) {
				$qb3->andWhere($qb3->expr()->eq('user_id', $qb3->createNamedParameter($this->userId)))
					->andWhere($qb3->expr()->isNull('project_id'));
			} else {
				$qb3->andWhere($qb3->expr()->eq('project_id', $qb3->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$qb3->executeStatement();
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	// ---------------------------------------------------------
	// ADMIN API
	// ---------------------------------------------------------

	private function getAdminGlobalPaymentPartner(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_payment_partners')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
			->setMaxResults(1);

		$row = $qb->executeQuery()->fetch();
		return $row ?: null;
	}

	public function adminIndex(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$this->ensureDefaultGlobalPaymentPartners();
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('cobudget_payment_partners')
				->where($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$result = $qb->executeQuery();
			$paymentPartners = $result->fetchAll();
			$result->closeCursor();
			return new DataResponse($paymentPartners);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminCreate(string $name = '', string $type = 'expense'): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

				if ($validationError = $this->validateTypedNamePayload($name, $type)) {
					return $validationError;
				}
				$matches = $this->findGlobalNameMatches('cobudget_payment_partners', $name, $type);
				if ($this->firstVisibleGlobalNameMatch($matches) !== null) {
					return $this->errorResponse('A global payment partner with this name already exists.', Http::STATUS_CONFLICT);
				}
				if ($hiddenPaymentPartner = $this->firstHiddenGlobalNameMatch($matches)) {
					$id = (int)$hiddenPaymentPartner['id'];
					$qb = $this->db->getQueryBuilder();
					$qb->update('cobudget_payment_partners')
						->set('name', $qb->createNamedParameter($name))
						->set('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
						->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
						->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
					$qb->executeStatement();
					return new DataResponse(['id' => $id, 'name' => $name, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
				}
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_payment_partners')
				->values([
					'name' => $qb->createNamedParameter($name),
					'type' => $qb->createNamedParameter($type),
					'is_global' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
					'is_hidden' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
				]);
			$qb->executeStatement();
			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_payment_partners');
			return new DataResponse(['id' => $id, 'name' => $name, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminUpdate(int $id, string $name = ''): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredName($name)) {
				return $validationError;
			}

			$paymentPartner = $this->getAdminGlobalPaymentPartner($id);
			if (!$paymentPartner) {
				return $this->errorResponse('Payment partner not found', Http::STATUS_NOT_FOUND);
			}

			$matches = $this->findGlobalNameMatches('cobudget_payment_partners', $name, $paymentPartner['type'] ?? 'expense', $id);
			if ($matches !== []) {
				return $this->errorResponse('A global payment partner with this name already exists.', Http::STATUS_CONFLICT);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_payment_partners')
				->set('name', $qb->createNamedParameter($name))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse([
				'id' => $id,
				'name' => $name,
				'type' => $paymentPartner['type'] ?? 'expense',
				'is_global' => true,
				'is_hidden' => (bool)($paymentPartner['is_hidden'] ?? false),
			]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminHide(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if (!$this->getAdminGlobalPaymentPartner($id)) {
				return $this->errorResponse('Payment partner not found', Http::STATUS_NOT_FOUND);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_payment_partners')
				->set('is_hidden', $qb->createNamedParameter(true, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminUnhide(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$paymentPartner = $this->getAdminGlobalPaymentPartner($id);
			if (!$paymentPartner) {
				return $this->errorResponse('Payment partner not found', Http::STATUS_NOT_FOUND);
			}

			$matches = $this->findGlobalNameMatches('cobudget_payment_partners', (string)$paymentPartner['name'], $paymentPartner['type'] ?? 'expense', $id);
			if ($this->firstVisibleGlobalNameMatch($matches) !== null) {
				return $this->errorResponse('A visible global payment partner with this name already exists.', Http::STATUS_CONFLICT);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_payment_partners')
				->set('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminDestroy(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('cobudget_entries')
				->where($qb->expr()->eq('payment_partner_id', $qb->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseEntries = $qb->executeQuery()->fetch();

			$qb2 = $this->db->getQueryBuilder();
			$qb2->select('id')
				->from('cobudget_templates')
				->where($qb2->expr()->eq('payment_partner_id', $qb2->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseTemplates = $qb2->executeQuery()->fetch();

			if ($inUseEntries !== false || $inUseTemplates !== false) {
				return $this->errorResponse('Payment partner is still in use and cannot be deleted.', Http::STATUS_CONFLICT);
			}

			$qb3 = $this->db->getQueryBuilder();
			$qb3->delete('cobudget_payment_partners')
				->where($qb3->expr()->eq('id', $qb3->createNamedParameter($id)))
				->andWhere($qb3->expr()->eq('is_global', $qb3->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb3->executeStatement();
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}
}

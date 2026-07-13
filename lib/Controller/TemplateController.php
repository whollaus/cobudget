<?php
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\ParticipantService;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\AppFramework\Http;
use OCP\IConfig;

class TemplateController extends Controller {
	use WorkspaceAwareTrait;

	private IDBConnection $db;
	private ?string $userId;
	private IConfig $config;
	private ParticipantService $participantService;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IConfig $config, ParticipantService $participantService) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->participantService = $participantService;
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
			if (!$this->templatesEnabled()) {
				return $this->errorResponse('Vorlagen sind deaktiviert', Http::STATUS_FORBIDDEN);
			}

			$workspaceId = $this->getWorkspaceId();
			$qb = $this->db->getQueryBuilder();
			$qb->select('t.*', 'c.name AS category_name', 'p.name AS paymentPartner')
				->from('cobudget_templates', 't')
				->leftJoin('t', 'cobudget_categories', 'c', $qb->expr()->eq('t.category_id', 'c.id'))
				->leftJoin('t', 'cobudget_payment_partners', 'p', $qb->expr()->eq('t.payment_partner_id', 'p.id'))
					->leftJoin('t', 'cobudget_members', 'm', $qb->expr()->andX(
						$qb->expr()->eq('t.project_id', 'm.project_id'),
						$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)),
						$qb->expr()->eq('m.personal_workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
					))
				->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($this->userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->andX(
						$qb->expr()->isNull('t.project_id'),
						$qb->expr()->eq('t.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
					),
					$qb->expr()->andX(
						$qb->expr()->isNotNull('t.project_id'),
						$qb->expr()->isNotNull('m.user_id')
					)
				))
				->orderBy('t.usage_count', 'DESC')
				->addOrderBy('t.name', 'ASC');

			$result = $qb->executeQuery();
			$templates = $result->fetchAll();
			$result->closeCursor();

			// Convert boolean and numbers properly
			foreach ($templates as &$t) {
				$t = $this->normalizeAmountRow($t);
				$t['id'] = (int)$t['id'];
				$t['category_id'] = $t['category_id'] !== null ? (int)$t['category_id'] : null;
				$t['project_id'] = $t['project_id'] !== null ? (int)$t['project_id'] : null;
				$t['payment_partner_id'] = $t['payment_partner_id'] !== null ? (int)$t['payment_partner_id'] : null;
				$t['usage_count'] = (int)($t['usage_count'] ?? 0);
				$t['is_subscription'] = $this->dbBool($t['is_subscription'] ?? false);
				$t['is_fixed_cost'] = $this->dbBool($t['is_fixed_cost'] ?? false);
				$t['is_child_related'] = $this->dbBool($t['is_child_related'] ?? false);
				$t['is_important'] = $this->dbBool($t['is_important'] ?? false);
				$t['needs_review'] = $this->dbBool($t['needs_review'] ?? false);
				$t['is_tax_relevant'] = $this->dbBool($t['is_tax_relevant'] ?? false);
			}

			return new DataResponse($templates);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(
		string $name,
		?string $description = null,
		string $type = 'expense',
		?float $amount = null,
		?int $categoryId = null,
		?int $paymentPartnerId = null,
		?int $projectId = null,
		bool $isSubscription = false,
		bool $isFixedCost = false,
		bool $isChildRelated = false,
		bool $isImportant = false,
		bool $needsReview = false,
		bool $isTaxRelevant = false,
		?string $splitMode = null,
		?string $splitUserId = null
	): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->templatesEnabled()) {
				return $this->errorResponse('Vorlagen sind deaktiviert', Http::STATUS_FORBIDDEN);
			}

				$amountCents = null;
					if ($validationError = $this->validateTemplatePayload($name, $description, $type, $amount, $amountCents, $categoryId, $paymentPartnerId, $projectId)) {
					return $validationError;
				}
			if ($validationError = $this->validateSplitMode($splitMode)) {
				return $validationError;
			}
			if ($validationError = $this->validateProjectSplitUser($projectId, $splitMode, $splitUserId, (string)$this->userId)) {
				return $validationError;
			}
			if ($splitUserId !== null && !$this->participantService->isActive($splitUserId)) {
				return $this->errorResponse('A former or inactive member cannot receive a new payment allocation.', Http::STATUS_BAD_REQUEST);
			}
			if ($type !== 'expense') {
				$isSubscription = false;
				$isFixedCost = false;
			}

			$workspaceId = $this->workspaceIdForEntryScope($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or no permission', Http::STATUS_BAD_REQUEST);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_templates')
				->values([
					'user_id' => $qb->createNamedParameter($this->userId),
					'name' => $qb->createNamedParameter($name),
					'description' => $qb->createNamedParameter($description),
					'type' => $qb->createNamedParameter($type),
					'amount' => $qb->createNamedParameter($this->centsToAmountString($amountCents), $amountCents === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'amount_cents' => $qb->createNamedParameter($amountCents, $amountCents === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'category_id' => $qb->createNamedParameter($categoryId),
					'payment_partner_id' => $qb->createNamedParameter($paymentPartnerId),
					'project_id' => $qb->createNamedParameter($projectId),
					'split_mode' => $qb->createNamedParameter($splitMode),
					'split_user_id' => $qb->createNamedParameter($splitUserId, $splitUserId === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'is_subscription' => $qb->createNamedParameter($isSubscription, \PDO::PARAM_BOOL),
					'is_fixed_cost' => $qb->createNamedParameter($isFixedCost, \PDO::PARAM_BOOL),
					'is_child_related' => $qb->createNamedParameter($isChildRelated, \PDO::PARAM_BOOL),
					'is_important' => $qb->createNamedParameter($isImportant, \PDO::PARAM_BOOL),
					'needs_review' => $qb->createNamedParameter($needsReview, \PDO::PARAM_BOOL),
					'is_tax_relevant' => $qb->createNamedParameter($isTaxRelevant, \PDO::PARAM_BOOL),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)
				]);

			$qb->executeStatement();
			$id = $this->db->lastInsertId('*PREFIX*cobudget_templates');

			return new DataResponse(['id' => (int)$id]);
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
				if (!$this->templatesEnabled()) {
					return $this->errorResponse('Vorlagen sind deaktiviert', Http::STATUS_FORBIDDEN);
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				if (!$this->templateOwnedInActiveWorkspace($id)) {
					return new DataResponse(['error' => 'Template not found or no permission'], Http::STATUS_NOT_FOUND);
				}

			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_templates')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
			
			$deleted = $qb->executeStatement();

			if ($deleted === 0) {
				return new DataResponse(['error' => 'Template not found or no permission'], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse(['status' => 'ok']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function markUsed(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->templatesEnabled()) {
				return $this->errorResponse('Vorlagen sind deaktiviert', Http::STATUS_FORBIDDEN);
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if (!$this->templateOwnedInActiveWorkspace($id)) {
				return new DataResponse(['error' => 'Template not found or no permission'], Http::STATUS_NOT_FOUND);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_templates')
				->set('usage_count', $qb->createFunction('COALESCE(usage_count, 0) + 1'))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));
			$updated = $qb->executeStatement();

			if ($updated === 0) {
				return new DataResponse(['error' => 'Template not found or no permission'], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse(['status' => 'ok']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1';
	}

	private function templatesEnabled(): bool {
		return $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_templates', 'yes') === 'yes';
	}
}

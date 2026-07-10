<?php

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\BudgetSnapshotService;
use OCA\CoBudget\Service\EntryShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class BudgetController extends Controller {
	use WorkspaceAwareTrait;

	private const TAG_COLUMNS = [
		'important' => 'is_important',
		'review' => 'needs_review',
		'fixedCosts' => 'is_fixed_cost',
		'childRelated' => 'is_child_related',
		'subscriptions' => 'is_subscription',
		'taxRelevant' => 'is_tax_relevant',
	];

	private IDBConnection $db;
	private BudgetSnapshotService $budgetSnapshotService;
	private EntryShareService $entryShareService;
	private ?string $userId;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, BudgetSnapshotService $budgetSnapshotService, EntryShareService $entryShareService, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$this->budgetSnapshotService = $budgetSnapshotService;
		$this->entryShareService = $entryShareService;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->l10n = $l10n;
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

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found', Http::STATUS_BAD_REQUEST);
			}

			return new DataResponse($this->loadFormattedGoals($workspaceId));
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $name = '', $amount = 0, string $period = 'year', string $mode = 'flexible', array $criteria = []): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found', Http::STATUS_BAD_REQUEST);
			}

			$payload = $this->validateBudgetPayload($name, $amount, $period, $mode, $criteria, $workspaceId);
			if ($payload instanceof DataResponse) {
				return $payload;
			}

			$now = time();
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_budget_goals')
				->values([
					'user_id' => $qb->createNamedParameter($this->userId),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'name' => $qb->createNamedParameter($payload['name']),
					'amount_cents' => $qb->createNamedParameter($payload['amountCents'], \PDO::PARAM_INT),
					'period' => $qb->createNamedParameter($payload['period']),
					'mode' => $qb->createNamedParameter($payload['mode']),
					'criteria_json' => $qb->createNamedParameter(json_encode($payload['criteria'])),
					'created_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_budget_goals');
			$goal = $this->loadBudgetGoal($id, $workspaceId);
			if ($goal === null) {
				return $this->errorResponse('Budget goal could not be loaded', Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			return new DataResponse($this->formatGoal($goal, $workspaceId), Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $name = '', $amount = 0, string $period = 'year', string $mode = 'flexible', array $criteria = []): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found', Http::STATUS_BAD_REQUEST);
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			$currentGoal = $this->loadBudgetGoal($id, $workspaceId);
			if ($currentGoal === null) {
				return $this->errorResponse('Budget goal not found', Http::STATUS_NOT_FOUND);
			}

			$payload = $this->validateBudgetPayload($name, $amount, $period, $mode, $criteria, $workspaceId);
			if ($payload instanceof DataResponse) {
				return $payload;
			}

			$this->db->beginTransaction();
			try {
				$this->budgetSnapshotService->snapshotGoalForCurrentPeriod((string)$this->userId, $currentGoal, 'changed');

				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_budget_goals')
					->set('name', $qb->createNamedParameter($payload['name']))
					->set('amount_cents', $qb->createNamedParameter($payload['amountCents'], \PDO::PARAM_INT))
					->set('period', $qb->createNamedParameter($payload['period']))
					->set('mode', $qb->createNamedParameter($payload['mode']))
					->set('criteria_json', $qb->createNamedParameter(json_encode($payload['criteria'])))
					->set('updated_at', $qb->createNamedParameter(time(), \PDO::PARAM_INT))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
				$qb->executeStatement();

				$goal = $this->loadBudgetGoal($id, $workspaceId);
				if ($goal === null) {
					throw new \RuntimeException('Budget goal could not be loaded after update.');
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse($this->formatGoal($goal, $workspaceId));
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

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found', Http::STATUS_BAD_REQUEST);
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$currentGoal = $this->loadBudgetGoal($id, $workspaceId);
			if ($currentGoal === null) {
				return $this->errorResponse('Budget goal not found', Http::STATUS_NOT_FOUND);
			}

			$this->db->beginTransaction();
			try {
				$this->budgetSnapshotService->snapshotGoalForCurrentPeriod((string)$this->userId, $currentGoal, 'deleted');

				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_budget_goals')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
					->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
				$deleted = $qb->executeStatement();
				if ($deleted < 1) {
					throw new \RuntimeException('Budget goal could not be deleted.');
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function validateBudgetPayload(string $name, $amount, string $period, string $mode, array $criteria, int $workspaceId) {
		if ($error = $this->validateRequiredName($name, 'Name ist erforderlich', 128)) {
			return $error;
		}

		$amountCents = null;
		if ($error = $this->validateAmountCents($amount, $amountCents, false, 'Ungültiges Budget')) {
			return $error;
		}
		if ($amountCents === null || $amountCents <= 0) {
			return $this->errorResponse('Budget must be greater than 0', Http::STATUS_BAD_REQUEST);
		}

		$period = trim($period);
		if (!in_array($period, ['month', 'year'], true)) {
			return $this->errorResponse('Invalid budget period', Http::STATUS_BAD_REQUEST);
		}

		$mode = trim($mode);
		if (!in_array($mode, ['flexible', 'hard'], true)) {
			return $this->errorResponse('Invalid budget mode', Http::STATUS_BAD_REQUEST);
		}

		$criteria = $this->normalizeCriteria($criteria);
		if ($error = $this->validateCriteria($criteria, $workspaceId)) {
			return $error;
		}

		return [
			'name' => $name,
			'amountCents' => $amountCents,
			'period' => $period,
			'mode' => $mode,
			'criteria' => $criteria,
		];
	}

	private function normalizeCriteria(array $criteria): array {
		if (isset($criteria['rules']) && is_array($criteria['rules'])) {
			return [
				'rules' => $this->uniqueRules(array_map(fn($rule): array => $this->normalizeRule($rule), $criteria['rules'])),
			];
		}

		$rules = [];
		foreach ($this->positiveIntegerList($criteria['projectIds'] ?? []) as $projectId) {
			$rules[] = ['projectId' => $projectId, 'categoryId' => null, 'tag' => ''];
		}
		foreach ($this->positiveIntegerList($criteria['categoryIds'] ?? []) as $categoryId) {
			$rules[] = ['projectId' => null, 'categoryId' => $categoryId, 'tag' => ''];
		}
		foreach ($this->tagList($criteria['tags'] ?? []) as $tag) {
			$rules[] = ['projectId' => null, 'categoryId' => null, 'tag' => $tag];
		}

		return [
			'rules' => $this->uniqueRules($rules),
		];
	}

	private function normalizeRule($rule): array {
		if (!is_array($rule)) {
			return ['projectId' => null, 'categoryId' => null, 'tag' => ''];
		}

		$projectId = $rule['projectId'] ?? $rule['project_id'] ?? null;
		$categoryId = $rule['categoryId'] ?? $rule['category_id'] ?? null;
		$tag = (string)($rule['tag'] ?? '');

		return [
			'projectId' => is_numeric($projectId) && (int)$projectId > 0 ? (int)$projectId : null,
			'categoryId' => is_numeric($categoryId) && (int)$categoryId > 0 ? (int)$categoryId : null,
			'tag' => array_key_exists($tag, self::TAG_COLUMNS) ? $tag : '',
		];
	}

	private function uniqueRules(array $rules): array {
		$normalized = [];
		$seen = [];
		foreach ($rules as $rule) {
			$rule = $this->normalizeRule($rule);
			if ($rule['projectId'] === null && $rule['categoryId'] === null && $rule['tag'] === '') {
				continue;
			}

			$key = ($rule['projectId'] ?? '') . ':' . ($rule['categoryId'] ?? '') . ':' . $rule['tag'];
			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$normalized[] = $rule;
		}

		return $normalized;
	}

	private function positiveIntegerList($values): array {
		if (!is_array($values)) {
			return [];
		}

		$normalized = [];
		foreach ($values as $value) {
			if (is_numeric($value) && (int)$value > 0) {
				$normalized[] = (int)$value;
			}
		}

		return array_values(array_unique($normalized));
	}

	private function tagList($values): array {
		if (!is_array($values)) {
			return [];
		}

		$allowed = array_keys(self::TAG_COLUMNS);
		$normalized = [];
		foreach ($values as $value) {
			$value = (string)$value;
			if (in_array($value, $allowed, true)) {
				$normalized[] = $value;
			}
		}

		return array_values(array_unique($normalized));
	}

	private function validateCriteria(array $criteria, int $workspaceId): ?DataResponse {
		foreach ($criteria['rules'] as $rule) {
			if ($rule['projectId'] !== null && !$this->projectMemberInActiveWorkspace($rule['projectId'])) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_BAD_REQUEST);
			}

			if ($rule['categoryId'] !== null && !$this->categorySelectableForBudget($rule['categoryId'], $workspaceId)) {
				return $this->errorResponse('Category not found or not in the active workspace', Http::STATUS_BAD_REQUEST);
			}
		}

		return null;
	}

	private function categorySelectableForBudget(int $categoryId, int $workspaceId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.id')
			->from('cobudget_categories', 'c')
			->leftJoin('c', 'cobudget_projects', 'pr', $qb->expr()->eq('c.project_id', 'pr.id'))
			->leftJoin('c', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('c.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('c.id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.type', $qb->createNamedParameter('expense')))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->eq('c.is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
					$qb->expr()->eq('c.is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				),
				$qb->expr()->andX(
					$qb->expr()->eq('c.user_id', $qb->createNamedParameter($this->userId)),
					$qb->expr()->eq('c.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
					$qb->expr()->isNull('c.project_id')
				),
				$qb->expr()->andX(
					$qb->expr()->isNotNull('c.project_id'),
					$qb->expr()->eq('c.workspace_id', 'pr.workspace_id'),
					$qb->expr()->isNotNull('m.user_id')
				)
			))
			->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	private function loadFormattedGoals(int $workspaceId): array {
		$goals = $this->loadBudgetGoals($workspaceId);
		if ($goals === []) {
			return [];
		}

		$entries = $this->loadVisibleExpenseEntries($workspaceId);
		$shares = $this->loadProjectShares($workspaceId);

		return array_map(fn(array $goal): array => $this->formatGoal($goal, $workspaceId, $entries, $shares), $goals);
	}

	private function loadBudgetGoals(int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_budget_goals')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->orderBy('name', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $this->entryShareService->attachPersonalShares($rows, (string)$this->userId);
	}

	private function loadBudgetGoal(int $id, int $workspaceId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_budget_goals')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	private function formatGoal(array $goal, int $workspaceId, ?array $entries = null, ?array $shares = null): array {
		$entries ??= $this->loadVisibleExpenseEntries($workspaceId);
		$shares ??= $this->loadProjectShares($workspaceId);
		$criteria = $this->criteriaFromRow($goal);
		$amountCents = (int)($goal['amount_cents'] ?? 0);

		return [
			'id' => (int)$goal['id'],
			'name' => (string)$goal['name'],
			'amount' => $this->centsToAmount($amountCents),
			'amount_cents' => $amountCents,
			'period' => (string)$goal['period'],
			'mode' => (string)$goal['mode'],
			'criteria' => $criteria,
			'evaluation' => $this->evaluateGoal($amountCents, (string)$goal['period'], (string)$goal['mode'], $criteria, $entries, $shares),
		];
	}

	private function criteriaFromRow(array $goal): array {
		$decoded = json_decode((string)($goal['criteria_json'] ?? '{}'), true);
		if (!is_array($decoded)) {
			$decoded = [];
		}

		return $this->normalizeCriteria($decoded);
	}

	private function evaluateGoal(int $amountCents, string $period, string $mode, array $criteria, array $entries, array $shares): array {
		[$periodStart, $periodEnd] = $this->periodBounds($period);
		$now = time();
		$spentCents = 0;

		foreach ($entries as $entry) {
			$date = (int)($entry['date'] ?? 0);
			if ($date < $periodStart || $date > $now || $date >= $periodEnd) {
				continue;
			}
			if (!$this->entryMatchesCriteria($entry, $criteria)) {
				continue;
			}

			$spentCents += $this->entryPersonalCents($entry, $shares);
		}

		$totalSeconds = max(1, $periodEnd - $periodStart);
		$elapsedSeconds = min($totalSeconds, max(0, $now - $periodStart));
		$elapsedRatio = $elapsedSeconds / $totalSeconds;
		$plannedCents = (int)round($amountCents * $elapsedRatio);
		$bufferCents = $plannedCents - $spentCents;
		$remainingCents = $amountCents - $spentCents;
		$forecastCents = $elapsedRatio > 0 ? (int)round($spentCents / $elapsedRatio) : $spentCents;
		$progressPercent = $amountCents > 0 ? round(($spentCents / $amountCents) * 100, 1) : 0.0;
		$status = $this->budgetStatus($spentCents, $amountCents, $forecastCents, $bufferCents, $mode);

		return [
			'period_start' => $periodStart,
			'period_end' => $periodEnd,
			'spent' => $this->centsToAmount($spentCents),
			'spent_cents' => $spentCents,
			'planned' => $this->centsToAmount($plannedCents),
			'planned_cents' => $plannedCents,
			'buffer' => $this->centsToAmount($bufferCents),
			'buffer_cents' => $bufferCents,
			'remaining' => $this->centsToAmount($remainingCents),
			'remaining_cents' => $remainingCents,
			'forecast' => $this->centsToAmount($forecastCents),
			'forecast_cents' => $forecastCents,
			'progress_percent' => min(999, $progressPercent),
			'elapsed_percent' => round($elapsedRatio * 100, 1),
			'status' => $status,
		];
	}

	private function budgetStatus(int $spentCents, int $amountCents, int $forecastCents, int $bufferCents, string $mode): string {
		if ($spentCents > $amountCents) {
			return 'exceeded';
		}
		if ($mode === 'hard') {
			return $spentCents >= (int)round($amountCents * 0.8) ? 'warning' : 'ok';
		}
		if ($bufferCents < 0 || $forecastCents > $amountCents) {
			return 'warning';
		}

		return 'ok';
	}

	private function periodBounds(string $period): array {
		if ($period === 'month') {
			$start = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
			$end = strtotime('+1 month', $start);
			return [$start, $end === false ? $start + 31 * 86400 : $end];
		}

		$start = mktime(0, 0, 0, 1, 1, (int)date('Y'));
		$end = mktime(0, 0, 0, 1, 1, (int)date('Y') + 1);
		return [$start, $end];
	}

	private function loadVisibleExpenseEntries(int $workspaceId): array {
		$yearStart = mktime(0, 0, 0, 1, 1, (int)date('Y'));
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.id',
			'e.user_id',
			'e.project_id',
			'e.type',
			'e.amount',
			'e.amount_cents',
			'e.date',
			'e.category_id',
			'e.split_mode',
			'e.split_user_id',
			'e.is_subscription',
			'e.is_fixed_cost',
			'e.is_child_related',
			'e.is_important',
			'e.needs_review',
			'e.is_tax_relevant'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('e.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('e.type', $qb->createNamedParameter('expense')))
			->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($yearStart, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId)),
					$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				),
				$qb->expr()->isNotNull('m.user_id')
			));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function loadProjectShares(int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.project_id', 'm.user_id', 'm.share_basis_points')
			->from('cobudget_members', 'm')
			->innerJoin('m', 'cobudget_projects', 'p', $qb->expr()->eq('m.project_id', 'p.id'))
			->innerJoin('p', 'cobudget_members', 'me', $qb->expr()->andX(
				$qb->expr()->eq('p.id', 'me.project_id'),
				$qb->expr()->eq('me.user_id', $qb->createNamedParameter($this->userId))
			))
			->orderBy('m.project_id', 'ASC')
			->addOrderBy('m.id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$membersByProject = [];
		foreach ($rows as $row) {
			$membersByProject[(int)$row['project_id']][] = $row;
		}

		$sharesByProject = [];
		foreach ($membersByProject as $projectId => $members) {
			$sharesByProject[$projectId] = $this->memberShareBasisPoints($members);
		}

		return $sharesByProject;
	}

	private function entryPersonalCents(array $entry, array $sharesByProject): int {
		$amountCents = $this->amountCentsFromRow($entry) ?? 0;
		$projectId = empty($entry['project_id']) ? null : (int)$entry['project_id'];
		if ($projectId === null) {
			return $amountCents;
		}
		if (array_key_exists('snapshot_share_cents', $entry)) {
			return max(0, (int)$entry['snapshot_share_cents']);
		}

		$shares = $sharesByProject[$projectId] ?? [(string)$this->userId => 10000];
		return $this->entryShareCentsForUser($entry, (string)$this->userId, $amountCents, $shares);
	}

	private function entryMatchesCriteria(array $entry, array $criteria): bool {
		$rules = $criteria['rules'] ?? [];
		if ($rules === []) {
			return true;
		}

		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			if (($rule['projectId'] ?? null) !== null && (int)($entry['project_id'] ?? 0) !== (int)$rule['projectId']) {
				continue;
			}

			if (($rule['categoryId'] ?? null) !== null && (int)($entry['category_id'] ?? 0) !== (int)$rule['categoryId']) {
				continue;
			}

			$tag = (string)($rule['tag'] ?? '');
			$column = self::TAG_COLUMNS[$tag] ?? null;
			if ($column !== null && !$this->dbBool($entry[$column] ?? false)) {
				continue;
			}

			return true;
		}

		return false;
	}

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}

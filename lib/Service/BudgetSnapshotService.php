<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Controller\WorkspaceAwareTrait;
use OCP\IDBConnection;

class BudgetSnapshotService {
	use WorkspaceAwareTrait;

	private const TAG_COLUMNS = [
		'important' => 'is_important',
		'review' => 'needs_review',
		'fixedCosts' => 'is_fixed_cost',
		'childRelated' => 'is_child_related',
		'subscriptions' => 'is_subscription',
		'taxRelevant' => 'is_tax_relevant',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function snapshotGoalForCurrentPeriod(string $userId, array $goal, string $reason): ?array {
		$workspaceId = (int)($goal['workspace_id'] ?? 0);
		if ($userId === '' || $workspaceId <= 0) {
			return null;
		}

		$now = time();
		[$periodStart, $periodEnd] = $this->periodBoundsForTimestamp((string)($goal['period'] ?? 'year'), $now);

		return $this->createSnapshot($userId, $goal, $workspaceId, $reason, $periodStart, $periodEnd, $now);
	}

	public function createDueSnapshots(): int {
		$goals = $this->loadAllBudgetGoals();
		$created = 0;
		$now = time();

		foreach ($goals as $goal) {
			$userId = (string)($goal['user_id'] ?? '');
			$workspaceId = (int)($goal['workspace_id'] ?? 0);
			if ($userId === '' || $workspaceId <= 0) {
				continue;
			}

			[$periodStart, $periodEnd] = $this->previousClosedPeriodBounds((string)($goal['period'] ?? 'year'), $now);
			if ($periodStart <= 0 || $periodEnd <= $periodStart || $periodEnd > $now) {
				continue;
			}
			if ($this->snapshotExists((int)$goal['id'], $userId, $workspaceId, $periodStart, $periodEnd, 'period_closed')) {
				continue;
			}

			if ($this->createSnapshot($userId, $goal, $workspaceId, 'period_closed', $periodStart, $periodEnd, $now) !== null) {
				$created++;
			}
		}

		return $created;
	}

	public function history(string $userId, int $workspaceId, int $rangeStart, int $rangeEnd): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from('cobudget_budget_snapshots', 's')
			->innerJoin('s', 'cobudget_budget_goals', 'g', $qb->expr()->andX(
				$qb->expr()->eq('g.id', 's.budget_goal_id'),
				$qb->expr()->eq('g.user_id', 's.user_id'),
				$qb->expr()->eq('g.workspace_id', 's.workspace_id')
			))
			->where($qb->expr()->eq('s.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('s.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lt('s.period_start', $qb->createNamedParameter($rangeEnd, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->gt('s.period_end', $qb->createNamedParameter($rangeStart, \PDO::PARAM_INT)))
			->orderBy('s.period_start', 'DESC')
			->addOrderBy('s.created_at', 'DESC')
			->setMaxResults(24);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$items = array_map(fn(array $row): array => $this->formatSnapshot($row), $rows);
		$summary = [
			'total' => count($items),
			'ok' => 0,
			'warning' => 0,
			'exceeded' => 0,
			'bufferCents' => 0,
		];

		foreach ($items as $item) {
			$status = (string)($item['status'] ?? '');
			if (isset($summary[$status])) {
				$summary[$status]++;
			}
			$summary['bufferCents'] += (int)($item['bufferCents'] ?? 0);
		}

		return [
			'summary' => $summary,
			'items' => $items,
		];
	}

	private function createSnapshot(string $userId, array $goal, int $workspaceId, string $reason, int $periodStart, int $periodEnd, int $snapshotAt): ?array {
		$amountCents = (int)($goal['amount_cents'] ?? 0);
		if ($amountCents <= 0 || $periodStart <= 0 || $periodEnd <= $periodStart) {
			return null;
		}

		$period = (string)($goal['period'] ?? 'year');
		$mode = (string)($goal['mode'] ?? 'flexible');
		$criteriaJson = (string)($goal['criteria_json'] ?? '{}');
		$criteria = $this->criteriaFromJson($criteriaJson);
		$evaluation = $this->evaluateGoal($userId, $workspaceId, $amountCents, $period, $mode, $criteria, $periodStart, $periodEnd, $snapshotAt);

		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_budget_snapshots')
			->values([
				'budget_goal_id' => $qb->createNamedParameter((int)($goal['id'] ?? 0), \PDO::PARAM_INT),
				'user_id' => $qb->createNamedParameter($userId),
				'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
				'snapshot_reason' => $qb->createNamedParameter($this->normalizeReason($reason)),
				'goal_name' => $qb->createNamedParameter(mb_substr((string)($goal['name'] ?? 'Budgetziel'), 0, 128)),
				'amount_cents' => $qb->createNamedParameter($amountCents, \PDO::PARAM_INT),
				'period' => $qb->createNamedParameter($period),
				'mode' => $qb->createNamedParameter($mode),
				'criteria_json' => $qb->createNamedParameter($criteriaJson),
				'period_start' => $qb->createNamedParameter($periodStart, \PDO::PARAM_INT),
				'period_end' => $qb->createNamedParameter($periodEnd, \PDO::PARAM_INT),
				'spent_cents' => $qb->createNamedParameter($evaluation['spentCents'], \PDO::PARAM_INT),
				'planned_cents' => $qb->createNamedParameter($evaluation['plannedCents'], \PDO::PARAM_INT),
				'buffer_cents' => $qb->createNamedParameter($evaluation['bufferCents'], \PDO::PARAM_INT),
				'forecast_cents' => $qb->createNamedParameter($evaluation['forecastCents'], \PDO::PARAM_INT),
				'progress_tenths' => $qb->createNamedParameter($evaluation['progressTenths'], \PDO::PARAM_INT),
				'status' => $qb->createNamedParameter($evaluation['status']),
				'created_at' => $qb->createNamedParameter($snapshotAt, \PDO::PARAM_INT),
			]);
		$qb->executeStatement();

		$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_budget_snapshots');
		return $this->loadSnapshot($id) ?? null;
	}

	private function evaluateGoal(string $userId, int $workspaceId, int $amountCents, string $period, string $mode, array $criteria, int $periodStart, int $periodEnd, int $snapshotAt): array {
		$cutoff = min($snapshotAt, $periodEnd - 1);
		$entries = $this->loadVisibleExpenseEntries($userId, $workspaceId, $periodStart, $periodEnd, $cutoff);
		$shares = $this->loadProjectShares($userId, $workspaceId);
		$spentCents = 0;

		foreach ($entries as $entry) {
			if (!$this->entryMatchesCriteria($entry, $criteria)) {
				continue;
			}
			$spentCents += $this->entryPersonalCents($userId, $entry, $shares);
		}

		$totalSeconds = max(1, $periodEnd - $periodStart);
		$elapsedSeconds = min($totalSeconds, max(0, min($snapshotAt, $periodEnd) - $periodStart));
		$elapsedRatio = $elapsedSeconds / $totalSeconds;
		$plannedCents = (int)round($amountCents * $elapsedRatio);
		$bufferCents = $plannedCents - $spentCents;
		$forecastCents = $elapsedRatio > 0 ? (int)round($spentCents / $elapsedRatio) : $spentCents;
		$progressPercent = $amountCents > 0 ? ($spentCents / $amountCents) * 100 : 0;
		$status = $this->budgetStatus($spentCents, $amountCents, $forecastCents, $bufferCents, $mode);

		return [
			'spentCents' => $spentCents,
			'plannedCents' => $plannedCents,
			'bufferCents' => $bufferCents,
			'forecastCents' => $forecastCents,
			'progressTenths' => (int)round(min(9999, $progressPercent * 10)),
			'status' => $status,
		];
	}

	private function loadAllBudgetGoals(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_budget_goals')
			->orderBy('id', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function loadSnapshot(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_budget_snapshots')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	private function snapshotExists(int $goalId, string $userId, int $workspaceId, int $periodStart, int $periodEnd, string $reason): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_budget_snapshots')
			->where($qb->expr()->eq('budget_goal_id', $qb->createNamedParameter($goalId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('period_start', $qb->createNamedParameter($periodStart, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('period_end', $qb->createNamedParameter($periodEnd, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('snapshot_reason', $qb->createNamedParameter($reason)))
			->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	private function loadVisibleExpenseEntries(string $userId, int $workspaceId, int $periodStart, int $periodEnd, int $cutoff): array {
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
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId))
			))
			->where($qb->expr()->eq('e.type', $qb->createNamedParameter('expense')))
			->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($periodStart, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lt('e.date', $qb->createNamedParameter($periodEnd, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter($cutoff, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				),
				$qb->expr()->isNotNull('m.user_id')
			))
			->groupBy('e.id');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function loadProjectShares(string $userId, int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.project_id', 'm.user_id', 'm.share_basis_points')
			->from('cobudget_members', 'm')
			->innerJoin('m', 'cobudget_projects', 'p', $qb->expr()->eq('m.project_id', 'p.id'))
			->innerJoin('p', 'cobudget_members', 'me', $qb->expr()->andX(
				$qb->expr()->eq('p.id', 'me.project_id'),
				$qb->expr()->eq('me.user_id', $qb->createNamedParameter($userId))
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

	private function entryPersonalCents(string $userId, array $entry, array $sharesByProject): int {
		$amountCents = $this->amountCentsFromRow($entry) ?? 0;
		$projectId = empty($entry['project_id']) ? null : (int)$entry['project_id'];
		if ($projectId === null) {
			return $amountCents;
		}

		$shares = $sharesByProject[$projectId] ?? [$userId => 10000];
		return $this->entryShareCentsForUser($entry, $userId, $amountCents, $shares);
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

	private function criteriaFromJson(string $criteriaJson): array {
		$decoded = json_decode($criteriaJson === '' ? '{}' : $criteriaJson, true);
		if (!is_array($decoded)) {
			$decoded = [];
		}

		return $this->normalizeCriteria($decoded);
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

	private function formatSnapshot(array $row): array {
		return [
			'id' => (int)($row['id'] ?? 0),
			'budgetGoalId' => (int)($row['budget_goal_id'] ?? 0),
			'name' => (string)($row['goal_name'] ?? ''),
			'reason' => (string)($row['snapshot_reason'] ?? ''),
			'period' => (string)($row['period'] ?? ''),
			'mode' => (string)($row['mode'] ?? ''),
			'amountCents' => (int)($row['amount_cents'] ?? 0),
			'spentCents' => (int)($row['spent_cents'] ?? 0),
			'plannedCents' => (int)($row['planned_cents'] ?? 0),
			'bufferCents' => (int)($row['buffer_cents'] ?? 0),
			'forecastCents' => (int)($row['forecast_cents'] ?? 0),
			'progressPercent' => round(((int)($row['progress_tenths'] ?? 0)) / 10, 1),
			'status' => (string)($row['status'] ?? 'ok'),
			'periodStart' => (int)($row['period_start'] ?? 0),
			'periodEnd' => (int)($row['period_end'] ?? 0),
			'createdAt' => (int)($row['created_at'] ?? 0),
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

	private function periodBoundsForTimestamp(string $period, int $timestamp): array {
		if ($period === 'month') {
			$start = mktime(0, 0, 0, (int)date('n', $timestamp), 1, (int)date('Y', $timestamp));
			$end = strtotime('+1 month', $start);
			return [$start, $end === false ? $start + 31 * 86400 : $end];
		}

		$start = mktime(0, 0, 0, 1, 1, (int)date('Y', $timestamp));
		$end = mktime(0, 0, 0, 1, 1, (int)date('Y', $timestamp) + 1);
		return [$start, $end];
	}

	private function previousClosedPeriodBounds(string $period, int $now): array {
		[$currentStart] = $this->periodBoundsForTimestamp($period, $now);
		if ($period === 'month') {
			$previousStart = strtotime('-1 month', $currentStart);
			return [$previousStart === false ? $currentStart - 31 * 86400 : $previousStart, $currentStart];
		}

		$year = (int)date('Y', $currentStart) - 1;
		return [
			mktime(0, 0, 0, 1, 1, $year),
			mktime(0, 0, 0, 1, 1, $year + 1),
		];
	}

	private function normalizeReason(string $reason): string {
		return in_array($reason, ['period_closed', 'changed', 'deleted'], true) ? $reason : 'changed';
	}

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}
}

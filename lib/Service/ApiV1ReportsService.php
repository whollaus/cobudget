<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Exception\ApiV1HttpException;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Stateless service implementing reports and budget goals for external API v1.
 * Strictly enforces `reports:read` + full read coverage of personal + active areas
 * and excludes archived areas (and all area rows when `enable_projects === 'no'`)
 * at the SQL and rule-filtering layers.
 */
final class ApiV1ReportsService {
	public const MAX_CUSTOM_SUMMARY_RANGE_SECONDS = 316_224_000;

	private const PAGE_SIZE = 512;

	private const TAG_COLUMNS = [
		'important' => 'is_important',
		'review' => 'needs_review',
		'fixedCosts' => 'is_fixed_cost',
		'childRelated' => 'is_child_related',
		'subscriptions' => 'is_subscription',
		'taxRelevant' => 'is_tax_relevant',
		'insurance' => 'is_insurance',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * @param array{period: string, date_from: ?int, date_to: ?int, include_future: bool} $params
	 * @return array<string, mixed>
	 */
	public function summary(array $auth, array $params): array {
		$this->assertCanUseReports($auth);
		/** @var \DateTimeZone $tz */
		$tz = $auth['timezone'];
		$now = time();

		$dateFrom = $params['date_from'] ?? null;
		$dateTo = $params['date_to'] ?? null;
		if ($dateFrom !== null || $dateTo !== null) {
			if ($dateFrom === null || $dateTo === null || $dateFrom <= 0 || $dateTo < $dateFrom || $dateTo > ApiV1EntriesService::MAX_ENTRY_TIMESTAMP) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Provide valid positive date_from and date_to timestamps with date_to >= date_from.');
			}
			if (($dateTo - $dateFrom) > self::MAX_CUSTOM_SUMMARY_RANGE_SECONDS) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Custom summary range must not exceed 10 years.');
			}
			$start = (int)$dateFrom;
			$end = (int)$dateTo + 1;
			$periodLabel = 'custom';
		} else {
			$rawPeriod = (string)($params['period'] ?? '');
			$periodLabel = $rawPeriod !== '' ? $rawPeriod : 'current-month';
			[$start, $end] = $this->resolveSummaryBounds($periodLabel, $tz, $now);
		}

		$includeFuture = !empty($params['include_future']);
		$effectiveEnd = $includeFuture ? ($end - 1) : min($end - 1, $now);
		$rows = $effectiveEnd >= $start
			? $this->loadAuthorizedPersonalReportRows($auth, $start, $effectiveEnd)
			: [];

		$aggregates = $this->aggregateRows($rows, $tz);

		return [
			'period' => $periodLabel,
			'date_from' => $start,
			'date_to' => $end - 1,
			'include_future' => $includeFuture,
			'currency' => $this->resolveCurrency((string)$auth['user_id']),
			'totals' => $aggregates['totals'],
			'by_category' => $aggregates['by_category'],
			'by_area' => $aggregates['by_area'],
			'monthly_series' => $aggregates['monthly_series'],
		];
	}

	/**
	 * @param array{year: ?int, include_future: bool} $params
	 * @return array<string, mixed>
	 */
	public function annual(array $auth, array $params): array {
		$this->assertCanUseReports($auth);
		/** @var \DateTimeZone $tz */
		$tz = $auth['timezone'];
		$now = time();
		$currentYear = (int)(new \DateTimeImmutable('@' . $now))->setTimezone($tz)->format('Y');
		$year = $params['year'] ?? $currentYear;
		if ($year < 1970 || $year > 2099) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Parameter "year" must be between 1970 and 2099.');
		}

		$startDt = (new \DateTimeImmutable('now', $tz))->setDate($year, 1, 1)->setTime(0, 0, 0);
		$endDt = $startDt->modify('+1 year');
		$start = $startDt->getTimestamp();
		$end = $endDt->getTimestamp();
		$effectiveEnd = $params['include_future'] ? ($end - 1) : min($end - 1, $now);

		$rows = $effectiveEnd >= $start
			? $this->loadAuthorizedPersonalReportRows($auth, $start, $effectiveEnd)
			: [];

		$aggregates = $this->aggregateRows($rows, $tz);
		$monthsMap = [];
		for ($m = 1; $m <= 12; $m++) {
			$key = sprintf('%04d-%02d', $year, $m);
			$monthsMap[$key] = [
				'month' => $key,
				'income_cents' => 0,
				'expense_cents' => 0,
				'balance_cents' => 0,
				'entry_count' => 0,
			];
		}
		foreach ($aggregates['monthly_series'] as $bucket) {
			if (isset($monthsMap[$bucket['month']])) {
				$monthsMap[$bucket['month']] = $bucket;
			}
		}

		return [
			'year' => $year,
			'include_future' => (bool)$params['include_future'],
			'currency' => $this->resolveCurrency((string)$auth['user_id']),
			'totals' => $aggregates['totals'],
			'months' => array_values($monthsMap),
			'by_category' => $aggregates['by_category'],
			'by_area' => $aggregates['by_area'],
		];
	}

	/** @return array<string, mixed> */
	public function budgets(array $auth): array {
		$this->assertCanUseReports($auth);
		$userId = (string)$auth['user_id'];
		$workspaceId = (int)$auth['workspace_id'];
		$enabled = $this->config->getUserValue($userId, 'cobudget', 'enable_budget_goals', 'yes') === 'yes';
		if (!$enabled) {
			return [
				'enabled' => false,
				'currency' => $this->resolveCurrency($userId),
				'goals' => [],
			];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_budget_goals')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->orderBy('name', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$goalRows = $result->fetchAll();
		$result->closeCursor();

		if ($goalRows === []) {
			return [
				'enabled' => true,
				'currency' => $this->resolveCurrency($userId),
				'goals' => [],
			];
		}

		/** @var \DateTimeZone $tz */
		$tz = $auth['timezone'];
		$now = time();
		[$ruleIndex, $goalGroups, $rulesByGoal] = $this->indexBudgetRules($goalRows, $auth);
		$spent = $this->budgetSpending($auth, $ruleIndex, $now);

		$goals = [];
		foreach ($goalRows as $goal) {
			$id = (int)$goal['id'];
			if (!isset($goalGroups[$id])) {
				continue;
			}
			$criteria = ['rules' => $rulesByGoal[$id]];
			$period = (string)($goal['period'] ?? 'year');
			[$periodStart, $periodEnd] = $this->periodBounds($period, $tz, $now);
			$spentCents = $spent[$goalGroups[$id]][$period === 'month' ? 'month' : 'year'] ?? 0;

			$amountCents = max(0, (int)($goal['amount_cents'] ?? 0));
			$mode = (string)($goal['mode'] ?? 'flexible');
			$totalSeconds = max(1, $periodEnd - $periodStart);
			$elapsedSeconds = min($totalSeconds, max(0, $now - $periodStart));
			$elapsedRatio = $elapsedSeconds / $totalSeconds;
			$plannedCents = (int)round($amountCents * $elapsedRatio);
			$bufferCents = $plannedCents - $spentCents;
			$remainingCents = $amountCents - $spentCents;
			$forecastCents = $elapsedRatio > 0 ? (int)round($spentCents / $elapsedRatio) : $spentCents;
			$progressPercent = $amountCents > 0 ? round(($spentCents / $amountCents) * 100, 1) : 0.0;

			$goals[] = [
				'id' => (int)$goal['id'],
				'name' => (string)$goal['name'],
				'amount_cents' => $amountCents,
				'amount' => round($amountCents / 100, 2),
				'period' => $period,
				'mode' => $mode,
				'criteria' => $criteria,
				'evaluation' => [
					'period_start' => $periodStart,
					'period_end' => $periodEnd,
					'spent_cents' => $spentCents,
					'spent' => round($spentCents / 100, 2),
					'planned_cents' => $plannedCents,
					'buffer_cents' => $bufferCents,
					'remaining_cents' => $remainingCents,
					'forecast_cents' => $forecastCents,
					'progress_percent' => min(999.0, $progressPercent),
					'elapsed_percent' => round($elapsedRatio * 100, 1),
					'status' => $this->budgetStatus($spentCents, $amountCents, $forecastCents, $bufferCents, $mode),
				],
			];
		}

		return [
			'enabled' => true,
			'currency' => $this->resolveCurrency($userId),
			'goals' => $goals,
		];
	}

	private function indexBudgetRules(array $goalRows, array $auth): array {
		// Index equivalent rule sets once. A payment can match several rules of the same goal.
		$categoryProjectMap = $this->loadCategoryProjectMap($goalRows);
		$ruleIndex = [];
		$goalGroups = [];
		$rulesByGoal = [];
		foreach ($goalRows as $goal) {
			$rules = $this->normalizeGoalCriteria($goal['criteria_json'] ?? '{}')['rules'];
			$rules = $this->filterGoalRulesForActiveScope($rules, $auth, $categoryProjectMap);
			if ($rules === null) {
				continue;
			}
			$id = (int)$goal['id'];
			$rulesByGoal[$id] = $rules;
			$selectors = [];
			foreach ($rules ?: [['projectId' => null, 'categoryId' => null, 'tag' => '']] as $rule) {
				$selectors[] = ($rule['projectId'] ?? '*') . '/' . ($rule['categoryId'] ?? '*') . '/' . $rule['tag'];
			}
			$selectors = array_unique($selectors);
			sort($selectors, SORT_STRING);
			$group = implode(',', $selectors);
			$goalGroups[$id] = $group;
			foreach ($selectors as $selector) {
				$ruleIndex[$selector][$group] = true;
			}
		}
		return [$ruleIndex, $goalGroups, $rulesByGoal];
	}

	private function budgetSpending(array $auth, array $ruleIndex, int $now): array {
		$spent = [];
		if ($ruleIndex === []) {
			return [];
		}
		[$yearStart] = $this->periodBounds('year', $auth['timezone'], $now);
		[$monthStart] = $this->periodBounds('month', $auth['timezone'], $now);
		foreach ($this->loadAuthorizedPersonalReportRows($auth, $yearStart, $now, 'expense') as $entry) {
			$tags = [''];
			foreach (self::TAG_COLUMNS as $tag => $column) {
				if ($this->dbBool($entry[$column] ?? false)) {
					$tags[] = $tag;
				}
			}
			$matchedGroups = [];
			foreach (['*', (int)$entry['project_id']] as $area) {
				foreach (['*', (int)$entry['category_id']] as $category) {
					foreach ($tags as $tag) {
						$matchedGroups += $ruleIndex[$area . '/' . $category . '/' . $tag] ?? [];
					}
				}
			}
			$amount = max(0, (int)$entry['amount_cents']);
			foreach ($matchedGroups as $group => $_) {
				$spent[$group]['year'] = ($spent[$group]['year'] ?? 0) + $amount;
				if ((int)$entry['date'] >= $monthStart) {
					$spent[$group]['month'] = ($spent[$group]['month'] ?? 0) + $amount;
				}
			}
		}
		return $spent;
	}

	private function assertCanUseReports(array $auth): void {
		if (empty($auth['can_use_reports'])) {
			throw new ApiV1HttpException(
				Http::STATUS_FORBIDDEN,
				'Reports and budget goals require "reports:read" plus read access to personal payments and all active areas.'
			);
		}
	}

	/** @return \Generator<int, array<string, mixed>> */
	private function loadAuthorizedPersonalReportRows(array $auth, int $dateFrom, int $dateTo, ?string $onlyType = null): \Generator {
		// Freeze an upper ID so new bookings cannot prolong this request. IDs may have gaps.
		$upper = $this->db->getQueryBuilder();
		$result = $upper->select('id')->from('cobudget_entries')->orderBy('id', 'DESC')->setMaxResults(1)->executeQuery();
		$lastId = (int)$result->fetchOne();
		$result->closeCursor();
		$afterId = 0;
		while ($afterId < $lastId) {
			$qb = $this->reportPageQuery($auth, $dateFrom, $dateTo, $onlyType);
			$qb->andWhere($qb->expr()->gt('e.id', $qb->createNamedParameter($afterId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->lte('e.id', $qb->createNamedParameter($lastId, \PDO::PARAM_INT)))
				->orderBy('e.id', 'ASC')->setMaxResults(self::PAGE_SIZE);
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			foreach ($rows as $row) {
				$afterId = (int)$row['id'];
				yield $row;
			}
			if (count($rows) < self::PAGE_SIZE) {
				break;
			}
		}
	}

	private function reportPageQuery(array $auth, int $dateFrom, int $dateTo, ?string $onlyType): IQueryBuilder {
		$userId = (string)$auth['user_id'];
		$workspaceId = (int)$auth['workspace_id'];
		$activeAreaIds = $auth['projects_enabled']
			? array_map('intval', array_keys($auth['active_areas_by_id']))
			: [];

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.id',
			'e.project_id',
			'e.type',
			'e.amount_cents',
			'e.date',
			'e.category_id',
			'e.is_subscription',
			'e.is_fixed_cost',
			'e.is_child_related',
			'e.is_important',
			'e.needs_review',
			'e.is_tax_relevant',
			'e.is_insurance',
			'c.name AS category_name'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->where($qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('personal')))
			->andWhere($qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($dateFrom, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter($dateTo, \PDO::PARAM_INT)));

		if ($activeAreaIds !== []) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('e.project_id'),
				$qb->expr()->in('e.project_id', $qb->createNamedParameter($activeAreaIds, IQueryBuilder::PARAM_INT_ARRAY))
			));
		} else {
			$qb->andWhere($qb->expr()->isNull('e.project_id'));
		}

		if ($onlyType !== null) {
			$qb->andWhere($qb->expr()->eq('e.type', $qb->createNamedParameter($onlyType)));
		}

		return $qb;
	}

	/** @return array{totals: array<string, int>, by_category: list<array<string, mixed>>, by_area: list<array<string, mixed>>, monthly_series: list<array<string, mixed>>} */
	private function aggregateRows(iterable $rows, \DateTimeZone $tz): array {
		$incomeCents = 0;
		$expenseCents = 0;
		$byCategory = [];
		$byArea = [];
		$monthly = [];
		$entryCount = 0;

		foreach ($rows as $row) {
			$entryCount++;
			$amount = max(0, (int)($row['amount_cents'] ?? 0));
			$type = (string)($row['type'] ?? 'expense');
			if ($type === 'income') {
				$incomeCents += $amount;
			} else {
				$expenseCents += $amount;
			}

			$catKey = ($row['category_id'] !== null && $row['category_id'] !== '' ? (int)$row['category_id'] : 0) . ':' . $type;
			if (!isset($byCategory[$catKey])) {
				$byCategory[$catKey] = [
					'category_id' => $row['category_id'] !== null && $row['category_id'] !== '' ? (int)$row['category_id'] : null,
					'category_name' => $row['category_name'] !== null ? (string)$row['category_name'] : null,
					'type' => $type,
					'amount_cents' => 0,
					'entry_count' => 0,
				];
			}
			$byCategory[$catKey]['amount_cents'] += $amount;
			$byCategory[$catKey]['entry_count']++;

			$areaId = $row['project_id'] !== null && $row['project_id'] !== '' ? (int)$row['project_id'] : null;
			$areaKey = $areaId === null ? 'personal' : (string)$areaId;
			if (!isset($byArea[$areaKey])) {
				$byArea[$areaKey] = [
					'project_id' => $areaId,
					'income_cents' => 0,
					'expense_cents' => 0,
					'balance_cents' => 0,
					'entry_count' => 0,
				];
			}
			if ($type === 'income') {
				$byArea[$areaKey]['income_cents'] += $amount;
			} else {
				$byArea[$areaKey]['expense_cents'] += $amount;
			}
			$byArea[$areaKey]['balance_cents'] = $byArea[$areaKey]['income_cents'] - $byArea[$areaKey]['expense_cents'];
			$byArea[$areaKey]['entry_count']++;

			$monthKey = (new \DateTimeImmutable('@' . (int)$row['date']))->setTimezone($tz)->format('Y-m');
			if (!isset($monthly[$monthKey])) {
				$monthly[$monthKey] = [
					'month' => $monthKey,
					'income_cents' => 0,
					'expense_cents' => 0,
					'balance_cents' => 0,
					'entry_count' => 0,
				];
			}
			if ($type === 'income') {
				$monthly[$monthKey]['income_cents'] += $amount;
			} else {
				$monthly[$monthKey]['expense_cents'] += $amount;
			}
			$monthly[$monthKey]['balance_cents'] = $monthly[$monthKey]['income_cents'] - $monthly[$monthKey]['expense_cents'];
			$monthly[$monthKey]['entry_count']++;
		}

		ksort($monthly, SORT_STRING);
		usort($byCategory, static fn (array $a, array $b): int => $b['amount_cents'] <=> $a['amount_cents']);

		return [
			'totals' => [
				'income_cents' => $incomeCents,
				'expense_cents' => $expenseCents,
				'balance_cents' => $incomeCents - $expenseCents,
				'entry_count' => $entryCount,
			],
			'by_category' => array_values($byCategory),
			'by_area' => array_values($byArea),
			'monthly_series' => array_values($monthly),
		];
	}

	/** @return array{0: int, 1: int} */
	private function resolveSummaryBounds(string $period, \DateTimeZone $tz, int $now): array {
		$nowDt = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
		$year = (int)$nowDt->format('Y');
		$month = (int)$nowDt->format('n');

		return match ($period) {
			'current-month' => [
				($s = $nowDt->setDate($year, $month, 1)->setTime(0, 0, 0))->getTimestamp(),
				$s->modify('+1 month')->getTimestamp(),
			],
			'last-month' => [
				($s = $nowDt->setDate($year, $month, 1)->setTime(0, 0, 0)->modify('-1 month'))->getTimestamp(),
				$s->modify('+1 month')->getTimestamp(),
			],
			'current-year' => [
				($s = $nowDt->setDate($year, 1, 1)->setTime(0, 0, 0))->getTimestamp(),
				$s->modify('+1 year')->getTimestamp(),
			],
			'last-year' => [
				($s = $nowDt->setDate($year - 1, 1, 1)->setTime(0, 0, 0))->getTimestamp(),
				$s->modify('+1 year')->getTimestamp(),
			],
			default => throw new ApiV1HttpException(
				Http::STATUS_BAD_REQUEST,
				'Invalid period parameter. Expected current-month, last-month, current-year, or last-year.'
			),
		};
	}

	/** @return array{0: int, 1: int} */
	private function periodBounds(string $period, \DateTimeZone $tz, int $now): array {
		$nowDt = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
		$year = (int)$nowDt->format('Y');
		$month = (int)$nowDt->format('n');

		if ($period === 'month') {
			$start = $nowDt->setDate($year, $month, 1)->setTime(0, 0, 0);
			return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
		}

		$start = $nowDt->setDate($year, 1, 1)->setTime(0, 0, 0);
		return [$start->getTimestamp(), $start->modify('+1 year')->getTimestamp()];
	}

	private function normalizeGoalCriteria(mixed $json): array {
		$decoded = is_string($json) ? json_decode($json, true) : null;
		if (!is_array($decoded)) {
			return ['rules' => []];
		}
		$rules = [];
		foreach ((array)($decoded['rules'] ?? []) as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			$projectId = array_key_exists('projectId', $rule) && $rule['projectId'] !== null
				? (int)$rule['projectId']
				: null;
			$categoryId = array_key_exists('categoryId', $rule) && $rule['categoryId'] !== null
				? (int)$rule['categoryId']
				: null;
			$tag = isset($rule['tag']) && is_string($rule['tag']) && isset(self::TAG_COLUMNS[$rule['tag']])
				? $rule['tag']
				: '';
			$rules[] = [
				'projectId' => $projectId,
				'categoryId' => $categoryId,
				'tag' => $tag,
			];
		}
		return ['rules' => $rules];
	}

	/**
	 * Filters goal rules against active area scope.
	 * Returns null when the goal originally had rules and ALL were stripped
	 * (preventing the empty-rules wildcard hazard from BudgetController).
	 *
	 * @param array<int, ?int> $categoryProjectMap
	 * @return ?list<array<string, mixed>>
	 */
	private function filterGoalRulesForActiveScope(array $rules, array $auth, array $categoryProjectMap): ?array {
		if ($rules === []) {
			return [];
		}

		$activeAreas = $auth['projects_enabled'] ? $auth['active_areas_by_id'] : [];
		$filtered = [];
		foreach ($rules as $rule) {
			$ruleProjectId = $rule['projectId'];
			if ($ruleProjectId !== null && $ruleProjectId !== 0 && !isset($activeAreas[$ruleProjectId])) {
				continue;
			}
			$ruleCategoryId = $rule['categoryId'];
			if ($ruleCategoryId !== null && array_key_exists($ruleCategoryId, $categoryProjectMap)) {
				$catProjectId = $categoryProjectMap[$ruleCategoryId];
				if ($catProjectId !== null && !isset($activeAreas[$catProjectId])) {
					continue;
				}
			}
			$filtered[] = $rule;
		}

		if ($filtered === []) {
			return null;
		}
		return $filtered;
	}

	/** @return array<int, ?int> */
	private function loadCategoryProjectMap(array $goalRows): array {
		$categoryIds = [];
		foreach ($goalRows as $goal) {
			$criteria = $this->normalizeGoalCriteria($goal['criteria_json'] ?? '{}');
			foreach ($criteria['rules'] as $rule) {
				if ($rule['categoryId'] !== null && $rule['categoryId'] > 0) {
					$categoryIds[$rule['categoryId']] = $rule['categoryId'];
				}
			}
		}
		if ($categoryIds === []) {
			return [];
		}

		$map = [];
		foreach (array_chunk(array_values($categoryIds), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'project_id')
				->from('cobudget_categories')
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$map[(int)$row['id']] = $row['project_id'] !== null && $row['project_id'] !== '' ? (int)$row['project_id'] : null;
			}
			$result->closeCursor();
		}
		return $map;
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

	private function resolveCurrency(string $userId): string {
		return ApiV1EntriesService::resolveUserCurrency($this->config, $userId);
	}

	private function dbBool(mixed $value): bool {
		return in_array($value, [true, 1, '1', 't', 'true'], true);
	}
}

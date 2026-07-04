<?php

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\BudgetSnapshotService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class AnalyticsController extends Controller {
	use WorkspaceAwareTrait;

	private const TAG_COLUMNS = [
		'important' => 'is_important',
		'review' => 'needs_review',
		'fixedCosts' => 'is_fixed_cost',
		'childRelated' => 'is_child_related',
		'subscriptions' => 'is_subscription',
		'taxRelevant' => 'is_tax_relevant',
	];

	private const TAG_LABELS = [
		'important' => 'Important',
		'review' => 'Review',
		'fixedCosts' => 'Fixed costs',
		'childRelated' => 'Children',
		'subscriptions' => 'Subscription',
		'taxRelevant' => 'Tax-relevant',
	];

	private const ATTACHMENT_LOOKUP_CHUNK_SIZE = 500;

	private IDBConnection $db;
	private IConfig $config;
	private IUserManager $userManager;
	private BudgetSnapshotService $budgetSnapshotService;
	private ?string $userId;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IConfig $config, IUserSession $userSession, IUserManager $userManager, BudgetSnapshotService $budgetSnapshotService, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->budgetSnapshotService = $budgetSnapshotService;
		$this->l10n = $l10n;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->initWorkspace();
	}

	/**
	 * @NoAdminRequired
	 */
	public function summary(string $period = 'current-month'): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null) {
				return $this->errorResponse('Workspace not found', Http::STATUS_BAD_REQUEST);
			}

			$sharesByProject = $this->loadProjectShares($workspaceId);
			$periods = $this->buildPeriodOptions($this->loadAnalyticsEntryDates($workspaceId));
			$selectedPeriod = $this->resolvePeriod($period, $periods);
			$periodEntries = $this->loadAnalyticsEntries($workspaceId, $sharesByProject, (int)$selectedPeriod['start'], (int)$selectedPeriod['end']);
			$periodEntries = $this->attachAnalyticsAttachmentFlags($periodEntries, $workspaceId);
			$comparisonPeriod = $this->comparisonPeriodFor($selectedPeriod);
			$comparisonEntries = $comparisonPeriod === null
				? []
				: $this->entriesForAnalyticsRange($workspaceId, $sharesByProject, $periodEntries, $selectedPeriod, (int)$comparisonPeriod['start'], (int)$comparisonPeriod['end']);
			$directionWindows = $this->directionWindowsFor($selectedPeriod);
			$directionRecentEntries = $directionWindows === null
				? []
				: $this->entriesForAnalyticsRange($workspaceId, $sharesByProject, $periodEntries, $selectedPeriod, (int)$directionWindows['recentStart'], (int)$directionWindows['recentEnd']);
			$directionBaselineEntries = $directionWindows === null
				? []
				: $this->entriesForAnalyticsRange($workspaceId, $sharesByProject, $periodEntries, $selectedPeriod, (int)$directionWindows['baselineStart'], (int)$directionWindows['baselineEnd']);
			$series = $this->buildSeries($periodEntries, $selectedPeriod);
			$summary = $this->summarizeEntries($periodEntries, $selectedPeriod);
			$projection = $this->buildProjection($periodEntries, $selectedPeriod, $summary);
			$upcomingEntries = $this->loadUpcomingEntries($workspaceId, $sharesByProject);
			$sharedProjectEntries = $this->loadSharedProjectEntries($workspaceId, $sharesByProject, (int)$selectedPeriod['start'], (int)$selectedPeriod['end']);
			$currentMonthComparisonEntries = [];
			if ($selectedPeriod['kind'] === 'current-month') {
				$previousMonthPeriod = $this->previousFullMonthPeriodFor($selectedPeriod);
				$currentMonthComparisonEntries = $previousMonthPeriod === null
					? []
					: $this->loadAnalyticsEntries($workspaceId, $sharesByProject, (int)$previousMonthPeriod['start'], (int)$previousMonthPeriod['end']);
			}

			$payload = [
				'period' => $selectedPeriod,
				'periods' => $periods,
				'currency' => $this->config->getUserValue((string)$this->userId, 'cobudget', 'currency', 'EUR'),
				'summary' => $summary,
				'comparison' => $selectedPeriod['kind'] === 'current-month'
					? $this->buildCurrentMonthComparison($currentMonthComparisonEntries, $selectedPeriod, $summary)
					: null,
				'projection' => $projection,
				'availableForecast' => $this->buildAvailableForecast($selectedPeriod, $summary, $projection),
				'series' => $series,
				'budgetHistory' => $this->budgetSnapshotService->history((string)$this->userId, $workspaceId, (int)$selectedPeriod['start'], (int)$selectedPeriod['end']),
				'breakdowns' => $this->buildBreakdowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'categoryDrilldowns' => $this->buildCategoryDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'paymentPartnerDrilldowns' => $this->buildPaymentPartnerDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'tagDrilldowns' => $this->buildTagDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'projectDrilldowns' => $this->buildProjectDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'outliers' => $this->buildOutliers($periodEntries),
				'sharedProjects' => $this->buildSharedProjects($sharedProjectEntries, $sharesByProject),
				'upcoming' => $this->buildUpcoming($upcomingEntries),
				'receiptChecks' => $this->receiptsEnabled() ? $this->buildReceiptChecks($periodEntries) : [],
			];

			return new DataResponse($payload);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function loadAnalyticsEntryDates(int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.id', 'e.date')
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('e.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId))
				),
				$qb->expr()->isNotNull('m.user_id')
			))
			->groupBy('e.id')
			->orderBy('e.date', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn(array $row): array => [
			'date' => (int)($row['date'] ?? 0),
		], $rows);
	}

	private function loadAnalyticsEntries(int $workspaceId, array $sharesByProject, ?int $start = null, ?int $end = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.id',
			'e.user_id',
			'e.type',
			'e.amount',
			'e.amount_cents',
			'e.date',
			'e.description',
			'e.category_id',
			'e.payment_partner_id',
			'e.project_id',
			'e.split_mode',
			'e.is_settled',
			'e.is_subscription',
			'e.is_fixed_cost',
			'e.is_child_related',
			'e.is_important',
			'e.needs_review',
			'e.is_tax_relevant',
			'c.name AS category_name',
			'p.name AS payment_partner_name',
			'pr.name AS project_name'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->leftJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'))
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('e.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId))
				),
				$qb->expr()->isNotNull('m.user_id')
			));

		if ($start !== null) {
			$qb->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($start, \PDO::PARAM_INT)));
		}
		if ($end !== null) {
			$qb->andWhere($qb->expr()->lt('e.date', $qb->createNamedParameter($end, \PDO::PARAM_INT)));
		}

		$qb->groupBy('e.id')
			->orderBy('e.date', 'ASC')
			->addOrderBy('e.id', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$entries = [];
		foreach ($rows as $row) {
			$entry = $this->normalizeAnalyticsEntry($row, $sharesByProject);
			if ($entry !== null) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	private function attachAnalyticsAttachmentFlags(array $entries, int $workspaceId): array {
		if ($entries === []) {
			return $entries;
		}

		$ids = array_values(array_filter(array_map(static fn(array $entry): int => (int)($entry['id'] ?? 0), $entries)));
		if ($ids === []) {
			return $entries;
		}

		$counts = [];
		foreach (array_chunk($ids, self::ATTACHMENT_LOOKUP_CHUNK_SIZE) as $idChunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id')
				->from('cobudget_entry_attachments')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($idChunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$entryId = (int)($row['entry_id'] ?? 0);
				if ($entryId > 0) {
					$counts[$entryId] = ($counts[$entryId] ?? 0) + 1;
				}
			}
		}

		return array_map(static function(array $entry) use ($counts): array {
			$entryId = (int)($entry['id'] ?? 0);
			$count = $counts[$entryId] ?? 0;
			$entry['attachmentCount'] = $count;
			$entry['hasAttachment'] = $count > 0;
			return $entry;
		}, $entries);
	}

	private function buildReceiptChecks(array $entries): array {
		$checks = [
			'taxRelevant' => [
				'key' => 'taxRelevantMissingReceipt',
				'filter' => 'taxRelevant',
				'title' => $this->l10n->t('Tax-relevant payments without receipt'),
				'description' => $this->l10n->t('Tax-relevant payments that do not have a receipt yet.'),
				'count' => 0,
				'amountCents' => 0,
			],
			'review' => [
				'key' => 'reviewMissingReceipt',
				'filter' => 'review',
				'title' => $this->l10n->t('Payments to review without receipt'),
				'description' => $this->l10n->t('Payments to review that do not have a receipt yet.'),
				'count' => 0,
				'amountCents' => 0,
			],
		];

		foreach ($entries as $entry) {
			if (!empty($entry['hasAttachment'])) {
				continue;
			}

			$tags = array_flip($entry['tags'] ?? []);
			$amountCents = abs((int)($entry['personalCents'] ?? $entry['amountCents'] ?? 0));
			foreach (['taxRelevant', 'review'] as $key) {
				if (!isset($tags[$key])) {
					continue;
				}
				$checks[$key]['count']++;
				$checks[$key]['amountCents'] += $amountCents;
			}
		}

		return array_values(array_filter($checks, static fn(array $check): bool => $check['count'] > 0));
	}

	private function receiptsEnabled(): bool {
		return $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_receipts', 'yes') === 'yes';
	}

	private function loadSharedProjectEntries(int $workspaceId, array $sharesByProject, int $start, int $end): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.id',
			'e.user_id',
			'e.type',
			'e.amount',
			'e.amount_cents',
			'e.date',
			'e.project_id',
			'e.split_mode',
			'e.is_settled',
			'pr.name AS project_name'
		)
			->from('cobudget_entries', 'e')
			->innerJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'))
			->innerJoin('e', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('e.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.type', $qb->createNamedParameter('expense')))
			->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($start, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lt('e.date', $qb->createNamedParameter($end, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->groupBy('e.id')
			->orderBy('e.date', 'ASC')
			->addOrderBy('e.id', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$entries = [];
		foreach ($rows as $row) {
			$amountCents = $this->amountCentsFromRow($row) ?? 0;
			$projectId = empty($row['project_id']) ? null : (int)$row['project_id'];
			if ($amountCents <= 0 || $projectId === null) {
				continue;
			}

			$shares = $sharesByProject[$projectId] ?? [];
			if (count($shares) < 2) {
				continue;
			}

			$userId = (string)($row['user_id'] ?? '');
			$entries[] = [
				'id' => (int)($row['id'] ?? 0),
				'type' => 'expense',
				'date' => (int)($row['date'] ?? 0),
				'amountCents' => $amountCents,
				'personalCents' => $this->entryShareCentsForUser($row, (string)$this->userId, $amountCents, $shares),
				'userId' => $userId,
				'userDisplayName' => $this->displayNameForUser($userId),
				'projectId' => $projectId,
				'projectName' => (string)($row['project_name'] ?? ''),
				'isSettled' => $this->dbBool($row['is_settled'] ?? false),
			];
		}

		return $entries;
	}

	private function loadUpcomingEntries(int $workspaceId, array $sharesByProject): array {
		$now = time();
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.id',
			'e.user_id',
			'e.type',
			'e.amount',
			'e.amount_cents',
			'e.date',
			'e.description',
			'e.category_id',
			'e.payment_partner_id',
			'e.project_id',
			'e.split_mode',
			'e.is_settled',
			'e.is_subscription',
			'e.is_fixed_cost',
			'e.is_child_related',
			'e.is_important',
			'e.needs_review',
			'e.is_tax_relevant',
			'e.recurrence_interval',
			'e.recurrence_next_date',
			'e.reminder_date',
			'e.reminder_notified',
			'e.reminder_text',
			'c.name AS category_name',
			'p.name AS payment_partner_name',
			'pr.name AS project_name'
		)
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->leftJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'))
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->andX(
				$qb->expr()->eq('e.project_id', 'm.project_id'),
				$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
			))
			->where($qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->gt('e.date', $qb->createNamedParameter($now, \PDO::PARAM_INT)),
				$qb->expr()->isNotNull('e.recurrence_next_date'),
				$qb->expr()->andX(
					$qb->expr()->isNotNull('e.reminder_date'),
					$qb->expr()->eq('e.reminder_notified', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				)
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId))
				),
				$qb->expr()->isNotNull('m.user_id')
			))
			->groupBy('e.id')
			->orderBy('e.date', 'ASC')
			->addOrderBy('e.id', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$entries = [];
		foreach ($rows as $row) {
			$entry = $this->normalizeAnalyticsEntry($row, $sharesByProject);
			if ($entry === null) {
				continue;
			}

			$entry['plannedDate'] = !empty($row['recurrence_next_date'])
				? (int)$row['recurrence_next_date']
				: (int)$row['date'];
			$entry['isRecurring'] = !empty($row['recurrence_next_date']);
			$entry['reminderDate'] = empty($row['reminder_date']) ? null : (int)$row['reminder_date'];
			$entry['reminderText'] = (string)($row['reminder_text'] ?? '');
			$entry['reminderNotified'] = $this->dbBool($row['reminder_notified'] ?? false);
			$entries[] = $entry;
		}

		return $entries;
	}

	private function normalizeAnalyticsEntry(array $row, array $sharesByProject): ?array {
		$type = (string)($row['type'] ?? '');
		if ($type !== 'income' && $type !== 'expense') {
			return null;
		}

		$date = (int)($row['date'] ?? 0);
		if ($date <= 0) {
			return null;
		}

		$amountCents = $this->amountCentsFromRow($row) ?? 0;
		if ($amountCents <= 0) {
			return null;
		}

		$personalCents = $this->entryPersonalCents($row, $amountCents, $sharesByProject);
		if ($personalCents <= 0) {
			return null;
		}

		$categoryName = trim((string)($row['category_name'] ?? ''));
		$categoryId = empty($row['category_id']) || $categoryName === '' ? null : (int)$row['category_id'];
		$paymentPartnerName = trim((string)($row['payment_partner_name'] ?? ''));
		$paymentPartnerId = empty($row['payment_partner_id']) || $paymentPartnerName === '' ? null : (int)$row['payment_partner_id'];
		$projectName = trim((string)($row['project_name'] ?? ''));
		$projectId = empty($row['project_id']) || $projectName === '' ? null : (int)$row['project_id'];

		return [
			'id' => (int)($row['id'] ?? 0),
			'type' => $type,
			'date' => $date,
			'description' => (string)($row['description'] ?? ''),
			'amountCents' => $amountCents,
			'personalCents' => $personalCents,
			'signedPersonalCents' => $type === 'income' ? $personalCents : -$personalCents,
			'userId' => (string)($row['user_id'] ?? ''),
			'userDisplayName' => $this->displayNameForUser((string)($row['user_id'] ?? '')),
			'isSettled' => $this->dbBool($row['is_settled'] ?? false),
			'categoryId' => $categoryId,
			'categoryName' => $categoryName,
			'paymentPartnerId' => $paymentPartnerId,
			'paymentPartnerName' => $paymentPartnerName,
			'projectId' => $projectId,
			'projectName' => $projectId === null ? 'Persönlich' : $projectName,
			'tags' => $this->entryTags($row),
		];
	}

	private function entryPersonalCents(array $entry, int $amountCents, array $sharesByProject): int {
		$projectId = empty($entry['project_id']) ? null : (int)$entry['project_id'];
		if ($projectId === null) {
			return (string)($entry['user_id'] ?? '') === (string)$this->userId ? $amountCents : 0;
		}

		$shares = $sharesByProject[$projectId] ?? [(string)$this->userId => 10000];
		return $this->entryShareCentsForUser($entry, (string)$this->userId, $amountCents, $shares);
	}

	private function entryTags(array $row): array {
		$tags = [];
		foreach (self::TAG_COLUMNS as $key => $column) {
			if ($this->dbBool($row[$column] ?? false)) {
				$tags[] = $key;
			}
		}

		return $tags;
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
			->where($qb->expr()->eq('p.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
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

	private function buildPeriodOptions(array $entries): array {
		$currentYear = (int)date('Y');
		$options = [
			['key' => 'current-year', 'label' => 'Aktuelles Jahr'],
			['key' => 'current-month', 'label' => 'Aktueller Monat'],
			['key' => 'last-12-months', 'label' => 'Letzte 12 Monate'],
		];

		$years = [];
		foreach ($entries as $entry) {
			$year = (int)date('Y', (int)$entry['date']);
			if ($year > 0) {
				$years[$year] = true;
			}
		}
		krsort($years);

		foreach (array_keys($years) as $year) {
			if ((int)$year === $currentYear) {
				continue;
			}
			$options[] = ['key' => 'year:' . $year, 'label' => (string)$year];
		}

		return $options;
	}

	private function resolvePeriod(string $period, array $options): array {
		$validKeys = array_column($options, 'key');
		if (!in_array($period, $validKeys, true)) {
			$period = 'current-year';
		}

		if ($period === 'current-year') {
			$year = (int)date('Y');
			$start = mktime(0, 0, 0, 1, 1, $year);
			$end = mktime(0, 0, 0, 1, 1, $year + 1);
			return [
				'key' => 'current-year',
				'kind' => 'year',
				'label' => 'Aktuelles Jahr',
				'start' => $start,
				'end' => $end,
				'startDate' => date('Y-m-d', $start),
				'endDate' => date('Y-m-d', $end - 1),
				'granularity' => 'month',
				'monthCount' => 12,
			];
		}

		if (str_starts_with($period, 'year:')) {
			$year = max(1970, (int)substr($period, 5));
			$start = mktime(0, 0, 0, 1, 1, $year);
			$end = mktime(0, 0, 0, 1, 1, $year + 1);
			return [
				'key' => $period,
				'kind' => 'year',
				'label' => (string)$year,
				'start' => $start,
				'end' => $end,
				'startDate' => date('Y-m-d', $start),
				'endDate' => date('Y-m-d', $end - 1),
				'granularity' => 'month',
				'monthCount' => 12,
			];
		}

		if ($period === 'last-12-months') {
			$currentMonth = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
			$start = strtotime('-11 months', $currentMonth);
			$end = strtotime('+1 month', $currentMonth);
			$start = $start === false ? $currentMonth : $start;
			$end = $end === false ? time() : $end;
			return [
				'key' => 'last-12-months',
				'kind' => 'last-12-months',
				'label' => 'Letzte 12 Monate',
				'start' => $start,
				'end' => $end,
				'startDate' => date('Y-m-d', $start),
				'endDate' => date('Y-m-d', $end - 1),
				'granularity' => 'month',
				'monthCount' => 12,
			];
		}

		$start = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
		$end = strtotime('+1 month', $start);
		$end = $end === false ? $start + 31 * 86400 : $end;

		return [
			'key' => 'current-month',
			'kind' => 'current-month',
			'label' => 'Aktueller Monat',
			'start' => $start,
			'end' => $end,
			'startDate' => date('Y-m-d', $start),
			'endDate' => date('Y-m-d', $end - 1),
			'granularity' => 'day',
			'monthCount' => 1,
		];
	}

	private function comparisonPeriodFor(array $period): ?array {
		$start = (int)($period['start'] ?? 0);
		$end = (int)($period['end'] ?? 0);
		if ($start <= 0 || $end <= $start) {
			return null;
		}

		$key = (string)($period['key'] ?? '');
		$kind = (string)($period['kind'] ?? '');
		$now = time();

		if ($key === 'current-month') {
			$previousStart = strtotime('-1 month', $start);
			if ($previousStart === false) {
				return null;
			}
			$previousMonthEnd = strtotime('+1 month', $previousStart);
			$elapsedSeconds = max(86400, min($now + 1, $end) - $start);
			return [
				'start' => $previousStart,
				'end' => min($previousMonthEnd === false ? $start : $previousMonthEnd, $previousStart + $elapsedSeconds),
			];
		}

		if ($key === 'current-year') {
			$previousStart = strtotime('-1 year', $start);
			if ($previousStart === false) {
				return null;
			}
			$previousYearEnd = strtotime('+1 year', $previousStart);
			$elapsedSeconds = max(86400, min($now + 1, $end) - $start);
			return [
				'start' => $previousStart,
				'end' => min($previousYearEnd === false ? $start : $previousYearEnd, $previousStart + $elapsedSeconds),
			];
		}

		if ($kind === 'year') {
			$previousStart = strtotime('-1 year', $start);
			$previousEnd = strtotime('-1 year', $end);
			if ($previousStart === false || $previousEnd === false || $previousEnd <= $previousStart) {
				return null;
			}
			return [
				'start' => $previousStart,
				'end' => $previousEnd,
			];
		}

		if ($kind === 'last-12-months') {
			$previousStart = strtotime('-12 months', $start);
			if ($previousStart === false) {
				return null;
			}
			return [
				'start' => $previousStart,
				'end' => $start,
			];
		}

		return null;
	}

	private function directionWindowsFor(array $period): ?array {
		$start = (int)($period['start'] ?? 0);
		$end = (int)($period['end'] ?? 0);
		if ($start <= 0 || $end <= $start) {
			return null;
		}

		$key = (string)($period['key'] ?? '');
		$kind = (string)($period['kind'] ?? '');
		$effectiveEnd = min($end, max($start + 1, time() + 1));

		if ($key === 'current-month') {
			return $this->fixedDirectionWindow($effectiveEnd, 7 * 86400);
		}

		if ($key === 'current-year' || $kind === 'last-12-months') {
			return $this->monthDirectionWindow($effectiveEnd, 3);
		}

		if ($kind === 'year') {
			$middle = strtotime('+6 months', $start);
			if ($middle === false || $middle <= $start || $middle >= $end) {
				return null;
			}

			return [
				'baselineStart' => $start,
				'baselineEnd' => $middle,
				'recentStart' => $middle,
				'recentEnd' => $end,
			];
		}

		return null;
	}

	private function fixedDirectionWindow(int $recentEnd, int $windowSeconds): ?array {
		if ($recentEnd <= 0 || $windowSeconds <= 0) {
			return null;
		}

		$recentStart = $recentEnd - $windowSeconds;
		$baselineEnd = $recentStart;
		$baselineStart = $baselineEnd - $windowSeconds;
		if ($baselineStart <= 0 || $baselineEnd <= $baselineStart || $recentEnd <= $recentStart) {
			return null;
		}

		return [
			'baselineStart' => $baselineStart,
			'baselineEnd' => $baselineEnd,
			'recentStart' => $recentStart,
			'recentEnd' => $recentEnd,
		];
	}

	private function monthDirectionWindow(int $recentEnd, int $months): ?array {
		if ($recentEnd <= 0 || $months <= 0) {
			return null;
		}

		$recentStart = strtotime(sprintf('-%d months', $months), $recentEnd);
		if ($recentStart === false) {
			return null;
		}
		$baselineEnd = $recentStart;
		$baselineStart = strtotime(sprintf('-%d months', $months), $baselineEnd);
		if ($baselineStart === false || $baselineEnd <= $baselineStart || $recentEnd <= $recentStart) {
			return null;
		}

		return [
			'baselineStart' => $baselineStart,
			'baselineEnd' => $baselineEnd,
			'recentStart' => $recentStart,
			'recentEnd' => $recentEnd,
		];
	}

	private function filterEntriesByRange(array $entries, int $start, int $end): array {
		return array_values(array_filter($entries, static function(array $entry) use ($start, $end): bool {
			$date = (int)$entry['date'];
			return $date >= $start && $date < $end;
		}));
	}

	private function entriesForAnalyticsRange(int $workspaceId, array $sharesByProject, array $loadedEntries, array $loadedPeriod, int $start, int $end): array {
		if ($start >= (int)$loadedPeriod['start'] && $end <= (int)$loadedPeriod['end']) {
			return $this->filterEntriesByRange($loadedEntries, $start, $end);
		}

		return $this->loadAnalyticsEntries($workspaceId, $sharesByProject, $start, $end);
	}

	private function summarizeEntries(array $entries, array $period): array {
		$income = 0;
		$expense = 0;
		$completedMonthIncome = 0;
		$completedMonthExpense = 0;
		$currentMonthStart = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
		foreach ($entries as $entry) {
			if ($entry['type'] === 'income') {
				$income += (int)$entry['personalCents'];
				if ((int)$entry['date'] < $currentMonthStart) {
					$completedMonthIncome += (int)$entry['personalCents'];
				}
			} elseif ($entry['type'] === 'expense') {
				$expense += (int)$entry['personalCents'];
				if ((int)$entry['date'] < $currentMonthStart) {
					$completedMonthExpense += (int)$entry['personalCents'];
				}
			}
		}

		$days = $this->elapsedDaysForPeriod($period);
		$weeks = max(1, $days / 7);
		$months = $this->completedMonthsForAverage($period);
		$monthIncome = $months > 0 ? $completedMonthIncome : $income;
		$monthExpense = $months > 0 ? $completedMonthExpense : $expense;
		$monthDivisor = max(1, $months);

		return [
			'incomeCents' => $income,
			'expenseCents' => $expense,
			'balanceCents' => $income - $expense,
			'bookingCount' => count($entries),
			'incomeCount' => count(array_filter($entries, static fn(array $entry): bool => $entry['type'] === 'income')),
			'expenseCount' => count(array_filter($entries, static fn(array $entry): bool => $entry['type'] === 'expense')),
			'averageIncomePerMonthCents' => (int)round($monthIncome / $monthDivisor),
			'averageExpensePerMonthCents' => (int)round($monthExpense / $monthDivisor),
			'averageBalancePerMonthCents' => (int)round(($monthIncome - $monthExpense) / $monthDivisor),
			'averageIncomePerWeekCents' => (int)round($income / $weeks),
			'averageExpensePerWeekCents' => (int)round($expense / $weeks),
			'averageBalancePerWeekCents' => (int)round(($income - $expense) / $weeks),
			'averageIncomePerDayCents' => (int)round($income / max(1, $days)),
			'averageExpensePerDayCents' => (int)round($expense / max(1, $days)),
			'averageBalancePerDayCents' => (int)round(($income - $expense) / max(1, $days)),
			'averageMonthCount' => $months,
			'averageDayCount' => $days,
		];
	}

	private function previousFullMonthPeriodFor(array $period): ?array {
		$previousEnd = (int)($period['start'] ?? 0);
		if ($previousEnd <= 0) {
			return null;
		}

		$previousStart = strtotime('-1 month', $previousEnd);
		if ($previousStart === false) {
			$previousStart = $previousEnd - 31 * 86400;
		}

		return [
			'start' => $previousStart,
			'end' => $previousEnd,
			'monthCount' => 1,
		];
	}

	private function buildCurrentMonthComparison(array $previousEntries, array $period, array $currentSummary): array {
		$previousPeriod = $this->previousFullMonthPeriodFor($period) ?? [
			'start' => (int)$period['start'],
			'end' => (int)$period['start'],
			'monthCount' => 1,
		];
		$previousSummary = $this->summarizeEntries($previousEntries, $previousPeriod);

		return [
			'label' => 'Zum Vormonat',
			'previousLabel' => date('m.Y', (int)$previousPeriod['start']),
			'previous' => $previousSummary,
			'deltaIncomeCents' => $currentSummary['incomeCents'] - $previousSummary['incomeCents'],
			'deltaExpenseCents' => $currentSummary['expenseCents'] - $previousSummary['expenseCents'],
			'deltaBalanceCents' => $currentSummary['balanceCents'] - $previousSummary['balanceCents'],
		];
	}

	private function buildProjection(array $entries, array $period, array $summary): ?array {
		$now = time();
		$start = (int)$period['start'];
		$end = (int)$period['end'];

		if ((string)$period['kind'] === 'last-12-months') {
			return [
				'label' => 'Monatlicher Durchschnitt',
				'basisLabel' => 'berechnet aus den letzten 12 Monaten',
				'incomeCents' => $summary['averageIncomePerMonthCents'],
				'expenseCents' => $summary['averageExpensePerMonthCents'],
				'balanceCents' => $summary['averageBalancePerMonthCents'],
			];
		}

		if ($now < $start || $now >= $end) {
			return null;
		}

		$elapsedSeconds = max(86400, min($now, $end - 1) - $start + 1);
		$totalSeconds = max(86400, $end - $start);
		$factor = $totalSeconds / $elapsedSeconds;

		return [
			'label' => (string)$period['kind'] === 'year' ? 'Prognose bis Jahresende' : 'Prognose bis Monatsende',
			'basisLabel' => 'hochgerechnet aus dem bisherigen Zeitraum',
			'incomeCents' => (int)round($summary['incomeCents'] * $factor),
			'expenseCents' => (int)round($summary['expenseCents'] * $factor),
			'balanceCents' => (int)round($summary['balanceCents'] * $factor),
		];
	}

	private function buildAvailableForecast(array $period, array $summary, ?array $projection): ?array {
		if ($projection === null) {
			return null;
		}

		$key = (string)($period['key'] ?? '');
		$kind = (string)($period['kind'] ?? '');
		if ($key !== 'current-year' && $kind !== 'current-month') {
			return null;
		}

		$start = (int)($period['start'] ?? 0);
		$end = (int)($period['end'] ?? 0);
		$now = time();
		if ($start <= 0 || $end <= $start || $now < $start || $now >= $end) {
			return null;
		}

		$elapsedSeconds = max(86400, min($now + 1, $end) - $start);
		$totalSeconds = max(86400, $end - $start);
		$elapsedRatio = min(1.0, $elapsedSeconds / $totalSeconds);
		$bookingCount = (int)($summary['bookingCount'] ?? 0);

		$confidence = 'low';
		if ($elapsedRatio >= 0.35 && $bookingCount >= 10) {
			$confidence = 'high';
		} elseif ($elapsedRatio >= 0.15 && $bookingCount >= 5) {
			$confidence = 'medium';
		}

		$bufferRate = [
			'high' => 0.05,
			'medium' => 0.10,
			'low' => 0.15,
		][$confidence];
		$expectedIncomeCents = (int)($projection['incomeCents'] ?? 0);
		$expectedExpenseCents = (int)($projection['expenseCents'] ?? 0);
		$forecastCents = (int)($projection['balanceCents'] ?? ($expectedIncomeCents - $expectedExpenseCents));
		$expenseBufferCents = (int)round($expectedExpenseCents * $bufferRate);
		$currentBalanceCents = (int)($summary['balanceCents'] ?? 0);

		return [
			'label' => $kind === 'current-month' ? 'Zum Monatsende voraussichtlich' : 'Zum Jahresende voraussichtlich',
			'basisLabel' => $kind === 'current-month'
				? 'hochgerechnet aus dem bisherigen Monat'
				: 'hochgerechnet aus dem bisherigen Jahr',
			'forecastCents' => $forecastCents,
			'currentBalanceCents' => $currentBalanceCents,
			'remainingChangeCents' => $forecastCents - $currentBalanceCents,
			'expectedIncomeCents' => $expectedIncomeCents,
			'expectedExpenseCents' => $expectedExpenseCents,
			'rangeLowCents' => $expectedIncomeCents - ($expectedExpenseCents + $expenseBufferCents),
			'rangeHighCents' => $expectedIncomeCents - max(0, $expectedExpenseCents - $expenseBufferCents),
			'remainingDays' => max(0, (int)ceil(($end - min($now + 1, $end)) / 86400)),
			'confidence' => $confidence,
			'confidenceLabel' => [
				'high' => 'stabile Datenbasis',
				'medium' => 'mittlere Datenbasis',
				'low' => 'frühe Schätzung',
			][$confidence],
		];
	}

	private function buildSeries(array $entries, array $period): array {
		$granularity = (string)($period['granularity'] ?? 'month');
		$buckets = $granularity === 'day'
			? $this->emptyDayBuckets((int)$period['start'], (int)$period['end'])
			: $this->emptyMonthBuckets((int)$period['start'], (int)$period['end']);

		foreach ($entries as $entry) {
			$key = $granularity === 'day'
				? date('Y-m-d', (int)$entry['date'])
				: date('Y-m', (int)$entry['date']);
			if (!isset($buckets[$key])) {
				continue;
			}

			if ($entry['type'] === 'income') {
				$buckets[$key]['incomeCents'] += (int)$entry['personalCents'];
			} elseif ($entry['type'] === 'expense') {
				$buckets[$key]['expenseCents'] += (int)$entry['personalCents'];
			}
			$buckets[$key]['count']++;
		}

		$running = 0;
		foreach ($buckets as &$bucket) {
			$bucket['balanceCents'] = $bucket['incomeCents'] - $bucket['expenseCents'];
			$running += $bucket['balanceCents'];
			$bucket['cumulativeBalanceCents'] = $running;
		}
		unset($bucket);

		return array_values($buckets);
	}

	private function emptyDayBuckets(int $start, int $end): array {
		$buckets = [];
		for ($cursor = $start; $cursor < $end; $cursor = strtotime('+1 day', $cursor) ?: $cursor + 86400) {
			$key = date('Y-m-d', $cursor);
			$buckets[$key] = [
				'key' => $key,
				'label' => date('d.m.', $cursor),
				'incomeCents' => 0,
				'expenseCents' => 0,
				'balanceCents' => 0,
				'cumulativeBalanceCents' => 0,
				'count' => 0,
			];
		}

		return $buckets;
	}

	private function emptyMonthBuckets(int $start, int $end): array {
		$buckets = [];
		$cursor = mktime(0, 0, 0, (int)date('n', $start), 1, (int)date('Y', $start));
		while ($cursor < $end) {
			$key = date('Y-m', $cursor);
			$buckets[$key] = [
				'key' => $key,
				'label' => date('m.Y', $cursor),
				'incomeCents' => 0,
				'expenseCents' => 0,
				'balanceCents' => 0,
				'cumulativeBalanceCents' => 0,
				'count' => 0,
			];
			$next = strtotime('+1 month', $cursor);
			$cursor = $next === false ? $cursor + 31 * 86400 : $next;
		}

		return $buckets;
	}

	private function buildBreakdowns(array $entries, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		return [
			'categories' => [
				'expense' => $this->buildBreakdown($entries, 'categoryId', 'categoryName', 'Ohne Kategorie', 'expense', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'income' => $this->buildBreakdown($entries, 'categoryId', 'categoryName', 'Ohne Kategorie', 'income', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
			],
			'paymentPartners' => [
				'expense' => $this->buildBreakdown($entries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', 'expense', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'income' => $this->buildBreakdown($entries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', 'income', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
			],
			'tags' => [
				'expense' => $this->buildTagBreakdown($entries, 'expense', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'income' => $this->buildTagBreakdown($entries, 'income', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
			],
			'projects' => [
				'expense' => $this->buildBreakdown($entries, 'projectId', 'projectName', 'Persönlich', 'expense', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
				'income' => $this->buildBreakdown($entries, 'projectId', 'projectName', 'Persönlich', 'income', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries),
			],
		];
	}

	private function buildCategoryDrilldowns(array $entries, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$result = [
			'expense' => [],
			'income' => [],
		];

		foreach (['expense', 'income'] as $type) {
			$categories = $this->buildBreakdown($entries, 'categoryId', 'categoryName', 'Ohne Kategorie', $type);
			foreach ($categories as $category) {
				$key = (string)($category['key'] ?? ($category['id'] === null ? 'none' : $category['id']));
				if ($key === 'rest') {
					continue;
				}

				$categoryEntries = $this->filterBreakdownEntries($entries, $type, 'categoryId', 'categoryName', 'Ohne Kategorie', $key);
				if ($categoryEntries === []) {
					continue;
				}

				$comparisonCategoryEntries = $this->filterBreakdownEntries($comparisonEntries, $type, 'categoryId', 'categoryName', 'Ohne Kategorie', $key);
				$directionRecentCategoryEntries = $this->filterBreakdownEntries($directionRecentEntries, $type, 'categoryId', 'categoryName', 'Ohne Kategorie', $key);
				$directionBaselineCategoryEntries = $this->filterBreakdownEntries($directionBaselineEntries, $type, 'categoryId', 'categoryName', 'Ohne Kategorie', $key);

				$result[$type][$key] = [
					'id' => $category['id'] ?? null,
					'key' => $key,
					'label' => (string)($category['name'] ?? 'Kategorie'),
					'paymentPartners' => $this->buildBreakdown($categoryEntries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $type, $comparisonCategoryEntries, $directionRecentCategoryEntries, $directionBaselineCategoryEntries),
					'tags' => $this->buildTagBreakdown($categoryEntries, $type, $comparisonCategoryEntries, $directionRecentCategoryEntries, $directionBaselineCategoryEntries),
					'projects' => $this->buildBreakdown($categoryEntries, 'projectId', 'projectName', 'Persönlich', $type, $comparisonCategoryEntries, $directionRecentCategoryEntries, $directionBaselineCategoryEntries),
				];
			}
		}

		return $result;
	}

	private function buildPaymentPartnerDrilldowns(array $entries, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$result = [
			'expense' => [],
			'income' => [],
		];

		foreach (['expense', 'income'] as $type) {
			$paymentPartners = $this->buildBreakdown($entries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $type);
			foreach ($paymentPartners as $paymentPartner) {
				$key = (string)($paymentPartner['key'] ?? ($paymentPartner['id'] === null ? 'none' : $paymentPartner['id']));
				if ($key === 'rest') {
					continue;
				}

				$paymentPartnerEntries = $this->filterBreakdownEntries($entries, $type, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $key);
				if ($paymentPartnerEntries === []) {
					continue;
				}

				$comparisonPaymentPartnerEntries = $this->filterBreakdownEntries($comparisonEntries, $type, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $key);
				$directionRecentPaymentPartnerEntries = $this->filterBreakdownEntries($directionRecentEntries, $type, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $key);
				$directionBaselinePaymentPartnerEntries = $this->filterBreakdownEntries($directionBaselineEntries, $type, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $key);

				$result[$type][$key] = [
					'id' => $paymentPartner['id'] ?? null,
					'key' => $key,
					'label' => (string)($paymentPartner['name'] ?? 'Zahlungspartner'),
					'categories' => $this->buildBreakdown($paymentPartnerEntries, 'categoryId', 'categoryName', 'Ohne Kategorie', $type, $comparisonPaymentPartnerEntries, $directionRecentPaymentPartnerEntries, $directionBaselinePaymentPartnerEntries),
					'tags' => $this->buildTagBreakdown($paymentPartnerEntries, $type, $comparisonPaymentPartnerEntries, $directionRecentPaymentPartnerEntries, $directionBaselinePaymentPartnerEntries),
					'projects' => $this->buildBreakdown($paymentPartnerEntries, 'projectId', 'projectName', 'Persönlich', $type, $comparisonPaymentPartnerEntries, $directionRecentPaymentPartnerEntries, $directionBaselinePaymentPartnerEntries),
				];
			}
		}

		return $result;
	}

	private function buildTagDrilldowns(array $entries, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$result = [
			'expense' => [],
			'income' => [],
		];

		foreach (['expense', 'income'] as $type) {
			foreach (self::TAG_LABELS as $tag => $label) {
				$tagEntries = array_values(array_filter($entries, static function(array $entry) use ($type, $tag): bool {
					return $entry['type'] === $type && in_array($tag, $entry['tags'] ?? [], true);
				}));
				if ($tagEntries === []) {
					continue;
				}

				$comparisonTagEntries = array_values(array_filter($comparisonEntries, static function(array $entry) use ($type, $tag): bool {
					return $entry['type'] === $type && in_array($tag, $entry['tags'] ?? [], true);
				}));
				$directionRecentTagEntries = array_values(array_filter($directionRecentEntries, static function(array $entry) use ($type, $tag): bool {
					return $entry['type'] === $type && in_array($tag, $entry['tags'] ?? [], true);
				}));
				$directionBaselineTagEntries = array_values(array_filter($directionBaselineEntries, static function(array $entry) use ($type, $tag): bool {
					return $entry['type'] === $type && in_array($tag, $entry['tags'] ?? [], true);
				}));

				$result[$type][$tag] = [
					'id' => $tag,
					'label' => $this->l10n->t($label),
					'categories' => $this->buildBreakdown($tagEntries, 'categoryId', 'categoryName', 'Ohne Kategorie', $type, $comparisonTagEntries, $directionRecentTagEntries, $directionBaselineTagEntries),
					'paymentPartners' => $this->buildBreakdown($tagEntries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $type, $comparisonTagEntries, $directionRecentTagEntries, $directionBaselineTagEntries),
					'projects' => $this->buildBreakdown($tagEntries, 'projectId', 'projectName', 'Persönlich', $type, $comparisonTagEntries, $directionRecentTagEntries, $directionBaselineTagEntries),
				];
			}
		}

		return $result;
	}

	private function buildProjectDrilldowns(array $entries, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$result = [
			'expense' => [],
			'income' => [],
		];

		foreach (['expense', 'income'] as $type) {
			$projects = $this->buildBreakdown($entries, 'projectId', 'projectName', 'Persönlich', $type);
			foreach ($projects as $project) {
				$key = (string)($project['key'] ?? ($project['id'] === null ? 'none' : $project['id']));
				if ($key === 'rest') {
					continue;
				}

				$projectEntries = $this->filterBreakdownEntries($entries, $type, 'projectId', 'projectName', 'Persönlich', $key);
				if ($projectEntries === []) {
					continue;
				}

				$comparisonProjectEntries = $this->filterBreakdownEntries($comparisonEntries, $type, 'projectId', 'projectName', 'Persönlich', $key);
				$directionRecentProjectEntries = $this->filterBreakdownEntries($directionRecentEntries, $type, 'projectId', 'projectName', 'Persönlich', $key);
				$directionBaselineProjectEntries = $this->filterBreakdownEntries($directionBaselineEntries, $type, 'projectId', 'projectName', 'Persönlich', $key);

				$result[$type][$key] = [
					'id' => $project['id'] ?? null,
					'key' => $key,
					'label' => (string)($project['name'] ?? 'Bereich'),
					'categories' => $this->buildBreakdown($projectEntries, 'categoryId', 'categoryName', 'Ohne Kategorie', $type, $comparisonProjectEntries, $directionRecentProjectEntries, $directionBaselineProjectEntries),
					'paymentPartners' => $this->buildBreakdown($projectEntries, 'paymentPartnerId', 'paymentPartnerName', 'Ohne Zahlungspartner', $type, $comparisonProjectEntries, $directionRecentProjectEntries, $directionBaselineProjectEntries),
					'tags' => $this->buildTagBreakdown($projectEntries, $type, $comparisonProjectEntries, $directionRecentProjectEntries, $directionBaselineProjectEntries),
				];
			}
		}

		return $result;
	}

	private function buildTagBreakdown(array $entries, string $type, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$rows = $this->buildTagBreakdownRows($entries, $type);
		$comparisonRows = $this->buildTagBreakdownRows($comparisonEntries, $type);
		$directionRecentRows = $this->buildTagBreakdownRows($directionRecentEntries, $type);
		$directionBaselineRows = $this->buildTagBreakdownRows($directionBaselineEntries, $type);

		return $this->withBreakdownTrends($rows, $comparisonRows, $directionRecentRows, $directionBaselineRows, $type);
	}

	private function buildTagBreakdownRows(array $entries, string $type): array {
		$rows = [];
		foreach ($entries as $entry) {
			if ($entry['type'] !== $type) {
				continue;
			}

			foreach ($entry['tags'] ?? [] as $tag) {
				$key = (string)$tag;
				if (!isset(self::TAG_LABELS[$key])) {
					continue;
				}

				if (!isset($rows[$key])) {
					$rows[$key] = [
						'id' => $key,
						'key' => $key,
						'name' => $this->l10n->t(self::TAG_LABELS[$key]),
						'amountCents' => 0,
						'count' => 0,
					];
				}

				$rows[$key]['amountCents'] += (int)$entry['personalCents'];
				$rows[$key]['count']++;
			}
		}

		usort($rows, static function(array $a, array $b): int {
			$amountCompare = $b['amountCents'] <=> $a['amountCents'];
			if ($amountCompare !== 0) {
				return $amountCompare;
			}

			return strcasecmp((string)$a['name'], (string)$b['name']);
		});

		return $rows;
	}

	private function buildBreakdown(array $entries, string $idKey, string $nameKey, string $fallbackName, string $type, array $comparisonEntries = [], array $directionRecentEntries = [], array $directionBaselineEntries = []): array {
		$rows = $this->buildBreakdownRows($entries, $idKey, $nameKey, $fallbackName, $type);
		$comparisonRows = $this->buildBreakdownRows($comparisonEntries, $idKey, $nameKey, $fallbackName, $type);
		$directionRecentRows = $this->buildBreakdownRows($directionRecentEntries, $idKey, $nameKey, $fallbackName, $type);
		$directionBaselineRows = $this->buildBreakdownRows($directionBaselineEntries, $idKey, $nameKey, $fallbackName, $type);
		$rows = $this->withBreakdownTrends($rows, $comparisonRows, $directionRecentRows, $directionBaselineRows, $type);

		return $this->withRestBucket($rows, 8);
	}

	private function buildBreakdownRows(array $entries, string $idKey, string $nameKey, string $fallbackName, string $type): array {
		$rows = [];
		foreach ($entries as $entry) {
			if ($entry['type'] !== $type) {
				continue;
			}
			$id = $entry[$idKey] ?? null;
			$name = trim((string)($entry[$nameKey] ?? ''));
			if ($name === '') {
				$name = $fallbackName;
				$id = null;
			}
			$key = $this->breakdownEntryKey($entry, $idKey, $nameKey, $fallbackName);
			if (!isset($rows[$key])) {
				$rows[$key] = [
					'id' => $id,
					'key' => $key,
					'name' => $name,
					'amountCents' => 0,
					'count' => 0,
				];
			}
			$rows[$key]['amountCents'] += (int)$entry['personalCents'];
			$rows[$key]['count']++;
		}

		usort($rows, static function(array $a, array $b): int {
			$amountCompare = $b['amountCents'] <=> $a['amountCents'];
			if ($amountCompare !== 0) {
				return $amountCompare;
			}

			return strcasecmp((string)$a['name'], (string)$b['name']);
		});

		return $rows;
	}

	private function filterBreakdownEntries(array $entries, string $type, string $idKey, string $nameKey, string $fallbackName, string $key): array {
		return array_values(array_filter($entries, function(array $entry) use ($type, $idKey, $nameKey, $fallbackName, $key): bool {
			return ($entry['type'] ?? '') === $type
				&& $this->breakdownEntryKey($entry, $idKey, $nameKey, $fallbackName) === $key;
		}));
	}

	private function breakdownEntryKey(array $entry, string $idKey, string $nameKey, string $fallbackName): string {
		$name = trim((string)($entry[$nameKey] ?? ''));
		if ($name === '') {
			return 'none';
		}

		if ($this->breakdownUsesNameKey($idKey)) {
			return 'name:' . $this->normalizeBreakdownName($name);
		}

		return $this->breakdownKey($entry[$idKey] ?? null);
	}

	private function breakdownUsesNameKey(string $idKey): bool {
		return in_array($idKey, ['categoryId', 'paymentPartnerId'], true);
	}

	private function normalizeBreakdownName(string $name): string {
		$name = trim((string)preg_replace('/\s+/u', ' ', $name));
		return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
	}

	private function breakdownKey($id): string {
		if ($id === null || $id === '' || (is_numeric($id) && (int)$id <= 0)) {
			return 'none';
		}

		return (string)(int)$id;
	}

	private function withBreakdownTrends(array $rows, array $comparisonRows, array $directionRecentRows, array $directionBaselineRows, string $type): array {
		$comparisonByKey = $this->breakdownAmountByKey($comparisonRows);
		$directionRecentByKey = $this->breakdownAmountByKey($directionRecentRows);
		$directionBaselineByKey = $this->breakdownAmountByKey($directionBaselineRows);

		foreach ($rows as &$row) {
			$key = (string)($row['key'] ?? '');
			if ($key === '' || $key === 'rest') {
				continue;
			}

			$trend = $this->breakdownTrend($directionRecentByKey[$key] ?? 0, $directionBaselineByKey[$key] ?? 0, $type);
			if ($trend !== null) {
				$row['trend'] = $trend;
			}

			$comparison = $this->breakdownTrend((int)($row['amountCents'] ?? 0), $comparisonByKey[$key] ?? 0, $type);
			if ($comparison !== null) {
				$row['comparison'] = $comparison;
			}
		}
		unset($row);

		return $rows;
	}

	private function breakdownAmountByKey(array $rows): array {
		$amounts = [];
		foreach ($rows as $row) {
			$key = (string)($row['key'] ?? '');
			if ($key === '') {
				continue;
			}
			$amounts[$key] = (int)($row['amountCents'] ?? 0);
		}

		return $amounts;
	}

	private function breakdownTrend(int $currentCents, int $previousCents, string $type): ?array {
		$currentCents = max(0, $currentCents);
		$previousCents = max(0, $previousCents);
		$deltaCents = $currentCents - $previousCents;
		$absoluteDeltaCents = abs($deltaCents);

		if ($previousCents <= 0 && $currentCents < 1000) {
			return null;
		}
		if ($currentCents <= 0 && $previousCents < 1000) {
			return null;
		}

		if ($previousCents <= 0) {
			return $this->formatBreakdownTrend('new', $currentCents >= 2500 ? 'strong' : 'medium', $type, $currentCents, $previousCents, $deltaCents, null);
		}

		if ($currentCents <= 0) {
			return $this->formatBreakdownTrend('gone', $previousCents >= 2500 ? 'strong' : 'medium', $type, $currentCents, $previousCents, $deltaCents, null);
		}

		$deltaPercent = ($deltaCents / $previousCents) * 100;
		$absoluteDeltaPercent = abs($deltaPercent);
		if ($absoluteDeltaCents < 1000 || $absoluteDeltaPercent < 15) {
			return null;
		}

		$level = $absoluteDeltaCents >= 2500 && $absoluteDeltaPercent >= 30 ? 'strong' : 'medium';
		return $this->formatBreakdownTrend($deltaCents > 0 ? 'up' : 'down', $level, $type, $currentCents, $previousCents, $deltaCents, (int)round($deltaPercent * 10));
	}

	private function formatBreakdownTrend(string $direction, string $level, string $type, int $currentCents, int $previousCents, int $deltaCents, ?int $deltaPercentTenths): array {
		$moreIsPositive = $type === 'income';
		$increased = in_array($direction, ['up', 'new'], true);
		$tone = $increased === $moreIsPositive ? 'positive' : 'negative';

		return [
			'direction' => $direction,
			'level' => $level,
			'tone' => $tone,
			'currentCents' => $currentCents,
			'previousCents' => $previousCents,
			'deltaCents' => $deltaCents,
			'deltaPercentTenths' => $deltaPercentTenths,
		];
	}

	private function withRestBucket(array $rows, int $limit): array {
		if (count($rows) <= $limit) {
			return $rows;
		}

		$visible = array_slice($rows, 0, $limit);
		$restRows = array_slice($rows, $limit);
		$restAmount = 0;
		$restCount = 0;
		foreach ($restRows as $row) {
			$restAmount += (int)$row['amountCents'];
			$restCount += (int)$row['count'];
		}
		$visible[] = [
			'id' => null,
			'key' => 'rest',
			'name' => 'Sonstige',
			'amountCents' => $restAmount,
			'count' => $restCount,
		];

		return $visible;
	}

	private function buildOutliers(array $entries): array {
		$expenses = array_values(array_filter($entries, static fn(array $entry): bool => $entry['type'] === 'expense'));
		usort($expenses, static fn(array $a, array $b): int => $b['personalCents'] <=> $a['personalCents']);
		if ($expenses === []) {
			return [
				'baselineCents' => 0,
				'items' => [],
			];
		}

		$topCount = max(1, (int)ceil(count($expenses) * 0.05));
		$baselineRows = array_slice($expenses, $topCount);
		$baselineCents = $baselineRows === []
			? 0
			: (int)round(array_sum(array_map(static fn(array $entry): int => (int)$entry['personalCents'], $baselineRows)) / count($baselineRows));
		$threshold = (int)$expenses[$topCount - 1]['personalCents'];
		$items = array_values(array_filter($expenses, static fn(array $entry): bool => (int)$entry['personalCents'] >= $threshold));
		$items = array_slice($items, 0, 5);

		return [
			'baselineCents' => $baselineCents,
			'items' => array_map(static function(array $entry): array {
				return [
					'id' => $entry['id'],
					'date' => $entry['date'],
					'description' => $entry['description'],
					'amountCents' => $entry['personalCents'],
					'categoryName' => $entry['categoryName'],
					'paymentPartnerName' => $entry['paymentPartnerName'],
					'projectName' => $entry['projectName'],
				];
			}, $items),
		];
	}

	private function buildSharedProjects(array $entries, array $sharesByProject): array {
		$projects = [];
		foreach ($entries as $entry) {
			if (($entry['type'] ?? '') !== 'expense') {
				continue;
			}

			$projectId = empty($entry['projectId']) ? null : (int)$entry['projectId'];
			if ($projectId === null || count($sharesByProject[$projectId] ?? []) < 2) {
				continue;
			}

			$amountCents = (int)($entry['amountCents'] ?? 0);
			if ($amountCents <= 0) {
				continue;
			}

			if (!isset($projects[$projectId])) {
				$projects[$projectId] = $this->emptySharedProjectSummary($projectId, (string)($entry['projectName'] ?? 'Bereich'), $sharesByProject[$projectId] ?? []);
			}

			$projects[$projectId]['totalPaidCents'] += $amountCents;
			$projects[$projectId]['personalShareCents'] += (int)($entry['personalCents'] ?? 0);
			$projects[$projectId]['entryCount']++;

			if (!empty($entry['isSettled'])) {
				$projects[$projectId]['settledCents'] += $amountCents;
			} else {
				$projects[$projectId]['openCents'] += $amountCents;
			}

			$payerId = (string)($entry['userId'] ?? '');
			if ($payerId === '') {
				continue;
			}

			if (!isset($projects[$projectId]['members'][$payerId])) {
				$projects[$projectId]['members'][$payerId] = [
					'userId' => $payerId,
					'displayName' => (string)($entry['userDisplayName'] ?? $payerId),
					'shareBasisPoints' => $sharesByProject[$projectId][$payerId] ?? 0,
					'paidCents' => 0,
				];
			}
			$projects[$projectId]['members'][$payerId]['paidCents'] += $amountCents;
		}

		$rows = array_values(array_map(function(array $project): array {
			$currentUserPaidCents = (int)($project['members'][(string)$this->userId]['paidCents'] ?? 0);
			$project['currentUserPaidCents'] = $currentUserPaidCents;
			$project['currentUserBalanceCents'] = $currentUserPaidCents - (int)($project['personalShareCents'] ?? 0);

			$members = array_values($project['members']);
			usort($members, static function(array $a, array $b): int {
				$paidCompare = (int)$b['paidCents'] <=> (int)$a['paidCents'];
				if ($paidCompare !== 0) {
					return $paidCompare;
				}

				return strcasecmp((string)$a['displayName'], (string)$b['displayName']);
			});
			$project['members'] = $members;
			return $project;
		}, $projects));

		usort($rows, static function(array $a, array $b): int {
			$amountCompare = (int)$b['totalPaidCents'] <=> (int)$a['totalPaidCents'];
			if ($amountCompare !== 0) {
				return $amountCompare;
			}

			return strcasecmp((string)$a['name'], (string)$b['name']);
		});

		return $rows;
	}

	private function emptySharedProjectSummary(int $projectId, string $projectName, array $shares): array {
		$members = [];
		foreach ($shares as $userId => $shareBasisPoints) {
			$userId = (string)$userId;
			$members[$userId] = [
				'userId' => $userId,
				'displayName' => $this->displayNameForUser($userId),
				'shareBasisPoints' => (int)$shareBasisPoints,
				'paidCents' => 0,
			];
		}

		return [
			'id' => $projectId,
			'name' => $projectName !== '' ? $projectName : 'Bereich',
			'totalPaidCents' => 0,
			'personalShareCents' => 0,
			'currentUserPaidCents' => 0,
			'currentUserBalanceCents' => 0,
			'openCents' => 0,
			'settledCents' => 0,
			'entryCount' => 0,
			'members' => $members,
		];
	}

	private function buildUpcoming(array $entries): array {
		$now = time();
		$reminders = [];
		$planned = [];

		foreach ($entries as $entry) {
			if (!empty($entry['reminderDate']) && empty($entry['reminderNotified'])) {
				$reminders[] = $this->compactUpcomingEntry($entry, (int)$entry['reminderDate']);
			}

			if (!empty($entry['plannedDate']) && (int)$entry['plannedDate'] > $now) {
				$planned[] = $this->compactUpcomingEntry($entry, (int)$entry['plannedDate']);
			}
		}

		usort($reminders, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
		usort($planned, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);

		return [
			'reminders' => array_slice($reminders, 0, 8),
			'planned' => array_slice($planned, 0, 8),
		];
	}

	private function compactUpcomingEntry(array $entry, int $date): array {
		return [
			'id' => $entry['id'],
			'type' => $entry['type'],
			'date' => $date,
			'entryDate' => $entry['date'],
			'description' => $entry['description'],
			'amountCents' => $entry['personalCents'],
			'categoryName' => $entry['categoryName'],
			'paymentPartnerName' => $entry['paymentPartnerName'],
			'projectName' => $entry['projectName'],
			'tags' => $entry['tags'] ?? [],
			'isRecurring' => !empty($entry['isRecurring']),
			'reminderText' => $entry['reminderText'] ?? '',
		];
	}

	private function elapsedDaysForPeriod(array $period): int {
		$start = (int)($period['start'] ?? 0);
		$end = (int)($period['end'] ?? 0);
		if ($start <= 0 || $end <= $start) {
			return 1;
		}

		$effectiveEnd = min($end, time() + 1);
		return max(1, (int)ceil(($effectiveEnd - $start) / 86400));
	}

	private function completedMonthsForAverage(array $period): int {
		$start = (int)($period['start'] ?? 0);
		$end = (int)($period['end'] ?? 0);
		if ($start <= 0 || $end <= $start) {
			return 0;
		}

		$currentMonthStart = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
		$averageEnd = min($end, $currentMonthStart);
		if ($averageEnd <= $start) {
			return 0;
		}

		$cursor = mktime(0, 0, 0, (int)date('n', $start), 1, (int)date('Y', $start));
		$months = 0;
		while ($cursor < $averageEnd) {
			$next = strtotime('+1 month', $cursor);
			$next = $next === false ? $cursor + 31 * 86400 : $next;
			if ($next <= $averageEnd) {
				$months++;
			}
			$cursor = $next;
		}

		return $months;
	}

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1';
	}

	private function displayNameForUser(string $userId): string {
		if ($userId === '') {
			return '';
		}

		$user = $this->userManager->get($userId);
		return $user ? $user->getDisplayName() : $userId;
	}
}

<?php
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\HashtagService;
use OCA\CoBudget\Service\ProjectNotificationService;
use OCP\IRequest;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IL10N;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\DB\QueryBuilder\IQueryBuilder;

class EntryController extends Controller {
	use WorkspaceAwareTrait;

	private const EXPORT_LIMIT = 50000;
	private const EXPORT_ATTACHMENT_CHUNK_SIZE = 500;
	private const ENTRY_HISTORY_CHUNK_SIZE = 500;
	private const ENTRY_HISTORY_FIELDS = [
		'type',
		'amount_cents',
		'currency',
		'date',
		'category_id',
		'payment_partner_id',
		'description',
		'project_id',
		'user_id',
		'split_mode',
		'recurrence_interval',
		'recurrence_multiplier',
		'recurrence_next_date',
		'recurrence_end_date',
		'is_subscription',
		'is_fixed_cost',
		'is_child_related',
		'is_important',
		'needs_review',
		'is_tax_relevant',
		'reminder_date',
		'reminder_text',
	];
	private const ENTRY_HISTORY_BOOLEAN_FIELDS = [
		'is_subscription',
		'is_fixed_cost',
		'is_child_related',
		'is_important',
		'needs_review',
		'is_tax_relevant',
	];
	private const ENTRY_HISTORY_INTEGER_FIELDS = [
		'amount_cents',
		'date',
		'category_id',
		'payment_partner_id',
		'project_id',
		'recurrence_multiplier',
		'recurrence_next_date',
		'recurrence_end_date',
		'reminder_date',
	];
	private const ENTRY_HISTORY_STRING_FIELDS = [
		'type',
		'currency',
		'description',
		'user_id',
		'split_mode',
		'recurrence_interval',
		'reminder_text',
	];

	private IDBConnection $db;
	private ?string $userId;
	private IConfig $config;
	private IUserManager $userManager;
	private HashtagService $hashtagService;
	private ProjectNotificationService $projectNotificationService;
	private IRootFolder $rootFolder;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IConfig $config, IUserManager $userManager, HashtagService $hashtagService, ProjectNotificationService $projectNotificationService, IRootFolder $rootFolder, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->hashtagService = $hashtagService;
		$this->projectNotificationService = $projectNotificationService;
		$this->rootFolder = $rootFolder;
		$this->l10n = $l10n;
		$this->initWorkspace();
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(
		int $limit = 50,
		int $offset = 0,
		string $search = '',
		?int $paymentPartnerId = null,
		?int $categoryId = null,
		?int $dateFrom = null,
		?int $dateTo = null,
		string $type = 'all',
		string $sortBy = 'date',
		string $sortDir = 'desc',
		?int $projectId = null,
		?bool $isSettled = null,
		?bool $isRecurring = null,
		?bool $isSubscription = null,
		?bool $isFixedCost = null,
		?bool $isChildRelated = null,
		?bool $isImportant = null,
		?bool $needsReview = null,
		?bool $isTaxRelevant = null,
		?bool $hasReminder = null,
		?bool $hasAttachment = null,
		?int $hashtagId = null,
		$isFuturePayments = false
	): DataResponse {
		$isFuture = ($isFuturePayments === true || $isFuturePayments === 'true');
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->workspaceIdForEntryScope($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Bereich nicht gefunden oder nicht im aktiven Workspace', Http::STATUS_FORBIDDEN);
			}
			return new DataResponse($this->fetchEntryListPayload($workspaceId, $limit, $offset, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $sortBy, $sortDir, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture, null, true));
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function exportCsv(
		string $search = '',
		?int $paymentPartnerId = null,
		?int $categoryId = null,
		?int $dateFrom = null,
		?int $dateTo = null,
		string $type = 'all',
		string $sortBy = 'date',
		string $sortDir = 'desc',
		?int $projectId = null,
		?bool $isSettled = null,
		?bool $isRecurring = null,
		?bool $isSubscription = null,
		?bool $isFixedCost = null,
		?bool $isChildRelated = null,
		?bool $isImportant = null,
		?bool $needsReview = null,
		?bool $isTaxRelevant = null,
		?bool $hasReminder = null,
		?bool $hasAttachment = null,
		?int $hashtagId = null,
		$isFuturePayments = false
	): Response {
		$isFuture = ($isFuturePayments === true || $isFuturePayments === 'true');
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			$entries = $this->fetchEntryRows($workspaceId, self::EXPORT_LIMIT, 0, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $sortBy, $sortDir, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);
			$projectShareBasisPoints = $this->projectShareBasisPointsFromProjects($this->fetchDashboardProjects($workspaceId));
			$csv = $this->buildEntriesCsv($entries, $projectShareBasisPoints);
			$filename = 'cobudget-zahlungen-' . date('Ymd-His') . '.csv';

			return new DataDownloadResponse($csv, $filename, 'text/csv; charset=UTF-8');
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function dashboard(
		int $limit = 50,
		int $offset = 0,
		string $search = '',
		?int $paymentPartnerId = null,
		?int $categoryId = null,
		?int $dateFrom = null,
		?int $dateTo = null,
		string $type = 'all',
		string $sortBy = 'date',
		string $sortDir = 'desc',
		?int $projectId = null,
		?bool $isSettled = null,
		?bool $isRecurring = null,
		?bool $isSubscription = null,
		?bool $isFixedCost = null,
		?bool $isChildRelated = null,
		?bool $isImportant = null,
		?bool $needsReview = null,
		?bool $isTaxRelevant = null,
		?bool $hasReminder = null,
		?bool $hasAttachment = null,
		?int $hashtagId = null,
		$isFuturePayments = false,
		$summaryOnly = false
	): DataResponse {
		$isFuture = ($isFuturePayments === true || $isFuturePayments === 'true');
		$isSummaryOnly = ($summaryOnly === true || $summaryOnly === 'true' || $summaryOnly === 1 || $summaryOnly === '1');
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$workspaceId = $this->getWorkspaceId();
			if ($isSummaryOnly) {
				$summaryData = $this->fetchEntryTotalsData($workspaceId, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);
				$futureSummaryData = $this->fetchEntryTotalsData($workspaceId, $search, $paymentPartnerId, $categoryId, null, null, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, true);

				return new DataResponse([
					'tagCounts' => $this->countDashboardTags($summaryData, $futureSummaryData),
				]);
			}

			$projects = $this->fetchDashboardProjects($workspaceId);
			$projectShareBasisPoints = $this->projectShareBasisPointsFromProjects($projects);
			$main = $this->fetchEntryListPayload($workspaceId, $limit, $offset, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $sortBy, $sortDir, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture, $projectShareBasisPoints);
			$currentMonthStart = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
			$currentMonthData = $this->fetchEntryTotalsData($workspaceId, $search, $paymentPartnerId, $categoryId, $currentMonthStart, null, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, false);
			$futureData = $this->fetchEntryTotalsData($workspaceId, $search, $paymentPartnerId, $categoryId, null, null, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, true);

			return new DataResponse([
				'entries' => $main['entries'],
				'total' => $main['total'],
				'limit' => $main['limit'],
				'offset' => $main['offset'],
				'dateGroups' => $main['dateGroups'],
				'metrics' => $this->buildDashboardMetrics($main['totalsData'], $currentMonthData, $futureData, $projectShareBasisPoints),
				'tagCounts' => $this->countDashboardTags($main['totalsData'], $futureData),
				'lookups' => [
					'categories' => $this->fetchDashboardCategories($workspaceId),
					'paymentPartners' => $this->fetchDashboardPaymentPartners($workspaceId),
					'projects' => $projects,
					'hashtags' => $this->hashtagService->fetchVisibleHashtagsForUser($workspaceId, (string)$this->userId),
				],
			]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function fetchEntryListPayload(
		int $workspaceId,
		int $limit,
		int $offset,
		string $search,
		?int $paymentPartnerId,
		?int $categoryId,
		?int $dateFrom,
		?int $dateTo,
		string $type,
		string $sortBy,
		string $sortDir,
		?int $projectId,
		?bool $isSettled,
		?bool $isRecurring,
		?bool $isSubscription,
		?bool $isFixedCost,
		?bool $isChildRelated,
		?bool $isImportant,
		?bool $needsReview,
		?bool $isTaxRelevant,
		?bool $hasReminder,
		?bool $hasAttachment,
		?int $hashtagId,
		bool $isFuture,
		?array $projectShareBasisPoints = null,
		bool $includeLookups = false
	): array {
		$totalsData = $this->fetchEntryTotalsData($workspaceId, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);
		$entries = $this->fetchEntryRows($workspaceId, $limit, $offset, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $sortBy, $sortDir, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);

		$payload = [
			'entries' => $entries,
			'totalsData' => $totalsData,
			'total' => count($totalsData),
			'limit' => $limit,
			'offset' => $offset,
			'dateGroups' => $this->buildDateGroups($totalsData, $offset, count($entries), $sortBy, $sortDir, $isFuture, $projectShareBasisPoints)
		];

		if ($includeLookups) {
			$payload['lookups'] = [
				'hashtags' => $this->hashtagService->fetchVisibleHashtagsForUser($workspaceId, (string)$this->userId),
			];
		}

		return $payload;
	}

	private function buildDateGroups(array $allEntries, int $offset, int $pageCount, string $sortBy, string $sortDir, bool $isFuture, ?array $projectShareBasisPoints = null): array {
		if ($sortBy !== 'date' || $allEntries === []) {
			return [
				'summaries' => [],
				'visibleKeys' => [],
			];
		}

		$orderedEntries = array_values($allEntries);
		$dir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
		usort($orderedEntries, function(array $a, array $b) use ($dir, $isFuture): int {
			$dateA = $this->dateGroupTimestamp($a, $isFuture);
			$dateB = $this->dateGroupTimestamp($b, $isFuture);
			if ($dateA !== $dateB) {
				return $dir === 'ASC' ? $dateA <=> $dateB : $dateB <=> $dateA;
			}

			return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
		});

		$summaries = [];
		$lastIndexByKey = [];
		foreach ($orderedEntries as $index => $entry) {
			$timestamp = $this->dateGroupTimestamp($entry, $isFuture);
			if ($timestamp <= 0) {
				continue;
			}

			$keys = $this->dateGroupKeys($timestamp);
			$amountCents = $projectShareBasisPoints === null
				? ($this->amountCentsFromRow($entry) ?? 0)
				: $this->entryPersonalAmountCents($entry, $projectShareBasisPoints);

			foreach ($keys as $key) {
				if (!isset($summaries[$key])) {
					$summaries[$key] = [
						'income' => 0,
						'expense' => 0,
						'balance' => 0,
						'count' => 0,
					];
				}

				$summaries[$key]['count']++;
				if (($entry['type'] ?? '') === 'income') {
					$summaries[$key]['income'] += $amountCents;
					$summaries[$key]['balance'] += $amountCents;
				} elseif (($entry['type'] ?? '') === 'expense') {
					$summaries[$key]['expense'] += $amountCents;
					$summaries[$key]['balance'] -= $amountCents;
				}

				$lastIndexByKey[$key] = $index;
			}
		}

		$pageStart = max(0, $offset);
		$pageEnd = $pageCount > 0 ? $pageStart + $pageCount - 1 : -1;
		$visibleKeys = [];
		foreach ($lastIndexByKey as $key => $lastIndex) {
			if ($lastIndex >= $pageStart && $lastIndex <= $pageEnd) {
				$visibleKeys[] = $key;
			}
		}

		foreach ($summaries as &$summary) {
			$summary['income'] = $this->centsToAmount((int)$summary['income']);
			$summary['expense'] = $this->centsToAmount((int)$summary['expense']);
			$summary['balance'] = $this->centsToAmount((int)$summary['balance']);
		}
		unset($summary);

		return [
			'summaries' => $summaries,
			'visibleKeys' => $visibleKeys,
		];
	}

	private function dateGroupTimestamp(array $entry, bool $isFuture): int {
		if ($isFuture && !empty($entry['recurrence_next_date'])) {
			return (int)$entry['recurrence_next_date'];
		}

		return (int)($entry['date'] ?? 0);
	}

	private function dateGroupKeys(int $timestamp): array {
		return [
			'year-' . date('Y', $timestamp),
			'month-' . date('Y-m', $timestamp),
		];
	}

	private function fetchEntryTotalsData(
		int $workspaceId,
		string $search,
		?int $paymentPartnerId,
		?int $categoryId,
		?int $dateFrom,
		?int $dateTo,
		string $type,
		?int $projectId,
		?bool $isSettled,
		?bool $isRecurring,
		?bool $isSubscription,
		?bool $isFixedCost,
		?bool $isChildRelated,
		?bool $isImportant,
		?bool $needsReview,
		?bool $isTaxRelevant,
		?bool $hasReminder,
		?bool $hasAttachment,
		?int $hashtagId,
		bool $isFuture
	): array {
		$qb = $this->buildVisibleEntriesQuery($workspaceId);
		$qb->select('e.id', 'e.user_id', 'e.type', 'e.amount', 'e.amount_cents', 'e.project_id', 'e.split_mode', 'e.is_subscription', 'e.is_fixed_cost', 'e.is_child_related', 'e.is_important', 'e.needs_review', 'e.is_tax_relevant', 'e.date', 'e.recurrence_next_date');
		$this->applyFilters($qb, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);
		$qb->groupBy('e.id');

		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();
		$entries = array_map(fn(array $entry): array => $this->normalizeAmountRow($entry), $entries);

		if ($isFuture) {
			foreach ($entries as &$entry) {
				if (!empty($entry['recurrence_next_date'])) {
					$entry['date'] = $entry['recurrence_next_date'];
				}
			}
			unset($entry);
		}

		return $entries;
	}

	private function fetchEntryRows(
		int $workspaceId,
		int $limit,
		int $offset,
		string $search,
		?int $paymentPartnerId,
		?int $categoryId,
		?int $dateFrom,
		?int $dateTo,
		string $type,
		string $sortBy,
		string $sortDir,
		?int $projectId,
		?bool $isSettled,
		?bool $isRecurring,
		?bool $isSubscription,
		?bool $isFixedCost,
		?bool $isChildRelated,
		?bool $isImportant,
		?bool $needsReview,
		?bool $isTaxRelevant,
		?bool $hasReminder,
		?bool $hasAttachment,
		?int $hashtagId,
		bool $isFuture
	): array {
		$qb = $this->buildVisibleEntriesQuery($workspaceId);
		$qb->leftJoin('e', 'cobudget_projects', 'pr', $qb->expr()->eq('e.project_id', 'pr.id'));
		$qb->select('e.*', 'c.name AS category_name', 'c.icon AS category_icon', 'p.name AS paymentPartner', 'pr.name AS project_name');
		$this->applyFilters($qb, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $projectId, $isSettled, $isRecurring, $isSubscription, $isFixedCost, $isChildRelated, $isImportant, $needsReview, $isTaxRelevant, $hasReminder, $hasAttachment, $hashtagId, $isFuture);
		$qb->groupBy('e.id');
		$this->applyEntryOrdering($qb, $sortBy, $sortDir, $isFuture);
		$qb->setMaxResults($limit);
		$qb->setFirstResult($offset);

		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();
		$entries = array_map(fn(array $entry): array => $this->normalizeEntryRow($entry), $entries);
		$entries = $this->attachEntryHistoryFlags($entries, $workspaceId);
		$entries = $this->attachEntryAttachmentDetails($entries, $workspaceId);
		$entries = $this->hashtagService->attachHashtagsToEntries($entries);

		if ($isFuture) {
			foreach ($entries as &$entry) {
				if (!empty($entry['recurrence_next_date'])) {
					$entry['date'] = $entry['recurrence_next_date'];
				}
			}
		}

		return $entries;
	}

	private function buildEntriesCsv(array $entries, array $projectShareBasisPoints): string {
		$handle = fopen('php://temp', 'r+');
		if ($handle === false) {
			throw new \RuntimeException('CSV export could not be created.');
		}

		fwrite($handle, "\xEF\xBB\xBF");
		$headers = [
			'ID',
			'Date',
			'Type',
			'Type key',
			'Amount',
			'Amount cents',
			'Personal share',
			'Personal share cents',
			'Currency',
			'Purpose or description',
			'Category ID',
			'Category',
			'Payment partner ID',
			'Payment partner',
			'Area ID',
			'Area',
			'Paid/received by ID',
			'Paid/received by',
			'Split',
			'Labels',
			'Hashtags',
			'Important',
			'Review',
			'Fixed costs',
			'Children',
			'Subscription',
			'Tax-relevant',
			'Settled',
			'Settlement ID',
			'Recurring',
			'Repeat every',
			'Recurrence unit',
			'Next recurrence',
			'Recurrence until',
			'Reminder',
			'Reminder text',
			'Planned',
			'Receipts',
			'Receipt names',
			'Receipt paths',
		];
		fputcsv($handle, array_map(fn(string $header): string => $this->l10n->t($header), $headers), ';');

		foreach ($entries as $entry) {
			$type = (string)($entry['type'] ?? '');
			$signedAmountCents = $this->signedExportCents($this->amountCentsFromRow($entry) ?? 0, $type);
			$personalAmountCents = $this->signedExportCents($this->entryPersonalAmountCents($entry, $projectShareBasisPoints), $type);
			fputcsv($handle, [
				(string)(int)($entry['id'] ?? 0),
				$this->exportDate($entry['date'] ?? null),
				$this->exportTypeLabel($type),
				$type,
				$this->exportAmountFromCents($signedAmountCents),
				(string)$signedAmountCents,
				$this->exportAmountFromCents($personalAmountCents),
				(string)$personalAmountCents,
				(string)($entry['currency'] ?? ''),
				(string)($entry['description'] ?? ''),
				$this->exportNullableId($entry['category_id'] ?? null),
				(string)($entry['category_name'] ?? ''),
				$this->exportNullableId($entry['payment_partner_id'] ?? null),
				(string)($entry['paymentPartner'] ?? ''),
				$this->exportNullableId($entry['project_id'] ?? null),
				(string)($entry['project_name'] ?? ''),
				(string)($entry['user_id'] ?? ''),
				(string)($entry['user_display_name'] ?? $entry['user_id'] ?? ''),
				$this->exportSplitMode((string)($entry['split_mode'] ?? '')),
				implode(', ', $this->exportTagLabels($entry)),
				implode(', ', $this->exportHashtagLabels($entry)),
				$this->exportBool($entry['is_important'] ?? false),
				$this->exportBool($entry['needs_review'] ?? false),
				$this->exportBool($entry['is_fixed_cost'] ?? false),
				$this->exportBool($entry['is_child_related'] ?? false),
				$this->exportBool($entry['is_subscription'] ?? false),
				$this->exportBool($entry['is_tax_relevant'] ?? false),
				$this->exportBool($entry['is_settled'] ?? false),
				$this->exportNullableId($entry['settlement_id'] ?? null),
				$this->exportBool(!empty($entry['recurrence_interval']) || !empty($entry['recurrence_next_date'])),
				$this->exportNullableNumber($entry['recurrence_interval'] ?? null),
				(string)($entry['recurrence_unit'] ?? ''),
				$this->exportDateTime($entry['recurrence_next_date'] ?? null),
				$this->exportDate($entry['recurrence_end_date'] ?? null),
				$this->exportDateTime($entry['reminder_date'] ?? null),
				(string)($entry['reminder_text'] ?? ''),
				$this->exportBool($this->isPlannedEntry($entry)),
				(string)(int)($entry['attachments_count'] ?? 0),
				implode(', ', $entry['attachment_names'] ?? []),
				implode(', ', $entry['attachment_paths'] ?? []),
			], ';');
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return $csv === false ? '' : $csv;
	}

	private function signedExportCents(int $amountCents, string $type): int {
		if ($type === 'expense') {
			return -abs($amountCents);
		}

		return abs($amountCents);
	}

	private function exportAmountFromCents(int $amountCents): string {
		return number_format($amountCents / 100, 2, '.', '');
	}

	private function exportBool($value): string {
		return $this->dbBool($value) ? 'Ja' : 'Nein';
	}

	private function exportDate($timestamp): string {
		$timestamp = (int)($timestamp ?? 0);
		return $timestamp > 0 ? date('Y-m-d', $timestamp) : '';
	}

	private function exportDateTime($timestamp): string {
		$timestamp = (int)($timestamp ?? 0);
		return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '';
	}

	private function exportNullableId($value): string {
		$id = (int)($value ?? 0);
		return $id > 0 ? (string)$id : '';
	}

	private function exportNullableNumber($value): string {
		if ($value === null || $value === '') {
			return '';
		}

		return (string)(int)$value;
	}

	private function exportTypeLabel(string $type): string {
		return $type === 'income' ? $this->l10n->t('Income') : $this->l10n->t('Expense');
	}

	private function exportSplitMode(string $splitMode): string {
		return $this->normalizeSplitMode($splitMode) === 'single_user'
			? $this->l10n->t('Assigned fully to the selected user')
			: $this->l10n->t('By area split');
	}

	private function exportTagLabels(array $entry): array {
		$tags = [];
		if ($this->dbBool($entry['is_important'] ?? false)) {
			$tags[] = $this->l10n->t('Important');
		}
		if ($this->dbBool($entry['needs_review'] ?? false)) {
			$tags[] = $this->l10n->t('Review');
		}
		if ($this->dbBool($entry['is_fixed_cost'] ?? false)) {
			$tags[] = $this->l10n->t('Fixed costs');
		}
		if ($this->dbBool($entry['is_child_related'] ?? false)) {
			$tags[] = $this->l10n->t('Children');
		}
		if ($this->dbBool($entry['is_subscription'] ?? false)) {
			$tags[] = $this->l10n->t('Subscription');
		}
		if ($this->dbBool($entry['is_tax_relevant'] ?? false)) {
			$tags[] = $this->l10n->t('Tax-relevant');
		}

		return $tags;
	}

	private function exportHashtagLabels(array $entry): array {
		$hashtags = [];
		foreach (($entry['hashtags'] ?? []) as $hashtag) {
			if (!is_array($hashtag)) {
				continue;
			}
			$name = trim((string)($hashtag['displayName'] ?? $hashtag['name'] ?? ''));
			if ($name !== '') {
				$hashtags[] = '#' . $name;
			}
		}

		return $hashtags;
	}

	private function isPlannedEntry(array $entry): bool {
		$date = (int)($entry['date'] ?? 0);
		$recurrenceDate = (int)($entry['recurrence_next_date'] ?? 0);
		$now = time();

		return ($date > $now) || ($recurrenceDate > $now);
	}

	private function attachEntryAttachmentDetails(array $entries, int $workspaceId): array {
		if ($entries === []) {
			return $entries;
		}

		$ids = array_values(array_filter(array_map(static fn(array $entry): int => (int)($entry['id'] ?? 0), $entries)));
		if ($ids === []) {
			return $entries;
		}

		$details = [];
		foreach (array_chunk($ids, self::EXPORT_ATTACHMENT_CHUNK_SIZE) as $idChunk) {
			$qb = $this->db->getQueryBuilder();
			$idFilter = $qb->expr()->orX();
			foreach ($idChunk as $entryId) {
				$idFilter->add($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
			}
			$qb->select('entry_id', 'file_name', 'file_path')
				->from('cobudget_entry_attachments')
				->where($idFilter)
				->orderBy('entry_id', 'ASC')
				->addOrderBy('created_at', 'ASC')
				->addOrderBy('id', 'ASC');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$entryId = (int)$row['entry_id'];
				if (!isset($details[$entryId])) {
					$details[$entryId] = [
						'count' => 0,
						'names' => [],
						'paths' => [],
					];
				}
				$details[$entryId]['count']++;
				$details[$entryId]['names'][] = (string)($row['file_name'] ?? '');
				$details[$entryId]['paths'][] = (string)($row['file_path'] ?? '');
			}
		}

		return array_map(static function(array $entry) use ($details): array {
			$entryId = (int)($entry['id'] ?? 0);
			$attachmentDetails = $details[$entryId] ?? [
				'count' => 0,
				'names' => [],
				'paths' => [],
			];
			$entry['attachments_count'] = $attachmentDetails['count'];
			$entry['attachment_names'] = $attachmentDetails['names'];
			$entry['attachment_paths'] = $attachmentDetails['paths'];
			return $entry;
		}, $entries);
	}

	private function attachEntryHistoryFlags(array $entries, int $workspaceId): array {
		if ($entries === []) {
			return $entries;
		}

		$ids = array_values(array_filter(array_map(static fn(array $entry): int => (int)($entry['id'] ?? 0), $entries)));
		if ($ids === []) {
			return $entries;
		}

		$counts = [];
		foreach (array_chunk($ids, self::ENTRY_HISTORY_CHUNK_SIZE) as $idChunk) {
			$qb = $this->db->getQueryBuilder();
			$idFilter = $qb->expr()->orX();
			foreach ($idChunk as $entryId) {
				$idFilter->add($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
			}

			$qb->select('entry_id')
				->selectAlias($qb->createFunction('COUNT(*)'), 'history_count')
				->from('cobudget_entry_history')
				->where($idFilter)
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->groupBy('entry_id');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$counts[(int)$row['entry_id']] = (int)$row['history_count'];
			}
		}

		return array_map(static function(array $entry) use ($counts): array {
			$entryId = (int)($entry['id'] ?? 0);
			$count = $counts[$entryId] ?? 0;
			$entry['has_history'] = $count > 0;
			$entry['history_count'] = $count;
			return $entry;
		}, $entries);
	}

	private function buildVisibleEntriesQuery(int $workspaceId) {
		$qb = $this->db->getQueryBuilder();
		$qb->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->eq('e.project_id', 'm.project_id'))
			->where($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId)),
					$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				),
				$qb->expr()->andX(
					$qb->expr()->isNotNull('e.project_id'),
					$qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId))
				)
			));

		return $qb;
	}

	private function applyEntryOrdering($qb, string $sortBy, string $sortDir, bool $isFuture): void {
		if (in_array($sortBy, ['date', 'amount', 'paymentPartner', 'description', 'category_name'], true)) {
			$dir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
			if ($sortBy === 'category_name') {
				$qb->orderBy('c.name', $dir);
			} elseif ($sortBy === 'paymentPartner') {
				$qb->orderBy('p.name', $dir);
			} elseif ($sortBy === 'amount') {
				$qb->orderBy('e.amount_cents', $dir);
			} else {
				$qb->orderBy('e.' . $sortBy, $dir);
			}
		} elseif ($isFuture) {
			$qb->orderBy('e.recurrence_next_date', 'ASC');
		} else {
			$qb->orderBy('e.date', 'DESC');
		}

		$qb->addOrderBy('e.id', 'DESC');
	}

	private function fetchDashboardCategories(int $workspaceId): array {
		$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_categories', '[]');
		$hiddenIds = json_decode($hiddenJson, true);
		$hiddenIds = is_array($hiddenIds) ? array_map('intval', $hiddenIds) : [];

		$qb = $this->db->getQueryBuilder();
		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);
		$qb->select('*')
			->from('cobudget_categories')
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

		$result = $qb->executeQuery();
		$categories = $result->fetchAll();
		$result->closeCursor();

		$categories = array_values(array_filter($categories, static function(array $category) use ($hiddenIds): bool {
			return !in_array((int)$category['id'], $hiddenIds, true);
		}));

		return $this->addRecentUsageCounts($categories, 'category_id', $workspaceId);
	}

	private function fetchDashboardPaymentPartners(int $workspaceId): array {
		$hiddenJson = $this->config->getUserValue($this->userId, 'cobudget', 'hidden_payment_partners', '[]');
		$hiddenIds = json_decode($hiddenJson, true);
		$hiddenIds = is_array($hiddenIds) ? array_map('intval', $hiddenIds) : [];

		$qb = $this->db->getQueryBuilder();
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

		$result = $qb->executeQuery();
		$paymentPartners = $result->fetchAll();
		$result->closeCursor();

		$paymentPartners = array_values(array_filter($paymentPartners, static function(array $paymentPartner) use ($hiddenIds): bool {
			return !in_array((int)$paymentPartner['id'], $hiddenIds, true);
		}));

		return $this->addRecentUsageCounts($paymentPartners, 'payment_partner_id', $workspaceId);
	}

	private function fetchDashboardProjects(int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from('cobudget_projects', 'p')
			->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)));

		$result = $qb->executeQuery();
		$projects = $result->fetchAll();
		$result->closeCursor();

		if ($projects === []) {
			return [];
		}

		$projectIds = array_values(array_unique(array_map(static function(array $project): int {
			return (int)$project['id'];
		}, $projects)));
		$membersByProject = $this->fetchProjectMembersByProjectIds($projectIds);
		$entriesByProject = $this->fetchOpenExpenseEntriesByProjectIds($projectIds);

		foreach ($projects as &$project) {
			$projectId = (int)$project['id'];
			$memberRows = $membersByProject[$projectId] ?? [];
			$shares = $this->memberShareBasisPoints($memberRows);
			$project['member_count'] = count($memberRows);
			$project['my_share_basis_points'] = $shares[(string)$this->userId] ?? 10000;
			$project['personal_balance'] = 0.0;

			$paidByMeCents = 0;
			$fairShareMeCents = 0;
			foreach ($entriesByProject[$projectId] ?? [] as $entry) {
				$amountCents = $this->amountCentsFromRow($entry) ?? 0;

				if (($entry['user_id'] ?? null) === $this->userId) {
					$paidByMeCents += $amountCents;
				}
				$fairShareMeCents += $this->entryShareCentsForUser($entry, (string)$this->userId, $amountCents, $shares);
			}

			$project['personal_balance'] = round(($paidByMeCents - $fairShareMeCents) / 100, 2);
		}
		unset($project);

		return $projects;
	}

	private function fetchProjectMembersByProjectIds(array $projectIds): array {
		if ($projectIds === []) {
			return [];
		}

		$membersByProject = [];
		foreach (array_chunk($projectIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('project_id', 'user_id', 'share_basis_points')
				->from('cobudget_members')
				->where($qb->expr()->in('project_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('id', 'ASC');

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$membersByProject[(int)$row['project_id']][] = $row;
			}
			$result->closeCursor();
		}

		return $membersByProject;
	}

	private function fetchOpenExpenseEntriesByProjectIds(array $projectIds): array {
		if ($projectIds === []) {
			return [];
		}

		$entriesByProject = [];
		foreach (array_chunk($projectIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('project_id', 'user_id', 'amount', 'amount_cents', 'type', 'split_mode')
				->from('cobudget_entries')
				->where($qb->expr()->in('project_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('expense')))
				->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$entriesByProject[(int)$row['project_id']][] = $row;
			}
			$result->closeCursor();
		}

		return $entriesByProject;
	}

	private function projectShareBasisPointsFromProjects(array $projects): array {
		$shares = [];
		foreach ($projects as $project) {
			if (!isset($project['id'])) {
				continue;
			}

			$shares[(int)$project['id']] = max(0, (int)($project['my_share_basis_points'] ?? 10000));
		}

		return $shares;
	}

	private function buildDashboardMetrics(array $totalsData, array $currentMonthData, array $futureData, array $projectShareBasisPoints): array {
		$future30DaysData = $this->filterFuture30Days($futureData);

		return [
			'total' => $this->summarizeDashboardEntries($totalsData, $projectShareBasisPoints),
			'average' => $this->calculateAverageDashboardMetrics($totalsData, $projectShareBasisPoints),
			'currentMonth' => $this->summarizeDashboardEntries($currentMonthData, $projectShareBasisPoints),
			'future' => $this->summarizeDashboardEntries($futureData, $projectShareBasisPoints),
			'future30Days' => $this->summarizeDashboardEntries($future30DaysData, $projectShareBasisPoints),
		];
	}

	private function zeroDashboardMetrics(): array {
		return [
			'income' => 0.0,
			'expense' => 0.0,
			'balance' => 0.0,
			'important' => 0.0,
			'review' => 0.0,
			'subscriptions' => 0.0,
			'fixedCosts' => 0.0,
			'childRelated' => 0.0,
			'taxRelevant' => 0.0,
		];
	}

	private function zeroDashboardTagCounts(): array {
		return [
			'income' => 0,
			'future' => 0,
			'important' => 0,
			'review' => 0,
			'fixedCosts' => 0,
			'childRelated' => 0,
			'subscriptions' => 0,
			'taxRelevant' => 0,
		];
	}

	private function countDashboardTags(array $entries, array $futureEntries = []): array {
		$counts = $this->zeroDashboardTagCounts();
		$counts['future'] = count($futureEntries);

		foreach ($entries as $entry) {
			if (($entry['type'] ?? '') === 'income') {
				$counts['income']++;
			}
			if ($this->dbBool($entry['is_important'] ?? false)) {
				$counts['important']++;
			}
			if ($this->dbBool($entry['needs_review'] ?? false)) {
				$counts['review']++;
			}
			if ($this->dbBool($entry['is_fixed_cost'] ?? false)) {
				$counts['fixedCosts']++;
			}
			if ($this->dbBool($entry['is_child_related'] ?? false)) {
				$counts['childRelated']++;
			}
			if ($this->dbBool($entry['is_subscription'] ?? false)) {
				$counts['subscriptions']++;
			}
			if ($this->dbBool($entry['is_tax_relevant'] ?? false)) {
				$counts['taxRelevant']++;
			}
		}

		return $counts;
	}

	private function summarizeDashboardEntries(array $entries, array $projectShareBasisPoints): array {
		$summary = $this->zeroDashboardMetrics();

		foreach ($entries as $entry) {
			$amount = $this->entryPersonalAmount($entry, $projectShareBasisPoints);
			if (($entry['type'] ?? '') === 'income') {
				$summary['income'] += $amount;
				$signedAmount = $amount;
			} elseif (($entry['type'] ?? '') === 'expense') {
				$summary['expense'] += $amount;
				$signedAmount = -$amount;
			} else {
				continue;
			}

			if ($this->dbBool($entry['is_important'] ?? false)) {
				$summary['important'] += $signedAmount;
			}
			if ($this->dbBool($entry['needs_review'] ?? false)) {
				$summary['review'] += $signedAmount;
			}
			if ($this->dbBool($entry['is_child_related'] ?? false)) {
				$summary['childRelated'] += $signedAmount;
			}
			if ($this->dbBool($entry['is_tax_relevant'] ?? false)) {
				$summary['taxRelevant'] += $signedAmount;
			}

			if (($entry['type'] ?? '') !== 'expense') {
				continue;
			}
			if ($this->dbBool($entry['is_subscription'] ?? false)) {
				$summary['subscriptions'] += $amount;
			}
			if ($this->dbBool($entry['is_fixed_cost'] ?? false)) {
				$summary['fixedCosts'] += $amount;
			}
		}

		$summary['balance'] = $summary['income'] - $summary['expense'];

		return $summary;
	}

	private function entryPersonalAmount(array $entry, array $projectShareBasisPoints): float {
		return $this->entryPersonalAmountCents($entry, $projectShareBasisPoints) / 100;
	}

	private function entryPersonalAmountCents(array $entry, array $projectShareBasisPoints): int {
		$amountCents = $this->amountCentsFromRow($entry) ?? 0;
		$projectId = empty($entry['project_id']) ? null : (int)$entry['project_id'];
		if ($projectId === null) {
			return $amountCents;
		}

		if ($this->normalizeSplitMode($entry['split_mode'] ?? null) === 'single_user') {
			return ((string)($entry['user_id'] ?? '') === (string)$this->userId) ? $amountCents : 0;
		}

		$shareBasisPoints = $projectShareBasisPoints[$projectId] ?? 10000;
		return (int)round($amountCents * $shareBasisPoints / 10000);
	}

	private function filterFuture30Days(array $entries): array {
		$limit = time() + 30 * 86400;

		return array_values(array_filter($entries, static function(array $entry) use ($limit): bool {
			$date = (int)($entry['recurrence_next_date'] ?? $entry['date'] ?? 0);

			return $date > 0 && $date <= $limit;
		}));
	}

	private function calculateAverageDashboardMetrics(array $entries, array $projectShareBasisPoints): array {
		if ($entries === []) {
			return $this->zeroDashboardMetrics();
		}

		$validDates = array_values(array_filter(array_map(static function(array $entry): int {
			return (int)($entry['date'] ?? 0);
		}, $entries), static function(int $date): bool {
			return $date > 0;
		}));

		if ($validDates === []) {
			return $this->zeroDashboardMetrics();
		}

		$minDate = min($validDates);
		$maxDate = max($validDates);
		$minParts = getdate($minDate);
		$maxParts = getdate($maxDate);

		if ($minParts['year'] === $maxParts['year'] && $minParts['mon'] === $maxParts['mon']) {
			return $this->summarizeDashboardEntries($entries, $projectShareBasisPoints);
		}

		$currentYear = (int)date('Y');
		$currentMonth = (int)date('n');
		$averageEntries = $entries;

		if ($maxParts['year'] > $currentYear || ($maxParts['year'] === $currentYear && $maxParts['mon'] >= $currentMonth)) {
			$averageEntries = array_values(array_filter($entries, static function(array $entry) use ($currentYear, $currentMonth): bool {
				$date = (int)($entry['date'] ?? 0);
				if ($date <= 0) {
					return false;
				}

				$parts = getdate($date);
				return $parts['year'] < $currentYear || ($parts['year'] === $currentYear && $parts['mon'] < $currentMonth);
			}));

			$pastDates = array_values(array_filter(array_map(static function(array $entry): int {
				return (int)($entry['date'] ?? 0);
			}, $averageEntries), static function(int $date): bool {
				return $date > 0;
			}));

			if ($pastDates === []) {
				return $this->summarizeDashboardEntries($entries, $projectShareBasisPoints);
			}

			$maxDate = max($pastDates);
		}

		$numMonths = max(1, $this->monthSpanInclusive($minDate, $maxDate));
		$summary = $this->summarizeDashboardEntries($averageEntries, $projectShareBasisPoints);

		return $this->divideDashboardMetrics($summary, $numMonths);
	}

	private function monthSpanInclusive(int $minDate, int $maxDate): int {
		$minParts = getdate($minDate);
		$maxParts = getdate($maxDate);

		return (($maxParts['year'] - $minParts['year']) * 12) + ($maxParts['mon'] - $minParts['mon']) + 1;
	}

	private function divideDashboardMetrics(array $metrics, int $months): array {
		$months = max(1, $months);
		foreach ($metrics as $key => $value) {
			$metrics[$key] = ((float)$value) / $months;
		}

		return $metrics;
	}

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1';
	}

	private function normalizeEntryRow(array $entry): array {
		$entry = $this->normalizeAmountRow($entry);
		foreach (['is_subscription', 'is_fixed_cost', 'is_child_related', 'is_important', 'needs_review', 'is_tax_relevant', 'is_settled', 'reminder_notified'] as $key) {
			if (array_key_exists($key, $entry)) {
				$entry[$key] = $this->dbBool($entry[$key]);
			}
		}

		if (!empty($entry['user_id'])) {
			$userId = (string)$entry['user_id'];
			$user = $this->userManager->get($userId);
			$entry['user_display_name'] = $user ? $user->getDisplayName() : $userId;
		}

		return $entry;
	}

	private function applyFilters($qb, $search, $paymentPartnerId, $categoryId, $dateFrom, $dateTo, $type, $projectId, $isSettled, $isRecurring = null, $isSubscription = null, $isFixedCost = null, $isChildRelated = null, $isImportant = null, $needsReview = null, $isTaxRelevant = null, $hasReminder = null, $hasAttachment = null, ?int $hashtagId = null, $isFuturePayments = false) {
		$now = time();
		if ($isFuturePayments) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNotNull('e.recurrence_next_date'),
				$qb->expr()->gt('e.date', $qb->createNamedParameter($now, \PDO::PARAM_INT))
			));
		} else {
			$qb->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter($now, \PDO::PARAM_INT)));
		}
		
		if ($projectId !== null) {
			$qb->andWhere($qb->expr()->eq('e.project_id', $qb->createNamedParameter($projectId)));
		}
		
		if ($isSettled !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_settled', $qb->createNamedParameter($isSettled, \PDO::PARAM_BOOL)));
		}
		
		if (!empty($search)) {
			$searchParam = $qb->createNamedParameter('%' . $search . '%');
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('e.description', $searchParam),
				$qb->expr()->iLike('p.name', $searchParam),
				$qb->expr()->iLike('c.name', $searchParam)
			));
		}

		if ($paymentPartnerId !== null) {
			$qb->andWhere($qb->expr()->eq('e.payment_partner_id', $qb->createNamedParameter($paymentPartnerId)));
		}

		if ($categoryId !== null) {
			$qb->andWhere($qb->expr()->eq('e.category_id', $qb->createNamedParameter($categoryId)));
		}

		if ($hashtagId !== null) {
			$qb->innerJoin('e', 'cobudget_entry_hashtags', 'hashtag_filter', $qb->expr()->eq('hashtag_filter.entry_id', 'e.id'));
			$qb->andWhere($qb->expr()->eq('hashtag_filter.hashtag_id', $qb->createNamedParameter($hashtagId, \PDO::PARAM_INT)));
			$qb->andWhere($qb->expr()->eq('hashtag_filter.workspace_id', 'e.workspace_id'));
		}

		if ($dateFrom !== null) {
			$qb->andWhere($qb->expr()->gte('e.date', $qb->createNamedParameter($dateFrom, \PDO::PARAM_INT)));
		}

		if ($dateTo !== null) {
			$qb->andWhere($qb->expr()->lte('e.date', $qb->createNamedParameter($dateTo, \PDO::PARAM_INT)));
		}

		if ($type === 'income' || $type === 'expense') {
			$qb->andWhere($qb->expr()->eq('e.type', $qb->createNamedParameter($type)));
		}

		if ($isRecurring === true) {
			$qb->andWhere($qb->expr()->isNotNull('e.recurrence_interval'));
		} elseif ($isRecurring === false) {
			$qb->andWhere($qb->expr()->isNull('e.recurrence_interval'));
		}

		if ($isSubscription !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_subscription', $qb->createNamedParameter($isSubscription, \PDO::PARAM_BOOL)));
		}

		if ($isFixedCost !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_fixed_cost', $qb->createNamedParameter($isFixedCost, \PDO::PARAM_BOOL)));
		}

		if ($isChildRelated !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_child_related', $qb->createNamedParameter($isChildRelated, \PDO::PARAM_BOOL)));
		}

		if ($isImportant !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_important', $qb->createNamedParameter($isImportant, \PDO::PARAM_BOOL)));
		}

		if ($needsReview !== null) {
			$qb->andWhere($qb->expr()->eq('e.needs_review', $qb->createNamedParameter($needsReview, \PDO::PARAM_BOOL)));
		}

		if ($isTaxRelevant !== null) {
			$qb->andWhere($qb->expr()->eq('e.is_tax_relevant', $qb->createNamedParameter($isTaxRelevant, \PDO::PARAM_BOOL)));
		}

		if ($hasReminder === true) {
			$qb->andWhere($qb->expr()->isNotNull('e.reminder_date'));
		} elseif ($hasReminder === false) {
			$qb->andWhere($qb->expr()->isNull('e.reminder_date'));
		}

		if ($hasAttachment !== null) {
			$qb->leftJoin('e', 'cobudget_entry_attachments', 'attachment_filter', $qb->expr()->eq('attachment_filter.entry_id', 'e.id'));
			if ($hasAttachment === true) {
				$qb->andWhere($qb->expr()->isNotNull('attachment_filter.id'));
			} else {
				$qb->andWhere($qb->expr()->isNull('attachment_filter.id'));
			}
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(
		string $type = 'expense',
		float $amount = 0,
		?string $description = '',
		int $date = 0,
		string $currency = 'EUR',
		?int $projectId = null,
		?int $categoryId = null,
		?int $paymentPartnerId = null,
		?string $recurrenceInterval = null,
		?int $recurrenceMultiplier = null,
		?int $recurrenceNextDate = null,
		?int $recurrenceEndDate = null,
		bool $isSubscription = false,
		bool $isFixedCost = false,
		bool $isChildRelated = false,
		bool $isImportant = false,
		bool $needsReview = false,
		bool $isTaxRelevant = false,
		?int $reminderDate = null,
		bool $reminderNotified = false,
		?string $reminderText = null,
		?int $recurrenceParentId = null,
		?string $userId = null,
		?string $splitMode = null
	): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($date === 0) {
				$date = time();
			}

			$amountCents = null;
			if ($validationError = $this->validateEntryPayload($type, $amount, $date, $projectId, $categoryId, $paymentPartnerId, $recurrenceInterval, $recurrenceMultiplier, $recurrenceNextDate, $recurrenceEndDate, $reminderDate, $amountCents, $recurrenceParentId)) {
				return $validationError;
			}
			if ($validationError = $this->validateSplitMode($splitMode)) {
				return $validationError;
			}
			$entryUserId = $userId;
			if ($validationError = $this->validateEntryUserId($projectId, $entryUserId)) {
				return $validationError;
			}
			if ($type !== 'expense') {
				$isSubscription = false;
				$isFixedCost = false;
			}

			$workspaceId = $this->getWorkspaceId();

			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entries')
				->values([
					'user_id' => $qb->createNamedParameter($entryUserId),
					'project_id' => $qb->createNamedParameter($projectId, $projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'type' => $qb->createNamedParameter($type),
					'amount' => $qb->createNamedParameter($this->centsToAmountString($amountCents)),
					'amount_cents' => $qb->createNamedParameter($amountCents, \PDO::PARAM_INT),
					'currency' => $qb->createNamedParameter($currency),
					'date' => $qb->createNamedParameter($date, \PDO::PARAM_INT),
					'category_id' => $qb->createNamedParameter($categoryId, $categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'description' => $qb->createNamedParameter($description ?? ''),
					'payment_partner_id' => $qb->createNamedParameter($paymentPartnerId, $paymentPartnerId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'split_mode' => $qb->createNamedParameter($splitMode),
					'is_settled' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'recurrence_interval' => $qb->createNamedParameter($recurrenceInterval, $recurrenceInterval === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'recurrence_multiplier' => $qb->createNamedParameter($recurrenceMultiplier, $recurrenceMultiplier === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'recurrence_next_date' => $qb->createNamedParameter($recurrenceNextDate, $recurrenceNextDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'recurrence_end_date' => $qb->createNamedParameter($recurrenceEndDate, $recurrenceEndDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'is_subscription' => $qb->createNamedParameter($isSubscription, \PDO::PARAM_BOOL),
					'is_fixed_cost' => $qb->createNamedParameter($isFixedCost, \PDO::PARAM_BOOL),
					'is_child_related' => $qb->createNamedParameter($isChildRelated, \PDO::PARAM_BOOL),
					'is_important' => $qb->createNamedParameter($isImportant, \PDO::PARAM_BOOL),
					'needs_review' => $qb->createNamedParameter($needsReview, \PDO::PARAM_BOOL),
					'is_tax_relevant' => $qb->createNamedParameter($isTaxRelevant, \PDO::PARAM_BOOL),
					'reminder_date' => $qb->createNamedParameter($reminderDate, $reminderDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'reminder_notified' => $qb->createNamedParameter($reminderNotified, \PDO::PARAM_BOOL),
					'reminder_text' => $qb->createNamedParameter($reminderText, $reminderText === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'recurrence_parent_id' => $qb->createNamedParameter($recurrenceParentId, $recurrenceParentId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'recurrence_series_id' => $qb->createNamedParameter(null, \PDO::PARAM_NULL),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_entries');
			$this->hashtagService->syncEntryHashtags($id, $workspaceId, $description ?? '');
			if ($recurrenceInterval !== null) {
				$this->setEntryRecurrenceSeriesId($id, $id, $workspaceId);
			}
			if ($projectId !== null) {
				$this->projectNotificationService->notifyEntryCreated(
					$projectId,
					$workspaceId,
					$id,
					(string)$this->userId,
					$entryUserId,
					$type,
					$amountCents ?? 0,
					$currency,
					$description ?? ''
				);
			}

			return new DataResponse(['id' => $id, 'status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		string $type = 'expense',
		float $amount = 0,
		?string $description = '',
		int $date = 0,
		string $currency = 'EUR',
		?int $projectId = null,
		?int $categoryId = null,
		?int $paymentPartnerId = null,
		?string $recurrenceInterval = null,
		?int $recurrenceMultiplier = null,
		?int $recurrenceNextDate = null,
		?int $recurrenceEndDate = null,
		bool $isSubscription = false,
		bool $isFixedCost = false,
		bool $isChildRelated = false,
		bool $isImportant = false,
		bool $needsReview = false,
		bool $isTaxRelevant = false,
		?int $reminderDate = null,
		bool $reminderNotified = false,
		?string $reminderText = null,
		?string $userId = null,
		?string $splitMode = null
		): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

			$amountCents = null;
			if ($validationError = $this->validateEntryPayload($type, $amount, $date, $projectId, $categoryId, $paymentPartnerId, $recurrenceInterval, $recurrenceMultiplier, $recurrenceNextDate, $recurrenceEndDate, $reminderDate, $amountCents)) {
				return $validationError;
			}
			if ($validationError = $this->validateSplitMode($splitMode)) {
				return $validationError;
			}
			$entryUserId = $userId;
			if ($validationError = $this->validateEntryUserId($projectId, $entryUserId)) {
				return $validationError;
			}
			if ($type !== 'expense') {
				$isSubscription = false;
				$isFixedCost = false;
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);

			if (!$entry) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			if ($entry['is_settled']) {
				return $this->errorResponse('Settled payments cannot be edited', Http::STATUS_FORBIDDEN);
			}
			$currentWorkspaceId = (int)$entry['workspace_id'];
			$workspaceId = $this->workspaceIdForEntryScope($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Bereich nicht gefunden oder nicht im aktiven Workspace', Http::STATUS_FORBIDDEN);
			}

			$updatedEntry = $entry;
			$updatedEntry['user_id'] = $entryUserId;
			$updatedEntry['project_id'] = $projectId;
			$updatedEntry['workspace_id'] = $workspaceId;
			$updatedEntry['type'] = $type;
			$updatedEntry['amount'] = $this->centsToAmountString($amountCents);
			$updatedEntry['amount_cents'] = $amountCents;
			$updatedEntry['currency'] = $currency;
			$updatedEntry['date'] = $date;
			$updatedEntry['category_id'] = $categoryId;
			$updatedEntry['description'] = $description ?? '';
			$updatedEntry['payment_partner_id'] = $paymentPartnerId;
			$updatedEntry['split_mode'] = $splitMode;
			$updatedEntry['recurrence_interval'] = $recurrenceInterval;
			$updatedEntry['recurrence_multiplier'] = $recurrenceMultiplier;
			$updatedEntry['recurrence_next_date'] = $recurrenceNextDate;
			$updatedEntry['recurrence_end_date'] = $recurrenceEndDate;
			$updatedEntry['is_subscription'] = $isSubscription;
			$updatedEntry['is_fixed_cost'] = $isFixedCost;
			$updatedEntry['is_child_related'] = $isChildRelated;
			$updatedEntry['is_important'] = $isImportant;
			$updatedEntry['needs_review'] = $needsReview;
			$updatedEntry['is_tax_relevant'] = $isTaxRelevant;
			$updatedEntry['reminder_date'] = $reminderDate;
			$updatedEntry['reminder_notified'] = $reminderNotified;
			$updatedEntry['reminder_text'] = $reminderText;

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entries')
				->set('user_id', $qb->createNamedParameter($entryUserId))
				->set('project_id', $qb->createNamedParameter($projectId, $projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				->set('type', $qb->createNamedParameter($type))
				->set('amount', $qb->createNamedParameter($this->centsToAmountString($amountCents)))
				->set('amount_cents', $qb->createNamedParameter($amountCents, \PDO::PARAM_INT))
				->set('currency', $qb->createNamedParameter($currency))
				->set('date', $qb->createNamedParameter($date, \PDO::PARAM_INT))
				->set('category_id', $qb->createNamedParameter($categoryId, $categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('description', $qb->createNamedParameter($description ?? ''))
				->set('payment_partner_id', $qb->createNamedParameter($paymentPartnerId, $paymentPartnerId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('split_mode', $qb->createNamedParameter($splitMode))
				->set('recurrence_interval', $qb->createNamedParameter($recurrenceInterval, $recurrenceInterval === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR))
				->set('recurrence_multiplier', $qb->createNamedParameter($recurrenceMultiplier, $recurrenceMultiplier === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('recurrence_next_date', $qb->createNamedParameter($recurrenceNextDate, $recurrenceNextDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('recurrence_end_date', $qb->createNamedParameter($recurrenceEndDate, $recurrenceEndDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('is_subscription', $qb->createNamedParameter($isSubscription, \PDO::PARAM_BOOL))
				->set('is_fixed_cost', $qb->createNamedParameter($isFixedCost, \PDO::PARAM_BOOL))
				->set('is_child_related', $qb->createNamedParameter($isChildRelated, \PDO::PARAM_BOOL))
				->set('is_important', $qb->createNamedParameter($isImportant, \PDO::PARAM_BOOL))
				->set('needs_review', $qb->createNamedParameter($needsReview, \PDO::PARAM_BOOL))
				->set('is_tax_relevant', $qb->createNamedParameter($isTaxRelevant, \PDO::PARAM_BOOL))
				->set('reminder_date', $qb->createNamedParameter($reminderDate, $reminderDate === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('reminder_notified', $qb->createNamedParameter($reminderNotified, \PDO::PARAM_BOOL))
				->set('reminder_text', $qb->createNamedParameter($reminderText, $reminderText === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($currentWorkspaceId, \PDO::PARAM_INT)));
			$qb->executeStatement();
			$this->recordEntryHistory($id, $workspaceId, $projectId, $entry, $updatedEntry);
			$this->hashtagService->syncEntryHashtags($id, $workspaceId, $description ?? '');

			if ($recurrenceInterval !== null) {
				$this->setEntryRecurrenceSeriesId($id, empty($entry['recurrence_series_id']) ? $id : (int)$entry['recurrence_series_id'], $workspaceId);
			}

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function stopRecurrence(int $id): DataResponse {
			try {
				if ($error = $this->authErrorResponse()) {
					return $error;
				}

				if ($validationError = $this->validatePositiveId($id)) {
					return $validationError;
				}

				$entry = $this->entryVisibleInActiveWorkspace($id);

			if (!$entry) {
				return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$entry['workspace_id'];

			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('cobudget_entries')
				->set('recurrence_interval', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('recurrence_multiplier', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('recurrence_next_date', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('recurrence_end_date', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($updateQb->expr()->eq('workspace_id', $updateQb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$updateQb->executeStatement();
			$updatedEntry = $entry;
			$updatedEntry['recurrence_interval'] = null;
			$updatedEntry['recurrence_multiplier'] = null;
			$updatedEntry['recurrence_next_date'] = null;
			$updatedEntry['recurrence_end_date'] = null;
			$this->recordEntryHistory(
				$id,
				$workspaceId,
				empty($entry['project_id']) ? null : (int)$entry['project_id'],
				$entry,
				$updatedEntry
			);

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function history(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);
			if ($entry === null) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('cobudget_entry_history')
				->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter((int)$entry['workspace_id'], \PDO::PARAM_INT)))
				->orderBy('changed_at', 'DESC')
				->addOrderBy('id', 'DESC');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			return new DataResponse([
				'history' => array_map(fn(array $row): array => $this->normalizeEntryHistoryRow($row), $rows),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function attachments(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->receiptsEnabled()) {
				return $this->errorResponse('Receipts are disabled', Http::STATUS_FORBIDDEN);
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);
			if ($entry === null) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$entry['workspace_id'];

			return new DataResponse([
				'attachments' => $this->fetchEntryAttachments($id, (int)$workspaceId),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function uploadAttachment(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->receiptsEnabled()) {
				return $this->errorResponse('Receipts are disabled', Http::STATUS_FORBIDDEN);
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);
			if ($entry === null) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$entry['workspace_id'];

			$upload = $this->request->getUploadedFile('file');
			if (!is_array($upload) || empty($upload['tmp_name'])) {
				return $this->errorResponse('No file uploaded', Http::STATUS_BAD_REQUEST);
			}

			if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
				return $this->errorResponse('File could not be uploaded', Http::STATUS_BAD_REQUEST);
			}

			$tmpPath = (string)$upload['tmp_name'];
			if (!is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
				return $this->errorResponse('File could not be read', Http::STATUS_BAD_REQUEST);
			}

			$content = file_get_contents($tmpPath);
			if ($content === false) {
				return $this->errorResponse('File could not be read', Http::STATUS_BAD_REQUEST);
			}

			$originalName = (string)($upload['name'] ?? 'beleg');
			$displayName = $this->sanitizeAttachmentFileName($originalName);
			$fileName = $this->uniqueAttachmentFileName($displayName, $id);
			$folderPath = $this->attachmentFolderPath((int)($entry['date'] ?? time()));
			$userFolder = $this->rootFolder->getUserFolder((string)$this->userId);
			$targetFolder = $this->ensureFolderPath($userFolder, $folderPath);
			$fileName = $this->resolveUniqueNameInFolder($targetFolder, $fileName);
			$file = $targetFolder->newFile($fileName);
			$file->putContent($content);

			$relativePath = trim($folderPath . '/' . $fileName, '/');
			$mimeType = method_exists($file, 'getMimeType') ? (string)$file->getMimeType() : (string)($upload['type'] ?? '');
			$fileSize = method_exists($file, 'getSize') ? (int)$file->getSize() : (int)($upload['size'] ?? strlen($content));
			$fileId = method_exists($file, 'getId') ? $file->getId() : null;

			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_attachments')
				->values([
					'entry_id' => $qb->createNamedParameter($id, \PDO::PARAM_INT),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'owner_user_id' => $qb->createNamedParameter($this->userId),
					'file_id' => $qb->createNamedParameter($fileId, $fileId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'file_path' => $qb->createNamedParameter($relativePath),
					'file_name' => $qb->createNamedParameter(mb_substr($displayName, 0, 255)),
					'mime_type' => $qb->createNamedParameter($mimeType !== '' ? mb_substr($mimeType, 0, 128) : null, $mimeType === '' ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'file_size' => $qb->createNamedParameter($fileSize, \PDO::PARAM_INT),
					'created_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$attachmentId = (int)$this->db->lastInsertId('*PREFIX*cobudget_entry_attachments');
			$attachment = $this->fetchEntryAttachment($attachmentId, $id, (int)$workspaceId);

			return new DataResponse([
				'status' => 'success',
				'attachment' => $attachment,
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function downloadAttachment(int $id, int $attachmentId, ?int $workspaceId = null): Response {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->receiptsEnabled()) {
				return $this->errorResponse('Receipts are disabled', Http::STATUS_FORBIDDEN);
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if ($validationError = $this->validatePositiveId($attachmentId)) {
				return $validationError;
			}
			if ($workspaceId !== null) {
				if ($validationError = $this->validatePositiveId($workspaceId, 'Invalid workspace id')) {
					return $validationError;
				}
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);
			if ($entry === null) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			$activeWorkspaceId = (int)$entry['workspace_id'];
			if ($workspaceId !== null && (int)$workspaceId !== $activeWorkspaceId) {
				return $this->errorResponse('Workspace not found or no permission', Http::STATUS_FORBIDDEN);
			}

			$attachment = $this->fetchEntryAttachment($attachmentId, $id, (int)$activeWorkspaceId);
			if ($attachment === null) {
				return $this->errorResponse('Receipt not found', Http::STATUS_NOT_FOUND);
			}

			$file = $this->attachmentFile($attachment);
			if (!$file instanceof File) {
				return $this->errorResponse('Receipt file not found', Http::STATUS_NOT_FOUND);
			}

			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Type' => (string)($attachment['mime_type'] ?: $file->getMimeType() ?: 'application/octet-stream'),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroyAttachment(int $id, int $attachmentId): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}
			if (!$this->receiptsEnabled()) {
				return $this->errorResponse('Receipts are disabled', Http::STATUS_FORBIDDEN);
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if ($validationError = $this->validatePositiveId($attachmentId)) {
				return $validationError;
			}

			$entry = $this->entryVisibleInActiveWorkspace($id);
			if ($entry === null) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$entry['workspace_id'];

			$attachment = $this->fetchEntryAttachment($attachmentId, $id, (int)$workspaceId);
			if ($attachment === null) {
				return $this->errorResponse('Receipt not found', Http::STATUS_NOT_FOUND);
			}

			$this->deleteAttachmentFile($attachment);
			$this->deleteAttachmentRow($attachmentId, $id, (int)$workspaceId);

			return new DataResponse(['status' => 'success']);
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

				$entry = $this->entryVisibleInActiveWorkspace($id);

			if (!$entry) {
				return $this->errorResponse('Payment not found', Http::STATUS_NOT_FOUND);
			}
			if ($entry['is_settled']) {
				return $this->errorResponse('Settled payments cannot be deleted', Http::STATUS_FORBIDDEN);
			}
			$workspaceId = (int)$entry['workspace_id'];

			$this->deleteEntryAttachments($id, (int)$workspaceId);
			$this->hashtagService->deleteEntryHashtags($id);
			$this->deleteEntryHistory($id, (int)$workspaceId);

			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_entries')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function fetchEntryAttachments(int $entryId, int $workspaceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(fn(array $row): array => $this->normalizeAttachmentRow($row), $rows);
	}

	private function receiptsEnabled(): bool {
		return $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_receipts', 'yes') === 'yes';
	}

	private function fetchEntryAttachment(int $attachmentId, int $entryId, int $workspaceId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($attachmentId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ? $this->normalizeAttachmentRow($row) : null;
	}

	private function normalizeAttachmentRow(array $row): array {
		return [
			'id' => (int)$row['id'],
			'entry_id' => (int)$row['entry_id'],
			'file_name' => (string)$row['file_name'],
			'mime_type' => (string)($row['mime_type'] ?? ''),
			'file_size' => (int)($row['file_size'] ?? 0),
			'created_at' => (int)($row['created_at'] ?? 0),
			'owner_user_id' => (string)($row['owner_user_id'] ?? ''),
			'file_path' => (string)($row['file_path'] ?? ''),
		];
	}

	private function attachmentFolderPath(int $entryDate): string {
		$baseFolder = $this->normalizedReceiptStorageFolder(
			$this->config->getUserValue((string)$this->userId, 'cobudget', 'receipt_storage_folder', 'CoBudget/Belege')
		);
		$grouping = $this->config->getUserValue((string)$this->userId, 'cobudget', 'receipt_folder_grouping', 'year');
		$timestamp = $entryDate > 0 ? $entryDate : time();

		if ($grouping === 'year_month') {
			return trim($baseFolder . '/' . date('Y', $timestamp) . '/' . date('m', $timestamp), '/');
		}
		if ($grouping === 'year') {
			return trim($baseFolder . '/' . date('Y', $timestamp), '/');
		}

		return $baseFolder;
	}

	private function normalizedReceiptStorageFolder(string $folder): string {
		$folder = trim($folder);
		$folder = trim($folder, '/');

		if ($folder === '' || str_contains($folder, '\\') || preg_match('~(^|/)\.\.(/|$)~', $folder) === 1) {
			return 'CoBudget/Belege';
		}

		return $folder;
	}

	private function ensureFolderPath(Folder $root, string $relativePath): Folder {
		$current = $root;
		$segments = array_values(array_filter(explode('/', trim($relativePath, '/')), static fn(string $segment): bool => $segment !== ''));

		foreach ($segments as $segment) {
			if (!$current->nodeExists($segment)) {
				$current = $current->newFolder($segment);
				continue;
			}

			$node = $current->get($segment);
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Receipt folder could not be created.');
			}
			$current = $node;
		}

		return $current;
	}

	private function sanitizeAttachmentFileName(string $fileName): string {
		$fileName = trim($fileName);
		$fileName = preg_replace('/[^\pL\pN._ -]+/u', '_', $fileName) ?? '';
		$fileName = trim($fileName, " ._\t\n\r\0\x0B");

		if ($fileName === '') {
			return 'beleg';
		}

		return mb_substr($fileName, 0, 120);
	}

	private function uniqueAttachmentFileName(string $fileName, int $entryId): string {
		$timestamp = date('Ymd-His');
		$extension = '';
		$baseName = $fileName;
		$dotPos = strrpos($fileName, '.');
		if ($dotPos !== false && $dotPos > 0) {
			$baseName = substr($fileName, 0, $dotPos);
			$extension = substr($fileName, $dotPos);
		}

		return 'eintrag-' . $entryId . '-' . $timestamp . '-' . $baseName . $extension;
	}

	private function resolveUniqueNameInFolder(Folder $folder, string $fileName): string {
		if (!$folder->nodeExists($fileName)) {
			return $fileName;
		}

		$extension = '';
		$baseName = $fileName;
		$dotPos = strrpos($fileName, '.');
		if ($dotPos !== false && $dotPos > 0) {
			$baseName = substr($fileName, 0, $dotPos);
			$extension = substr($fileName, $dotPos);
		}

		for ($i = 2; $i < 1000; $i++) {
			$candidate = $baseName . '-' . $i . $extension;
			if (!$folder->nodeExists($candidate)) {
				return $candidate;
			}
		}

		return $baseName . '-' . time() . $extension;
	}

	private function attachmentFile(array $attachment): ?File {
		try {
			$ownerUserId = (string)($attachment['owner_user_id'] ?? '');
			$filePath = trim((string)($attachment['file_path'] ?? ''), '/');
			if ($ownerUserId === '' || $filePath === '') {
				return null;
			}

			$userFolder = $this->rootFolder->getUserFolder($ownerUserId);
			if (!$userFolder->nodeExists($filePath)) {
				return null;
			}

			$node = $userFolder->get($filePath);
			return $node instanceof File ? $node : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function deleteAttachmentFile(array $attachment): void {
		$file = $this->attachmentFile($attachment);
		if ($file instanceof File) {
			$file->delete();
		}
	}

	private function deleteAttachmentRow(int $attachmentId, int $entryId, int $workspaceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_attachments')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($attachmentId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function deleteEntryAttachments(int $entryId, int $workspaceId): void {
		$attachments = $this->fetchEntryAttachments($entryId, $workspaceId);
		if ($this->config->getUserValue((string)$this->userId, 'cobudget', 'delete_receipts_with_entry', 'no') === 'yes') {
			foreach ($attachments as $attachment) {
				$this->deleteAttachmentFile($attachment);
			}
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_attachments')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function recordEntryHistory(int $entryId, int $workspaceId, ?int $projectId, array $oldEntry, array $newEntry): void {
		$changes = [];
		foreach (self::ENTRY_HISTORY_FIELDS as $field) {
			$oldValue = $this->historyComparableValue($field, $oldEntry[$field] ?? null);
			$newValue = $this->historyComparableValue($field, $newEntry[$field] ?? null);
			if ($oldValue === $newValue) {
				continue;
			}

			$changes[] = [
				'field' => $field,
				'old_value' => $oldValue,
				'new_value' => $newValue,
				'old_display' => $this->historyDisplayValue($field, $oldValue, $oldEntry),
				'new_display' => $this->historyDisplayValue($field, $newValue, $newEntry),
			];
		}

		if ($changes === []) {
			return;
		}

		try {
			$changeGroup = bin2hex(random_bytes(8));
		} catch (\Throwable $e) {
			$changeGroup = str_replace('.', '', uniqid('', true));
		}

		$changedBy = (string)$this->userId;
		$changedByDisplayName = $this->displayNameForUserId($changedBy);
		$changedAt = time();

		foreach ($changes as $change) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_history')
				->values([
					'entry_id' => $qb->createNamedParameter($entryId, \PDO::PARAM_INT),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'project_id' => $qb->createNamedParameter($projectId, $projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					'changed_by' => $qb->createNamedParameter($changedBy),
					'changed_by_display_name' => $qb->createNamedParameter($changedByDisplayName),
					'changed_at' => $qb->createNamedParameter($changedAt, \PDO::PARAM_INT),
					'change_group' => $qb->createNamedParameter($changeGroup),
					'field' => $qb->createNamedParameter($change['field']),
					'old_value' => $qb->createNamedParameter($change['old_value'], $change['old_value'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'new_value' => $qb->createNamedParameter($change['new_value'], $change['new_value'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'old_display' => $qb->createNamedParameter($change['old_display']),
					'new_display' => $qb->createNamedParameter($change['new_display']),
				]);
			$qb->executeStatement();
		}
	}

	private function normalizeEntryHistoryRow(array $row): array {
		$changedAt = (int)($row['changed_at'] ?? 0);

		return [
			'id' => (int)($row['id'] ?? 0),
			'entry_id' => (int)($row['entry_id'] ?? 0),
			'workspace_id' => (int)($row['workspace_id'] ?? 0),
			'project_id' => empty($row['project_id']) ? null : (int)$row['project_id'],
			'changed_by' => (string)($row['changed_by'] ?? ''),
			'changed_by_display_name' => (string)($row['changed_by_display_name'] ?? $row['changed_by'] ?? ''),
			'changed_at' => $changedAt,
			'changed_at_display' => $changedAt > 0 ? date('d.m.Y H:i', $changedAt) : '-',
			'change_group' => (string)($row['change_group'] ?? ''),
			'field' => (string)($row['field'] ?? ''),
			'field_label' => $this->historyFieldLabel((string)($row['field'] ?? '')),
			'old_value' => $row['old_value'] ?? null,
			'new_value' => $row['new_value'] ?? null,
			'old_display' => (string)($row['old_display'] ?? '-'),
			'new_display' => (string)($row['new_display'] ?? '-'),
		];
	}

	private function deleteEntryHistory(int $entryId, int $workspaceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_history')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function historyComparableValue(string $field, $value): ?string {
		if (in_array($field, self::ENTRY_HISTORY_BOOLEAN_FIELDS, true)) {
			return $this->dbBool($value) ? '1' : '0';
		}

		if ($value === null || $value === '') {
			return null;
		}

		if (in_array($field, self::ENTRY_HISTORY_INTEGER_FIELDS, true)) {
			$intValue = (int)$value;
			return $intValue > 0 || $field === 'amount_cents' || $field === 'recurrence_multiplier' ? (string)$intValue : null;
		}

		if (in_array($field, self::ENTRY_HISTORY_STRING_FIELDS, true)) {
			$stringValue = trim((string)$value);
			return $stringValue === '' ? null : $stringValue;
		}

		return (string)$value;
	}

	private function historyDisplayValue(string $field, ?string $value, array $entry): string {
		if ($value === null || $value === '') {
			return '-';
		}

		if ($field === 'amount_cents') {
			$currency = trim((string)($entry['currency'] ?? 'EUR')) ?: 'EUR';
			return number_format(((int)$value) / 100, 2, ',', '.') . ' ' . $currency;
		}
		if ($field === 'type') {
			return $value === 'income' ? $this->l10n->t('Income') : $this->l10n->t('Expense');
		}
		if ($field === 'split_mode') {
			return $this->normalizeSplitMode($value) === 'single_user'
				? $this->l10n->t('Assigned fully to the selected user')
				: $this->l10n->t('By area split');
		}
		if (in_array($field, self::ENTRY_HISTORY_BOOLEAN_FIELDS, true)) {
			return $value === '1' ? $this->l10n->t('Yes') : $this->l10n->t('No');
		}
		if (in_array($field, ['date', 'recurrence_end_date'], true)) {
			return $this->formatHistoryTimestamp($value);
		}
		if (in_array($field, ['recurrence_next_date', 'reminder_date'], true)) {
			return $this->formatHistoryTimestamp($value, true);
		}
		if ($field === 'category_id') {
			return $this->lookupHistoryName('cobudget_categories', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'payment_partner_id') {
			return $this->lookupHistoryName('cobudget_payment_partners', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'project_id') {
			return $this->lookupHistoryName('cobudget_projects', (int)$value) ?? ('#' . $value);
		}
		if ($field === 'user_id') {
			return $this->displayNameForUserId($value);
		}

		return $value;
	}

	private function historyFieldLabel(string $field): string {
		return match ($field) {
			'type' => $this->l10n->t('Type'),
			'amount_cents' => $this->l10n->t('Amount'),
			'currency' => $this->l10n->t('Currency'),
			'date' => $this->l10n->t('Date'),
			'category_id' => $this->l10n->t('Category'),
			'payment_partner_id' => $this->l10n->t('Payment partner'),
			'description' => $this->l10n->t('Description'),
			'project_id' => $this->l10n->t('Area'),
			'user_id' => $this->l10n->t('Paid/received by'),
			'split_mode' => $this->l10n->t('Split'),
			'recurrence_interval' => $this->l10n->t('Recurrence'),
			'recurrence_multiplier' => $this->l10n->t('Recurrence count'),
			'recurrence_next_date' => $this->l10n->t('Next recurrence'),
			'recurrence_end_date' => $this->l10n->t('Recurs until'),
			'is_subscription' => $this->l10n->t('Subscription'),
			'is_fixed_cost' => $this->l10n->t('Fixed costs'),
			'is_child_related' => $this->l10n->t('Children'),
			'is_important' => $this->l10n->t('Important'),
			'needs_review' => $this->l10n->t('Review'),
			'is_tax_relevant' => $this->l10n->t('Tax-relevant'),
			'reminder_date' => $this->l10n->t('Reminder'),
			'reminder_text' => $this->l10n->t('Reminder text'),
			default => $field,
		};
	}

	private function displayNameForUserId(string $userId): string {
		$userId = trim($userId);
		if ($userId === '') {
			return '-';
		}

		$user = $this->userManager->get($userId);
		return $user ? $user->getDisplayName() : $userId;
	}

	private function lookupHistoryName(string $table, int $id): ?string {
		if ($id <= 0) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('name')
			->from($table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$name = $result->fetchOne();
		$result->closeCursor();

		return is_string($name) && $name !== '' ? $name : null;
	}

	private function formatHistoryTimestamp(?string $value, bool $includeTime = false): string {
		$timestamp = (int)($value ?? 0);
		if ($timestamp <= 0) {
			return '-';
		}

		return date($includeTime ? 'd.m.Y H:i' : 'd.m.Y', $timestamp);
	}

	private function setEntryRecurrenceSeriesId(int $entryId, int $seriesId, int $workspaceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('recurrence_series_id', $qb->createNamedParameter($seriesId, \PDO::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function validateEntryUserId(?int $projectId, ?string &$entryUserId): ?DataResponse {
		$entryUserId = trim((string)($entryUserId ?: $this->userId));
		if ($entryUserId === '') {
			return $this->errorResponse('User could not be determined', Http::STATUS_BAD_REQUEST);
		}

		if ($projectId === null) {
			$entryUserId = $this->userId;
			return null;
		}

		if (!$this->projectUserMemberInActiveWorkspace($projectId, $entryUserId)) {
			return $this->errorResponse('User is not a member of this area', Http::STATUS_FORBIDDEN);
		}

		return null;
	}
}

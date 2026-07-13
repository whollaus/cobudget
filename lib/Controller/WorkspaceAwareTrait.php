<?php

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\ParticipantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait WorkspaceAwareTrait {
	protected ?int $workspaceId = null;
	protected ?DataResponse $workspaceHeaderErrorResponse = null;

	protected function initWorkspace(): void {
		$workspaceIdStr = trim((string)$this->request->getHeader('X-Workspace-Id'));
		if ($workspaceIdStr === '') {
			return;
		}

		if (!ctype_digit($workspaceIdStr) || (int)$workspaceIdStr <= 0) {
			$this->workspaceHeaderErrorResponse = $this->errorResponse('Invalid workspace id', Http::STATUS_BAD_REQUEST);
			return;
		}

		$workspaceId = (int)$workspaceIdStr;
		if (!$this->workspaceBelongsToUser($workspaceId)) {
			$this->workspaceHeaderErrorResponse = $this->errorResponse('Workspace not found or no permission', Http::STATUS_FORBIDDEN);
			return;
		}

		$this->workspaceId = $workspaceId;
	}

	protected function getWorkspaceId(): ?int {
		if ($this->workspaceId !== null) {
			return $this->workspaceId;
		}

		if ($this->workspaceHeaderErrorResponse !== null) {
			return null;
		}
		
		if (empty($this->userId)) {
			return null;
		}

		// Fallback to default workspace
		$this->workspaceId = $this->findDefaultWorkspaceId();

		if ($this->workspaceId === null) {
			$this->workspaceId = $this->createDefaultWorkspace();
		}

		if ($this->workspaceId !== null) {
			$this->assignUnscopedRowsToWorkspace($this->workspaceId);
		}

		return $this->workspaceId;
	}

	protected function authErrorResponse(): ?DataResponse {
		if (empty($this->userId)) {
			return $this->errorResponse('Not authenticated', Http::STATUS_UNAUTHORIZED);
		}

		if ($this->workspaceHeaderErrorResponse !== null) {
			return $this->workspaceHeaderErrorResponse;
		}

		return null;
	}

	protected function errorResponse(string $message, int $status): DataResponse {
		if (property_exists($this, 'l10n') && $this->l10n instanceof \OCP\IL10N) {
			$message = $this->l10n->t($message);
		}
		return new DataResponse(['error' => $message], $status);
	}

	protected function loggedErrorResponse(
		\Throwable $e,
		string $message = 'Ein interner Fehler ist aufgetreten.',
		int $status = Http::STATUS_INTERNAL_SERVER_ERROR,
		?string $logMessage = null
	): DataResponse {
		$this->logInternalException($e, $logMessage ?? 'Unhandled CoBudget controller error');

		return $this->errorResponse($message, $status);
	}

	private function logInternalException(\Throwable $e, string $message): void {
		try {
			$logger = null;
			if (property_exists($this, 'logger') && $this->logger instanceof \Psr\Log\LoggerInterface) {
				$logger = $this->logger;
			} elseif (class_exists(\OC::class) && isset(\OC::$server)) {
				$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
			}

			if ($logger instanceof \Psr\Log\LoggerInterface) {
				$logger->error($message . ': ' . $e->getMessage(), [
					'app' => 'cobudget',
					'exception' => $e,
					'userId' => property_exists($this, 'userId') ? $this->userId : null,
					'controller' => static::class,
				]);
			}
		} catch (\Throwable $loggingError) {
			// Never let logging failures change the API response.
		}
	}

	protected function validateRequiredName(string &$name, string $message = 'Name is required', int $maxLength = 128): ?DataResponse {
		$normalized = $this->normalizeRequiredName($name, $maxLength);
		if ($normalized === null) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		$name = $normalized;
		return null;
	}

	protected function normalizeRequiredName(string $name, int $maxLength = 128): ?string {
		$name = trim((string)preg_replace('/\s+/u', ' ', $name));
		if ($name === '') {
			return null;
		}

		if (mb_strlen($name) > $maxLength) {
			return mb_substr($name, 0, $maxLength);
		}

		return $name;
	}

	protected function normalizeVisibleName(string $name): string {
		$name = trim((string)preg_replace('/\s+/u', ' ', $name));
		return mb_strtolower($name, 'UTF-8');
	}

	protected function normalizeOptionalString(?string $value, int $maxLength = 1024): string {
		$value = trim((string)$value);
		if (mb_strlen($value) > $maxLength) {
			return mb_substr($value, 0, $maxLength);
		}

		return $value;
	}

	protected function validateRequiredString(string &$value, string $message, int $maxLength = 255): ?DataResponse {
		$value = $this->normalizeOptionalString($value, $maxLength);
		if ($value === '') {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validatePositiveId(int $id, string $message = 'Invalid id'): ?DataResponse {
		if ($id <= 0) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function isValidEntryType(string $type): bool {
		return $type === 'expense' || $type === 'income';
	}

	protected function validateEntryType(string $type, string $message = 'Ungültiger Typ'): ?DataResponse {
		if (!$this->isValidEntryType($type)) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function isValidAmount($amount): bool {
		$normalizedAmount = $this->normalizeAmountInput($amount);
		if ($normalizedAmount === null) {
			return false;
		}

		return is_finite($normalizedAmount) && $normalizedAmount >= 0 && $normalizedAmount <= 99999999.99;
	}

	protected function validateAmountCents($amount, ?int &$amountCents, bool $allowNull = false, string $message = 'Ungültiger Betrag'): ?DataResponse {
		$amountCents = null;
		if ($allowNull && $amount === null) {
			return null;
		}

		if (!$this->isValidAmount($amount)) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		$amountCents = $this->amountToCents($amount);
		if ($amountCents === null) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function amountToCents($amount): ?int {
		$normalizedAmount = $this->normalizeAmountInput($amount);
		if ($normalizedAmount === null || !$this->isValidAmount($normalizedAmount)) {
			return null;
		}

		$decimal = number_format($normalizedAmount, 2, '.', '');
		[$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '00');

		return ((int)$whole * 100) + (int)str_pad(substr($fraction, 0, 2), 2, '0');
	}

	protected function normalizeAmountInput($amount): ?float {
		if (is_int($amount) || is_float($amount)) {
			$value = (float)$amount;
			return is_finite($value) ? $value : null;
		}

		$value = trim((string)$amount);
		if ($value === '') {
			return null;
		}

		$value = preg_replace('/\s+/u', '', $value);
		if (!is_string($value) || !preg_match('/^\+?\d+(?:[.,]\d+)*$/', $value)) {
			return null;
		}

		$lastComma = strrpos($value, ',');
		$lastDot = strrpos($value, '.');
		$hasComma = $lastComma !== false;
		$hasDot = $lastDot !== false;

		if ($hasComma && $hasDot) {
			$decimalSeparator = ($hasComma && (!$hasDot || $lastComma > $lastDot)) ? ',' : '.';
			$otherSeparator = $decimalSeparator === ',' ? '.' : ',';
			$separatorIndex = strrpos($value, $decimalSeparator);
			$fraction = substr($value, $separatorIndex + 1);
			$whole = substr($value, 0, $separatorIndex);
			$value = str_replace($otherSeparator, '', $whole) . '.' . $fraction;
		} elseif ($hasComma || $hasDot) {
			$decimalSeparator = $hasComma ? ',' : '.';
			$separatorIndex = strrpos($value, $decimalSeparator);
			$whole = substr($value, 0, $separatorIndex);
			$fraction = substr($value, $separatorIndex + 1);

			$value = $whole . '.' . $fraction;
		}

		if (!is_numeric($value)) {
			return null;
		}

		$normalized = (float)$value;
		return is_finite($normalized) ? $normalized : null;
	}

	protected function centsToAmount($cents): float {
		return round(((int)$cents) / 100, 2);
	}

	protected function centsToAmountString(?int $cents): ?string {
		if ($cents === null) {
			return null;
		}

		return number_format($this->centsToAmount($cents), 2, '.', '');
	}

	protected function amountCentsFromRow(array $row): ?int {
		if (array_key_exists('amount_cents', $row) && $row['amount_cents'] !== null && $row['amount_cents'] !== '') {
			return (int)$row['amount_cents'];
		}

		if (!array_key_exists('amount', $row) || $row['amount'] === null || $row['amount'] === '') {
			return null;
		}

		return $this->amountToCents($row['amount']);
	}

	protected function normalizeAmountRow(array $row): array {
		$cents = $this->amountCentsFromRow($row);
		$row['amount'] = $cents === null ? null : $this->centsToAmount($cents);
		unset($row['amount_cents']);

		return $row;
	}

	protected function addRecentUsageCounts(array $items, string $referenceColumn, int $workspaceId): array {
		if ($items === [] || !in_array($referenceColumn, ['category_id', 'payment_partner_id'], true) || empty($this->userId)) {
			return array_map(static function(array $item): array {
				$item['recent_usage_count'] = 0;
				return $item;
			}, $items);
		}

		$since = strtotime('-6 months');
		if ($since === false) {
			$since = time() - (183 * 86400);
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select($referenceColumn)
			->addSelect($qb->createFunction('COUNT(*) AS recent_usage_count'))
			->from('cobudget_entries')
			->where($qb->expr()->isNotNull($referenceColumn))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('personal')))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($since, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->lte('date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->groupBy($referenceColumn);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$counts = [];
		foreach ($rows as $row) {
			$counts[(int)$row[$referenceColumn]] = (int)($row['recent_usage_count'] ?? 0);
		}

		return array_map(static function(array $item) use ($counts): array {
			$item['recent_usage_count'] = $counts[(int)($item['id'] ?? 0)] ?? 0;
			return $item;
		}, $items);
	}

	protected function validateRequiredTimestamp(int $timestamp, string $message): ?DataResponse {
		if ($timestamp <= 0) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validateOptionalTimestamp(?int $timestamp, string $message): ?DataResponse {
		if ($timestamp !== null && $timestamp <= 0) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validateOptionalChoice(?string $value, array $allowedValues, string $message): ?DataResponse {
		if ($value !== null && !in_array($value, $allowedValues, true)) {
			return $this->errorResponse($message, Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function normalizeSplitMode(?string $splitMode): string {
		$splitMode = trim((string)$splitMode);
		if ($splitMode === 'single_user') {
			return 'single_user';
		}

		return 'project_shares';
	}

	protected function validateSplitMode(?string &$splitMode): ?DataResponse {
		$rawSplitMode = trim((string)$splitMode);
		if ($rawSplitMode !== '' && !in_array($rawSplitMode, ['project_shares', 'single_user'], true)) {
			return $this->errorResponse('Ungültige Aufteilung', Http::STATUS_BAD_REQUEST);
		}

		$splitMode = $this->normalizeSplitMode($rawSplitMode);
		return null;
	}

	protected function validateTypedNamePayload(string &$name, string $type, string $nameMessage = 'Name is required'): ?DataResponse {
		if ($error = $this->validateRequiredName($name, $nameMessage, 128)) {
			return $error;
		}

		return $this->validateEntryType($type);
	}

	protected function validateEntryReferences(?int $projectId, ?int $categoryId, ?int $paymentPartnerId, ?int $recurrenceParentId = null): ?DataResponse {
		if ($projectId !== null && !$this->projectMemberInActiveWorkspace($projectId)) {
			return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
		}

		if (!$this->categoryAvailableInActiveWorkspace($categoryId, $projectId)) {
			return $this->errorResponse('Category not found or not in the active workspace', Http::STATUS_BAD_REQUEST);
		}

		if (!$this->paymentPartnerAvailableInActiveWorkspace($paymentPartnerId, $projectId)) {
			return $this->errorResponse('Payment partner not found or not in the active workspace', Http::STATUS_BAD_REQUEST);
		}

		if ($recurrenceParentId !== null && $this->entryVisibleInActiveWorkspace($recurrenceParentId) === null) {
			return $this->errorResponse('Ursprungseintrag nicht gefunden oder nicht im aktiven Workspace', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validateRecurrencePayload(
		?string $recurrenceInterval,
		?int $recurrenceMultiplier,
		?int $recurrenceNextDate,
		?int $recurrenceEndDate,
		?int $reminderDate
	): ?DataResponse {
		if ($error = $this->validateOptionalChoice($recurrenceInterval, ['day', 'week', 'month'], 'Invalid recurrence interval')) {
			return $error;
		}

		if ($recurrenceMultiplier !== null && $recurrenceMultiplier < 1) {
			return $this->errorResponse('Invalid recurrence factor', Http::STATUS_BAD_REQUEST);
		}

		if ($error = $this->validateOptionalTimestamp($recurrenceNextDate, 'Invalid next recurrence date')) {
			return $error;
		}

		if ($error = $this->validateOptionalTimestamp($recurrenceEndDate, 'Invalid recurrence end date')) {
			return $error;
		}

		return $this->validateOptionalTimestamp($reminderDate, 'Invalid reminder date');
	}

	protected function validateEntryPayload(
		string $type,
		float $amount,
		int $date,
		?string &$description,
		string &$currency,
		?int $projectId,
		?int $categoryId,
		?int $paymentPartnerId,
		?string $recurrenceInterval,
		?int $recurrenceMultiplier,
		?int $recurrenceNextDate,
		?int $recurrenceEndDate,
		?int $reminderDate,
		?string &$reminderText,
		?int &$amountCents,
		?int $recurrenceParentId = null
	): ?DataResponse {
		if ($error = $this->validateEntryType($type)) {
			return $error;
		}

		if ($error = $this->validateAmountCents($amount, $amountCents)) {
			return $error;
		}

		if ($error = $this->validateRequiredTimestamp($date, 'Invalid date')) {
			return $error;
		}

		if ($error = $this->validateEntryTextPayload($description, $currency, $reminderText)) {
			return $error;
		}

		if ($error = $this->validateEntryReferences($projectId, $categoryId, $paymentPartnerId, $recurrenceParentId)) {
			return $error;
		}

		return $this->validateRecurrencePayload($recurrenceInterval, $recurrenceMultiplier, $recurrenceNextDate, $recurrenceEndDate, $reminderDate);
	}

	protected function validateTemplatePayload(
		string &$name,
		?string &$description,
		string $type,
		?float $amount,
		?int &$amountCents,
		?int $categoryId,
		?int $paymentPartnerId,
		?int $projectId
	): ?DataResponse {
		if ($error = $this->validateTypedNamePayload($name, $type)) {
			return $error;
		}

		if ($description !== null && mb_strlen($description) > 512) {
			return $this->errorResponse('Invalid description', Http::STATUS_BAD_REQUEST);
		}

		if ($error = $this->validateAmountCents($amount, $amountCents, true)) {
			return $error;
		}

		return $this->validateEntryReferences($projectId, $categoryId, $paymentPartnerId);
	}

	protected function validateCurrencySetting(string &$currency): ?DataResponse {
		$currency = trim($currency);
		if (mb_strlen($currency) > 10) {
			return $this->errorResponse('Invalid currency', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validateEntryTextPayload(?string &$description, string &$currency, ?string &$reminderText): ?DataResponse {
		$description ??= '';
		if (mb_strlen($description) > 512) {
			return $this->errorResponse('Invalid description', Http::STATUS_BAD_REQUEST);
		}

		$currency = trim($currency);
		if ($currency === '' || mb_strlen($currency) > 10) {
			return $this->errorResponse('Invalid currency', Http::STATUS_BAD_REQUEST);
		}

		if ($reminderText !== null && mb_strlen($reminderText) > 255) {
			return $this->errorResponse('Invalid reminder text', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function validateDefaultStartPage(?string $defaultStartPage): ?DataResponse {
		if ($defaultStartPage === null || in_array($defaultStartPage, ['personal', 'currentYear', 'projects'], true)) {
			return null;
		}

		if (preg_match('/^project:[1-9]\d*$/', $defaultStartPage) === 1) {
			return null;
		}

		return $this->errorResponse('Invalid default start page', Http::STATUS_BAD_REQUEST);
	}

	protected function validateEntriesPerPage(?int $entriesPerPage): ?DataResponse {
		if ($entriesPerPage !== null && !in_array($entriesPerPage, [10, 25, 50, 100, 250], true)) {
			return $this->errorResponse('Invalid entries per page', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	protected function normalizeUserIdList(array $userIds): array {
		$normalized = [];
		foreach ($userIds as $userId) {
			$userId = $this->normalizeOptionalString((string)$userId, 255);
			if ($userId !== '') {
				$normalized[] = $userId;
			}
		}

		return array_values(array_unique($normalized));
	}

	protected function equalShareBasisPoints(array $userIds): array {
		$userIds = array_values(array_unique(array_filter(array_map('strval', $userIds), static fn(string $userId): bool => $userId !== '')));
		$count = count($userIds);
		if ($count === 0) {
			return [];
		}

		$basePercent = intdiv(100, $count);
		$remainderPercent = 100 - ($basePercent * $count);
		$shares = [];
		foreach ($userIds as $index => $userId) {
			$shares[$userId] = ($basePercent + ($index < $remainderPercent ? 1 : 0)) * 100;
		}

		return $shares;
	}

	protected function normalizeShareBasisPointsToWholePercent(array $userIds, array $shares, int $total): array {
		if ($userIds === []) {
			return [];
		}

		if ($total <= 0) {
			return $this->equalShareBasisPoints($userIds);
		}

		$percentRows = [];
		$allocatedPercent = 0;
		foreach ($userIds as $index => $userId) {
			$rawPercent = (($shares[$userId] ?? 0) / $total) * 100;
			$wholePercent = (int)floor($rawPercent);
			$allocatedPercent += $wholePercent;
			$percentRows[] = [
				'userId' => $userId,
				'percent' => $wholePercent,
				'remainder' => $rawPercent - $wholePercent,
				'index' => $index,
			];
		}

		$remainingPercent = 100 - $allocatedPercent;
		usort($percentRows, static function(array $a, array $b): int {
			$remainderCompare = $b['remainder'] <=> $a['remainder'];
			if ($remainderCompare !== 0) {
				return $remainderCompare;
			}

			return $a['index'] <=> $b['index'];
		});

		for ($i = 0; $i < $remainingPercent && $i < count($percentRows); $i++) {
			$percentRows[$i]['percent']++;
		}

		usort($percentRows, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);

		$normalized = [];
		foreach ($percentRows as $row) {
			$normalized[$row['userId']] = $row['percent'] * 100;
		}

		return $normalized;
	}

	protected function memberShareBasisPoints(array $members): array {
		$shares = [];
		$userIds = [];
		foreach ($members as $member) {
			$userId = (string)($member['id'] ?? $member['user_id'] ?? $member['userId'] ?? '');
			if ($userId === '') {
				continue;
			}
			$userIds[] = $userId;
			$shares[$userId] = max(0, (int)($member['share_basis_points'] ?? $member['shareBasisPoints'] ?? 0));
		}

		if ($userIds === []) {
			return [];
		}

		$total = array_sum($shares);
		if ($total <= 0) {
			return $this->equalShareBasisPoints($userIds);
		}

		$allWholePercent = true;
		foreach ($shares as $shareBasisPoints) {
			if ($shareBasisPoints % 100 !== 0) {
				$allWholePercent = false;
				break;
			}
		}

		if ($total === 10000 && $allWholePercent) {
			return $shares;
		}

		return $this->normalizeShareBasisPointsToWholePercent($userIds, $shares, $total);
	}

	protected function distributeAmountCents(int $amountCents, array $shareBasisPointsByUserId): array {
		$userIds = array_keys($shareBasisPointsByUserId);
		if ($userIds === []) {
			return [];
		}

		$distributed = [];
		$allocated = 0;
		$lastIndex = count($userIds) - 1;
		foreach ($userIds as $index => $userId) {
			if ($index === $lastIndex) {
				$distributed[$userId] = $amountCents - $allocated;
				continue;
			}

			$share = (int)round($amountCents * ((int)$shareBasisPointsByUserId[$userId]) / 10000);
			$distributed[$userId] = $share;
			$allocated += $share;
		}

		return $distributed;
	}

	protected function entryShareCentsForUser(array $entry, string $userId, int $amountCents, array $shareBasisPointsByUserId): int {
		$splitMode = $this->normalizeSplitMode($entry['split_mode'] ?? null);
		if ($splitMode === 'single_user') {
			return $this->entrySplitTargetUserId($entry) === $userId ? $amountCents : 0;
		}

		if (!isset($shareBasisPointsByUserId[$userId])) {
			return 0;
		}

		return $this->distributeAmountCents($amountCents, $shareBasisPointsByUserId)[$userId] ?? 0;
	}

	protected function normalizeSplitUserId(?string $splitUserId): ?string {
		$splitUserId = trim((string)$splitUserId);
		return $splitUserId === '' ? null : $splitUserId;
	}

	protected function entrySplitTargetUserId(array $entry): string {
		return $this->normalizeSplitUserId($entry['split_user_id'] ?? null) ?? (string)($entry['user_id'] ?? '');
	}

	protected function validateProjectSplitUser(?int $projectId, string $splitMode, ?string &$splitUserId, string $fallbackUserId): ?DataResponse {
		$splitUserId = $this->normalizeSplitUserId($splitUserId);
		if ($projectId === null || $this->normalizeSplitMode($splitMode) !== 'single_user') {
			$splitUserId = null;
			return null;
		}

		if ($splitUserId === null) {
			$splitUserId = trim($fallbackUserId);
		}

		if ($splitUserId === '') {
			return $this->errorResponse('Split target is required', Http::STATUS_BAD_REQUEST);
		}

		if (!$this->projectUserMemberInActiveWorkspace($projectId, $splitUserId)) {
			return $this->errorResponse('Split target is not a member of this area', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	protected function validateRequiredUserId(string &$userId): ?DataResponse {
		return $this->validateRequiredString($userId, 'userId is required', 255);
	}

	protected function findVisibleScopedNameMatch(string $table, string $name, int $workspaceId, ?int $excludeId = null, ?int $projectId = null, ?string $type = null): ?array {
		$qb = $this->db->getQueryBuilder();
		$localScope = $projectId !== null
			? $qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			)
			: $qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)),
				$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
				$qb->expr()->isNull('project_id')
			);

		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);

		$qb->select('id', 'name', 'is_global', 'is_hidden', 'project_id')
		   ->from($table)
		   ->where(
			   $qb->expr()->orX(
				   $globalScope,
				   $localScope
			   )
		   );

		if ($type !== null) {
			$qb->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type)));
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$normalizedName = $this->normalizeVisibleName($name);
		foreach ($rows as $row) {
			if ($excludeId !== null && (int)$row['id'] === $excludeId) {
				continue;
			}

			if ($this->normalizeVisibleName((string)$row['name']) === $normalizedName) {
				return $row;
			}
		}

		return null;
	}

	protected function findGlobalNameMatches(string $table, string $name, string $type, ?int $excludeId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'type', 'is_global', 'is_hidden')
			->from($table)
			->where($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$normalizedName = $this->normalizeVisibleName($name);
		$matches = [];
		foreach ($rows as $row) {
			if ($excludeId !== null && (int)$row['id'] === $excludeId) {
				continue;
			}

			if ($this->normalizeVisibleName((string)$row['name']) === $normalizedName) {
				$matches[] = $row;
			}
		}

		return $matches;
	}

	protected function firstVisibleGlobalNameMatch(array $matches): ?array {
		foreach ($matches as $match) {
			if (!(bool)($match['is_hidden'] ?? false)) {
				return $match;
			}
		}

		return null;
	}

	protected function firstHiddenGlobalNameMatch(array $matches): ?array {
		foreach ($matches as $match) {
			if ((bool)($match['is_hidden'] ?? false)) {
				return $match;
			}
		}

		return null;
	}

	protected function workspaceNameExists(string $name, ?int $excludeId = null): bool {
		if (empty($this->userId)) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)))
		   ->setMaxResults(1);

		if ($excludeId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, \PDO::PARAM_INT)));
		}

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function projectBelongsToActiveWorkspace(int $projectId): bool {
		if ($projectId <= 0) {
			return false;
		}

		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_projects')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function projectVisibleForCurrentUser(int $projectId): ?array {
		if (empty($this->userId) || $projectId <= 0) {
			return null;
		}
		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
		   ->from('cobudget_projects', 'p')
		   ->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
		   ->where($qb->expr()->eq('p.id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('m.personal_workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	protected function projectOwnerForCurrentUser(int $projectId): ?array {
		$project = $this->projectVisibleForCurrentUser($projectId);
		if ($project === null || (string)($project['owner_id'] ?? '') !== (string)$this->userId) {
			return null;
		}

		return $project;
	}

	protected function projectWorkspaceIdForCurrentUser(?int $projectId): ?int {
		if ($projectId === null) {
			return $this->getWorkspaceId();
		}

		$project = $this->projectVisibleForCurrentUser($projectId);
		if ($project === null) {
			return null;
		}

		return (int)$project['workspace_id'];
	}

	protected function workspaceIdForEntryScope(?int $projectId): ?int {
		return $this->projectWorkspaceIdForCurrentUser($projectId);
	}

	protected function projectMemberCountInActiveWorkspace(?int $projectId): ?int {
		if ($projectId === null || $projectId <= 0 || $this->projectVisibleForCurrentUser($projectId) === null) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count('id'), 'member_count')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	protected function projectUsesSharedEntries(?int $projectId): bool {
		$memberCount = $this->projectMemberCountInActiveWorkspace($projectId);

		return $memberCount !== null && $memberCount > 1;
	}

	protected function projectHasFormerMember(?int $projectId): bool {
		if ($projectId === null || $projectId <= 0) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->like(
				'user_id',
				$qb->createNamedParameter(ParticipantService::FORMER_PREFIX . '%')
			))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$hasFormerMember = $result->fetchOne() !== false;
		$result->closeCursor();

		return $hasFormerMember;
	}

	protected function projectMemberInActiveWorkspace(int $projectId): bool {
		return $this->projectVisibleForCurrentUser($projectId) !== null;
	}

	protected function projectUserMemberInActiveWorkspace(int $projectId, string $memberUserId): bool {
		$memberUserId = trim($memberUserId);
		if ($memberUserId === '' || $projectId <= 0) {
			return false;
		}

		if ($this->projectVisibleForCurrentUser($projectId) === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('project_id')
		   ->from('cobudget_members', 'm')
		   ->where($qb->expr()->eq('m.project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('m.user_id', $qb->createNamedParameter($memberUserId)))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function projectOwnerInActiveWorkspace(int $projectId): bool {
		return $this->projectOwnerForCurrentUser($projectId) !== null;
	}

	protected function entryOwnedInActiveWorkspace(int $entryId): ?array {
		if (empty($this->userId) || $entryId <= 0) {
			return null;
		}

		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from('cobudget_entries')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	protected function entryVisibleInActiveWorkspace(int $entryId): ?array {
		if (empty($this->userId) || $entryId <= 0) {
			return null;
		}

		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*')
		   ->from('cobudget_entries', 'e')
		   ->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->eq('e.project_id', 'm.project_id'))
		   ->where($qb->expr()->eq('e.id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->orX(
			   $qb->expr()->andX(
				   $qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('personal')),
				   $qb->expr()->eq('e.user_id', $qb->createNamedParameter($this->userId)),
				   $qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			   ),
				   $qb->expr()->andX(
					   $qb->expr()->eq('e.entry_kind', $qb->createNamedParameter('shared')),
					   $qb->expr()->isNotNull('e.project_id'),
					   $qb->expr()->eq('m.user_id', $qb->createNamedParameter($this->userId)),
					   $qb->expr()->eq('m.personal_workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				   )
		   ))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	protected function categoryAvailableInActiveWorkspace(?int $categoryId, ?int $projectId = null): bool {
		if ($categoryId === null) {
			return true;
		}

		if (empty($this->userId) || $categoryId <= 0) {
			return false;
		}

		$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
		if ($workspaceId === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
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

		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);

		$qb->select('id')
		   ->from('cobudget_categories')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->orX(
			   $globalScope,
			   $localScope
		   ))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function personalCategoryInActiveWorkspace(int $categoryId): bool {
		if (empty($this->userId) || $categoryId <= 0) {
			return false;
		}

		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_categories')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->isNull('project_id'))
		   ->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function editableCategoryInActiveWorkspace(int $categoryId): ?array {
		if (empty($this->userId) || $categoryId <= 0) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'icon', 'type', 'is_global', 'user_id', 'workspace_id', 'project_id')
		   ->from('cobudget_categories')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if (!$row) {
			return null;
		}

		if ($row['project_id'] === null || $row['project_id'] === '') {
			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null || (int)$row['workspace_id'] !== (int)$workspaceId) {
				return null;
			}
			return $row['user_id'] === $this->userId ? $row : null;
		}

		$projectWorkspaceId = $this->projectWorkspaceIdForCurrentUser((int)$row['project_id']);
		return $projectWorkspaceId !== null && (int)$row['workspace_id'] === $projectWorkspaceId ? $row : null;
	}

	protected function paymentPartnerAvailableInActiveWorkspace(?int $paymentPartnerId, ?int $projectId = null): bool {
		if ($paymentPartnerId === null) {
			return true;
		}

		if (empty($this->userId) || $paymentPartnerId <= 0) {
			return false;
		}

		$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
		if ($workspaceId === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
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

		$globalScope = $qb->expr()->andX(
			$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
			$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
		);

		$qb->select('id')
		   ->from('cobudget_payment_partners')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($paymentPartnerId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->orX(
			   $globalScope,
			   $localScope
		   ))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function personalPaymentPartnerInActiveWorkspace(int $paymentPartnerId): bool {
		if (empty($this->userId) || $paymentPartnerId <= 0) {
			return false;
		}

		$workspaceId = $this->getWorkspaceId();
		if ($workspaceId === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_payment_partners')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($paymentPartnerId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->isNull('project_id'))
		   ->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function editablePaymentPartnerInActiveWorkspace(int $paymentPartnerId): ?array {
		if (empty($this->userId) || $paymentPartnerId <= 0) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'type', 'is_global', 'user_id', 'workspace_id', 'project_id')
		   ->from('cobudget_payment_partners')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($paymentPartnerId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
		   ->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if (!$row) {
			return null;
		}

		if ($row['project_id'] === null || $row['project_id'] === '') {
			$workspaceId = $this->getWorkspaceId();
			if ($workspaceId === null || (int)$row['workspace_id'] !== (int)$workspaceId) {
				return null;
			}
			return $row['user_id'] === $this->userId ? $row : null;
		}

		$projectWorkspaceId = $this->projectWorkspaceIdForCurrentUser((int)$row['project_id']);
		return $projectWorkspaceId !== null && (int)$row['workspace_id'] === $projectWorkspaceId ? $row : null;
	}

	protected function templateOwnedInActiveWorkspace(int $templateId): bool {
		if (empty($this->userId) || $templateId <= 0) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'workspace_id', 'project_id')
		   ->from('cobudget_templates')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($templateId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->setMaxResults(1);

		$row = $qb->executeQuery()->fetch();
		if (!$row) {
			return false;
		}

		if ($row['project_id'] === null || $row['project_id'] === '') {
			$workspaceId = $this->getWorkspaceId();
			return $workspaceId !== null && (int)$row['workspace_id'] === (int)$workspaceId;
		}

		$projectWorkspaceId = $this->projectWorkspaceIdForCurrentUser((int)$row['project_id']);
		return $projectWorkspaceId !== null && (int)$row['workspace_id'] === $projectWorkspaceId;
	}

	protected function workspaceBelongsToUser(int $workspaceId): bool {
		if (empty($this->userId) || $workspaceId <= 0) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
		   ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	protected function findDefaultWorkspaceId(): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
		   ->from('cobudget_workspaces')
		   ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
		   ->andWhere($qb->expr()->eq('is_default', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
		   ->setMaxResults(1);
		$result = $qb->executeQuery()->fetch();

		return $result ? (int)$result['id'] : null;
	}

	protected function createDefaultWorkspace(): ?int {
		if (empty($this->userId)) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_workspaces')
		   ->values([
			   'name' => $qb->createNamedParameter('Haupt-Workspace'),
			   'user_id' => $qb->createNamedParameter($this->userId),
			   'is_default' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
			   'created_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
		   ]);
		$qb->executeStatement();

		return (int)$this->db->lastInsertId('*PREFIX*cobudget_workspaces');
	}

	protected function assignUnscopedRowsToWorkspace(int $workspaceId): void {
		$tablesWithUsers = [
			['table' => 'cobudget_projects', 'column' => 'owner_id'],
			['table' => 'cobudget_entries', 'column' => 'user_id'],
			['table' => 'cobudget_categories', 'column' => 'user_id'],
			['table' => 'cobudget_payment_partners', 'column' => 'user_id'],
			['table' => 'cobudget_templates', 'column' => 'user_id'],
		];

		foreach ($tablesWithUsers as $twu) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($twu['table'])
			   ->set('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			   ->where($qb->expr()->eq($twu['column'], $qb->createNamedParameter($this->userId)))
			   ->andWhere($qb->expr()->isNull('workspace_id'));

			if ($twu['table'] === 'cobudget_categories' || $twu['table'] === 'cobudget_payment_partners') {
				$qb->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			}

			$qb->executeStatement();
		}
	}
}

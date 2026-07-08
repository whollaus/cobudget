<?php
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\BackupService;
use OCA\CoBudget\Service\ResetBlockedException;
use OCA\CoBudget\Service\UserResetService;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IL10N;

class UserController extends Controller {
	use WorkspaceAwareTrait;

	private const USER_SEARCH_MIN_LENGTH = 3;
	private const USER_SEARCH_LIMIT = 10;

	private const CURRENCY_BY_COUNTRY = [
		'AT' => 'EUR',
		'BE' => 'EUR',
		'CH' => 'CHF',
		'DE' => 'EUR',
		'DK' => 'DKK',
		'ES' => 'EUR',
		'FI' => 'EUR',
		'FR' => 'EUR',
		'GB' => 'GBP',
		'IE' => 'EUR',
		'IT' => 'EUR',
		'LI' => 'CHF',
		'LU' => 'EUR',
		'NL' => 'EUR',
		'NO' => 'NOK',
		'PL' => 'PLN',
		'PT' => 'EUR',
		'SE' => 'SEK',
		'US' => 'USD',
	];

	private const CURRENCY_BY_LANGUAGE = [
		'de' => 'EUR',
		'es' => 'EUR',
		'fr' => 'EUR',
		'it' => 'EUR',
		'nl' => 'EUR',
		'pt' => 'EUR',
	];

	private IDBConnection $db;
	private IUserManager $userManager;
	private ?string $userId;
	private IConfig $config;
	private BackupService $backupService;
	private UserResetService $userResetService;
	private IL10N $l10n;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserManager $userManager, IUserSession $userSession, IConfig $config, BackupService $backupService, UserResetService $userResetService, IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->backupService = $backupService;
		$this->userResetService = $userResetService;
		$this->l10n = $l10n;
		$this->initWorkspace();
	}

	/**
	 * @NoAdminRequired
	 */
	public function search(string $term = ''): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$term = trim($term);
			if (!$this->sharedProjectsEnabled() || !$this->userSearchAllowed()) {
				return new DataResponse([]);
			}

			if (mb_strlen($term) < self::USER_SEARCH_MIN_LENGTH) {
				return new DataResponse([]);
			}

			$users = [];
			$searchResult = $this->userManager->search($term, self::USER_SEARCH_LIMIT);

			foreach ($searchResult as $user) {
				// Don't include the current user in results
				if ($user->getUID() === $this->userId) {
					continue;
				}
				$users[] = [
					'id' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
				];
			}

			return new DataResponse($users);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function sharedProjectsEnabled(): bool {
		return $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_projects', 'yes') === 'yes'
			&& $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes';
	}

	private function userSearchAllowed(): bool {
		return $this->systemFlagEnabled('shareapi_allow_share_dialog_user_enumeration', true);
	}

	private function systemFlagEnabled(string $key, bool $default): bool {
		$value = $this->config->getSystemValue($key, $default);
		if (is_bool($value)) {
			return $value;
		}

		return !in_array(strtolower((string)$value), ['0', 'false', 'no', 'off'], true);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getSettings(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			return new DataResponse([
				'currency' => $this->effectiveCurrency(),
				'enable_subscriptions' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_subscriptions', 'yes') === 'yes',
				'enable_fixed_costs' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_fixed_costs', 'yes') === 'yes',
				'enable_child_related' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_child_related', 'yes') === 'yes',
				'enable_important_payments' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_important_payments', 'yes') === 'yes',
				'enable_review_payments' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_review_payments', 'yes') === 'yes',
				'enable_tax_relevant' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_tax_relevant', 'yes') === 'yes',
				'enable_future_payments' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_future_payments', 'yes') === 'yes',
				'enable_templates' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_templates', 'yes') === 'yes',
				'enable_budget_goals' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_budget_goals', 'yes') === 'yes',
				'enable_incomes' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_incomes', 'yes') === 'yes',
				'enable_projects' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_projects', 'yes') === 'yes',
				'enable_shared_projects' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes',
				'notify_project_entries' => $this->config->getUserValue($this->userId, 'cobudget', 'notify_project_entries', 'yes') === 'yes',
				'notify_project_settlements' => $this->config->getUserValue($this->userId, 'cobudget', 'notify_project_settlements', 'yes') === 'yes',
				'enable_workspaces' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_workspaces', 'no') === 'yes',
				'show_workspace_switcher' => $this->config->getUserValue($this->userId, 'cobudget', 'show_workspace_switcher', 'yes') === 'yes',
				'enable_receipts' => $this->config->getUserValue($this->userId, 'cobudget', 'enable_receipts', 'yes') === 'yes',
				'default_start_page' => $this->config->getUserValue($this->userId, 'cobudget', 'default_start_page', 'personal'),
				'entries_per_page' => (int)$this->config->getUserValue($this->userId, 'cobudget', 'entries_per_page', '25'),
				'theme_mode' => $this->config->getUserValue($this->userId, 'cobudget', 'theme_mode', 'auto'),
				'receipt_storage_folder' => $this->config->getUserValue($this->userId, 'cobudget', 'receipt_storage_folder', 'CoBudget/Belege'),
				'receipt_folder_grouping' => $this->config->getUserValue($this->userId, 'cobudget', 'receipt_folder_grouping', 'year'),
				'delete_receipts_with_entry' => $this->config->getUserValue($this->userId, 'cobudget', 'delete_receipts_with_entry', 'no') === 'yes',
				'backup_storage_folder' => $this->backupService->getBackupFolder($this->userId),
				'backup_retention_count' => $this->backupService->getRetentionCount($this->userId),
				'backup_schedule' => $this->backupService->getBackupSchedule($this->userId)
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function saveSettings(string $currency = '', ?bool $enable_subscriptions = null, ?bool $enable_fixed_costs = null, ?bool $enable_child_related = null, ?bool $enable_important_payments = null, ?bool $enable_review_payments = null, ?bool $enable_tax_relevant = null, ?bool $enable_future_payments = null, ?bool $enable_templates = null, ?bool $enable_budget_goals = null, ?bool $enable_incomes = null, ?bool $enable_projects = null, ?bool $enable_shared_projects = null, ?bool $notify_project_entries = null, ?bool $notify_project_settlements = null, ?bool $enable_workspaces = null, ?bool $show_workspace_switcher = null, ?bool $enable_receipts = null, ?string $default_start_page = null, ?int $entries_per_page = null, ?string $theme_mode = null, ?string $receipt_storage_folder = null, ?string $receipt_folder_grouping = null, ?bool $delete_receipts_with_entry = null, ?string $backup_storage_folder = null, ?int $backup_retention_count = null, ?string $backup_schedule = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validateCurrencySetting($currency)) {
				return $validationError;
			}

			if ($validationError = $this->validateDefaultStartPage($default_start_page)) {
				return $validationError;
			}

			if ($validationError = $this->validateEntriesPerPage($entries_per_page)) {
				return $validationError;
			}

			if ($validationError = $this->validateThemeMode($theme_mode)) {
				return $validationError;
			}

			if ($validationError = $this->validateReceiptStorageFolder($receipt_storage_folder)) {
				return $validationError;
			}

			if ($validationError = $this->validateReceiptFolderGrouping($receipt_folder_grouping)) {
				return $validationError;
			}

			if ($validationError = $this->validateBackupSettings($backup_storage_folder, $backup_retention_count, $backup_schedule)) {
				return $validationError;
			}

			if ($currency === '') {
				$currency = $this->detectCurrencyFromLocale();
			}
			$this->config->setUserValue($this->userId, 'cobudget', 'currency', $currency);
			if ($enable_subscriptions !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_subscriptions', $enable_subscriptions ? 'yes' : 'no');
			}
			if ($enable_fixed_costs !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_fixed_costs', $enable_fixed_costs ? 'yes' : 'no');
			}
			if ($enable_child_related !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_child_related', $enable_child_related ? 'yes' : 'no');
			}
			if ($enable_important_payments !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_important_payments', $enable_important_payments ? 'yes' : 'no');
			}
			if ($enable_review_payments !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_review_payments', $enable_review_payments ? 'yes' : 'no');
			}
			if ($enable_tax_relevant !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_tax_relevant', $enable_tax_relevant ? 'yes' : 'no');
			}
			if ($enable_future_payments !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_future_payments', $enable_future_payments ? 'yes' : 'no');
			}
			if ($enable_templates !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_templates', $enable_templates ? 'yes' : 'no');
			}
			if ($enable_budget_goals !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_budget_goals', $enable_budget_goals ? 'yes' : 'no');
			}
			if ($enable_incomes !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_incomes', $enable_incomes ? 'yes' : 'no');
			}
			if ($enable_projects !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_projects', $enable_projects ? 'yes' : 'no');
			}
			if ($enable_shared_projects !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_shared_projects', $enable_shared_projects ? 'yes' : 'no');
			}
			if ($notify_project_entries !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'notify_project_entries', $notify_project_entries ? 'yes' : 'no');
			}
			if ($notify_project_settlements !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'notify_project_settlements', $notify_project_settlements ? 'yes' : 'no');
			}
			if ($enable_workspaces !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_workspaces', $enable_workspaces ? 'yes' : 'no');
			}
			if ($show_workspace_switcher !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'show_workspace_switcher', $show_workspace_switcher ? 'yes' : 'no');
			}
			if ($enable_receipts !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'enable_receipts', $enable_receipts ? 'yes' : 'no');
			}
			if ($default_start_page !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'default_start_page', $default_start_page);
			}
			if ($entries_per_page !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'entries_per_page', (string)$entries_per_page);
			}
			if ($theme_mode !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'theme_mode', $theme_mode);
			}
			if ($receipt_storage_folder !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'receipt_storage_folder', $receipt_storage_folder);
			}
			if ($receipt_folder_grouping !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'receipt_folder_grouping', $receipt_folder_grouping);
			}
			if ($delete_receipts_with_entry !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'delete_receipts_with_entry', $delete_receipts_with_entry ? 'yes' : 'no');
			}
			if ($backup_storage_folder !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'backup_storage_folder', $backup_storage_folder);
			}
			if ($backup_retention_count !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'backup_retention_count', (string)$backup_retention_count);
			}
			if ($backup_schedule !== null) {
				$this->config->setUserValue($this->userId, 'cobudget', 'backup_schedule', $backup_schedule);
			}
			return new DataResponse(['status' => 'success']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function resetPreview(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			return new DataResponse([
				'reset' => $this->userResetService->preview((string)$this->userId),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function resetAll(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$confirmation = trim((string)$this->request->getParam('confirmation', ''));
			$reset = $this->userResetService->reset((string)$this->userId, $confirmation);

			return new DataResponse([
				'status' => 'success',
				'reset' => $reset,
			]);
		} catch (ResetBlockedException $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
				'reset' => $e->preview(),
			], Http::STATUS_CONFLICT);
		} catch (\InvalidArgumentException $e) {
			return $this->errorResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	private function validateReceiptStorageFolder(?string &$folder): ?DataResponse {
		if ($folder === null) {
			return null;
		}

		$folder = trim($folder);
		if ($folder === '') {
			return $this->errorResponse('Receipt folder must not be empty', Http::STATUS_BAD_REQUEST);
		}
		if (str_contains($folder, '\\')) {
			return $this->errorResponse('Receipt folder must not contain backslashes', Http::STATUS_BAD_REQUEST);
		}

		$folder = trim($folder, '/');
		if ($folder === '' || preg_match('~(^|/)\.\.(/|$)~', $folder) === 1 || preg_match('/[\x00-\x1F]/', $folder) === 1) {
			return $this->errorResponse('Invalid receipt folder', Http::STATUS_BAD_REQUEST);
		}
		if (mb_strlen($folder) > 180) {
			return $this->errorResponse('Receipt folder is too long', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	private function validateReceiptFolderGrouping(?string $grouping): ?DataResponse {
		if ($grouping === null) {
			return null;
		}
		if (!in_array($grouping, ['none', 'year', 'year_month'], true)) {
			return $this->errorResponse('Invalid receipt folder structure', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	private function validateThemeMode(?string &$themeMode): ?DataResponse {
		if ($themeMode === null) {
			return null;
		}

		$themeMode = trim($themeMode);
		if (!in_array($themeMode, ['auto', 'light', 'dark'], true)) {
			return $this->errorResponse('Invalid appearance setting', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	private function validateBackupSettings(?string &$folder, ?int $retentionCount, ?string &$schedule): ?DataResponse {
		if ($folder !== null) {
			try {
				$folder = $this->backupService->normalizePersonalExportFolder($folder);
			} catch (\InvalidArgumentException $e) {
				return $this->errorResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
			}
		}

		if ($retentionCount !== null) {
			try {
				$this->backupService->normalizeRetentionCount($retentionCount);
			} catch (\InvalidArgumentException $e) {
				return $this->errorResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
			}
		}

		if ($schedule !== null) {
			try {
				$schedule = $this->backupService->normalizeSchedule($schedule);
			} catch (\InvalidArgumentException $e) {
				return $this->errorResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
			}
		}

		return null;
	}

	private function effectiveCurrency(): string {
		$storedCurrency = trim($this->config->getUserValue((string)$this->userId, 'cobudget', 'currency', ''));
		if ($storedCurrency !== '') {
			return $storedCurrency;
		}

		return $this->detectCurrencyFromLocale();
	}

	private function detectCurrencyFromLocale(): string {
		$locale = trim($this->config->getUserValue((string)$this->userId, 'core', 'locale', ''));
		if ($locale === '') {
			$locale = trim($this->config->getUserValue((string)$this->userId, 'core', 'lang', ''));
		}

		$localeParts = array_values(array_filter(explode('_', str_replace('-', '_', $locale)), static fn(string $part): bool => $part !== ''));
		$language = strtolower((string)($localeParts[0] ?? ''));
		$country = strtoupper((string)($localeParts[1] ?? ''));

		if ($country !== '' && isset(self::CURRENCY_BY_COUNTRY[$country])) {
			return self::CURRENCY_BY_COUNTRY[$country];
		}
		if ($language !== '' && isset(self::CURRENCY_BY_LANGUAGE[$language])) {
			return self::CURRENCY_BY_LANGUAGE[$language];
		}

		return 'EUR';
	}

}

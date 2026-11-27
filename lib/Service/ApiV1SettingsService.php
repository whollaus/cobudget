<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Exception\ApiV1HttpException;
use OCP\AppFramework\Http;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Stateless service implementing strict allowlisted settings reads and partial
 * updates for external API v1 tokens.
 */
final class ApiV1SettingsService {
	private const READABLE_BOOLEAN_DEFAULTS = [
		'enable_subscriptions' => true,
		'enable_fixed_costs' => true,
		'enable_child_related' => true,
		'enable_important_payments' => true,
		'enable_review_payments' => true,
		'enable_tax_relevant' => true,
		'enable_insurance' => true,
		'enable_future_payments' => true,
		'enable_reminders' => true,
		'enable_budget_goals' => true,
		'enable_advanced_master_data' => false,
		'enable_incomes' => true,
		'enable_projects' => true,
		'enable_shared_projects' => true,
		'enable_workspaces' => false,
		'show_workspace_switcher' => true,
		'enable_receipts' => true,
		'show_nextcloud_header_desktop' => true,
		'show_nextcloud_header_mobile' => true,
		'notify_reminders' => true,
		'notify_planned_payments' => false,
		'notify_project_entries' => true,
		'notify_project_settlements' => true,
	];

	private const WRITABLE_BOOLEAN_KEYS = [
		'enable_subscriptions',
		'enable_fixed_costs',
		'enable_child_related',
		'enable_important_payments',
		'enable_review_payments',
		'enable_tax_relevant',
		'enable_insurance',
		'enable_future_payments',
		'enable_reminders',
		'enable_budget_goals',
		'enable_advanced_master_data',
		'enable_incomes',
		'enable_receipts',
		'show_workspace_switcher',
		'show_nextcloud_header_desktop',
		'show_nextcloud_header_mobile',
		'notify_reminders',
		'notify_planned_payments',
		'notify_project_entries',
		'notify_project_settlements',
	];

	private const WRITABLE_NON_BOOLEAN_KEYS = [
		'currency',
		'default_start_page',
		'entries_per_page',
		'theme_mode',
	];

	private const FORBIDDEN_SETTINGS_KEYS = [
		'enable_api_access',
		'api_access_admin_enabled',
		'enable_payment_imports',
		'enable_workspaces',
		'enable_projects',
		'enable_shared_projects',
		'receipt_storage_folder',
		'receipt_folder_grouping',
		'delete_receipts_with_entry',
		'backup_storage_folder',
		'backup_retention_count',
		'backup_schedule',
		'notify_reminders_email',
		'notify_planned_payments_email',
		'notify_project_entries_email',
		'notify_project_settlements_email',
		'notify_automatic_imports',
		'notify_automatic_imports_email',
	];

	private const VALID_PAGE_SIZES = [10, 25, 50, 100, 250];
	private const VALID_THEME_MODES = ['auto', 'light', 'dark'];

	public function __construct(
		private IConfig $config,
		private ?IUserConfig $userConfig = null,
		private ?DataChangeService $dataChangeService = null,
		private ?LoggerInterface $logger = null,
	) {
	}

	/** @return array<string, mixed> */
	public function getSettings(array $auth): array {
		if (!in_array('settings:read', $auth['scopes'], true)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key does not have the "settings:read" scope.');
		}

		$userId = (string)$auth['user_id'];
		$startPage = $this->config->getUserValue($userId, 'cobudget', 'default_start_page', 'personal');

		$settings = [
			'currency' => ApiV1EntriesService::resolveUserCurrency($this->config, $userId),
			'default_start_page' => $startPage === 'currentYear' ? 'personal' : $startPage,
			'entries_per_page' => (int)$this->config->getUserValue($userId, 'cobudget', 'entries_per_page', '25'),
			'theme_mode' => $this->config->getUserValue($userId, 'cobudget', 'theme_mode', 'auto'),
		];

		foreach (self::READABLE_BOOLEAN_DEFAULTS as $key => $default) {
			$settings[$key] = $this->config->getUserValue($userId, 'cobudget', $key, $default ? 'yes' : 'no') === 'yes';
		}

		return $settings;
	}

	/** @return array{status: string, updated: list<string>} */
	public function patchSettings(array $auth, array $payload): array {
		if (!in_array('settings:write', $auth['scopes'], true)) {
			throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key does not have the "settings:write" scope.');
		}

		if ($payload === []) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Provide at least one setting to update.');
		}

		$forbiddenLookup = array_flip(self::FORBIDDEN_SETTINGS_KEYS);
		$writableBoolLookup = array_flip(self::WRITABLE_BOOLEAN_KEYS);
		$writableOtherLookup = array_flip(self::WRITABLE_NON_BOOLEAN_KEYS);

		$validated = [];
		foreach ($payload as $key => $value) {
			if (!is_string($key)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid setting key.');
			}
			if (isset($forbiddenLookup[$key])) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'Setting "' . $key . '" cannot be modified via the API.');
			}
			if (!isset($writableBoolLookup[$key]) && !isset($writableOtherLookup[$key])) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Unknown setting "' . $key . '".');
			}

			if (isset($writableBoolLookup[$key])) {
				if (!is_bool($value)) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Setting "' . $key . '" must be a boolean.');
				}
				$validated[$key] = $value ? 'yes' : 'no';
				continue;
			}

			if ($key === 'currency') {
				if (!is_string($value) || preg_match('/^[A-Z]{3}$/D', trim($value)) !== 1) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Setting "currency" must be a 3-letter uppercase ISO code.');
				}
				$validated[$key] = trim($value);
			} elseif ($key === 'entries_per_page') {
				if (!is_int($value) || !in_array($value, self::VALID_PAGE_SIZES, true)) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Setting "entries_per_page" must be one of 10, 25, 50, 100, 250.');
				}
				$validated[$key] = (string)$value;
			} elseif ($key === 'theme_mode') {
				if (!is_string($value) || !in_array($value, self::VALID_THEME_MODES, true)) {
					throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Setting "theme_mode" must be auto, light, or dark.');
				}
				$validated[$key] = $value;
			} elseif ($key === 'default_start_page') {
				$validated[$key] = $this->validateDefaultStartPage($auth, $value);
			}
		}

		$userId = (string)$auth['user_id'];
		$this->userConfig?->clearCache($userId);
		foreach ($validated as $key => $storedValue) {
			$this->config->setUserValue($userId, 'cobudget', $key, $storedValue);
		}

		$updatedKeys = array_keys($validated);
		if (isset($validated['enable_reminders'])) {
			$this->dataChangeService?->publishForUsers([$userId], ['entries', 'reminders']);
		}

		try {
			$this->logger?->info('CoBudget API v1 settings updated', [
				'app' => 'cobudget',
				'event' => 'api_v1.settings.updated',
				'user_id' => $userId,
				'workspace_id' => (int)($auth['workspace_id'] ?? 0),
				'token_id' => (int)($auth['token_id'] ?? 0),
				'token_prefix' => (string)($auth['token_prefix'] ?? ''),
				'updated' => $updatedKeys,
			]);
		} catch (\Throwable) {
			// Audit logging must not break settings persistence.
		}

		return [
			'status' => 'success',
			'updated' => $updatedKeys,
		];
	}

	private function validateDefaultStartPage(array $auth, mixed $value): string {
		if (!is_string($value)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Setting "default_start_page" must be a string.');
		}
		$trimmed = trim($value);
		if ($trimmed === 'personal') {
			return 'personal';
		}
		if ($trimmed === 'projects') {
			if (empty($auth['projects_enabled'])) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Areas are disabled in CoBudget settings.');
			}
			return 'projects';
		}
		if (preg_match('/^project:([1-9]\d*)$/D', $trimmed, $matches) === 1) {
			$areaId = (int)$matches[1];
			$hasAreaPermission = !empty($auth['target_permissions'][(string)$areaId]);
			if (empty($auth['projects_enabled']) || !isset($auth['active_areas_by_id'][$areaId]) || !$hasAreaPermission) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Referenced start-page area is not active in the token workspace.');
			}
			return 'project:' . $areaId;
		}
		throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid "default_start_page" value.');
	}
}

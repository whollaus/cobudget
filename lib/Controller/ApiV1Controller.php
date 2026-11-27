<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Exception\ApiV1HttpException;
use OCA\CoBudget\Exception\AppBusyException;
use OCA\CoBudget\Service\ApiV1AuthService;
use OCA\CoBudget\Service\ApiV1EntriesService;
use OCA\CoBudget\Service\ApiV1ReportsService;
use OCA\CoBudget\Service\ApiV1SettingsService;
use OCA\CoBudget\Service\AppDataLock;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoSameSiteCookieRequired;
use OCP\AppFramework\Http\Attribute\NoTwoFactorRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * External REST API v1 controller for CoBudget.
 *
 * Security invariants enforced here:
 * - No WorkspaceAwareTrait, no IUserSession, no cookie-derived locale.
 * - Zero PHP method parameters on every action so Dispatcher::executeController()
 *   never invokes Request::getParam() or decodes php://input pre-auth.
 * - All endpoints carry PublicPage + NoCSRFRequired + NoSameSiteCookieRequired
 *   + NoTwoFactorRequired and zero CORS attributes.
 * - All exceptions are caught and emitted as sanitized JSONResponse with
 *   explicit security headers.
 */
final class ApiV1Controller extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ApiV1AuthService $auth,
		private ApiV1EntriesService $entriesService,
		private ApiV1ReportsService $reportsService,
		private ApiV1SettingsService $settingsService,
		private AppDataLock $appDataLock,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function me(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			return $this->entriesService->me($ctx);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function areas(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			return ['areas' => $this->entriesService->listAreas($ctx)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function categories(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$projectId = $this->parseOptionalAreaQueryParam();
			return ['categories' => $this->entriesService->listCategories($ctx, $projectId)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function paymentPartners(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$projectId = $this->parseOptionalAreaQueryParam();
			return ['payment_partners' => $this->entriesService->listPaymentPartners($ctx, $projectId)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function entriesIndex(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$filters = [
				'project_id' => $this->auth->queryString('project_id'),
				'scope' => $this->auth->queryString('scope') ?? 'canonical',
				'type' => $this->auth->queryString('type') ?? 'all',
				'date_from' => $this->auth->queryInt('date_from', 1, ApiV1EntriesService::MAX_ENTRY_TIMESTAMP),
				'date_to' => $this->auth->queryInt('date_to', 1, ApiV1EntriesService::MAX_ENTRY_TIMESTAMP),
				'category_id' => $this->auth->queryInt('category_id', 1, ApiV1AuthService::MAX_DB_ID),
				'payment_partner_id' => $this->auth->queryInt('payment_partner_id', 1, ApiV1AuthService::MAX_DB_ID),
				'search' => $this->auth->queryString('search') ?? '',
				'include_future' => $this->auth->queryBool('include_future', false) ?? false,
				'limit' => $this->auth->queryInt('limit', 1, ApiV1EntriesService::MAX_PAGE_SIZE) ?? 50,
				'offset' => $this->auth->queryInt('offset', 0, ApiV1EntriesService::MAX_PAGE_OFFSET) ?? 0,
				'sort_by' => $this->auth->queryString('sort_by') ?? 'date',
				'sort_dir' => $this->auth->queryString('sort_dir') ?? 'desc',
			];
			return $this->entriesService->listEntries($ctx, $filters);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function entriesShow(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$id = $this->auth->requirePositiveUrlId('id');
			return ['entry' => $this->entriesService->getEntry($ctx, $id)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function entriesCreate(): JSONResponse {
		return $this->runEndpoint('write', function (array $ctx): array {
			$payload = $this->auth->readJsonObjectBody();
			return ['entry' => $this->entriesService->createEntry($ctx, $payload)];
		}, Http::STATUS_CREATED);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function entriesUpdate(): JSONResponse {
		return $this->runEndpoint('write', function (array $ctx): array {
			$id = $this->auth->requirePositiveUrlId('id');
			$payload = $this->auth->readJsonObjectBody();
			return ['entry' => $this->entriesService->updateEntry($ctx, $id, $payload)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function entriesDestroy(): JSONResponse {
		return $this->runEndpoint('write', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$id = $this->auth->requirePositiveUrlId('id');
			return $this->entriesService->deleteEntry($ctx, $id);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function reportsSummary(): JSONResponse {
		return $this->runEndpoint('reports', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$params = [
				'period' => trim($this->auth->queryString('period') ?? 'current-month'),
				'date_from' => $this->auth->queryInt('date_from', 1, ApiV1EntriesService::MAX_ENTRY_TIMESTAMP),
				'date_to' => $this->auth->queryInt('date_to', 1, ApiV1EntriesService::MAX_ENTRY_TIMESTAMP),
				'include_future' => $this->auth->queryBool('include_future', false) ?? false,
			];
			return $this->reportsService->summary($ctx, $params);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function reportsAnnual(): JSONResponse {
		return $this->runEndpoint('reports', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			$params = [
				'year' => $this->auth->queryInt('year', 1970, 2099),
				'include_future' => $this->auth->queryBool('include_future', false) ?? false,
			];
			return $this->reportsService->annual($ctx, $params);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function budgetsIndex(): JSONResponse {
		return $this->runEndpoint('reports', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			return $this->reportsService->budgets($ctx);
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function settingsGet(): JSONResponse {
		return $this->runEndpoint('read', function (array $ctx): array {
			$this->auth->requireEmptyBody();
			return ['settings' => $this->settingsService->getSettings($ctx)];
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	#[NoTwoFactorRequired]
	public function settingsPatch(): JSONResponse {
		return $this->runEndpoint('write', function (array $ctx): array {
			$payload = $this->auth->readJsonObjectBody();
			return $this->settingsService->patchSettings($ctx, $payload);
		});
	}

	/**
	 * @param callable(array<string, mixed>): array<string, mixed> $handler
	 */
	private function runEndpoint(string $bucket, callable $handler, int $successStatus = Http::STATUS_OK): JSONResponse {
		try {
			$credential = $this->auth->admitCredential($bucket);
			$this->appDataLock->enter();
			try {
				$ctx = $this->auth->authenticate($credential);
				$data = $handler($ctx);
			} finally {
				$this->appDataLock->leave();
			}
			return $this->auth->jsonResponse($data, $successStatus);
		} catch (AppBusyException) {
			return $this->auth->jsonResponse(
				[
					'error' => 'CoBudget is temporarily busy with a backup, restore or another operation. Please try again shortly.',
					'code' => 'cobudget_busy',
				],
				Http::STATUS_SERVICE_UNAVAILABLE,
				['Retry-After' => '5']
			);
		} catch (ApiV1HttpException $e) {
			return $this->auth->errorResponse($e->getMessage(), $e->getStatusCode(), $e->getHeaders());
		} catch (\Throwable $e) {
			try {
				$this->logger->error('Unhandled CoBudget API v1 error: ' . $e->getMessage(), [
					'app' => 'cobudget',
					'exception' => $e,
				]);
			} catch (\Throwable) {
				// Never let logging failures suppress JSON error formatting.
			}
			return $this->auth->errorResponse('An internal error occurred.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function parseOptionalAreaQueryParam(): ?int {
		$raw = $this->auth->queryString('project_id');
		if ($raw === null || trim($raw) === '') {
			return null;
		}
		$trimmed = trim($raw);
		if (!ctype_digit($trimmed)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid project_id query parameter.');
		}
		$validated = filter_var($trimmed, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 0, 'max_range' => ApiV1AuthService::MAX_DB_ID],
		]);
		if ($validated === false) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid project_id query parameter.');
		}
		return $validated === 0 ? null : $validated;
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Exception\ApiV1HttpException;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\Bruteforce\MaxDelayReached;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use Psr\Log\LoggerInterface;

/**
 * Stateless authentication, brute-force protection, rate limiting, and bounded request
 * parsing for CoBudget's external REST API v1.
 */
class ApiV1AuthService {
	public const BRUTEFORCE_ACTION = 'cobudget_api_v1';
	public const MAX_JSON_BODY_BYTES = 65536;
	public const MAX_QUERY_STRING_BYTES = 4096;
	public const MAX_QUERY_ARRAY_DEPTH = 8;
	public const MAX_QUERY_BRACKET_COUNT = 64;
	public const MAX_DB_ID = 2_147_483_647;
	public const IP_RATE_LIMIT = 180;
	public const BUCKET_LIMITS = [
		'read' => 120,
		'write' => 60,
		'reports' => 20,
	];
	private const FORBIDDEN_QUERY_TOKEN_KEYS = [
		'token',
		'api_key',
		'apikey',
		'access_token',
		'key',
	];
	private const FORBIDDEN_AUTH_COOKIES = [
		'nc_session_id' => true,
		'nc_token' => true,
		'nc_username' => true,
	];

	public function __construct(
		private IRequest $request,
		private IUserManager $userManager,
		private IAppManager $appManager,
		private IFactory $l10nFactory,
		private IThrottler $throttler,
		private ILimiter $limiter,
		private ApiTokenService $apiTokenService,
		private LoggerInterface $logger,
		private ?ISession $session = null,
	) {
	}

	/**
	 * Applies strict cache, content-type, referrer, and UID-suppression headers
	 * on every external API v1 response (including middleware-generated errors).
	 */
	public static function applySecurityHeadersToResponse(Response $response): void {
		$response->addHeader('Cache-Control', 'no-store, private');
		$response->addHeader('Pragma', 'no-cache');
		$response->addHeader('Vary', 'Authorization, X-CoBudget-Token');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Referrer-Policy', 'no-referrer');
		// Clear Response::getHeaders()'s automatic session UID leak without logging out browser sessions.
		$response->addHeader('X-User-Id', '');
	}

	public function jsonResponse(array $data, int $status = Http::STATUS_OK, array $extraHeaders = []): JSONResponse {
		$response = new JSONResponse($data, $status);
		foreach ($extraHeaders as $header => $value) {
			$response->addHeader((string)$header, (string)$value);
		}
		self::applySecurityHeadersToResponse($response);
		return $response;
	}

	public function errorResponse(string $message, int $status, array $extraHeaders = []): JSONResponse {
		return $this->jsonResponse(['error' => $message], $status, $extraHeaders);
	}

	/** Reject invalid or over-quota credentials before acquiring the app lock or loading memberships. */
	public function admitCredential(string $bucket, ?int $now = null): string {
		$now ??= time();
		$remoteIp = trim($this->request->getRemoteAddress());

		try {
			$this->limiter->registerAnonRequest('cobudget_api_v1_ip', self::IP_RATE_LIMIT, 60, $remoteIp);
		} catch (IRateLimitExceededException $e) {
			throw new ApiV1HttpException(
				Http::STATUS_TOO_MANY_REQUESTS,
				'Rate limit exceeded. Please try again later.',
				['Retry-After' => '60'],
				$e
			);
		}

		$this->assertNoQueryTokens();

		if ($this->hasAuthenticatedWebSession()) {
			throw $this->unauthorizedException();
		}

		if ($this->hasForbiddenAuthHeaders()) {
			$this->enforceBruteForceDelay($remoteIp);
			$this->registerBruteForceAttempt($remoteIp);
			throw $this->unauthorizedException();
		}

		$authHeader = trim($this->request->getHeader('Authorization'));
		$customHeader = trim($this->request->getHeader('X-CoBudget-Token'));
		if ($authHeader === '' && $customHeader === '') {
			// Missing credentials must not trip IP brute-force lockouts from cross-origin tags.
			throw $this->unauthorizedException();
		}

		$plainToken = $this->extractValidatedHeaderToken($authHeader, $customHeader);
		if ($plainToken === null) {
			$this->enforceBruteForceDelay($remoteIp);
			$this->registerBruteForceAttempt($remoteIp);
			throw $this->unauthorizedException();
		}

		if (!$this->apiTokenService->isGloballyEnabled()) {
			$this->enforceBruteForceDelay($remoteIp);
			$this->registerBruteForceAttempt($remoteIp);
			throw $this->unauthorizedException();
		}

		$tokenHash = hash('sha256', $plainToken);
		$row = $this->apiTokenService->findActiveTokenRowByHash($tokenHash, $now, true);
		if ($row === null || !hash_equals((string)($row['token_hash'] ?? ''), $tokenHash)) {
			$this->enforceBruteForceDelay($remoteIp);
			if (!$this->apiTokenService->tokenHashExists($tokenHash)) {
				$this->registerBruteForceAttempt($remoteIp);
			}
			throw $this->unauthorizedException();
		}

		$bucketKey = isset(self::BUCKET_LIMITS[$bucket]) ? $bucket : 'read';
		$bucketLimit = self::BUCKET_LIMITS[$bucketKey];
		try {
			$this->limiter->registerAnonRequest(
				'cobudget_api_v1_token:' . $row['id'] . ':' . $bucketKey,
				$bucketLimit,
				60,
				''
			);
		} catch (IRateLimitExceededException $e) {
			throw new ApiV1HttpException(
				Http::STATUS_TOO_MANY_REQUESTS,
				'Rate limit exceeded for this API key. Please try again later.',
				['Retry-After' => '60'],
				$e
			);
		}

		return $tokenHash;
	}

	/** Resolve all rights against the current dataset while the caller holds AppDataLock. */
	public function authenticate(string $tokenHash, ?int $now = null): array {
		$now ??= time();
		$row = $this->activeTokenRow($tokenHash, $now, false);
		$userId = trim((string)($row['user_id'] ?? ''));
		$user = $userId !== '' ? $this->userManager->get($userId) : null;
		if ($user === null || (method_exists($user, 'isEnabled') && !$user->isEnabled())) {
			throw $this->unauthorizedException();
		}

		if (!$this->appManager->isEnabledForUser('cobudget', $user)) {
			throw $this->unauthorizedException();
		}

		$context = $this->apiTokenService->resolveAuthContextFromRow($row, $now);
		if ($context === null) {
			throw $this->unauthorizedException();
		}

		$wsHeader = trim($this->request->getHeader('X-Workspace-Id'));
		if ($wsHeader !== '') {
			if (!ctype_digit($wsHeader)) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid X-Workspace-Id header.');
			}
			$requestedWsId = filter_var($wsHeader, FILTER_VALIDATE_INT, [
				'options' => ['min_range' => 1, 'max_range' => self::MAX_DB_ID],
			]);
			if ($requestedWsId === false) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid X-Workspace-Id header.');
			}
			if ($requestedWsId !== $context['workspace_id']) {
				throw new ApiV1HttpException(Http::STATUS_FORBIDDEN, 'API key is not authorized for the requested workspace.');
			}
		}

		$this->apiTokenService->touchLastUsed($context['token_id'], $now);

		$userLang = $this->l10nFactory->getUserLanguage($user);
		$l10n = $this->l10nFactory->get('cobudget', $userLang);
		$context['l10n'] = $l10n;
		$context['user'] = $user;
		$context['timezone'] = $this->resolveTimezone();
		return $context;
	}

	private function activeTokenRow(string $tokenHash, int $now, bool $credentialOnly): array {
		$row = $this->apiTokenService->findActiveTokenRowByHash($tokenHash, $now, $credentialOnly);
		if ($row === null || !hash_equals((string)($row['token_hash'] ?? ''), $tokenHash)) {
			if (!$this->apiTokenService->tokenHashExists($tokenHash)) {
				$this->registerBruteForceAttempt(trim($this->request->getRemoteAddress()));
			}
			throw $this->unauthorizedException();
		}
		return $row;
	}

	private function hasAuthenticatedWebSession(): bool {
		if ($this->session !== null && trim((string)($this->session->get('user_id') ?? '')) !== '') {
			return true;
		}

		$cookieHeader = trim($this->request->getHeader('Cookie'));
		if ($cookieHeader !== '') {
			foreach (explode(';', $cookieHeader) as $segment) {
				$cookieName = trim(explode('=', $segment, 2)[0]);
				if ($cookieName !== '' && isset(self::FORBIDDEN_AUTH_COOKIES[$cookieName])) {
					return true;
				}
			}
		}

		$cookies = $this->request->cookies ?? null;
		if (is_array($cookies)) {
			foreach (array_keys($cookies) as $cookieName) {
				$normalized = trim((string)$cookieName);
				if ($normalized !== '' && isset(self::FORBIDDEN_AUTH_COOKIES[$normalized])) {
					return true;
				}
			}
		}

		return false;
	}

	private function hasForbiddenAuthHeaders(): bool {
		$appApiHeader = trim($this->request->getHeader('AUTHORIZATION-APP-API'));
		$xAuthHeader = trim($this->request->getHeader('XAUTHORIZATION'));
		if ($appApiHeader !== '' || $xAuthHeader !== '') {
			return true;
		}

		$server = is_array($this->request->server ?? null) ? $this->request->server : [];
		foreach (['PHP_AUTH_USER', 'PHP_AUTH_PW', 'REMOTE_USER', 'REDIRECT_REMOTE_USER'] as $serverKey) {
			if (trim((string)($server[$serverKey] ?? '')) !== '') {
				return true;
			}
		}

		$redirectAuth = trim((string)($server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
		if ($redirectAuth !== '' && preg_match('/^(?i:Bearer)[ \t]+/', $redirectAuth) !== 1) {
			return true;
		}

		return false;
	}

	private function enforceBruteForceDelay(string $remoteIp): void {
		try {
			$this->throttler->sleepDelayOrThrowOnMax($remoteIp, self::BRUTEFORCE_ACTION);
		} catch (MaxDelayReached $e) {
			throw new ApiV1HttpException(
				Http::STATUS_TOO_MANY_REQUESTS,
				'Too many failed API authentication attempts. Please try again later.',
				['Retry-After' => '300'],
				$e
			);
		}
	}

	public function requirePositiveUrlId(string $param = 'id'): int {
		$raw = $this->request->urlParams[$param] ?? null;
		if (is_int($raw)) {
			$raw = (string)$raw;
		}
		if (!is_string($raw) || !ctype_digit($raw)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid resource ID.');
		}
		$validated = filter_var($raw, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 1, 'max_range' => self::MAX_DB_ID],
		]);
		if ($validated === false) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid resource ID.');
		}
		return $validated;
	}

	public function queryString(string $key): ?string {
		$query = $this->readQueryParameters();
		if (!array_key_exists($key, $query)) {
			return null;
		}
		$value = $query[$key];
		if (!is_string($value)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameter "' . $key . '" must be a string.');
		}
		return $value;
	}

	public function queryInt(string $key, ?int $min = null, ?int $max = null): ?int {
		$raw = $this->queryString($key);
		if ($raw === null || trim($raw) === '') {
			return null;
		}
		$trimmed = trim($raw);
		if (preg_match('/^-?\d+$/D', $trimmed) !== 1) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameter "' . $key . '" must be an integer.');
		}
		$value = filter_var($trimmed, FILTER_VALIDATE_INT);
		if ($value === false || ($min !== null && $value < $min) || ($max !== null && $value > $max)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameter "' . $key . '" is out of range.');
		}
		return $value;
	}

	public function queryBool(string $key, ?bool $default = null): ?bool {
		$raw = $this->queryString($key);
		if ($raw === null || trim($raw) === '') {
			return $default;
		}
		return match (strtolower(trim($raw))) {
			'1', 'true' => true,
			'0', 'false' => false,
			default => throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameter "' . $key . '" must be a boolean.'),
		};
	}

	/**
	 * Reads strictly up to 1 byte on non-body GET/DELETE requests post-auth.
	 */
	public function requireEmptyBody(): void {
		$expectedLength = $this->parseContentLengthHeader($this->request->getHeader('Content-Length'));
		if ($expectedLength !== null && $expectedLength > 0) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Request body is not allowed for this method.');
		}

		$stream = $this->openRawInputStream();
		if (is_resource($stream)) {
			try {
				$chunk = stream_get_contents($stream, 1);
			} finally {
				fclose($stream);
			}
			if (is_string($chunk) && $chunk !== '') {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Request body is not allowed for this method.');
			}
		}
	}

	/**
	 * Reads and decodes a bounded JSON object request body (max 64 KiB) post-auth.
	 *
	 * @return array<string, mixed>
	 */
	public function readJsonObjectBody(): array {
		$stream = $this->openRawInputStream();
		if (!is_resource($stream)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Missing JSON request body.');
		}

		try {
			return $this->parseJsonObjectFromStream(
				$this->request->getHeader('Content-Type'),
				$this->request->getHeader('Content-Length'),
				$stream
			);
		} finally {
			fclose($stream);
		}
	}

	/**
	 * Exposed for unit tests to verify stream cap and JSON object validation cleanly.
	 *
	 * @param resource $stream
	 * @return array<string, mixed>
	 */
	public function parseJsonObjectFromStream(string $contentTypeHeader, string $contentLengthHeader, mixed $stream): array {
		$mediaType = strtolower(trim(explode(';', $contentTypeHeader, 2)[0]));
		if ($mediaType !== 'application/json') {
			throw new ApiV1HttpException(415, 'Content-Type must be application/json.');
		}

		$expectedLength = $this->parseContentLengthHeader($contentLengthHeader);
		if ($expectedLength !== null && $expectedLength > self::MAX_JSON_BODY_BYTES) {
			throw new ApiV1HttpException(413, 'Request body exceeds the 64 KiB limit.');
		}

		if (!is_resource($stream)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Missing JSON request body.');
		}

		$raw = stream_get_contents($stream, self::MAX_JSON_BODY_BYTES + 1);
		if (!is_string($raw)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Unable to read request body.');
		}
		if (strlen($raw) > self::MAX_JSON_BODY_BYTES) {
			throw new ApiV1HttpException(413, 'Request body exceeds the 64 KiB limit.');
		}
		if ($expectedLength !== null && strlen($raw) !== $expectedLength) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Request body length does not match Content-Length.');
		}

		$trimmed = ltrim($raw);
		if ($trimmed === '' || $trimmed[0] !== '{') {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Request body must be a JSON object.');
		}

		try {
			$decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Malformed JSON request body.', [], $e);
		}

		if (!is_array($decoded)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Request body must be a JSON object.');
		}

		return $decoded;
	}

	private function parseContentLengthHeader(string $header): ?int {
		$trimmed = trim($header);
		if ($trimmed === '') {
			return null;
		}
		if (!ctype_digit($trimmed)) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid Content-Length header.');
		}
		$validated = filter_var($trimmed, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 0, 'max_range' => self::MAX_DB_ID],
		]);
		if ($validated === false) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid Content-Length header.');
		}
		return $validated;
	}

	/** @return resource|false */
	protected function openRawInputStream() {
		return fopen('php://input', 'rb');
	}

	private function extractValidatedHeaderToken(string $authHeader, string $customHeader): ?string {
		$bearerToken = null;
		if ($authHeader !== '') {
			if (preg_match('/^(?i:Bearer)[ \t]+(cb_[0-9a-f]{64})$/D', $authHeader, $matches) !== 1) {
				return null;
			}
			$bearerToken = $matches[1];
		}

		$customToken = null;
		if ($customHeader !== '') {
			if (preg_match('/^cb_[0-9a-f]{64}$/D', $customHeader) !== 1) {
				return null;
			}
			$customToken = $customHeader;
		}

		if ($bearerToken !== null && $customToken !== null && !hash_equals($bearerToken, $customToken)) {
			return null;
		}

		return $bearerToken ?? $customToken;
	}

	private function assertNoQueryTokens(): void {
		$rawQuery = $this->extractRawQueryString();
		if ($rawQuery !== null && $rawQuery !== '') {
			if (preg_match('/(?:^|[^a-z0-9_])cb_[0-9a-f]{32,}/i', rawurldecode($rawQuery)) === 1) {
				throw new ApiV1HttpException(
					Http::STATUS_BAD_REQUEST,
					'API keys must be sent in the Authorization or X-CoBudget-Token header, never in URLs.'
				);
			}
		}
		$this->scanQueryArrayForTokens($this->readQueryParameters());
	}

	private function extractRawQueryString(): ?string {
		$server = $this->request->server ?? null;
		if (!is_array($server)) {
			return null;
		}
		$raw = null;
		if (isset($server['QUERY_STRING']) && is_string($server['QUERY_STRING']) && $server['QUERY_STRING'] !== '') {
			$raw = $server['QUERY_STRING'];
		} elseif (isset($server['REQUEST_URI']) && is_string($server['REQUEST_URI'])) {
			$parsed = parse_url($server['REQUEST_URI'], PHP_URL_QUERY);
			if (is_string($parsed)) {
				$raw = $parsed;
			}
		}
		if ($raw === null && isset($server['QUERY_STRING']) && is_string($server['QUERY_STRING'])) {
			$raw = $server['QUERY_STRING'];
		}
		if ($raw !== null && strlen($raw) > self::MAX_QUERY_STRING_BYTES) {
			throw new ApiV1HttpException(414, 'Query string exceeds the maximum allowed length.');
		}
		return $raw;
	}

	/**
	 * Reads URL query parameters safely across all HTTP methods without triggering
	 * OC\AppFramework\Http\Request::__get('get') LogicException on POST/PATCH/DELETE.
	 *
	 * @return array<string, mixed>
	 */
	private function readQueryParameters(): array {
		$rawQuery = $this->extractRawQueryString();
		if ($rawQuery !== null) {
			if ($rawQuery === '') {
				return [];
			}
			if (substr_count(rawurldecode($rawQuery), '[') > self::MAX_QUERY_BRACKET_COUNT) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameters exceed maximum nesting complexity.');
			}
			$parsed = [];
			parse_str($rawQuery, $parsed);
			return is_array($parsed) ? $parsed : [];
		}
		if (strtoupper($this->request->getMethod()) === 'GET') {
			$query = $this->request->get;
			return is_array($query) ? $query : [];
		}
		return [];
	}

	private function scanQueryArrayForTokens(array $values, int $depth = 0): void {
		if ($depth > self::MAX_QUERY_ARRAY_DEPTH) {
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Query parameters exceed maximum nesting depth.');
		}
		foreach ($values as $key => $value) {
			if (is_string($key) && preg_match('/(?:^|[^a-z0-9_])cb_[0-9a-f]{32,}/i', $key) === 1) {
				throw new ApiV1HttpException(
					Http::STATUS_BAD_REQUEST,
					'API keys must be sent in the Authorization or X-CoBudget-Token header, never in URLs.'
				);
			}
			$keyLower = is_string($key) ? strtolower(trim($key)) : '';
			if (in_array($keyLower, self::FORBIDDEN_QUERY_TOKEN_KEYS, true)) {
				if ((is_string($value) && trim($value) !== '') || (is_array($value) && $value !== [])) {
					throw new ApiV1HttpException(
						Http::STATUS_BAD_REQUEST,
						'API keys must be sent in the Authorization or X-CoBudget-Token header, never in URLs.'
					);
				}
			}
			if (is_array($value)) {
				$this->scanQueryArrayForTokens($value, $depth + 1);
				continue;
			}
			if (is_string($value) && preg_match('/(?:^|[^a-z0-9_])cb_[0-9a-f]{32,}/i', $value) === 1) {
				throw new ApiV1HttpException(
					Http::STATUS_BAD_REQUEST,
					'API keys must be sent in the Authorization or X-CoBudget-Token header, never in URLs.'
				);
			}
		}
	}

	private function resolveTimezone(): \DateTimeZone {
		$header = trim($this->request->getHeader('X-CoBudget-Timezone'));
		if ($header !== '') {
			if (strlen($header) > 64) {
				throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid X-CoBudget-Timezone header.');
			}
			static $allowedZones = null;
			if ($allowedZones === null) {
				$allowedZones = array_flip(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL));
			}
			if (isset($allowedZones[$header])) {
				return new \DateTimeZone($header);
			}
			throw new ApiV1HttpException(Http::STATUS_BAD_REQUEST, 'Invalid X-CoBudget-Timezone header.');
		}

		$fallback = date_default_timezone_get() ?: 'UTC';
		return new \DateTimeZone($fallback);
	}

	private function registerBruteForceAttempt(string $remoteIp): void {
		try {
			$this->throttler->registerAttempt(self::BRUTEFORCE_ACTION, $remoteIp, ['app' => 'cobudget']);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to record API brute-force attempt: ' . $e->getMessage(), ['app' => 'cobudget']);
		}
	}

	private function unauthorizedException(): ApiV1HttpException {
		return new ApiV1HttpException(
			Http::STATUS_UNAUTHORIZED,
			'Invalid or inactive API key.',
			['WWW-Authenticate' => 'Bearer realm="CoBudget API"']
		);
	}
}

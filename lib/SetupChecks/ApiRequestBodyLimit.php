<?php

declare(strict_types=1);

namespace OCA\CoBudget\SetupChecks;

use OCA\CoBudget\Service\ApiV1AuthService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

final class ApiRequestBodyLimit implements ISetupCheck {
	public function __construct(
		private IConfig $config,
		private IClientService $clients,
		private IL10N $l10n,
		private IURLGenerator $urls,
		private IUserSession $session,
		private IGroupManager $groups,
	) {
	}

	public function getCategory(): string {
		return 'security';
	}

	public function getName(): string {
		return $this->l10n->t('CoBudget API request size');
	}

	public function run(): SetupResult {
		// Nextcloud also exposes the setup-check manager to signed-in non-admins.
		if (!defined('OC_CONSOLE') || !OC_CONSOLE) {
			$user = $this->session->getUser();
			if ($user === null || !$this->groups->isAdmin($user->getUID())) {
				return SetupResult::info($this->l10n->t('A Nextcloud administrator can run this check in the administration overview.'));
			}
		}
		$help = $this->urls->linkToRoute('settings.AdminSettings.index', ['section' => 'cobudget']) . '#cobudget-api-request-limit';
		$guide = ['guide' => [
			'type' => 'highlight',
			'id' => 'cobudget-api-request-limit',
			'name' => $this->l10n->t('Open the CoBudget web server guide'),
			'link' => $help,
		]];
		$unknown = SetupResult::warning($this->l10n->t('CoBudget could not verify the 64 KiB limit. Check that overwrite.cli.url points to the public Nextcloud address and that the server can resolve its hostname and validate its TLS certificate when using HTTPS. The hosting provider can verify the limit at the public web server. {guide}'), $help, $guide);

		// Only the explicitly configured instance URL may receive a local request.
		$base = rtrim($this->config->getSystemValueString('overwrite.cli.url', ''), '/');
		$parts = parse_url($base);
		if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
			|| empty($parts['host']) || strpbrk($base, "\\\r\n\t ") !== false
			|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
			return $unknown;
		}

		$client = $this->clients->newClient();
		$limit = ApiV1AuthService::MAX_JSON_BODY_BYTES;
		$verifiedPaths = 0;
		$inconclusive = false;
		foreach ([
			'/index.php/apps/cobudget/api/v1/entries',
			'/apps/cobudget/api/v1/entries',
			'/index.php//apps/cobudget/api/v1/entries',
		] as $path) {
			$url = $base . $path;
			try {
				foreach ([false, true] as $chunked) {
					$control = $this->probe($client, $url, $limit, $chunked);
					if (!$chunked && $control->getStatusCode() === 404) {
						// This URL form is not served. At least one form must pass.
						continue 2;
					}
					if (!$this->isApiChallenge($control)) {
						$inconclusive = true;
						continue 2;
					}
					$oversized = $this->probe($client, $url, $limit + 1, $chunked);
					if ($this->isApiChallenge($oversized)) {
						return SetupResult::warning($this->l10n->t('A request over 64 KiB reached CoBudget. Ask the server administrator or hosting provider to limit the external CoBudget API to 65,536 bytes before PHP. Keep the existing limit for Nextcloud Files uploads. {guide}'), $help, $guide);
					}
					if ($oversized->getStatusCode() !== 413) {
						$inconclusive = true;
						continue 2;
					}
				}
				$verifiedPaths++;
			} catch (\Exception) {
				$inconclusive = true;
			}
		}

		return !$inconclusive && $verifiedPaths > 0
			? SetupResult::success($this->l10n->t('The API at {address} rejected requests above 64 KiB with HTTP 413 for both fixed-length and chunked transfers.'), descriptionParameters: [
				'address' => ['type' => 'highlight', 'id' => 'cobudget-api-address', 'name' => $base],
			])
			: $unknown;
	}

	private function isApiChallenge(IResponse $response): bool {
		return $response->getStatusCode() === 401 && $response->getHeader('WWW-Authenticate') === 'Bearer realm="CoBudget API"';
	}

	private function probe(IClient $client, string $url, int $length, bool $chunked): IResponse {
		$headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
		if ($chunked) {
			$headers['Transfer-Encoding'] = 'chunked';
		}
		return $client->post($url, [
			'body' => str_pad('{}', $length),
			'headers' => $headers,
			'version' => '1.1',
			// Clear Nextcloud's matching low-level HTTP/2 preference as well.
			'curl' => [],
			'allow_redirects' => false,
			'cookies' => false,
			'expect' => false,
			'http_errors' => false,
			'connect_timeout' => 2,
			'timeout' => 4,
			'nextcloud' => ['allow_local_address' => true],
		]);
	}
}

<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

class TalkNotificationService {
	public const SETTING = 'notify_project_entries_talk';
	public const TOKEN = 'talk_personal_token';

	public function __construct(
		private TalkBackend $backend,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private IFactory $l10nFactory,
		private ILockingProvider $lockingProvider,
		private LoggerInterface $logger,
	) {
	}

	public function state(string $userId): array {
		$available = false;
		try {
			$available = $this->backend->available($userId);
		} catch (\Throwable $e) {
			$this->logFailure($e);
		}
		$token = $this->config->getUserValue($userId, 'cobudget', self::TOKEN, '');
		$ready = $available && $this->backend->ready($token, $userId);
		return [
			'available' => $available,
			'enabled' => $this->config->getUserValue($userId, 'cobudget', self::SETTING, 'no') === 'yes',
			'ready' => $ready,
			'url' => $ready ? $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $token]) : null,
		];
	}

	public function setEnabled(string $userId, bool $enabled): array {
		// Serialize bot creation and opt-in/out with delivery, including concurrent requests.
		$lock = 'cobudget:personal-talk:' . hash('sha256', $userId);
		$this->lockingProvider->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			if ($enabled) {
				if (!$this->backend->available($userId)) {
					throw new \RuntimeException('Talk unavailable');
				}
				$token = $this->config->getUserValue($userId, 'cobudget', self::TOKEN, '');
				if (!$this->backend->ready($token, $userId)) {
					$token = $this->backend->createPersonalConversation($userId);
					$this->config->setUserValue($userId, 'cobudget', self::TOKEN, $token);
				}
			}
			$this->config->setUserValue($userId, 'cobudget', self::SETTING, $enabled ? 'yes' : 'no');
		} finally {
			$this->lockingProvider->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
		return $this->state($userId);
	}

	public function notifyEntryCreated(string $userId, array $params, string $link): void {
		$locked = false;
		$lock = 'cobudget:personal-talk:' . hash('sha256', $userId);
		try {
			if (!$this->wantsMessages($userId)) {
				return;
			}
			$this->lockingProvider->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
			$locked = true;
			if (!$this->wantsMessages($userId) || !$this->backend->available($userId)) {
				return;
			}
			$user = $this->userManager->get($userId);
			if ($user === null || !$user->isEnabled()) {
				return;
			}
			$l = $this->l10nFactory->get('cobudget', $this->l10nFactory->getUserLanguage($user));
			$values = array_map([self::class, 'plainText'], [
				$params['actorName'], $params['amount'], $params['currency'], $params['projectName'],
			]);
			$message = $params['type'] === 'income'
				? $l->t('%s created an income of %s %s in area %s.', $values)
				: $l->t('%s created an expense of %s %s in area %s.', $values);
			// Deliberately omit free-form descriptions and receipt contents from Talk.
			$this->backend->send(
				$this->config->getUserValue($userId, 'cobudget', self::TOKEN, ''),
				$userId, $message . "\n" . $link
			);
		} catch (\Throwable $e) {
			$this->logFailure($e);
		} finally {
			if ($locked) {
				$this->lockingProvider->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
			}
		}
	}

	private function wantsMessages(string $userId): bool {
		return !ParticipantService::isReservedFormerId($userId)
			&& $this->config->getUserValue($userId, 'cobudget', self::SETTING, 'no') === 'yes'
			&& $this->config->getUserValue($userId, 'cobudget', 'enable_projects', 'yes') === 'yes'
			&& $this->config->getUserValue($userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes';
	}

	private static function plainText(string $value): string {
		$value = preg_replace('/[\r\n\x00-\x1f\x7f]/u', ' ', mb_substr($value, 0, 200)) ?? '';
		// Avoid mentions and Markdown/link injection through user-controlled names.
		$value = str_replace('@', "@\u{200B}", $value);
		return preg_replace('/([\\\\`*_{}\[\]()<>!~])/u', '\\\\$1', $value) ?? '';
	}

	private function logFailure(\Throwable $e): void {
		// Do not log financial contents or transport exception messages.
		$this->logger->warning('CoBudget personal Talk notification unavailable', ['app' => 'cobudget', 'exceptionType' => get_class($e)]);
	}
}

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
	public const PLANNED_SETTING = 'notify_planned_payments_talk';
	public const REMINDER_SETTING = 'notify_reminders_talk';

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
			'planned_enabled' => $this->config->getUserValue($userId, 'cobudget', self::PLANNED_SETTING, 'no') === 'yes',
			'reminders_enabled' => $this->config->getUserValue($userId, 'cobudget', self::REMINDER_SETTING, 'no') === 'yes',
			'ready' => $ready,
			'url' => $ready ? $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $token]) : null,
		];
	}

	public function setEnabled(string $userId, bool $enabled, string $channel = 'payments'): array {
		$setting = match ($channel) {
			'payments' => self::SETTING,
			'reminders' => self::REMINDER_SETTING,
			'planned' => self::PLANNED_SETTING,
			default => throw new \InvalidArgumentException('Unknown Talk notification channel'),
		};
		// Serialize bot creation and opt-in/out with delivery, including concurrent requests.
		// Keep the complete key within Nextcloud's VARCHAR(64) database lock limit.
		$lock = hash('sha256', 'cobudget:personal-talk:' . $userId);
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
			$this->config->setUserValue($userId, 'cobudget', $setting, $enabled ? 'yes' : 'no');
		} finally {
			$this->lockingProvider->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
		return $this->state($userId);
	}

	public function notifyEntryCreated(string $userId, array $params, string $link): void {
		$this->deliver($userId, self::SETTING, function ($l) use ($params, $link): string {
			$values = array_map([self::class, 'plainText'], [
				$params['actorName'], $params['amount'], $params['currency'], $params['projectName'],
			]);
			$message = $params['type'] === 'income'
				? $l->t('%s created an income of %s %s in area %s.', $values)
				: $l->t('%s created an expense of %s %s in area %s.', $values);
			// Deliberately omit free-form descriptions and receipt contents from Talk.
			return $message . "\n" . $link;
		});
	}

	public function notifyReminder(string $userId, array $params): void {
		$this->deliver($userId, self::REMINDER_SETTING, function ($l) use ($params): string {
			// Only the explicitly entered reminder text is copied, not the payment description.
			$title = trim((string)($params['reminderText'] ?? ''));
			$title = $title !== '' ? self::plainText($title, 255) : $l->t('Payment reminder');
			$message = $l->t('CoBudget reminder: %s', [$title]);
			$message .= "\n" . $l->t('%s of %s %s', [
				$l->t($params['type'] === 'Income' ? 'Income' : 'Expense'),
				self::plainText($params['amount']), self::plainText($params['currency']),
			]);
			$message .= "\n" . $l->t('Reminder due since %s', [self::plainText($params['reminderDate'])]);
			$link = $this->urlGenerator->linkToRouteAbsolute('cobudget.page.index');
			if (!empty($params['organizerId'])) $link .= '#/reminders?reminder=' . (int)$params['organizerId'];
			elseif (!empty($params['projectId'])) $link .= '#/projects/' . (int)$params['projectId'];
			return $message . "\n" . $link;
		});
	}

	public function notifyPlannedPayment(string $userId, array $params): bool {
		return $this->deliver($userId, self::PLANNED_SETTING, function ($l) use ($params): string {
			$message = $l->t('Planned payment due');
			$message .= "\n" . $l->t('%s of %s %s', [$l->t($params['type']), self::plainText($params['amount']), self::plainText($params['currency'])]);
			$message .= "\n" . $l->t('Payment from %s', [self::plainText($params['entryDate'])]);
			$link = $this->urlGenerator->linkToRouteAbsolute('cobudget.page.index');
			if (!empty($params['projectId'])) $link .= '#/projects/' . (int)$params['projectId'];
			return $message . "\n" . $link;
		});
	}

	public function notifyOrganizerReminder(string $userId, array $reminder): bool {
		return $this->deliver($userId, self::REMINDER_SETTING, function ($l) use ($reminder): string {
			return $l->t('CoBudget reminder: %s', [self::plainText($reminder['title'], 255)]) . "\n"
				. self::plainText($reminder['description'] ?? '', 10000) . "\n"
				. $this->urlGenerator->linkToRouteAbsolute('cobudget.page.index') . '#/reminders?reminder=' . (int)$reminder['id'];
		});
	}

	private function deliver(string $userId, string $setting, \Closure $message): bool {
		$locked = false;
		// Keep the complete key within Nextcloud's VARCHAR(64) database lock limit.
		$lock = hash('sha256', 'cobudget:personal-talk:' . $userId);
		try {
			if (!$this->wantsMessages($userId, $setting)) return true;
			$this->lockingProvider->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
			$locked = true;
			if (!$this->wantsMessages($userId, $setting)) return true;
			if (!$this->backend->available($userId)) return false;
			$user = $this->userManager->get($userId);
			if ($user === null || !$user->isEnabled()) return true;
			$l = $this->l10nFactory->get('cobudget', $this->l10nFactory->getUserLanguage($user));
			$this->backend->send(
				$this->config->getUserValue($userId, 'cobudget', self::TOKEN, ''),
				$userId, $message($l)
			);
			return true;
		} catch (\Throwable $e) {
			$this->logFailure($e);
			return false;
		} finally {
			if ($locked) $this->lockingProvider->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	private function wantsMessages(string $userId, string $setting): bool {
		return !ParticipantService::isReservedFormerId($userId)
			&& $this->config->getUserValue($userId, 'cobudget', $setting, 'no') === 'yes'
			&& ($setting !== self::SETTING || (
				$this->config->getUserValue($userId, 'cobudget', 'enable_projects', 'yes') === 'yes'
				&& $this->config->getUserValue($userId, 'cobudget', 'enable_shared_projects', 'yes') === 'yes'
			));
	}

	private static function plainText(string $value, int $limit = 200): string {
		$value = preg_replace('/[\r\n\x00-\x1f\x7f]/u', ' ', mb_substr($value, 0, $limit)) ?? '';
		// Avoid mentions and Markdown/link injection through user-controlled names.
		$value = str_replace('@', "@\u{200B}", $value);
		return preg_replace('/([\\\\`*_{}\[\]()<>!~])/u', '\\\\$1', $value) ?? '';
	}

	private function logFailure(\Throwable $e): void {
		// Do not log financial contents or transport exception messages.
		$this->logger->warning('CoBudget personal Talk notification unavailable', ['app' => 'cobudget', 'exceptionType' => get_class($e)]);
	}
}

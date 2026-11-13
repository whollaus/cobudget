<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/** Uses only the recipient's current Nextcloud profile address and the server mail transport. */
class EmailNotificationService {
	public const SETTINGS = ['notify_reminders_email', 'notify_planned_payments_email', 'notify_project_entries_email', 'notify_project_settlements_email'];

	public function __construct(
		private IMailer $mailer,
		private IUserManager $users,
		private IConfig $config,
		private IFactory $l10n,
		private IURLGenerator $urls,
		private LoggerInterface $logger,
	) {}

	/** False means a transport failure that a scheduled caller may retry. */
	public function send(string $uid, string $event, array $params): bool {
		$setting = match ($event) {
			'reminder' => self::SETTINGS[0],
			'planned' => self::SETTINGS[1],
			'entry' => self::SETTINGS[2],
			'settlement' => self::SETTINGS[3],
			default => throw new \InvalidArgumentException('Unknown email event'),
		};
		try {
			if (ParticipantService::isReservedFormerId($uid) || $this->config->getUserValue($uid, 'cobudget', $setting, 'no') !== 'yes') return true;
			if (in_array($event, ['entry', 'settlement'], true) && (
				$this->config->getUserValue($uid, 'cobudget', 'enable_projects', 'yes') !== 'yes'
				|| $this->config->getUserValue($uid, 'cobudget', 'enable_shared_projects', 'yes') !== 'yes'
			)) return true;
			$user = $this->users->get($uid);
			if (!$user || !$user->isEnabled()) return true;
			$address = trim((string)$user->getEMailAddress());
			if (!filter_var($address, FILTER_VALIDATE_EMAIL)) return true;
			$l = $this->l10n->get('cobudget', $this->l10n->getUserLanguage($user));
			$link = $this->urls->linkToRouteAbsolute('cobudget.page.index');
			if ($event === 'reminder') {
				$subject = $l->t('Payment reminder');
				$body = $l->t('CoBudget reminder: %s', [trim((string)$params['title']) ?: $l->t('Payment reminder')]);
				$body .= "\n" . (string)($params['description'] ?? '');
				$link .= '#/reminders?reminder=' . (int)$params['id'];
			} elseif ($event === 'planned') {
				$subject = $l->t('Planned payment due');
				$body = $l->t('%s of %s %s', [$l->t($params['type']), $params['amount'], $params['currency']]);
				$body .= "\n" . $l->t('Payment from %s', [$params['entryDate']]);
				if (!empty($params['projectId'])) $link .= '#/projects/' . (int)$params['projectId'];
			} elseif ($event === 'entry') {
				$subject = $l->t('New payments in shared areas');
				$body = $l->t($params['type'] === 'income'
					? '%s created an income of %s %s in area %s.'
					: '%s created an expense of %s %s in area %s.',
					[$params['actorName'], $params['amount'], $params['currency'], $params['projectName']]);
				$link .= '#/projects/' . (int)$params['projectId'];
			} else {
				$subject = $l->t('Settled areas');
				$body = $l->t('%s settled area %s.', [$params['actorName'], $params['projectName']]);
				$link .= '#/projects/' . (int)$params['projectId'];
			}
			$message = $this->mailer->createMessage();
			$message->setTo([$address]);
			$message->setSubject('CoBudget: ' . $subject);
			// Plain text prevents HTML injection. No payment descriptions or attachments are copied.
			$message->setPlainBody($body . "\n\n" . $link);
			if ($this->mailer->send($message) !== []) throw new \RuntimeException('Mail recipient rejected');
			return true;
		} catch (\Throwable $e) {
			$this->logger->warning('CoBudget email delivery failed', ['app' => 'cobudget', 'event' => $event, 'exceptionType' => get_class($e)]);
			return false;
		}
	}
}

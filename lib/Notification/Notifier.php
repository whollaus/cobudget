<?php
namespace OCA\CoBudget\Notification;

use OCP\L10N\IFactory;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	private IFactory $l10nFactory;
	private IURLGenerator $urlGenerator;

	public function __construct(IFactory $l10nFactory, IURLGenerator $urlGenerator) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return 'cobudget';
	}

	public function getName(): string {
		return 'CoBudget';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'cobudget') {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get('cobudget', $languageCode);

		if ($notification->getSubject() === 'reminder') {
			$params = $notification->getSubjectParameters();
			$title = $this->param($params, 'title') ?: ($this->param($params, 'description') ?: $l->t('Payment without description'));

			$notification->setParsedSubject($l->t('CoBudget reminder: %s', [$title]))
				->setLink($this->appLink());
			return $this->setParsedMessageWhenPresent($notification, $this->buildReminderMessage($params, $l));
		}

		if ($notification->getSubject() === 'project_entry_created') {
			$params = $notification->getSubjectParameters();
			$projectId = $this->param($params, 'projectId');
			$actorName = $this->param($params, 'actorName') ?: $l->t('Someone');
			$projectName = $this->param($params, 'projectName') ?: $l->t('Untitled area');
			$type = $this->param($params, 'type');
			$amount = $this->param($params, 'amount');
			$currency = $this->param($params, 'currency') ?: 'EUR';

			$subject = $type === 'income'
				? $l->t('%s created an income of %s %s in area %s.', [$actorName, $amount, $currency, $projectName])
				: $l->t('%s created an expense of %s %s in area %s.', [$actorName, $amount, $currency, $projectName]);

			$notification->setParsedSubject($subject)
				->setLink($this->projectLink($projectId));
			return $this->setParsedMessageWhenPresent($notification, $this->buildProjectEntryMessage($params, $l));
		}

		if ($notification->getSubject() === 'project_settled') {
			$params = $notification->getSubjectParameters();
			$projectId = $this->param($params, 'projectId');
			$actorName = $this->param($params, 'actorName') ?: $l->t('Someone');
			$projectName = $this->param($params, 'projectName') ?: $l->t('Untitled area');

			$notification->setParsedSubject($l->t('%s settled area %s.', [$actorName, $projectName]))
				->setLink($this->projectLink($projectId));
			return $this->setParsedMessageWhenPresent($notification, $this->buildProjectSettlementMessage($params, $l));
		}

		// Keep obsolete CoBudget notifications readable instead of allowing one
		// stale database row to break the complete Nextcloud notification list.
		$notification->setParsedSubject($l->t('CoBudget notification'))
			->setLink($this->appLink());
		return $this->setParsedMessageWhenPresent($notification, $l->t('This notification is no longer available.'));
	}

	private function buildReminderMessage(array $params, $l): string {
		$type = $l->t($this->param($params, 'type') ?: 'Payment');
		$amount = $this->param($params, 'amount');
		$currency = $this->param($params, 'currency') ?: 'EUR';
		$paymentPartner = $this->param($params, 'paymentPartner');
		$category = $this->param($params, 'category');
		$entryDate = $this->param($params, 'entryDate');
		$reminderDate = $this->param($params, 'reminderDate');

		$details = [];
		if ($amount !== '') {
			$details[] = $l->t('%s of %s %s', [$type, $amount, $currency]);
		} else {
			$details[] = $type;
		}
		if ($paymentPartner !== '') {
			$details[] = $l->t('Payment partner: %s', [$paymentPartner]);
		}
		if ($category !== '') {
			$details[] = $l->t('Category: %s', [$category]);
		}
		if ($entryDate !== '') {
			$details[] = $l->t('Payment from %s', [$entryDate]);
		}
		if ($reminderDate !== '') {
			$details[] = $l->t('Reminder due since %s', [$reminderDate]);
		}

		return implode(' · ', $details);
	}

	private function buildProjectEntryMessage(array $params, $l): string {
		$type = $this->param($params, 'type');
		$entryUserName = $this->param($params, 'entryUserName');
		$description = $this->param($params, 'description');

		$details = [];
		if ($entryUserName !== '') {
			$details[] = $type === 'income'
				? $l->t('Received by: %s', [$entryUserName])
				: $l->t('Paid by: %s', [$entryUserName]);
		}
		if ($description !== '') {
			$details[] = $l->t('Description: %s', [$description]);
		}

		return implode(' · ', $details);
	}

	private function buildProjectSettlementMessage(array $params, $l): string {
		$direction = $this->param($params, 'balanceDirection');
		$balanceAmount = $this->param($params, 'balanceAmount');
		$totalAmount = $this->param($params, 'totalAmount');
		$currency = $this->param($params, 'currency') ?: 'EUR';

		if ($direction === 'receives') {
			$message = $l->t('You get %s %s back.', [$balanceAmount, $currency]);
		} elseif ($direction === 'owes') {
			$message = $l->t('You owe %s %s.', [$balanceAmount, $currency]);
		} else {
			$message = $l->t('You are settled.');
		}

		if ($totalAmount !== '') {
			$message .= ' ' . $l->t('Total settled expenses: %s %s.', [$totalAmount, $currency]);
		}

		return $message;
	}

	private function projectLink(string $projectId): string {
		$url = $this->appLink();
		if ($projectId !== '') {
			return $url . '#/projects/' . rawurlencode($projectId);
		}
		return $url;
	}

	private function appLink(): string {
		return $this->urlGenerator->linkToRouteAbsolute('cobudget.page.index');
	}

	private function setParsedMessageWhenPresent(INotification $notification, string $message): INotification {
		$message = trim($message);
		if ($message !== '') {
			$notification->setParsedMessage($message);
		}

		return $notification;
	}

	private function param(array $params, string $key): string {
		$value = $params[$key] ?? '';
		if (is_scalar($value) || $value instanceof \Stringable) {
			return trim((string)$value);
		}

		return '';
	}
}

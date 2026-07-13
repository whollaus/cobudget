<?php
namespace OCA\CoBudget\Cron;

use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use OCA\CoBudget\Service\ParticipantService;
use Psr\Log\LoggerInterface;

class RemindersJob extends TimedJob {

	private const JOB_INTERVAL_SECONDS = 5 * 60;

	private IDBConnection $db;
	private LoggerInterface $logger;
	private IManager $notificationManager;
	private ParticipantService $participantService;

	public function __construct(ITimeFactory $timeFactory, IDBConnection $db, LoggerInterface $logger, IManager $notificationManager, ParticipantService $participantService) {
		parent::__construct($timeFactory);
		$this->db = $db;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->participantService = $participantService;
		
		// Match common web-cron setups so reminders are sent shortly after they are due.
		$this->setInterval(self::JOB_INTERVAL_SECONDS);
	}

	protected function run($argument) {
		$now = time();
		
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*', 'c.name AS category_name', 'p.name AS payment_partner_name')
			->from('cobudget_entries', 'e')
			->leftJoin('e', 'cobudget_categories', 'c', $qb->expr()->eq('e.category_id', 'c.id'))
			->leftJoin('e', 'cobudget_payment_partners', 'p', $qb->expr()->eq('e.payment_partner_id', 'p.id'))
			->where($qb->expr()->isNotNull('e.reminder_date'))
			->andWhere($qb->expr()->eq('e.reminder_notified', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->andWhere($qb->expr()->lte('e.reminder_date', $qb->createNamedParameter($now, \PDO::PARAM_INT)));
			
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		foreach ($entries as $entry) {
			try {
				foreach ($this->reminderRecipientUserIds($entry) as $recipientUserId) {
					$notification = $this->notificationManager->createNotification();
					$notification->setApp('cobudget')
						->setUser($recipientUserId)
						->setDateTime(new \DateTime())
						->setObject('entry', $entry['id'])
						->setSubject('reminder', [
							'title' => $this->reminderTitle($entry),
							'description' => $entry['description'] ?: '',
							'type' => $this->entryTypeLabel($entry),
							'amount' => $this->formatAmount($entry),
							'currency' => $entry['currency'] ?: 'EUR',
							'category' => $entry['category_name'] ?? '',
							'paymentPartner' => $entry['payment_partner_name'] ?: ($entry['paymentPartner'] ?? ''),
							'entryDate' => $this->formatTimestamp((int)$entry['date']),
							'reminderDate' => $this->formatTimestamp((int)$entry['reminder_date']),
						]);

					$this->notificationManager->notify($notification);
				}

				$updateQb = $this->db->getQueryBuilder();
				$updateQb->update('cobudget_entries')
					->set('reminder_notified', $updateQb->createNamedParameter(true, \PDO::PARAM_BOOL))
					->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($entry['id'], \PDO::PARAM_INT)))
					->andWhere($updateQb->expr()->eq('user_id', $updateQb->createNamedParameter($entry['user_id'])));
				if (($entry['workspace_id'] ?? null) === null) {
					$updateQb->andWhere($updateQb->expr()->isNull('workspace_id'));
				} else {
					$updateQb->andWhere($updateQb->expr()->eq('workspace_id', $updateQb->createNamedParameter((int)$entry['workspace_id'], \PDO::PARAM_INT)));
				}
				$updateQb->executeStatement();

			} catch (\Exception $e) {
				$this->logger->error('Failed to process reminder for entry ' . $entry['id'] . ': ' . $e->getMessage(), ['app' => 'cobudget']);
			}
		}
	}

	/** @return string[] */
	private function reminderRecipientUserIds(array $entry): array {
		$entryKind = strtolower(trim((string)($entry['entry_kind'] ?? 'personal')));
		$projectId = empty($entry['project_id']) ? null : (int)$entry['project_id'];
		if ($entryKind !== 'shared' || $projectId === null) {
			$userId = trim((string)($entry['user_id'] ?? ''));
			return $userId !== '' && $this->participantService->isActive($userId) ? [$userId] : [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$userIds = [];
		foreach ($result->fetchAll() as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			if ($userId !== '' && $this->participantService->isActive($userId)) {
				$userIds[$userId] = true;
			}
		}
		$result->closeCursor();

		return array_keys($userIds);
	}

	private function reminderTitle(array $entry): string {
		$title = trim((string)($entry['reminder_text'] ?? ''));
		if ($title !== '') {
			return $title;
		}

		foreach (['description', 'payment_partner_name', 'paymentPartner', 'category_name'] as $field) {
			$value = trim((string)($entry[$field] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}

		return 'Payment without description';
	}

	private function entryTypeLabel(array $entry): string {
		return ($entry['type'] ?? '') === 'income' ? 'Income' : 'Expense';
	}

	private function formatAmount(array $entry): string {
		$cents = $this->amountCentsFromRow($entry);
		return number_format($cents / 100, 2, ',', '.');
	}

	private function formatTimestamp(int $timestamp): string {
		if ($timestamp <= 0) {
			return '';
		}

		return date('d.m.Y H:i', $timestamp);
	}

	private function amountCentsFromRow(array $row): int {
		if (isset($row['amount_cents']) && $row['amount_cents'] !== null && $row['amount_cents'] !== '') {
			return (int)$row['amount_cents'];
		}

		return $this->decimalToCents((string)($row['amount'] ?? '0'));
	}

	private function decimalToCents(string $amount): int {
		$amount = trim(str_replace(',', '.', $amount));
		if ($amount === '') {
			return 0;
		}

		$negative = false;
		if ($amount[0] === '-') {
			$negative = true;
			$amount = substr($amount, 1);
		}

		[$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
		$whole = preg_replace('/\D/', '', $whole) ?: '0';
		$fraction = preg_replace('/\D/', '', $fraction);
		$fraction = str_pad($fraction, 3, '0');

		$cents = ((int)$whole * 100) + (int)substr($fraction, 0, 2);
		if ((int)$fraction[2] >= 5) {
			$cents++;
		}

		return $negative ? -$cents : $cents;
	}
}

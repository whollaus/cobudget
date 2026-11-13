<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

/** Delivery follows committed originals; personal projections never produce a second event. */
class PlannedPaymentNotificationService {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private ParticipantService $participants,
		private IManager $notifications,
		private TalkNotificationService $talk,
		private ILockingProvider $locks,
		private LoggerInterface $logger,
		private EmailNotificationService $email,
	) {}

	public function processDue(int $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('cobudget_entries')
			->where($qb->expr()->eq('planned_notified', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($now, \PDO::PARAM_INT)))
			->orderBy('date')->addOrderBy('id');
		$result = $qb->executeQuery();
		$ids = $result->fetchAll();
		$result->closeCursor();
		foreach ($ids as $row) {
			$lock = 'cobudget:planned-payment:' . (int)$row['id'];
			$locked = false;
			try {
				$this->locks->acquireLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
				$locked = true;
				$this->deliverEntry((int)$row['id'], $now);
			} catch (\Throwable $e) {
				$this->logger->warning('CoBudget planned payment notification failed', ['app' => 'cobudget', 'exceptionType' => get_class($e)]);
			} finally {
				if ($locked) $this->locks->releaseLock($lock, ILockingProvider::LOCK_EXCLUSIVE);
			}
		}
	}

	private function deliverEntry(int $id, int $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_entries')->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$entry = $result->fetch();
		$result->closeCursor();
		if (!$entry || $entry['planned_notified'] || (int)$entry['date'] > $now) return;
		if (!empty($entry['source_entry_id']) || !empty($entry['is_settled']) || !empty($entry['settlement_id'])) {
			$this->saveState($entry, [], true);
			return;
		}
		$recipients = $this->recipients($entry);
		$state = !empty($entry['planned_delivery']) ? json_decode($entry['planned_delivery'], true, 512, JSON_THROW_ON_ERROR) : null;
		if ($state === null) {
			$state = [];
			foreach ($recipients as $uid) {
				$state[hash('sha256', $uid)] = [
					'nextcloud' => $this->enabled($uid, 'notify_planned_payments'),
					'talk' => $this->enabled($uid, TalkNotificationService::PLANNED_SETTING),
					'email' => $this->enabled($uid, 'notify_planned_payments_email'),
				];
			}
			// Snapshot eligibility before any transport: later opt-in does not replay this event.
			if (!$this->saveState($entry, $state, false)) return;
		}
		$params = [
			'type' => $entry['type'] === 'income' ? 'Income' : 'Expense',
			'amount' => number_format((int)$entry['amount_cents'] / 100, 2, ',', '.'),
			'currency' => $entry['currency'] ?: 'EUR',
			'entryDate' => date('d.m.Y H:i', (int)$entry['date']),
			'projectId' => $entry['entry_kind'] === 'shared' ? (int)$entry['project_id'] : null,
		];
		$state = array_intersect_key($state, array_fill_keys(array_map(fn($uid) => hash('sha256', $uid), $recipients), true));
		$pending = false;
		foreach ($recipients as $uid) {
			$key = hash('sha256', $uid);
			foreach (['nextcloud' => 'notify_planned_payments', 'talk' => TalkNotificationService::PLANNED_SETTING, 'email' => 'notify_planned_payments_email'] as $channel => $setting) {
				if (empty($state[$key][$channel])) continue;
				try {
					if ($this->enabled($uid, $setting)) {
						if ($channel === 'nextcloud') {
							$notification = $this->notifications->createNotification();
							$notification->setApp('cobudget')->setUser($uid)->setDateTime(new \DateTime())
								->setObject('entry', (string)$id)->setSubject('planned_payment_due', $params);
							$this->notifications->notify($notification);
						} elseif ($channel === 'email' ? !$this->email->send($uid, 'planned', $params) : !$this->talk->notifyPlannedPayment($uid, $params)) {
							$pending = true;
							continue;
						}
					}
					$state[$key][$channel] = false;
					if (!$this->saveState($entry, $state, false)) return;
				} catch (\Throwable $e) {
					$pending = true;
					$this->logger->warning('CoBudget planned payment channel failed', ['app' => 'cobudget', 'channel' => $channel, 'exceptionType' => get_class($e)]);
				}
			}
		}
		// Removed/disabled accounts and former members are not retried.
		$this->saveState($entry, $state, !$pending);
	}

	private function enabled(string $uid, string $setting): bool {
		return $this->config->getUserValue($uid, 'cobudget', $setting, 'no') === 'yes';
	}

	/** @return string[] */
	private function recipients(array $entry): array {
		if ($entry['entry_kind'] !== 'shared') {
			return $this->participants->isActive($entry['user_id']) ? [$entry['user_id']] : [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter((int)$entry['project_id'], \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$members = $result->fetchAll();
		$result->closeCursor();
		return array_values(array_unique(array_filter(array_column($members, 'user_id'), fn($uid) => $this->participants->isActive($uid))));
	}

	private function saveState(array $entry, array $state, bool $done): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('planned_delivery', $qb->createNamedParameter(json_encode($done ? [] : $state, JSON_THROW_ON_ERROR)))
			->set('planned_notified', $qb->createNamedParameter($done, \PDO::PARAM_BOOL))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$entry['id'], \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($entry['user_id'])))
			->andWhere($qb->expr()->eq('date', $qb->createNamedParameter((int)$entry['date'], \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('planned_notified', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$entry['workspace_id'] === null
			? $qb->andWhere($qb->expr()->isNull('workspace_id'))
			: $qb->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter((int)$entry['workspace_id'], \PDO::PARAM_INT)));
		return $qb->executeStatement() > 0;
	}
}

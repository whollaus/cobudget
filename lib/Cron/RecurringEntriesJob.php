<?php
namespace OCA\CoBudget\Cron;

use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

class RecurringEntriesJob extends TimedJob {

	private const RECURRENCE_HOUR = 9;
	private const RECURRENCE_MINUTE = 0;
	private const JOB_INTERVAL_SECONDS = 5 * 60;

	private IDBConnection $db;
	private LoggerInterface $logger;

	public function __construct(ITimeFactory $timeFactory, IDBConnection $db, LoggerInterface $logger) {
		parent::__construct($timeFactory);
		$this->db = $db;
		$this->logger = $logger;
		
		// Match common web-cron setups and allow due recurrences to run shortly after 09:00.
		$this->setInterval(self::JOB_INTERVAL_SECONDS);
	}

	protected function run($argument) {
		$now = time();
		$dueCutoff = $this->recurrenceDueCutoff($now);
		
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entries')
			->where($qb->expr()->isNotNull('recurrence_interval'))
			->andWhere($qb->expr()->isNotNull('recurrence_next_date'))
			->andWhere($qb->expr()->lte('recurrence_next_date', $qb->createNamedParameter($dueCutoff, \PDO::PARAM_INT)));
			
		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		foreach ($entries as $entry) {
			try {
				$this->db->beginTransaction();
				$amountCents = $this->amountCentsFromRow($entry);
				$runDate = $this->normalizeToRecurrenceTime((int)$entry['recurrence_next_date']);
				$seriesId = $this->recurrenceSeriesIdFromRow($entry);

				$claimed = $this->deactivateCurrentSeriesHead($entry);
				if (!$claimed) {
					$this->db->rollBack();
					continue;
				}

				if (!empty($entry['recurrence_end_date']) && $runDate > (int)$entry['recurrence_end_date']) {
					$this->db->commit();
					continue;
				}

				$nextDate = $this->calculateNextDate($runDate, $entry['recurrence_interval'], (int)$entry['recurrence_multiplier']);
				$hasNextRun = empty($entry['recurrence_end_date']) || $nextDate <= (int)$entry['recurrence_end_date'];

				$insertQb = $this->db->getQueryBuilder();
				$insertQb->insert('cobudget_entries')
					->values([
						'user_id' => $insertQb->createNamedParameter($entry['user_id']),
						'project_id' => $insertQb->createNamedParameter($entry['project_id'], $entry['project_id'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
						'type' => $insertQb->createNamedParameter($entry['type']),
						'amount' => $insertQb->createNamedParameter($this->centsToAmountString($amountCents)),
						'amount_cents' => $insertQb->createNamedParameter($amountCents, \PDO::PARAM_INT),
						'currency' => $insertQb->createNamedParameter($entry['currency']),
						'date' => $insertQb->createNamedParameter($runDate, \PDO::PARAM_INT),
						'category_id' => $insertQb->createNamedParameter($entry['category_id'], $entry['category_id'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
						'description' => $insertQb->createNamedParameter($entry['description']),
						'payment_partner_id' => $insertQb->createNamedParameter($entry['payment_partner_id'] ?? null, ($entry['payment_partner_id'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
						'split_mode' => $insertQb->createNamedParameter($entry['split_mode'] ?? 'project_shares'),
						'is_settled' => $insertQb->createNamedParameter(false, \PDO::PARAM_BOOL),
						'is_subscription' => $insertQb->createNamedParameter($this->dbBool($entry['is_subscription'] ?? false), \PDO::PARAM_BOOL),
						'is_fixed_cost' => $insertQb->createNamedParameter($this->dbBool($entry['is_fixed_cost'] ?? false), \PDO::PARAM_BOOL),
						'is_child_related' => $insertQb->createNamedParameter($this->dbBool($entry['is_child_related'] ?? false), \PDO::PARAM_BOOL),
						'is_important' => $insertQb->createNamedParameter($this->dbBool($entry['is_important'] ?? false), \PDO::PARAM_BOOL),
						'needs_review' => $insertQb->createNamedParameter($this->dbBool($entry['needs_review'] ?? false), \PDO::PARAM_BOOL),
						'is_tax_relevant' => $insertQb->createNamedParameter($this->dbBool($entry['is_tax_relevant'] ?? false), \PDO::PARAM_BOOL),
						'recurrence_interval' => $insertQb->createNamedParameter($hasNextRun ? $entry['recurrence_interval'] : null, $hasNextRun ? \PDO::PARAM_STR : \PDO::PARAM_NULL),
						'recurrence_multiplier' => $insertQb->createNamedParameter($hasNextRun ? (int)$entry['recurrence_multiplier'] : null, $hasNextRun ? \PDO::PARAM_INT : \PDO::PARAM_NULL),
						'recurrence_next_date' => $insertQb->createNamedParameter($hasNextRun ? $nextDate : null, $hasNextRun ? \PDO::PARAM_INT : \PDO::PARAM_NULL),
						'recurrence_end_date' => $insertQb->createNamedParameter($hasNextRun ? ($entry['recurrence_end_date'] ?? null) : null, ($hasNextRun && ($entry['recurrence_end_date'] ?? null) !== null) ? \PDO::PARAM_INT : \PDO::PARAM_NULL),
						'recurrence_parent_id' => $insertQb->createNamedParameter($entry['id'], \PDO::PARAM_INT),
						'recurrence_series_id' => $insertQb->createNamedParameter($seriesId, \PDO::PARAM_INT),
						'workspace_id' => $insertQb->createNamedParameter($entry['workspace_id'] ?? null, ($entry['workspace_id'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT),
					]);
				$insertQb->executeStatement();

				$this->db->commit();
			} catch (\Exception $e) {
				$this->db->rollBack();
				$this->logger->error('Failed to process recurring entry ' . $entry['id'] . ': ' . $e->getMessage(), ['app' => 'cobudget']);
			}
		}
	}

	private function recurrenceDueCutoff(int $now): int {
		return $now;
	}

	private function deactivateCurrentSeriesHead(array $entry): bool {
		$updateQb = $this->db->getQueryBuilder();
		$updated = $updateQb->update('cobudget_entries')
			->set('recurrence_interval', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_multiplier', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_next_date', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_end_date', $updateQb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_series_id', $updateQb->createNamedParameter($this->recurrenceSeriesIdFromRow($entry), \PDO::PARAM_INT))
			->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($entry['id'], \PDO::PARAM_INT)))
			->andWhere($updateQb->expr()->isNotNull('recurrence_interval'))
			->andWhere($updateQb->expr()->eq('recurrence_next_date', $updateQb->createNamedParameter((int)$entry['recurrence_next_date'], \PDO::PARAM_INT)))
			->executeStatement();

		return $updated > 0;
	}

	private function recurrenceSeriesIdFromRow(array $entry): int {
		if (!empty($entry['recurrence_series_id'])) {
			return (int)$entry['recurrence_series_id'];
		}

		return (int)$entry['id'];
	}

	private function calculateNextDate(int $currentTimestamp, string $interval, int $multiplier): int {
		$date = new \DateTime();
		$date->setTimestamp($currentTimestamp);
		$date->setTime(self::RECURRENCE_HOUR, self::RECURRENCE_MINUTE, 0);
		
		$multiplier = max(1, $multiplier);

		switch ($interval) {
			case 'day':
				$date->modify("+$multiplier day");
				break;
			case 'week':
				$date->modify("+$multiplier week");
				break;
			case 'month':
				// Handle month rollover (e.g. Jan 31 + 1 month -> March 3 usually)
				// A common trick is to use No-Overflow logic if needed, but native modify is usually fine for most recurring payments
				// Alternatively, we use native '+1 month'
				$date->modify("+$multiplier month");
				break;
			default:
				$date->modify("+$multiplier month");
		}

		return $this->normalizeToRecurrenceTime($date->getTimestamp());
	}

	private function normalizeToRecurrenceTime(int $timestamp): int {
		$date = new \DateTime();
		$date->setTimestamp($timestamp);
		$date->setTime(self::RECURRENCE_HOUR, self::RECURRENCE_MINUTE, 0);

		return $date->getTimestamp();
	}

	private function amountCentsFromRow(array $row): int {
		if (isset($row['amount_cents']) && $row['amount_cents'] !== null && $row['amount_cents'] !== '') {
			return (int)$row['amount_cents'];
		}

		return $this->decimalToCents((string)($row['amount'] ?? '0'));
	}

	private function centsToAmountString(int $cents): string {
		return number_format($cents / 100, 2, '.', '');
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

	private function dbBool($value): bool {
		return $value === true || $value === 1 || $value === '1';
	}
}

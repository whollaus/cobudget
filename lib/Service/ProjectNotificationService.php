<?php
namespace OCA\CoBudget\Service;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class ProjectNotificationService {

	private const APP_ID = 'cobudget';
	private const SETTING_NOTIFY_ENTRIES = 'notify_project_entries';
	private const SETTING_NOTIFY_SETTLEMENTS = 'notify_project_settlements';

	private IDBConnection $db;
	private IManager $notificationManager;
	private IConfig $config;
	private IUserManager $userManager;
	private IURLGenerator $urlGenerator;
	private LoggerInterface $logger;

	public function __construct(
		IDBConnection $db,
		IManager $notificationManager,
		IConfig $config,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		LoggerInterface $logger
	) {
		$this->db = $db;
		$this->notificationManager = $notificationManager;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	public function notifyEntryCreated(
		int $projectId,
		int $workspaceId,
		int $entryId,
		string $actorUserId,
		string $entryUserId,
		string $type,
		int $amountCents,
		string $currency,
		string $description = ''
	): void {
		try {
			if (!$this->projectNotificationsEnabled($actorUserId)) {
				return;
			}

			$project = $this->project($projectId, $workspaceId);
			if ($project === null) {
				return;
			}

			$members = $this->members($projectId);
			if (count($members) <= 1) {
				return;
			}

			$actorName = $this->displayName($actorUserId);
			$entryUserName = $this->displayName($entryUserId);
			$entryType = $type === 'income' ? 'Income' : 'Expense';
			$link = $this->projectLink($projectId);

			foreach ($members as $recipientUserId) {
				if ($recipientUserId === $actorUserId || !$this->userWants($recipientUserId, self::SETTING_NOTIFY_ENTRIES)) {
					continue;
				}

				$notification = $this->notificationManager->createNotification();
				$notification->setApp(self::APP_ID)
					->setUser($recipientUserId)
					->setDateTime(new \DateTime())
					->setObject('project', (string)$projectId)
					->setSubject('project_entry_created', [
						'projectId' => (string)$projectId,
						'projectName' => (string)$project['name'],
						'actorName' => $actorName,
						'entryUserName' => $entryUserName,
						'entryType' => $entryType,
						'type' => $type,
						'amount' => $this->formatAmount($amountCents),
						'currency' => $currency !== '' ? $currency : 'EUR',
						'description' => $description,
					])
					->setLink($link);

				$this->notificationManager->notify($notification);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Failed to send CoBudget project entry notification: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}

	public function prepareSettlementNotifications(int $projectId, int $workspaceId, string $actorUserId): array {
		try {
			if (!$this->projectNotificationsEnabled($actorUserId)) {
				return [];
			}

			$project = $this->project($projectId, $workspaceId);
			if ($project === null) {
				return [];
			}

			$members = $this->members($projectId);
			if (count($members) <= 1) {
				return [];
			}

			$balances = $this->openExpenseBalances($projectId, $workspaceId, $members);
			if ($balances === []) {
				return [];
			}

			$payloads = [];
			$actorName = $this->displayName($actorUserId);
			$link = $this->projectLink($projectId);
			foreach ($members as $recipientUserId) {
				if ($recipientUserId === $actorUserId || !$this->userWants($recipientUserId, self::SETTING_NOTIFY_SETTLEMENTS)) {
					continue;
				}

				$payloads[] = [
					'userId' => $recipientUserId,
					'projectId' => $projectId,
					'link' => $link,
					'params' => [
						'projectId' => (string)$projectId,
						'projectName' => (string)$project['name'],
						'actorName' => $actorName,
						'balanceDirection' => $this->balanceDirection($balances[$recipientUserId] ?? 0),
						'balanceAmount' => $this->formatAmount(abs($balances[$recipientUserId] ?? 0)),
						'totalAmount' => $this->formatAmount($balances['_total'] ?? 0),
						'currency' => (string)($balances['_currency'] ?? 'EUR'),
					],
				];
			}

			return $payloads;
		} catch (\Throwable $e) {
			$this->logger->error('Failed to prepare CoBudget settlement notifications: ' . $e->getMessage(), ['app' => self::APP_ID]);
			return [];
		}
	}

	public function sendPreparedNotifications(array $payloads): void {
		foreach ($payloads as $payload) {
			try {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(self::APP_ID)
					->setUser((string)$payload['userId'])
					->setDateTime(new \DateTime())
					->setObject('project', (string)$payload['projectId'])
					->setSubject('project_settled', $payload['params'])
					->setLink((string)$payload['link']);

				$this->notificationManager->notify($notification);
			} catch (\Throwable $e) {
				$this->logger->error('Failed to send CoBudget settlement notification: ' . $e->getMessage(), ['app' => self::APP_ID]);
			}
		}
	}

	private function projectNotificationsEnabled(string $userId): bool {
		return $this->config->getUserValue($userId, self::APP_ID, 'enable_projects', 'yes') === 'yes'
			&& $this->config->getUserValue($userId, self::APP_ID, 'enable_shared_projects', 'yes') === 'yes';
	}

	private function userWants(string $userId, string $setting): bool {
		return $this->projectNotificationsEnabled($userId)
			&& $this->config->getUserValue($userId, self::APP_ID, $setting, 'yes') === 'yes';
	}

	private function project(int $projectId, int $workspaceId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name')
			->from('cobudget_projects')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)));

		$result = $qb->executeQuery();
		$project = $result->fetch();
		$result->closeCursor();

		return $project ?: null;
	}

	private function members(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_values(array_map(static fn(array $row): string => (string)$row['user_id'], $rows));
	}

	private function openExpenseBalances(int $projectId, int $workspaceId, array $members): array {
		$paid = [];
		$fairShare = [];
		foreach ($members as $memberId) {
			$paid[$memberId] = 0;
			$fairShare[$memberId] = 0;
		}
		$shareBasisPoints = $this->memberShareBasisPoints($projectId, $members);

		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'amount', 'amount_cents', 'currency', 'split_mode')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter('expense')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));

		$result = $qb->executeQuery();
		$entries = $result->fetchAll();
		$result->closeCursor();

		if ($entries === []) {
			return [];
		}

		$totalCents = 0;
		$currency = (string)($entries[0]['currency'] ?? 'EUR');
		foreach ($entries as $entry) {
			$amountCents = $this->amountCentsFromRow($entry);
			$userId = (string)($entry['user_id'] ?? '');
			if (isset($paid[$userId])) {
				$paid[$userId] += $amountCents;
			}
			$totalCents += $amountCents;
			$currency = (string)($entry['currency'] ?: $currency);

			if ($this->normalizeSplitMode($entry['split_mode'] ?? null) === 'single_user') {
				if (isset($fairShare[$userId])) {
					$fairShare[$userId] += $amountCents;
				}
				continue;
			}

			foreach ($this->distributeAmountCents($amountCents, $shareBasisPoints) as $memberId => $shareCents) {
				if (isset($fairShare[$memberId])) {
					$fairShare[$memberId] += $shareCents;
				}
			}
		}

		$balances = [
			'_total' => $totalCents,
			'_currency' => $currency !== '' ? $currency : 'EUR',
		];
		foreach ($members as $memberId) {
			$balances[$memberId] = (int)(($paid[$memberId] ?? 0) - ($fairShare[$memberId] ?? 0));
		}

		return $balances;
	}

	private function memberShareBasisPoints(int $projectId, array $members): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$shares = [];
		foreach ($rows as $row) {
			$userId = (string)$row['user_id'];
			if (in_array($userId, $members, true)) {
				$shares[$userId] = max(0, (int)($row['share_basis_points'] ?? 0));
			}
		}

			$total = array_sum($shares);
			$allWholePercent = true;
			foreach ($shares as $shareBasisPoints) {
				if ($shareBasisPoints % 100 !== 0) {
					$allWholePercent = false;
					break;
				}
			}

			if ($total === 10000 && $allWholePercent) {
				return $shares;
			}

			$count = max(1, count($members));
			if ($total <= 0) {
				$basePercent = intdiv(100, $count);
				$remainderPercent = 100 - ($basePercent * $count);
				$shares = [];
				foreach (array_values($members) as $index => $memberId) {
					$shares[$memberId] = ($basePercent + ($index < $remainderPercent ? 1 : 0)) * 100;
				}

				return $shares;
			}

			$percentRows = [];
			$allocatedPercent = 0;
			foreach (array_values($members) as $index => $memberId) {
				$rawPercent = (($shares[$memberId] ?? 0) / $total) * 100;
				$wholePercent = (int)floor($rawPercent);
				$allocatedPercent += $wholePercent;
				$percentRows[] = [
					'userId' => $memberId,
					'percent' => $wholePercent,
					'remainder' => $rawPercent - $wholePercent,
					'index' => $index,
				];
			}

			$remainingPercent = 100 - $allocatedPercent;
			usort($percentRows, static function(array $a, array $b): int {
				$remainderCompare = $b['remainder'] <=> $a['remainder'];
				if ($remainderCompare !== 0) {
					return $remainderCompare;
				}

				return $a['index'] <=> $b['index'];
			});

			for ($i = 0; $i < $remainingPercent && $i < count($percentRows); $i++) {
				$percentRows[$i]['percent']++;
			}

			usort($percentRows, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);

			$shares = [];
			foreach ($percentRows as $row) {
				$shares[$row['userId']] = $row['percent'] * 100;
			}

			return $shares;
	}

	private function distributeAmountCents(int $amountCents, array $shareBasisPointsByUserId): array {
		$userIds = array_keys($shareBasisPointsByUserId);
		if ($userIds === []) {
			return [];
		}

		$distributed = [];
		$allocated = 0;
		$lastIndex = count($userIds) - 1;
		foreach ($userIds as $index => $userId) {
			if ($index === $lastIndex) {
				$distributed[$userId] = $amountCents - $allocated;
				continue;
			}

			$share = (int)round($amountCents * ((int)$shareBasisPointsByUserId[$userId]) / 10000);
			$distributed[$userId] = $share;
			$allocated += $share;
		}

		return $distributed;
	}

	private function normalizeSplitMode(?string $splitMode): string {
		return $splitMode === 'single_user' ? 'single_user' : 'project_shares';
	}

	private function balanceDirection(int $balanceCents): string {
		if ($balanceCents > 0) {
			return 'receives';
		}
		if ($balanceCents < 0) {
			return 'owes';
		}
		return 'balanced';
	}

	private function displayName(string $userId): string {
		$user = $this->userManager->get($userId);
		if ($user !== null && $user->getDisplayName() !== '') {
			return $user->getDisplayName();
		}
		return $userId;
	}

	private function projectLink(int $projectId): string {
		return $this->urlGenerator->linkToRouteAbsolute('cobudget.page.index') . '#/projects/' . $projectId;
	}

	private function formatAmount(int $amountCents): string {
		return number_format($amountCents / 100, 2, ',', '.');
	}

	private function amountCentsFromRow(array $row): int {
		if (isset($row['amount_cents']) && $row['amount_cents'] !== null && $row['amount_cents'] !== '') {
			return (int)$row['amount_cents'];
		}

		$amount = trim(str_replace(',', '.', (string)($row['amount'] ?? '0')));
		$negative = str_starts_with($amount, '-');
		if ($negative) {
			$amount = substr($amount, 1);
		}
		[$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
		$whole = preg_replace('/\D/', '', $whole) ?: '0';
		$fraction = str_pad(preg_replace('/\D/', '', $fraction), 3, '0');
		$cents = ((int)$whole * 100) + (int)substr($fraction, 0, 2);
		if ((int)$fraction[2] >= 5) {
			$cents++;
		}

		return $negative ? -$cents : $cents;
	}
}

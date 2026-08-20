<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

final class DataChangeService {
	public const PUSH_EVENT = 'cobudget_data_changed';

	private const APP_ID = 'cobudget';
	private const REVISION_CONFIG_KEY = 'data_revision';
	private const DOMAINS = ['entries', 'projects', 'settlements', 'budgets', 'analytics'];

	private bool $pushQueueResolved = false;
	private ?object $pushQueue = null;

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function revisionForUser(?string $userId): string {
		$userId = trim((string)$userId);
		if ($userId === '') {
			return '';
		}

		try {
			return $this->config->getUserValue($userId, self::APP_ID, self::REVISION_CONFIG_KEY, '');
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to read CoBudget data revision: ' . $e->getMessage(), ['app' => self::APP_ID]);
			return '';
		}
	}

	public function publishForEntry(array $entry, array $domains): void {
		$projectId = $this->positiveId($entry['project_id'] ?? null);
		$entryKind = strtolower(trim((string)($entry['entry_kind'] ?? 'personal')));
		if ($projectId !== null && $entryKind === 'shared') {
			$this->publishForProject($projectId, $domains);
			return;
		}

		$this->publishForUsers([
			(string)($entry['user_id'] ?? ''),
		], $domains, $projectId);
	}

	public function publishForEntryChange(array $before, array $after, array $domains): void {
		try {
			$beforeProjectId = $this->positiveId($before['project_id'] ?? null);
			$afterProjectId = $this->positiveId($after['project_id'] ?? null);
			$beforeKind = strtolower(trim((string)($before['entry_kind'] ?? 'personal')));
			$afterKind = strtolower(trim((string)($after['entry_kind'] ?? 'personal')));
			if ($beforeProjectId !== null && $beforeProjectId === $afterProjectId && $beforeKind === 'shared' && $afterKind === 'shared') {
				$this->publishForProject($afterProjectId, $domains);
				return;
			}

			$this->publishForUsers(
				array_merge(
					$this->userIdsForEntry($before),
					$this->userIdsForEntry($after),
				),
				$domains,
				$beforeProjectId === $afterProjectId ? $afterProjectId : null,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to prepare CoBudget entry data change: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}

	public function publishForProject(int $projectId, array $domains, array $additionalUserIds = []): void {
		if ($projectId <= 0) {
			return;
		}

		try {
			$this->publishForUsers(
				array_merge($this->projectMemberUserIds($projectId), $additionalUserIds),
				$domains,
				$projectId,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to prepare CoBudget project data change: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}

	public function publishForUsers(array $userIds, array $domains, ?int $projectId = null): void {
		try {
			$userIds = $this->normalizeUserIds($userIds);
			$domains = $this->normalizeDomains($domains);
			if ($userIds === [] || $domains === []) {
				return;
			}

			$revision = bin2hex(random_bytes(16));
			foreach ($userIds as $userId) {
				$this->config->setUserValue($userId, self::APP_ID, self::REVISION_CONFIG_KEY, $revision);
			}

			$body = [
				'revision' => $revision,
				'domains' => $domains,
			];
			if ($projectId !== null && $projectId > 0) {
				$body['projectId'] = $projectId;
			}

			$queue = $this->pushQueue();
			if ($queue === null) {
				return;
			}

			foreach ($userIds as $userId) {
				$queue->push('notify_custom', [
					'user' => $userId,
					'message' => self::PUSH_EVENT,
					'body' => $body,
				]);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to publish CoBudget data change: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}

	private function projectMemberUserIds(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$userIds = array_column($result->fetchAll(), 'user_id');
		$result->closeCursor();

		return $this->normalizeUserIds($userIds);
	}

	private function userIdsForEntry(array $entry): array {
		$projectId = $this->positiveId($entry['project_id'] ?? null);
		$entryKind = strtolower(trim((string)($entry['entry_kind'] ?? 'personal')));
		if ($projectId !== null && $entryKind === 'shared') {
			return $this->projectMemberUserIds($projectId);
		}

		return $this->normalizeUserIds([(string)($entry['user_id'] ?? '')]);
	}

	private function pushQueue(): ?object {
		if ($this->pushQueueResolved) {
			return $this->pushQueue;
		}
		$this->pushQueueResolved = true;

		if (!interface_exists('OCA\\NotifyPush\\IQueue') || !class_exists(\OC::class) || !isset(\OC::$server)) {
			return null;
		}

		try {
			$queue = \OC::$server->get(\OCA\NotifyPush\IQueue::class);
			if (is_object($queue) && method_exists($queue, 'push')) {
				$this->pushQueue = $queue;
			}
		} catch (\Throwable $e) {
			$this->logger->debug('CoBudget notify_push integration is unavailable: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}

		return $this->pushQueue;
	}

	private function normalizeUserIds(array $userIds): array {
		$normalized = [];
		foreach ($userIds as $userId) {
			$userId = trim((string)$userId);
			if ($userId === '' || str_starts_with($userId, ParticipantService::FORMER_PREFIX)) {
				continue;
			}
			$normalized[$userId] = true;
		}

		return array_keys($normalized);
	}

	private function normalizeDomains(array $domains): array {
		$normalized = [];
		foreach ($domains as $domain) {
			$domain = trim((string)$domain);
			if (in_array($domain, self::DOMAINS, true)) {
				$normalized[$domain] = true;
			}
		}

		return array_keys($normalized);
	}

	private function positiveId(mixed $value): ?int {
		if (!is_numeric($value) || (int)$value <= 0) {
			return null;
		}

		return (int)$value;
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IDBConnection;
use OCP\IUserManager;

class ParticipantService {
	public const FORMER_PREFIX = 'former:';

	/** @var array<string, array{displayName: string, isFormer: bool, isActive: bool}> */
	private array $cache = [];

	public function __construct(
		private IDBConnection $db,
		private IUserManager $userManager,
	) {
	}

	public static function isReservedFormerId(string $userId): bool {
		return str_starts_with(trim($userId), self::FORMER_PREFIX);
	}

	/** @return array{displayName: string, isFormer: bool, isActive: bool} */
	public function participant(string $userId): array {
		$userId = trim($userId);
		if ($userId === '') {
			return ['displayName' => '', 'isFormer' => false, 'isActive' => false];
		}
		if (isset($this->cache[$userId])) {
			return $this->cache[$userId];
		}

		if (self::isReservedFormerId($userId)) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('display_name')
				->from('cobudget_deleted_users')
				->where($qb->expr()->eq('tombstone_id', $qb->createNamedParameter($userId)))
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();
			return $this->cache[$userId] = [
				'displayName' => trim((string)($row['display_name'] ?? '')) ?: 'Former member',
				'isFormer' => true,
				'isActive' => false,
			];
		}

		$user = $this->userManager->get($userId);
		$isActive = $user !== null && (!method_exists($user, 'isEnabled') || $user->isEnabled());
		return $this->cache[$userId] = [
			'displayName' => $user !== null && trim($user->getDisplayName()) !== '' ? $user->getDisplayName() : $userId,
			'isFormer' => false,
			'isActive' => $isActive,
		];
	}

	public function displayName(string $userId): string {
		return $this->participant($userId)['displayName'];
	}

	public function isFormer(string $userId): bool {
		return $this->participant($userId)['isFormer'];
	}

	public function isActive(string $userId): bool {
		return $this->participant($userId)['isActive'];
	}

	public function createFormerParticipant(string $displayName): string {
		$displayName = trim($displayName) ?: 'Former member';
		do {
			$tombstoneId = self::FORMER_PREFIX . bin2hex(random_bytes(16));
		} while ($this->tombstoneExists($tombstoneId));

		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_deleted_users')
			->values([
				'tombstone_id' => $qb->createNamedParameter($tombstoneId),
				'display_name' => $qb->createNamedParameter($displayName),
				'deleted_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
			]);
		$qb->executeStatement();

		$this->cache[$tombstoneId] = [
			'displayName' => $displayName,
			'isFormer' => true,
			'isActive' => false,
		];
		return $tombstoneId;
	}

	private function tombstoneExists(string $tombstoneId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_deleted_users')
			->where($qb->expr()->eq('tombstone_id', $qb->createNamedParameter($tombstoneId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();
		return $exists;
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

final class EntryShareCalculator {

	/**
	 * @param array<string, int> $shareBasisPointsByUserId Ordered user/share map.
	 * @return array<string, array{share_basis_points: int, amount_cents: int}>
	 */
	public static function calculate(
		int $amountCents,
		string $splitMode,
		?string $splitUserId,
		string $payerUserId,
		array $shareBasisPointsByUserId,
	): array {
		$amountCents = max(0, $amountCents);
		$splitMode = trim($splitMode) === 'single_user' ? 'single_user' : 'project_shares';
		if ($splitMode === 'single_user') {
			$targetUserId = trim((string)$splitUserId);
			if ($targetUserId === '') {
				$targetUserId = trim($payerUserId);
			}

			return $targetUserId === '' ? [] : [
				$targetUserId => [
					'share_basis_points' => 10000,
					'amount_cents' => $amountCents,
				],
			];
		}

		$shares = [];
		foreach ($shareBasisPointsByUserId as $userId => $shareBasisPoints) {
			$userId = trim((string)$userId);
			if ($userId === '') {
				continue;
			}
			$shares[$userId] = max(0, (int)$shareBasisPoints);
		}
		if ($shares === []) {
			$payerUserId = trim($payerUserId);
			return $payerUserId === '' ? [] : [
				$payerUserId => [
					'share_basis_points' => 10000,
					'amount_cents' => $amountCents,
				],
			];
		}

		$totalShare = array_sum($shares);
		if ($totalShare <= 0) {
			$equalShare = 1;
			foreach (array_keys($shares) as $userId) {
				$shares[$userId] = $equalShare;
			}
			$totalShare = array_sum($shares);
		}
		$shares = self::normalizeShares($shares, $totalShare);
		$totalShare = 10000;

		$result = [];
		$allocatedCents = 0;
		$userIds = array_keys($shares);
		$lastIndex = count($userIds) - 1;
		foreach ($userIds as $index => $userId) {
			$shareCents = $index === $lastIndex
				? $amountCents - $allocatedCents
				: self::roundHalfUpShare($amountCents, $shares[$userId], $totalShare);
			$shareCents = max(0, min($amountCents - $allocatedCents, $shareCents));
			$result[$userId] = [
				'share_basis_points' => $shares[$userId],
				'amount_cents' => $shareCents,
			];
			$allocatedCents += $shareCents;
		}

		return $result;
	}

	/** @param array<string, int> $shares */
	private static function normalizeShares(array $shares, int $totalShare): array {
		$normalized = [];
		$allocated = 0;
		$userIds = array_keys($shares);
		$lastIndex = count($userIds) - 1;
		foreach ($userIds as $index => $userId) {
			$basisPoints = $index === $lastIndex
				? 10000 - $allocated
				: self::roundHalfUpShare(10000, $shares[$userId], $totalShare);
			$basisPoints = max(0, min(10000 - $allocated, $basisPoints));
			$normalized[$userId] = $basisPoints;
			$allocated += $basisPoints;
		}

		return $normalized;
	}

	private static function roundHalfUpShare(int $amountCents, int $share, int $totalShare): int {
		if ($amountCents <= 0 || $share <= 0 || $totalShare <= 0) {
			return 0;
		}

		return intdiv(($amountCents * $share * 2) + $totalShare, $totalShare * 2);
	}
}

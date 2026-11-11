<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Display identities only. Never grants access to payments or mutates master data. */
class ReportLookupService {
	public static function key(string $dimension, int $id, string $name, string $parentName = ''): string {
		if ($id === 0 || trim($name) === '') return (string)$id;
		$normalize = static function(string $value): string {
			$value = trim((string)preg_replace('/\s+/u', ' ', $value));
			return mb_strtolower($value, 'UTF-8');
		};
		return 'name:' . hash('sha256', json_encode([$dimension, $normalize($parentName), $normalize($name)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
	}

	public static function isKey(string $id): bool {
		return preg_match('/^(?:name:[a-f0-9]{64}|0)(?::area:(?:0|[1-9][0-9]{0,15}))?$/D', $id) === 1;
	}

	public static function split(string $id): array {
		$parts = explode(':area:', $id, 2);
		return [$parts[0], isset($parts[1]) ? (int)$parts[1] : null];
	}

	public static function matches(array $payment, string $dimension, string $id, bool $direct = false): bool {
		[$key, $area] = self::split($id);
		if ($area !== null && (int)($payment['project_id'] ?? 0) !== $area) return false;
		$category = $dimension === 'categories';
		$lookupId = (int)($category ? ($payment['category_id'] ?? 0) : ($payment['partner_id'] ?? $payment['payment_partner_id'] ?? 0));
		$parentId = $category ? (int)($payment['parent_id'] ?? 0) : 0;
		if (!str_starts_with($key, 'name:')) return $lookupId === (int)$key || ($category && !$direct && (int)$key > 0 && $parentId === (int)$key);
		$name = (string)($payment[$category ? 'category_name' : 'partner_name'] ?? '');
		$parentName = $parentId ? (string)($payment['parent_name'] ?? '') : '';
		return self::key($dimension, $lookupId, $name, $parentName) === $key
			|| ($category && !$direct && $parentId > 0 && self::key($dimension, $parentId, $parentName) === $key);
	}

	public static function matchesAggregate(array $row, string $dimension, string $id, bool $direct): bool {
		return self::matches($row + [
			$dimension === 'categories' ? 'category_id' : 'partner_id' => $row['group_id'] ?? 0,
			$dimension === 'categories' ? 'category_name' : 'partner_name' => $row['group_name'] ?? '',
		], $dimension, $id, $direct);
	}
}

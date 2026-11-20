<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** File references belong to the same delivery as the localized result text. */
final class AutoImportResult {
	public static function pack(string $message, ?string $name, ?int $fileId): string {
		if ($name === null || $fileId === null || $fileId < 1) return $message;
		return json_encode(['message' => $message, 'file' => ['name' => $name, 'id' => $fileId]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	}

	public static function unpack(?string $stored): array {
		$result = json_decode($stored ?? '', true);
		if (is_array($result) && is_string($result['message'] ?? null)
			&& is_string($result['file']['name'] ?? null) && is_int($result['file']['id'] ?? null) && $result['file']['id'] > 0) {
			return $result;
		}
		return ['message' => (string)$stored, 'file' => null];
	}
}

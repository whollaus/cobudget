<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

final class CsvCellSanitizer {

	public static function sanitize(string $value): string {
		if ($value === '') {
			return '';
		}

		return preg_match('/^(?:[\t\r\n]|[\x00-\x20]*[=+\-@])/', $value) === 1
			? "'" . $value
			: $value;
	}
}

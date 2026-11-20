<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Keep fractional cents intact for row validation, even when a JSON number would round to an exact cent. */
final class PaymentImportJson {
	public static function decode(string $json, bool $associative, int $depth): mixed {
		if (preg_match('/\.\d{3}|[eE]-\d/', $json)) {
			// Consume whole string tokens so text in descriptions cannot be mistaken for an amount.
			$tokens = <<<'REGEX'
~("(?:[^"\\]++|\\.)*+")(?:([ \t\r\n]*:[ \t\r\n]*)(-?(0|[1-9]\d*)(?:\.(\d+))?(?:[eE]([+-]?\d+))?))?~
REGEX;
			$json = preg_replace_callback($tokens, static function (array $token): string {
				if (!isset($token[3]) || ($token[1] !== '"amount"' && json_decode($token[1]) !== 'amount')) return $token[0];
				$fraction = $token[5] ?? '';
				$digits = $token[4] . $fraction;
				$trailingZeros = strlen($digits) - strlen(rtrim($digits, '0'));
				if (strlen($fraction) - $trailingZeros - (int)($token[6] ?? 0) <= 2) return $token[0];
				return $token[1] . $token[2] . '"' . $token[3] . '"';
			}, $json) ?? throw new \JsonException('Invalid import JSON.');
		}
		return json_decode($json, $associative, $depth, JSON_THROW_ON_ERROR);
	}
}

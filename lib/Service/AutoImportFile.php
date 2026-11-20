<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\Files\NotPermittedException;

/** Validate the JSON envelope before writing and decode at most one payment packet at a time. */
final class AutoImportFile {
	public const MAX_BYTES = 5 * 1024 * 1024;
	private const MAX_ROWS = 5000;
	private const SPACE = " \r\n\t";

	public readonly string $digest;
	public readonly string $contentHash;
	public readonly int $count;

	private function __construct(private readonly string $json) {
		$hash = hash_init('sha256');
		$canonical = hash_init('sha256');
		hash_update($hash, '[');
		hash_update($canonical, '[');
		$count = 0;
		foreach ($this->rows() as $row) {
			if (++$count > self::MAX_ROWS) {
				throw new \DomainException('A file can contain at most 5,000 payments.');
			}
			if ($count > 1) {
				hash_update($hash, ',');
				hash_update($canonical, ',');
			}
			$flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS;
			hash_update($hash, json_encode((object)$row, $flags));
			ksort($row, SORT_STRING);
			hash_update($canonical, json_encode((object)$row, $flags));
		}
		if ($count === 0) throw new \DomainException('The "payments" array is empty.');
		hash_update($hash, ']');
		hash_update($canonical, ']');
		$this->contentHash = hash_final($canonical);
		$this->digest = hash_final($hash);
		$this->count = $count;
	}

	/** Include the parser's first excess byte so even oversized files have a bounded fingerprint. */
	public static function fingerprintAndClose($stream, ?bool &$complete = null): string {
		if (!is_resource($stream)) throw new NotPermittedException('Unable to open import stream');
		try {
			$hash = hash_init('sha256');
			$limit = self::MAX_BYTES + 1;
			$complete = hash_update_stream($hash, $stream, $limit) < $limit;
			if ($complete && !feof($stream)) {
				throw new \RuntimeException('Could not read the complete import file.');
			}
			return hash_final($hash);
		} finally {
			fclose($stream);
		}
	}

	/** The caller owns and closes the Files stream. */
	public static function fromStream($stream, ?string $expectedHash = null, ?string &$rawHash = null): ?self {
		$json = stream_get_contents($stream, self::MAX_BYTES + 1);
		if ($json === false || (strlen($json) < self::MAX_BYTES + 1 && !feof($stream))) {
			throw new \RuntimeException('Could not read the complete import file.');
		}
		$rawHash = hash('sha256', $json);
		if ($expectedHash !== null && $rawHash !== $expectedHash) return null;
		if (strlen($json) > self::MAX_BYTES) throw new \DomainException('The payment data exceeds the 5 MB limit.');
		if (str_starts_with($json, "\xEF\xBB\xBF")) $json = substr($json, 3);
		return new self($json);
	}

	public function snapshot(): string {
		return $this->json;
	}

	public static function fromSnapshot(string $json): self {
		if (strlen($json) > self::MAX_BYTES) throw new \DomainException('The payment data exceeds the 5 MB limit.');
		return new self($json);
	}

	public function packets(): \Generator {
		$packet = [];
		$offset = 0;
		foreach ($this->rows() as $row) {
			$packet[] = $row;
			if (count($packet) === 50) {
				yield $offset => $packet;
				$offset += count($packet);
				$packet = [];
			}
		}
		if ($packet !== []) yield $offset => $packet;
	}

	private function skipSpace(int &$pos): void {
		$pos += strspn($this->json, self::SPACE, $pos);
	}

	private function take(int &$pos, string $token): bool {
		$this->skipSpace($pos);
		if (($this->json[$pos] ?? '') !== $token) return false;
		$pos++;
		return true;
	}

	private function decodeValue(int &$pos): mixed {
		$this->skipSpace($pos);
		$end = $this->valueEnd($pos);
		// Bound even an invalid row or an unused envelope property before materializing it.
		if ($end - $pos > 196608) throw new \DomainException('Invalid payment in the import file.');
		try {
			$value = PaymentImportJson::decode(substr($this->json, $pos, $end - $pos), false, 32);
		} catch (\JsonException) {
			throw new \DomainException('The payment data must be valid JSON.');
		}
		$pos = $end;
		return $value;
	}

	private function rows(): \Generator {
		$pos = 0;
		$found = false;
		if (!$this->take($pos, '{')) throw new \DomainException('The import data must contain a "payments" array.');
		do {
			$key = $this->decodeValue($pos);
			if (!is_string($key) || !$this->take($pos, ':')) throw new \DomainException('The payment data must be valid JSON.');
			if ($key !== 'payments') {
				$this->decodeValue($pos);
				continue;
			}
			if ($found || !$this->take($pos, '[')) throw new \DomainException('The import data must contain a "payments" array.');
			$found = true;
			if ($this->take($pos, ']')) continue;
			do {
				$row = $this->decodeValue($pos);
				if (!$row instanceof \stdClass || count((array)$row) > 32) throw new \DomainException('Invalid payment in the import file.');
				foreach ($row as $value) {
					if (is_array($value) || is_object($value) || (is_float($value) && !is_finite($value))) {
						throw new \DomainException('Invalid payment in the import file.');
					}
				}
				yield (array)$row;
			} while ($this->take($pos, ','));
			if (!$this->take($pos, ']')) throw new \DomainException('The payment data must be valid JSON.');
		} while ($this->take($pos, ','));
		if (!$this->take($pos, '}')) throw new \DomainException('The payment data must be valid JSON.');
		$this->skipSpace($pos);
		if ($pos !== strlen($this->json)) throw new \DomainException('The payment data must be valid JSON.');
		if (!$found) throw new \DomainException('The import data must contain a "payments" array.');
	}

	// Locate a complete JSON value without allocating its contents; decodeValue validates its syntax.
	private function stringEnd(int $start): int {
		$pos = $start + 1;
		while ($pos < strlen($this->json)) {
			$pos += strcspn($this->json, '"\\', $pos);
			if (($this->json[$pos] ?? '') === '"') return $pos + 1;
			$pos += 2;
		}
		throw new \DomainException('The payment data must be valid JSON.');
	}

	private function valueEnd(int $pos): int {
		$first = $this->json[$pos] ?? '';
		if ($first === '"') return $this->stringEnd($pos);
		if ($first !== '[' && $first !== '{') return $pos + strcspn($this->json, ",]}" . self::SPACE, $pos);
		$depth = 1;
		$pos++;
		while ($depth > 0) {
			$pos += strcspn($this->json, '"{}[]', $pos);
			$char = $this->json[$pos] ?? '';
			if ($char === '') throw new \DomainException('The payment data must be valid JSON.');
			if ($char === '"') {
				$pos = $this->stringEnd($pos);
			} else {
				$depth += ($char === '[' || $char === '{') ? 1 : -1;
				$pos++;
			}
		}
		return $pos;
	}
}

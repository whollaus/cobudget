<?php

declare(strict_types=1);

namespace OCA\CoBudget\Exception;

/**
 * Structured HTTP error raised inside API v1 services for consistent JSON error responses.
 */
final class ApiV1HttpException extends \RuntimeException {
	/** @param array<string, string> $headers */
	public function __construct(
		private int $statusCode,
		string $message,
		private array $headers = [],
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/** @return array<string, string> */
	public function getHeaders(): array {
		return $this->headers;
	}
}

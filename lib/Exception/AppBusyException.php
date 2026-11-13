<?php

declare(strict_types=1);

namespace OCA\CoBudget\Exception;

final class AppBusyException extends \RuntimeException {
	public const MESSAGE = 'CoBudget is temporarily busy with a backup, restore or another operation. Please try again shortly.';

	public function __construct(?\Throwable $previous = null) {
		parent::__construct(self::MESSAGE, 0, $previous);
	}
}

<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

final class AutoImportConflict extends \DomainException {
	public function __construct(public readonly int $booked, public readonly ?string $originalName) {
		parent::__construct('This filename was already used for different payments.');
	}
}

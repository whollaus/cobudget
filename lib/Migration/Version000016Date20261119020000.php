<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCA\CoBudget\Service\AutoImportRules;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000016Date20261119020000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		AutoImportRules::reservePausedFolders($this->db);
	}
}

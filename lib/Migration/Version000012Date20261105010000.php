<?php
declare(strict_types=1);
namespace OCA\CoBudget\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
class Version000012Date20261105010000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  $table = $schema->getTable('cobudget_calendar_links');
  if (!$table->hasColumn('sync_state')) $table->addColumn('sync_state', 'text', ['notnull'=>false]);
  return $schema;
 }
}

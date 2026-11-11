<?php
declare(strict_types=1);

/** SQLite ignores VARCHAR lengths. Model Nextcloud's file_locks.key limit in rollback-only tests. */
function enforceNativeLockKeyLength(\OCP\IDBConnection $db, \OCP\IConfig $config): void {
	if ($config->getSystemValue('dbtype') !== 'sqlite3') return;
	$table = $db->getDatabasePlatform()->quoteIdentifier($config->getSystemValue('dbtableprefix', 'oc_') . 'file_locks');
	$db->executeStatement("CREATE TEMP TRIGGER cobudget_test_lock_key_limit BEFORE INSERT ON $table "
		. "WHEN LENGTH(NEW.key) > 64 BEGIN SELECT RAISE(ABORT, 'Lock key exceeds Nextcloud VARCHAR(64)'); END");
}

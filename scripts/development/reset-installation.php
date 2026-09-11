<?php

declare(strict_types=1);

// Standalone because Nextcloud does not load an app's OCC commands in maintenance mode.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$options = getopt('', ['nextcloud:', 'confirm:', 'help']);
if (isset($options['help'])) {
	fwrite(STDOUT, "CoBudget-Installations-Reset (PostgreSQL/SQLite)\n\n"
		. "Vorschau: php reset-installation.php --nextcloud=/var/www/html\n"
		. "Löschen:  gleicher Befehl mit --confirm=DELETE-COBUDGET-INSTALLATION\n\n"
		. "Löscht CoBudget-Tabellen samt Daten aller Benutzer, Einstellungen, Migrationseinträge und CoBudget-Jobs.\n"
		. "App-Code, Beleg-/Backup-Dateien und Daten anderer Apps bleiben erhalten.\n"
		. "Vor dem Löschen: Backup erstellen, CoBudget deaktivieren und Nextcloud-Wartungsmodus einschalten.\n");
	exit(0);
}

try {
	$confirmation = $options['confirm'] ?? null;
	if ($confirmation !== null && $confirmation !== 'DELETE-COBUDGET-INSTALLATION') {
		throw new RuntimeException('Ungültige Bestätigung. Erwartet: --confirm=DELETE-COBUDGET-INSTALLATION');
	}
	$root = realpath($options['nextcloud'] ?? dirname(__DIR__, 4));
	if ($root === false || !is_file($root . '/occ') || !is_file($root . '/lib/base.php')) {
		throw new RuntimeException('Nextcloud-Verzeichnis fehlt. --nextcloud=/var/www/html angeben.');
	}
	define('OC_CONSOLE', true);
	chdir($root);
	require_once $root . '/lib/base.php';
	if (!function_exists('posix_getuid') || posix_getuid() !== fileowner(\OC::$configDir . 'config.php')) {
		throw new RuntimeException('Mit dem Eigentümer der Nextcloud-config.php ausführen, bei AIO: docker exec -u www-data ...');
	}

	// Explicit loading also works while CoBudget is disabled, without booting the app.
	require_once __DIR__ . '/InstallationResetService.php';
	$reset = new \OCA\CoBudget\Service\InstallationResetService(
		\OCP\Server::get(\OCP\IDBConnection::class),
		\OCP\Server::get(\OCP\IConfig::class),
		\OCP\Server::get(\OCP\IAppConfig::class),
	);
	$report = $confirmation === null ? $reset->preview() : $reset->reset($confirmation);
	fwrite(STDOUT, $confirmation === null ? "VORSCHAU – keine Daten gelöscht.\n" : "CoBudget-Installationszustand gelöscht.\n");
	fwrite(STDOUT, 'Datenbank: ' . $report['database'] . "\n");
	foreach ($report['tables'] as $table => $count) {
		fwrite(STDOUT, sprintf("  %s%s: %d Zeilen\n", $report['prefix'], $table, $count));
	}
	foreach ($report['metadata'] as $table => $count) {
		fwrite(STDOUT, sprintf("  %s%s: %d CoBudget-Einträge\n", $report['prefix'], $table, $count));
	}
	if ($confirmation === null) {
		fwrite(STDOUT, "\nZum Löschen aller CoBudget-Daten: App deaktivieren, Wartungsmodus einschalten und mit --confirm=DELETE-COBUDGET-INSTALLATION wiederholen.\n");
	} else {
		fwrite(STDOUT, "\nApp-Code und Dateien in Nextcloud Files wurden nicht gelöscht.\n"
			. "Nach erfolgreichem Reset: occ maintenance:mode --off, danach occ app:enable cobudget.\n");
	}
	exit(0);
} catch (Throwable $e) {
	fwrite(STDERR, 'Installations-Reset fehlgeschlagen: ' . $e->getMessage() . "\n");
	exit(1);
}

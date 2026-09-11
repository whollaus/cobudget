<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;

/** Development-only reset for a fresh-install test, without touching Files. */
final class InstallationResetService {
	public const CONFIRMATION = 'DELETE-COBUDGET-INSTALLATION';
	private const APP_ID = 'cobudget';
	private const JOB_NAMESPACE = 'OCA\\CoBudget\\';
	private const METADATA = ['preferences' => 'appid', 'migrations' => 'app', 'appconfig' => 'appid'];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IAppConfig $appConfig,
	) {
	}

	public function preview(): array {
		$prefix = (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
		$tables = [];
		foreach ($this->db->createSchema()->getTables() as $table) {
			$name = $table->getName();
			if (str_starts_with($name, $prefix . 'cobudget_')) {
				$logicalName = substr($name, strlen($prefix));
				if (!preg_match('/^cobudget_[a-z0-9_]+$/D', $logicalName)) {
					throw new \RuntimeException('Unerwarteter CoBudget-Tabellenname: ' . $name);
				}
				$qb = $this->db->getQueryBuilder();
				$qb->from($logicalName);
				$tables[$logicalName] = $this->count($qb);
			}
		}
		ksort($tables);

		$metadata = [];
		foreach (self::METADATA as $table => $column) {
			$metadata[$table] = $this->count($this->metadataQuery($table, $column));
		}
		$metadata['jobs'] = $this->count($this->jobQuery());

		return [
			'database' => $this->db->getDatabaseProvider(),
			'prefix' => $prefix,
			'tables' => $tables,
			'metadata' => $metadata,
		];
	}

	public function reset(string $confirmation): array {
		if ($confirmation !== self::CONFIRMATION) {
			throw new \InvalidArgumentException('Zum Löschen ist die exakte Bestätigung ' . self::CONFIRMATION . ' erforderlich.');
		}
		if (!$this->config->getSystemValueBool('maintenance', false)) {
			throw new \RuntimeException('Zuerst den Nextcloud-Wartungsmodus einschalten: occ maintenance:mode --on');
		}
		if (!in_array($this->appConfig->getValueString(self::APP_ID, 'enabled', 'no'), ['', 'no'], true)) {
			throw new \RuntimeException('Zuerst CoBudget deaktivieren: occ app:disable cobudget');
		}
		// Only platforms with transactional DROP TABLE are accepted by this test tool.
		if (!in_array($this->db->getDatabaseProvider(), ['postgres', 'sqlite'], true)) {
			throw new \RuntimeException('Dieser Installations-Reset unterstützt PostgreSQL und SQLite. Es wurde nichts gelöscht.');
		}
		if ($this->db->inTransaction()) {
			throw new \RuntimeException('Der Installations-Reset benötigt eine eigene Datenbanktransaktion.');
		}

		$this->db->beginTransaction();
		try {
			// Prevent a cron process from reserving these rows between inspection and deletion.
			$jobs = $this->jobQuery()->select('id', 'reserved_at');
			if ($this->db->getDatabaseProvider() === 'postgres') {
				$jobs->forUpdate();
			}
			$result = $jobs->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			foreach ($rows as $row) {
				if ((int)$row['reserved_at'] !== 0) {
					throw new \RuntimeException('Ein CoBudget-Job ist noch reserviert (ID ' . $row['id'] . '). Lauf beenden lassen und erneut versuchen.');
				}
			}

			$report = $this->preview();
			$this->jobQuery()->delete('jobs')->executeStatement();
			foreach (array_keys($report['tables']) as $table) {
				// Nextcloud applies its configured prefix. No CASCADE and no wildcard DDL.
				$this->db->dropTable($table);
			}
			foreach (['preferences', 'migrations'] as $table) {
				$this->metadataQuery($table, self::METADATA[$table])->delete($table)->executeStatement();
			}
			// Also removes installed_version/enabled and invalidates Nextcloud's app-config cache.
			$this->appConfig->deleteApp(self::APP_ID);
			$this->db->commit();
			return $report;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function metadataQuery(string $table, string $column): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		return $qb->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter(self::APP_ID)));
	}

	private function jobQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		return $qb->from('jobs')
			->where($qb->expr()->like('class', $qb->createNamedParameter($this->db->escapeLikeParameter(self::JOB_NAMESPACE) . '%')));
	}

	private function count(IQueryBuilder $qb): int {
		$qb->select($qb->createFunction('COUNT(*) AS row_count'));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)$row['row_count'];
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResetAllCommand extends Command {
	private const CONFIRMATION_TEXT = 'RESET-COBUDGET';
	private const APP_ID = 'cobudget';

	private const TABLES = [
		'cobudget_workspaces',
		'cobudget_projects',
		'cobudget_members',
		'cobudget_categories',
		'cobudget_payment_partners',
		'cobudget_templates',
		'cobudget_entries',
		'cobudget_entry_attachments',
		'cobudget_settlements',
		'cobudget_settlement_balances',
		'cobudget_settlement_transfers',
		'cobudget_budget_goals',
		'cobudget_budget_snapshots',
	];

	private const APP_VALUE_KEYS_TO_RESET = [
		'default_categories_seeded',
		'default_payment_partners_seeded',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:reset-all')
			->setDescription('Loescht alle CoBudget-Datenbankdaten und setzt CoBudget-Einstellungen global zurueck.')
			->addOption(
				'confirm',
				null,
				InputOption::VALUE_REQUIRED,
				'Zum Ausfuehren exakt "' . self::CONFIRMATION_TEXT . '" angeben.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ((string)$input->getOption('confirm') !== self::CONFIRMATION_TEXT) {
			$output->writeln('<error>Dieser Befehl loescht ALLE CoBudget-Daten fuer ALLE Benutzer.</error>');
			$output->writeln('<comment>Auch offene Bereiche, Zahlungen, Workspaces, Budgets und Abrechnungen werden geloescht.</comment>');
			$output->writeln('<comment>Beleg- und Backup-Dateien in Nextcloud Files bleiben unveraendert.</comment>');
			$output->writeln('');
			$output->writeln('<info>Zum Ausfuehren:</info>');
			$output->writeln('  occ cobudget:reset-all --confirm=' . self::CONFIRMATION_TEXT);

			return Command::FAILURE;
		}

		$tableCounts = [];
		$preferencesCount = 0;
		$appValuesReset = 0;

		$this->db->beginTransaction();
		try {
			foreach (array_reverse(self::TABLES) as $table) {
				$tableCounts[$table] = $this->countRows($table);
				$this->deleteRows($table);
			}

			$preferencesCount = $this->countCoBudgetPreferences();
			$this->deleteCoBudgetPreferences();

			foreach (self::APP_VALUE_KEYS_TO_RESET as $key) {
				if ($this->config->getAppValue(self::APP_ID, $key, '') !== '') {
					$appValuesReset++;
				}
				$this->config->deleteAppValue(self::APP_ID, $key);
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$output->writeln('<error>CoBudget-Reset fehlgeschlagen. Alle Aenderungen wurden zurueckgerollt.</error>');
			$output->writeln('<error>' . $e->getMessage() . '</error>');

			return Command::FAILURE;
		}

		$totalRows = array_sum($tableCounts);
		$output->writeln('<info>CoBudget wurde zurueckgesetzt.</info>');
		$output->writeln(sprintf('Geloeschte Tabellenzeilen: %d', $totalRows));
		foreach (array_reverse($tableCounts, true) as $table => $count) {
			$output->writeln(sprintf(' - %s: %d', $table, $count));
		}
		$output->writeln(sprintf('Geloeschte CoBudget-Benutzereinstellungen: %d', $preferencesCount));
		$output->writeln(sprintf('Zurueckgesetzte App-Seed-Marker: %d', $appValuesReset));
		$output->writeln('<comment>Globale Standard-Kategorien und Zahlungspartner werden beim naechsten Aufruf der jeweiligen Einstellungen neu angelegt.</comment>');

		return Command::SUCCESS;
	}

	private function countRows(string $table): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS row_count'))
			->from($table);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['row_count'] ?? 0);
	}

	private function deleteRows(string $table): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table);
		$qb->executeStatement();
	}

	private function countCoBudgetPreferences(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS row_count'))
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['row_count'] ?? 0);
	}

	private function deleteCoBudgetPreferences(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)));
		$qb->executeStatement();
	}
}

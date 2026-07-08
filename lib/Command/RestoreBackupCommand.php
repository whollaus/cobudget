<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCA\CoBudget\Service\BackupService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreBackupCommand extends Command {
	public function __construct(
		private IUserManager $userManager,
		private BackupService $backupService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:backup:restore')
			->setDescription('Stellt einen persoenlichen CoBudget-Export in einen leeren Benutzerstand wieder her.')
			->addArgument('user', InputArgument::REQUIRED, 'Nextcloud Benutzer-ID')
			->addArgument('file', InputArgument::REQUIRED, 'Export-Dateiname')
			->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Optionaler Export-Ordner in Nextcloud Files')
			->addOption('force', null, InputOption::VALUE_NONE, 'Bestaetigt den persoenlichen Import ausdruecklich');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('user');
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			$output->writeln('<error>Benutzer wurde nicht gefunden.</error>');
			return self::FAILURE;
		}

		if (!$input->getOption('force')) {
			$output->writeln('<comment>Persoenlicher Import ersetzt einen leeren CoBudget-Stand des Zielbenutzers.</comment>');
			$output->writeln('<comment>Vorher wird automatisch ein Sicherheitsexport erstellt.</comment>');
			$output->writeln('<comment>Bestehende CoBudget-Daten blockieren den Import.</comment>');
			$output->writeln('<comment>Noch einmal mit --force ausfuehren, um das ausdruecklich zu bestaetigen.</comment>');
			return self::FAILURE;
		}

		$fileName = (string)$input->getArgument('file');
		$folder = trim((string)($input->getOption('folder') ?? ''));

		try {
			$result = $this->backupService->restoreBackup($userId, $fileName, $folder !== '' ? $folder : null);
			$output->writeln('<info>Persoenlicher Export wurde wiederhergestellt.</info>');
			$safetyBackup = (string)($result['safety_backup']['file_name'] ?? '');
			if ($safetyBackup !== '') {
				$output->writeln('Sicherheitsexport: ' . $safetyBackup);
			}
			$importedTables = $result['report']['imported_tables'] ?? [];
			if (is_array($importedTables)) {
				foreach ($importedTables as $row) {
					if (!is_array($row)) {
						continue;
					}
					$output->writeln(sprintf(
						' - %s: %d',
						(string)($row['table'] ?? ''),
						(int)($row['rows'] ?? 0)
					));
				}
			}
			return self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Persoenlicher Export konnte nicht wiederhergestellt werden: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

	}
}

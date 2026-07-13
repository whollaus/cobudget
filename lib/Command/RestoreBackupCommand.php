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
		$userId = trim((string)$input->getArgument('user'));
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

		$fileName = trim((string)$input->getArgument('file'));
		if ($fileName === '') {
			$output->writeln('<error>Bitte Export-Datei angeben.</error>');
			return self::FAILURE;
		}
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
				$output->writeln('Importierte Daten:');
				foreach ($importedTables as $row) {
					if (!is_array($row)) {
						continue;
					}
					$output->writeln(sprintf(
						' - %s: %d',
						(string)($row['label'] ?? $row['table'] ?? 'Unbekannt'),
						(int)($row['count'] ?? 0)
					));
				}
			}

			$settings = $result['report']['settings'] ?? [];
			if (is_array($settings) && $settings !== []) {
				$output->writeln('Einstellungen:');
				foreach ($settings as $row) {
					if (is_array($row)) {
						$output->writeln(sprintf(' - %s: %d Werte', (string)($row['user_id'] ?? ''), (int)($row['count'] ?? 0)));
					}
				}
			}

			$userMappings = $result['report']['user_mappings'] ?? [];
			if (is_array($userMappings) && $userMappings !== []) {
				$output->writeln('User-Mapping:');
				foreach ($userMappings as $row) {
					if (is_array($row)) {
						$output->writeln(sprintf(' - %s -> %s', (string)($row['source_user_id'] ?? ''), (string)($row['target_user_id'] ?? '')));
					}
				}
			}

			$attachmentPaths = $result['report']['attachment_paths'] ?? null;
			if (is_array($attachmentPaths) && (int)($attachmentPaths['count'] ?? 0) > 0) {
				$output->writeln('Beleg-Pfade: ' . (int)$attachmentPaths['count'] . ' importiert; Dateien selbst werden nicht kopiert.');
			}
			return self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Persoenlicher Export konnte nicht wiederhergestellt werden: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

	}
}

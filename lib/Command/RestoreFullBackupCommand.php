<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCA\CoBudget\Service\BackupService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreFullBackupCommand extends Command {
	public function __construct(
		private BackupService $backupService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:backup:restore-full')
			->setDescription('Stellt ein vollständiges CoBudget-Backup aller Benutzer wieder her.')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nextcloud Administrator-ID, in dessen Files das Backup liegt')
			->addOption('file', null, InputOption::VALUE_REQUIRED, 'Full-Backup-Dateiname im Backup-Ordner')
			->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Optionaler Backup-Ordner in Nextcloud Files')
			->addOption('map-user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User-Mapping im Format alterUser:neuerUser')
			->addOption('force', null, InputOption::VALUE_NONE, 'Bestätigt, dass alle bestehenden CoBudget-Daten ersetzt werden dürfen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$storageUserId = trim((string)$input->getOption('user'));
		if ($storageUserId === '') {
			$output->writeln('<error>Bitte Speicher-Benutzer mit --user angeben.</error>');
			return self::FAILURE;
		}
		if (!$this->userManager->userExists($storageUserId)) {
			$output->writeln('<error>Speicher-Benutzer wurde nicht gefunden.</error>');
			return self::FAILURE;
		}
		if (!$input->getOption('force')) {
			$output->writeln('<error>Full-Restore überschreibt alle aktuellen CoBudget-Daten mit dem Backup. Bitte mit --force ausdrücklich bestätigen.</error>');
			return self::FAILURE;
		}

		$fileName = trim((string)$input->getOption('file'));
		if ($fileName === '') {
			$output->writeln('<error>Bitte Backup-Datei mit --file angeben.</error>');
			return self::FAILURE;
		}

		$folder = $input->getOption('folder');
		$folder = is_string($folder) ? trim($folder) : $folder;

		try {
			$userMap = $this->parseUserMap($input->getOption('map-user'));
			foreach ($userMap as $targetUserId) {
				if (!$this->userManager->userExists($targetUserId)) {
					$output->writeln('<error>Ziel-Benutzer "' . $targetUserId . '" wurde nicht gefunden.</error>');
					return self::FAILURE;
				}
			}

			$restore = $this->backupService->restoreFullBackup(
				$storageUserId,
				$fileName,
				is_string($folder) && $folder !== '' ? $folder : null,
				$userMap
			);
			$output->writeln('<info>Vollständiges CoBudget-Backup wiederhergestellt.</info>');
			$output->writeln('Datei: ' . $restore['file_name']);
			if (!empty($restore['safety_backup']['file_name'])) {
				$output->writeln('Sicherheitsbackup: ' . $restore['safety_backup']['file_name']);
			}
			$output->writeln('Benutzer: ' . implode(', ', $restore['users']));
			$this->printRestoreReport($output, is_array($restore['report'] ?? null) ? $restore['report'] : []);

			return self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}
	}

	private function parseUserMap(mixed $values): array {
		$map = [];
		foreach (is_array($values) ? $values : [] as $value) {
			$value = (string)$value;
			$parts = explode(':', $value, 2);
			if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
				throw new \InvalidArgumentException('User-Mapping muss im Format alterUser:neuerUser angegeben werden');
			}
			$map[trim($parts[0])] = trim($parts[1]);
		}

		return $map;
	}

	private function printRestoreReport(OutputInterface $output, array $report): void {
		if ($report === []) {
			return;
		}

		$output->writeln('Restore-Protokoll:');
		$output->writeln('Importiert: ' . (int)($report['imported_total'] ?? 0) . ' Tabellenzeilen');
		foreach (($report['imported_tables'] ?? []) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$output->writeln(sprintf(' - %s: %d', (string)($row['label'] ?? $row['table'] ?? 'Unbekannt'), (int)($row['count'] ?? 0)));
		}

		if (!empty($report['settings'])) {
			$output->writeln('Einstellungen:');
			foreach ($report['settings'] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$output->writeln(sprintf(' - %s: %d Werte', (string)($row['user_id'] ?? ''), (int)($row['count'] ?? 0)));
			}
		}

		if (!empty($report['user_mappings'])) {
			$output->writeln('User-Mapping:');
			foreach ($report['user_mappings'] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$output->writeln(sprintf(' - %s -> %s', (string)($row['source_user_id'] ?? ''), (string)($row['target_user_id'] ?? '')));
			}
		}

		if (!empty($report['skipped'])) {
			$output->writeln('Übersprungen:');
			foreach ($report['skipped'] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$output->writeln(sprintf(' - %s: %d (%s)', (string)($row['label'] ?? $row['table'] ?? 'Unbekannt'), (int)($row['count'] ?? 0), (string)($row['reason'] ?? '')));
			}
		}

		$attachmentPaths = $report['attachment_paths'] ?? null;
		if (is_array($attachmentPaths) && (int)($attachmentPaths['count'] ?? 0) > 0) {
			$output->writeln('Beleg-Pfade: ' . (int)$attachmentPaths['count'] . ' importiert; Dateien selbst werden nicht kopiert.');
		}
	}
}

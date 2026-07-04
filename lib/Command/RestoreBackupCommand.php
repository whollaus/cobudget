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
		private BackupService $backupService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:backup:restore')
			->setDescription('Stellt ein CoBudget-Benutzer-Backup aus Nextcloud Files wieder her.')
			->addArgument('user', InputArgument::REQUIRED, 'Nextcloud Benutzer-ID, dessen Daten ersetzt werden')
			->addArgument('file', InputArgument::REQUIRED, 'Backup-Dateiname im Backup-Ordner')
			->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Optionaler Backup-Ordner in Nextcloud Files')
			->addOption('map-user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User-Mapping im Format alterUser:neuerUser')
			->addOption('force', null, InputOption::VALUE_NONE, 'Bestätigt, dass bestehende CoBudget-Daten ersetzt werden dürfen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('user');
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			$output->writeln('<error>Benutzer wurde nicht gefunden.</error>');
			return self::FAILURE;
		}
		if (!$input->getOption('force')) {
			$output->writeln('<error>Restore ist destruktiv. Bitte mit --force ausdrücklich bestätigen.</error>');
			return self::FAILURE;
		}

		$fileName = (string)$input->getArgument('file');
		$folder = $input->getOption('folder');

		try {
			$userMap = $this->parseUserMap($input->getOption('map-user'));
			foreach ($userMap as $targetUserId) {
				if (!$this->userManager->userExists($targetUserId)) {
					$output->writeln('<error>Ziel-Benutzer "' . $targetUserId . '" wurde nicht gefunden.</error>');
					return self::FAILURE;
				}
			}

			$restore = $this->backupService->restoreBackup(
				$userId,
				$fileName,
				is_string($folder) && $folder !== '' ? $folder : null,
				$userMap
			);
			$output->writeln('<info>CoBudget-Backup wiederhergestellt.</info>');
			$output->writeln('Benutzer: ' . $restore['user_id']);
			$output->writeln('Datei: ' . $restore['file_name']);
			if (!empty($restore['safety_backup']['file_name'])) {
				$output->writeln('Sicherheitsbackup: ' . $restore['safety_backup']['file_name']);
			}
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

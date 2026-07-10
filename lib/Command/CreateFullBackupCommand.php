<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCA\CoBudget\Service\BackupService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateFullBackupCommand extends Command {
	public function __construct(
		private BackupService $backupService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:backup:create-full')
			->setDescription('Erstellt ein vollständiges CoBudget-Backup aller Benutzer in Nextcloud Files.')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nextcloud Administrator-ID, in dessen Files das Backup gespeichert wird')
			->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Optionaler Backup-Ordner in Nextcloud Files')
			->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Anzahl der Full-Backups, die behalten werden sollen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$configuredSettings = $this->backupService->getFullBackupSettings();
		$storageUserId = (string)$input->getOption('user');
		$useConfiguredDefaults = $storageUserId === '';
		if ($storageUserId === '') {
			$storageUserId = (string)$configuredSettings['storage_user_id'];
		}
		if ($storageUserId === '') {
			$output->writeln('<error>Bitte Speicher-Benutzer mit --user angeben. Alternativ in den Admin-Einstellungen konfigurieren.</error>');
			return self::FAILURE;
		}
		if (!$this->userManager->userExists($storageUserId)) {
			$output->writeln('<error>Speicher-Benutzer wurde nicht gefunden.</error>');
			return self::FAILURE;
		}

		$folder = $input->getOption('folder');
		$keep = $input->getOption('keep');

		try {
			$backup = $this->backupService->createFullBackup(
				$storageUserId,
				is_string($folder) && $folder !== '' ? $folder : ($useConfiguredDefaults ? (string)$configuredSettings['storage_folder'] : null),
				$keep !== null ? (int)$keep : ($useConfiguredDefaults ? (int)$configuredSettings['retention_count'] : null)
			);
			$output->writeln('<info>Vollständiges CoBudget-Backup erstellt.</info>');
			$output->writeln('Datei: ' . $backup['file_path']);
			$output->writeln('Groesse: ' . $backup['file_size'] . ' Bytes');

			return self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}
	}
}

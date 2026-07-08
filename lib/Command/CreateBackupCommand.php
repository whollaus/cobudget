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

class CreateBackupCommand extends Command {
	public function __construct(
		private BackupService $backupService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:backup:create')
			->setDescription('Erstellt einen persoenlichen CoBudget-Export fuer einen Benutzer in Nextcloud Files.')
			->addArgument('user', InputArgument::REQUIRED, 'Nextcloud Benutzer-ID')
			->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Optionaler Export-Ordner in Nextcloud Files')
			->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Anzahl der Exporte, die behalten werden sollen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('user');
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			$output->writeln('<error>Benutzer wurde nicht gefunden.</error>');
			return self::FAILURE;
		}

		$folder = $input->getOption('folder');
		$keep = $input->getOption('keep');

		try {
			$backup = $this->backupService->createBackup(
				$userId,
				is_string($folder) && $folder !== '' ? $folder : null,
				$keep !== null ? (int)$keep : null
			);
			$output->writeln('<info>Persoenlicher CoBudget-Export erstellt.</info>');
			$output->writeln('Datei: ' . $backup['file_path']);
			$output->writeln('Groesse: ' . $backup['file_size'] . ' Bytes');

			return self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}
	}
}

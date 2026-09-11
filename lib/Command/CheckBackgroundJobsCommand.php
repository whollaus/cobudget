<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCA\CoBudget\Service\BackgroundJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckBackgroundJobsCommand extends Command {
	public function __construct(private BackgroundJobService $backgroundJobService) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:background-jobs:check')
			->setDescription('Prüft CoBudget-Hintergrundjobs und zeigt Ladefehler direkt an.')
			->addOption('repair', null, InputOption::VALUE_NONE, 'Registriert fehlende Jobs, wenn sich alle CoBudget-Jobs laden lassen; führt keine Jobs aus.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$report = $this->backgroundJobService->check((bool)$input->getOption('repair'));
		} catch (\Throwable $e) {
			do {
				$output->writeln('<error>' . OutputFormatter::escape(get_class($e) . ': ' . $e->getMessage()) . '</error>');
				$e = $e->getPrevious();
			} while ($e !== null);
			return self::FAILURE;
		}

		$output->writeln('PHP: ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
		$output->writeln('Nextcloud-Hintergrundmodus: ' . OutputFormatter::escape($report['backgroundMode']));
		$output->writeln('Letzter Nextcloud-Hintergrundlauf: ' . ($report['lastCron'] > 0 ? date(DATE_ATOM, $report['lastCron']) : 'noch nicht verzeichnet'));
		$output->writeln('');

		foreach ($report['jobs'] as $status) {
			$output->writeln(OutputFormatter::escape($status['class']));
			$output->writeln('  Job-Konstruktor: ' . ($status['loadable'] ? 'OK' : 'FEHLER'));
			$output->writeln('  Warteschlange: ' . ($status['registered'] ? ($status['restored'] ? 'wiederhergestellt' : 'registriert') : 'fehlt'));
			if ($status['file'] !== null) {
				$output->writeln('  Geladene Datei: ' . OutputFormatter::escape($status['file']));
			}
			if ($status['interval'] !== null) {
				$output->writeln('  Prüfintervall: ' . $status['interval'] . ' Sekunden');
			}
			foreach ($status['errors'] as $error) {
				$output->writeln('  <error>' . OutputFormatter::escape($error) . '</error>');
			}
			$output->writeln('');
		}

		if ($report['repairBlocked']) {
			$output->writeln('<error>Keine Jobs registriert: Zuerst die angezeigten Lade- oder Datenbankfehler beheben.</error>');
		} elseif (!$report['healthy'] && !$input->getOption('repair')) {
			$output->writeln('Fehlende Einträge lassen sich mit occ cobudget:background-jobs:check --repair wiederherstellen.');
		}
		if ($report['backgroundMode'] !== 'cron') {
			$output->writeln('<comment>Für zuverlässige Ausführung Nextcloud-System-Cron alle fünf Minuten einrichten.</comment>');
		}
		$output->writeln('Es wurden keine Hintergrundjobs ausgeführt. Der tatsächliche Job-Lauf wird hier nicht geprüft.');

		return $report['healthy'] ? self::SUCCESS : self::FAILURE;
	}
}

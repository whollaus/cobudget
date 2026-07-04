<?php

declare(strict_types=1);

namespace OCA\CoBudget\Command;

use OCA\CoBudget\Service\DataIntegrityService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckDataIntegrityCommand extends Command {
	public function __construct(
		private DataIntegrityService $dataIntegrityService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('cobudget:integrity:check')
			->setDescription('Prueft CoBudget-Daten auf verwaiste Referenzen und sichtbare Namens-Dubletten.')
			->addOption('repair', null, InputOption::VALUE_NONE, 'Setzt verwaiste Kategorie-, Zahlungspartner- und Bereichsreferenzen auf leer.')
			->addOption('merge-category', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Fuehrt Kategorie-Dubletten zusammen: BEHALTEN:ENTFERNEN[,ENTFERNEN]')
			->addOption('merge-payment-partner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Fuehrt Zahlungspartner-Dubletten zusammen: BEHALTEN:ENTFERNEN[,ENTFERNEN]');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$mergeResults = [];
			foreach ($this->parseMergeOptions($input->getOption('merge-category')) as $merge) {
				$mergeResults[] = $this->dataIntegrityService->mergeDuplicate('category', $merge['keepId'], $merge['mergeIds']);
			}
			foreach ($this->parseMergeOptions($input->getOption('merge-payment-partner')) as $merge) {
				$mergeResults[] = $this->dataIntegrityService->mergeDuplicate('payment_partner', $merge['keepId'], $merge['mergeIds']);
			}

			$report = $this->dataIntegrityService->inspect();
			if ($input->getOption('repair')) {
				$report = $this->dataIntegrityService->repair($report);
			}
			$report['mergeResults'] = $mergeResults;

			$this->writeReport($output, $report);

			return ((int)($report['orphanReferenceCount'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}
	}

	private function parseMergeOptions(mixed $options): array {
		$options = is_array($options) ? $options : [];
		$merges = [];
		foreach ($options as $option) {
			$option = trim((string)$option);
			if ($option === '' || !str_contains($option, ':')) {
				throw new \InvalidArgumentException('Merge-Format: BEHALTEN:ENTFERNEN[,ENTFERNEN]');
			}

			[$keep, $remove] = explode(':', $option, 2);
			$keepId = (int)trim($keep);
			$mergeIds = array_values(array_filter(array_map(
				static fn (string $id): int => (int)trim($id),
				explode(',', $remove)
			), static fn (int $id): bool => $id > 0));

			if ($keepId <= 0 || $mergeIds === []) {
				throw new \InvalidArgumentException('Merge-Format: BEHALTEN:ENTFERNEN[,ENTFERNEN]');
			}

			$merges[] = [
				'keepId' => $keepId,
				'mergeIds' => $mergeIds,
			];
		}

		return $merges;
	}

	private function writeReport(OutputInterface $output, array $report): void {
		$orphanCount = (int)($report['orphanReferenceCount'] ?? 0);
		$duplicateCount = (int)($report['duplicateVisibleNameCount'] ?? 0);
		$repairedReferences = (int)($report['repairedReferences'] ?? 0);
		$mergeResults = is_array($report['mergeResults'] ?? null) ? $report['mergeResults'] : [];

		foreach ($mergeResults as $merge) {
			$output->writeln(sprintf(
				'<info>%s "%s" zusammengefuehrt: %s -> %s; Eintraege: %d, Vorlagen: %d, Budgetziele: %d.</info>',
				(string)($merge['label'] ?? 'Dubletten'),
				(string)($merge['name'] ?? ''),
				implode(', ', array_map('strval', $merge['mergedIds'] ?? [])),
				(string)($merge['keepId'] ?? ''),
				(int)($merge['entriesUpdated'] ?? 0),
				(int)($merge['templatesUpdated'] ?? 0),
				(int)($merge['budgetGoalsUpdated'] ?? 0)
			));
		}

		if ($orphanCount === 0 && $duplicateCount === 0 && $repairedReferences === 0 && $mergeResults === []) {
			$output->writeln('<info>Keine Datenintegritaetsprobleme gefunden.</info>');
			return;
		}

		if ($repairedReferences > 0) {
			$output->writeln('<info>Repariert: ' . $repairedReferences . ' Referenzen zurueckgesetzt.</info>');
		}

		if ($orphanCount > 0) {
			$output->writeln('<error>Verwaiste Referenzen: ' . $orphanCount . '</error>');
			foreach ($report['orphanReferences'] ?? [] as $issue) {
				$output->writeln(sprintf(
					' - %s.%s -> %s: %s',
					(string)($issue['sourceTable'] ?? ''),
					(string)($issue['column'] ?? ''),
					(string)($issue['targetTable'] ?? ''),
					implode(', ', array_map('strval', $issue['ids'] ?? []))
				));
			}
			$output->writeln('<comment>Zum Reparieren: occ cobudget:integrity:check --repair</comment>');
		}

		if ($duplicateCount > 0) {
			$output->writeln('<comment>Sichtbare Namens-Dubletten: ' . $duplicateCount . '</comment>');
			foreach ($report['duplicateVisibleNames'] ?? [] as $issue) {
				$output->writeln(sprintf(
					' - %s "%s" (%s, %s): %s',
					(string)($issue['label'] ?? ''),
					(string)($issue['name'] ?? ''),
					(string)($issue['type'] ?? ''),
					(string)($issue['scope'] ?? 'scope unbekannt'),
					implode(', ', array_map('strval', $issue['ids'] ?? []))
				));
				$ids = array_values(array_map('intval', $issue['ids'] ?? []));
				if (count($ids) >= 2) {
					$optionName = ((string)($issue['table'] ?? '')) === 'cobudget_categories' ? 'merge-category' : 'merge-payment-partner';
					$output->writeln(sprintf(
						'   Merge-Vorschlag: occ cobudget:integrity:check --%s=%d:%s',
						$optionName,
						$ids[0],
						implode(',', array_slice(array_map('strval', $ids), 1))
					));
				}
			}
			$output->writeln('<comment>Hinweis: Dubletten werden nur mit explizitem Merge-Befehl zusammengefuehrt.</comment>');
		}
	}
}

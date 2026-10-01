<?php

declare(strict_types=1);

namespace OCP {
	if (!interface_exists(IL10N::class, false)) {
		interface IL10N {
			public function t(string $text, array $parameters = []): string;
		}
	}
}

namespace CoBudget\Tests {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\AnalyticsController;
	use OCA\CoBudget\Controller\BackupController;
	use OCA\CoBudget\Controller\WorkspaceController;
	use OCA\CoBudget\Service\BackupService;
	use OCP\IL10N;

	require_once dirname(__DIR__, 2) . '/lib/Controller/AnalyticsController.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/BackupController.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceController.php';
	require_once dirname(__DIR__, 2) . '/lib/Service/BackupService.php';

	final class CatalogTestL10n implements IL10N {
		public function __construct(private array $translations) {}

		public function t(string $text, array $parameters = []): string {
			$value = $this->translations[$text] ?? $text;
			return $parameters === [] ? $value : vsprintf($value, $parameters);
		}
	}

	function localizedTestObject(string $className, array $translations): object {
		$class = new \ReflectionClass($className);
		$object = $class->newInstanceWithoutConstructor();
		$class->getProperty('l10n')->setValue($object, new CatalogTestL10n($translations));
		return $object;
	}

	function localizationMethod(object $object, string $method, mixed ...$args): mixed {
		return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
	}

	function localizationCatalogs(TestRunner $t): array {
		return [
			[],
			json_decode($t->read('l10n/de.json'), true, flags: JSON_THROW_ON_ERROR)['translations'],
			json_decode($t->read('l10n/fr.json'), true, flags: JSON_THROW_ON_ERROR)['translations'],
			json_decode($t->read('l10n/es.json'), true, flags: JSON_THROW_ON_ERROR)['translations'],
		];
	}

	return [
		'Restore report table labels use the requested language' => static function(TestRunner $t): void {
			foreach (localizationCatalogs($t) as $catalog) {
				$service = localizedTestObject(BackupService::class, $catalog);
				foreach (['cobudget_projects' => 'Areas', 'cobudget_entries' => 'Payments', 'cobudget_entry_attachments' => 'Receipt paths'] as $table => $key) {
					$t->assertSame($catalog[$key] ?? $key, localizationMethod($service, 'backupTableLabel', $table), 'Restore table labels must be localized');
				}
				$t->assertSame('unknown_table', localizationMethod($service, 'backupTableLabel', 'unknown_table'), 'Unknown table identifiers must remain unchanged');
			}
		},
		'Analytics labels and forecasts follow the requested language without translating user data' => static function(TestRunner $t): void {
			foreach (localizationCatalogs($t) as $catalog) {
				$controller = localizedTestObject(AnalyticsController::class, $catalog);
				$options = localizationMethod($controller, 'buildPeriodOptions', []);
				foreach (['Current year', 'Current month', 'Last month', 'Last 12 months', 'Last year'] as $index => $key) {
					$t->assertSame($catalog[$key] ?? $key, $options[$index]['label'], 'Period labels must use the selected language');
				}
				$period = localizationMethod($controller, 'resolvePeriod', 'current-month', $options);
				$t->assertSame($catalog['Current month'] ?? 'Current month', $period['label'], 'Resolved periods must also be localized');
				$summary = ['incomeCents' => 10000, 'expenseCents' => 2500, 'balanceCents' => 7500, 'bookingCount' => 1];
				$projection = localizationMethod($controller, 'buildProjection', [], $period, $summary);
				$t->assertSame($catalog['Month-end forecast'] ?? 'Month-end forecast', $projection['label'], 'Projection titles must be localized');
				$forecast = localizationMethod($controller, 'buildAvailableForecast', $period, $summary, $projection);
				$t->assertSame($catalog['Early estimate'] ?? 'Early estimate', $forecast['confidenceLabel'], 'Forecast confidence must be localized');
				$rows = localizationMethod($controller, 'buildBreakdownRows', [
					['type' => 'expense', 'paymentPartnerId' => 7, 'paymentPartnerName' => 'Miete & Nebenkosten', 'personalCents' => 500],
				], 'paymentPartnerId', 'paymentPartnerName', 'unused', 'expense');
				$t->assertSame('Miete & Nebenkosten', $rows[0]['name'], 'User-defined names must survive language changes');
				$t->assertSame(500, $rows[0]['amountCents'], 'Localization must preserve financial values');
			}
		},
		'Personal export and workspace errors translate English fallback keys' => static function(TestRunner $t): void {
			foreach (localizationCatalogs($t) as $catalog) {
				foreach ([BackupController::class => 'Personal export could not be downloaded.', WorkspaceController::class => 'A workspace with this name already exists.'] as $class => $key) {
					$response = localizationMethod(localizedTestObject($class, $catalog), 'errorResponse', $key, 400);
					$t->assertSame(['error' => $catalog[$key] ?? $key], $response->getData(), 'API errors must use the requested language');
					$t->assertSame(400, $response->getStatus(), 'Localization must preserve error status codes');
				}
			}
		},
	];
}

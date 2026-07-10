<?php

declare(strict_types=1);

namespace CoBudget\Tests;

require_once dirname(__DIR__, 2) . '/lib/Service/CsvCellSanitizer.php';

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\CsvCellSanitizer;

return [
	'CSV text cells neutralize spreadsheet formula prefixes' => function (TestRunner $t): void {
		foreach (['=1+1', '+cmd', '-2+3', '@SUM(A1:A2)', "\tformula", "\rformula", "\nformula", '  =1+1'] as $value) {
			$t->assertSame("'" . $value, CsvCellSanitizer::sanitize($value), 'Dangerous CSV text should be prefixed with an apostrophe: ' . json_encode($value));
		}
	},

	'CSV text cells preserve ordinary user content' => function (TestRunner $t): void {
		foreach (['', 'Lebensmittel', 'Rechnung 4711', '#urlaub', '20 EUR'] as $value) {
			$t->assertSame($value, CsvCellSanitizer::sanitize($value), 'Ordinary CSV text should remain unchanged: ' . $value);
		}
	},
];

<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;

require_once __DIR__ . '/Support/TestRunner.php';

$root = dirname(__DIR__, 2);
$runner = new TestRunner($root);

$testFiles = glob(__DIR__ . '/*Test.php') ?: [];
sort($testFiles);

foreach ($testFiles as $testFile) {
	$tests = require $testFile;
	if (!is_array($tests)) {
		fwrite(STDERR, basename($testFile) . " did not return tests.\n");
		exit(1);
	}

	foreach ($tests as $name => $test) {
		if (!is_callable($test)) {
			fwrite(STDERR, basename($testFile) . " contains a non-callable test: {$name}\n");
			exit(1);
		}

		$runner->add((string)$name, $test);
	}
}

exit($runner->run());

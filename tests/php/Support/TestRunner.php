<?php

declare(strict_types=1);

namespace CoBudget\Tests\Support;

final class TestRunner {
	private string $root;
	/** @var array<int, array{name: string, test: callable}> */
	private array $tests = [];
	private int $assertions = 0;

	public function __construct(string $root) {
		$this->root = rtrim($root, '/');
	}

	public function add(string $name, callable $test): void {
		$this->tests[] = [
			'name' => $name,
			'test' => $test,
		];
	}

	public function run(): int {
		$failures = [];

		foreach ($this->tests as $case) {
			try {
				($case['test'])($this);
				echo '.';
			} catch (\Throwable $e) {
				echo 'F';
				$failures[] = [
					'name' => $case['name'],
					'error' => $e,
				];
			}
		}

		echo "\n";

		if ($failures !== []) {
			fwrite(STDERR, "PHP backend tests failed:\n");
			foreach ($failures as $failure) {
				fwrite(STDERR, "- {$failure['name']}: {$failure['error']->getMessage()}\n");
			}
			fwrite(STDERR, sprintf("%d tests, %d assertions\n", count($this->tests), $this->assertions));
			return 1;
		}

		echo sprintf("PHP backend tests passed: %d tests, %d assertions.\n", count($this->tests), $this->assertions);
		return 0;
	}

	public function path(string $relativePath): string {
		return $this->root . '/' . ltrim($relativePath, '/');
	}

	public function read(string $relativePath): string {
		$path = $this->path($relativePath);
		if (!is_file($path)) {
			throw new \RuntimeException('Missing file: ' . $relativePath);
		}

		return (string)file_get_contents($path);
	}

	public function methodBody(string $relativePath, string $method): string {
		$source = $this->read($relativePath);
		$pattern = '/function\s+' . preg_quote($method, '/') . '\s*\(/';
		if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
			throw new \RuntimeException(sprintf('Missing method %s in %s', $method, $relativePath));
		}

		$openBrace = strpos($source, '{', $match[0][1]);
		if ($openBrace === false) {
			throw new \RuntimeException(sprintf('Method %s has no body in %s', $method, $relativePath));
		}

		$depth = 0;
		$length = strlen($source);
		for ($i = $openBrace; $i < $length; $i++) {
			if ($source[$i] === '{') {
				$depth++;
			} elseif ($source[$i] === '}') {
				$depth--;
				if ($depth === 0) {
					return substr($source, $openBrace, $i - $openBrace + 1);
				}
			}
		}

		throw new \RuntimeException(sprintf('Could not extract method %s in %s', $method, $relativePath));
	}

	public function assertTrue(bool $condition, string $message): void {
		$this->assertions++;
		if (!$condition) {
			throw new \RuntimeException($message);
		}
	}

	public function assertFalse(bool $condition, string $message): void {
		$this->assertTrue(!$condition, $message);
	}

	/**
	 * @param mixed $expected
	 * @param mixed $actual
	 */
	public function assertSame($expected, $actual, string $message): void {
		$this->assertions++;
		if ($expected !== $actual) {
			throw new \RuntimeException($message . sprintf(' Expected %s, got %s.', var_export($expected, true), var_export($actual, true)));
		}
	}

	/**
	 * @param mixed $value
	 */
	public function assertNull($value, string $message): void {
		$this->assertSame(null, $value, $message);
	}

	/**
	 * @param mixed $value
	 */
	public function assertNotNull($value, string $message): void {
		$this->assertions++;
		if ($value === null) {
			throw new \RuntimeException($message);
		}
	}

	public function assertContains(string $needle, string $haystack, string $message): void {
		$this->assertions++;
		if (strpos($haystack, $needle) === false) {
			throw new \RuntimeException($message . ' Missing `' . $needle . '`.');
		}
	}

	public function assertNotContains(string $needle, string $haystack, string $message): void {
		$this->assertions++;
		if (strpos($haystack, $needle) !== false) {
			throw new \RuntimeException($message . ' Unexpected `' . $needle . '`.');
		}
	}

	public function assertMatches(string $pattern, string $haystack, string $message): void {
		$this->assertions++;
		if (preg_match($pattern, $haystack) !== 1) {
			throw new \RuntimeException($message . ' Pattern did not match: ' . $pattern);
		}
	}
}

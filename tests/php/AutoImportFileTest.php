<?php
declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AutoImportFile;

require_once dirname(__DIR__, 2) . '/lib/Service/PaymentImportJson.php';
require_once dirname(__DIR__, 2) . '/lib/Service/AutoImportFile.php';

$read = static function (string $json): AutoImportFile {
	$stream = fopen('php://temp', 'w+b');
	try {
		fwrite($stream, $json);
		rewind($stream);
		return AutoImportFile::fromStream($stream);
	} finally {
		fclose($stream);
	}
};

return [
	'Archive recovery stores only fingerprints of complete files' => function (TestRunner $t): void {
		$content = str_repeat(' ', AutoImportFile::MAX_BYTES);
		foreach (['', 'ab'] as $suffix) {
			$stream = fopen('php://temp', 'w+b');
			fwrite($stream, $content . $suffix);
			rewind($stream);
			$hash = AutoImportFile::fingerprintAndClose($stream, $complete);
			$t->assertSame(hash('sha256', $content . substr($suffix, 0, 1)), $hash, 'Fingerprint reads through the first excess byte');
			$t->assertSame($suffix === '', $complete, 'A prefix of an oversized upload cannot identify a moved file');
		}
	},
	'Folder imports hash the ordered payment list and paginate the complete input' => function (TestRunner $t) use ($read): void {
		$rows = array_fill(0, 5000, ['date' => '2026-01-01', 'amount' => -12.5, 'description' => 'Café / "A" {[]}']);
		$canonical = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$json = json_encode(['extra' => ['ignore' => true], 'payments' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
		$input = $read("\xEF\xBB\xBF" . $json);
		$t->assertSame(5000, $input->count, 'All rows must be counted before the first commit');
		$t->assertSame(hash('sha256', $canonical), $input->digest, 'File formatting and envelope fields do not affect the digest');
		$count = 0;
		foreach ($input->packets() as $offset => $packet) {
			$t->assertSame($count, $offset, 'Packet offsets must be absolute');
			$t->assertSame(50, count($packet), 'Each packet must be bounded');
			$count += count($packet);
		}
		$t->assertSame(5000, $count, 'The last preview packet must be included');
		$atLimit = $read(str_pad($json, 5 * 1024 * 1024));
		$t->assertSame($input->digest, $atLimit->digest, 'A valid file at the exact byte limit is accepted');
	},
	'Folder imports preserve sub-cent literals for validation and the digest of valid JSON numbers' => function (TestRunner $t) use ($read): void {
		$file = $read('{"payments":[{"date":"2026-01-01","\u0061mount":-99999999.990000001}]}');
		$t->assertSame('-99999999.990000001', $file->packets()->current()[0]['amount'], 'Floating-point conversion must not hide a fractional cent');
		$t->assertSame($read('{"payments":[{"amount":-1.23}]}')->digest, $read('{"payments":[{"amount":-12300e-4}]}')->digest, 'Exact cents keep the same import key');
	},
	'Folder imports reject malformed and oversized files before yielding any payment' => function (TestRunner $t) use ($read): void {
		foreach (['{"payments":[{"amount":01.000001}]}', '{}', '{"payments":[]}', '{"payments":[{}],}', '{"payments":[{}]}x', '{"payments":[{}],"payments":[{}]}',
			'{"payments":[{"amount":1e400}]}', '{"payments":[{"date":["bad"]}]}', '{"payments":[{"text":"unterminated}]}',
			'{"payments":[' . str_repeat('{},', 5000) . '{}]}', str_repeat(' ', 5 * 1024 * 1024 + 1)] as $json) {
			try {
				$read($json);
				throw new \RuntimeException('Expected invalid input to be rejected');
			} catch (\DomainException $e) {
				$t->assertTrue($e->getMessage() !== '', 'Rejected files should explain the error');
			}
		}
	},
];

<?php

declare(strict_types=1);

use CoBudget\Tests\BulkImportTestRequest;
use function CoBudget\Tests\makeBulkImportHarness;

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once dirname(__DIR__) . '/BulkImportContractTest.php';

$user = $argv[1] ?? 'alice';
[$controller, $db, $config] = makeBulkImportHarness($user);
$config->setUserValue($user, 'cobudget', 'enable_payment_imports', 'yes');
$pdo = $db->pdo;
$pdo->exec("UPDATE cobudget_members SET share_basis_points = 5000 WHERE project_id = 10 AND user_id = 'alice'");
$pdo->exec("UPDATE cobudget_members SET share_basis_points = 3000 WHERE project_id = 10 AND user_id = 'bob'");
$pdo->exec("INSERT INTO cobudget_workspaces (id, user_id, name, is_default) VALUES (4, 'carol', 'Home', 1)");
$pdo->exec("INSERT INTO cobudget_members (project_id, user_id, share_basis_points, personal_workspace_id) VALUES (10, 'carol', 2000, 4)");
// Match the production lookup indexes for this 5,000-payment fixture.
$pdo->exec('CREATE UNIQUE INDEX cb_ent_source_user ON cobudget_entries (source_entry_id, user_id)');
$pdo->exec('CREATE UNIQUE INDEX cb_esh_entry_user ON cobudget_entry_shares (entry_id, user_id)');

while (($line = fgets(STDIN)) !== false) {
	$body = rtrim($line, "\r\n");
	$status = 200;
	if ($body === 'memberSnapshot') {
		$reply = [
			'entryCount' => (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(),
			'shareCount' => (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entry_shares')->fetchColumn(),
			'originals' => $pdo->query("SELECT user_id, project_id, description, category_id, payment_partner_id
				FROM cobudget_entries WHERE entry_kind = 'shared' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
			'categories' => $pdo->query('SELECT id, name, project_id FROM cobudget_categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
			'partners' => $pdo->query('SELECT id, name, project_id FROM cobudget_payment_partners ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
		];
	} elseif ($body === 'snapshot') {
		$reply = [
			'entryCount' => (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entries')->fetchColumn(),
			'shareCount' => (int)$pdo->query('SELECT COUNT(*) FROM cobudget_entry_shares')->fetchColumn(),
			'rows' => $pdo->query("
				SELECT e.id AS original, e.description, e.import_key, e.amount_cents AS original_cents,
					s.user_id, s.share_basis_points, s.amount_cents AS share_cents,
					p.id AS personal, p.workspace_id, p.amount_cents AS personal_cents
				FROM cobudget_entries e
				LEFT JOIN cobudget_entry_shares s ON s.entry_id = e.id
				LEFT JOIN cobudget_entries p ON p.id = s.personal_entry_id AND p.source_entry_id = e.id
					AND p.user_id = s.user_id AND p.project_id = e.project_id AND p.entry_kind = 'personal'
					AND p.is_locked = 1 AND p.allocation_basis_points = s.share_basis_points
				WHERE e.entry_kind = 'shared' AND e.project_id = 10
				ORDER BY e.id, s.user_id
			")->fetchAll(PDO::FETCH_ASSOC),
		];
	} else {
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $body);
		rewind($stream);
		$controller->request = new BulkImportTestRequest([
			'Content-Type' => 'application/octet-stream',
			'Content-Length' => (string)strlen($body),
		], $stream);
		$response = $controller->importPacket();
		$status = $response->getStatus();
		$reply = $response->getData();
	}
	echo json_encode(['status' => $status, 'data' => $reply], JSON_THROW_ON_ERROR), "\n";
	unset($reply);
}

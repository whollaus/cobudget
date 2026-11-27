<?php
declare(strict_types=1);

use OCA\CoBudget\AppInfo\Application;
use OCA\CoBudget\Controller\EntryController;
use OCA\CoBudget\Controller\ProjectController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

$base = dirname(getcwd());
if (PHP_SAPI !== 'cli' || !is_file($base . '/fresh-install-test') || !in_array($argv[1] ?? '', ['create', 'verify'], true)) {
	throw new RuntimeException('Run through run-fresh-install.py in a disposable instance.');
}
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require getcwd() . '/lib/base.php';
set_exception_handler(static function (Throwable $error): void {
	fwrite(STDERR, (string)$error . "\n");
	exit(1);
});
if (realpath(Server::get(IConfig::class)->getSystemValueString('datadirectory')) !== $base . '/data') {
	throw new RuntimeException('Expected the disposable data directory.');
}
OC_App::loadApps();
Server::get(IUserSession::class)->setUser(Server::get(IUserManager::class)->get('release-user'));
$app = (new Application())->getContainer();
$entries = $app->get(EntryController::class);
function data(DataResponse $response): array {
	if ($response->getStatus() >= 300) throw new RuntimeException('CoBudget response: ' . json_encode($response->getData()));
	return $response->getData();
}

$payments = [
	'groceries' => ['Fresh groceries', 'expense', 1299],
	'refund' => ['Fresh refund', 'income', 740],
	'shared' => ['Fresh shared lunch', 'expense', 1000],
];
$stateFile = $base . '/payments.json';
if ($argv[1] === 'create') {
	$area = data($app->get(ProjectController::class)->create('Fresh shared area', ['release-admin']))['id'];
	$ids = [];
	foreach ($payments as $key => [$name, $type, $cents]) {
		$ids[$key] = data($entries->create(type: $type, amount: $cents / 100, description: $name,
			projectId: $key === 'shared' ? $area : null))['id'];
	}
	file_put_contents($stateFile, json_encode($ids, JSON_THROW_ON_ERROR));
} else {
	$ids = json_decode(file_get_contents($stateFile), true, flags: JSON_THROW_ON_ERROR);
	foreach ($payments as $key => [$name, $type, $cents]) {
		$row = data($entries->show($ids[$key]))['entry'];
		if ($row['description'] !== $name || $row['type'] !== $type || (int)round($row['amount'] * 100) !== $cents
			|| $row['entry_kind'] !== ($key === 'shared' ? 'shared' : 'personal')) {
			throw new RuntimeException("Could not reload payment $name with its original values.");
		}
	}
	$q = Server::get(IDBConnection::class)->getQueryBuilder();
	$result = $q->select('s.user_id', 's.amount_cents', 'p.user_id AS owner', 'p.amount_cents AS personal_amount', 'p.source_entry_id')
		->from('cobudget_entry_shares', 's')->innerJoin('s', 'cobudget_entries', 'p', $q->expr()->eq('p.id', 's.personal_entry_id'))
		->where($q->expr()->eq('s.entry_id', $q->createNamedParameter($ids['shared'], PDO::PARAM_INT)))
		->orderBy('s.user_id')->executeQuery();
	$shares = $result->fetchAll();
	$result->closeCursor();
	if (array_column($shares, 'user_id') !== ['release-admin', 'release-user']) throw new RuntimeException('Expected two participants.');
	foreach ($shares as $share) {
		if ($share['user_id'] !== $share['owner'] || (int)$share['amount_cents'] !== 500
			|| (int)$share['personal_amount'] !== 500 || (int)$share['source_entry_id'] !== (int)$ids['shared']) {
			throw new RuntimeException('Incorrect allocation or linked personal payment.');
		}
	}
}
echo "PASS payments {$argv[1]}\n";

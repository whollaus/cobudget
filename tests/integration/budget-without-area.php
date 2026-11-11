<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	fwrite(STDERR, "Usage: php tests/integration/budget-without-area.php --nextcloud=/path/to/local/server --user=admin\n");
	exit(1);
}
$root = realpath($options['nextcloud']);
if (!$root || !is_file($root . '/lib/base.php')) throw new RuntimeException('Nextcloud installation missing');
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $error): void {
	fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
	exit(1);
});

use OCA\CoBudget\Controller\BudgetController;
use OCP\Server;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IRequest;
use OCP\IRequestId;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;

$db = Server::get(IDBConnection::class);
$session = Server::get(IUserSession::class);
$originalUser = $session->getUser();
$user = Server::get(IUserManager::class)->get($options['user']);
if (!$user) throw new RuntimeException('Existing local test user required');
$session->setUser($user);
$uid = $user->getUID();
$assertions = 0;
$check = static function(bool $condition, string $message) use (&$assertions): void {
	if (!$condition) throw new RuntimeException($message);
	$assertions++;
};
$insert = static function(string $table, array $data) use ($db): int {
	$qb = $db->getQueryBuilder();
	$qb->insert('cobudget_' . $table);
	foreach ($data as $key => $value) $qb->setValue($key, $qb->createNamedParameter($value));
	$qb->executeStatement();
	return (int)$qb->getLastInsertId();
};
$fresh = static function(int $workspaceId): BudgetController {
	$request = new OC\AppFramework\Http\Request(['server' => ['HTTP_X_WORKSPACE_ID' => (string)$workspaceId]], Server::get(IRequestId::class), Server::get(IConfig::class));
	$reflection = new ReflectionClass(BudgetController::class);
	return $reflection->newInstanceArgs(array_map(static function(ReflectionParameter $parameter) use ($request): mixed {
		if ($parameter->getName() === 'appName') return 'cobudget';
		$type = $parameter->getType()->getName();
		return match ($type) {
			IRequest::class => $request,
			IL10N::class => Server::get(IFactory::class)->get('cobudget', 'en'),
			default => Server::get($type),
		};
	}, $reflection->getConstructor()->getParameters()));
};
$data = static function($response): array {
	if ($response->getStatus() >= 400) throw new RuntimeException('API failure: ' . json_encode($response->getData()));
	return $response->getData();
};
$db->beginTransaction();
try {
	$workspace = $insert('workspaces', ['name' => '__budget_unassigned__', 'user_id' => $uid]);
	$otherWorkspace = $insert('workspaces', ['name' => '__budget_other__', 'user_id' => $uid]);
	$area = $insert('projects', ['name' => '__budget_assigned__', 'owner_id' => $uid, 'workspace_id' => $workspace]);
	$otherArea = $insert('projects', ['name' => '__budget_other_area__', 'owner_id' => $uid, 'workspace_id' => $otherWorkspace]);
	$insert('members', ['project_id' => $area, 'user_id' => $uid, 'personal_workspace_id' => $workspace, 'share_basis_points' => 10000]);
	$category = $insert('categories', ['name' => '__budget_category__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace]);
	$base = ['type' => 'expense', 'entry_kind' => 'personal', 'user_id' => $uid, 'workspace_id' => $workspace, 'date' => time() - 1, 'currency' => 'EUR', 'category_id' => $category];
	foreach ([
		['amount_cents' => 101, 'is_important' => 1],
		['amount_cents' => 202],
		['amount_cents' => 303, 'project_id' => $area, 'is_important' => 1],
		['amount_cents' => 9999, 'type' => 'income'],
		['amount_cents' => 8888, 'user_id' => '__budget_foreign__'],
		['amount_cents' => 7777, 'workspace_id' => $otherWorkspace],
		['amount_cents' => 6666, 'project_id' => $area, 'entry_kind' => 'shared'],
	] as $entry) {
		$row = array_replace($base, $entry);
		$row['amount'] = $row['amount_cents'] / 100;
		$insert('entries', $row);
	}
	$controller = $fresh($workspace);
	$criteria = ['rules' => [['projectId' => 0, 'categoryId' => null, 'tag' => '']]];
	$goal = $data($controller->create('Without area fixture', 100, 'year', 'hard', $criteria));
	$check($goal['criteria'] === $criteria, 'Saving keeps zero distinct from the all-area wildcard');
	$check($goal['evaluation']['spent_cents'] === 303, 'Only unassigned owned expenses in the active workspace count');
	$goals = $data($fresh($workspace)->index());
	$loaded = array_values(array_filter($goals, static fn(array $item): bool => $item['id'] === $goal['id']))[0];
	$check($loaded['criteria'] === $criteria && $loaded['evaluation']['spent_cents'] === 303, 'Reloading preserves the criterion and its exact amount');
	$wildcard = $data($controller->create('All areas fixture', 100, 'year', 'hard'));
	$check($wildcard['evaluation']['spent_cents'] === 606, 'Existing no-criteria budgets still include assigned and unassigned expenses');
	$combined = ['rules' => [['projectId' => 0, 'categoryId' => $category, 'tag' => 'important']]];
	$updated = $data($controller->update($goal['id'], 'Without area fixture', 100, 'year', 'hard', $combined));
	$check($updated['criteria'] === $combined && $updated['evaluation']['spent_cents'] === 101, 'Area, category and label conditions combine using AND');
	$orCriteria = ['rules' => [...$criteria['rules'], ['projectId' => $area, 'categoryId' => null, 'tag' => '']]];
	$orGoal = $data($controller->create('Area OR unassigned', 100, 'year', 'hard', $orCriteria));
	$check($orGoal['evaluation']['spent_cents'] === 606, 'Separate unassigned and named-area rules combine using OR');
	$denied = $controller->create('Foreign area rejected', 100, 'year', 'hard', ['rules' => [['projectId' => $otherArea]]]);
	$check($denied->getStatus() === 400, 'The sentinel does not bypass authorization for real area IDs');
	$check($fresh($otherWorkspace)->update($goal['id'], 'Wrong workspace', 100, 'year', 'hard', $criteria)->getStatus() === 404, 'Budget writes remain workspace scoped');
	$qb = $db->getQueryBuilder();
	$qb->select('*')->from('cobudget_budget_goals')->where($qb->expr()->eq('id', $qb->createNamedParameter($goal['id'], \PDO::PARAM_INT)));
	$raw = $qb->executeQuery()->fetch();
	$snapshots = Server::get(OCA\CoBudget\Service\BudgetSnapshotService::class);
	$snapshot = $snapshots->snapshotGoalForCurrentPeriod($uid, $raw, 'update');
	$check((int)$snapshot['spent_cents'] === 101, 'Historical snapshots retain the unassigned/category/label intersection');
	$raw['criteria_json'] = json_encode($criteria);
	$snapshot = $snapshots->snapshotGoalForCurrentPeriod($uid, $raw, 'update');
	$check((int)$snapshot['spent_cents'] === 303, 'Historical snapshots count exactly the same unassigned expenses as live goals');
	$backup = Server::get(OCA\CoBudget\Service\BackupService::class);
	foreach (['sanitizeBudgetCriteriaJson' => [[ $area => true ], [ $category => true ]], 'sanitizePersonalBudgetCriteriaJson' => [[ $area => true ], [ $category => [ $workspace => $category ] ], $workspace], 'remapBudgetCriteriaJson' => [[ 0 => 999, $area => 900 ], [ $category => 901 ]]] as $method => $args) {
		$normalized = json_decode((new ReflectionMethod($backup, $method))->invoke($backup, json_encode($criteria), ...$args), true);
		$check($normalized === $criteria, $method . ' cannot drop or remap the Without area sentinel');
		$normalizedCombined = json_decode((new ReflectionMethod($backup, $method))->invoke($backup, json_encode($combined), ...$args), true);
		$expectedCategory = $method === 'remapBudgetCriteriaJson' ? 901 : $category;
		$check($normalizedCombined === ['rules' => [['projectId' => 0, 'categoryId' => $expectedCategory, 'tag' => 'important']]], $method . ' preserves area restriction while translating category IDs');
	}
	echo 'Budget Without area integration passed: ' . $assertions . " assertions; fixtures rolled back.\n";
} finally {
	$db->rollBack();
	$session->setUser($originalUser);
}

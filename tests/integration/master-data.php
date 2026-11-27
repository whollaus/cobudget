<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	fwrite(STDERR, "Usage: php tests/integration/master-data.php --nextcloud=/path/to/local/server --user=admin\n");
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

use OCA\CoBudget\Controller\EntryController;
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
$controllers = static function(int $workspaceId) use ($db, $session): array {
	$request = new OC\AppFramework\Http\Request(['server' => ['HTTP_X_WORKSPACE_ID' => (string)$workspaceId]], Server::get(IRequestId::class), Server::get(IConfig::class));
	$config = Server::get(IConfig::class);
	$l10n = Server::get(IFactory::class)->get('cobudget', 'en');
	$groups = Server::get(OCP\IGroupManager::class);
	$categories = new OCA\CoBudget\Controller\CategoryController('cobudget', $request, $db, $session, $config, $l10n, $groups);
	$partners = new OCA\CoBudget\Controller\PaymentPartnerController('cobudget', $request, $db, $session, $config, $l10n, $groups);
	$manager = new OCA\CoBudget\Controller\MasterDataController('cobudget', $request, $db, $session, $config, $l10n, $groups, $categories, $partners);
	return [$manager, $categories, $partners];
};
$data = static function($response): array {
	if ($response->getStatus() >= 400) throw new RuntimeException(json_encode($response->getData()));
	return $response->getData();
};
$db->beginTransaction();
try {
	$workspace = $insert('workspaces', ['name' => '__master_fixture__', 'user_id' => $uid]);
	$foreignWorkspace = $insert('workspaces', ['name' => '__foreign_master__', 'user_id' => '__master_foreign__']);
	$house = $insert('projects', ['name' => 'House fixture', 'owner_id' => $uid, 'workspace_id' => $workspace]);
	$flat = $insert('projects', ['name' => 'Flat fixture', 'owner_id' => $uid, 'workspace_id' => $workspace]);
	$readonly = $insert('projects', ['name' => 'Readonly fixture', 'owner_id' => '__master_foreign__', 'workspace_id' => $foreignWorkspace]);
	foreach ([$house, $flat, $readonly] as $project) $insert('members', ['project_id' => $project, 'user_id' => $uid, 'personal_workspace_id' => $workspace, 'share_basis_points' => 10000]);
	[$manager, $categories, $partners] = $controllers($workspace);
	$base = ['kind' => 'payment-partners', 'type' => 'expense'];
	$ops = [];
	foreach ([$house => 'House account', $flat => 'Flat account'] as $project => $note) {
		$ops[] = $base + ['action' => 'create', 'name' => '__Master Supplier__', 'projectId' => $project, 'ref' => '@p' . $project];
		$ops[] = $base + ['action' => 'update', 'id' => '@p' . $project, 'name' => '__Master Supplier__', 'details' => ['note' => $note, 'iban' => $note]];
	}
	$data($manager->save($ops));
	$getPartner = static function(int $project) use ($partners, $data): array {
		return array_values(array_filter($data($partners->settingsData($project)), static fn(array $row): bool => $row['name'] === '__Master Supplier__'))[0];
	};
	$housePartner = $getPartner($house); $flatPartner = $getPartner($flat);
	$check((int)$housePartner['id'] !== (int)$flatPartner['id'], 'Separate areas retain separate identities');
	$check($housePartner['note'] === 'House account' && $flatPartner['note'] === 'Flat account', 'Details remain separate');
	$data($manager->save([$base + ['action' => 'update', 'id' => $housePartner['id'], 'name' => '__Master Supplier__', 'details' => ['note' => 'Updated house']]]));
	$check($getPartner($house)['iban'] === 'House account', 'Partial update preserves omitted details');
	$check($getPartner($flat)['note'] === 'Flat account', 'Editing house never changes flat');
	$data($manager->save([$base + ['action' => 'hide', 'id' => $housePartner['id'], 'projectId' => $house]]));
	$check((bool)$getPartner($house)['is_hidden'], 'Area visibility is persisted');
	$check(!(bool)$getPartner($flat)['is_hidden'], 'Visibility does not leak between areas');
	$check(!in_array((int)$housePartner['id'], array_map('intval', array_column($data($partners->index($house)), 'id')), true), 'Hidden partner disappears from new payment choices');
	$data($manager->save([$base + ['action' => 'unhide', 'id' => $housePartner['id'], 'projectId' => $house]]));
	$check($getPartner($house)['note'] === 'Updated house', 'Reactivation preserves details');
	$check($manager->save([$base + ['action' => 'hide', 'id' => $housePartner['id'], 'projectId' => $flat]])->getStatus() === 404, 'Foreign-area record cannot be hidden');
	$check($manager->save([$base + ['action' => 'create', 'name' => 'Denied', 'projectId' => $readonly, 'ref' => '@denied']])->getStatus() === 403, 'Members cannot edit area settings');
	$before = count($data($partners->settingsData($house)));
	$response = $manager->save([
		$base + ['action' => 'create', 'name' => '__Rollback Supplier__', 'projectId' => $house, 'ref' => '@rollback'],
		$base + ['action' => 'update', 'id' => '@rollback', 'name' => '__Rollback Supplier__', 'details' => ['email' => 'invalid-email']],
	]);
	$check($response->getStatus() === 400, 'Invalid details reject the batch');
	$check(count($data($partners->settingsData($house))) === $before, 'Failed batch rolls back earlier creates');
	$check($manager->save([$base + ['action' => 'create', 'name' => '__Master Supplier__', 'projectId' => $house, 'ref' => '@duplicate']])->getStatus() === 409, 'Duplicate create cannot overwrite existing details');
	$check($getPartner($house)['note'] === 'Updated house', 'Duplicate failure preserves original details');
	$data($manager->save([
		['kind' => 'categories', 'action' => 'create', 'name' => '__Energy__', 'projectId' => $house, 'ref' => '@parent'],
		['kind' => 'categories', 'action' => 'create', 'name' => '__Power__', 'projectId' => $house, 'ref' => '@child'],
		['kind' => 'categories', 'action' => 'update', 'id' => '@child', 'name' => '__Power__', 'parentCategoryId' => '@parent'],
	]));
	$all = $data($categories->settingsData($house));
	$child = array_values(array_filter($all, static fn(array $row): bool => $row['name'] === '__Power__'))[0];
	$check((int)$child['parent_category_id'] > 0, 'Parent references resolve inside the same transaction');
	$data($categories->hide((int)$child['id'], $house));
	$check(!in_array((int)$child['id'], array_map('intval', array_column($data($categories->index($house)), 'id')), true), 'Area-local category can be hidden');
	$data($categories->unhide((int)$child['id'], $house));
	$check(in_array((int)$child['id'], array_map('intval', array_column($data($categories->index($house)), 'id')), true), 'Category reactivation keeps its identity');
	$check($controllers($foreignWorkspace)[0]->save([])->getStatus() === 403, 'Foreign workspace is rejected even for an empty batch');
	$duplicate = $insert('payment_partners', ['name' => '__Master Supplier__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace, 'project_id' => $house]);
	$data($partners->hide($duplicate, $house));
	$integrity = Server::get(OCA\CoBudget\Service\DataIntegrityService::class);
	$merged = $integrity->mergeDuplicate('payment_partner', (int)$housePartner['id'], [$duplicate]);
	$check($merged['areaVisibilityUpdated'] === 1, 'Duplicate cleanup remaps area partner visibility');
	$check((bool)$getPartner($house)['is_hidden'], 'Merged hidden partner stays hidden');
	$data($partners->destroy((int)$housePartner['id']));
	$qb = $db->getQueryBuilder();
	$qb->select('hidden_payment_partner_ids')->from('cobudget_projects')->where($qb->expr()->eq('id', $qb->createNamedParameter($house)));
	$check(json_decode($qb->executeQuery()->fetchOne(), true) === [], 'Deleting an unused hidden partner cleans its area visibility reference');
	// Whole-row deletion is atomic even if one area denies permission.
	foreach (['categories', 'payment-partners'] as $kind) {
		$table = $kind === 'categories' ? 'categories' : 'payment_partners';
		$own = $insert($table, ['name' => '__delete_row__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace]);
		$other = $insert($table, ['name' => '__delete_row__', 'type' => 'expense', 'user_id' => '__master_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $readonly]);
		$exists = static function(int $id) use ($db, $table): bool {
			$qb = $db->getQueryBuilder();
			$qb->select('id')->from('cobudget_' . $table)->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
			return $qb->executeQuery()->fetchOne() !== false;
		};
		$response = $manager->save([
			['kind' => $kind, 'action' => 'destroy', 'id' => $own],
			['kind' => $kind, 'action' => 'destroy', 'id' => $other],
		]);
		$check($response->getStatus() >= 400, $kind . ': members cannot delete owner-controlled shared records');
		$check($exists($own) && $exists($other), $kind . ': permission failure rolls back the entire deletion');
		$area = $insert($table, ['name' => '__delete_row__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace, 'project_id' => $flat]);
		$data($manager->save([
			['kind' => $kind, 'action' => 'destroy', 'id' => $own],
			['kind' => $kind, 'action' => 'destroy', 'id' => $area],
		]));
		$check(!$exists($own) && !$exists($area) && $exists($other), $kind . ': authorized row deletion removes all selected identities only');
	}
	// A settled projection may retain a master-data ID in another workspace.
	foreach (['categories' => 'category_id', 'payment-partners' => 'payment_partner_id'] as $kind => $reference) {
		$table = $kind === 'categories' ? 'categories' : 'payment_partners';
		$controller = $kind === 'categories' ? $categories : $partners;
		$usedId = $insert($table, ['name' => '__used_elsewhere__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace, 'project_id' => $flat]);
		$unusedId = $insert($table, ['name' => '__unused_delete__', 'type' => 'expense', 'user_id' => $uid, 'workspace_id' => $workspace]);
		$globalId = $insert($table, ['name' => '__used_global__', 'type' => 'expense', 'is_global' => true]);
		foreach ([$usedId, $globalId] as $id) $insert('entries', ['user_id' => '__master_foreign__', 'created_by' => '__master_foreign__', 'workspace_id' => $foreignWorkspace, 'date' => time(), 'amount' => 1, 'amount_cents' => 100, 'type' => 'expense', 'entry_kind' => 'personal', $reference => $id]);
		$catalog = $data($controller->settingsData($flat));
		$record = array_values(array_filter($catalog, static fn(array $row): bool => (int)$row['id'] === $usedId))[0];
		$check((bool)$record['in_use'], $kind . ': usage includes references outside the current workspace');
		$global = array_values(array_filter($data($controller->adminIndex()), static fn(array $row): bool => (int)$row['id'] === $globalId))[0];
		$check((bool)$global['in_use'], $kind . ': administrator catalog reports usage');
		$response = $manager->save([['kind' => $kind, 'action' => 'destroy', 'id' => $unusedId], ['kind' => $kind, 'action' => 'destroy', 'id' => $usedId]]);
		$check($response->getStatus() === 409, $kind . ': payment usage blocks deletion');
		$catalog = $data($controller->settingsData(null));
		$check(in_array($unusedId, array_map('intval', array_column($catalog, 'id')), true), $kind . ': used occurrence rolls back deletion of unused occurrences');
	}
	// Backup visibility validation accepts local identities only in their own area.
	$backup = Server::get(OCA\CoBudget\Service\BackupService::class);
	$validate = new ReflectionMethod($backup, 'assertProjectCategoryVisibilityConsistency');
	$validate->invoke($backup, ['cobudget_projects' => [['id' => $house, 'workspace_id' => $workspace, 'hidden_category_ids' => json_encode([(int)$child['id']]), 'hidden_payment_partner_ids' => json_encode([(int)$housePartner['id']])]], 'cobudget_categories' => [$child], 'cobudget_payment_partners' => [$housePartner]]);
	$check(true, 'Backups accept matching area visibility');
	try {
		$validate->invoke($backup, ['cobudget_projects' => [['id' => $flat, 'workspace_id' => $workspace, 'hidden_payment_partner_ids' => json_encode([(int)$housePartner['id']])]], 'cobudget_payment_partners' => [$housePartner]]);
		throw new RuntimeException('Foreign backup visibility accepted');
	} catch (InvalidArgumentException $expected) { $check(true, 'Backups reject foreign-area visibility'); }
	$restoreTables = [
		'cobudget_workspaces' => [['id' => $workspace, 'name' => '__restore_visibility__', 'user_id' => $uid]],
		'cobudget_projects' => [['id' => $house, 'name' => '__restore_area__', 'owner_id' => $uid, 'workspace_id' => $workspace, 'hidden_category_ids' => json_encode([(int)$child['id']]), 'hidden_payment_partner_ids' => json_encode([(int)$housePartner['id']])]],
		'cobudget_categories' => [['id' => $child['id'], 'name' => '__restore_category__', 'type' => 'expense', 'is_global' => false, 'user_id' => $uid, 'workspace_id' => $workspace, 'project_id' => $house]],
		'cobudget_payment_partners' => [array_diff_key($housePartner, ['in_use' => true])],
	];
	$restore = new ReflectionMethod($backup, 'insertTablesWithGeneratedIds');
	$maps = $restore->invoke($backup, $restoreTables);
	$qb = $db->getQueryBuilder();
	$qb->select('*')->from('cobudget_projects')->where($qb->expr()->eq('id', $qb->createNamedParameter($maps['cobudget_projects'][$house])));
	$restored = $qb->executeQuery()->fetch();
	$check(json_decode($restored['hidden_category_ids'], true) === [$maps['cobudget_categories'][(int)$child['id']]], 'Restore remaps local category visibility to generated IDs');
	$check(json_decode($restored['hidden_payment_partner_ids'], true) === [$maps['cobudget_payment_partners'][(int)$housePartner['id']]], 'Restore remaps partner visibility to generated IDs');
	// Admin master-data save: create, edit, hide/unhide, parent-child, delete, duplicate conflict, and authorization.
	$request = new OC\AppFramework\Http\Request([], Server::get(IRequestId::class), Server::get(IConfig::class));
	$config = Server::get(IConfig::class);
	$l10n = Server::get(IFactory::class)->get('cobudget', 'en');
	$groups = Server::get(OCP\IGroupManager::class);
	$session->setUser(null);
	$anonManager = new OCA\CoBudget\Controller\MasterDataController('cobudget', $request, $db, $session, $config, $l10n, $groups, $categories, $partners);
	$nonAdminUser = Server::get(IUserManager::class)->get('testuser');
	$session->setUser($nonAdminUser ?? $user);
	$nonAdminManager = new OCA\CoBudget\Controller\MasterDataController('cobudget', $request, $db, $session, $config, $l10n, $groups, $categories, $partners);
	if ($nonAdminUser === null) {
		$userIdProp = new ReflectionProperty(OCA\CoBudget\Controller\MasterDataController::class, 'userId');
		$userIdProp->setValue($nonAdminManager, '__non_admin_master__');
	}
	$session->setUser($user);
	$check($anonManager->adminSave([])->getStatus() === 401, 'Anonymous caller is rejected on adminSave');
	$check($nonAdminManager->adminSave([])->getStatus() === 403, 'Non-admin caller is rejected on adminSave');

	$data($manager->adminSave([
		['kind' => 'categories', 'action' => 'create', 'ref' => '@entry0', 'name' => '__Admin Global Category__', 'type' => 'expense', 'icon' => 'Tag'],
		['kind' => 'categories', 'action' => 'update', 'id' => '@entry0', 'name' => '__Admin Global Category__', 'code' => 'GC-100', 'parentCategoryId' => 0],
		['kind' => 'categories', 'action' => 'updateIcon', 'id' => '@entry0', 'icon' => 'Home'],
		['kind' => 'categories', 'action' => 'unhide', 'id' => '@entry0'],
	]));
	$findGlobalCategory = static function(string $name) use ($categories, $data): ?array {
		$matches = array_values(array_filter($data($categories->adminIndex()), static fn(array $row): bool => $row['name'] === $name));
		return $matches[0] ?? null;
	};
	$globalCat = $findGlobalCategory('__Admin Global Category__');
	$check($globalCat !== null && $globalCat['code'] === 'GC-100' && $globalCat['icon'] === 'Home' && !(bool)$globalCat['is_hidden'], 'adminSave creates global category with code, icon, and visible state');

	$data($manager->adminSave([
		['kind' => 'categories', 'action' => 'update', 'id' => (int)$globalCat['id'], 'name' => '__Admin Global Category Edited__', 'code' => 'GC-200', 'parentCategoryId' => 0],
		['kind' => 'categories', 'action' => 'updateIcon', 'id' => (int)$globalCat['id'], 'icon' => 'Car'],
		['kind' => 'categories', 'action' => 'hide', 'id' => (int)$globalCat['id']],
	]));
	$editedGlobalCat = $findGlobalCategory('__Admin Global Category Edited__');
	$check($editedGlobalCat !== null && $editedGlobalCat['code'] === 'GC-200' && $editedGlobalCat['icon'] === 'Car' && (bool)$editedGlobalCat['is_hidden'], 'adminSave edits and hides existing global category');

	$data($manager->adminSave([
		['kind' => 'categories', 'action' => 'unhide', 'id' => (int)$editedGlobalCat['id']],
	]));
	$check(!(bool)$findGlobalCategory('__Admin Global Category Edited__')['is_hidden'], 'adminSave unhides global category');

	$data($manager->adminSave([
		['kind' => 'categories', 'action' => 'create', 'ref' => '@child0', 'name' => '__Admin Global Child__', 'type' => 'expense', 'icon' => 'Tag'],
		['kind' => 'categories', 'action' => 'update', 'id' => '@child0', 'name' => '__Admin Global Child__', 'code' => 'GC-201', 'parentCategoryId' => (int)$editedGlobalCat['id']],
		['kind' => 'categories', 'action' => 'unhide', 'id' => '@child0'],
	]));
	$globalChild = $findGlobalCategory('__Admin Global Child__');
	$check($globalChild !== null && (int)$globalChild['parent_category_id'] === (int)$editedGlobalCat['id'], 'adminSave creates global subcategory with parent_category_id');
	$data($manager->adminSave([
		['kind' => 'categories', 'action' => 'destroy', 'id' => (int)$globalChild['id']],
		['kind' => 'categories', 'action' => 'destroy', 'id' => (int)$editedGlobalCat['id']],
	]));
	$check($findGlobalCategory('__Admin Global Child__') === null && $findGlobalCategory('__Admin Global Category Edited__') === null, 'adminSave deletes global categories');

	$data($manager->adminSave([
		['kind' => 'payment-partners', 'action' => 'create', 'ref' => '@entry0', 'name' => '__Admin Global Partner__', 'type' => 'expense'],
		['kind' => 'payment-partners', 'action' => 'update', 'id' => '@entry0', 'name' => '__Admin Global Partner__', 'details' => ['note' => 'Global note', 'iban' => 'DE89370400440532013000']],
		['kind' => 'payment-partners', 'action' => 'unhide', 'id' => '@entry0'],
	]));
	$findGlobalPartner = static function(string $name) use ($partners, $data): ?array {
		$matches = array_values(array_filter($data($partners->adminIndex()), static fn(array $row): bool => $row['name'] === $name));
		return $matches[0] ?? null;
	};
	$globalPartner = $findGlobalPartner('__Admin Global Partner__');
	$check($globalPartner !== null && $globalPartner['note'] === 'Global note' && $globalPartner['iban'] === 'DE89370400440532013000' && !(bool)$globalPartner['is_hidden'], 'adminSave creates global payment partner with details');

	$data($manager->adminSave([
		['kind' => 'payment-partners', 'action' => 'update', 'id' => (int)$globalPartner['id'], 'name' => '__Admin Global Partner Edited__', 'details' => ['note' => 'Updated global note']],
		['kind' => 'payment-partners', 'action' => 'hide', 'id' => (int)$globalPartner['id']],
	]));
	$editedGlobalPartner = $findGlobalPartner('__Admin Global Partner Edited__');
	$check($editedGlobalPartner !== null && $editedGlobalPartner['note'] === 'Updated global note' && $editedGlobalPartner['iban'] === 'DE89370400440532013000' && (bool)$editedGlobalPartner['is_hidden'], 'adminSave edits and hides existing global payment partner while preserving omitted details');

	$data($manager->adminSave([
		['kind' => 'payment-partners', 'action' => 'unhide', 'id' => (int)$editedGlobalPartner['id']],
	]));
	$check(!(bool)$findGlobalPartner('__Admin Global Partner Edited__')['is_hidden'], 'adminSave unhides global payment partner');

	$duplicateGlobalResponse = $manager->adminSave([
		['kind' => 'payment-partners', 'action' => 'create', 'ref' => '@dup0', 'name' => '__Admin Global Partner Edited__', 'type' => 'expense'],
	]);
	$check($duplicateGlobalResponse->getStatus() === 409, 'adminSave rejects duplicate global payment partner with 409');
	$data($manager->adminSave([
		['kind' => 'payment-partners', 'action' => 'destroy', 'id' => (int)$editedGlobalPartner['id']],
	]));
	$check($findGlobalPartner('__Admin Global Partner Edited__') === null, 'adminSave deletes global payment partner');

	fwrite(STDOUT, "Master-data integration passed: {$assertions} assertions (rolled back).\n");
} finally {
	$db->rollBack();
	$session->setUser($originalUser);
}

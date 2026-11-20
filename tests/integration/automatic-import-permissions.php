<?php
declare(strict_types=1);

// Included by the disposable integration fixture. Use the storage's actual listing order.
$permissionInbox = $files->newFolder($folder . '-permissions');
$permissionRule = $createRule($fixture['workspaces'][$actor], $permissionInbox->getName(), null, 'error');
for ($i = 0; $i < 13; $i++) {
	$permissionInbox->newFile("delivery-$i.json", $payload("permission-delivery-$i", 1));
}
$storage = $permissionInbox->getStorage();
$handle = $storage->opendir($permissionInbox->getInternalPath());
$names = [];
while (($name = readdir($handle)) !== false) {
	if (str_ends_with($name, '.json')) $names[] = $name;
}
closedir($handle);
expect(count($names) === 13, 'Expected 13 deliveries in storage order.');
$ready = array_pop($names);
$cache = $storage->getCache();
$permissions = [];
foreach ($names as $name) {
	$file = $permissionInbox->get($name);
	$permissions[$name] = $file->getPermissions();
	$cache->update($file->getId(), ['permissions' => $permissions[$name] & ~\OCP\Constants::PERMISSION_DELETE]);
	expect(!$permissionInbox->get($name)->isDeletable(), 'Fixture must remove the Files delete permission for ' . $name);
}
$notificationsBefore = count(AutoImportNotificationProbe::$received);
for ($tick = 0; $tick < 2; $tick++) {
	expect($runner->runRule($permissionRule) === 'checked', 'Twelve blocked files must leave the file allowance available.');
	expect($permissionInbox->nodeExists('Verarbeitet/' . $ready) && count($read('import_files', ['rule_id' => $permissionRule])) === 1,
		'Only the thirteenth file should have been journaled and archived.');
	foreach ($names as $name) expect($permissionInbox->nodeExists($name), 'An unmovable file must stay in the inbox.');
}
$status = $rules->forOwner($permissionRule, $actor, $fixture['workspaces'][$actor]);
expect($status['last_error'] && str_contains($status['last_message'], $names[0])
	&& count(AutoImportNotificationProbe::$received) === $notificationsBefore + 1, 'Report the first blocked file once across unchanged retries.');
foreach ($permissions as $name => $mask) {
	$cache->update($permissionInbox->get($name)->getId(), ['permissions' => $mask]);
}
for ($tick = 0; $tick < 4; $tick++) $runner->runRule($permissionRule);
foreach ([...$names, $ready] as $name) {
	$description = 'permission-' . basename($name, '.json') . '-1';
	expect($permissionInbox->nodeExists('Verarbeitet/' . $name) && !$permissionInbox->nodeExists($name)
		&& count($read('entries', ['description' => $description])) === 1, 'Expected exactly one payment after restoring permissions for ' . $name);
}
echo "Inbox permissions passed: 12 unmovable files, thirteenth payment booked immediately, one warning, all 13 archived and booked once after repair.\n";

// Put each obstruction first in the actual storage order and use separate filenames for each delivery.
foreach (['locked', 'archive'] as $case) {
	$box = $files->newFolder($folder . '-obstruction-' . $case);
	$id = $createRule($fixture['workspaces'][$actor], $box->getName(), null, 'none');
	foreach (['a', 'b'] as $suffix) {
		$prefix = $case . '-' . $suffix;
		$box->newFile($prefix . '.json', $payload($prefix, 1));
	}
	$handle = $box->getStorage()->opendir($box->getInternalPath());
	$ordered = [];
	while (($name = readdir($handle)) !== false) {
		if (str_ends_with($name, '.json')) $ordered[] = $name;
	}
	closedir($handle);
	[$first, $next] = $ordered;
	$blocked = $box->get($first);
	if ($case === 'locked') {
		$blocked->lock(\OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE);
	} else {
		$blocked->putContent('{"payments":[]}');
		$obstruction = $box->newFile('Fehler', 'Existing file');
	}
	try {
		for ($tick = 0; $tick < 2; $tick++) {
			if ($case === 'locked') {
				$worker = proc_open([PHP_BINARY, '-d', 'memory_limit=96M', __DIR__ . '/automatic-import.php',
					'--nextcloud=' . $root, '--phase=file-lock', '--rule=' . $id], [], $pipes);
				expect(is_resource($worker) && proc_close($worker) === 0, 'Separate process must check the folder while the lock is held.');
			} else {
				expect($runner->runRule($id) === 'checked', 'An unavailable error archive must not abort the scan.');
			}
			$status = $rules->forOwner($id, $actor, $fixture['workspaces'][$actor]);
			expect($status['last_error'] && $status['last_file']['id'] === $blocked->getId()
				&& ($case === 'locked' || str_contains($status['last_message'], 'Fehler')), 'Report the blocked file and archive destination.');
			expect($box->nodeExists($first) && $box->nodeExists('Verarbeitet/' . $next)
				&& !$read('entries', ['description' => basename($first, '.json') . '-1'])
				&& count($read('entries', ['description' => basename($next, '.json') . '-1'])) === 1,
				'The blocked file stays in place while the following file is booked exactly once.');
		}
	} finally {
		if ($case === 'locked') $blocked->unlock(\OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE);
		else $obstruction->delete();
	}
	$runner->runRule($id);
	$runner->runRule($id);
	$accepted = $case === 'locked';
	expect($box->nodeExists(($accepted ? 'Verarbeitet/' : 'Fehler/') . $first)
		&& count($read('entries', ['description' => basename($first, '.json') . '-1'])) === ($accepted ? 1 : 0)
		&& count($read('entries', ['description' => basename($next, '.json') . '-1'])) === 1,
		'After repair the held file receives the correct result and the successful delivery stays unique.');
}
echo "File isolation passed: exclusive Files lock and unavailable error archive, other deliveries booked once.\n";

$invalid = '{"payments":[{"date":"2026-01-01","amount":-2,"description":"precision-first"},'
	. '{"date":"2026-01-01","amount":-99999999.990000001,"description":"precision-second"}]}';
$permissionInbox->newFile('fractional-cents.json', $invalid);
$runner->runRule($permissionRule);
expect($permissionInbox->nodeExists('Fehler/fractional-cents.json') && !$read('entries', ['description' => 'precision-first'])
	&& !$read('entries', ['description' => 'precision-second']), 'Automatic preview must reject the whole delivery on fractional cents in the second row.');
echo "Amount precision passed: entire folder delivery rejected before booking.\n";

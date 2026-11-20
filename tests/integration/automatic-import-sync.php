<?php
declare(strict_types=1);

// Two Files processes share the same disposable Nextcloud storage and database.
$box = $files->newFolder($folder . '-concurrent-sync');
$id = $createRule($fixture['workspaces'][$actor], $box->getName(), $fixture['project']);
$original = $payload('during-sync', 50);
$source = $box->newFile('sync-test-delivery.json', $original);
$runner->runRule($id, 2);
$identities = static fn (string $prefix, int $count): array => $graph(['folder' => $prefix] + $fixture, $count, true);
$partial = $identities('during-sync', 33);
$cache = $box->getStorage()->getCache();
$mask = $source->getPermissions();
$originalEtag = $box->get('sync-test-delivery.json')->getEtag();
$cache->update($source->getId(), ['permissions' => $mask & ~\OCP\Constants::PERMISSION_DELETE]);
$runner->runRule($id);
$booked = $identities('during-sync', 50);
expect(array_slice($booked, 0, 33) === $partial && $box->nodeExists('sync-test-delivery.json'), 'Expected an unarchived, fully booked delivery.');
$selected = $box->newFile('sync-test-selected.json', $payload('before-edit', 1));
$replace = $box->newFile('sync-test-replaced.json', $payload('before-replacement', 1));
$replaceId = $replace->getId();
$untagged = $box->newFile('sync-test-untagged.json', $payload('before-untagged', 1));
$cache->update($untagged->getId(), ['etag' => '']);
expect($box->get('sync-test-untagged.json')->getEtag() === '', 'Expected a source without a version tag.');
$box->newFile('sync-test-independent.json', $payload('independent-sync', 1));
$changed = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
$changed['payments'][49]['amount'] = -11;
$changed = json_encode($changed, JSON_THROW_ON_ERROR);
$worker = proc_open([PHP_BINARY, '-d', 'memory_limit=96M', __DIR__ . '/automatic-import.php',
	'--nextcloud=' . $root, '--phase=sync-worker', '--rule=' . $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
expect(is_resource($worker), 'Could not start the concurrent import.');
try {
	stream_set_timeout($pipes[1], 20);
	expect(trim((string)fgets($pipes[1])) === 'RECONCILED', 'Worker did not reach file admission.');
	$box->get('sync-test-delivery.json')->putContent($changed);
	$cache->update($source->getId(), ['permissions' => $mask & ~\OCP\Constants::PERMISSION_DELETE, 'etag' => $originalEtag]);
	$box->newFile('sync-test-new-upload.json', $changed);
	$selected->putContent($changed);
	$untagged->putContent($changed);
	$cache->update($untagged->getId(), ['etag' => '']);
	$replace->delete();
	$replacement = $box->newFile('sync-test-replaced.json', $changed);
	expect($replacement->getId() !== $replaceId, 'Expected a replacement Files node.');
} finally {
	fclose($pipes[0]);
	fclose($pipes[1]);
	$exit = proc_close($worker);
}
expect($exit === 0, 'Concurrent worker failed.');
$independent = $identities('independent-sync', 1);
foreach (['sync-test-selected.json', 'sync-test-replaced.json', 'sync-test-untagged.json', 'sync-test-new-upload.json'] as $name) {
	expect($box->nodeExists($name), 'A changed or newly uploaded version entered the old selection: ' . $name);
}
expect($identities('during-sync', 50) === $booked, 'Concurrent sync duplicated a booked payment or changed its shares.');
$checked = $runner->runRule($id);
expect($checked === 'checked', 'Checking the updated delivery returned ' . $checked);
foreach (['sync-test-selected.json', 'sync-test-replaced.json', 'sync-test-untagged.json', 'sync-test-new-upload.json'] as $name) {
	expect($box->nodeExists('Fehler/' . $name), 'The new pass must reject ' . $name . '. Last result: ' . $read('import_rules', ['id' => $id])[0]['last_message']);
}
expect($box->nodeExists('sync-test-delivery.json') && $identities('during-sync', 50) === $booked,
	'Copies must be rejected while the booked source still lacks delete permission.');
$cache->update($source->getId(), ['permissions' => $mask]);
$runner->runRule($id);
expect($box->nodeExists('Fehler/sync-test-delivery.json'), 'Repair must archive the changed source as an error.');
$box->newFile('sync-test-unchanged-replay.json', $original);
$box->newFile('sync-test-changed-replay.json', $changed);
$runner->runRule($id);
expect($box->nodeExists('Verarbeitet/sync-test-unchanged-replay.json') && $box->nodeExists('Fehler/sync-test-changed-replay.json')
	&& $identities('during-sync', 50) === $booked && $identities('independent-sync', 1) === $independent,
	'Replay after repair must preserve all booked identities.');
$identities('before-edit', 0);
$identities('before-replacement', 0);
echo "Concurrent sync passed: upload, edit and replacement after reconciliation, independent delivery, 50 original payments and 150 shares after replay.\n";

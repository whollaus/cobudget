<?php
declare(strict_types=1);

$workspace = $fixture['workspaces'][$actor];
$project = $fixture['project'];
$box = $files->newFolder($folder . '-two-reports');
$id = $createRule($workspace, $box->getName(), $project);
$deliveryGraph = static fn (string $prefix, int $count): array => $graph(['folder' => $prefix] + $fixture, $count, true);

$first = $box->newFile('older-report.json', $payload('older-report', 50));
$runner->runRule($id, 2);
$deliveryGraph('older-report', 33);
$cache = $box->getStorage()->getCache();
$mask = $first->getPermissions();
$cache->update($first->getId(), ['permissions' => $mask & ~\OCP\Constants::PERMISSION_DELETE]);
$runner->runRule($id);
$olderIds = $deliveryGraph('older-report', 50);
expect($box->nodeExists('older-report.json'), 'The first booked file must still await archiving.');

$original = $payload('newer-report', 50);
$second = $box->newFile('newer-report.json', $original);
$runner->runRule($id, 2);
$partialIds = $deliveryGraph('newer-report', 33);
$second = $second->move($box->getPath() . '/edited-report.json');
$edited = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
$edited['payments'][49]['amount'] = -11;
$edited = json_encode($edited, JSON_THROW_ON_ERROR);
$second->putContent($edited);
$box->newFile('copy-of-edited-report.json', $edited);
$runner->runRule($id);
$laterIds = $deliveryGraph('newer-report', 50);
expect(array_slice($laterIds, 0, 33) === $partialIds && $box->nodeExists('Fehler/edited-report.json')
	&& $box->nodeExists('Fehler/copy-of-edited-report.json'), 'Changed source and copy must be rejected despite an older blocked report.');
$cache->update($first->getId(), ['permissions' => $mask]);
$box->newFile('replayed-report.json', $original);
$runner->runRule($id);
expect($box->nodeExists('Verarbeitet/older-report.json') && $box->nodeExists('Verarbeitet/replayed-report.json')
	&& $deliveryGraph('older-report', 50) === $olderIds && $deliveryGraph('newer-report', 50) === $laterIds,
	'Repair and replay must preserve every original, projection and allocation.');

// The first page is stale if a sync client edits one of its sources before the next check.
$box = $files->newFolder($folder . '-report-pages');
$id = $createRule($workspace, $box->getName(), $project);
$cache = $box->getStorage()->getCache();
$permissions = [];
$pagedOriginal = $payload('report-page-0', 50);
for ($i = 0; $i < 13; $i++) {
	$name = "report-page-$i";
	$file = $box->newFile($name . '.json', $i === 0 ? $pagedOriginal : $payload($name, 1));
	$runner->runRule($id, $i === 0 ? 2 : 1);
	if ($i === 0) $pagePartial = $deliveryGraph($name, 33);
	$permissions[$file->getId()] = $file->getPermissions();
	$cache->update($file->getId(), ['permissions' => $file->getPermissions() & ~\OCP\Constants::PERMISSION_DELETE]);
	$runner->runRule($id);
	expect($box->nodeExists($name . '.json'), 'The booked source must be waiting for its archive move.');
}
$pageIds = $deliveryGraph('report-page-0', 50);
expect(array_slice($pageIds, 0, 33) === $pagePartial && count($read('import_files', ['rule_id' => $id, 'state' => 'reporting'])) === 13
	&& (int)$read('import_rules', ['id' => $id])[0]['report_after'] > 0, 'The worker must have checkpointed a partial reconciliation.');

// New uploads on every tick must allow an existing selection to finish both source pages.
for ($tick = 1; $tick <= 6; $tick++) {
	$box->newFile("continuous-$tick.json", $payload("continuous-$tick", 1));
	$runner->runRule($id);
	if ($tick >= 3) {
		expect($box->nodeExists('Verarbeitet/continuous-' . ($tick - 2) . '.json'), 'Continuous uploads stalled a source page.');
		$deliveryGraph('continuous-' . ($tick - 2), 1);
	}
}
for ($tick = 0; $tick < 3; $tick++) $runner->runRule($id);
for ($i = 1; $i <= 6; $i++) $deliveryGraph("continuous-$i", 1);
expect($deliveryGraph('report-page-0', 50) === $pageIds, 'Continuous uploads changed an existing booking.');
echo "Continuous uploads passed: six new deliveries, 18 shares and 18 personal entries with 13 outstanding archives.\n";
// Start an additional paged pass so the following edit occurs between pages.
if ((int)$read('import_rules', ['id' => $id])[0]['report_after'] === 0) $runner->runRule($id);

$source = $box->get('report-page-0.json');
$cache->update($source->getId(), ['permissions' => $permissions[$source->getId()]]);
$source = $box->get('report-page-0.json')->move($box->getPath() . '/changed-first-page.json');
$pagedEdit = json_decode($pagedOriginal, true, flags: JSON_THROW_ON_ERROR);
$pagedEdit['payments'][49]['amount'] = -11;
$pagedEdit = json_encode($pagedEdit, JSON_THROW_ON_ERROR);
$source->putContent($pagedEdit);
$cache->update($source->getId(), ['permissions' => $permissions[$source->getId()] & ~\OCP\Constants::PERMISSION_DELETE]);
$box->newFile('first-page-copy.json', $pagedEdit);
$box->newFile('after-report-page.json', $payload('after-report-page', 1));
$cache->update($file->getId(), ['permissions' => $permissions[$file->getId()]]);
for ($tick = 0; $tick < 6; $tick++) {
	$runner->runRule($id);
	expect($deliveryGraph('report-page-0', 50) === $pageIds, 'A copy from an earlier page duplicated a booking.');
	if ($box->nodeExists('Verarbeitet/after-report-page.json') && $box->nodeExists('Fehler/first-page-copy.json')) break;
}
expect($box->nodeExists('Verarbeitet/after-report-page.json') && $box->nodeExists('Fehler/first-page-copy.json')
	&& $box->nodeExists('changed-first-page.json'), 'Reject the edited copy while the independent delivery continues.');
foreach ($permissions as $fileId => $mask) $cache->update($fileId, ['permissions' => $mask]);
for ($i = 0; $i < 4; $i++) $runner->runRule($id);
$box->newFile('page-original-replay.json', $pagedOriginal);
$box->newFile('page-changed-replay.json', $pagedEdit);
$runner->runRule($id);
expect($box->nodeExists('Fehler/changed-first-page.json') && $box->nodeExists('Fehler/page-changed-replay.json')
	&& $box->nodeExists('Verarbeitet/page-original-replay.json') && $deliveryGraph('report-page-0', 50) === $pageIds,
	'All 50 payments and 150 shares must keep their identities after repair and replay.');
for ($i = 1; $i < 13; $i++) {
	expect($box->nodeExists("Verarbeitet/report-page-$i.json"), 'Repair must finish every outstanding archive move.');
	$deliveryGraph("report-page-$i", 1);
}
echo "Changed source on an earlier page passed: 13 pending archives, independent delivery, 50 original payments and 150 shares after replay.\n";

$box = $files->newFolder($folder . '-prior-preview');
$id = $createRule($workspace, $box->getName(), $project);
$draftOriginal = $payload('prior-preview', 50);
$missingName = 'Category for prior preview';
$draft = $payload('prior-preview', 50, ['category' => $missingName]);
$box->newFile('prior-draft.json', $draft);
$runner->runRule($id);
expect($box->nodeExists('Fehler/prior-draft.json'), 'The rejected preview must not book any payments.');
$deliveryGraph('prior-preview', 0);
$box->newFile('prior-payments.json', $draftOriginal);
$runner->runRule($id);
$draftIds = $deliveryGraph('prior-preview', 50);
$insert('categories', ['name' => $missingName, 'type' => 'expense', 'user_id' => 'upgrade-admin',
	'project_id' => $project, 'workspace_id' => $fixture['workspaces']['upgrade-admin']]);
$box->newFile('prior-payments.json', $draft);
$runner->runRule($id);
expect($box->nodeExists('Fehler/prior-payments.json'), 'The booked name must reject the replacement.');
$box->newFile('prior-renamed-copy.json', $draft);
$box->newFile('prior-draft.json', $draft);
$box->newFile('prior-original-replay.json', $draftOriginal);
$runner->runRule($id);
$runner->runRule($id);
expect(!$box->nodeExists('prior-draft.json') && !$box->nodeExists('Verarbeitet/prior-draft.json')
	&& $box->nodeExists('Fehler/prior-renamed-copy.json') && $box->nodeExists('Verarbeitet/prior-original-replay.json')
	&& $deliveryGraph('prior-preview', 50) === $draftIds, 'A conflict must override both named and renamed preview replays.');
$box->newFile('prior-draft.json', $payload('valid-corrected-draft', 1));
$runner->runRule($id);
expect($box->nodeExists('Verarbeitet/prior-draft.json'), 'An unbooked draft filename remains available for a genuine correction.');
$deliveryGraph('valid-corrected-draft', 1);
echo "Prior rejected preview passed: changed content rejected under both names, corrected draft accepted, 50 original payments and 150 shares retained.\n";

$box = $files->newFolder($folder . '-blocked-rejections');
$id = $createRule($workspace, $box->getName(), null);
for ($i = 0; $i < 13; $i++) $box->newFile("queued-$i.json", $payload("queued-$i", 1));
// Choose the last file in the storage's actual enumeration order.
$handle = $box->getStorage()->opendir($box->getInternalPath());
$names = [];
while (($name = readdir($handle)) !== false) {
	if (str_ends_with($name, '.json')) $names[] = $name;
}
closedir($handle);
$healthy = array_pop($names);
$description = basename($healthy, '.json') . '-1';
foreach ($names as $name) {
	$box->get($name)->putContent(json_encode(['payments' => [['date' => '2026-01-01', 'amount' => 0, 'description' => $name]]], JSON_THROW_ON_ERROR));
}
$obstruction = $box->newFile('Fehler', 'occupied');
expect($runner->runRule($id) === 'more' && !$box->nodeExists('Verarbeitet/' . $healthy), 'First tick must exhaust its preview allowance.');
$runner->runRule($id);
$booked = $read('entries', ['description' => $description]);
expect($box->nodeExists('Verarbeitet/' . $healthy) && count($booked) === 1, 'The second tick must reach the healthy delivery.');
foreach ($names as $name) expect($box->nodeExists($name), 'Blocked rejection must stay in the inbox.');
$obstruction->delete();
for ($i = 0; $i < 3; $i++) $runner->runRule($id);
foreach ($names as $name) {
	expect($box->nodeExists('Fehler/' . $name) && $read('entries', ['description' => $name]) === [], 'Rejected files must move without booking.');
}
expect($read('entries', ['description' => $description]) === $booked, 'Draining rejected files changed the healthy booking.');

$box = $files->newFolder($folder . '-currency');
$id = $createRule($workspace, $box->getName(), $project);
$previous = $config->getUserValue($actor, 'cobudget', 'currency', '');
foreach (['after-preview' => 1, 'after-commit' => 2, 'legacy-checkpoint' => 2] as $prefix => $budget) {
	$config->setUserValue($actor, 'cobudget', 'currency', 'EUR');
	$box->newFile($prefix . '.json', $payload($prefix, 50));
	$runner->runRule($id, $budget);
	$deliveryGraph($prefix, $budget === 1 ? 0 : 33);
	if ($prefix === 'legacy-checkpoint') {
		$q = $db->getQueryBuilder();
		$q->update('cobudget_import_files')->set('currency', $q->createNamedParameter(null, \PDO::PARAM_NULL))
			->where($q->expr()->eq('rule_id', $q->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($q->expr()->eq('source_name', $q->createNamedParameter($prefix . '.json')))->executeStatement();
	}
	$config->setUserValue($actor, 'cobudget', 'currency', 'CHF');
	$runner->runRule($id);
	$deliveryGraph($prefix, 50);
	foreach (range(1, 50) as $index) {
		$rows = $read('entries', ['description' => $prefix . '-' . $index]);
		expect(array_unique(array_column($rows, 'currency')) === ['EUR'], 'Mixed currency in ' . $prefix . ' on row ' . $index);
	}
}
$box->newFile('next-currency.json', $payload('next-currency', 1));
$runner->runRule($id);
$deliveryGraph('next-currency', 1);
expect(array_unique(array_column($read('entries', ['description' => 'next-currency-1']), 'currency')) === ['CHF'],
	'A separate file must use the updated currency for all members.');
$config->setUserValue($actor, 'cobudget', 'currency', $previous);
echo "Automatic import queue regressions passed: two outstanding reports, blocked rejection backlog, and currency across preview, commit and legacy checkpoints.\n";

// A sync client changes a partly booked source and uploads the changed bytes again under another name.
foreach (['permission', 'lock', 'waiting-permission', 'waiting-lock'] as $case) {
	$locked = str_ends_with($case, 'lock');
	$waiting = str_starts_with($case, 'waiting');
	$prefix = 'protected-' . $case;
	$box = $files->newFolder($folder . '-' . $prefix);
	$id = $createRule($workspace, $box->getName(), $project);
	$category = $waiting ? $insert('categories', [
		'name' => $prefix, 'type' => 'expense', 'project_id' => $project,
		'workspace_id' => $fixture['workspaces']['upgrade-admin'], 'user_id' => 'upgrade-admin',
	]) : null;
	$setCategoryName = static function (string $name) use ($db, $category): void {
		$q = $db->getQueryBuilder();
		$q->update('cobudget_categories')->set('name', $q->createNamedParameter($name))
			->where($q->expr()->eq('id', $q->createNamedParameter($category, PDO::PARAM_INT)))->executeStatement();
	};
	$original = $payload($prefix, 50, $waiting ? ['category' => $prefix] : []);
	$file = $box->newFile($prefix . '.json', $original);
	$runner->runRule($id, 2);
	$first = $deliveryGraph($prefix, 33);
	if ($waiting) {
		$setCategoryName($prefix . '-unavailable');
		$runner->runRule($id);
		expect(count($rules->forOwner($id, $actor, $workspace)['waiting_results']) === 1, 'The partially booked file must wait for its category.');
	}
	$file = $file->move($box->getPath() . '/' . $prefix . '-edited.json');
	$changed = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
	$changed['payments'][49]['amount'] = -11;
	unset($changed['payments'][49]['category']);
	$changed = json_encode($changed, JSON_THROW_ON_ERROR);
	$file->putContent($changed);
	$copy = $prefix . '-copy.json';
	$box->newFile($copy, $changed);
	$other = 'unrelated-' . $case;
	$box->newFile($other . '.json', $payload($other, 1));

	$mask = $file->getPermissions();
	$cache = $box->getStorage()->getCache();
	if ($locked) $file->lock(\OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE);
	else $cache->update($file->getId(), ['permissions' => $mask & ~\OCP\Constants::PERMISSION_DELETE]);
	try {
		for ($tick = 0; $tick < 2; $tick++) {
			if ($locked) {
				$worker = proc_open([PHP_BINARY, '-d', 'memory_limit=96M', __DIR__ . '/automatic-import.php',
					'--nextcloud=' . $root, '--phase=changed-source-lock', '--rule=' . $id], [], $pipes);
				expect(is_resource($worker) && proc_close($worker) === 0, 'The locked changed source must defer new deliveries.');
			} else {
				$runner->runRule($id);
			}
			$beforeRelease = $deliveryGraph($prefix, $waiting ? 33 : 50);
			expect(array_slice($beforeRelease, 0, 33) === $first && $box->nodeExists($file->getName())
				&& !$box->nodeExists('Verarbeitet/' . $copy), 'The original snapshot must be completed exactly once.');
			$deliveryGraph($other, $locked ? 0 : 1);
			expect($box->nodeExists($locked ? $copy : 'Fehler/' . $copy), 'Wrong disposition for the changed copy.');
		}
	} finally {
		if ($locked) $file->unlock(\OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE);
		else $cache->update($file->getId(), ['permissions' => $mask]);
	}
	$runner->runRule($id);
	expect($box->nodeExists('Fehler/' . $copy)
		&& $box->nodeExists('Verarbeitet/' . $other . '.json'), 'Recovery must reject both changes and archive the independent payment.');
	if ($waiting) {
		expect($deliveryGraph($prefix, 33) === $first, 'Checking the changed source must preserve the pending snapshot.');
		$setCategoryName($prefix);
		$runner->runRule($id);
	}
	$complete = $deliveryGraph($prefix, 50);
	expect($box->nodeExists('Fehler/' . $file->getName()), 'The changed source must be rejected once the original is complete.');
	expect(array_slice($complete, 0, count($beforeRelease)) === $beforeRelease, 'Clearing the obstruction changed booked identities.');
	$otherIds = $deliveryGraph($other, 1);
	$box->newFile($prefix . '-original-replay.json', $original);
	$box->newFile($prefix . '-changed-replay.json', $changed);
	$runner->runRule($id);
	$runner->runRule($id);
	expect($box->nodeExists('Fehler/' . $prefix . '-changed-replay.json') && $box->nodeExists('Verarbeitet/' . $prefix . '-original-replay.json')
		&& $deliveryGraph($prefix, 50) === $complete && $deliveryGraph($other, 1) === $otherIds,
		'Recovery and repeated uploads must preserve all original payments, allocations and personal entries.');
}
echo "Blocked changed source passed: missing delete permission and exclusive file lock in reporting and waiting states, 50 original payments and 150 shares each after repair and replay.\n";

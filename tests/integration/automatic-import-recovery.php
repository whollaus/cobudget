<?php
declare(strict_types=1);

// Included by the disposable three-member fixture after its other deliveries have finished.
$renamedBatch = ['folder' => $folder . '-rename-only'] + $fixture;
$batch = $payload($renamedBatch['folder'], 50);
$originalName = 'before-rename.json';
$archiveName = 'after-rename.json';
$file = $inbox->newFile($originalName, $batch);
$runner->runRule($rule, 2);
$first = $graph($renamedBatch, 33, true);
$config->setUserValue($actor, 'cobudget', 'enable_payment_imports', 'no');
expect(!in_array($rule, array_column($rules->due(time() + 86400), 'id'), true), 'Disabled accounts must not occupy a scheduled work slot.');
expect($runner->runRule($rule) === 'skipped' && $manualStart()->getStatus() === 403 && $inbox->nodeExists($originalName)
	&& $graph($renamedBatch, 33, true) === $first, 'Disabling imports must pause scheduled and manual starts at the saved checkpoint.');
$config->setUserValue($actor, 'cobudget', 'enable_payment_imports', 'yes');
expect(in_array($rule, array_column($rules->due(time() + 86400), 'id'), true), 'Re-enabling restores eligible rules to the schedule.');
$file->move($inbox->getPath() . '/' . $archiveName);
$runner->runRule($rule);
$complete = $graph($renamedBatch, 50, true);
expect(array_slice($complete, 0, 33) === $first && $inbox->nodeExists('Verarbeitet/' . $archiveName),
	'Renaming during booking must preserve the first packet and archive the completed delivery under its new name.');
foreach ([$archiveName => '2026-01-02', $originalName => '2026-01-03'] as $name => $date) {
	$inbox->newFile($name, str_replace('2026-01-01', $date, $batch));
	$runner->runRule($rule);
	$status = $read('import_rules', ['id' => $rule])[0];
	expect($inbox->nodeExists('Fehler/' . $name) && str_contains($status['last_message'], $name)
		&& str_contains($status['last_message'], '50') && $graph($renamedBatch, 50, true) === $complete,
		'Both names must reject changed dates and preserve all 50 payments, allocations and projections.');
}
echo "Mid-import rename passed: both names reserved, 50 payments and 150 shares booked once.\n";

// A sync client can both rename and edit the original node, then immediately reuse its old name.
$collision = ['folder' => $folder . '-rename-replaced'] + $fixture;
$originalName = 'before-replacement.json';
$renamedName = 'changed-after-rename.json';
$batch = $payload($collision['folder'], 50);
$file = $inbox->newFile($originalName, $batch);
$runner->runRule($rule, 2);
$first = $graph($collision, 33, true);
$renamed = $file->move($inbox->getPath() . '/' . $renamedName);
$changed = json_decode($batch, true, flags: JSON_THROW_ON_ERROR);
$changed['payments'][49]['amount'] = -11;
$changed = json_encode($changed, JSON_THROW_ON_ERROR);
$renamed->putContent($changed);
$replacement = $payload('occupied-original-name', 1);
$inbox->newFile($originalName, $replacement);
$runner->runRule($rule);
$complete = $graph($collision, 50, true);
expect(array_slice($complete, 0, 33) === $first && $inbox->nodeExists('Fehler/' . $originalName)
	&& $inbox->nodeExists('Fehler/' . $renamedName) && !$read('entries', ['description' => 'occupied-original-name-1']),
	'Both changed deliveries must be rejected after completing only the saved original.');
foreach (['changed-replay.json' => $changed, 'original-replay.json' => $batch, $renamedName => $payload('reserved-rename', 1)] as $name => $json) {
	$inbox->newFile($name, $json);
	$runner->runRule($rule);
}
expect($graph($collision, 50, true) === $complete && $inbox->nodeExists('Verarbeitet/original-replay.json')
	&& $inbox->nodeExists('Fehler/changed-replay.json') && !$read('entries', ['description' => 'reserved-rename-1']),
	'Replays must preserve all payment, share and projection IDs and the new filename reservation.');
echo "Occupied original filename passed: 50 payments, 150 shares and all projections preserved across replays.\n";

// Use a separate inbox so removing it leaves the other test archives intact.
$path = $folder . '-recreated';
$oldInbox = $files->newFolder($path);
$oldId = (int)$oldInbox->getId();
$workspace = $fixture['workspaces'][$actor];
$project = $fixture['project'];
$id = $createRule($workspace, $path, $project);
$replacementBatch = ['folder' => $path] + $fixture;
$batch = $payload($path, 50);
$oldInbox->newFile('recovery.json', $batch);
$runner->runRule($id, 2);
$first = $graph($replacementBatch, 33, true);
$oldInbox->delete();
$newInbox = $files->newFolder($path);
$newId = (int)$newInbox->getId();
expect($oldId !== $newId && $runner->runRule($id) === 'error', 'The worker must stop at a replaced inbox.');
$update = static fn (string $target, bool $enabled, ?int $selected = null) =>
	$asMember($workspace, fn ($api) => $api->update($id, $target, 5, 'none', $enabled, 'Europe/Vienna', $project, $selected));
expect($update($path, true)->getStatus() === 409 && $update($path, true, $oldId)->getStatus() === 409,
	'Ordinary saves and a stale selection cannot authorize a replacement.');
expect($asMember($workspace, fn ($api) => $api->destroy($id))->getStatus() === 409,
	'An outstanding snapshot must survive rule removal attempts.');
$otherInbox = $files->newFolder($path . '-other');
expect($update($otherInbox->getName(), true, (int)$otherInbox->getId())->getStatus() === 409,
	'A pending delivery cannot be redirected to another path.');
expect($update($path, false)->getData()['folder_id'] === $oldId, 'Pausing must keep the saved folder identity.');
$confirmed = $update($path, false, $newId);
expect($confirmed->getStatus() === 200 && $confirmed->getData()['folder_id'] === $newId && !$confirmed->getData()['enabled']
	&& $graph($replacementBatch, 33, true) === $first, 'Picking the replacement while paused must preserve the exact checkpoint.');
expect($update($path, true)->getStatus() === 200 && $runner->runRule($id) === 'checked',
	'After confirmation, the worker must finish the saved snapshot in the empty replacement folder.');
$complete = $graph($replacementBatch, 50, true);
expect(array_slice($complete, 0, 33) === $first, 'Recovery must add only 17 payments, 51 shares and 51 personal entries.');
$newInbox->newFile('recovery.json', $batch);
$runner->runRule($id);
expect($newInbox->nodeExists('Verarbeitet/recovery.json') && $graph($replacementBatch, 50, true) === $complete,
	'Uploading the original again after recovery must preserve all payment, share and projection IDs.');
echo "Replacement folder passed: confirmed node, saved snapshot, 17 payments and 51 shares recovered exactly once.\n";

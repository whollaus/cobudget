<?php
declare(strict_types=1);

$state = $base . '/archive-ownership-rules.json';
if ($phase === 'ownership-prepare') {
	$ids = [];
	foreach (['moved', 'removed', 'overwritten', 'replaced'] as $case) {
		$prefix = 'ownership-' . $case;
		$box = $files->newFolder($folder . '-' . $prefix);
		$ids[$case] = $createRule($fixture['workspaces'][$actor], $box->getName(), $fixture['project']);
		$box->newFile($prefix . '.json', $payload($prefix, 50));
	}
	file_put_contents($state, json_encode($ids, JSON_THROW_ON_ERROR));
	return;
}

$locks = \OCP\Server::get(\OCP\Lock\ILockingProvider::class);
if (method_exists($locks, 'cleanExpiredLocks')) $locks->cleanExpiredLocks();
$id = (int)$options['rule'];
$case = array_search($id, json_decode(file_get_contents($state), true, flags: JSON_THROW_ON_ERROR), true);
expect($case !== false, 'Unknown interrupted archive rule.');
$prefix = 'ownership-' . $case;
$name = $prefix . '.json';
$box = $files->get($folder . '-' . $prefix);
$archive = $box->get('Verarbeitet');
$row = $read('import_files', ['rule_id' => $id, 'state' => 'reporting'])[0];
$original = $payload($prefix, 50);
expect($row['archive_name'] === $name && $row['archive_hash'] === hash('sha256', $original),
	'Worker must save the fingerprint of the booked file.');
$originalIds = $graph(['folder' => $prefix] + $fixture, 50, true);
$independent = 'new-payment-' . $case;
$contents = $payload($independent, 1);
$afterMove = in_array($case, ['overwritten', 'replaced'], true);
$location = $afterMove ? $archive : $box;
$source = $location->get($name);
expect($source->getId() === (int)$row['detail'] && $source->getContent() === $original
	&& ($afterMove ? !$box->nodeExists($name) : !$archive->nodeExists($name)), 'Unexpected Files state at interruption.');
if ($case === 'moved') {
	$kept = $files->newFolder($folder . '-kept');
	$keptFile = $source->move($kept->getPath() . '/original.json');
	expect($keptFile->getId() === (int)$row['detail'], 'Original Files ID must survive the move.');
} elseif ($case === 'overwritten') {
	$source->putContent($contents);
} else {
	$source->delete();
}
if ($case !== 'overwritten') $archive->newFile($name, $contents);
$otherId = $archive->get($name)->getId();
expect(($otherId === (int)$row['detail']) === ($case === 'overwritten'), 'Unexpected Files ID for ' . $case);
$newName = $independent . '.json';
$box->newFile($newName, $contents);
$runner->runRule($id);
expect($archive->get($name)->getId() === $otherId && $archive->get($name)->getContent() === $contents
	&& !$box->nodeExists('Fehler/' . $name), 'Recovery moved or modified an independent archive file.');
expect(count($read('import_files', ['rule_id' => $id, 'state' => 'done'])) === 2
	&& $read('import_files', ['rule_id' => $id, 'state' => 'conflict']) === [], 'Recovery must close the intent without reserving the independent payload.');
if ($case === 'moved') expect($kept->get('original.json')->getContent() === $payload($prefix, 50), 'Recovery modified the original.');

$independentIds = $graph(['folder' => $independent] + $fixture, 1, true);
$box->newFile($newName, $contents);
$runner->runRule($id);
expect($archive->get($newName)->getContent() === $contents && !$box->nodeExists($newName)
	&& $graph(['folder' => $independent] + $fixture, 1, true) === $independentIds
	&& $graph(['folder' => $prefix] + $fixture, 50, true) === $originalIds,
	'Fresh upload or replay changed the original or independent payment identities.');
echo "Archive ownership passed ($case): 50 original payments retained; independent payment and three shares booked once.\n";

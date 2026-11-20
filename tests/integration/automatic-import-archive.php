<?php
declare(strict_types=1);

// The disposable runner pauses a second PHP process immediately before and after its Files move.
foreach (['etag', 'crash', 'changed-node', 'moved-archive'] as $case) {
	$prefix = 'archive-race-' . $case;
	$name = $prefix . '.json';
	$box = $files->newFolder($folder . '-' . $prefix);
	$id = $createRule($fixture['workspaces'][$actor], $box->getName(), $fixture['project']);
	$archive = $box->newFolder('Verarbeitet');
	$archive->newFile($name, 'Existing archive entry');
	$original = $payload($prefix, 50);
	$file = $box->newFile($name, $original);
	$etag = $file->getEtag();
	$changed = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
	$changed['payments'][49]['amount'] = -11;
	$changed = json_encode($changed, JSON_THROW_ON_ERROR);
	$ids = static fn (int $count): array => $graph(['folder' => $prefix] + $fixture, $count, true);
	$worker = proc_open([PHP_BINARY, '-d', 'memory_limit=96M', __DIR__ . '/automatic-import.php',
		'--nextcloud=' . $root, '--phase=archive-worker', '--rule=' . $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
	expect(is_resource($worker), 'Could not start archive worker.');
	$crash = $case !== 'etag';
	try {
		stream_set_timeout($pipes[1], 20);
		expect(trim((string)fgets($pipes[1])) === 'BEFORE_MOVE', 'Missing pre-move checkpoint.');
		$booked = $ids(50);
		$file->putContent($changed);
		// Files may assign the same ETag to rapid edits. Exercise both metadata outcomes deterministically.
		$box->getStorage()->getCache()->update($file->getId(), ['etag' => $crash ? md5('replaced-' . $etag) : $etag]);
		expect(($box->get($name)->getEtag() === $etag) === !$crash, 'Incorrect ETag for the archive test.');
		if ($case !== 'moved-archive') $box->newFile($prefix . '-copy.json', $changed);
		fwrite($pipes[0], "move\n");
		expect(trim((string)fgets($pipes[1])) === 'AFTER_MOVE', 'Missing post-move checkpoint.');
		expect(count($read('import_files', ['rule_id' => $id, 'state' => 'conflict'])) === 1, 'The conflicting payments must be recorded before archive verification.');
		$journal = $read('import_files', ['rule_id' => $id, 'state' => 'archiving'])[0];
		$target = $journal['archive_name'];
		expect($target !== $name && $archive->get($target)->getContent() === $changed, 'The move must preserve the earlier archive entry.');
	} finally {
		if ($crash) proc_terminate($worker, 9);
		fclose($pipes[0]);
		fclose($pipes[1]);
		$exit = proc_close($worker);
	}
	if ($crash) {
		// Same short Files lock expiry as the other SIGKILL recovery fixtures.
		expect($exit !== 0, 'The interrupted worker unexpectedly finished.');
		sleep(11);
		$provider = \OCP\Server::get(\OCP\Lock\ILockingProvider::class);
		if (method_exists($provider, 'cleanExpiredLocks')) $provider->cleanExpiredLocks();
		if ($case === 'moved-archive') {
			$monthly = $files->newFolder($folder . '-monthly');
			$kept = $archive->get($target)->move($monthly->getPath() . '/' . $target);
			$keptId = $kept->getId();
			$box->newFile($prefix . '-copy.json', $changed);
		}
		if ($case === 'changed-node') {
			$movedId = $archive->get($target)->getId();
			$archive->get($target)->delete();
			expect($archive->newFile($target, $changed)->getId() !== $movedId, 'Expected a new archive node ID.');
		}
	} else {
		expect($exit === 0, 'Archive verification failed.');
	}
	$runner->runRule($id);
	expect($archive->get($name)->getContent() === 'Existing archive entry' && !$archive->nodeExists($target)
		&& $box->nodeExists('Fehler/' . $prefix . '-copy.json'), 'Both changed versions must be rejected for ' . $case);
	if ($case === 'moved-archive') {
		expect($monthly->get($target)->getId() === $keptId && $monthly->get($target)->getContent() === $changed
			&& !$box->nodeExists('Fehler/' . $target), 'The relocated archive must stay in the monthly folder.');
	} else {
		expect($box->get('Fehler/' . $target)->getContent() === $changed, 'Expected the changed archive in the error folder.');
	}
	$status = $rules->forOwner($id, $actor, $fixture['workspaces'][$actor]);
	expect($status['last_error'] && str_contains($status['last_message'], $name) && str_contains($status['last_message'], '50'),
		'The error must identify the booked delivery and its count.');
	expect(count($read('import_files', ['rule_id' => $id, 'state' => 'conflict'])) === 1, 'Expected one durable conflict.');
	$box->newFile($prefix . '-original-replay.json', $original);
	$reordered = json_decode($changed, true, flags: JSON_THROW_ON_ERROR);
	$reordered['payments'] = array_map(static fn (array $payment) => array_reverse($payment, true), $reordered['payments']);
	$box->newFile($prefix . '-changed-replay.json', json_encode($reordered, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
	$runner->runRule($id);
	expect($ids(50) === $booked && $box->nodeExists('Verarbeitet/' . $prefix . '-original-replay.json')
		&& $box->nodeExists('Fehler/' . $prefix . '-changed-replay.json'), 'Replay changed booking identities for ' . $case);
	echo "Archive race passed ($case): 50 original payments, 150 shares and 150 personal entries retained their IDs.\n";
}

// Change Files from the parent process after the worker chose its target, before the locked storage rename.
foreach (['success-collision', 'error-collision', 'correct-date', 'correct-json'] as $case) {
	$prefix = 'archive-' . $case;
	$name = $prefix . '.json';
	$box = $files->newFolder($folder . '-' . $prefix);
	$id = $createRule($fixture['workspaces'][$actor], $box->getName(), $fixture['project']);
	$valid = $payload($prefix, 50);
	$draft = match ($case) {
		'success-collision' => $valid,
		'correct-json' => '{"payments":',
		default => str_replace('2026-01-01', '2026-02-31', $valid),
	};
	$source = $box->newFile($name, $draft);
	$collision = str_ends_with($case, '-collision');
	$archive = $box->newFolder($case === 'success-collision' ? 'Verarbeitet' : 'Fehler');
	$ids = static fn (int $count): array => $graph(['folder' => $prefix] + $fixture, $count, true);
	$worker = proc_open([PHP_BINARY, '-d', 'memory_limit=96M', __DIR__ . '/automatic-import.php',
		'--nextcloud=' . $root, '--phase=archive-worker', '--rule=' . $id,
		'--expected=' . ($collision ? 'more' : 'checked')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
	expect(is_resource($worker), 'Could not start archive worker.');
	try {
		stream_set_timeout($pipes[1], 20);
		expect(trim((string)fgets($pipes[1])) === 'BEFORE_MOVE', 'Missing Files checkpoint for ' . $case);
		$booked = $ids($case === 'success-collision' ? 50 : 0);
		if ($collision) {
			$other = $archive->newFile($name, 'Independent synced file');
			$otherId = $other->getId();
		} else {
			$etag = $source->getEtag();
			$source->putContent($valid);
			$box->getStorage()->getCache()->update($source->getId(), ['etag' => $etag]);
			expect($box->get($name)->getEtag() === $etag, 'Expected the original ETag.');
		}
	} finally {
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$exit = proc_close($worker);
	}
	expect($exit === 0 && $output === '' && $box->nodeExists($name), 'The raced move should leave the source in place for ' . $case);
	$runner->runRule($id);
	if ($collision) {
		expect($archive->get($name)->getId() === $otherId && $archive->get($name)->getContent() === 'Independent synced file'
			&& count($archive->getDirectoryListing()) === 2 && $archive->get($prefix . ' (2).json')->getContent() === $draft
			&& !$box->nodeExists($name), 'Both archive payloads must survive ' . $case);
		expect($ids($case === 'success-collision' ? 50 : 0) === $booked, 'Archive retry changed booking identities.');
	} else {
		expect(!$box->nodeExists($name) && !$archive->nodeExists($name) && $box->get('Verarbeitet/' . $name)->getContent() === $valid,
			'Corrected bytes must reach the processed archive.');
		$booked = $ids(50);
		$box->newFile($name, $valid);
		$runner->runRule($id);
		expect($ids(50) === $booked && !$box->nodeExists($name), 'Replay changed corrected payment or allocation identities.');
	}
	echo "Archive race passed ($case).\n";
}

// Files uses moveFromStorage when an archive directory is on a different mount.
$sourceFolder = $files->newFolder($folder . '-cross-storage');
$otherHome = \OCP\Server::get(\OCP\Files\IRootFolder::class)->getUserFolder('upgrade-admin');
$destination = $otherHome->newFolder($folder . '-cross-storage');
expect($sourceFolder->getStorage()->getId() !== $destination->getStorage()->getId(), 'Expected two distinct storages.');
$filename = 'cross-storage.json';
$sourceFile = $sourceFolder->newFile($filename, 'corrected');
$destination->newFile($filename, 'previous archive');
$hash = hash('sha256', 'corrected');
try {
	\OCA\CoBudget\Service\AutoImportArchiveMove::move($sourceFile, $destination, $filename, $hash);
	throw new RuntimeException('Cross-storage move overwrote the archive.');
} catch (\OCP\Files\AlreadyExistsException) {
	expect($destination->get($filename)->getContent() === 'previous archive', 'Archive bytes changed.');
}
$freeName = $destination->getNonExistingName($filename);
try {
	\OCA\CoBudget\Service\AutoImportArchiveMove::move($sourceFile, $destination, $freeName, hash('sha256', 'old'));
	throw new RuntimeException('Cross-storage move archived a corrected file.');
} catch (\OCP\Files\NotFoundException) {
	expect($sourceFolder->get($filename)->getContent() === 'corrected' && !$destination->nodeExists($freeName), 'Corrected file left the inbox.');
}
$moved = \OCA\CoBudget\Service\AutoImportArchiveMove::move($sourceFile, $destination, $freeName, $hash);
expect(!$sourceFolder->nodeExists($filename) && $moved->getContent() === 'corrected', 'Cross-storage retry failed.');
echo "Cross-storage archive passed: collision, corrected source and successful retry.\n";

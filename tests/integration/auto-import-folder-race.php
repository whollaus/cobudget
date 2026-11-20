<?php
declare(strict_types=1);

// Loaded by auto-import-rules.php in a disposable installation. Each phase has its own DB connection.
$case = $options['race'];
$phase = $options['phase'] ?? '';
$assert(in_array($case, ['create', 'update'], true), 'Unknown folder race scenario.');
$base = dirname($root);
$marker = json_decode(file_get_contents($base . '/test-instance.json'), true, flags: JSON_THROW_ON_ERROR);
$assert($marker['data'] === realpath($config->getSystemValue('datadirectory'))
	&& $marker['data'] === $base . '/data'
	&& ($marker['database'] === 'sqlite' ? 'sqlite3' : $marker['database']) === $config->getSystemValue('dbtype')
	&& ($marker['database'] === 'sqlite' || $marker['dbname'] === $config->getSystemValue('dbname')), 'Expected disposable database and Files storage.');
$dir = $base . '/folder-race-' . $case;
$stateFile = $dir . '/fixture.json';

if ($phase === 'prepare') {
	mkdir($dir);
	$folder = $home->newFolder('Folder race ' . $case);
	$workspace = $insert('workspaces', ['user_id' => $member->getUID(), 'name' => 'Folder race ' . $case]);
	$ownerWorkspace = $insert('workspaces', ['user_id' => $owner->getUID(), 'name' => 'Folder race ' . $case]);
	$api = $controller($member, $workspace);
	$projects = [];
	$ruleIds = [];
	foreach (['first', 'second'] as $side) {
		$project = $insert('projects', ['name' => $side, 'owner_id' => $owner->getUID(), 'workspace_id' => $ownerWorkspace]);
		$insert('members', ['project_id' => $project, 'user_id' => $owner->getUID(), 'personal_workspace_id' => $ownerWorkspace, 'share_basis_points' => 5000]);
		$insert('members', ['project_id' => $project, 'user_id' => $member->getUID(), 'personal_workspace_id' => $workspace, 'share_basis_points' => 5000]);
		$projects[$side] = $project;
		if ($case === 'update') {
			$ruleIds[$side] = $response($api->create('', 15, 'none', false, 'UTC', $project), 201)['id'];
		}
	}
	$state = ['workspace' => $workspace, 'projects' => $projects, 'rules' => $ruleIds,
		'folder' => $folder->getName(), 'folder_id' => (int)$folder->getId()];
	file_put_contents($stateFile, json_encode($state, JSON_THROW_ON_ERROR));
	return;
}

$state = json_decode(file_get_contents($stateFile), true, flags: JSON_THROW_ON_ERROR);
$api = $controller($member, $state['workspace']);
if (isset($state['projects'][$phase])) {
	// The disposable service copy calls this immediately before its INSERT/UPDATE, after the folder check.
	define('FOLDER_RACE_READY', "$dir/$phase.ready");
	define('FOLDER_RACE_PEER', $dir . ($phase === 'first' ? '/second.ready' : '/first.ready'));
	function awaitFolderSelectionPeer(): void {
		if (!touch(FOLDER_RACE_READY)) throw new RuntimeException('Cannot signal folder selection.');
		$deadline = hrtime(true) + 30_000_000_000;
		while (!is_file(FOLDER_RACE_PEER)) {
			if (hrtime(true) >= $deadline) throw new RuntimeException('Other request did not reach the folder write.');
			usleep(10000);
		}
	}
	$args = [$state['folder'], 60, 'none', false, 'UTC', $state['projects'][$phase]];
	$result = $case === 'create' ? $api->create(...$args) : $api->update($state['rules'][$phase], ...$args);
	file_put_contents("$dir/$phase.json", json_encode([$result->getStatus(), $result->getData()], JSON_THROW_ON_ERROR));
	return;
}

$assert($phase === 'verify', 'Unknown folder race phase.');
$q = $db->getQueryBuilder();
$result = $q->selectAlias($q->func()->count('*'), 'assignments')->from('cobudget_import_rules')
	->where($q->expr()->eq('folder_id', $q->createNamedParameter($state['folder_id'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
	->executeQuery();
try {
	$count = (int)$result->fetchOne();
} finally {
	$result->closeCursor();
}
$assert($count === 1, "Expected one stored folder assignment after simultaneous $case, found $count.");
$rows = $response($api->index(), 200);
$assert(count($rows) === ($case === 'create' ? 1 : 2), 'Unexpected number of saved rules after simultaneous ' . $case);
$byProject = array_column($rows, null, 'project_id');
$success = $case === 'create' ? 201 : 200;
$statuses = [];
foreach ($state['projects'] as $side => $project) {
	$assert(is_file("$dir/$side.ready"), 'Request did not reach the write barrier: ' . $side);
	[$status, $body] = json_decode(file_get_contents("$dir/$side.json"), true, flags: JSON_THROW_ON_ERROR);
	$statuses[] = $status;
	$row = $byProject[$project] ?? null;
	if ($status === $success) {
		$assert($row !== null && $row['id'] === $body['id'] && !$row['enabled']
			&& $row['folder_id'] === $state['folder_id'] && $row['folder'] === $state['folder'], 'Winning paused assignment was not persisted.');
	} else {
		$assert($status === 409 && ($body['error'] ?? '') === 'Another automatic import already uses this folder.',
			'Expected a folder conflict, got ' . json_encode([$status, $body]));
		if ($case === 'create') {
			$assert($row === null, 'Rejected create left a rule behind.');
		} else {
			$assert($row !== null && $row['id'] === $state['rules'][$side] && !$row['enabled']
				&& $row['folder_id'] === 0 && $row['folder'] === '' && $row['interval_minutes'] === 15, 'Rejected update changed the paused rule.');
		}
	}
}
sort($statuses);
$assert($statuses === [$success, 409], 'Expected exactly one saved assignment and one conflict, got ' . json_encode($statuses));
echo "Concurrent paused folder $case passed ($success and 409, one saved assignment).\n";

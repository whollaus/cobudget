<?php
declare(strict_types=1);

// Same three-member area, filename distinct from all earlier deliveries in the fixture.
$config->setUserValue($actor, 'core', 'lang', 'de');
$name = 'monthly.json';
$prefix = $folder . '-monthly';
$monthly = ['folder' => $prefix] + $fixture;
$following = ['folder' => $folder . '-independent'] + $fixture;
$lookup = 'Monthly groceries';
$category = $insert('categories', [
	'name' => $lookup, 'type' => 'expense', 'project_id' => $fixture['project'],
	'workspace_id' => $fixture['workspaces']['upgrade-admin'], 'user_id' => 'upgrade-admin',
]);
$rename = static function (string $label) use ($db, $category): void {
	$q = $db->getQueryBuilder();
	$q->update('cobudget_categories')->set('name', $q->createNamedParameter($label))
		->where($q->expr()->eq('id', $q->createNamedParameter($category, PDO::PARAM_INT)))->executeStatement();
};
$status = static function () use ($rules, $actor, $fixture, $rule): array {
	return array_column($rules->forWorkspace($actor, $fixture['workspaces'][$actor]), null, 'id')[$rule];
};
$batch = $payload($prefix, 50, ['category' => $lookup]);
$inbox->newFile($name, $batch);
$runner->runRule($rule, 2);
$first = $graph($monthly, 33, true);
$rename('Renamed groceries');
$inbox->newFile('independent.json', $payload($following['folder'], 1));
// Even with a single packet per invocation, the blocked file must leave room for a new delivery.
for ($tick = 0; $tick < 3; $tick++) $runner->runRule($rule, 1);
$healthy = $graph($following, 1, true);
expect($status()['waiting_results'][0]['file'] === ['name' => $name, 'id' => $inbox->get($name)->getId()]
	&& $status()['last_file']['id'] === $inbox->get('Verarbeitet/independent.json')->getId(),
	'Waiting and completed results must open their own Files nodes.');
$messages = $status()['waiting_messages'];
expect(count($messages) === 1 && str_contains($messages[0], $name) && str_contains($messages[0], '33 von 50 gebucht')
	&& str_contains($messages[0], 'Fehlende Kategorien: ' . $lookup), 'Settings must identify the waiting file, exact progress and missing category.');
expect($status()['last_error'] === false && str_contains($status()['last_message'], 'independent.json') && $inbox->nodeExists('Verarbeitet/independent.json')
	&& $inbox->nodeExists($name), 'The successful file result and waiting file must coexist.');
$inbox->newFile('monthly-copy.json', $batch);
$runner->runRule($rule);
expect($status()['waiting_messages'] === $messages && $graph($monthly, 33, true) === $first,
	'Retrying while blocked or receiving an identical copy must preserve the checkpoint and all 99 allocations.');
$rename($lookup);
$runner->runRule($rule);
$complete = $graph($monthly, 50, true);
expect(array_slice($complete, 0, 33) === $first && $status()['waiting_messages'] === []
	&& $inbox->nodeExists('Verarbeitet/' . $name) && $inbox->nodeExists('Verarbeitet/monthly-copy.json'),
	'Recovery must add exactly 17 originals, 51 shares and 51 projections, archive both filenames and clear the warning.');
$last = $read('entries', ['project_id' => $fixture['project'], 'description' => $prefix . '-50', 'entry_kind' => 'shared'])[0];
expect((int)$last['category_id'] === $category, 'The final payment must use the restored category.');
$inbox->newFile($name, $batch);
$runner->runRule($rule);
expect($graph($monthly, 50, true) === $complete && $graph($following, 1, true) === $healthy,
	'Re-upload must preserve every payment, share and projection identity in both deliveries.');
echo "Waiting import passed: 33/50 stayed visible alongside the next file; recovered 17 payments and 51 shares exactly once.\n";

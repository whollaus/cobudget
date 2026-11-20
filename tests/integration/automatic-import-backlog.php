<?php
declare(strict_types=1);

// Included only by the guarded disposable Nextcloud fixture.
if ($phase === 'load') {
	for ($tick = 0; $tick < 20; $tick++) {
		$runner->runRule($rule);
	}
	echo 'Backlog worker peak: ' . memory_get_peak_usage(true) . ' bytes, limit ' . ini_get('memory_limit') . "\n";
	return;
}
foreach (['shared' => 5000, 'personal' => 15000] as $kind => $expected) {
	$q = $db->getQueryBuilder();
	$rs = $q->select('description')->selectAlias($q->func()->count('id'), 'n')->from('cobudget_entries')
		->where($q->expr()->like('description', $q->createNamedParameter('backlog-%')))
		->andWhere($q->expr()->eq('entry_kind', $q->createNamedParameter($kind)))->groupBy('description')->executeQuery();
	$count = 0;
	while ($row = $rs->fetch()) {
		expect((int)$row['n'] === intdiv($expected, 5000), 'Unexpected multiplicity for ' . $row['description']);
		$count += (int)$row['n'];
	}
	$rs->closeCursor();
	expect($count === $expected, 'Unexpected backlog count for ' . $kind);
}
$q = $db->getQueryBuilder();
$rs = $q->select('s.user_id')->selectAlias($q->func()->count('s.id'), 'n')->from('cobudget_entry_shares', 's')
	->innerJoin('s', 'cobudget_entries', 'e', $q->expr()->eq('s.entry_id', 'e.id'))
	->where($q->expr()->like('e.description', $q->createNamedParameter('backlog-%')))->groupBy('s.user_id')->executeQuery();
$counts = $rs->fetchAll();
$rs->closeCursor();
expect(count($counts) === 3, 'Expected three share recipients.');
foreach ($counts as $row) expect(isset($shares[$row['user_id']]) && (int)$row['n'] === 5000, 'Unexpected share count.');
echo "Backlog passed: 5,000 distinct payments, 15,000 shares and 15,000 personal entries.\n";

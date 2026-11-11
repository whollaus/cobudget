<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	fwrite(STDERR, "Usage: php tests/integration/annual-overview.php --nextcloud=/path/to/local/server --user=admin\n");
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
$fresh = static function(int $workspaceId) : EntryController {
	$request = new OC\AppFramework\Http\Request(['server' => [
		'HTTP_X_WORKSPACE_ID' => (string)$workspaceId, 'HTTP_X_COBUDGET_TIMEZONE' => 'Europe/Vienna',
	]], Server::get(IRequestId::class), Server::get(IConfig::class));
	$reflection = new ReflectionClass(EntryController::class);
	return $reflection->newInstanceArgs(array_map(static function(ReflectionParameter $parameter) use ($request): mixed {
		if ($parameter->getName() === 'appName') return 'cobudget';
		$type = $parameter->getType()->getName();
		return match ($type) {
			IRequest::class => $request,
			IL10N::class => Server::get(IFactory::class)->get('cobudget', 'en'),
			default => Server::get($type),
		};
	}, $reflection->getConstructor()->getParameters()));
};
$data = static function($response): array {
	if ($response->getStatus() !== 200) throw new RuntimeException('API failure: ' . json_encode($response->getData()));
	return $response->getData();
};

$listCents = static fn(array $list): int => array_sum(array_map(static fn(array $entry): int => (int)round($entry['amount'] * 100), $list['entries']));

$db->beginTransaction();
try {
	$workspace = $insert('workspaces', ['name' => 'Annual overview integration fixture', 'user_id' => $uid]);
	$foreignWorkspace = $insert('workspaces', ['name' => 'Foreign fixture', 'user_id' => '__annual_foreign__']);
	$emptyWorkspace = $insert('workspaces', ['name' => 'Empty year-list fixture', 'user_id' => $uid]);
	$project = $insert('projects', ['name' => 'Shared fixture', 'owner_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace]);
	$solo = $insert('projects', ['name' => 'Solo fixture', 'owner_id' => $uid, 'workspace_id' => $workspace]);
	$insert('members', ['project_id' => $project, 'user_id' => $uid, 'personal_workspace_id' => $workspace, 'share_basis_points' => 4000]);
	$insert('members', ['project_id' => $project, 'user_id' => '__annual_foreign__', 'personal_workspace_id' => $foreignWorkspace, 'share_basis_points' => 6000]);
	$insert('members', ['project_id' => $solo, 'user_id' => $uid, 'personal_workspace_id' => $workspace, 'share_basis_points' => 10000]);
	$parent = $insert('categories', ['name' => 'Home', 'icon' => 'Home', 'user_id' => $uid, 'workspace_id' => $workspace]);
	$child = $insert('categories', ['name' => 'Rent', 'icon' => 'Cash', 'user_id' => $uid, 'workspace_id' => $workspace, 'parent_category_id' => $parent]);
	$partner = $insert('payment_partners', ['name' => 'Partner', 'user_id' => $uid, 'workspace_id' => $workspace]);
	$date = strtotime('2020-01-01 00:30 Europe/Vienna');
	$futureYear = (int)date('Y') + 1;
	$futureDate = strtotime($futureYear . '-02-01 09:00 Europe/Vienna');
	$entry = static function(int $cents, array $overrides = []) use ($insert, $uid, $workspace, $date): int {
		return $insert('entries', array_replace([
			'user_id' => $uid, 'created_by' => $uid, 'workspace_id' => $workspace, 'date' => $date,
			'amount' => $cents / 100, 'amount_cents' => $cents, 'type' => 'expense', 'entry_kind' => 'personal',
		], $overrides));
	};
	$entry(101, ['category_id' => $parent, 'recurrence_interval' => 'month', 'recurrence_multiplier' => 1, 'recurrence_next_date' => $futureDate]);
	$entry(202, ['category_id' => $child, 'payment_partner_id' => $partner]);
	$entry(303, ['project_id' => $solo]);
	$entry(5000, ['type' => 'income']);
	$source = $entry(10000, ['entry_kind' => 'shared', 'workspace_id' => $foreignWorkspace, 'project_id' => $project]);
	$entry(4000, ['project_id' => $project, 'source_entry_id' => $source, 'is_locked' => 1]);
	$entry(1000, ['entry_kind' => 'shared', 'workspace_id' => $foreignWorkspace, 'project_id' => $project, 'is_settled' => 1]);
	$entry(400, ['project_id' => $project, 'is_settled' => 1]);
	$entry(777, ['date' => $futureDate]);
	$entry(999999, ['workspace_id' => $foreignWorkspace, 'date' => strtotime('2018-06-01 UTC')]);
	$entry(888888, ['user_id' => '__annual_foreign__', 'date' => strtotime('2017-06-01 UTC')]);
	for ($i = 0; $i < 300; $i++) $entry(1);

	$report = $data($fresh($workspace)->annualOverview(2020));
	$homeIconRow = array_values(array_filter($report['groups']['expense'], static fn(array $row): bool => $row['id'] === $parent))[0];
	$rentIconRow = array_values(array_filter($homeIconRow['children'], static fn(array $row): bool => $row['id'] === $child))[0];
	$check($homeIconRow['icon'] === 'Home' && $rentIconRow['icon'] === 'Cash', 'Report query retains distinct parent and child category icons');
	$check($report['years'] === [$futureYear, 2020], 'Year list is local-time aware, unique, unpaginated and excludes foreign users/workspaces');
	$check($data($fresh($workspace)->annualOverview(2020, 'paymentPartners'))['years'] === $report['years'], 'Grouping does not change year choices');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared'))['years'] === [2020], 'Shared area year choices exclude other personal payments');
	$personalChoices = $report['filterChoices'];
	$check(in_array($parent, $personalChoices['categoryIds'], true) && in_array($child, $personalChoices['categoryIds'], true) && in_array(0, $personalChoices['categoryIds'], true), 'Category choices are the categories used in that year, including payments without one');
	$check(in_array($partner, $personalChoices['partnerIds'], true) && in_array(0, $personalChoices['partnerIds'], true), 'Payment-partner choices are the partners used in that year, including payments without one');
	$sharedChoices = $data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared'))['filterChoices'];
	$check($sharedChoices['categoryIds'] === [0] && !in_array($parent, $sharedChoices['categoryIds'], true), 'Area filter choices stay inside that area');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo))['years'] === [2020], 'Solo area year choices are scoped to that area');
	$check($data($fresh($emptyWorkspace)->annualOverview(2020))['years'] === [], 'Empty workspace has no year choices');
	$check($data($fresh($workspace)->annualOverview(2020, includeFuturePayments: true))['years'] === $report['years'], 'Year discovery includes future entries independently of the cutoff toggle');
	$check($report['totals']['expense']['total'] === 5306, 'Personal sum excludes shared sources, foreign users/workspaces and includes all pages');
	$check($report['totals']['expense']['totalCount'] === 305, 'Payment counts include every row even when SQL groups identical dates and categories');
	$check($report['totals']['balance']['totalCount'] === 306, 'Balance count includes income and expenses exactly once');
	$check($report['totals']['expense']['counts'][0] === 305 && $report['totals']['expense']['counts'][1] === 0, 'Monthly counts follow the same local dates as amounts');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared'))['totals']['expense']['totalCount'] === 2, 'Shared payment counts exclude personal projections');
	$check($report['totals']['balance']['months'][0] === -306, 'Local January includes midnight payments and exact cents');
	$check(array_sum(array_column($report['groups']['expense'], 'total')) === 5306, 'Parent totals never double count');
	$check($report['periods'][0]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'Response preserves browser timezone boundaries');
	$check($data($fresh($workspace)->annualOverview(2020, 'paymentPartners'))['totals'] === $report['totals'], 'Changing grouping preserves every total');
	$untagged = $data($fresh($workspace)->annualOverview(2020, 'labelsAndTags'));
	$check($untagged['groups']['expense'] === [] && $untagged['groups']['income'] === [], 'Payments without an enabled label or tag have no rows');
	$check($untagged['totals']['expense']['total'] === 0 && $untagged['totals']['expense']['totalCount'] === 0, 'Payments without an enabled label or tag stay out of the expense total');
	$check($untagged['totals']['income']['total'] === 0 && $untagged['totals']['income']['totalCount'] === 0, 'Payments without an enabled label or tag stay out of the income total');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared'))['totals']['expense']['total'] === 11000, 'Shared view uses complete source amounts including settlements');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo))['totals']['expense']['total'] === 303, 'Solo area uses personal entries');
	$check($data($fresh($workspace)->annualOverview($futureYear))['totals']['expense']['total'] === 0, 'Future payments are opt-in');
	$futureReport = $data($fresh($workspace)->annualOverview($futureYear, includeFuturePayments: true));
	$plannedIconRow = array_values(array_filter($futureReport['groups']['expense'], static fn(array $row): bool => $row['id'] === $parent))[0];
	$check($plannedIconRow['icon'] === 'Home', 'Projected recurrence payments retain their category icon');
	$check($futureReport['totals']['expense']['total'] === 777 + 11 * 101, 'Future overview includes the saved payment and February-to-December recurring occurrences');

	// Nested report choices are extra AND predicates and never replace grouping/global filters.
	$detailFilters = json_encode([['dimension' => 'type', 'id' => 'expense'], ['dimension' => 'categories', 'id' => (string)$parent, 'direct' => false], ['dimension' => 'paymentPartners', 'id' => (string)$partner]]);
	$nestedReport = $data($fresh($workspace)->annualOverview(2020, reportFilters: $detailFilters));
	$check($nestedReport['totals']['expense']['total'] === 202 && $nestedReport['totals']['expense']['totalCount'] === 1, 'Category-parent plus partner selects exactly the combination, including matching subcategories');
	$nestedList = $data($fresh($workspace)->index(dateFrom: $date - 10000, dateTo: $date + 10000, reportFilters: $detailFilters));
	$check($nestedList['total'] === 1 && $listCents($nestedList) === 202, 'Saved payments use the exact same combined subject');
	$nestedComposition = $data($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-31', reportFilters: $detailFilters));
	$check($nestedComposition['totals']['expense']['amountCents'] === 202 && $nestedComposition['groups']['categories']['expense'][0]['id'] === $child, 'Composition resolves authorized names from the combined population');
	$directFilters = json_encode([['dimension' => 'categories', 'id' => (string)$parent, 'direct' => true], ['dimension' => 'paymentPartners', 'id' => (string)$partner]]);
	$check($data($fresh($workspace)->annualOverview(2020, reportFilters: $directFilters))['totals']['expense']['total'] === 0, 'Direct category rows cannot include a child merely because its partner matches');
	$unassignedFilters = json_encode([['dimension' => 'type', 'id' => 'expense'], ['dimension' => 'categories', 'id' => '0'], ['dimension' => 'paymentPartners', 'id' => '0'], ['dimension' => 'projects', 'id' => '0'], ['dimension' => 'hashtags', 'id' => '0'], ['dimension' => 'labels', 'id' => '']]);
	$check($data($fresh($workspace)->annualOverview(2020, reportFilters: $unassignedFilters))['totals']['expense']['total'] === 300, 'Unassigned category, partner, area, labels and tags remain exact selectable rows');
	$check($data($fresh($workspace)->annualOverview(2020, partners: '0', reportFilters: $detailFilters))['totals']['expense']['total'] === 0, 'Contradicting global and nested choices intersect rather than broadening to an OR');
	$plannedFilters = json_encode([['dimension' => 'categories', 'id' => (string)$parent, 'direct' => false], ['dimension' => 'paymentPartners', 'id' => '0']]);
	$check($data($fresh($workspace)->annualOverview($futureYear, includeFuturePayments: true, reportFilters: $plannedFilters))['totals']['expense']['total'] === 11 * 101, 'Known future recurrence occurrences obey the same combined scope');
	$foreignFilter = json_encode([['dimension' => 'categories', 'id' => '2147483647']]);
	$check($data($fresh($workspace)->annualOverview(2020, reportFilters: $foreignFilter))['totals']['expense']['total'] === 0, 'Unknown or foreign detail identities never expose another financial scope');
	foreach (['{', '{}', '[{"dimension":"sql","id":"1"}]'] as $invalid) {
		$check($fresh($workspace)->annualOverview(2020, reportFilters: $invalid)->getStatus() === 400, 'Annual reports fail closed for malformed nested filters');
		$check($fresh($workspace)->index(reportFilters: $invalid)->getStatus() === 400, 'Payment lists fail closed for malformed nested filters');
		$check($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-31', reportFilters: $invalid)->getStatus() === 400, 'Composition fails closed for malformed nested filters');
	}

	$check($futureReport['totals']['expense']['totalCount'] === 12, 'Saved payments and planned occurrences each count once');
	$check($futureReport['totals']['expense']['months'][0] === 0 && $futureReport['totals']['expense']['months'][1] === 878 && $futureReport['totals']['expense']['months'][2] === 101, 'Planned recurrences appear in their scheduled months');
	$check($data($fresh($workspace)->annualOverview($futureYear, 'paymentPartners', includeFuturePayments: true))['totals'] === $futureReport['totals'], 'Recurring forecasts preserve totals across category and partner grouping');
	$check($data($fresh($workspace)->annualOverview($futureYear, includeFuturePayments: true, amountMin: '2.00'))['totals']['expense']['total'] === 777, 'Amount filters also apply to planned recurrences');
	$check($fresh($foreignWorkspace)->annualOverview(2020)->getStatus() === 403, 'Foreign workspace header is denied');
	$check($fresh($workspace)->annualOverview(2020, projectId: 2147483647)->getStatus() === 403, 'Unknown/foreign area is denied');
	$check($fresh($workspace)->annualOverview(1969)->getStatus() === 400, 'Invalid year is rejected');
	$check($fresh($workspace)->annualOverview(2020, 'invalid')->getStatus() === 400, 'Invalid grouping is rejected');
	$check(count($report['periods']) === 13, 'The year overview still returns twelve months and the year');
	$january = $data($fresh($workspace)->annualOverview(2020, period: 'month', month: 1));
	$check(count($january['periods']) === 32, 'January has one period per day plus the month total');
	$check($january['totals']['expense']['total'] === $report['totals']['expense']['total'], 'January contains the fixture payments');
	$check($january['totals']['expense']['months'][0] === $report['totals']['expense']['months'][0], 'Day one matches the January amount');
	$check($january['periods'][0]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'The first day starts at local midnight');
	$check($january['periods'][31]['dateTo'] === strtotime('2020-02-01 Europe/Vienna') - 1, 'The last period is the whole month');
	$check($january['years'] === $report['years'], 'The month view keeps the unfiltered year list');
	$march = $data($fresh($workspace)->annualOverview(2020, period: 'month', month: 3));
	$check(count($march['periods']) === 32 && $march['totals']['expense']['total'] === 0, 'An empty month still lists every day and stays at zero');
	$check($fresh($workspace)->annualOverview(2020, period: 'month', month: 0)->getStatus() === 400, 'A month overview needs a month from 1 to 12');
	$quarter = $data($fresh($workspace)->annualOverview(2020, period: 'quarter'));
	$check(count($quarter['periods']) === 5, 'The quarterly overview has four quarters and the year');
	$check(count($quarter['totals']['expense']['months']) === 4, 'The quarterly overview keeps four quarter buckets');
	$check($quarter['totals']['expense']['total'] === $report['totals']['expense']['total'], 'The quarterly year total matches the yearly overview');
	$check($quarter['totals']['expense']['months'][0] === $report['totals']['expense']['total'], 'January payments stay in the first quarter');
	$check($quarter['totals']['expense']['months'][1] === 0 && $quarter['totals']['expense']['months'][2] === 0 && $quarter['totals']['expense']['months'][3] === 0, 'The later quarters of the fixture stay empty');
	$check($quarter['periods'][0]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'The first quarter starts on 1 January');
	$check($quarter['periods'][0]['dateTo'] === strtotime('2020-04-01 Europe/Vienna') - 1, 'The first quarter ends on 31 March');
	$check($quarter['periods'][4]['dateTo'] === strtotime('2021-01-01 Europe/Vienna') - 1, 'The last period is the whole year');
	$check($quarter['years'] === $report['years'], 'The quarter view keeps the unfiltered year list');
	$check($quarter['filterChoices'] === $report['filterChoices'], 'Quarter filter choices use the full year');
	$ignored = $data($fresh($workspace)->annualOverview(2020, period: 'quarter', quarter: 1));
	$check($ignored['periods'] === $quarter['periods'] && $ignored['totals'] === $quarter['totals'], 'A leftover quarter parameter is ignored');
	$check($data($fresh($workspace)->annualOverview(2020, period: 'quarter', quarter: 5))['totals'] === $quarter['totals'], 'Quarter 5 is ignored');
	$week = $data($fresh($workspace)->annualOverview(2020, period: 'week'));
	$check(count($week['periods']) === 54, '2020 has 53 calendar weeks and the week-year total');
	$check(count($week['totals']['expense']['months']) === 53, 'The weekly overview keeps one bucket per calendar week');
	$check($week['totals']['expense']['months'][0] === $report['totals']['expense']['total'], '1 January 2020 stays in calendar week 1');
	$check(array_sum(array_slice($week['totals']['expense']['months'], 1)) === 0, 'The later weeks of the fixture stay empty');
	$check($week['periods'][0]['dateFrom'] === strtotime('2019-12-30 Europe/Vienna'), 'Week 1 of 2020 starts on Monday 30 December 2019');
	$check($week['periods'][0]['dateTo'] === strtotime('2020-01-06 Europe/Vienna') - 1, 'Week 1 ends on Sunday 5 January');
	$check($week['periods'][53]['dateFrom'] === strtotime('2019-12-30 Europe/Vienna'), 'The last period starts with the week-year');
	$check($week['periods'][53]['dateTo'] === strtotime('2021-01-04 Europe/Vienna') - 1, 'The last period ends with week 53');
	$check($week['years'] === $report['years'], 'The week view keeps the unfiltered year list');
	$check($week['filterChoices'] === $report['filterChoices'], 'Week filter choices use the payments inside the week span');
	$day = $data($fresh($workspace)->annualOverview(2020, period: 'day'));
	$check(count($day['periods']) === 367, '2020 has 366 calendar days and the year total');
	$check(count($day['totals']['expense']['months']) === 366, 'The daily overview keeps one bucket per calendar day');
	$check($day['totals']['expense']['months'][0] === $report['totals']['expense']['total'], '1 January 2020 keeps the fixture payments');
	$check(array_sum(array_slice($day['totals']['expense']['months'], 1)) === 0, 'The later days of the fixture stay empty');
	$check($day['periods'][0]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'The first day starts at local midnight');
	$check($day['periods'][0]['dateTo'] === strtotime('2020-01-02 Europe/Vienna') - 1, 'The first day ends before 2 January');
	$check($day['periods'][366]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'The last period starts with the year');
	$check($day['periods'][366]['dateTo'] === strtotime('2021-01-01 Europe/Vienna') - 1, 'The last period ends with the year');
	$check($day['years'] === $report['years'], 'The day view keeps the unfiltered year list');
	$check($day['filterChoices'] === $report['filterChoices'], 'Day filter choices use the calendar year');
	$dayJanuary = $data($fresh($workspace)->annualOverview(2020, period: 'day', month: 1));
	$check(count($dayJanuary['periods']) === 32, 'January in the daily overview has one period per day plus the month total');
	$check(count($dayJanuary['totals']['expense']['months']) === 31, 'January in the daily overview keeps one bucket per day');
	$check($dayJanuary['totals']['expense']['months'][0] === $january['totals']['expense']['months'][0], 'The first January day matches the monthly overview');
	$check($dayJanuary['totals']['expense']['total'] === $january['totals']['expense']['total'], 'The January total matches the monthly overview');
	$check($dayJanuary['periods'] === $january['periods'], 'January days use the same period bounds as the monthly overview');
	$check($dayJanuary['filterChoices'] === $january['filterChoices'], 'A selected month uses that month for filter choices');
	$check($dayJanuary['years'] === $report['years'], 'The day view keeps the unfiltered year list');
	$february = $data($fresh($workspace)->annualOverview(2020, period: 'day', month: 2));
	$check(count($february['periods']) === 30, 'February 2020 has 29 days and the month total');
	$check(count($february['totals']['expense']['months']) === 29, 'February 2020 keeps one bucket per day');
	$check($february['totals']['expense']['total'] === 0, 'February 2020 has no fixture payments');
	$check($fresh($workspace)->annualOverview(2020, period: 'day', month: 13)->getStatus() === 400, 'Day month 13 is rejected');
	$check($fresh($workspace)->annualOverview(2020, period: 'hour')->getStatus() === 400, 'Unknown periods are rejected');

	foreach (['year', 'quarter', 'week', 'day'] as $breakdown) {
		$rangeReport = $data($fresh($workspace)->annualOverview(2020, period: $breakdown, dateFrom: '2019-12-25', dateTo: '2020-01-03'));
		$check($rangeReport['totals']['expense']['total'] === 5306, 'Free range totals are independent of breakdown and preserve personal scope');
		$check($rangeReport['periods'][0]['dateFrom'] === strtotime('2019-12-25 Europe/Vienna'), 'Clipped range starts on the selected date');
		$check($rangeReport['periods'][count($rangeReport['periods']) - 1]['dateTo'] === strtotime('2020-01-04 Europe/Vienna') - 1, 'The final date is inclusive');
		$check($rangeReport['range'] === ['dateFrom' => '2019-12-25', 'dateTo' => '2020-01-03'], 'API exposes the applied dates for details and averages');
		$sharedRange = $data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared', period: $breakdown, dateFrom: '2019-12-25', dateTo: '2020-01-03'));
		$check($sharedRange['totals']['expense']['total'] === 11000, 'Shared range uses full sources exactly once');
		$comparedRange = $data($fresh($workspace)->annualOverview(2020, comparison: 'month', period: $breakdown, dateFrom: '2019-12-25', dateTo: '2020-01-03'));
		$check(count($comparedRange['totals']['expense']['comparisons']) === count($rangeReport['periods']) - 1, 'Comparison columns match clipped report columns');
	}
	foreach ([['2020-02-30', '2020-03-01'], ['2020-01-03', '2020-01-01'], ['', '2020-01-01']] as [$from, $to]) {
		$check($fresh($workspace)->annualOverview(2020, dateFrom: $from, dateTo: $to)->getStatus() === 400, 'Invalid date ranges are rejected by the API');
	}
	$rangeEmpty = $data($fresh($workspace)->annualOverview(2020, dateFrom: '2020-02-01', dateTo: '2020-02-10', period: 'day'));
	$check($rangeEmpty['totals']['expense']['total'] === 0 && count($rangeEmpty['periods']) === 11, 'Empty vacation ranges retain the selected ten days');

	$labelWorkspace = $insert('workspaces', ['name' => 'Label overview fixture', 'user_id' => $uid]);
	$labeledEntry = static function(int $cents, array $overrides = []) use ($insert, $uid, $labelWorkspace, $date): int {
		return $insert('entries', array_replace([
			'user_id' => $uid, 'created_by' => $uid, 'workspace_id' => $labelWorkspace, 'date' => $date,
			'amount' => $cents / 100, 'amount_cents' => $cents, 'type' => 'expense', 'entry_kind' => 'personal',
		], $overrides));
	};
	$usedCategory = $insert('categories', ['name' => 'Used year', 'user_id' => $uid, 'workspace_id' => $labelWorkspace]);
	$unusedCategory = $insert('categories', ['name' => 'Other year', 'user_id' => $uid, 'workspace_id' => $labelWorkspace]);
	$laterCategory = $insert('categories', ['name' => 'Later year', 'user_id' => $uid, 'workspace_id' => $labelWorkspace]);
	$usedPartner = $insert('payment_partners', ['name' => 'Used partner', 'user_id' => $uid, 'workspace_id' => $labelWorkspace]);
	$unusedPartner = $insert('payment_partners', ['name' => 'Other partner', 'user_id' => $uid, 'workspace_id' => $labelWorkspace]);
	$importantEntry = $labeledEntry(250, ['is_important' => 1, 'is_insurance' => 1, 'needs_review' => 1, 'description' => 'Reise #Alpen']);
	$taggedEntry = $labeledEntry(10, ['description' => 'Nur #Alpen']);
	$labeledEntry(77, ['description' => 'Ohne Label', 'category_id' => $usedCategory, 'payment_partner_id' => $usedPartner]);
	$labeledEntry(15, ['is_fixed_cost' => 1, 'description' => 'Nur Fixkosten']);
	$hashtag = $insert('hashtags', [
		'workspace_id' => $labelWorkspace, 'normalized_name' => 'alpen', 'display_name' => 'Alpen',
		'created_at' => $date, 'updated_at' => $date,
	]);
	foreach ([$importantEntry, $taggedEntry] as $entryId) {
		$insert('entry_hashtags', ['entry_id' => $entryId, 'hashtag_id' => $hashtag, 'workspace_id' => $labelWorkspace, 'created_at' => $date]);
	}
	$oldDate = strtotime('2019-06-01 12:00 Europe/Vienna');
	$oldEntry = $labeledEntry(5, ['date' => $oldDate, 'category_id' => $unusedCategory, 'payment_partner_id' => $unusedPartner, 'is_subscription' => 1, 'description' => 'Alt #Winter']);
	$oldHashtag = $insert('hashtags', [
		'workspace_id' => $labelWorkspace, 'normalized_name' => 'winter', 'display_name' => 'Winter',
		'created_at' => $oldDate, 'updated_at' => $oldDate,
	]);
	$insert('entry_hashtags', ['entry_id' => $oldEntry, 'hashtag_id' => $oldHashtag, 'workspace_id' => $labelWorkspace, 'created_at' => $oldDate]);
	$tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Vienna')))->setTime(12, 0);
	$labeledEntry(3, ['date' => $tomorrow->getTimestamp(), 'category_id' => $laterCategory]);
	$labelReport = $data($fresh($labelWorkspace)->annualOverview(2020, 'labelsAndTags'));
	$config = Server::get(IConfig::class);
	$fixedEnabled = $config->getUserValue($uid, 'cobudget', 'enable_fixed_costs', 'yes') === 'yes';
	$expectedTotal = 260 + ($fixedEnabled ? 15 : 0);
	$expectedCount = 2 + ($fixedEnabled ? 1 : 0);
	$check($labelReport['totals']['expense']['total'] === $expectedTotal, 'Label totals count each qualifying payment once');
	$check($labelReport['totals']['expense']['totalCount'] === $expectedCount, 'Unlabeled payments stay out of the label totals');
	$labelRows = [];
	foreach ($labelReport['groups']['expense'] as $row) {
		$check($row['children'] === [], 'Labels and tags stay flat');
		$labelRows[$row['kind'] . ':' . $row['filter']] = $row;
	}
	$check(($labelRows['hashtag:' . $hashtag]['total'] ?? null) === 260, 'A description tag includes every matching payment');
	$check(($labelRows['hashtag:' . $hashtag]['name'] ?? '') === '#Alpen', 'Tags keep their description name');
	foreach ([
		'important' => 'enable_important_payments',
		'insurance' => 'enable_insurance',
		'review' => 'enable_review_payments',
		'fixedCost' => 'enable_fixed_costs',
	] as $filter => $setting) {
		$enabled = $config->getUserValue($uid, 'cobudget', $setting, 'yes') === 'yes';
		$present = isset($labelRows['label:' . $filter]);
		$check($present === $enabled, $setting . ' controls whether its label row appears');
		if ($enabled) {
			$check($labelRows['label:' . $filter]['total'] === ($filter === 'fixedCost' ? 15 : 250), $filter . ' counts only payments with that label');
		}
	}
	$labelComparison = $data($fresh($labelWorkspace)->annualOverview(2020, 'labelsAndTags', comparison: 'month'));
	$check($labelComparison['totals']['expense']['comparisons'][1]['previous'] === $expectedTotal, 'Label comparisons count each qualifying payment once');
	$labelPeriod = $labelReport['periods'][0];
	$labelList = $data($fresh($labelWorkspace)->index(hashtagId: $hashtag, type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo']));
	$check($labelList['total'] === 2 && $listCents($labelList) === 260, 'Tag drilldown matches the tag row');
	$labelList = $data($fresh($labelWorkspace)->index(isImportant: true, type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo']));
	$check($labelList['total'] === 1 && $listCents($labelList) === 250, 'Label drilldown matches the label row');

	$tagIntersection = json_encode([['dimension' => 'hashtags', 'id' => (string)$hashtag], ['dimension' => 'paymentPartners', 'id' => '0']]);
	$check($data($fresh($labelWorkspace)->annualOverview(2020, reportFilters: $tagIntersection))['totals']['expense']['total'] === 260, 'A joined tag and partner predicate count each payment once');
	$check($data($fresh($labelWorkspace)->index(dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo'], reportFilters: $tagIntersection))['total'] === 2, 'Tag intersections agree with saved-payment pagination');
	$labelIntersection = json_encode([['dimension' => 'labels', 'id' => 'important'], ['dimension' => 'labels', 'id' => 'insurance']]);
	$bothLabels = $config->getUserValue($uid, 'cobudget', 'enable_important_payments', 'yes') === 'yes' && $config->getUserValue($uid, 'cobudget', 'enable_insurance', 'yes') === 'yes';
	$check($data($fresh($labelWorkspace)->annualOverview(2020, reportFilters: $labelIntersection))['totals']['expense']['total'] === ($bothLabels ? 250 : 0), 'Multiple detail labels use AND and respect disabled features');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, hashtags: (string)$oldHashtag, reportFilters: $tagIntersection))['totals']['expense']['total'] === 0, 'Global description-tag filters remain in force alongside a different nested tag');

	$categoryReport = $data($fresh($labelWorkspace)->annualOverview(2020, 'categories'));
	$check($categoryReport['totals']['expense']['total'] === 352 && $categoryReport['totals']['expense']['totalCount'] === 4, 'Category totals include every payment before filtering');
	$check($categoryReport['years'] === $labelReport['years'], 'Payment filters are not required for the year list');
	$check(array_column($categoryReport['hashtags'], 'displayName') === ['Alpen'], 'Only description tags used in the selected year are offered');
	$yearChoices = $categoryReport['filterChoices'];
	$check(in_array($usedCategory, $yearChoices['categoryIds'], true) && in_array(0, $yearChoices['categoryIds'], true) && !in_array($unusedCategory, $yearChoices['categoryIds'], true), 'Category choices follow payments in the selected year');
	$check(in_array($usedPartner, $yearChoices['partnerIds'], true) && in_array(0, $yearChoices['partnerIds'], true) && !in_array($unusedPartner, $yearChoices['partnerIds'], true), 'Payment-partner choices follow payments in the selected year');
	$check(in_array('important', $yearChoices['labels'], true) === ($config->getUserValue($uid, 'cobudget', 'enable_important_payments', 'yes') === 'yes'), 'A used label is offered only while its setting is enabled');
	$check(!in_array('subscription', $yearChoices['labels'], true), 'A label used only in another year is hidden');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', amountMin: '2.00', labels: 'important'))['filterChoices'] === $yearChoices, 'Active filters do not remove other values from the choices');
	$laterOverview = $data($fresh($labelWorkspace)->annualOverview((int)$tomorrow->format('Y')));
	$check(in_array($laterCategory, $laterOverview['filterChoices']['categoryIds'], true), 'A payment later in the selected year remains available as a filter');
	$check($laterOverview['totals']['expense']['total'] === 0, 'Payments after today stay out of the overview until future payments are included');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', categories: '999999'))['totals'] === $categoryReport['totals'], 'Category filters are ignored while grouping by category');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', amountMin: '2.00'))['totals']['expense']['total'] === 250, 'The amount filter keeps payments from the entered minimum');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', amountMin: '3.00', amountMax: '1.00'))['totals']['expense']['total'] === 250, 'Reversed amount bounds still include the matching payment');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', labels: 'important'))['totals']['expense']['total'] === 250, 'A label filter keeps payments with that label');
	$labelOr = $data($fresh($labelWorkspace)->annualOverview(2020, 'categories', labels: 'important,fixedCost'));
	$check($labelOr['totals']['expense']['total'] === 250 + ($fixedEnabled ? 15 : 0), 'Several labels match payments that have any of them');
	$check($labelOr['totals']['expense']['totalCount'] === 1 + ($fixedEnabled ? 1 : 0), 'Several labels do not require every label on the same payment');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', labels: 'not-a-label'))['totals']['expense']['total'] === 0, 'Unknown labels match no payments');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', hashtags: (string)$hashtag))['totals']['expense']['total'] === 260, 'A tag filter keeps payments that use that tag');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'paymentPartners', categories: '0'))['totals']['expense']['total'] === 275, 'Unassigned category matches payments without a category');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'paymentPartners', categories: '999999'))['totals']['expense']['total'] === 0, 'An unknown category matches no payments');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'labelsAndTags', labels: 'not-a-label'))['totals']['expense']['total'] === $expectedTotal, 'Label filters are ignored while grouping by labels and tags');
	$taggedOnly = $data($fresh($labelWorkspace)->annualOverview(2020, 'labelsAndTags', hashtags: (string)$hashtag));
	$check($taggedOnly['totals']['expense']['total'] === 260 && $taggedOnly['totals']['expense']['totalCount'] === 2, 'A tag filter still narrows label and tag totals');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'labelsAndTags', amountMin: '2.00'))['totals']['expense']['total'] === 250, 'The amount filter applies before label and tag totals');
	$check($data($fresh($labelWorkspace)->annualOverview(2020, 'categories', amountMin: '2.00'))['years'] === $categoryReport['years'], 'Payment filters do not remove years from the year list');
	$filteredList = $data($fresh($labelWorkspace)->index(type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo'], labels: 'important,fixedCost', amountMin: '2.00'));
	$check($filteredList['total'] === 1 && $listCents($filteredList) === 250, 'Payment list drilldown keeps the amount and any selected label');
	$labelDetailList = $data($fresh($labelWorkspace)->index(type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo'], reportGrouping: 'labelsAndTags'));
	$check($listCents($labelDetailList) === $expectedTotal, 'Report detail payments exclude unlabelled entries and count multiple labels and tags only once');
	$check($labelDetailList['total'] === $labelReport['totals']['expense']['totalCount'], 'Report detail saved-payment count matches label and tag totals');
	$filteredDashboard = $data($fresh($labelWorkspace)->dashboard(type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo'], hashtags: (string)$hashtag));
	$check($filteredDashboard['total'] === 2 && $listCents($filteredDashboard) === 260, 'Personal payment page uses the same tag filter');

	$comparison = $data($fresh($workspace)->annualOverview(2020, comparison: 'month'));
	$check($comparison['totals']['expense']['comparisons'][0] === null, 'Comparison history excludes foreign earlier entries');
	$check($comparison['totals']['expense']['comparisons'][1]['previous'] === 5306, 'February compares scoped January payments including all pages');
	$check($comparison['totals']['expense']['comparisons'][1]['delta'] === -5306, 'Empty month after history begins has a real decline');
	$check($data($fresh($workspace)->annualOverview(2021, comparison: 'year'))['totals']['expense']['comparisons'][0]['previous'] === 5306, 'Prior-year comparison crosses the report query boundary');
	$check($data($fresh($workspace)->annualOverview(2021, comparison: 'year'))['totals']['expense']['comparisons'][1] === null, 'No prior-year payments means no comparison');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project, entryScope: 'shared', comparison: 'month'))['totals']['expense']['comparisons'][1]['previous'] === 11000, 'Shared comparison uses full source amounts without personal duplicates');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo, comparison: 'month'))['totals']['expense']['comparisons'][1]['previous'] === 303, 'Solo comparison remains scoped');
	$check($fresh($workspace)->annualOverview(2020, comparison: 'invalid')->getStatus() === 400, 'Invalid comparison is rejected');

	$period = $report['periods'][0];
	$list = $data($fresh($workspace)->index(categoryId: $parent, dateFrom: $period['dateFrom'], dateTo: $period['dateTo'], includeSubcategories: true));
	$check((int)$list['total'] === 2 && $listCents($list) === 303, 'Parent drilldown matches parent total');
	$dashboard = $data($fresh($workspace)->dashboard(categoryId: $parent, dateFrom: $period['dateFrom'], dateTo: $period['dateTo'], includeSubcategories: true));
	$check($dashboard['total'] === 2 && $listCents($dashboard) === 303, 'Personal payment page uses the same filters through the dashboard endpoint');
	$list = $data($fresh($workspace)->index(categoryId: $child, dateFrom: $period['dateFrom'], dateTo: $period['dateTo']));
	$check($list['total'] === 1 && $listCents($list) === 202, 'Child drilldown is exact');
	$list = $data($fresh($workspace)->index(paymentPartnerId: $partner, dateFrom: $period['dateFrom'], dateTo: $period['dateTo']));
	$check($list['total'] === 1, 'Partner drilldown is exact');
	$list = $data($fresh($workspace)->index(categoryId: 0, type: 'expense', dateFrom: $period['dateFrom'], dateTo: $period['dateTo']));
	$check($list['total'] === 303, 'Unassigned drilldown includes all matching entries, beyond one page');
	$list = $data($fresh($workspace)->index(projectId: $project, entryScope: 'shared', dateFrom: $period['dateFrom'], dateTo: $period['dateTo']));
	$check($list['total'] === 2 && $listCents($list) === 11000, 'Area drilldown includes settled source rows exactly once');
	$future = $data($fresh($workspace)->annualOverview($futureYear, includeFuturePayments: true));
	$period = $future['periods'][12];
	$list = $data($fresh($workspace)->index(dateFrom: $period['dateFrom'], dateTo: $period['dateTo'], includeFuturePayments: true));
	$check($list['total'] === 1 && $listCents($list) === 777, 'Future drilldown contains saved payments only, excluding unsaved recurring occurrences');
	$dashboard = $data($fresh($workspace)->dashboard(dateFrom: $period['dateFrom'], dateTo: $period['dateTo'], includeFuturePayments: true));
	$check($dashboard['total'] === 1 && $listCents($dashboard) === 777, 'Personal dashboard includes future-dated entries only when requested');

	// Shared forecasts use the current user's stored share in their persisted
	// personal workspace, even when the source belongs to another member.
	$forecastWorkspace = $insert('workspaces', ['name' => 'Personal shared forecast fixture', 'user_id' => $uid]);
	$forecastProject = $insert('projects', ['name' => 'Shared recurring forecast', 'owner_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace]);
	$insert('members', ['project_id' => $forecastProject, 'user_id' => $uid, 'personal_workspace_id' => $forecastWorkspace, 'share_basis_points' => 4000]);
	$insert('members', ['project_id' => $forecastProject, 'user_id' => '__annual_foreign__', 'personal_workspace_id' => $foreignWorkspace, 'share_basis_points' => 6000]);
	$forecastCategory = $insert('categories', ['name' => 'Shared forecast category', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $forecastProject]);
	$forecastPartner = $insert('payment_partners', ['name' => 'Shared forecast partner', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $forecastProject]);
	$forecastEnd = strtotime($futureYear . '-03-01 23:59 Europe/Vienna');
	$sourceValues = [
		'entry_kind' => 'shared', 'user_id' => '__annual_foreign__', 'created_by' => '__annual_foreign__',
		'workspace_id' => $foreignWorkspace, 'project_id' => $forecastProject,
		'category_id' => $forecastCategory, 'payment_partner_id' => $forecastPartner,
		'description' => '#Holiday', 'is_important' => 1,
		'recurrence_interval' => 'month', 'recurrence_multiplier' => 1,
		'recurrence_next_date' => $futureDate, 'recurrence_end_date' => $forecastEnd,
	];
	$forecastSource = $entry(10001, $sourceValues);
	$personalValues = [
		'workspace_id' => $forecastWorkspace, 'project_id' => $forecastProject,
		'category_id' => $forecastCategory, 'payment_partner_id' => $forecastPartner,
		'description' => '#Holiday', 'is_important' => 1, 'is_locked' => 1,
	];
	$forecastPersonal = $entry(4001, $personalValues + ['source_entry_id' => $forecastSource]);
	$insert('entry_shares', ['entry_id' => $forecastSource, 'user_id' => $uid, 'share_basis_points' => 4000, 'amount_cents' => 4001, 'personal_entry_id' => $forecastPersonal]);
	$insert('entry_shares', ['entry_id' => $forecastSource, 'user_id' => '__annual_foreign__', 'share_basis_points' => 6000, 'amount_cents' => 6000]);
	$entry(7, ['workspace_id' => $forecastWorkspace, 'date' => $futureDate]);
	$forecastSourceTag = $insert('hashtags', ['workspace_id' => $foreignWorkspace, 'normalized_name' => 'holiday', 'display_name' => 'Holiday', 'created_at' => $date, 'updated_at' => $date]);
	$forecastPersonalTag = $insert('hashtags', ['workspace_id' => $forecastWorkspace, 'normalized_name' => 'holiday', 'display_name' => 'HOLIDAY', 'created_at' => $date, 'updated_at' => $date]);
	$insert('entry_hashtags', ['entry_id' => $forecastSource, 'hashtag_id' => $forecastSourceTag, 'workspace_id' => $foreignWorkspace, 'created_at' => $date]);
	$insert('entry_hashtags', ['entry_id' => $forecastPersonal, 'hashtag_id' => $forecastPersonalTag, 'workspace_id' => $forecastWorkspace, 'created_at' => $date]);
	$fixtureRows = static function(string $table, string $column, int $id) use ($db): array {
		$qb = $db->getQueryBuilder();
		$qb->select('*')->from('cobudget_' . $table)->where($qb->expr()->eq($column, $qb->createNamedParameter($id, \PDO::PARAM_INT)))->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	};
	$forecastState = static fn(): array => [
		$fixtureRows('entries', 'project_id', $forecastProject), $fixtureRows('entry_shares', 'entry_id', $forecastSource),
		$fixtureRows('members', 'project_id', $forecastProject), $fixtureRows('hashtags', 'workspace_id', $forecastWorkspace),
	];
	$beforeForecast = $forecastState();
	$personalForecast = $data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true));
	$check($personalForecast['totals']['expense']['total'] === 8009, 'My finances uses the two exact stored 4001-cent shares, including their balanced rounding cent, rather than rounding 40 percent again');
	$check($personalForecast['totals']['expense']['totalCount'] === 3, 'Shared forecast occurrences count once alongside saved personal payments');
	$check($personalForecast['totals']['expense']['months'][1] === 4008 && $personalForecast['totals']['expense']['months'][2] === 4001 && $personalForecast['totals']['expense']['months'][3] === 0, 'Shared personal forecasts follow scheduled months and stop at the series end');
	$areaPersonalForecast = $data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, includeFuturePayments: true));
	$check($areaPersonalForecast['totals']['expense']['total'] === 8002, 'A selected area defaults to its exact personal forecast and excludes unrelated personal payments');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, includeFuturePayments: true, amountMin: '50'))['totals']['expense']['total'] === 0, 'Personal area forecast filters use personal cents rather than source amounts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear))['totals']['expense']['total'] === 0, 'Shared forecasts remain opt-in');
	$check($data($fresh($emptyWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 0, 'Shared forecasts never enter another personal workspace');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, entryScope: 'shared', includeFuturePayments: true))['totals']['expense']['total'] === 20002, 'Area forecasts retain the full shared source amounts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, 'paymentPartners', includeFuturePayments: true))['totals'] === $personalForecast['totals'], 'Personal shared forecasts preserve totals across grouping');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, amountMin: '35', amountMax: '45'))['totals']['expense']['total'] === 8002, 'Amount limits filter shared forecasts by the personal share');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, amountMin: '50'))['totals']['expense']['total'] === 0, 'A full source amount never makes a smaller personal allocation match');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, 'paymentPartners', includeFuturePayments: true, categories: (string)$forecastCategory))['totals']['expense']['total'] === 8002, 'Category filters retain matching shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, partners: (string)$forecastPartner))['totals']['expense']['total'] === 8002, 'Payment-partner filters retain matching shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, hashtags: (string)$forecastPersonalTag))['totals']['expense']['total'] === 8002, 'Personal tag IDs match shared-series tags by normalized name');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, hashtags: (string)$forecastSourceTag))['totals']['expense']['total'] === 0, 'A foreign workspace tag ID cannot filter a personal shared forecast');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, entryScope: 'shared', includeFuturePayments: true, hashtags: (string)$forecastSourceTag))['totals']['expense']['total'] === 20002, 'Area tag filters retain their own workspace IDs');
	$forecastLabels = $data($fresh($forecastWorkspace)->annualOverview($futureYear, 'labelsAndTags', includeFuturePayments: true));
	$check($forecastLabels['totals']['expense']['total'] === 8002 && $forecastLabels['totals']['expense']['totalCount'] === 2, 'Labels and tags count shared personal forecast amounts once');
	$forecastTagRows = array_values(array_filter($forecastLabels['groups']['expense'], static fn(array $row): bool => $row['kind'] === 'hashtag'));
	$check(count($forecastTagRows) === 1 && $forecastTagRows[0]['filter'] === (string)$forecastPersonalTag && $forecastTagRows[0]['name'] === '#HOLIDAY', 'Forecast tag rows and drilldowns use the personal workspace identity');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'quarter', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Quarterly reports include the same shared personal forecast');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'month', month: 3, includeFuturePayments: true))['totals']['expense']['total'] === 4001, 'Monthly reports include only the matching shared personal occurrence');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'week', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Weekly reports include shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'day', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Daily reports include shared personal forecasts');
	$forecastComposition = $data($fresh($forecastWorkspace)->reportBreakdown($futureYear . '-01-01', $futureYear . '-12-31', includeFuturePayments: true));
	$personalAreas = $data($fresh($forecastWorkspace)->areaOverview($futureYear . '-01-01', $futureYear . '-12-31', includeFuturePayments: true));
	$fullAreas = $data($fresh($forecastWorkspace)->areaOverview($futureYear . '-01-01', $futureYear . '-12-31', amountView: 'area', includeFuturePayments: true));
	$check($personalAreas['totals']['expense']['total'] === 8009 && $personalAreas['totals']['expense']['totalCount'] === 3, 'Area comparison uses exact personal projections and recurrence allocations');
	$check($fullAreas['totals']['expense']['total'] === 20009 && $fullAreas['totals']['expense']['totalCount'] === 3, 'Full-area forecasts replace personal projections rather than adding both');
	$marchAreas = $data($fresh($forecastWorkspace)->areaOverview($futureYear . '-03-01', $futureYear . '-03-31', amountView: 'area', includeFuturePayments: true));
	$check($marchAreas['totals']['expense']['total'] === 10001, 'Projected area amounts stay inside the opened month');
	$taggedAreas = $data($fresh($forecastWorkspace)->areaOverview($futureYear . '-01-01', $futureYear . '-12-31', amountView: 'area', includeFuturePayments: true, hashtags: (string)$forecastPersonalTag));
	$check($taggedAreas['totals']['expense']['total'] === 20002, 'Full area comparison resolves personal tag identities for shared forecasts');
	$taggedAreaRows = array_column($taggedAreas['rows'], null, 'id');
	$check($taggedAreaRows[$forecastProject]['filterHashtags'] === [$forecastSourceTag], 'Full-area links carry the equivalent source-workspace tag filter');
	$check($forecastComposition['totals']['expense'] === ['amountCents' => 8009, 'count' => 3], 'Composition includes every personal saved payment and exact shared forecast once');
	$forecastTagComposition = $data($fresh($forecastWorkspace)->reportBreakdown($futureYear . '-01-01', $futureYear . '-12-31', grouping: 'labelsAndTags', detailId: 'tag:' . $forecastPersonalTag, includeFuturePayments: true));
	$check($forecastTagComposition['totals']['expense'] === ['amountCents' => 8002, 'count' => 2], 'Composition resolves projected tag identities in the personal workspace');
	$forecastAreaComposition = $data($fresh($forecastWorkspace)->reportBreakdown($futureYear . '-03-01', $futureYear . '-03-31', projectId: $forecastProject, entryScope: 'shared', includeFuturePayments: true));
	$check($forecastAreaComposition['totals']['expense'] === ['amountCents' => 10001, 'count' => 1], 'Composition clips full shared forecasts to the opened month');
	$check($data($fresh($forecastWorkspace)->reportBreakdown($futureYear . '-01-01', $futureYear . '-12-31', includeFuturePayments: true, amountMin: '50'))['totals']['expense']['amountCents'] === 0, 'Composition amount filters use allocated cents before forecast aggregation');
	$check($beforeForecast === $forecastState(), 'Forecast reads never create payments, rewrite snapshots or update rounding balances or tags');

	// Simulate advancing the source series after an occurrence was generated.
	$nextSource = $entry(10001, array_replace($sourceValues, ['date' => $futureDate, 'recurrence_next_date' => strtotime($futureYear . '-03-01 09:00 Europe/Vienna')]));
	$nextPersonal = $entry(4001, array_replace($personalValues, ['date' => $futureDate, 'source_entry_id' => $nextSource]));
	$insert('entry_shares', ['entry_id' => $nextSource, 'user_id' => $uid, 'share_basis_points' => 4000, 'amount_cents' => 4001, 'personal_entry_id' => $nextPersonal]);
	$insert('entry_shares', ['entry_id' => $nextSource, 'user_id' => '__annual_foreign__', 'share_basis_points' => 6000, 'amount_cents' => 6000]);
	$advance = $db->getQueryBuilder();
	$advance->update('cobudget_entries')->set('recurrence_interval', $advance->createNamedParameter(null, \PDO::PARAM_NULL))->set('recurrence_next_date', $advance->createNamedParameter(null, \PDO::PARAM_NULL))->where($advance->expr()->eq('id', $advance->createNamedParameter($forecastSource, \PDO::PARAM_INT)))->executeStatement();
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'A saved personal occurrence replaces its forecast without double counting');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, entryScope: 'shared', includeFuturePayments: true))['totals']['expense']['total'] === 20002, 'Advancing the source series also preserves the full area forecast');
	$zeroSource = $entry(10001, $sourceValues);
	$insert('entry_shares', ['entry_id' => $zeroSource, 'user_id' => $uid, 'share_basis_points' => 0, 'amount_cents' => 0]);
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'A zero-share shared series never enters personal forecasts');
	$insert('members', ['project_id' => $forecastProject, 'user_id' => 'former:annual-forecast-fixture', 'share_basis_points' => 0]);
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 4008, 'Former participants pause future shared forecasts while saved personal payments remain');

	// Personal area totals follow immutable stored payments, including zero/full
	// allocations, settled copies and shares that differ from today's defaults.
	$scopeWorkspace = $insert('workspaces', ['name' => 'Area perspective fixture', 'user_id' => $uid]);
	$scopeProject = $insert('projects', ['name' => 'Historical allocations', 'owner_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace]);
	$insert('members', ['project_id' => $scopeProject, 'user_id' => $uid, 'personal_workspace_id' => $scopeWorkspace, 'share_basis_points' => 5000]);
	$insert('members', ['project_id' => $scopeProject, 'user_id' => '__annual_foreign__', 'personal_workspace_id' => $foreignWorkspace, 'share_basis_points' => 5000]);
	$scopeCategory = $insert('categories', ['name' => 'Area original', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $scopeProject]);
	$personalCategory = $insert('categories', ['name' => 'Edited personal category', 'user_id' => $uid, 'workspace_id' => $scopeWorkspace]);
	$scopePartner = $insert('payment_partners', ['name' => 'Area partner', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $scopeProject]);
	foreach ([[10001, 4001, false], [2000, 800, true], [3000, 0, false], [100, 100, false]] as [$fullCents, $personalCents, $settled]) {
		$sourceId = $entry($fullCents, ['entry_kind' => 'shared', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $scopeProject, 'category_id' => $scopeCategory, 'payment_partner_id' => $scopePartner, 'is_important' => 1, 'is_settled' => (int)$settled]);
		$personalId = $personalCents > 0 ? $entry($personalCents, ['workspace_id' => $scopeWorkspace, 'project_id' => $scopeProject, 'source_entry_id' => $settled ? null : $sourceId, 'is_locked' => (int)!$settled, 'is_settled' => (int)$settled, 'category_id' => $settled ? $personalCategory : $scopeCategory, 'payment_partner_id' => $scopePartner, 'is_important' => 1]) : null;
		$insert('entry_shares', ['entry_id' => $sourceId, 'user_id' => $uid, 'share_basis_points' => $personalCents === 100 ? 10000 : ($personalCents ? 4000 : 0), 'amount_cents' => $personalCents, 'personal_entry_id' => $settled ? null : $personalId]);
	}
	$entry(9999, ['workspace_id' => $scopeWorkspace]);
	foreach (['categories', 'paymentPartners', 'labelsAndTags'] as $grouping) {
		foreach (['personal' => [4901, 3], 'shared' => [15101, 4]] as $entryScope => [$expectedCents, $expectedCount]) {
			$scoped = $data($fresh($scopeWorkspace)->annualOverview(2020, $grouping, projectId: $scopeProject, entryScope: $entryScope));
			$check($scoped['totals']['expense']['total'] === $expectedCents && $scoped['totals']['expense']['totalCount'] === $expectedCount, 'Each grouping follows stored historical, zero and full allocations in ' . $entryScope . ' scope');
			$check($scoped['entryScope'] === $entryScope, 'Report metadata identifies the actual amount perspective');
			$list = $data($fresh($scopeWorkspace)->index(limit: 500, projectId: $scopeProject, entryScope: $entryScope, reportGrouping: $grouping));
			$check($listCents($list) === $expectedCents && $list['total'] === $expectedCount, 'Saved detail payments match their ' . $entryScope . ' report without source/copy duplicates');
		}
	}
	$personalArea = $data($fresh($scopeWorkspace)->annualOverview(2020, projectId: $scopeProject));
	foreach (['personal' => [4001, 800, 100], 'shared' => [10001, 3000, 2000, 100]] as $perspective => $expected) {
		$ranked = $data($fresh($scopeWorkspace)->index(limit: 10, type: 'expense', sortBy: 'amount', sortDir: 'desc', projectId: $scopeProject, entryScope: $perspective));
		$check(array_map(static fn(array $row): int => (int)round((float)$row['amount'] * 100), $ranked['entries']) === $expected, 'Top 10 ranks the selected stored ' . $perspective . ' cents, retaining historical and full shares without duplicates');
	}
	$check($personalArea['entryScope'] === 'personal' && $personalArea['totals']['expense']['total'] === 4901, 'Area reports default to personal stored cents rather than today’s 50-percent membership default');
	$check(in_array($personalCategory, $personalArea['filterChoices']['categoryIds'], true), 'Released personal payment categories remain filterable');
	$check($data($fresh($scopeWorkspace)->annualOverview(2020, projectId: $scopeProject, amountMin: '5'))['totals']['expense']['total'] === 4801, 'Personal amount limits use the personal payment value');
	$check($data($fresh($scopeWorkspace)->annualOverview(2020, projectId: $scopeProject, entryScope: 'shared', amountMin: '50'))['totals']['expense']['total'] === 10001, 'Entire-area amount limits use full source values');
	$check($data($fresh($scopeWorkspace)->annualOverview(2021, projectId: $scopeProject, comparison: 'year'))['totals']['expense']['comparisons'][0]['previous'] === 4901, 'Historical comparisons use the selected personal perspective');
	$check($data($fresh($scopeWorkspace)->annualOverview(2021, projectId: $scopeProject, entryScope: 'shared', comparison: 'year'))['totals']['expense']['comparisons'][0]['previous'] === 15101, 'Historical comparisons also support entire-area totals');
	foreach (['year', 'quarter', 'week', 'day'] as $breakdown) {
		$check($data($fresh($scopeWorkspace)->annualOverview(2020, projectId: $scopeProject, period: $breakdown, dateFrom: '2020-01-01', dateTo: '2020-01-10'))['totals']['expense']['total'] === 4901, 'Custom area dates retain personal cents with ' . $breakdown . ' columns');
	}
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo, entryScope: 'shared'))['entryScope'] === 'personal', 'Solo areas always use their personal representation');
	$check($data($fresh($scopeWorkspace)->annualOverview(2020, entryScope: 'shared'))['entryScope'] === 'personal', 'My finances cannot switch to shared sources');
	$check($fresh($workspace)->annualOverview(2020, projectId: $scopeProject)->getStatus() === 403, 'A personal area report cannot cross the member’s persisted workspace');
	$check($fresh($scopeWorkspace)->annualOverview(2020, projectId: $project, entryScope: 'shared')->getStatus() === 403, 'Entire-area reports also retain membership/workspace authorization');
	// New detail comparisons use the exact same authorized scope for curves, means and metrics.
	foreach (['personal' => 4901, 'shared' => 15101] as $entryScope => $expected) {
		$detailReport = $data($fresh($scopeWorkspace)->annualOverview(2021, projectId: $scopeProject, entryScope: $entryScope, period: 'day', dateFrom: '2021-01-01', dateTo: '2021-01-31', comparison: 'detail', detailType: 'expense'));
		$references = array_column($detailReport['detailComparisons'], null, 'id');
		$check(array_keys($references) === ['previous', 'year', 'average-previous-quarter'], 'Monthly details include a monthly average from the previous quarter in ' . $entryScope);
		$check($references['year']['previous'] === $expected, 'Prior-year reference respects exact stored ' . $entryScope . ' amounts');
		$check(array_sum($references['year']['values']) === $expected, 'Daily reference curve agrees with the amount shown');
		$filtered = $data($fresh($scopeWorkspace)->annualOverview(2021, projectId: $scopeProject, entryScope: $entryScope, period: 'day', dateFrom: '2021-01-01', dateTo: '2021-01-31', comparison: 'detail', detailType: 'expense', amountMin: '99999999'));
		$filteredReferences = array_column($filtered['detailComparisons'], null, 'id');
		$check($filteredReferences['year']['available'] && $filteredReferences['year']['previous'] === 0, 'Filtering to no payments keeps known scope coverage and zero reference amounts');
		foreach (['categories', 'paymentPartners', 'labelsAndTags'] as $grouping) {
			$weeklyDetail = $data($fresh($scopeWorkspace)->annualOverview(2020, grouping: $grouping, projectId: $scopeProject, entryScope: $entryScope, period: 'day', dateFrom: '2020-02-03', dateTo: '2020-02-09', comparison: 'detail', detailType: 'expense', detailAverageUnit: 'week'));
			$weeklyMeans = array_column($weeklyDetail['detailComparisons'], null, 'id');
			$check($weeklyMeans['average-previous-month']['fullAverage'] === (int)round($expected * 7 / 31), 'Prior-month weekly means follow exact ' . $entryScope . ' amounts in ' . $grouping);
			$check($weeklyMeans['average-previous-month']['values'] === array_fill(0, 7, (int)round($expected * 7 / 31)), 'Weekly benchmarks stay constant in daily charts');
			$monthlyDetail = $data($fresh($scopeWorkspace)->annualOverview(2020, grouping: $grouping, projectId: $scopeProject, entryScope: $entryScope, period: 'day', dateFrom: '2020-04-01', dateTo: '2020-04-30', comparison: 'detail', detailType: 'expense', detailAverageUnit: 'month'));
			$monthlyMeans = array_column($monthlyDetail['detailComparisons'], null, 'id');
			$check($monthlyMeans['average-previous-quarter']['fullAverage'] === (int)round($expected / 3), 'Prior-quarter monthly means include empty months and deduplicate labels in ' . $entryScope);
		}
		$filteredAverage = $data($fresh($scopeWorkspace)->annualOverview(2020, projectId: $scopeProject, entryScope: $entryScope, period: 'day', dateFrom: '2020-02-03', dateTo: '2020-02-09', comparison: 'detail', detailType: 'expense', detailAverageUnit: 'week', amountMin: '50'));
		$filteredMeans = array_column($filteredAverage['detailComparisons'], null, 'id');
		$check($filteredMeans['average-previous-month']['fullAverage'] === ($entryScope === 'shared' ? (int)round(10001 * 7 / 31) : 0), 'Average amount filters use the chosen stored perspective');
	}
	$wholeYear = $data($fresh($workspace)->annualOverview(2021, comparison: 'detail', detailType: 'all'));
	$yearReferences = array_column($wholeYear['detailComparisons'], null, 'id');
	$check(array_keys($yearReferences) === ['previous'], 'Whole-year balance totals have suitable, nonduplicated references');
	$check($yearReferences['previous']['previous'] === -306, 'Whole-year balance uses deduplicated income minus expense');
	$tagDetail = $data($fresh($workspace)->annualOverview(2021, grouping: 'labelsAndTags', period: 'day', dateFrom: '2021-01-01', dateTo: '2021-01-31', comparison: 'detail', detailType: 'expense'));
	$check(count($tagDetail['detailComparisons']) === 3, 'Label and tag totals accept period and prior-quarter average comparisons');
	$check($fresh($workspace)->annualOverview(2021, comparison: 'detail', detailType: 'all', detailId: '1')->getStatus() === 400, 'Reject balance subjects that are not totals');
	$check($fresh($workspace)->annualOverview(2021, comparison: 'detail', detailType: 'expense', detailId: 'invalid')->getStatus() === 400, 'Reject malformed comparison subjects');
	$check($fresh($workspace)->annualOverview(2021, comparison: 'detail', detailType: 'expense', detailAverageUnit: 'invalid')->getStatus() === 400, 'Reject malformed comparison units');
	$check($fresh($scopeWorkspace)->annualOverview(2021, comparison: 'detail', detailType: 'expense', projectId: $project, entryScope: 'shared')->getStatus() === 403, 'Detail histories cannot cross workspace membership');

	$averageYear = (int)(new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y');
	$averageCategory = $insert('categories', ['name' => 'Sparse current-year average', 'user_id' => $uid, 'workspace_id' => $workspace]);
	$entry(32400, ['date' => strtotime($averageYear . '-01-10 Europe/Vienna'), 'category_id' => $averageCategory]);
	$currentDetail = $data($fresh($workspace)->annualOverview($averageYear, comparison: 'detail', detailType: 'expense', detailId: (string)$averageCategory));
	$currentCategory = array_values(array_filter($currentDetail['groups']['expense'], static fn(array $row): bool => $row['id'] === $averageCategory))[0];
	$check($currentCategory['total'] === 32400 && $currentCategory['totalCount'] === 1, 'A single current-year category payment retains its exact chart data');
	$currentReferences = array_column($currentDetail['detailComparisons'], null, 'id');
	$check(array_keys($currentReferences) === ['previous'], 'Historical data does not add separate average variants to a yearly detail');
	$check($currentReferences['previous']['available'] && $currentReferences['previous']['previous'] === 0, 'A known empty previous period is still a valid zero baseline');

	foreach ([['personal', 4901, 3], ['shared', 15101, 4]] as [$perspective, $expected, $count]) {
		$composition = $data($fresh($scopeWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', projectId: $scopeProject, entryScope: $perspective));
		$check($composition['totals']['expense'] === ['amountCents' => $expected, 'count' => $count], 'Composition follows historical, zero/full and settled ' . $perspective . ' amounts');
		$check(array_sum(array_column($composition['groups']['categories']['expense'], 'amountCents')) === $expected, 'Category composition matches its scoped total');
	}
	foreach ([[(string)$parent, false, 303], [(string)$parent, true, 101], [(string)$child, false, 202]] as [$subject, $direct, $expected]) {
		$composition = $data($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-01', detailId: $subject, detailDirect: $direct));
		$check($composition['totals']['expense']['amountCents'] === $expected, 'Composition retains category parent/direct/child semantics');
	}
	$labelComposition = $data($fresh($labelWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', grouping: 'labelsAndTags'));
	$check($labelComposition['totals']['expense'] === ['amountCents' => $labelReport['totals']['expense']['total'], 'count' => $labelReport['totals']['expense']['totalCount']], 'Label composition deduplicates qualifying payments and excludes unlabelled payments');
	$tagComposition = $data($fresh($labelWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', grouping: 'labelsAndTags', detailId: 'tag:' . $hashtag));
	$check($tagComposition['totals']['expense'] === ['amountCents' => 260, 'count' => 2], 'Tag composition retains its exact payment population');
	$partnerComposition = $data($fresh($labelWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', grouping: 'paymentPartners', detailId: (string)$usedPartner, categories: (string)$usedCategory));
	$check($partnerComposition['totals']['expense']['amountCents'] === 77, 'Detail subject and payment filters intersect before grouping');
	$check($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-31', projectId: $scopeProject, entryScope: 'shared')->getStatus() === 403, 'Composition cannot cross the member’s workspace');
	$check($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-31', detailId: 'label:important')->getStatus() === 400, 'Reject subjects from a different grouping');
	$check($fresh($workspace)->reportBreakdown('2020-01-01', '2020-01-31', detailType: 'all', detailId: '1')->getStatus() === 400, 'Balance composition is available only for totals');
	$check($fresh($workspace)->reportBreakdown('2020-02-30', '2020-03-01')->getStatus() === 400, 'Composition validates calendar dates');
	$personalAreas = $data($fresh($scopeWorkspace)->areaOverview('2020-01-01', '2020-12-31'));
	$fullAreas = $data($fresh($scopeWorkspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area'));
	$check($personalAreas['totals']['expense']['total'] === 14900, 'Area rows plus Without area reconcile to My finances');
	$check($fullAreas['totals']['expense']['total'] === 25100, 'Full areas count authorized shared sources and unassigned personal payments once');
	$personalAreaRows = array_column($personalAreas['rows'], null, 'id');
	$fullAreaRows = array_column($fullAreas['rows'], null, 'id');
	$check($personalAreaRows[$scopeProject]['expense']['total'] === 4901 && $fullAreaRows[$scopeProject]['expense']['total'] === 15101, 'Area comparison preserves historical, zero/full and independently edited settled allocations');
	$check($fullAreaRows[0]['expense']['total'] === 9999 && $fullAreaRows[0]['entryScope'] === 'personal', 'Without area remains personal in both views');
	$check($data($fresh($emptyWorkspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area'))['totals']['expense']['total'] === 0, 'Whole-area comparisons cannot leak another workspace’s shared payments');
	$check($data($fresh($scopeWorkspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area', amountMin: '50'))['totals']['expense']['total'] === 20000, 'Amount filters use the selected full amounts');
	$check($data($fresh($scopeWorkspace)->annualOverview(2020, withoutArea: true))['totals']['expense']['total'] === 9999, 'Without-area detail charts exclude every area');
	$check($data($fresh($scopeWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', withoutArea: true))['totals']['expense']['amountCents'] === 9999, 'Without-area composition retains its exact population');
	$privateList = $data($fresh($scopeWorkspace)->index(withoutArea: true));
	$check($privateList['total'] === 1 && $listCents($privateList) === 9999, 'Without-area saved payment lists exclude area payments');
	$unassigned = $data($fresh($scopeWorkspace)->index(projects: '0'));
	$check($unassigned['total'] === 1 && $listCents($unassigned) === 9999, 'The area filter zero sentinel returns only genuinely unassigned payments');
	$unassignedDashboard = $data($fresh($scopeWorkspace)->dashboard(projects: '0'));
	$check($unassignedDashboard['total'] === 1 && $listCents($unassignedDashboard) === 9999, 'Payment dashboard and list agree on Without area');
	$check((int)round($unassignedDashboard['metrics']['total']['expense'] * 100) === 9999 && (int)round($unassignedDashboard['metrics']['total']['balance'] * 100) === -9999, 'Filtered statistic amounts match the unassigned payment population in exact cents');
	$mixedAreas = $data($fresh($scopeWorkspace)->index(projects: '0,' . $scopeProject));
	$check($listCents($mixedAreas) === 14900, 'Without area combines with a named area using OR without double counting projections');
	$check($listCents($data($fresh($emptyWorkspace)->index(projects: '0,' . $scopeProject))) === 0, 'Mixed area filters cannot escape the authenticated workspace');
	$csvUnassigned = $fresh($scopeWorkspace)->exportCsv(projects: '0');
	$check($csvUnassigned->getStatus() === 200 && count(array_filter(explode("\n", trim($csvUnassigned->render())))) === 2, 'CSV exports keep exactly the header and unassigned payment');
	$check($fresh($workspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'invalid')->getStatus() === 400, 'Reject invalid area amount modes');
	$baseAreaRows = array_column($data($fresh($workspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area'))['rows'], null, 'id');
	$check($baseAreaRows[$solo]['expense']['total'] === 303 && $baseAreaRows[$solo]['entryScope'] === 'personal', 'Solo areas retain ordinary personal payments in the full view');
	$savedTagAreas = $data($fresh($forecastWorkspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area', hashtags: (string)$forecastPersonalTag));
	$check($savedTagAreas['totals']['expense']['total'] === 10001, 'Normalized tag filters match saved shared source rows as well as forecasts');
	$check($data($fresh($forecastWorkspace)->areaOverview('2020-01-01', '2020-12-31', amountView: 'area', hashtags: (string)$forecastSourceTag))['totals']['expense']['total'] === 0, 'Foreign workspace tag IDs cannot inject a filter into a personal comparison');
	$archive = $db->getQueryBuilder();
	$archive->update('cobudget_projects')->set('is_archived', $archive->createNamedParameter(true, \PDO::PARAM_BOOL))->where($archive->expr()->eq('id', $archive->createNamedParameter($scopeProject, \PDO::PARAM_INT)))->executeStatement();
	$archivedAreaRows = array_column($data($fresh($scopeWorkspace)->areaOverview('2020-01-01', '2020-12-31'))['rows'], null, 'id');
	$check($archivedAreaRows[$scopeProject]['archived'] && $archivedAreaRows[$scopeProject]['expense']['total'] === 4901, 'Archival does not remove historical area comparisons');
	$zeroArea = $insert('projects', ['name' => 'Zero-share tag-only forecast', 'owner_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace]);
	$insert('members', ['project_id' => $zeroArea, 'user_id' => $uid, 'personal_workspace_id' => $emptyWorkspace, 'share_basis_points' => 0]);
	$insert('members', ['project_id' => $zeroArea, 'user_id' => '__annual_foreign__', 'personal_workspace_id' => $foreignWorkspace, 'share_basis_points' => 10000]);
	$zeroHead = $entry(7777, ['entry_kind' => 'shared', 'workspace_id' => $foreignWorkspace, 'project_id' => $zeroArea, 'recurrence_interval' => 'month', 'recurrence_multiplier' => 1, 'recurrence_next_date' => $futureDate, 'recurrence_end_date' => strtotime($futureYear . '-03-31 23:59:59 Europe/Vienna')]);
	$insert('entry_shares', ['entry_id' => $zeroHead, 'user_id' => $uid, 'share_basis_points' => 0, 'amount_cents' => 0]);
	$sourceOnlyTag = $insert('hashtags', ['workspace_id' => $foreignWorkspace, 'normalized_name' => 'area-only-forecast', 'display_name' => 'Area only', 'created_at' => $date, 'updated_at' => $date]);
	$insert('entry_hashtags', ['entry_id' => $zeroHead, 'hashtag_id' => $sourceOnlyTag, 'workspace_id' => $foreignWorkspace, 'created_at' => $date]);
	$zeroFull = $data($fresh($emptyWorkspace)->areaOverview($futureYear . '-01-01', $futureYear . '-12-31', amountView: 'area', grouping: 'labelsAndTags', includeFuturePayments: true));
	$check($zeroFull['totals']['expense']['total'] === 15554, 'An authorized zero-share user sees full tag-only forecasts even without a personal copy of that tag: ' . json_encode($zeroFull['totals']['expense']));
	$check($data($fresh($emptyWorkspace)->areaOverview($futureYear . '-01-01', $futureYear . '-12-31', includeFuturePayments: true))['totals']['expense']['total'] === 0, 'Zero-share forecasts never enter the default personal comparison');
	// End-of-period forecasts have their own cutoff: saved plans and known
	// recurrence occurrences remain visible even in a payments-up-to-today report.
	$forecastNow = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna'));
	$forecastToday = $forecastNow->setTime(0, 0);
	$forecastFrom = $forecastToday->modify('-9 days')->format('Y-m-d');
	$forecastTo = $forecastToday->modify('+10 days')->format('Y-m-d');
	$forecastNext = $forecastToday->modify('+1 day')->setTime(9, 0)->getTimestamp();
	$currentForecastWorkspace = $insert('workspaces', ['name' => 'Current forecast fixture', 'user_id' => $uid]);
	$currentForecastCategory = $insert('categories', ['name' => 'Everyday spending', 'user_id' => $uid, 'workspace_id' => $currentForecastWorkspace]);
	$currentForecastOther = $insert('categories', ['name' => 'Other plans', 'user_id' => $uid, 'workspace_id' => $currentForecastWorkspace]);
	$currentForecastArea = $insert('projects', ['name' => 'Current forecast shared area', 'owner_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace]);
	$insert('members', ['project_id' => $currentForecastArea, 'user_id' => $uid, 'personal_workspace_id' => $currentForecastWorkspace, 'share_basis_points' => 4000]);
	$insert('members', ['project_id' => $currentForecastArea, 'user_id' => '__annual_foreign__', 'personal_workspace_id' => $foreignWorkspace, 'share_basis_points' => 6000]);
	$currentForecastEntry = static fn(int $cents, array $extra = []): int => $entry($cents, array_replace(['workspace_id' => $currentForecastWorkspace, 'date' => strtotime($forecastFrom . ' 09:00 Europe/Vienna')], $extra));
	$currentForecastEntry(300000, ['type' => 'income']);
	$currentForecastEntry(70000, ['is_fixed_cost' => 1]);
	$currentForecastVariable = $currentForecastEntry(10000, ['category_id' => $currentForecastCategory]);
	$currentForecastEntry(2000, ['category_id' => $currentForecastCategory, 'date' => $forecastNext]);
	$currentForecastEntry(1000, ['category_id' => $currentForecastOther, 'date' => $forecastNext]);
	$currentForecastEntry(7000, ['recurrence_interval' => 'month', 'recurrence_multiplier' => 12, 'recurrence_next_date' => $forecastNext]);
	$currentForecastSource = $currentForecastEntry(10001, ['entry_kind' => 'shared', 'workspace_id' => $foreignWorkspace, 'project_id' => $currentForecastArea, 'recurrence_interval' => 'month', 'recurrence_multiplier' => 12, 'recurrence_next_date' => $forecastNext]);
	$insert('entry_shares', ['entry_id' => $currentForecastSource, 'user_id' => $uid, 'share_basis_points' => 2500, 'amount_cents' => 2500]);
	$currentForecastEntry(2500, ['project_id' => $currentForecastArea, 'source_entry_id' => $currentForecastSource, 'is_locked' => 1]);
	foreach (['forecast-one', 'forecast-two'] as $tagName) {
		$tagId = $insert('hashtags', ['workspace_id' => $currentForecastWorkspace, 'normalized_name' => $tagName, 'display_name' => $tagName, 'created_at' => $date, 'updated_at' => $date]);
		$insert('entry_hashtags', ['entry_id' => $currentForecastVariable, 'hashtag_id' => $tagId, 'workspace_id' => $currentForecastWorkspace, 'created_at' => $date]);
	}
	$periodForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo));
	$check($periodForecast['status'] === 'current' && $periodForecast['elapsedDays'] === 10 && $periodForecast['remainingDays'] === 10, 'Forecasts count the browser-timezone calendar including empty days');
	$check($periodForecast['actual'] === ['incomeCents' => 300000, 'expenseCents' => 89500, 'balanceCents' => 210500], 'Forecast actuals use personal rows exactly once, independent of tag joins');
	$check($periodForecast['planned']['expenseCents'] === 12500, 'Known plans combine saved future payments and stored exact-cent recurrence allocations');
	$check($periodForecast['variableCount'] === 1 && $periodForecast['estimated']['expenseCents'] === 8000, 'Recurring sources behind locked personal copies and fixed costs are excluded from the pace, and same-category plans cover part of the remainder');
	$check($periodForecast['forecast'] === ['incomeCents' => 300000, 'expenseCents' => 110000, 'balanceCents' => 190000], 'The personal forecast reconciles actuals, plans and additional estimates without projecting salary');
	$forecastMain = $data($fresh($currentForecastWorkspace)->annualOverview(dateFrom: $forecastFrom, dateTo: $forecastTo));
	$forecastMainFuture = $data($fresh($currentForecastWorkspace)->annualOverview(dateFrom: $forecastFrom, dateTo: $forecastTo, includeFuturePayments: true));
	$check($forecastMain['totals']['expense']['total'] === $periodForecast['actual']['expenseCents'] && $forecastMainFuture['totals']['expense']['total'] === $periodForecast['actual']['expenseCents'] + $periodForecast['planned']['expenseCents'], 'Statistical estimates never enter the normal tables or their future-payment totals');
	foreach ([['personal', 2500], ['shared', 10001]] as [$view, $expected]) {
		$areaForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, projectId: $currentForecastArea, entryScope: $view));
		$check($areaForecast['actual']['expenseCents'] === $expected && $areaForecast['planned']['expenseCents'] === $expected, 'Forecasts preserve the selected ' . $view . ' historical and planned amounts');
		$check($areaForecast['estimated'] === null, 'Known series alone do not invent a variable-spending estimate');
	}
	$withoutAreaForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, withoutArea: true));
	$check($withoutAreaForecast['actual']['expenseCents'] === 87000 && $withoutAreaForecast['planned']['expenseCents'] === 10000, 'Without-area forecast details exclude all area amounts');

	$forecastFilters = json_encode([['dimension' => 'type', 'id' => 'expense'], ['dimension' => 'categories', 'id' => (string)$currentForecastCategory, 'direct' => true], ['dimension' => 'paymentPartners', 'id' => '0']]);
	$nestedForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, reportFilters: $forecastFilters));
	$check($nestedForecast['actual']['expenseCents'] === 10000 && $nestedForecast['planned']['expenseCents'] === 2000 && $nestedForecast['actual']['incomeCents'] === 0, 'Forecast actuals and plans retain every nested predicate');
	$check($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, reportFilters: '{')->getStatus() === 400, 'Forecasts reject malformed drill-down identities');
	$sharedFilters = json_encode([['dimension' => 'categories', 'id' => '0'], ['dimension' => 'paymentPartners', 'id' => '0']]);
	foreach ([['personal', 2500], ['shared', 10001]] as [$view, $expected]) {
		$nestedArea = $data($fresh($currentForecastWorkspace)->annualOverview(dateFrom: $forecastFrom, dateTo: $forecastTo, projectId: $currentForecastArea, entryScope: $view, includeFuturePayments: true, reportFilters: $sharedFilters));
		$check($nestedArea['totals']['expense']['total'] === 2 * $expected, 'Nested area details preserve stored personal/full-area allocations for actuals and known recurrence plans');
	}

	$categoryForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, detailType: 'expense', detailId: (string)$currentForecastCategory));
	$check($categoryForecast['actual']['expenseCents'] === 10000 && $categoryForecast['planned']['expenseCents'] === 2000 && $categoryForecast['estimated']['expenseCents'] === 8000, 'A category detail uses its own population for all three forecast parts');
	$filteredForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, grouping: 'paymentPartners', categories: (string)$currentForecastCategory));
	$check($filteredForecast['forecast']['expenseCents'] === $categoryForecast['forecast']['expenseCents'], 'Forecast payment filters match report filters before grouping');
	$tagForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, grouping: 'labelsAndTags', detailType: 'expense', detailId: 'tag:' . $tagId));
	$check($tagForecast['actual']['expenseCents'] === 10000 && $tagForecast['variableCount'] === 1, 'Tag details count one payment even when several tags are joined');
	$fullFilterForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, projectId: $currentForecastArea, entryScope: 'shared', amountMin: '50'));
	$personalFilterForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, projectId: $currentForecastArea, amountMin: '50'));
	$check($fullFilterForecast['planned']['expenseCents'] === 10001 && $personalFilterForecast['planned']['expenseCents'] === 0, 'Amount filters use full or stored personal recurrence cents as selected');
	$futureOnlyForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastToday->modify('+1 day')->format('Y-m-d'), $forecastTo));
	$check($futureOnlyForecast['status'] === 'future' && $futureOnlyForecast['estimated'] === null && $futureOnlyForecast['planned']['expenseCents'] === 12500, 'Future-only dates show plans without inventing a consumption pace');
	$check($data($fresh($currentForecastWorkspace)->reportForecast('2020-01-01', '2020-12-31')) === ['status' => 'past'], 'Completed periods have no forecast');
	$check($fresh($workspace)->reportForecast($forecastFrom, $forecastTo, projectId: $currentForecastArea, entryScope: 'shared')->getStatus() === 403, 'Forecasts cannot cross a member’s persisted workspace');
	$check($fresh($foreignWorkspace)->reportForecast($forecastFrom, $forecastTo)->getStatus() === 403, 'Forecasts reject foreign workspace headers');
	$check($fresh($workspace)->reportForecast('2026-02-30', '2026-03-01')->getStatus() === 400, 'Forecasts validate exact calendar dates');
	$check($fresh($workspace)->reportForecast($forecastFrom, $forecastTo, detailType: 'all', detailId: '1')->getStatus() === 400, 'Balance forecasts require a total subject');
	$check($fresh($workspace)->reportForecast($forecastFrom, $forecastTo, detailId: 'label:important')->getStatus() === 400, 'Forecast subjects must belong to their grouping');
	$snapshotQuery = $db->getQueryBuilder();
	$snapshotQuery->select('amount_cents')->from('cobudget_entry_shares')->where($snapshotQuery->expr()->eq('entry_id', $snapshotQuery->createNamedParameter($currentForecastSource)));
	$snapshotResult = $snapshotQuery->executeQuery();
	$check((int)$snapshotResult->fetchOne() === 2500, 'Forecast reads leave immutable share snapshots unchanged');
	$snapshotResult->closeCursor();
	$incomeWorkspace = $insert('workspaces', ['name' => 'Income pattern forecast', 'user_id' => $uid]);
	$incomeCategory = $insert('categories', ['name' => 'Regular income', 'user_id' => $uid, 'workspace_id' => $incomeWorkspace]);
	$incomeDue = $forecastToday->modify('+5 days');
	$incomeRows = [];
	for ($back = 4; $back >= 1; $back--) {
		$month = $incomeDue->modify('first day of this month')->modify('-' . $back . ' months');
		$incomeDate = $month->setDate((int)$month->format('Y'), (int)$month->format('n'), min((int)$incomeDue->format('j'), (int)$month->format('t')))->setTime(9, 0)->getTimestamp();
		$incomeRows[] = $entry(333333, ['workspace_id' => $incomeWorkspace, 'category_id' => $incomeCategory, 'type' => 'income', 'date' => $incomeDate]);
		if ($back === 2) {
			$beforeFour = $data($fresh($incomeWorkspace)->reportForecast($forecastFrom, $forecastTo));
			$check($beforeFour['incomePatternCount'] === 0 && $beforeFour['estimated'] === null, 'Three earlier salary entries do not imply an income pattern');
		}
	}
	foreach (['year', 'quarter', 'week', 'day'] as $chartPeriod) {
		$salaryForecast = $data($fresh($incomeWorkspace)->reportForecast($forecastFrom, $forecastTo, period: $chartPeriod));
		$check($salaryForecast['incomePatternCount'] === 1 && $salaryForecast['actual']['incomeCents'] === 0 && $salaryForecast['estimated']['incomeCents'] === 333333, 'Four authorized earlier salaries support current income estimates in ' . $chartPeriod . ' buckets without entering the actual total');
		foreach (['actual', 'planned', 'estimated'] as $part) foreach (['income', 'expense', 'balance'] as $kind) {
			$check(array_sum($salaryForecast['chart'][$part][$kind]) === ($salaryForecast[$part][$kind . 'Cents'] ?? 0), 'Forecast chart components reconcile to the table: ' . $part . '/' . $kind . '/' . $chartPeriod);
		}
	}
	$entry(345678, ['workspace_id' => $incomeWorkspace, 'category_id' => $incomeCategory, 'type' => 'income', 'date' => $incomeDue->setTime(9, 0)->getTimestamp()]);
	$knownSalaryForecast = $data($fresh($incomeWorkspace)->reportForecast($forecastFrom, $forecastTo));
	$check($knownSalaryForecast['planned']['incomeCents'] === 345678 && $knownSalaryForecast['estimated']['incomeCents'] === 0 && $knownSalaryForecast['forecast']['incomeCents'] === 345678, 'Known future income supersedes an inferred occurrence even when the amount changes');
	$check($data($fresh($emptyWorkspace)->reportForecast($forecastFrom, $forecastTo))['incomePatternCount'] === 0, 'Other workspaces cannot borrow the salary history');
	$check($fresh($incomeWorkspace)->reportForecast($forecastFrom, $forecastTo, period: 'invalid')->getStatus() === 400, 'Forecast chart buckets validate their period');
	$sharedIncomeCategory = $insert('categories', ['name' => 'Area salary', 'user_id' => '__annual_foreign__', 'workspace_id' => $foreignWorkspace, 'project_id' => $currentForecastArea]);
	for ($back = 4; $back >= 1; $back--) {
		$month = $incomeDue->modify('first day of this month')->modify('-' . $back . ' months');
		$incomeDate = $month->setDate((int)$month->format('Y'), (int)$month->format('n'), min((int)$incomeDue->format('j'), (int)$month->format('t')))->setTime(9, 0)->getTimestamp();
		$salarySource = $entry(10001, ['workspace_id' => $foreignWorkspace, 'entry_kind' => 'shared', 'project_id' => $currentForecastArea, 'category_id' => $sharedIncomeCategory, 'type' => 'income', 'date' => $incomeDate]);
		$insert('entry_shares', ['entry_id' => $salarySource, 'user_id' => $uid, 'share_basis_points' => 2500, 'amount_cents' => 2500]);
		$entry(2500, ['workspace_id' => $currentForecastWorkspace, 'project_id' => $currentForecastArea, 'source_entry_id' => $salarySource, 'is_locked' => 1, 'category_id' => $currentForecastCategory, 'type' => 'income', 'date' => $incomeDate]);
	}
	foreach ([['personal', 2500], ['shared', 10001]] as [$view, $expected]) {
		$areaIncomeForecast = $data($fresh($currentForecastWorkspace)->reportForecast($forecastFrom, $forecastTo, detailType: 'income', projectId: $currentForecastArea, entryScope: $view));
		$check($areaIncomeForecast['incomePatternCount'] === 1 && $areaIncomeForecast['estimated']['incomeCents'] === $expected && $areaIncomeForecast['forecast']['incomeCents'] === $expected, 'Income patterns retain historical exact cents in the ' . $view . ' perspective rather than recalculating current default shares');
	}
	$session->setUser(null);
	$check($fresh($workspace)->reportForecast($forecastFrom, $forecastTo)->getStatus() === 401, 'Forecasts require authentication');
	$check($fresh($workspace)->areaOverview('2020-01-01', '2020-12-31')->getStatus() === 401, 'Area comparisons require authentication');
	$check($fresh($workspace)->reportBreakdown('2020-01-01', '2020-12-31')->getStatus() === 401, 'Composition requires authentication');
	$check($fresh($workspace)->annualOverview(2020)->getStatus() === 401, 'Unauthenticated annual access is denied');
	$session->setUser($user);
	$rankingWorkspace = $insert('workspaces', ['name' => 'Top 10 fixture', 'user_id' => $uid]);
	for ($rank = 1; $rank <= 15; $rank++) $entry($rank * 100 + 1, ['workspace_id' => $rankingWorkspace, 'is_important' => $rank % 2]);
	$entry(8888, ['workspace_id' => $rankingWorkspace, 'type' => 'income']);
	$entry(999999, ['workspace_id' => $rankingWorkspace, 'user_id' => '__annual_foreign__']);
	$entry(777777, ['workspace_id' => $rankingWorkspace, 'date' => $futureDate]);
	$ranked = $data($fresh($rankingWorkspace)->index(limit: 10, type: 'expense', sortBy: 'amount', sortDir: 'desc', dateFrom: $date - 1, dateTo: $futureDate));
	$check(array_map(static fn(array $row): int => (int)round((float)$row['amount'] * 100), $ranked['entries']) === array_map(static fn(int $rank): int => $rank * 100 + 1, range(15, 6)), 'Top 10 limits after exact-cent descending ordering, excluding income, foreign users and future dates');
	$rankedIncome = $data($fresh($rankingWorkspace)->index(limit: 10, type: 'income', sortBy: 'amount', sortDir: 'desc', dateFrom: $date - 1, dateTo: $date + 1));
	$check(array_map(static fn(array $row): int => (int)round((float)$row['amount'] * 100), $rankedIncome['entries']) === [8888], 'Income ranking remains independent and returns fewer than ten rows without filler');
	$rankedImportant = $data($fresh($rankingWorkspace)->index(limit: 10, type: 'expense', sortBy: 'amount', sortDir: 'desc', dateFrom: $date - 1, dateTo: $date + 1, labels: 'important', amountMin: '10'));
	$check(array_map(static fn(array $row): int => (int)round((float)$row['amount'] * 100), $rankedImportant['entries']) === [1501, 1301, 1101], 'Ranking applies report labels, amount limits and exact selected dates before limiting');
	$rankedFuture = $data($fresh($rankingWorkspace)->index(limit: 10, type: 'expense', sortBy: 'amount', sortDir: 'desc', includeFuturePayments: true, dateFrom: $date - 1, dateTo: $futureDate));
	$check((int)round((float)$rankedFuture['entries'][0]['amount'] * 100) === 777777, 'Explicit future-payment mode includes saved future payments in the ranking');
	$lookupWorkspace = $insert('workspaces', ['name' => 'Lookup grouping fixture', 'user_id' => $uid]);
	$lookupArea = $insert('projects', ['name' => 'Haus', 'owner_id' => $uid, 'workspace_id' => $lookupWorkspace]);
	$insert('members', ['project_id' => $lookupArea, 'user_id' => $uid, 'personal_workspace_id' => $lookupWorkspace, 'share_basis_points' => 10000]);
	$lookupCat = $insert('categories', ['name' => 'Strom', 'user_id' => $uid, 'workspace_id' => $lookupWorkspace]);
	$lookupOther = $insert('categories', ['name' => ' STROM ', 'user_id' => $uid, 'workspace_id' => $lookupWorkspace, 'project_id' => $lookupArea]);
	$lookupPartner = $insert('payment_partners', ['name' => 'Anbieter', 'user_id' => $uid, 'workspace_id' => $lookupWorkspace]);
	$lookupPartnerOther = $insert('payment_partners', ['name' => 'ANBIETER', 'user_id' => $uid, 'workspace_id' => $lookupWorkspace, 'project_id' => $lookupArea]);
	$entry(101, ['workspace_id' => $lookupWorkspace, 'category_id' => $lookupCat, 'payment_partner_id' => $lookupPartner]);
	$entry(202, ['workspace_id' => $lookupWorkspace, 'category_id' => $lookupOther, 'payment_partner_id' => $lookupPartnerOther, 'project_id' => $lookupArea]);
	$entry(303, ['workspace_id' => $lookupWorkspace, 'category_id' => $lookupCat, 'payment_partner_id' => $lookupPartner, 'project_id' => $lookupArea]);
	$entry(999999, ['workspace_id' => $lookupWorkspace, 'user_id' => '__annual_foreign__', 'category_id' => $lookupCat, 'payment_partner_id' => $lookupPartner]);
	foreach (['categories', 'paymentPartners'] as $dimension) {
		$grouped = $data($fresh($lookupWorkspace)->annualOverview(2020, $dimension));
		$check(count($grouped['groups']['expense']) === 1, 'Equal lookup titles form one report row: ' . $dimension);
		$group = $grouped['groups']['expense'][0];
		$check($group['total'] === 606 && array_sum(array_column($group['areas'], 'total')) === 606, 'Grouped and area totals exclude foreign users: ' . $dimension);
		$check(count($group['areas']) === 2, 'Area split follows entries, including a reused category ID');
		foreach (array_merge([$group], $group['areas']) as $subject) {
			$filter = json_encode([['dimension' => $dimension, 'id' => $subject['reportKey'], 'direct' => false]]);
			$list = $data($fresh($lookupWorkspace)->index(type: 'expense', reportFilters: $filter));
			$check($listCents($list) === $subject['total'], 'Name/area payment links match the displayed total: ' . $dimension . ' expected=' . $subject['total'] . ' actual=' . $listCents($list) . ' key=' . $subject['reportKey']);
			$composition = $data($fresh($lookupWorkspace)->reportBreakdown('2020-01-01', '2020-12-31', $dimension, detailId: $subject['reportKey']));
			$check($composition['totals']['expense']['amountCents'] === $subject['total'], 'Composition keeps name/area identity');
			$narrowed = $data($fresh($lookupWorkspace)->annualOverview(2020, $dimension, reportFilters: $filter));
			$check($narrowed['totals']['expense']['total'] === $subject['total'], 'Nested reports retain the exact lookup scope');
			$check($data($fresh($emptyWorkspace)->annualOverview(2020, $dimension, reportFilters: $filter))['totals']['expense']['total'] === 0, 'Lookup identities do not grant access across workspaces');
			$comparison = $data($fresh($lookupWorkspace)->annualOverview(2020, $dimension, comparison: 'detail', detailType: 'expense', detailId: $subject['reportKey']));
			$check($comparison['detailComparisons'][0]['current'] === $subject['total'], 'Comparison subject matches the grouped report');
		}
	}
	$entry(404, ['workspace_id' => $lookupWorkspace, 'category_id' => $lookupOther, 'payment_partner_id' => $lookupPartnerOther, 'project_id' => $lookupArea, 'recurrence_interval' => 'month', 'recurrence_multiplier' => 1, 'recurrence_next_date' => $futureDate, 'recurrence_end_date' => $futureDate + 86400]);
	foreach (['categories', 'paymentPartners'] as $dimension) {
		$planned = $data($fresh($lookupWorkspace)->annualOverview($futureYear, $dimension, includeFuturePayments: true));
		$group = $planned['groups']['expense'][0];
		$check($group['total'] === 404 && $group['areas'][0]['name'] === 'Haus', 'Projected occurrences keep their area and name group: ' . json_encode($group));
		$subject = $group['areas'][0]['reportKey'];
		$filter = json_encode([['dimension' => $dimension, 'id' => $subject, 'direct' => false]]);
		$narrowed = $data($fresh($lookupWorkspace)->annualOverview($futureYear, $dimension, includeFuturePayments: true, reportFilters: $filter));
		$check($narrowed['totals']['expense']['total'] === 404, 'Grouped scope retains a visible recurring series before it has future saved payments');
		$futureForecast = $data($fresh($lookupWorkspace)->reportForecast($futureYear . '-01-01', $futureYear . '-12-31', $dimension, detailType: 'expense', detailId: $subject));
		$check($futureForecast['planned']['expenseCents'] === 404, 'Forecast matches the same grouped area subject');
	}
	echo "Annual overview integration passed: {$assertions} assertions; fixtures rolled back.\n";
} finally {
	$db->rollBack();
	$session->setUser($originalUser);
}

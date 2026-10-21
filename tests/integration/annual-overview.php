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
	$parent = $insert('categories', ['name' => 'Home', 'user_id' => $uid, 'workspace_id' => $workspace]);
	$child = $insert('categories', ['name' => 'Rent', 'user_id' => $uid, 'workspace_id' => $workspace, 'parent_category_id' => $parent]);
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
	$check($report['years'] === [$futureYear, 2020], 'Year list is local-time aware, unique, unpaginated and excludes foreign users/workspaces');
	$check($data($fresh($workspace)->annualOverview(2020, 'paymentPartners'))['years'] === $report['years'], 'Grouping does not change year choices');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project))['years'] === [2020], 'Shared area year choices exclude other personal payments');
	$personalChoices = $report['filterChoices'];
	$check(in_array($parent, $personalChoices['categoryIds'], true) && in_array($child, $personalChoices['categoryIds'], true) && in_array(0, $personalChoices['categoryIds'], true), 'Category choices are the categories used in that year, including payments without one');
	$check(in_array($partner, $personalChoices['partnerIds'], true) && in_array(0, $personalChoices['partnerIds'], true), 'Payment-partner choices are the partners used in that year, including payments without one');
	$sharedChoices = $data($fresh($workspace)->annualOverview(2020, projectId: $project))['filterChoices'];
	$check($sharedChoices['categoryIds'] === [0] && !in_array($parent, $sharedChoices['categoryIds'], true), 'Area filter choices stay inside that area');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo))['years'] === [2020], 'Solo area year choices are scoped to that area');
	$check($data($fresh($emptyWorkspace)->annualOverview(2020))['years'] === [], 'Empty workspace has no year choices');
	$check($data($fresh($workspace)->annualOverview(2020, includeFuturePayments: true))['years'] === $report['years'], 'Year discovery includes future entries independently of the cutoff toggle');
	$check($report['totals']['expense']['total'] === 5306, 'Personal sum excludes shared sources, foreign users/workspaces and includes all pages');
	$check($report['totals']['expense']['totalCount'] === 305, 'Payment counts include every row even when SQL groups identical dates and categories');
	$check($report['totals']['balance']['totalCount'] === 306, 'Balance count includes income and expenses exactly once');
	$check($report['totals']['expense']['counts'][0] === 305 && $report['totals']['expense']['counts'][1] === 0, 'Monthly counts follow the same local dates as amounts');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project))['totals']['expense']['totalCount'] === 2, 'Shared payment counts exclude personal projections');
	$check($report['totals']['balance']['months'][0] === -306, 'Local January includes midnight payments and exact cents');
	$check(array_sum(array_column($report['groups']['expense'], 'total')) === 5306, 'Parent totals never double count');
	$check($report['periods'][0]['dateFrom'] === strtotime('2020-01-01 Europe/Vienna'), 'Response preserves browser timezone boundaries');
	$check($data($fresh($workspace)->annualOverview(2020, 'paymentPartners'))['totals'] === $report['totals'], 'Changing grouping preserves every total');
	$untagged = $data($fresh($workspace)->annualOverview(2020, 'labelsAndTags'));
	$check($untagged['groups']['expense'] === [] && $untagged['groups']['income'] === [], 'Payments without an enabled label or tag have no rows');
	$check($untagged['totals']['expense']['total'] === 0 && $untagged['totals']['expense']['totalCount'] === 0, 'Payments without an enabled label or tag stay out of the expense total');
	$check($untagged['totals']['income']['total'] === 0 && $untagged['totals']['income']['totalCount'] === 0, 'Payments without an enabled label or tag stay out of the income total');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project))['totals']['expense']['total'] === 11000, 'Shared view uses complete source amounts including settlements');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $solo))['totals']['expense']['total'] === 303, 'Solo area uses personal entries');
	$check($data($fresh($workspace)->annualOverview($futureYear))['totals']['expense']['total'] === 0, 'Future payments are opt-in');
	$futureReport = $data($fresh($workspace)->annualOverview($futureYear, includeFuturePayments: true));
	$check($futureReport['totals']['expense']['total'] === 777 + 11 * 101, 'Future overview includes the saved payment and February-to-December recurring occurrences');
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
	$filteredDashboard = $data($fresh($labelWorkspace)->dashboard(type: 'expense', dateFrom: $labelPeriod['dateFrom'], dateTo: $labelPeriod['dateTo'], hashtags: (string)$hashtag));
	$check($filteredDashboard['total'] === 2 && $listCents($filteredDashboard) === 260, 'Personal payment page uses the same tag filter');

	$comparison = $data($fresh($workspace)->annualOverview(2020, comparison: 'month'));
	$check($comparison['totals']['expense']['comparisons'][0] === null, 'Comparison history excludes foreign earlier entries');
	$check($comparison['totals']['expense']['comparisons'][1]['previous'] === 5306, 'February compares scoped January payments including all pages');
	$check($comparison['totals']['expense']['comparisons'][1]['delta'] === -5306, 'Empty month after history begins has a real decline');
	$check($data($fresh($workspace)->annualOverview(2021, comparison: 'year'))['totals']['expense']['comparisons'][0]['previous'] === 5306, 'Prior-year comparison crosses the report query boundary');
	$check($data($fresh($workspace)->annualOverview(2021, comparison: 'year'))['totals']['expense']['comparisons'][1] === null, 'No prior-year payments means no comparison');
	$check($data($fresh($workspace)->annualOverview(2020, projectId: $project, comparison: 'month'))['totals']['expense']['comparisons'][1]['previous'] === 11000, 'Shared comparison uses full source amounts without personal duplicates');
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
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear))['totals']['expense']['total'] === 0, 'Shared forecasts remain opt-in');
	$check($data($fresh($emptyWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 0, 'Shared forecasts never enter another personal workspace');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, includeFuturePayments: true))['totals']['expense']['total'] === 20002, 'Area forecasts retain the full shared source amounts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, 'paymentPartners', includeFuturePayments: true))['totals'] === $personalForecast['totals'], 'Personal shared forecasts preserve totals across grouping');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, amountMin: '35', amountMax: '45'))['totals']['expense']['total'] === 8002, 'Amount limits filter shared forecasts by the personal share');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, amountMin: '50'))['totals']['expense']['total'] === 0, 'A full source amount never makes a smaller personal allocation match');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, 'paymentPartners', includeFuturePayments: true, categories: (string)$forecastCategory))['totals']['expense']['total'] === 8002, 'Category filters retain matching shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, partners: (string)$forecastPartner))['totals']['expense']['total'] === 8002, 'Payment-partner filters retain matching shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, hashtags: (string)$forecastPersonalTag))['totals']['expense']['total'] === 8002, 'Personal tag IDs match shared-series tags by normalized name');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true, hashtags: (string)$forecastSourceTag))['totals']['expense']['total'] === 0, 'A foreign workspace tag ID cannot filter a personal shared forecast');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, includeFuturePayments: true, hashtags: (string)$forecastSourceTag))['totals']['expense']['total'] === 20002, 'Area tag filters retain their own workspace IDs');
	$forecastLabels = $data($fresh($forecastWorkspace)->annualOverview($futureYear, 'labelsAndTags', includeFuturePayments: true));
	$check($forecastLabels['totals']['expense']['total'] === 8002 && $forecastLabels['totals']['expense']['totalCount'] === 2, 'Labels and tags count shared personal forecast amounts once');
	$forecastTagRows = array_values(array_filter($forecastLabels['groups']['expense'], static fn(array $row): bool => $row['kind'] === 'hashtag'));
	$check(count($forecastTagRows) === 1 && $forecastTagRows[0]['filter'] === (string)$forecastPersonalTag && $forecastTagRows[0]['name'] === '#HOLIDAY', 'Forecast tag rows and drilldowns use the personal workspace identity');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'quarter', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Quarterly reports include the same shared personal forecast');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'month', month: 3, includeFuturePayments: true))['totals']['expense']['total'] === 4001, 'Monthly reports include only the matching shared personal occurrence');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'week', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Weekly reports include shared personal forecasts');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, period: 'day', includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'Daily reports include shared personal forecasts');
	$check($beforeForecast === $forecastState(), 'Forecast reads never create payments, rewrite snapshots or update rounding balances or tags');

	// Simulate advancing the source series after an occurrence was generated.
	$nextSource = $entry(10001, array_replace($sourceValues, ['date' => $futureDate, 'recurrence_next_date' => strtotime($futureYear . '-03-01 09:00 Europe/Vienna')]));
	$nextPersonal = $entry(4001, array_replace($personalValues, ['date' => $futureDate, 'source_entry_id' => $nextSource]));
	$insert('entry_shares', ['entry_id' => $nextSource, 'user_id' => $uid, 'share_basis_points' => 4000, 'amount_cents' => 4001, 'personal_entry_id' => $nextPersonal]);
	$insert('entry_shares', ['entry_id' => $nextSource, 'user_id' => '__annual_foreign__', 'share_basis_points' => 6000, 'amount_cents' => 6000]);
	$advance = $db->getQueryBuilder();
	$advance->update('cobudget_entries')->set('recurrence_interval', $advance->createNamedParameter(null, \PDO::PARAM_NULL))->set('recurrence_next_date', $advance->createNamedParameter(null, \PDO::PARAM_NULL))->where($advance->expr()->eq('id', $advance->createNamedParameter($forecastSource, \PDO::PARAM_INT)))->executeStatement();
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'A saved personal occurrence replaces its forecast without double counting');
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, projectId: $forecastProject, includeFuturePayments: true))['totals']['expense']['total'] === 20002, 'Advancing the source series also preserves the full area forecast');
	$zeroSource = $entry(10001, $sourceValues);
	$insert('entry_shares', ['entry_id' => $zeroSource, 'user_id' => $uid, 'share_basis_points' => 0, 'amount_cents' => 0]);
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 8009, 'A zero-share shared series never enters personal forecasts');
	$insert('members', ['project_id' => $forecastProject, 'user_id' => 'former:annual-forecast-fixture', 'share_basis_points' => 0]);
	$check($data($fresh($forecastWorkspace)->annualOverview($futureYear, includeFuturePayments: true))['totals']['expense']['total'] === 4008, 'Former participants pause future shared forecasts while saved personal payments remain');
	$session->setUser(null);
	$check($fresh($workspace)->annualOverview(2020)->getStatus() === 401, 'Unauthenticated annual access is denied');
	echo "Annual overview integration passed: {$assertions} assertions; fixtures rolled back.\n";
} finally {
	$db->rollBack();
	$session->setUser($originalUser);
}

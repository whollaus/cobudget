<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;

require_once dirname(__DIR__, 2) . '/lib/Service/AnnualOverviewService.php';

return [
	'Annual comparisons respect history, gaps, exact thresholds and elapsed calendar days' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 12:00', $zone);
		$entry = static fn(string $date, int $amount, array $extra = []): array => array_replace(['date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'amount_cents' => $amount, 'type' => 'expense', 'group_id' => 1, 'group_name' => 'Home'], $extra);
		$entries = [$entry('2025-10-01', 5000), $entry('2026-01-10', 10000), $entry('2026-02-10', 10500), $entry('2026-02-25', 90000), $entry('2026-03-10', 7000), $entry('2026-03-25', 99999)];
		$report = $service->build($entries, 2026, $zone, 'categories');
		$result = $service->compare($report, $entries, 2026, $now, 'categories', 'month');
		$change = $result['totals']['expense']['comparisons'];
		$t->assertSame(0, $change[0]['previous'], 'January compares empty December when history started earlier');
		$t->assertSame(null, $change[0]['percent'], 'Zero baseline has no percentage or infinity');
		$t->assertSame(null, $change[0]['direction'], 'No percentage means no threshold arrow');
		$t->assertSame(10500, $change[2]['previous'], 'March compares only February 1–19');
		$t->assertSame(7000, $change[2]['current'], 'Even with future payments displayed, comparisons exclude them');
		$t->assertSame(-3500, $change[2]['delta'], 'Euro change uses exact cents');
		$t->assertSame('down', $change[2]['direction'], 'Decrease beyond 5% gets a down arrow');
		$t->assertSame('2026-02-19', $change[2]['previousTo'], 'Tooltip has the actual elapsed dates');
		$t->assertSame(null, $change[3], 'Future month has no comparison');
		$t->assertSame($change, $result['groups']['expense'][0]['comparisons'], 'Group and totals use identical arithmetic');
		$first = [$entry('2026-10-02', 10000)];
		$later = new DateTimeImmutable('2026-12-31', $zone);
		$report = $service->build($first, 2026, $zone, 'categories');
		$result = $service->compare($report, $first, 2026, $later, 'categories', 'month');
		$t->assertSame(null, $result['totals']['expense']['comparisons'][9], 'First October never compares unknown September');
		$t->assertSame(-10000, $result['totals']['expense']['comparisons'][10]['delta'], 'November compares October even without November payments');
		$t->assertSame(0, $result['totals']['expense']['comparisons'][11]['previous'], 'Empty November remains a known zero');
		foreach ([9500 => null, 10500 => null, 9499 => 'down', 10501 => 'up'] as $amount => $direction) {
			$rows = [$entry('2026-01-01', 10000), $entry('2026-02-01', $amount)];
			$r = $service->compare($service->build($rows, 2026, $zone, 'categories'), $rows, 2026, $later, 'categories', 'month');
			$t->assertSame($direction, $r['totals']['expense']['comparisons'][1]['direction'], 'Strict ±5% boundary');
		}
		$rows = [$entry('2025-03-05', 2000), $entry('2025-03-25', 9000), $entry('2026-03-05', 3000)];
		$r = $service->compare($service->build($rows, 2026, $zone, 'categories'), $rows, 2026, $now, 'categories', 'year');
		$t->assertSame(50, (int)$r['totals']['expense']['comparisons'][2]['percent'], 'Prior year uses the same day cutoff');
		$t->assertSame(null, $r['totals']['expense']['comparisons'][1], 'No prior-year entry means no comparison');
		$rows = [$entry('2024-02-29', 1000), $entry('2024-03-01', 2000)];
		$leapNow = new DateTimeImmutable('2024-03-31 12:00', $zone);
		$r = $service->compare($service->build($rows, 2024, $zone, 'categories'), $rows, 2024, $leapNow, 'categories', 'month');
		$t->assertSame('2024-02-29', $r['totals']['expense']['comparisons'][2]['previousTo'], 'Shorter reference month clamps to leap-day, never overflows');
	},

	'Annual year choices use actual local payment years without filling gaps' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$dates = [strtotime('2025-12-31 23:30 UTC'), strtotime('2023-06-01 UTC'), strtotime('2023-07-01 UTC'), strtotime('1960-01-01 UTC')];
		$t->assertSame([2026, 2023], $service->availableYears($dates, new DateTimeZone('Europe/Vienna')), 'Local boundary, duplicate dates and empty years are handled');
		$t->assertSame([2025, 2023], $service->availableYears($dates, new DateTimeZone('America/Los_Angeles')), 'Years follow the browser timezone');
		$t->assertSame([], $service->availableYears([], new DateTimeZone('UTC')), 'Empty scopes offer no years');
	},
	'Annual overview counts parent, child and unassigned payments exactly once' => function(TestRunner $t): void {
		$base = ['date' => strtotime('2026-01-15 UTC'), 'type' => 'expense', 'group_id' => 4, 'group_name' => 'Home', 'parent_id' => null];
		$rows = [
			$base + ['amount_cents' => 101],
			array_replace($base, ['group_id' => 5, 'group_name' => 'Rent', 'parent_id' => 4, 'parent_name' => 'Home', 'amount_cents' => 102]),
			array_replace($base, ['group_id' => null, 'group_name' => null, 'amount_cents' => 3]),
			array_replace($base, ['group_id' => 6, 'group_name' => 'Other', 'amount_cents' => 4]),
			array_replace($base, ['type' => 'income', 'amount_cents' => 1000]),
		];
		$result = (new AnnualOverviewService())->build($rows, 2026, new DateTimeZone('Europe/Vienna'), 'categories');
		$t->assertSame(210, $result['totals']['expense']['total'], 'Expenses must not double-count parent totals');
		$t->assertSame(790, $result['totals']['balance']['months'][0], 'Balance uses exact integer cents');
		$t->assertSame(203, $result['groups']['expense'][0]['total'], 'Parent includes direct and child payments');
		$t->assertSame(2, count($result['groups']['expense'][0]['children']), 'Expanded parent exposes direct and child rows');
		$t->assertSame(0, $result['groups']['expense'][1]['id'], 'Missing assignment has its own drilldown key');
		$t->assertSame(12, count($result['totals']['expense']['months']), 'All months remain visible');
		$t->assertSame(0, $result['totals']['expense']['months'][11], 'Empty months remain zero');
		$t->assertSame(4, $result['totals']['expense']['totalCount'], 'Each expense counts once');
		$t->assertSame(5, $result['totals']['balance']['counts'][0], 'Balance counts income and expense payments');
		$t->assertSame(2, $result['groups']['expense'][0]['totalCount'], 'Parent count includes direct and child payments once');
		$t->assertSame(1, $result['groups']['expense'][0]['children'][0]['totalCount'], 'Direct parent payments retain their own count');
	},
	'Annual overview preserves SQL payment counts when dates and groups coincide' => function(TestRunner $t): void {
		$rows = [['date' => strtotime('2026-01-15 UTC'), 'type' => 'expense', 'group_id' => 1, 'group_name' => 'Test', 'amount_cents' => 0, 'payment_count' => 17]];
		$result = (new AnnualOverviewService())->build($rows, 2026, new DateTimeZone('UTC'), 'paymentPartners');
		$t->assertSame(17, $result['groups']['expense'][0]['totalCount'], 'Grouped SQL rows keep their full count, even with zero amounts');
		$t->assertSame(17, $result['totals']['balance']['totalCount'], 'Balance includes every stored payment, not twelve artificial month additions');
		$t->assertSame(0, $result['totals']['balance']['counts'][1], 'Empty months have zero counts');
	},
	'Annual overview respects local year boundaries and keeps partner IDs separate' => function(TestRunner $t): void {
		$rows = [
			['date' => strtotime('2025-12-31 23:30 UTC'), 'type' => 'income', 'group_id' => 1, 'group_name' => 'Same name', 'amount_cents' => 1],
			['date' => strtotime('2026-12-31 23:30 UTC'), 'type' => 'income', 'group_id' => 1, 'group_name' => 'Same name', 'amount_cents' => 99],
			['date' => strtotime('2026-02-15 UTC'), 'type' => 'expense', 'group_id' => 2, 'group_name' => 'Same name', 'amount_cents' => 2],
			['date' => strtotime('2026-02-15 UTC'), 'type' => 'expense', 'group_id' => 3, 'group_name' => 'Same name', 'amount_cents' => 3],
		];
		$service = new AnnualOverviewService();
		$result = $service->build($rows, 2026, new DateTimeZone('Europe/Vienna'), 'paymentPartners');
		$t->assertSame(1, $result['totals']['income']['total'], 'Year follows the displayed local date');
		$t->assertSame(2, count($result['groups']['expense']), 'Equal names must not merge distinct partners');
		$t->assertSame([], $result['groups']['expense'][0]['children'], 'Partners have no category hierarchy');
		$t->assertSame(-4, $result['totals']['balance']['total'], 'Negative annual balances are preserved');
		$t->assertSame(99, $service->build($rows, 2026, new DateTimeZone('America/Los_Angeles'), 'paymentPartners')['totals']['income']['total'], 'Western timezone year boundary is respected');
		$t->assertSame(0, $service->build([], 2026, new DateTimeZone('UTC'), 'categories')['totals']['balance']['total'], 'Empty years have valid totals');
	},
	'Annual labels and tags overlap in rows and stay single-counted in totals' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('UTC');
		$date = strtotime('2026-05-10 UTC');
		$rows = $service->expandLabelsAndTags([
			['date' => $date, 'type' => 'expense', 'amount_cents' => 250, 'payment_count' => 1, 'is_important' => 1, 'is_insurance' => '1', 'needs_review' => true],
			['date' => $date, 'type' => 'expense', 'amount_cents' => 10, 'payment_count' => 1],
			['date' => $date, 'type' => 'expense', 'amount_cents' => 40, 'payment_count' => 1],
		], [
			['date' => $date, 'type' => 'expense', 'amount_cents' => 260, 'payment_count' => 2, 'group_id' => 4, 'group_name' => 'Alpen'],
			['date' => $date, 'type' => 'expense', 'amount_cents' => 1, 'payment_count' => 1, 'group_id' => 0, 'group_name' => 'skip'],
		], [
			'important' => ['column' => 'is_important', 'name' => 'Important'],
			'insurance' => ['column' => 'is_insurance', 'name' => 'Insurance'],
		], [
			['date' => $date, 'type' => 'expense', 'amount_cents' => 260, 'payment_count' => 2],
		]);
		$report = $service->build($rows, 2026, $zone, 'labelsAndTags');
		$t->assertSame(260, $report['totals']['expense']['total'], 'Overlapping labels and tags must not increase the year total');
		$t->assertSame(2, $report['totals']['expense']['totalCount'], 'Each qualifying payment counts once');
		$plain = $service->build($service->expandLabelsAndTags([
			['date' => $date, 'type' => 'expense', 'amount_cents' => 40, 'payment_count' => 1],
		], [], [
			'important' => ['column' => 'is_important', 'name' => 'Important'],
		]), 2026, $zone, 'labelsAndTags');
		$t->assertSame(0, $plain['totals']['expense']['total'], 'A payment without an enabled label or tag stays out of the total');
		$t->assertSame(0, $plain['totals']['expense']['totalCount'], 'That payment is not counted');
		$t->assertSame([], $plain['groups']['expense'], 'That payment has no label or tag row');
		$byFilter = [];
		foreach ($report['groups']['expense'] as $row) {
			$t->assertSame([], $row['children'], 'Labels and tags have no category hierarchy');
			$byFilter[$row['kind'] . ':' . $row['filter']] = $row;
		}
		$t->assertSame(250, $byFilter['label:important']['total'], 'Important keeps only its own payments');
		$t->assertSame(1, $byFilter['label:important']['totalCount'], 'Important counts its payment once');
		$t->assertSame('Important', $byFilter['label:important']['name'], 'Enabled labels use their display name');
		$t->assertSame(250, $byFilter['label:insurance']['total'], 'String database flags still count');
		$t->assertFalse(isset($byFilter['label:review']), 'Disabled labels stay hidden even when stored on the payment');
		$t->assertSame(260, $byFilter['hashtag:4']['total'], 'A tag includes every payment that uses it');
		$t->assertSame('#Alpen', $byFilter['hashtag:4']['name'], 'Tags are shown with a hash');
		$t->assertFalse(isset($byFilter['hashtag:0']), 'Incomplete hashtag rows are ignored');
		$split = $service->build($service->expandLabelsAndTags(
			[['date' => $date, 'type' => 'income', 'amount_cents' => 80, 'payment_count' => 1]],
			[
				['date' => $date, 'type' => 'income', 'amount_cents' => 80, 'payment_count' => 1, 'group_id' => 1, 'group_name' => 'alpha'],
				['date' => $date, 'type' => 'income', 'amount_cents' => 80, 'payment_count' => 1, 'group_id' => 2, 'group_name' => '#beta'],
			],
			[],
			[['date' => $date, 'type' => 'income', 'amount_cents' => 80, 'payment_count' => 1]]
		), 2026, $zone, 'labelsAndTags');
		$t->assertSame(80, $split['totals']['income']['total'], 'Several tags still count the payment once');
		$t->assertSame(2, count($split['groups']['income']), 'One payment with two tags produces two rows');
		$t->assertSame('#beta', $split['groups']['income'][1]['name'], 'An existing hash prefix is not doubled');
		$now = new DateTimeImmutable('2026-06-15 12:00', $zone);
		$compared = $service->compare($report, $rows, 2026, $now, 'labelsAndTags', 'month');
		$t->assertSame(260, $compared['totals']['expense']['comparisons'][5]['previous'], 'Comparisons keep the single-counted total');
		$important = null;
		foreach ($compared['groups']['expense'] as $row) {
			if (($row['filter'] ?? '') === 'important') {
				$important = $row;
			}
		}
		$t->assertSame(250, $important['comparisons'][5]['previous'], 'Label comparisons use only that label');
	},
	'Annual label grouping respects enabled settings and hashtag links' => function(TestRunner $t): void {
		$body = $t->methodBody('lib/Controller/EntryController.php', 'annualOverview');
		$t->assertContains("'labelsAndTags'", $body, 'Annual overview accepts the labels and tags grouping');
		$rows = $t->methodBody('lib/Controller/EntryController.php', 'annualLabelAndTagRows');
		$settings = $t->methodBody('lib/Controller/EntryController.php', 'enabledAnnualLabels');
		$t->assertContains('getUserValue', $settings, 'Label visibility reads the user settings');
		$t->assertContains("!== 'yes'", $settings, 'Only enabled labels are returned');
		$source = $t->read('lib/Controller/EntryController.php');
		foreach (['enable_important_payments', 'enable_review_payments', 'enable_fixed_costs', 'enable_child_related', 'enable_subscriptions', 'enable_tax_relevant', 'enable_insurance'] as $setting) {
			$t->assertContains($setting, $source, 'Disabled labels must be omitted');
		}
		foreach (['cobudget_entry_hashtags', 'cobudget_hashtags', 'expandLabelsAndTags', 'SUM(e.amount_cents)', 'annualQualifyingCondition', 'enabledAnnualLabels'] as $needle) {
			$t->assertContains($needle, $rows, 'Label grouping must aggregate authorized payments and description tags');
		}
		$qualifier = $t->methodBody('lib/Controller/EntryController.php', 'annualQualifyingCondition');
		$t->assertContains('EXISTS', $qualifier, 'Description tags belong in the year total');
		$t->assertContains('cobudget_entry_hashtags', $qualifier, 'The year total looks up description tags on the payment');
		$t->assertContains('eh.workspace_id', $qualifier, 'Description tags stay in the payment workspace');
		$t->assertContains('PARAM_BOOL', $qualifier, 'Enabled label flags belong in the year total');
		$t->assertNotContains('SUM(', $qualifier, 'Several tags on one payment stay a single total');
		$t->assertNotContains('setMaxResults', $rows, 'Label totals must cover every qualifying payment');
		$t->assertSame(3, substr_count($rows, 'applyAnnualPaymentNarrowing('), 'Label rows, tag rows and the single-counted total use the same payment filter');
		$narrowing = $t->methodBody('lib/Controller/EntryController.php', 'applyAnnualPaymentNarrowing');
		$t->assertContains('EXISTS', $narrowing, 'A tag filter must not multiply summed payments');
		$t->assertContains('PARAM_INT_ARRAY', $narrowing, 'Several tags match when any selected tag is present');
		$t->assertContains('eh.workspace_id', $narrowing, 'Tag filters stay in the payment workspace');
		$t->assertContains('e.amount_cents', $narrowing, 'The amount filter uses the stored payment amount');
		$t->assertContains('PARAM_BOOL', $narrowing, 'Several labels match when any selected label is present');
		$t->assertNotContains('SUM(', $narrowing, 'Filtering payments must not change how totals are summed');
		$t->assertContains('annualNarrowingForGrouping(', $body, 'The grouped dimension is not filtered again');
		$t->assertNotContains('applyAnnualPaymentNarrowing(', $t->methodBody('lib/Controller/EntryController.php', 'annualOverviewYears'), 'Year choices stay independent of the payment filter');
		$t->assertContains('annualFilterChoices($workspaceId, $projectId, $start->getTimestamp(), $end)', $body, 'Filter options use the selected year, not the payments-until-today cutoff');
		$t->assertContains('annualHashtagChoices($workspaceId, $projectId, $start->getTimestamp(), $end)', $body, 'Tag options use the selected year');
		$choices = $t->methodBody('lib/Controller/EntryController.php', 'annualFilterChoices');
		$t->assertNotContains('applyAnnualPaymentNarrowing', $choices, 'Selecting a filter must not hide the other available values');
		$t->assertNotContains('$cutoff', $choices, 'A payment later in the selected year remains selectable');
		$t->assertContains('enabledAnnualLabels', $choices, 'Only enabled labels can be offered');
	},
	'Annual endpoint uses authorized personal or area scope without pagination' => function(TestRunner $t): void {
		$body = $t->methodBody('lib/Controller/EntryController.php', 'annualOverview');
		foreach (['authErrorResponse()', 'projectVisibleForCurrentUser($projectId)', 'normalizeEntryScope(\'shared\', $projectId)', 'workspaceIdForEntryScope($projectId)', 'buildVisibleEntriesQuery($workspaceId)', 'SUM(e.amount_cents)', 'calendarDate()'] as $guard) {
			$t->assertContains($guard, $body, 'Annual overview must preserve authorization and money/calendar rules');
		}
		$t->assertNotContains('setMaxResults', $body, 'Totals must cover all payments, not just one page');
	},
	'Monthly overview buckets each day and compares the previous day and the same day last year' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 15:30:00', $zone);
		$entry = static fn(string $date, int $amount): array => [
			'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(),
			'amount_cents' => $amount,
			'type' => 'expense',
			'group_id' => 1,
			'group_name' => 'Home',
		];
		$entries = [
			$entry('2026-02-28 12:00:00', 50),
			$entry('2026-03-18 10:00:00', 4000),
			$entry('2026-03-18 18:00:00', 6000),
			$entry('2026-03-19 09:00:00', 2500),
			$entry('2026-03-19 16:00:00', 9999),
			$entry('2026-03-20 09:00:00', 1111),
			$entry('2025-03-19 09:00:00', 2000),
			$entry('2025-03-19 18:00:00', 8000),
		];
		$report = $service->build($entries, 2026, $zone, 'categories', 3);
		$t->assertSame(31, count($report['totals']['expense']['months']), 'March keeps one slot per day');
		$t->assertSame(50, $service->build($entries, 2026, $zone, 'categories', 2)['totals']['expense']['months'][27], 'February keeps its own days');
		$t->assertSame(28, count($service->build($entries, 2026, $zone, 'categories', 2)['totals']['expense']['months']), '2026 is not a leap year');
		$t->assertSame(10000, $report['totals']['expense']['months'][17], 'March 18 sums every payment on that day');
		$t->assertSame(2500 + 9999, $report['totals']['expense']['months'][18], 'Stored later payments stay in the day until the query cutoff removes them');
		$t->assertSame(1111, $report['totals']['expense']['months'][19], 'A later day in the month stays visible');
		$t->assertSame(0, $report['totals']['expense']['months'][0], 'Empty days remain zero');
		$t->assertSame(4000 + 6000 + 2500 + 9999 + 1111, $report['totals']['expense']['total'], 'The month total ignores other months and years');
		$change = $service->compare($report, $entries, 2026, $now, 'categories', 'month', 3)['totals']['expense']['comparisons'];
		$t->assertSame(31, count($change), 'Each day can carry a comparison');
		$t->assertSame(50, $change[0]['previous'], 'The first day compares the last day of the previous month');
		$t->assertSame(0, $change[0]['current'], 'March 1 itself has no payment');
		$t->assertSame(10000, $change[17]['current'], 'A finished day uses the whole day');
		$t->assertSame(0, $change[17]['previous'], 'An empty previous day stays zero after history has begun');
		$t->assertSame(2500, $change[18]['current'], 'The current day stops at the present clock time');
		$t->assertSame(4000, $change[18]['previous'], 'The previous day uses that same clock time');
		$t->assertSame('2026-03-18', $change[18]['previousFrom'], 'The previous day is March 18');
		$t->assertSame('2026-03-18', $change[18]['previousTo'], 'A one-day reference is a single date');
		$t->assertSame(null, $change[19], 'A future day has no comparison');
		$yearChange = $service->compare($report, $entries, 2026, $now, 'categories', 'year', 3)['totals']['expense']['comparisons'];
		$t->assertSame(2500, $yearChange[18]['current'], 'The year comparison also stops at the present clock time');
		$t->assertSame(2000, $yearChange[18]['previous'], 'The same day last year stops at the same clock time');
		$t->assertSame('2025-03-19', $yearChange[18]['previousFrom'], 'The reference is the same calendar day last year');
		$t->assertSame(null, $yearChange[17], 'No payment on that day last year means no year comparison');
		$leapNow = new DateTimeImmutable('2024-02-29 12:00:00', $zone);
		$leapEntries = [$entry('2024-02-29 10:00:00', 70)];
		$leap = $service->build($leapEntries, 2024, $zone, 'categories', 2);
		$t->assertSame(29, count($leap['totals']['expense']['months']), 'A leap-day month keeps February 29');
		$t->assertSame(70, $leap['totals']['expense']['months'][28], 'February 29 keeps its payment');
		$t->assertSame(null, $service->compare($leap, $leapEntries, 2024, $leapNow, 'categories', 'year', 2)['totals']['expense']['comparisons'][28], 'February 29 has no same day in the previous year');
	},
	'Quarterly overview buckets four quarters and compares each quarter' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 15:30:00', $zone);
		$entry = static fn(string $date, int $amount): array => [
			'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(),
			'amount_cents' => $amount,
			'type' => 'expense',
			'group_id' => 1,
			'group_name' => 'Home',
		];
		$entries = [
			$entry('2026-02-28 12:00:00', 50),
			$entry('2026-03-18 10:00:00', 4000),
			$entry('2026-03-18 18:00:00', 6000),
			$entry('2026-03-19 09:00:00', 2500),
			$entry('2026-03-19 16:00:00', 9999),
			$entry('2026-03-20 09:00:00', 1111),
			$entry('2026-12-02 10:00:00', 80),
			$entry('2025-03-19 09:00:00', 2000),
			$entry('2025-03-19 18:00:00', 8000),
		];
		$report = $service->build($entries, 2026, $zone, 'categories', 0, true);
		$t->assertSame(4, count($report['totals']['expense']['months']), 'The quarterly overview keeps one slot per quarter');
		$t->assertSame(50 + 4000 + 6000 + 2500 + 9999 + 1111, $report['totals']['expense']['months'][0], 'The first quarter sums every stored payment');
		$t->assertSame(0, $report['totals']['expense']['months'][1], 'The second quarter stays empty');
		$t->assertSame(0, $report['totals']['expense']['months'][2], 'The third quarter stays empty');
		$t->assertSame(80, $report['totals']['expense']['months'][3], 'December stays in the fourth quarter');
		$t->assertSame(50 + 4000 + 6000 + 2500 + 9999 + 1111 + 80, $report['totals']['expense']['total'], 'The year total includes every quarter and ignores the previous year');
		$year = $service->build($entries, 2026, $zone, 'categories');
		$t->assertSame(12, count($year['totals']['expense']['months']), 'The year overview still has twelve months');
		$t->assertSame(80, $year['totals']['expense']['months'][11], 'December stays the last month of the year');
		$change = $service->compare($report, $entries, 2026, $now, 'categories', 'month', 0, true)['totals']['expense']['comparisons'];
		$elapsed = $now->getTimestamp() - (new DateTimeImmutable('2026-01-01 00:00:00', $zone))->getTimestamp();
		$previousQuarterEnd = (new DateTimeImmutable('2025-10-01 00:00:00', $zone))->setTimestamp((new DateTimeImmutable('2025-10-01 00:00:00', $zone))->getTimestamp() + $elapsed);
		$t->assertSame(4, count($change), 'Each quarter can carry a comparison');
		$t->assertSame(50 + 4000 + 6000 + 2500, $change[0]['current'], 'The current quarter stops at the present clock time');
		$t->assertSame(0, $change[0]['previous'], 'The previous quarter has no payment in this fixture');
		$t->assertSame('2025-10-01', $change[0]['previousFrom'], 'The previous quarter starts on 1 October');
		$t->assertSame($previousQuarterEnd->format('Y-m-d'), $change[0]['previousTo'], 'The previous quarter stops after the same elapsed time');
		$t->assertSame(null, $change[1], 'A future quarter has no comparison');
		$t->assertSame(null, $change[2], 'A future quarter has no comparison');
		$t->assertSame(null, $change[3], 'A future quarter has no comparison');
		$yearChange = $service->compare($report, $entries, 2026, $now, 'categories', 'year', 0, true)['totals']['expense']['comparisons'];
		$t->assertSame(50 + 4000 + 6000 + 2500, $yearChange[0]['current'], 'The year comparison also stops at the present time');
		$t->assertSame(2000, $yearChange[0]['previous'], 'The same quarter last year stops at the same clock time');
		$t->assertSame('2025-01-01', $yearChange[0]['previousFrom'], 'The reference is the first quarter of the previous year');
		$t->assertSame(null, $yearChange[1], 'Later quarters have no year comparison');
	},
	'Weekly overview buckets every calendar week and compares each week' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 15:30:00', $zone);
		$entry = static fn(string $date, int $amount): array => [
			'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(),
			'amount_cents' => $amount,
			'type' => 'expense',
			'group_id' => 1,
			'group_name' => 'Home',
		];
		$entries = [
			$entry('2025-12-29 12:00:00', 40),
			$entry('2026-03-10 12:00:00', 200),
			$entry('2026-03-19 09:00:00', 1000),
			$entry('2026-03-19 18:00:00', 5000),
			$entry('2026-03-23 09:00:00', 80),
			$entry('2025-03-20 09:00:00', 300),
			$entry('2025-03-20 18:00:00', 900),
			$entry('2026-12-28 12:00:00', 70),
			$entry('2027-01-04 12:00:00', 15),
		];
		$report = $service->build($entries, 2026, $zone, 'categories', 0, false, true);
		$months = $report['totals']['expense']['months'];
		$t->assertSame(53, count($months), '2026 keeps one slot per calendar week');
		$t->assertSame(40, $months[0], '29 December 2025 belongs to week 1 of 2026');
		$t->assertSame(200, $months[10], '10 March stays in the previous week');
		$t->assertSame(6000, $months[11], 'Week 12 keeps every stored payment on 19 March');
		$t->assertSame(80, $months[12], '23 March stays in the following week');
		$t->assertSame(70, $months[52], '28 December stays in week 53');
		$t->assertSame(40 + 200 + 6000 + 80 + 70, $report['totals']['expense']['total'], 'The week-year total ignores the neighboring week-years');
		$year = $service->build($entries, 2026, $zone, 'categories');
		$t->assertSame(12, count($year['totals']['expense']['months']), 'The year overview still has twelve months');
		$t->assertSame(200 + 1000 + 5000 + 80, $year['totals']['expense']['months'][2], 'March keeps its calendar-year payments');
		$t->assertSame(70, $year['totals']['expense']['months'][11], 'December stays the last month of the calendar year');
		$t->assertSame(200 + 1000 + 5000 + 80 + 70, $year['totals']['expense']['total'], 'The calendar year excludes 29 December 2025');
		$change = $service->compare($report, $entries, 2026, $now, 'categories', 'month', 0, false, true)['totals']['expense']['comparisons'];
		$t->assertSame(53, count($change), 'Each calendar week can carry a comparison');
		$t->assertSame(1000, $change[11]['current'], 'The current week stops at the present clock time');
		$t->assertSame(200, $change[11]['previous'], 'The previous week uses that same elapsed time');
		$t->assertSame('2026-03-09', $change[11]['previousFrom'], 'The previous week starts on Monday 9 March');
		$t->assertSame('2026-03-12', $change[11]['previousTo'], 'The previous week stops after the same elapsed time');
		$t->assertSame(null, $change[12], 'A future week has no comparison');
		$t->assertSame(null, $change[52], 'Week 53 is still in the future');
		$yearChange = $service->compare($report, $entries, 2026, $now, 'categories', 'year', 0, false, true)['totals']['expense']['comparisons'];
		$t->assertSame(1000, $yearChange[11]['current'], 'The year comparison also stops at the present time');
		$t->assertSame(300, $yearChange[11]['previous'], 'The same week last year stops at the same clock time');
		$t->assertSame('2025-03-17', $yearChange[11]['previousFrom'], 'The reference is calendar week 12 of 2025');
		$t->assertSame(null, $yearChange[52], 'Week 53 has no match in a 52-week year');
	},
	'Daily overview buckets every calendar day and compares each day' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone('Europe/Vienna');
		$now = new DateTimeImmutable('2026-03-19 15:30:00', $zone);
		$entry = static fn(string $date, int $amount): array => [
			'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(),
			'amount_cents' => $amount,
			'type' => 'expense',
			'group_id' => 1,
			'group_name' => 'Home',
		];
		$entries = [
			$entry('2026-01-01 10:00:00', 40),
			$entry('2026-03-18 12:00:00', 200),
			$entry('2026-03-18 18:00:00', 900),
			$entry('2026-03-19 09:00:00', 1000),
			$entry('2026-03-19 18:00:00', 5000),
			$entry('2026-03-20 09:00:00', 80),
			$entry('2025-03-19 09:00:00', 300),
			$entry('2025-03-19 18:00:00', 700),
			$entry('2025-12-29 12:00:00', 15),
			$entry('2026-12-31 12:00:00', 70),
			$entry('2027-01-01 12:00:00', 11),
		];
		$report = $service->build($entries, 2026, $zone, 'categories', 0, false, false, true);
		$months = $report['totals']['expense']['months'];
		$t->assertSame(365, count($months), '2026 keeps one slot per calendar day');
		$t->assertSame(365, $service->calendarDayCount(2026, $zone), '2026 has 365 calendar days');
		$t->assertSame(40, $months[0], '1 January is the first day');
		$t->assertSame(1100, $months[76], '18 March sums every payment on that day');
		$t->assertSame(6000, $months[77], '19 March keeps every stored payment');
		$t->assertSame(80, $months[78], '20 March stays on the next day');
		$t->assertSame(70, $months[364], '31 December is the last day');
		$t->assertSame(40 + 1100 + 6000 + 80 + 70, $report['totals']['expense']['total'], 'The daily total matches the calendar year');
		$year = $service->build($entries, 2026, $zone, 'categories');
		$t->assertSame(12, count($year['totals']['expense']['months']), 'The year overview still has twelve months');
		$t->assertSame($report['totals']['expense']['total'], $year['totals']['expense']['total'], 'Gesamt matches the calendar-year total');
		$change = $service->compare($report, $entries, 2026, $now, 'categories', 'month', 0, false, false, true)['totals']['expense']['comparisons'];
		$t->assertSame(365, count($change), 'Each calendar day can carry a comparison');
		$t->assertSame(1000, $change[77]['current'], 'The current day stops at the present clock time');
		$t->assertSame(200, $change[77]['previous'], 'The previous day uses that same clock time');
		$t->assertSame('2026-03-18', $change[77]['previousFrom'], 'The previous day is 18 March');
		$t->assertSame('2026-03-18', $change[77]['previousTo'], 'A one-day reference is a single date');
		$t->assertSame(null, $change[78], 'A future day has no comparison');
		$yearChange = $service->compare($report, $entries, 2026, $now, 'categories', 'year', 0, false, false, true)['totals']['expense']['comparisons'];
		$t->assertSame(1000, $yearChange[77]['current'], 'The year comparison also stops at the present clock time');
		$t->assertSame(300, $yearChange[77]['previous'], 'The same day last year stops at the same clock time');
		$t->assertSame('2025-03-19', $yearChange[77]['previousFrom'], 'The reference is 19 March 2025');
		$leapNow = new DateTimeImmutable('2024-03-01 12:00:00', $zone);
		$leapEntries = [
			$entry('2024-01-02 10:00:00', 10),
			$entry('2024-02-29 10:00:00', 70),
		];
		$leap = $service->build($leapEntries, 2024, $zone, 'categories', 0, false, false, true);
		$t->assertSame(366, count($leap['totals']['expense']['months']), '2024 keeps the leap day');
		$t->assertSame(366, $service->calendarDayCount(2024, $zone), '2024 has 366 calendar days');
		$t->assertSame(70, $leap['totals']['expense']['months'][59], '29 February is the 60th day');
		$leapChange = $service->compare($leap, $leapEntries, 2024, $leapNow, 'categories', 'month', 0, false, false, true)['totals']['expense']['comparisons'];
		$t->assertSame(70, $leapChange[59]['current'], '29 February keeps its payment');
		$t->assertSame('2024-02-28', $leapChange[59]['previousFrom'], 'The previous day is 28 February');
		$t->assertSame(null, $service->compare($leap, $leapEntries, 2024, $leapNow, 'categories', 'year', 0, false, false, true)['totals']['expense']['comparisons'][59], '29 February has no same day in 2023');
	},
	'Recurring dates follow the job clock and land in the future month' => function(TestRunner $t): void {
		$service = new AnnualOverviewService();
		$zone = new DateTimeZone(date_default_timezone_get());
		$at = static fn(string $date): int => (new DateTimeImmutable($date, $zone))->getTimestamp();
		$day = static fn(int $timestamp): string => (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('Y-m-d');
		$after = $at('2026-10-21 15:30:00');
		$until = $at('2026-12-31 23:59:59');
		$dates = $service->recurrenceDates('month', 1, $at('2026-11-01 09:00:00'), null, $after, $until);
		$t->assertSame(['2026-11-01', '2026-12-01'], array_map($day, $dates), 'October shows the November and December occurrences');
		$t->assertSame(['2026-11-01'], array_map($day, $service->recurrenceDates('month', 1, $at('2026-11-01 09:00:00'), $at('2026-11-15 23:59:59'), $after, $until)), 'A series end keeps November and stops before December');
		$t->assertSame([], $service->recurrenceDates('month', 1, $at('2026-11-01 09:00:00'), $at('2026-10-31 23:59:59'), $after, $until), 'A finished series adds no occurrence');
		$t->assertSame(['2026-11-01', '2026-12-01'], array_map($day, $service->recurrenceDates('month', 1, $at('2026-09-01 09:00:00'), null, $after, $until)), 'An overdue next date still continues into the open period');
		$t->assertSame([], $service->recurrenceDates('month', 1, $at('2026-11-01 09:00:00'), null, $until, $after), 'A closed period adds nothing');
		$t->assertSame(['2026-11-01', '2026-11-15', '2026-11-29'], array_map($day, $service->recurrenceDates('week', 2, $at('2026-11-01 09:00:00'), null, $after, $at('2026-11-30 23:59:59'))), 'A two-week series keeps its interval');
		$t->assertSame('2026-03-03', $day($service->recurrenceDates('month', 1, $at('2026-01-31 09:00:00'), null, $at('2026-01-31 08:00:00'), $at('2026-03-31 23:59:59'))[1]), 'Month steps use the same overflow as the recurring job');
		$overview = new DateTimeZone('Europe/Vienna');
		$stored = $at('2026-10-01 09:00:00');
		$planned = $dates[0];
		$rows = [
			['date' => $at('2026-09-01 09:00:00'), 'amount_cents' => 2500, 'type' => 'expense', 'group_id' => 4, 'group_name' => 'Rent', 'payment_count' => 1],
			['date' => $stored, 'amount_cents' => 2500, 'type' => 'expense', 'group_id' => 4, 'group_name' => 'Rent', 'payment_count' => 1],
			['date' => $planned, 'amount_cents' => 2500, 'type' => 'expense', 'group_id' => 4, 'group_name' => 'Rent', 'payment_count' => 1],
		];
		$report = $service->build($rows, 2026, $overview, 'categories');
		$t->assertSame(2500, $report['totals']['expense']['months'][9], 'October keeps the saved payment');
		$t->assertSame(2500, $report['totals']['expense']['months'][10], 'November shows the planned occurrence');
		$t->assertSame(1, $report['groups']['expense'][0]['counts'][10], 'The planned occurrence counts as one payment');
		$now = new DateTimeImmutable('2026-10-21 15:30:00', $overview);
		$change = $service->compare($report, $rows, 2026, $now, 'categories', 'month')['totals']['expense']['comparisons'];
		$t->assertSame(null, $change[10], 'A future month still has no comparison');
		$t->assertSame(2500, $change[9]['current'], 'The current month comparison stops at the present time');
	},
];

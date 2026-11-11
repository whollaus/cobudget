<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\AnnualOverviewService;
use OCA\CoBudget\Service\ReportBreakdownService;
use OCA\CoBudget\Service\ReportForecastService;
use OCA\CoBudget\Service\ReportComparisonService;

require_once dirname(__DIR__, 2) . '/lib/Service/AnnualOverviewService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportBreakdownService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportForecastService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportComparisonService.php';
require_once dirname(__DIR__, 2) . '/lib/Service/ReportIncomePatternService.php';

return [
 'Calendar reports exclude intervening months and years in all breakdowns and composition totals' => function(TestRunner $t): void {
  $service = new AnnualOverviewService(); $zone = new DateTimeZone('Europe/Vienna');
  $row = static fn(int $id, string $date, int $cents): array => ['id' => $id, 'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'amount_cents' => $cents, 'type' => 'expense', 'category_id' => 1, 'category_name' => 'Home', 'group_id' => 1, 'group_name' => 'Home'];
  $rows = [$row(1, '2024-01-01', 101), $row(2, '2024-02-01', 999999), $row(3, '2024-03-31 23:59:59', 202), $row(4, '2025-01-01', 999999), $row(5, '2026-01-01', 303), $row(6, '2026-03-29 03:00', 404), $row(7, '2026-04-01', 999999)];
  foreach (['day' => 124, 'week' => 22, 'year' => 4, 'quarter' => 4] as $unit => $count) {
   $periods = $service->periods('2024-01-01', '2026-12-31', $zone, $unit, '2026,2024,2024', '3,1');
   if ($unit !== 'week') $t->assertSame($count, count($periods), 'Only selected columns are built');
   foreach ($periods as $index => $period) if ($index) $t->assertTrue($period['dateFrom'] > $periods[$index - 1]['dateTo'], 'Buckets never overlap');
   $report = $service->build($rows, 2026, $zone, 'categories', periods: $periods);
   $t->assertSame(1010, $report['totals']['expense']['total'], 'Skipped months and years cannot leak into totals');
   $t->assertSame(4, $report['totals']['expense']['totalCount'], 'Selected payments count once');
   $composition = (new ReportBreakdownService())->build($rows, $periods[0]['dateFrom'], $periods[count($periods) - 1]['dateTo'], 'categories', 'expense', 'total', false, [], $periods);
   $t->assertSame(1010, $composition['totals']['expense']['amountCents'], 'Composition agrees with overview');
  }
  $t->assertSame(29, count($service->periods('', '', $zone, 'day', '2024', '2')), 'Leap February is complete');
  $t->assertSame(1, count($service->periods('', '', $zone, 'quarter', '2024', '1,2,3')), 'Adjacent months merge into one quarter');
  $clipped = $service->periods('2024-03-29', '2024-03-29', $zone, 'day', '2024,2026', '1,3');
  $t->assertSame(1, count($clipped), 'Detail dates intersect the selected calendar');
 },
 'Calendar report validation rejects invalid selections without widening data' => function(TestRunner $t): void {
  $service = new AnnualOverviewService(); $zone = new DateTimeZone('UTC');
  foreach ([['', '2'], ['invalid', '1'], ['1969', '1'], ['2024', '13'], ['2024', '0'], ['2024', 'x'], [implode(',', range(1970, 2020)), '1']] as [$years, $months]) {
   $rejected = false;
   try { $service->periods('', '', $zone, 'year', $years, $months); } catch (InvalidArgumentException) { $rejected = true; }
   $t->assertTrue($rejected, 'Invalid choices must not fall back to the entire interval');
  }
  $t->assertSame(62, count($service->periods('', '', $zone, 'day', '1970,9999', '1')), 'Column limits count selected days rather than gaps');
  foreach ([['2024-13-01', '2024-12-31'], ['2024-03-02', '2024-03-01'], ['2024-02-01', '2024-02-29']] as [$from, $to]) {
   $rejected = false;
   try { $service->periods($from, $to, $zone, 'year', '2024', '1,3'); } catch (InvalidArgumentException) { $rejected = true; }
   $t->assertTrue($rejected, 'Invalid or empty clipping cannot expand selection');
  }
 },
 'Calendar forecasts use selected elapsed and remaining days and reconcile exact cents' => function(TestRunner $t): void {
  $zone = new DateTimeZone('Europe/Vienna'); $service = new AnnualOverviewService();
  $periods = $service->periods('', '', $zone, 'year', '2026', '1,3');
  $row = static fn(int $id, string $date, int $cents, int $category = 1): array => ['id' => $id, 'date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'amount_cents' => $cents, 'type' => 'expense', 'category_id' => $category];
  $rows = [$row(1, '2026-01-01', 3100), $row(2, '2026-02-10', 999999), $row(3, '2026-02-20', 999999), $row(4, '2026-03-10', 100, 2)];
  $forecast = (new ReportForecastService())->build($rows, $periods[0]['dateFrom'], $periods[1]['dateTo'], new DateTimeImmutable('2026-02-15 12:00', $zone), 'categories', 'expense', 'total', false, [], $periods);
  $t->assertSame(31, $forecast['elapsedDays'], 'Gap days do not dilute the expense pace');
  $t->assertSame(31, $forecast['remainingDays'], 'Only selected future days receive estimates');
  $t->assertSame(3100, $forecast['actual']['expenseCents'], 'Past payments in the gap are excluded');
  $t->assertSame(100, $forecast['planned']['expenseCents'], 'Future payments in the gap are excluded');
  $t->assertSame(3100, $forecast['estimated']['expenseCents'], 'Only selected days contribute to the estimate');
  foreach (['actual', 'planned', 'estimated', 'forecast'] as $key) {
   $sum = $key === 'forecast' ? array_sum($forecast['chart']['actual']['expense']) + array_sum($forecast['chart']['planned']['expense']) + array_sum($forecast['chart']['estimated']['expense']) : array_sum($forecast['chart'][$key]['expense']);
   $t->assertSame($forecast[$key]['expenseCents'], $sum, 'Chart and summary reconcile');
  }
 },
 'Calendar comparison totals omit gaps in current and prior-year periods' => function(TestRunner $t): void {
  $zone = new DateTimeZone('Europe/Vienna'); $service = new AnnualOverviewService();
  $periods = $service->periods('', '', $zone, 'year', '2026', '1,3');
  $row = static fn(string $date, int $cents): array => ['date' => (new DateTimeImmutable($date, $zone))->getTimestamp(), 'amount_cents' => $cents, 'type' => 'expense', 'group_id' => 1, 'group_name' => 'Home'];
  $rows = [$row('2026-01-10', 100), $row('2026-02-10', 999999), $row('2026-03-10', 200), $row('2025-01-10', 50), $row('2025-02-10', 999999), $row('2025-03-10', 100)];
  $now = new DateTimeImmutable('2026-04-01', $zone);
  $result = (new ReportComparisonService())->build($rows, $periods, $now, $now->getTimestamp(), 'categories', 'expense', 'total', false, (new DateTimeImmutable('2024-01-01', $zone))->getTimestamp(), 'year');
  $year = array_values(array_filter($result, static fn(array $item): bool => $item['id'] === 'year'))[0];
  $t->assertSame(300, $year['current'], 'Current comparison excludes February');
  $t->assertSame(150, $year['previous'], 'Prior-year comparison excludes February');
  $t->assertSame(2, count($year['periods']), 'Reference chart preserves selected segments');
  $t->assertSame($periods, $year['currentPeriods'], 'Comparison captions retain the selected current intervals');
  $periods = $service->periods('', '', $zone, 'year', '2024', '1,3,12');
  $now = new DateTimeImmutable('2024-03-15 12:00', $zone);
  $result = (new ReportComparisonService())->build([], $periods, $now, $now->getTimestamp(), 'categories', 'expense', 'total', false, (new DateTimeImmutable('2022-01-01', $zone))->getTimestamp(), 'year');
  $year = array_values(array_filter($result, static fn(array $item): bool => $item['id'] === 'year'))[0];
  $t->assertSame('range', $year['unit'], 'A January-to-December selection with gaps is not a complete year');
  $t->assertSame('2023-03-15', $year['previousTo'], 'Prior-year cutoff keeps the calendar date across leap years');
 },
];

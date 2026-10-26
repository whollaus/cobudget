<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Area rows share the report's clipped buckets and exact-cent arithmetic. */
class AreaOverviewService {
	public function build(iterable $entries, array $periods, \DateTimeZone $timezone, array $areas, string $amountView): array {
		$year = (int)(new \DateTimeImmutable('@' . $periods[0]['dateFrom']))->setTimezone($timezone)->format('Y');
		$report = (new AnnualOverviewService())->build($entries, $year, $timezone, 'projects', periods: $periods);
		$empty = ['months' => array_fill(0, count($periods), 0), 'counts' => array_fill(0, count($periods), 0), 'total' => 0, 'totalCount' => 0];
		$rows = [];
		foreach (['income', 'expense'] as $type) {
			foreach ($report['groups'][$type] as $row) {
				$id = (int)$row['id'];
				if (!isset($rows[$id])) $rows[$id] = [
					'id' => $id, 'name' => (string)($areas[$id]['name'] ?? ''),
					'archived' => in_array($areas[$id]['is_archived'] ?? false, [true, 1, '1', 't', 'true'], true),
					'entryScope' => $amountView === 'area' && (int)($areas[$id]['member_count'] ?? 0) > 1 ? 'shared' : 'personal',
					'income' => $empty, 'expense' => $empty,
					'filterHashtags' => $areas[$id]['filterHashtags'] ?? null,
				];
				$rows[$id][$type] = $row;
			}
		}
		foreach ($rows as &$row) {
			$row['balance'] = $empty;
			foreach ($periods as $index => $_period) {
				$row['balance']['months'][$index] = $row['income']['months'][$index] - $row['expense']['months'][$index];
				$row['balance']['counts'][$index] = $row['income']['counts'][$index] + $row['expense']['counts'][$index];
			}
			$row['balance']['total'] = $row['income']['total'] - $row['expense']['total'];
			$row['balance']['totalCount'] = $row['income']['totalCount'] + $row['expense']['totalCount'];
		}
		unset($row);
		return ['rows' => array_values($rows), 'totals' => $report['totals']];
	}
}

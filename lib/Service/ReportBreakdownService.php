<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Composition of an already authorized report subject. Money stays in integer cents. */
class ReportBreakdownService {
	public function build(iterable $rows, int $from, int $to, string $grouping, string $type, string $id, bool $direct, array $labels, array $periods = []): array {
		$calendar = new AnnualOverviewService();
		$groups = [];
		foreach (['categories', 'paymentPartners', 'labels', 'hashtags', 'projects'] as $dimension) {
			$groups[$dimension] = ['income' => [], 'expense' => []];
		}
		$totals = ['income' => ['amountCents' => 0, 'count' => 0], 'expense' => ['amountCents' => 0, 'count' => 0]];
		$overlaps = ['labels' => ['income' => false, 'expense' => false], 'hashtags' => ['income' => false, 'expense' => false]];
		foreach ($this->payments($rows, $from, $to, $grouping, $type, $id, $direct, $labels) as $payment) {
			if ($periods && $calendar->periodIndex((int)$payment['date'], $periods) < 0) continue;
			$paymentLabels = [];
			foreach ($labels as $key => $label) {
				if ($this->flag($payment[$label['column']] ?? false)) $paymentLabels[$key] = ['id' => $key, 'name' => $label['name']];
			}
			$kind = $payment['type'];
			$cents = (int)$payment['amount_cents'];
			$totals[$kind]['amountCents'] += $cents;
			$totals[$kind]['count']++;
			$choices = [
				'categories' => [['icon' => (string)($payment['category_icon'] ?? 'Shape'), 'id' => (int)($payment['category_id'] ?? 0), 'name' => (string)($payment['category_name'] ?? ''), 'parentName' => (string)($payment['parent_name'] ?? '')]],
				'paymentPartners' => [['id' => (int)($payment['partner_id'] ?? 0), 'name' => (string)($payment['partner_name'] ?? '')]],
				'projects' => [['id' => (int)($payment['project_id'] ?? 0), 'name' => (string)($payment['project_name'] ?? '')]],
				'labels' => $paymentLabels ?: [['id' => '', 'name' => '']],
				'hashtags' => $payment['hashtags'] ?: [['id' => 0, 'name' => '']],
			];
			foreach ($choices as $dimension => $items) {
				if (isset($overlaps[$dimension]) && count($items) > 1) $overlaps[$dimension][$kind] = true;
				foreach ($items as $item) {
					$key = in_array($dimension, ['categories', 'paymentPartners'], true)
						? ReportLookupService::key($dimension, $item['id'], $item['name'], $item['parentName'] ?? '') : (string)$item['id'];
					$item['reportKey'] = $key;
					if (!isset($groups[$dimension][$kind][$key])) $groups[$dimension][$kind][$key] = $item + ['amountCents' => 0, 'count' => 0, 'areas' => []];
					$groups[$dimension][$kind][$key]['amountCents'] += $cents;
					$groups[$dimension][$kind][$key]['count']++;
					if (in_array($dimension, ['categories', 'paymentPartners'], true)) {
						$areaId = (int)($payment['project_id'] ?? 0);
						$areas = &$groups[$dimension][$kind][$key]['areas'];
						if (!isset($areas[$areaId])) $areas[$areaId] = ['id' => $item['id'], 'reportKey' => $key . ':area:' . $areaId, 'areaId' => $areaId, 'lookupName' => implode(' · ', array_filter([$item['parentName'] ?? '', $item['name']])), 'name' => (string)($payment['project_name'] ?? ''), 'amountCents' => 0, 'count' => 0];
						$areas[$areaId]['amountCents'] += $cents;
						$areas[$areaId]['count']++;
						unset($areas);
					}
				}
			}
		}
		foreach ($groups as &$dimension) {
			foreach ($dimension as &$items) {
				foreach ($items as &$item) $item['areas'] = array_values($item['areas']);
				unset($item);
				$items = array_values($items);
			}
			unset($items);
		}
		unset($dimension);
		return ['groups' => $groups, 'totals' => $totals, 'overlaps' => $overlaps];
	}

	/** Unique, matching payments shared by composition and the period forecast. */
	public function payments(iterable $rows, int $from, int $to, string $grouping, string $type, string $id, bool $direct, array $labels): array {
		// Reassemble tag joins before selecting the subject. Several matching
		// labels or tags must never multiply either actual or planned amounts.
		$payments = [];
		foreach ($rows as $row) {
			if ((int)$row['date'] < $from || (int)$row['date'] > $to || !in_array($row['type'], ['income', 'expense'], true) || ($type !== 'all' && $row['type'] !== $type)) continue;
			$key = (string)$row['id'];
			if (!isset($payments[$key])) $payments[$key] = array_replace($row, ['hashtags' => []]);
			$tags = $row['hashtags'] ?? [];
			if ((int)($row['hashtag_id'] ?? 0) > 0) $tags[] = ['id' => $row['hashtag_id'], 'name' => $row['hashtag_name']];
			foreach ($tags as $tag) {
				if ((int)$tag['id'] > 0) $payments[$key]['hashtags'][(int)$tag['id']] = ['id' => (int)$tag['id'], 'name' => (string)$tag['name']];
			}
		}
		return array_values(array_filter($payments, function(array $payment) use ($grouping, $id, $direct, $labels): bool {
			$paymentLabels = [];
			foreach ($labels as $key => $label) {
				if ($this->flag($payment[$label['column']] ?? false)) $paymentLabels[$key] = true;
			}
			return $this->matches($payment, $paymentLabels, $grouping, $id, $direct);
		}));
	}

	private function matches(array $payment, array $labels, string $grouping, string $id, bool $direct): bool {
		if ($id === 'total') return $grouping !== 'labelsAndTags' || $labels !== [] || $payment['hashtags'] !== [];
		if (in_array($grouping, ['categories', 'paymentPartners'], true)) return ReportLookupService::matches($payment, $grouping, $id, $direct);
		if (str_starts_with($id, 'label:')) return isset($labels[substr($id, 6)]);
		if (str_starts_with($id, 'tag:')) return isset($payment['hashtags'][(int)substr($id, 4)]);
		return false;
	}

	private function flag(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
	}
}

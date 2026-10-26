<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Additional AND predicates for a report's drill-down path, never a data-access scope. */
class ReportScopeService {
	private const LABELS = ['important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance'];

	public function parse(string $value): array {
		if ($value === '') return [];
		if (strlen($value) > 4096 || !str_starts_with(ltrim($value), '[')) throw new \InvalidArgumentException('Invalid report detail filters');
		try { $filters = json_decode($value, true, 16, JSON_THROW_ON_ERROR); }
		catch (\JsonException $error) { throw new \InvalidArgumentException('Invalid report detail filters', 0, $error); }
		if (!is_array($filters) || !array_is_list($filters) || count($filters) > 8) throw new \InvalidArgumentException('Invalid report detail filters');
		$normalized = [];
		foreach ($filters as $filter) {
			if (!is_array($filter) || !isset($filter['dimension']) || !array_key_exists('id', $filter) || (!is_string($filter['id']) && !is_int($filter['id']))) throw new \InvalidArgumentException('Invalid report detail filter');
			$dimension = $filter['dimension'];
			$id = (string)$filter['id'];
			if ($dimension === 'type') $valid = in_array($id, ['income', 'expense'], true);
			elseif ($dimension === 'labels') $valid = $id === '' || in_array($id, self::LABELS, true);
			else $valid = in_array($dimension, ['categories', 'paymentPartners', 'hashtags', 'projects'], true)
				&& preg_match('/^(0|[1-9][0-9]*)$/D', $id) === 1 && strlen($id) <= 16 && (int)$id <= 9007199254740991;
			if (!$valid || (isset($filter['direct']) && !is_bool($filter['direct']))) throw new \InvalidArgumentException('Invalid report detail filter');
			$normalized[] = ['dimension' => $dimension, 'id' => $id, 'direct' => $dimension !== 'categories' || ($filter['direct'] ?? true)];
		}
		return $normalized;
	}

	/** Match projected occurrences after resolving their personal amounts and workspace tag IDs. */
	public function matches(array $payment, array $filters, array $labels): bool {
		foreach ($filters as $filter) {
			$id = $filter['id'];
			$matches = match ($filter['dimension']) {
				'type' => $payment['type'] === $id,
				'categories' => (int)($payment['category_id'] ?? 0) === (int)$id
					|| (!$filter['direct'] && (int)$id > 0 && (int)($payment['parent_id'] ?? 0) === (int)$id),
				'paymentPartners' => (int)($payment['partner_id'] ?? $payment['payment_partner_id'] ?? 0) === (int)$id,
				'projects' => (int)($payment['project_id'] ?? 0) === (int)$id,
				'labels' => $this->matchesLabel($payment, $id, $labels),
				'hashtags' => $id === '0' ? empty($payment['hashtags']) : in_array((int)$id, array_map('intval', array_column($payment['hashtags'] ?? [], 'id')), true),
				default => false,
			};
			if (!$matches) return false;
		}
		return true;
	}

	private function matchesLabel(array $payment, string $id, array $labels): bool {
		foreach ($labels as $key => $label) {
			$set = in_array($payment[$label['column']] ?? false, [true, 1, '1', 't', 'true'], true);
			if ($id !== '' && $key === $id) return $set;
			if ($id === '' && $set) return false;
		}
		return $id === '';
	}
}

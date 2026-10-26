<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/** Recognize recent, regular income without creating payment automation. */
class ReportIncomePatternService {
	public static function recurring(array $payment): bool {
		foreach (['recurrence_interval', 'recurrence_series_id', 'recurrence_parent_id', 'source_recurrence_interval', 'source_recurrence_series_id', 'source_recurrence_parent_id'] as $field) {
			if (!empty($payment[$field])) return true;
		}
		return false;
	}

	public function project(array $payments, \DateTimeImmutable $now, int $until): array {
		$groups = [];
		$known = [];
		$latest = [];
		foreach ($payments as $payment) {
			if ($payment['type'] !== 'income') continue;
			$identity = $this->identity($payment);
			if ($identity === null) continue;
			if ((int)$payment['date'] > $now->getTimestamp()) $known[$identity][] = $payment;
			else {
				$groups[$identity . ':' . (int)$payment['amount_cents']][] = $payment;
				$latest[$identity] = max($latest[$identity] ?? 0, (int)$payment['date']);
			}
		}
		$projected = [];
		$count = 0;
		foreach ($groups as $group) {
			usort($group, static fn(array $a, array $b): int => (int)$a['date'] <=> (int)$b['date']);
			if (count($group) < 4) continue;
			$sample = array_slice($group, -4);
			if (array_filter($sample, [self::class, 'recurring'])) continue;
			$dates = array_map(static fn(array $payment): \DateTimeImmutable => $now->setTimestamp((int)$payment['date'])->setTime(0, 0), $sample);
			$last = $sample[3];
			$identity = $this->identity($last);
			// A newer amount for the same position replaces the old pattern.
			if (($latest[$identity] ?? 0) > (int)$last['date']) continue;
			$cadence = $this->cadence($dates);
			if ($cadence === null) continue;
			[$months, $days, $tolerance] = $cadence;
			$anchorDay = max(array_map(static fn(\DateTimeImmutable $date): int => (int)$date->format('j'), $dates));
			$next = $this->step($dates[0], $months * 4, $days * 4, $anchorDay);
			if ($next < $now->setTime(0, 0)->modify('-' . $tolerance . ' days')) continue;
			$count++;
			for ($index = 4; $index < 4000; $index++) {
				$due = $this->step($dates[0], $months * $index, $days * $index, $anchorDay)->setTime(23, 59, 59);
				if ($due->getTimestamp() > $until) break;
				if ($due->getTimestamp() <= $now->getTimestamp()) continue;
				$covered = false;
				foreach ($known[$identity] ?? [] as $plan) {
					$plannedDate = $now->setTimestamp((int)$plan['date'])->setTime(0, 0);
					if ((int)$plannedDate->diff($due->setTime(0, 0))->days <= $tolerance) { $covered = true; break; }
				}
				if (!$covered) $projected[] = array_replace($last, ['id' => 'estimated-income:' . $last['id'] . ':' . $due->format('Y-m-d'), 'date' => $due->getTimestamp()]);
			}
		}
		return ['patterns' => $count, 'payments' => $projected];
	}

	private function identity(array $payment): ?string {
		$category = (int)($payment['category_id'] ?? 0);
		$partner = (int)($payment['partner_id'] ?? 0);
		// With neither category nor partner, require an identical nonempty description.
		$description = $category === 0 && $partner === 0 ? trim((string)($payment['description'] ?? '')) : '';
		if ($category === 0 && $partner === 0 && $description === '') return null;
		return json_encode([(int)($payment['project_id'] ?? 0), $category, $partner, $description]);
	}

	private function cadence(array $dates): ?array {
		$anchor = max(array_map(static fn(\DateTimeImmutable $date): int => (int)$date->format('j'), $dates));
		foreach ([[1, 0, 4], [2, 0, 4], [3, 0, 4], [6, 0, 4], [12, 0, 4], [0, 7, 1], [0, 14, 1], [0, 28, 1]] as [$months, $days, $tolerance]) {
			$matches = true;
			for ($index = 1; $index < 4; $index++) {
				if ((int)$dates[$index]->diff($this->step($dates[0], $months * $index, $days * $index, $anchor))->days > $tolerance) { $matches = false; break; }
			}
			if ($matches) return [$months, $days, $tolerance];
		}
		return null;
	}

	private function step(\DateTimeImmutable $start, int $months, int $days, int $anchor): \DateTimeImmutable {
		if ($days > 0) return $start->modify('+' . $days . ' days');
		$month = $start->modify('first day of this month')->modify('+' . $months . ' months');
		return $month->setDate((int)$month->format('Y'), (int)$month->format('n'), min($anchor, (int)$month->format('t')));
	}
}

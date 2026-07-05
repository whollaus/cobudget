<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\IDBConnection;

class DataIntegrityService {
	private const REFERENCE_CHECKS = [
		[
			'sourceTable' => 'cobudget_entries',
			'sourceLabel' => 'Eintraege',
			'column' => 'category_id',
			'targetTable' => 'cobudget_categories',
			'targetLabel' => 'Kategorie',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_entries',
			'sourceLabel' => 'Eintraege',
			'column' => 'payment_partner_id',
			'targetTable' => 'cobudget_payment_partners',
			'targetLabel' => 'Zahlungspartner',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_entries',
			'sourceLabel' => 'Eintraege',
			'column' => 'project_id',
			'targetTable' => 'cobudget_projects',
			'targetLabel' => 'Bereich',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_entries',
			'sourceLabel' => 'Eintraege',
			'column' => 'settlement_id',
			'targetTable' => 'cobudget_settlements',
			'targetLabel' => 'Abrechnung',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_templates',
			'sourceLabel' => 'Vorlagen',
			'column' => 'category_id',
			'targetTable' => 'cobudget_categories',
			'targetLabel' => 'Kategorie',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_templates',
			'sourceLabel' => 'Vorlagen',
			'column' => 'payment_partner_id',
			'targetTable' => 'cobudget_payment_partners',
			'targetLabel' => 'Zahlungspartner',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_templates',
			'sourceLabel' => 'Vorlagen',
			'column' => 'project_id',
			'targetTable' => 'cobudget_projects',
			'targetLabel' => 'Bereich',
			'repairAction' => 'clear',
		],
		[
			'sourceTable' => 'cobudget_entry_attachments',
			'sourceLabel' => 'Beleg-Pfade',
			'column' => 'entry_id',
			'targetTable' => 'cobudget_entries',
			'targetLabel' => 'Zahlung',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_entry_attachments',
			'sourceLabel' => 'Beleg-Pfade',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_hashtags',
			'sourceLabel' => 'Hashtags',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'deleteHashtag',
		],
		[
			'sourceTable' => 'cobudget_entry_hashtags',
			'sourceLabel' => 'Hashtag-Zuordnungen',
			'column' => 'entry_id',
			'targetTable' => 'cobudget_entries',
			'targetLabel' => 'Zahlung',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_entry_hashtags',
			'sourceLabel' => 'Hashtag-Zuordnungen',
			'column' => 'hashtag_id',
			'targetTable' => 'cobudget_hashtags',
			'targetLabel' => 'Hashtag',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_entry_hashtags',
			'sourceLabel' => 'Hashtag-Zuordnungen',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_members',
			'sourceLabel' => 'Bereichsmitglieder',
			'column' => 'project_id',
			'targetTable' => 'cobudget_projects',
			'targetLabel' => 'Bereich',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_settlements',
			'sourceLabel' => 'Abrechnungen',
			'column' => 'project_id',
			'targetTable' => 'cobudget_projects',
			'targetLabel' => 'Bereich',
			'repairAction' => 'deleteSettlement',
		],
		[
			'sourceTable' => 'cobudget_settlements',
			'sourceLabel' => 'Abrechnungen',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'deleteSettlement',
		],
		[
			'sourceTable' => 'cobudget_settlement_balances',
			'sourceLabel' => 'Abrechnungssalden',
			'column' => 'settlement_id',
			'targetTable' => 'cobudget_settlements',
			'targetLabel' => 'Abrechnung',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_settlement_transfers',
			'sourceLabel' => 'Rueckzahlungen',
			'column' => 'settlement_id',
			'targetTable' => 'cobudget_settlements',
			'targetLabel' => 'Abrechnung',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_budget_goals',
			'sourceLabel' => 'Budgetziele',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'deleteBudgetGoal',
		],
		[
			'sourceTable' => 'cobudget_budget_snapshots',
			'sourceLabel' => 'Budget-Historie',
			'column' => 'budget_goal_id',
			'targetTable' => 'cobudget_budget_goals',
			'targetLabel' => 'Budgetziel',
			'repairAction' => 'delete',
		],
		[
			'sourceTable' => 'cobudget_budget_snapshots',
			'sourceLabel' => 'Budget-Historie',
			'column' => 'workspace_id',
			'targetTable' => 'cobudget_workspaces',
			'targetLabel' => 'Workspace',
			'repairAction' => 'delete',
		],
	];

	private const DUPLICATE_NAME_CHECKS = [
		[
			'table' => 'cobudget_categories',
			'label' => 'Kategorie',
		],
		[
			'table' => 'cobudget_payment_partners',
			'label' => 'Zahlungspartner',
		],
	];

	private const MERGE_TARGETS = [
		'category' => [
			'table' => 'cobudget_categories',
			'label' => 'Kategorie',
			'referenceColumn' => 'category_id',
			'criteriaField' => 'categoryId',
		],
		'payment_partner' => [
			'table' => 'cobudget_payment_partners',
			'label' => 'Zahlungspartner',
			'referenceColumn' => 'payment_partner_id',
			'criteriaField' => null,
		],
	];

	/** @var array<string, bool> */
	private array $existsCache = [];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function inspect(): array {
		$orphanReferences = $this->orphanReferences();
		$duplicateVisibleNames = $this->duplicateVisibleNames();

		return [
			'orphanReferences' => $orphanReferences,
			'orphanReferenceCount' => $this->sumIssueCounts($orphanReferences),
			'duplicateVisibleNames' => $duplicateVisibleNames,
			'duplicateVisibleNameCount' => $this->sumIssueCounts($duplicateVisibleNames),
			'repairedReferences' => 0,
		];
	}

	public function repair(?array $report = null): array {
		$report ??= $this->inspect();
		$repairedReferences = 0;

		$this->db->beginTransaction();
		try {
			foreach ($report['orphanReferences'] ?? [] as $issue) {
				$ids = array_map('intval', $issue['ids'] ?? []);
				if ($ids === []) {
					continue;
				}

				$action = (string)($issue['repairAction'] ?? 'clear');
				if ($action === 'delete') {
					$repairedReferences += $this->deleteRows((string)$issue['sourceTable'], $ids);
					continue;
				}
				if ($action === 'deleteSettlement') {
					$repairedReferences += $this->deleteSettlementGroups($ids);
					continue;
				}
				if ($action === 'deleteBudgetGoal') {
					$repairedReferences += $this->deleteBudgetGoals($ids);
					continue;
				}
				if ($action === 'deleteHashtag') {
					$repairedReferences += $this->deleteHashtags($ids);
					continue;
				}

				$repairedReferences += $this->clearReference((string)$issue['sourceTable'], (string)$issue['column'], $ids);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$after = $this->inspect();
		$after['repairedReferences'] = $repairedReferences;
		$after['previousOrphanReferences'] = $report['orphanReferences'] ?? [];

		return $after;
	}

	public function mergeDuplicate(string $kind, int $keepId, array $mergeIds): array {
		if (!isset(self::MERGE_TARGETS[$kind])) {
			throw new \InvalidArgumentException('Unbekannter Dubletten-Typ.');
		}

		$mergeIds = array_values(array_unique(array_filter(array_map('intval', $mergeIds), static fn (int $id): bool => $id > 0)));
		if ($keepId <= 0 || $mergeIds === []) {
			throw new \InvalidArgumentException('Bitte eine Ziel-ID und mindestens eine Dubletten-ID angeben.');
		}
		if (in_array($keepId, $mergeIds, true)) {
			throw new \InvalidArgumentException('Die Ziel-ID darf nicht gleichzeitig entfernt werden.');
		}

		$target = self::MERGE_TARGETS[$kind];
		$table = (string)$target['table'];
		$referenceColumn = (string)$target['referenceColumn'];
		$keepRow = $this->nameRowById($table, $keepId);
		if ($keepRow === null) {
			throw new \InvalidArgumentException('Ziel-Datensatz wurde nicht gefunden.');
		}

		$removedRows = [];
		foreach ($mergeIds as $mergeId) {
			$mergeRow = $this->nameRowById($table, $mergeId);
			if ($mergeRow === null) {
				throw new \InvalidArgumentException('Dubletten-Datensatz ' . $mergeId . ' wurde nicht gefunden.');
			}
			$this->assertRowsCanBeMerged($keepRow, $mergeRow);
			$removedRows[] = $mergeRow;
		}

		$result = [
			'kind' => $kind,
			'label' => (string)$target['label'],
			'name' => trim((string)$keepRow['name']),
			'type' => (string)($keepRow['type'] ?? ''),
			'keepId' => $keepId,
			'mergedIds' => $mergeIds,
			'entriesUpdated' => 0,
			'templatesUpdated' => 0,
			'budgetGoalsUpdated' => 0,
			'removedRows' => 0,
		];

		$this->db->beginTransaction();
		try {
			$result['entriesUpdated'] = $this->replaceReferences('cobudget_entries', $referenceColumn, $keepId, $mergeIds);
			$result['templatesUpdated'] = $this->replaceReferences('cobudget_templates', $referenceColumn, $keepId, $mergeIds);
			if ($target['criteriaField'] !== null) {
				$result['budgetGoalsUpdated'] = $this->replaceBudgetCriteriaReferences((string)$target['criteriaField'], $keepId, $mergeIds);
			}
			$result['removedRows'] = $this->deleteRows($table, $mergeIds);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $result;
	}

	private function orphanReferences(): array {
		$issues = [];

		foreach (self::REFERENCE_CHECKS as $check) {
			$ids = $this->sourceIdsWithInvalidReference((string)$check['sourceTable'], (string)$check['column'], (string)$check['targetTable']);
			if ($ids === []) {
				continue;
			}

			$issues[] = [
				'sourceTable' => (string)$check['sourceTable'],
				'sourceLabel' => (string)$check['sourceLabel'],
				'column' => (string)$check['column'],
				'targetTable' => (string)$check['targetTable'],
				'targetLabel' => (string)$check['targetLabel'],
				'ids' => $ids,
				'count' => count($ids),
				'repairable' => true,
				'repairAction' => (string)($check['repairAction'] ?? 'clear'),
			];
		}

		return $issues;
	}

	private function sourceIdsWithInvalidReference(string $sourceTable, string $column, string $targetTable): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', $column)
			->from($sourceTable)
			->where($qb->expr()->isNotNull($column));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$ids = [];
		foreach ($rows as $row) {
			$sourceId = (int)($row['id'] ?? 0);
			$targetId = (int)($row[$column] ?? 0);
			if ($sourceId <= 0) {
				continue;
			}
			if ($targetId <= 0 || !$this->rowExists($targetTable, $targetId)) {
				$ids[] = $sourceId;
			}
		}

		return $ids;
	}

	private function duplicateVisibleNames(): array {
		$issues = [];

		foreach (self::DUPLICATE_NAME_CHECKS as $check) {
			$table = (string)$check['table'];
			$groups = [];
			foreach ($this->nameRows($table) as $row) {
				$name = trim((string)($row['name'] ?? ''));
				if ($name === '') {
					continue;
				}

				if (!$this->isVisibleNameRow($row)) {
					continue;
				}

				$key = implode('|', [
					(string)($row['type'] ?? ''),
					$this->normalizeVisibleName($name),
					$this->duplicateScopeKey($row),
				]);
				if (!isset($groups[$key])) {
					$groups[$key] = [
						'table' => $table,
						'label' => (string)$check['label'],
						'name' => $name,
						'type' => (string)($row['type'] ?? ''),
						'scope' => $this->duplicateScopeDescription($row),
						'ids' => [],
					];
				}
				$groups[$key]['ids'][] = (int)($row['id'] ?? 0);
			}

			foreach ($groups as $group) {
				$ids = array_values(array_filter(array_unique($group['ids']), static fn (int $id): bool => $id > 0));
				if (count($ids) < 2) {
					continue;
				}

				$issues[] = [
					'table' => $group['table'],
					'label' => $group['label'],
					'name' => $group['name'],
					'type' => $group['type'],
					'scope' => $group['scope'],
					'ids' => $ids,
					'count' => count($ids),
					'repairable' => false,
				];
			}
		}

		return $issues;
	}

	private function nameRows(string $table): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'type', 'user_id', 'is_global', 'is_hidden', 'workspace_id', 'project_id')
			->from($table);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function nameRowById(string $table, int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'type', 'user_id', 'is_global', 'is_hidden', 'workspace_id', 'project_id')
			->from($table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return is_array($row) ? $row : null;
	}

	private function assertRowsCanBeMerged(array $keepRow, array $mergeRow): void {
		if ((string)($keepRow['type'] ?? '') !== (string)($mergeRow['type'] ?? '')) {
			throw new \InvalidArgumentException('Dubletten koennen nur mit gleichem Typ zusammengefuehrt werden.');
		}

		$keepName = $this->normalizeVisibleName((string)($keepRow['name'] ?? ''));
		$mergeName = $this->normalizeVisibleName((string)($mergeRow['name'] ?? ''));
		if ($keepName === '' || $keepName !== $mergeName) {
			throw new \InvalidArgumentException('Dubletten koennen nur mit gleichem sichtbaren Namen zusammengefuehrt werden.');
		}

		if (!$this->rowsHaveSameDuplicateScope($keepRow, $mergeRow)) {
			throw new \InvalidArgumentException('Dubletten koennen nur im gleichen Benutzer-/Workspace-/Bereichs-Scope zusammengefuehrt werden.');
		}
	}

	private function rowsHaveSameDuplicateScope(array $keepRow, array $mergeRow): bool {
		return $this->duplicateScopeKey($keepRow) === $this->duplicateScopeKey($mergeRow);
	}

	private function duplicateScopeKey(array $row): string {
		return implode('|', [
			'user:' . $this->scopeValue($row['user_id'] ?? null),
			'workspace:' . $this->scopeValue($row['workspace_id'] ?? null),
			'project:' . $this->scopeValue($row['project_id'] ?? null),
			'global:' . ($this->boolValue($row['is_global'] ?? false) ? '1' : '0'),
		]);
	}

	private function duplicateScopeDescription(array $row): string {
		if ($this->boolValue($row['is_global'] ?? false)) {
			return 'global';
		}

		$parts = [];
		$userId = trim((string)($row['user_id'] ?? ''));
		if ($userId !== '') {
			$parts[] = 'user=' . $userId;
		}
		if (($row['workspace_id'] ?? null) !== null && $row['workspace_id'] !== '') {
			$parts[] = 'workspace=' . (int)$row['workspace_id'];
		}
		if (($row['project_id'] ?? null) !== null && $row['project_id'] !== '') {
			$parts[] = 'bereich=' . (int)$row['project_id'];
		}

		return $parts === [] ? 'persoenlich' : implode(', ', $parts);
	}

	private function scopeValue(mixed $value): string {
		if ($value === null || $value === '') {
			return 'null';
		}

		return (string)$value;
	}

	private function isVisibleNameRow(array $row): bool {
		return !$this->boolValue($row['is_hidden'] ?? false);
	}

	private function boolValue(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	private function replaceReferences(string $sourceTable, string $column, int $keepId, array $mergeIds): int {
		$updated = 0;
		foreach ($mergeIds as $mergeId) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($sourceTable)
				->set($column, $qb->createNamedParameter($keepId, \PDO::PARAM_INT))
				->where($qb->expr()->eq($column, $qb->createNamedParameter($mergeId, \PDO::PARAM_INT)));
			$updated += $qb->executeStatement();
		}

		return $updated;
	}

	private function replaceBudgetCriteriaReferences(string $field, int $keepId, array $mergeIds): int {
		$mergeMap = array_fill_keys(array_map('intval', $mergeIds), true);
		$updated = 0;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'criteria_json')
			->from('cobudget_budget_goals');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$criteria = json_decode((string)($row['criteria_json'] ?? ''), true);
			if (!is_array($criteria)) {
				continue;
			}

			$changed = false;
			if (isset($criteria['rules']) && is_array($criteria['rules'])) {
				$rules = &$criteria['rules'];
			} else {
				$rules = &$criteria;
			}
			foreach ($rules as &$rule) {
				if (!is_array($rule) || !isset($rule[$field]) || $rule[$field] === null) {
					continue;
				}
				if (isset($mergeMap[(int)$rule[$field]])) {
					$rule[$field] = $keepId;
					$changed = true;
				}
			}
			unset($rule, $rules);

			if (!$changed) {
				continue;
			}

			$update = $this->db->getQueryBuilder();
			$update->update('cobudget_budget_goals')
				->set('criteria_json', $update->createNamedParameter(json_encode($criteria)))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \PDO::PARAM_INT)));
			$updated += $update->executeStatement();
		}

		return $updated;
	}

	private function deleteRows(string $table, array $ids): int {
		$deleted = 0;
		foreach ($ids as $id) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, \PDO::PARAM_INT)));
			$deleted += $qb->executeStatement();
		}

		return $deleted;
	}

	private function deleteSettlementGroups(array $ids): int {
		$deleted = 0;
		$deleted += $this->deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', $ids);
		$deleted += $this->deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $ids);
		$deleted += $this->clearReference('cobudget_entries', 'settlement_id', $ids);
		$deleted += $this->deleteRows('cobudget_settlements', $ids);

		return $deleted;
	}

	private function deleteBudgetGoals(array $ids): int {
		$deleted = 0;
		$deleted += $this->deleteRowsByColumnValues('cobudget_budget_snapshots', 'budget_goal_id', $ids);
		$deleted += $this->deleteRows('cobudget_budget_goals', $ids);

		return $deleted;
	}

	private function deleteHashtags(array $ids): int {
		$deleted = 0;
		$deleted += $this->deleteRowsByColumnValues('cobudget_entry_hashtags', 'hashtag_id', $ids);
		$deleted += $this->deleteRows('cobudget_hashtags', $ids);

		return $deleted;
	}

	private function deleteRowsByColumnValues(string $table, string $column, array $values): int {
		$deleted = 0;
		foreach ($values as $value) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq($column, $qb->createNamedParameter((int)$value, \PDO::PARAM_INT)));
			$deleted += $qb->executeStatement();
		}

		return $deleted;
	}

	private function rowExists(string $table, int $id): bool {
		$key = $table . ':' . $id;
		if (array_key_exists($key, $this->existsCache)) {
			return $this->existsCache[$key];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();

		$this->existsCache[$key] = $exists;
		return $exists;
	}

	private function clearReference(string $sourceTable, string $column, array $ids): int {
		$updated = 0;
		foreach ($ids as $id) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($sourceTable)
				->set($column, $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, \PDO::PARAM_INT)));
			$updated += $qb->executeStatement();
		}

		return $updated;
	}

	private function normalizeVisibleName(string $name): string {
		$name = trim((string)preg_replace('/\s+/u', ' ', $name));
		return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
	}

	private function sumIssueCounts(array $issues): int {
		$count = 0;
		foreach ($issues as $issue) {
			$count += (int)($issue['count'] ?? 0);
		}

		return $count;
	}
}

<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class UserDeletionService {
	public function __construct(
		private IDBConnection $db,
		private ParticipantService $participants,
		private EntryProjectionService $entryProjectionService,
	) {
	}

	/**
	 * Remove personal data while retaining immutable shared financial records.
	 *
	 * @return array{former_id: ?string, transferred_projects: int, deleted_projects: int, deleted_entries: int, preserved_personal_shares: int}
	 */
	public function deleteUser(string $userId, string $displayName): array {
		$userId = trim($userId);
		if ($userId === '' || ParticipantService::isReservedFormerId($userId)) {
			return ['former_id' => null, 'transferred_projects' => 0, 'deleted_projects' => 0, 'deleted_entries' => 0, 'preserved_personal_shares' => 0];
		}

		$report = ['former_id' => null, 'transferred_projects' => 0, 'deleted_projects' => 0, 'deleted_entries' => 0, 'preserved_personal_shares' => 0];
		if (!$this->hasUserReferences($userId)) {
			return $report;
		}

		$step = 'initializing the cleanup transaction';
		$this->db->beginTransaction();
		try {
			$step = 'preserving shared areas and payment projections';
			$projects = $this->projectsForUser($userId);
			$survivingProjectIds = [];
			$survivingWorkspaceIds = [];
			$formerId = null;

			foreach ($projects as $project) {
				$projectId = (int)$project['id'];
				$memberIds = $this->projectMemberIds($projectId);
				$activeMembers = array_values(array_filter(
					$memberIds,
					fn (string $memberId): bool => $memberId !== $userId && $this->participants->isActive($memberId)
				));

				if ($activeMembers === []) {
					$report['deleted_entries'] += $this->deleteProjectTree($projectId);
					$report['deleted_projects']++;
					continue;
				}

				$formerId ??= $this->participants->createFormerParticipant($displayName);
				$survivingProjectIds[] = $projectId;
				$survivingWorkspaceIds[] = (int)$project['workspace_id'];
				if (in_array($userId, $memberIds, true)) {
					$this->ensureOpenProjectProjections($projectId);
				}
				if ((string)$project['owner_id'] === $userId) {
					$this->updateProjectOwner($projectId, $activeMembers[0]);
					$report['transferred_projects']++;
				}
				$this->stopDeletedUserRecurrences($projectId, $userId);
			}

			$step = 'removing standalone personal data';
			$personalEntryIds = $this->personalEntryIds($userId);
			$report['deleted_entries'] += count($personalEntryIds);
			$this->deleteEntryRows($personalEntryIds);

			$goalIds = $this->idsByStringColumn('cobudget_budget_goals', 'user_id', $userId);
			$this->deleteRowsByColumnValues('cobudget_budget_snapshots', 'budget_goal_id', $goalIds);
			$this->deleteRowsByStringColumn('cobudget_budget_snapshots', 'user_id', $userId);
			$this->deleteRowsByStringColumn('cobudget_budget_goals', 'user_id', $userId);
			$this->deleteRowsByStringColumn('cobudget_templates', 'user_id', $userId);
			$this->resetTemplateTargets($userId);
			$this->detachSurvivingAttachmentCopies($userId);
			$this->deleteRowsByStringColumn('cobudget_entry_attachments', 'owner_user_id', $userId);

			$step = 'preserving shared lookup data and workspaces';
			$survivingProjectIds = array_values(array_unique($survivingProjectIds));
			$preservedPersonalWorkspaceIds = $this->personalWorkspaceIdsForMember($userId, $survivingProjectIds);
			$report['preserved_personal_shares'] = $this->countProjectPersonalEntries($userId, $survivingProjectIds);
			$survivingWorkspaceIds = array_merge($survivingWorkspaceIds, $preservedPersonalWorkspaceIds);
			$survivingWorkspaceIds = array_values(array_unique($survivingWorkspaceIds));
			$lookupIds = $this->lookupIdsForProjects($survivingProjectIds);
			$step = 'anonymizing surviving financial references';
			if ($formerId !== null) {
				$this->updateRowsByIdsForOwner('cobudget_categories', $lookupIds['categories'], 'user_id', $userId, $formerId);
				$this->updateRowsByIdsForOwner('cobudget_payment_partners', $lookupIds['payment_partners'], 'user_id', $userId, $formerId);
			}
			$this->deleteRowsByStringColumn('cobudget_categories', 'user_id', $userId);
			$this->deleteRowsByStringColumn('cobudget_payment_partners', 'user_id', $userId);

			$ownedWorkspaceIds = $this->idsByStringColumn('cobudget_workspaces', 'user_id', $userId);
			$preservedWorkspaceIds = array_values(array_intersect($ownedWorkspaceIds, $survivingWorkspaceIds));
			$deletedWorkspaceIds = array_values(array_diff($ownedWorkspaceIds, $preservedWorkspaceIds));
			foreach ($deletedWorkspaceIds as $workspaceId) {
				$report['deleted_entries'] += $this->deleteWorkspaceTree((int)$workspaceId);
			}
			if ($formerId !== null) {
				$this->updateRowsByIds('cobudget_workspaces', $preservedWorkspaceIds, 'user_id', $formerId);
				$this->setWorkspaceDefaultsFalse($preservedWorkspaceIds);
				$this->remapHistoryValues($userId, $formerId, $displayName);
				$this->remapRemainingSharedReferences($userId, $formerId);
				$report['former_id'] = $formerId;
			} else {
				$this->deleteRowsByStringColumn('cobudget_members', 'user_id', $userId);
			}

			$step = 'removing orphaned description tags';
			$this->deleteOrphanHashtags();
			$this->db->commit();
			return $report;
		} catch (\Throwable $e) {
			try {
				$this->db->rollBack();
			} catch (\Throwable) {
			}
			throw new \RuntimeException('CoBudget user deletion failed while ' . $step . '.', 0, $e);
		}
	}

	private function hasUserReferences(string $userId): bool {
		foreach ([
			['cobudget_workspaces', 'user_id'],
			['cobudget_projects', 'owner_id'],
			['cobudget_members', 'user_id'],
			['cobudget_categories', 'user_id'],
			['cobudget_payment_partners', 'user_id'],
			['cobudget_entries', 'user_id'],
			['cobudget_entries', 'created_by'],
			['cobudget_entries', 'split_user_id'],
			['cobudget_templates', 'user_id'],
			['cobudget_templates', 'split_user_id'],
			['cobudget_entry_attachments', 'owner_user_id'],
			['cobudget_entry_shares', 'user_id'],
			['cobudget_entry_history', 'changed_by'],
			['cobudget_settlements', 'created_by'],
			['cobudget_settlement_balances', 'user_id'],
			['cobudget_settlement_transfers', 'from_user_id'],
			['cobudget_settlement_transfers', 'to_user_id'],
			['cobudget_budget_goals', 'user_id'],
			['cobudget_budget_snapshots', 'user_id'],
		] as [$table, $column]) {
			if ($this->rowExistsByStringColumn($table, $column, $userId)) {
				return true;
			}
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entry_history')
			->where($qb->expr()->orX(
				$qb->expr()->eq('old_value', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('new_value', $qb->createNamedParameter($userId))
			))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('field', $qb->createNamedParameter('user_id')),
				$qb->expr()->eq('field', $qb->createNamedParameter('split_user_id'))
			))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();

		return $exists;
	}

	private function rowExistsByStringColumn(string $table, string $column, string $value): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();

		return $exists;
	}

	private function ensureOpenProjectProjections(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('shared')))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->orderBy('id', 'ASC');
		foreach ($this->ids($this->fetchAll($qb)) as $entryId) {
			$this->entryProjectionService->ensureOpenSharedEntry($entryId);
		}
	}

	/** @param list<int> $projectIds @return list<int> */
	private function personalWorkspaceIdsForMember(string $userId, array $projectIds): array {
		if ($projectIds === []) {
			return [];
		}

		$workspaceIds = [];
		$members = $this->db->getQueryBuilder();
		$members->select('personal_workspace_id')
			->from('cobudget_members')
			->where($members->expr()->eq('user_id', $members->createNamedParameter($userId)))
			->andWhere($members->expr()->in('project_id', $members->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($members->expr()->isNotNull('personal_workspace_id'));
		foreach ($this->fetchAll($members) as $row) {
			$workspaceId = (int)($row['personal_workspace_id'] ?? 0);
			if ($workspaceId > 0) {
				$workspaceIds[] = $workspaceId;
			}
		}

		$entries = $this->db->getQueryBuilder();
		$entries->select('workspace_id')
			->from('cobudget_entries')
			->where($entries->expr()->eq('user_id', $entries->createNamedParameter($userId)))
			->andWhere($entries->expr()->eq('entry_kind', $entries->createNamedParameter('personal')))
			->andWhere($entries->expr()->in('project_id', $entries->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($entries->expr()->isNotNull('workspace_id'));
		foreach ($this->fetchAll($entries) as $row) {
			$workspaceId = (int)($row['workspace_id'] ?? 0);
			if ($workspaceId > 0) {
				$workspaceIds[] = $workspaceId;
			}
		}

		return array_values(array_unique($workspaceIds));
	}

	/** @param list<int> $projectIds */
	private function countProjectPersonalEntries(string $userId, array $projectIds): int {
		if ($projectIds === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count('*'), 'entry_count')
			->from('cobudget_entries')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('personal')))
			->andWhere($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	/** @return list<array{id: int|string, owner_id: string, workspace_id: int|string}> */
	private function projectsForUser(string $userId): array {
		$memberProjectIds = $this->idsByStringColumn('cobudget_members', 'user_id', $userId, 'project_id');
		$ownedWorkspaceIds = $this->idsByStringColumn('cobudget_workspaces', 'user_id', $userId);
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'owner_id', 'workspace_id')->from('cobudget_projects');
		$conditions = [$qb->expr()->eq('owner_id', $qb->createNamedParameter($userId))];
		if ($memberProjectIds !== []) {
			$conditions[] = $qb->expr()->in('id', $qb->createNamedParameter($memberProjectIds, IQueryBuilder::PARAM_INT_ARRAY));
		}
		if ($ownedWorkspaceIds !== []) {
			$conditions[] = $qb->expr()->in('workspace_id', $qb->createNamedParameter($ownedWorkspaceIds, IQueryBuilder::PARAM_INT_ARRAY));
		}
		$qb->where($qb->expr()->orX(...$conditions))->orderBy('id', 'ASC');
		return $this->fetchAll($qb);
	}

	/** @return list<string> */
	private function projectMemberIds(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		return array_values(array_filter(array_map(
			static fn (array $row): string => trim((string)$row['user_id']),
			$this->fetchAll($qb)
		)));
	}

	private function updateProjectOwner(int $projectId, string $ownerId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_projects')->set('owner_id', $qb->createNamedParameter($ownerId))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function stopDeletedUserRecurrences(int $projectId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('recurrence_interval', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_multiplier', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_next_date', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->set('recurrence_end_date', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('split_user_id', $qb->createNamedParameter($userId))
			));
		$qb->executeStatement();
	}

	private function detachSurvivingAttachmentCopies(string $ownerUserId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('owner_user_id', $qb->createNamedParameter($ownerUserId)))
			->andWhere($qb->expr()->isNull('source_attachment_id'));
		$sourceAttachmentIds = $this->ids($this->fetchAll($qb));
		if ($sourceAttachmentIds === []) {
			return;
		}

		foreach (array_chunk($sourceAttachmentIds, 500) as $chunk) {
			$update = $this->db->getQueryBuilder();
			$update->update('cobudget_entry_attachments')
				->set('source_attachment_id', $update->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($update->expr()->in('source_attachment_id', $update->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($update->expr()->neq('owner_user_id', $update->createNamedParameter($ownerUserId)));
			$update->executeStatement();
		}
	}

	/** @return list<int> */
	private function personalEntryIds(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('cobudget_entries')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNull('project_id'));
		return $this->ids($this->fetchAll($qb));
	}

	private function deleteProjectTree(int $projectId): int {
		$entryIds = $this->idsByIntColumn('cobudget_entries', 'project_id', $projectId);
		$settlementIds = $this->idsByIntColumn('cobudget_settlements', 'project_id', $projectId);
		$this->deleteEntryRows($entryIds);
		$this->deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', $settlementIds);
		$this->deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $settlementIds);
		$this->deleteRowsByIntColumn('cobudget_settlements', 'project_id', $projectId);
		foreach (['cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates', 'cobudget_members'] as $table) {
			$this->deleteRowsByIntColumn($table, 'project_id', $projectId);
		}
		$this->deleteRowsByIntColumn('cobudget_projects', 'id', $projectId);
		return count($entryIds);
	}

	private function deleteWorkspaceTree(int $workspaceId): int {
		$deletedEntries = 0;
		$projectIds = $this->idsByIntColumn('cobudget_projects', 'workspace_id', $workspaceId);
		foreach ($projectIds as $projectId) {
			$deletedEntries += $this->deleteProjectTree($projectId);
		}

		$entryIds = $this->idsByIntColumn('cobudget_entries', 'workspace_id', $workspaceId);
		$this->deleteEntryRows($entryIds);
		$deletedEntries += count($entryIds);
		$settlementIds = $this->idsByIntColumn('cobudget_settlements', 'workspace_id', $workspaceId);
		$this->deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', $settlementIds);
		$this->deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $settlementIds);
		$this->deleteRowsByIntColumn('cobudget_settlements', 'workspace_id', $workspaceId);
		$goalIds = $this->idsByIntColumn('cobudget_budget_goals', 'workspace_id', $workspaceId);
		$this->deleteRowsByColumnValues('cobudget_budget_snapshots', 'budget_goal_id', $goalIds);
		foreach (['cobudget_budget_snapshots', 'cobudget_budget_goals', 'cobudget_templates', 'cobudget_categories', 'cobudget_payment_partners', 'cobudget_hashtags'] as $table) {
			$this->deleteRowsByIntColumn($table, 'workspace_id', $workspaceId);
		}
		$this->deleteRowsByIntColumn('cobudget_workspaces', 'id', $workspaceId);
		return $deletedEntries;
	}

	/** @param list<int> $entryIds */
	private function deleteEntryRows(array $entryIds): void {
		if ($entryIds === []) {
			return;
		}
		foreach (['cobudget_entry_hashtags', 'cobudget_entry_attachments', 'cobudget_entry_history', 'cobudget_entry_shares'] as $table) {
			$this->deleteRowsByColumnValues($table, 'entry_id', $entryIds);
		}
		$this->deleteRowsByColumnValues('cobudget_entries', 'id', $entryIds);
	}

	/** @param list<int> $projectIds @return array{categories: list<int>, payment_partners: list<int>} */
	private function lookupIdsForProjects(array $projectIds): array {
		if ($projectIds === []) {
			return ['categories' => [], 'payment_partners' => []];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('category_id', 'payment_partner_id')->from('cobudget_entries')
			->where($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$categories = [];
		$partners = [];
		foreach ($this->fetchAll($qb) as $row) {
			if ((int)($row['category_id'] ?? 0) > 0) {
				$categories[] = (int)$row['category_id'];
			}
			if ((int)($row['payment_partner_id'] ?? 0) > 0) {
				$partners[] = (int)$row['payment_partner_id'];
			}
		}
		$categories = array_merge($categories, $this->idsByIntValues('cobudget_categories', 'project_id', $projectIds));
		$partners = array_merge($partners, $this->idsByIntValues('cobudget_payment_partners', 'project_id', $projectIds));
		return [
			'categories' => array_values(array_unique($categories)),
			'payment_partners' => array_values(array_unique($partners)),
		];
	}

	private function resetTemplateTargets(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_templates')
			->set('split_mode', $qb->createNamedParameter('project_shares'))
			->set('split_user_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->where($qb->expr()->eq('split_user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	private function remapRemainingSharedReferences(string $oldUserId, string $formerId): void {
		foreach ([
			['cobudget_members', 'user_id'],
			['cobudget_entries', 'user_id'],
			['cobudget_entries', 'created_by'],
			['cobudget_entries', 'split_user_id'],
			['cobudget_entry_shares', 'user_id'],
			['cobudget_entry_history', 'changed_by'],
			['cobudget_settlements', 'created_by'],
			['cobudget_settlement_balances', 'user_id'],
			['cobudget_settlement_transfers', 'from_user_id'],
			['cobudget_settlement_transfers', 'to_user_id'],
		] as [$table, $column]) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($table)->set($column, $qb->createNamedParameter($formerId))
				->where($qb->expr()->eq($column, $qb->createNamedParameter($oldUserId)));
			$qb->executeStatement();
		}
	}

	private function remapHistoryValues(string $oldUserId, string $formerId, string $displayName): void {
		$displayName = trim($displayName) ?: 'Former member';
		foreach ([['old_value', 'old_display'], ['new_value', 'new_display']] as [$valueColumn, $displayColumn]) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entry_history')
				->set($valueColumn, $qb->createNamedParameter($formerId))
				->set($displayColumn, $qb->createNamedParameter($displayName))
				->where($qb->expr()->orX(
					$qb->expr()->eq('field', $qb->createNamedParameter('user_id')),
					$qb->expr()->eq('field', $qb->createNamedParameter('split_user_id'))
				))
				->andWhere($qb->expr()->eq($valueColumn, $qb->createNamedParameter($oldUserId)));
			$qb->executeStatement();
		}
	}

	/** @param list<int> $workspaceIds */
	private function setWorkspaceDefaultsFalse(array $workspaceIds): void {
		if ($workspaceIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_workspaces')->set('is_default', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			->where($qb->expr()->in('id', $qb->createNamedParameter($workspaceIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	private function deleteOrphanHashtags(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('h.id', 'id')->from('cobudget_hashtags', 'h')
			->leftJoin('h', 'cobudget_entry_hashtags', 'eh', $qb->expr()->eq('eh.hashtag_id', 'h.id'))
			->where($qb->expr()->isNull('eh.id'));
		$this->deleteRowsByColumnValues('cobudget_hashtags', 'id', $this->ids($this->fetchAll($qb)));
	}

	/** @return list<int> */
	private function idsByStringColumn(string $table, string $column, string $value, string $select = 'id'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select($select)->from($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($value)));
		return array_values(array_unique(array_map(static fn (array $row): int => (int)$row[$select], $this->fetchAll($qb))));
	}

	/** @return list<int> */
	private function idsByIntColumn(string $table, string $column, int $value): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)));
		return $this->ids($this->fetchAll($qb));
	}

	/** @param list<int> $values @return list<int> */
	private function idsByIntValues(string $table, string $column, array $values): array {
		if ($values === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($table)
			->where($qb->expr()->in($column, $qb->createNamedParameter($values, IQueryBuilder::PARAM_INT_ARRAY)));
		return $this->ids($this->fetchAll($qb));
	}

	/** @param list<int> $ids */
	private function updateRowsByIds(string $table, array $ids, string $column, string $value): void {
		if ($ids === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update($table)->set($column, $qb->createNamedParameter($value))
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/** @param list<int> $ids */
	private function updateRowsByIdsForOwner(string $table, array $ids, string $column, string $oldValue, string $newValue): void {
		if ($ids === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update($table)->set($column, $qb->createNamedParameter($newValue))
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($oldValue)));
		$qb->executeStatement();
	}

	private function deleteRowsByStringColumn(string $table, string $column, string $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($value)));
		$qb->executeStatement();
	}

	private function deleteRowsByIntColumn(string $table, string $column, int $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	/** @param list<int> $values */
	private function deleteRowsByColumnValues(string $table, string $column, array $values): void {
		if ($values === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)->where($qb->expr()->in($column, $qb->createNamedParameter($values, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/** @return list<array<string, mixed>> */
	private function fetchAll(IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** @param list<array<string, mixed>> $rows @return list<int> */
	private function ids(array $rows): array {
		return array_values(array_unique(array_map(static fn (array $row): int => (int)$row['id'], $rows)));
	}
}

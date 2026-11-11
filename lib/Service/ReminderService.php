<?php
declare(strict_types = 1);

namespace OCA\CoBudget\Service;

use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
/** Account-wide personal organizer. Payment title/time remain shared payment fields; completion and notes are personal. */
class ReminderService {
	public const LABELS = ['cancel', 'renew', 'review', 'pay', 'subscription', 'insurance', 'fixedCost', 'important', 'childRelated', 'taxRelevant'];

	private array $heldLocks = [];

	public function __construct(private IDBConnection $db, private ParticipantService $participants, private ILockingProvider $locks) {
	}

	/** Derived on read, so archival does not depend on cron or change the draft revision. */
	private function automaticArchiveAt(array $row): ?int {
		if ($row['keep_open']) return null;
		$at = (int)($row['completed_at'] ?: $row['remind_at']) + 14 * 86400;
		return $at <= time() ? $at : null;
	}

	private function locked(string $uid, \Closure $fn): mixed {
		$this->lockPaymentEdit($uid);
		try {
			return $fn();
		}
		finally {
			$this->unlockPaymentEdit($uid);
		}
	}

	/** Hold the organizer lock until the caller commits or rolls back its payment transaction. */
	public function lockPaymentEdit(string $uid): void {
		if (!empty($this->heldLocks[$uid])) { $this->heldLocks[$uid]++; return; }
		$this->locks->acquireLock(hash('sha256', 'cobudget:reminders:'.$uid), ILockingProvider::LOCK_EXCLUSIVE);
		$this->heldLocks[$uid] = 1;
	}

	public function unlockPaymentEdit(string $uid): void {
		if (empty($this->heldLocks[$uid]) || --$this->heldLocks[$uid] > 0) return;
		unset($this->heldLocks[$uid]);
		$this->locks->releaseLock(hash('sha256', 'cobudget:reminders:'.$uid), ILockingProvider::LOCK_EXCLUSIVE);
	}

	/** Called before changing the payment, under lock and inside its transaction. */
	public function paymentDraft(string $uid, ?int $entryId, array $data): ?array {
		if (!$this->participants->isActive($uid)) throw new \OutOfBoundsException();
		$current = null;
		foreach ($this->records($uid) as $row) {
			if ($entryId !== null && (int)$row['entry_id'] === $entryId) $current = $row;
		}
		if ((int)($data['id'] ?? 0) !== (int)($current['id'] ?? 0)
			|| (int)($data['revision'] ?? 0) !== (int)($current['revision'] ?? 0)) throw new \DomainException();
		if ($entryId !== null) {
			$entry = $this->payments($uid, false)[$entryId] ?? null;
			if (!$entry) throw new \OutOfBoundsException();
			if ($current && $current['entry_signature'] !== $this->signature($entry)) throw new \DomainException();
		}
		return $current;
	}

	/** Personal fields join the financial transaction; never change another recipient's notes/status. */
	public function savePaymentDetails(string $uid, int $entryId, array $data, ?array $current): void {
		$entry = $this->payments($uid, false)[$entryId] ?? null;
		if (!$entry) throw new \OutOfBoundsException();
		$date = (int)($entry['reminder_date'] ?? 0);
		if (!$date) {
			if ($current) $this->deleteRecord($uid, (int)$current['id']);
			return;
		}
		$title = trim((string)($entry['reminder_text'] ?? ''));
		$description = $data['description'] ?? '';
		$labels = $data['labels'] ?? [];
		if (!is_string($description) || mb_strlen($description) > 10000 || mb_strlen($title) > 255
			|| $date < 1 || $date > 4102444800 || !is_array($labels)
			|| array_filter($labels, fn($v) => !is_string($v) || !in_array($v, ['cancel', 'renew', 'review', 'pay'], true))) throw new \InvalidArgumentException();
		foreach (['completed', 'archived'] as $field) {
			if (isset($data[$field]) && !is_bool($data[$field])) throw new \InvalidArgumentException();
		}
		// Old payment reminders may have no title. Preserve those until explicitly edited.
		if ($title === '' && (!$current || trim((string)$current['title']) !== '')) throw new \InvalidArgumentException();
		$changedTime = !$current || (int)$current['remind_at'] !== $date;
		$automaticArchive = $current ? $this->automaticArchiveAt($current) : null;
		$wasArchived = $current['archived_at'] ?? $automaticArchive;
		$completed = !empty($data['completed']);
		$archived = !empty($data['archived']);
		if ($current && ($current['completed_at'] || $wasArchived) && !$completed && !$archived && $date <= time()) throw new \InvalidArgumentException();
		$this->write($uid, $current ? (int)$current['id'] : null, [
			'entry_id' => $entryId, 'title' => $title, 'description' => trim($description),
			'remind_at' => $date, 'entry_signature' => $this->signature($entry),
			'labels' => json_encode(array_values(array_unique($labels))),
			'keep_open' => (bool)($current['keep_open'] ?? false),
			'completed_at' => $completed ? ($current['completed_at'] ?? time()) : null,
			'archived_at' => $archived ? ($wasArchived ?? time()) : null,
			'dismissed' => $changedTime ? false : (bool)($current['dismissed'] ?? false),
			'notified' => $changedTime ? false : (bool)($current['notified'] ?? $entry['reminder_notified']),
			'revision' => (int)($current['revision'] ?? 0)+1,
		]);
	}

	public function paymentChoices(string $uid, string $search = ''): array {
		return array_values($this->payments($uid, false, 100, mb_substr(trim($search), 0, 255)));
	}

	private function payments(string $uid, bool $remindersOnly = true, ?int $limit = null, string $search = ''): array {
		$q = $this->db->getQueryBuilder();
		$q->select('e.*', 'p.name AS project_name', 'm.personal_workspace_id AS member_workspace_id')->from('cobudget_entries', 'e') ->leftJoin('e', 'cobudget_members', 'm', $q->expr()->andX($q->expr()->eq('e.project_id', 'm.project_id'), $q->expr()->eq('m.user_id', $q->createNamedParameter($uid)))) ->leftJoin('e', 'cobudget_projects', 'p', $q->expr()->eq('e.project_id', 'p.id')) ->where($q->expr()->isNull('e.source_entry_id')) ->andWhere($q->expr()->orX($q->expr()->andX($q->expr()->eq('e.entry_kind', $q->createNamedParameter('personal')), $q->expr()->eq('e.user_id', $q->createNamedParameter($uid))), $q->expr()->andX($q->expr()->eq('e.entry_kind', $q->createNamedParameter('shared')), $q->expr()->isNotNull('m.id'))));
		if ($remindersOnly)$q->andWhere($q->expr()->isNotNull('e.reminder_date'))->andWhere($q->expr()->gt('e.reminder_date', $q->createNamedParameter(0)));
		if ($limit !== null) {
			$q->setMaxResults($limit)->orderBy('e.date', 'DESC');
			$q->andWhere($q->expr()->orX($q->expr()->isNull('e.reminder_date'), $q->expr()->lte('e.reminder_date', $q->createNamedParameter(0))));
		}
		if ($search !== '') $q->andWhere($q->expr()->iLike('e.description', $q->createNamedParameter('%'.$this->db->escapeLikeParameter($search).'%')));
		$r = $q->executeQuery();
		$rows = array_column($r->fetchAll(), null, 'id');
		$r->closeCursor();
		return $rows;
	}

	private function records(string $uid): array {
		$q = $this->db->getQueryBuilder();
		$r = $q->select('*')->from('cobudget_reminders')->where($q->expr()->eq('user_id', $q->createNamedParameter($uid)))->executeQuery();
		$rows = array_column($r->fetchAll(), null, 'id');
		$r->closeCursor();
		return $rows;
	}

	private function signature(array $entry): string {
		return hash('sha256', json_encode([(int)$entry['reminder_date'], (string)($entry['reminder_text'] ?? '')], JSON_THROW_ON_ERROR));
	}

	private function write(string $uid, ?int $id, array $values): int {
		$q = $this->db->getQueryBuilder();
		if ($id === null) {
			$q->insert('cobudget_reminders');
			$values['user_id'] = $uid;
			foreach ($values as $k => $v)$q->setValue($k, $q->createNamedParameter($v, $v === null ? \PDO::PARAM_NULL : (is_bool($v) ? \PDO::PARAM_BOOL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR))));
		}
		else {
			$q->update('cobudget_reminders')->where($q->expr()->eq('id', $q->createNamedParameter($id)))->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($uid)));
			foreach ($values as $k => $v)$q->set($k, $q->createNamedParameter($v, $v === null ? \PDO::PARAM_NULL : (is_bool($v) ? \PDO::PARAM_BOOL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR))));
		}
		$q->executeStatement();
		return $id ?? (int)$q->getLastInsertId();
	}
	/** Lazy adoption preserves existing delivery flags and calendar identities; repeat calls are idempotent. */

	public function rows(string $uid, bool $includeDismissed = false): array {
		if (!$this->participants->isActive($uid))return [];
		return $this->locked($uid, function()use($uid, $includeDismissed) {
			$entries = $this->payments($uid);
			$rows = $this->records($uid);
			$byEntry = array_column(array_filter($rows, fn($r) => $r['entry_id'] !== null), null, 'entry_id');
			foreach ($entries as $eid => $e) {
				$signature = $this->signature($e);
				$old = $byEntry[$eid] ?? null;
				if (!$old) {
					$this->write($uid, null, ['entry_id' => $eid, 'title' => trim((string)($e['reminder_text'] ?? '')), 'remind_at' => (int)$e['reminder_date'], 'notified' => (bool)$e['reminder_notified'], 'entry_signature' => $signature]);
				}
				elseif ($old['entry_signature'] !== $signature) {
					$values = ['title' => trim((string)($e['reminder_text'] ?? '')), 'remind_at' => (int)$e['reminder_date'], 'entry_signature' => $signature, 'notified' => (bool)$e['reminder_notified'], 'revision' => (int)$old['revision']+1];
					if ((int)$old['remind_at'] !== (int)$e['reminder_date'])$values += ['completed_at' => null, 'archived_at' => null, 'dismissed' => false];
					$this->write($uid, (int)$old['id'], $values);
				}
			}
			foreach ($rows as $id => $r)if ($r['entry_id'] !== null && !isset($entries[$r['entry_id']]))$this->deleteRecord($uid, (int)$id);
			$rows = $this->records($uid);
			foreach ($rows as $id => &$r) {
				if ($r['dismissed'] && !$includeDismissed) {
					unset($rows[$id]);
					continue;
				}
				$e = $entries[$r['entry_id']] ?? null;
				$r['id'] = (int)$id;
				$r['entry_id'] = $r['entry_id'] === null?null:(int)$r['entry_id'];
				$r['remind_at'] = (int)$r['remind_at'];
				$r['revision'] = (int)$r['revision'];
				$r['keep_open'] = (bool)$r['keep_open'];
				$r['labels'] = json_decode($r['labels'], true)?:[];
				$r['effective_archived_at'] = $r['archived_at'] ? (int)$r['archived_at'] : $this->automaticArchiveAt($r);
				$r['status'] = $r['effective_archived_at'] ? 'archived' : ($r['completed_at'] ? 'completed' : 'active');
				$r['project_id'] = $e['project_id'] ?? null;
				$r['project_name'] = $e['project_name'] ?? null;
				$r['entry_kind'] = $e['entry_kind'] ?? 'personal';
				$r['payment_title'] = $e?trim((string)($e['description'] ?? '')): null;
				$r['payment_workspace_id'] = $e ? (int)($e['entry_kind'] === 'shared' ? $e['member_workspace_id'] : $e['workspace_id']) : null;
				$r['payment_date'] = $e?(int)$e['date']:null;
				$r['payment_labels'] = [];
				foreach (['is_subscription' => 'subscription', 'is_insurance' => 'insurance', 'is_fixed_cost' => 'fixedCost', 'is_important' => 'important', 'needs_review' => 'review', 'is_child_related' => 'childRelated', 'is_tax_relevant' => 'taxRelevant']as$field => $label)if (!empty($e[$field]))$r['payment_labels'][] = $label;
			}
			unset($r);
			uasort($rows, fn($a, $b) => $a['remind_at']<=>$b['remind_at']);
			return $rows;
		});
	}

	/** Change only the time of an open reminder, preserving personal metadata. */
	public function postpone(string $uid, int $id, int $revision, int $remindAt): array {
		$row = $this->rows($uid)[$id] ?? null;
		if (!$row) throw new \OutOfBoundsException();
		if ((int)$row['revision'] !== $revision) throw new \DomainException();
		if ($row['status'] !== 'active' || $remindAt <= time() || $remindAt <= (int)$row['remind_at']) {
			throw new \InvalidArgumentException('Choose a later reminder time');
		}
		return $this->save($uid, $id, array_replace($row, [
			'revision' => $revision, 'remind_at' => $remindAt,
			'completed' => false, 'archived' => false,
		]));
	}

	public function save(string $uid, ?int $id, array $data): array {
		if (!$this->participants->isActive($uid)) throw new \OutOfBoundsException();
		foreach (['title', 'description', 'due_date'] as $field) if (isset($data[$field]) && !is_string($data[$field])) throw new \InvalidArgumentException();
		if (isset($data['entry_id']) && !is_scalar($data['entry_id'])) throw new \InvalidArgumentException();
		$rows = $this->rows($uid, true);
		$existing = $id === null?null:($rows[$id] ?? null);
		if ($id !== null && !$existing)throw new \OutOfBoundsException();
		$title = trim((string)($data['title'] ?? ''));
		$description = trim((string)($data['description'] ?? ''));
		$date = filter_var($data['remind_at'] ?? null, FILTER_VALIDATE_INT);
		$due = trim((string)($data['due_date'] ?? ''));
		$labels = $data['labels'] ?? [];
		if (($title === '' && !($existing && $existing['entry_id'] !== null && trim((string)$existing['title']) === '')) || mb_strlen($title)>255 || mb_strlen($description)>10000 || !$date || $date<1 || $date>4102444800 || !is_array($labels) || array_filter($labels, fn($v) => !is_string($v) || !in_array($v, self::LABELS, true)))throw new \InvalidArgumentException('Invalid reminder fields');
		if ($due !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || !checkdate((int)substr($due, 5, 2), (int)substr($due, 8, 2), (int)substr($due, 0, 4))))throw new \InvalidArgumentException('Invalid due date');
		$entryId = $existing ? $existing['entry_id'] : (empty($data['entry_id'])?null:(int)$data['entry_id']);
		$entries = $this->payments($uid, false);
		if ($entryId !== null && !isset($entries[$entryId]))throw new \OutOfBoundsException();
		if (!$existing && $entryId !== null) {
			foreach ($rows as$r)if ($r['entry_id'] === $entryId)throw new \DomainException('A reminder already exists for this payment');
		}
		if (array_key_exists('archived', $data) && !is_bool($data['archived'])) throw new \InvalidArgumentException('Invalid archive state');
		if ($existing && $existing['status'] !== 'active' && empty($data['completed']) && empty($data['archived']) && ($existing['status'] === 'completed' || $date !== $existing['remind_at'] || (!$existing['keep_open'] && !empty($data['keep_open'])) || (array_key_exists('archived', $data) && !$data['archived'])) && $date <= time())throw new \InvalidArgumentException('Choose a future reminder time to reactivate');
		$id = $this->locked($uid, function()use($uid, $id, $existing, $data, $title, $description, $date, $due, $labels, $entryId, $entries) {
			$current = $id === null?null:($this->records($uid)[$id] ?? null);
			if ($id !== null && (!$current || (int)$current['revision'] !== (int)($data['revision'] ?? 0)))throw new \DomainException('Reminder changed; reload before saving');
			$values = ['entry_id' => $entryId, 'title' => $title, 'description' => $description, 'remind_at' => $date, 'due_date' => $due?:null, 'labels' => json_encode(array_values(array_unique($labels))), 'keep_open' => (bool)($data['keep_open'] ?? false), 'completed_at' => !empty($data['completed'])?($current['completed_at'] ?? time()): null, 'archived_at' => ($current && (int)$current['remind_at'] === $date) ? $current['archived_at'] : null, 'dismissed' => false, 'revision' => ($current?(int)$current['revision']:0)+1];
			// Sidebar status switches are saved atomically with notes and the date.
			if (array_key_exists('archived', $data)) $values['archived_at'] = $data['archived'] ? ($current['archived_at'] ?? $existing['effective_archived_at'] ?? time()) : null;
			if (!$current || (int)$current['remind_at'] !== $date)$values['notified'] = false;
			$this->db->beginTransaction();
			try {
				if ($entryId !== null) {
					$e = $this->payments($uid, false)[$entryId] ?? null;
					if (!$e) throw new \OutOfBoundsException();
					if ($current && $current['entry_signature'] !== $this->signature($e)) throw new \DomainException();
					if (!$current && !empty($e['reminder_date'])) throw new \DomainException();
					$values['entry_signature'] = $this->signature(['reminder_date' => $date, 'reminder_text' => $title]);
					$q = $this->db->getQueryBuilder();
					$q->update('cobudget_entries')->set('reminder_date', $q->createNamedParameter($date))->set('reminder_text', $q->createNamedParameter($title));
					if ((int)($e['reminder_date'] ?? 0) !== $date)$q->set('reminder_notified', $q->createNamedParameter(false));
					$q->andWhere($q->expr()->orX($q->expr()->isNull('reminder_date'), $q->expr()->eq('reminder_date', $q->createNamedParameter((int)($e['reminder_date'] ?? 0))))) ->andWhere($q->expr()->eq('entry_kind', $q->createNamedParameter($e['entry_kind'])));
					if ($e['entry_kind'] === 'shared') {
						$membership = $this->db->getQueryBuilder();
						$membership->select('project_id')->from('cobudget_members')->where($membership->expr()->eq('user_id', $q->createNamedParameter($uid)));
						$q->andWhere($q->expr()->in('project_id', $q->createFunction($membership->getSQL())));
					}
					if ($e['reminder_text'] === null) $q->andWhere($q->expr()->isNull('reminder_text'));
					else $q->andWhere($q->expr()->eq('reminder_text', $q->createNamedParameter($e['reminder_text'])));
					$changed = $q->andWhere($q->expr()->eq('id', $q->createNamedParameter($entryId)))->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($e['user_id'])))->executeStatement();
					if ($changed === 0) {
						$actual = $this->payments($uid, false)[$entryId] ?? null;
						if (!$actual || (int)$actual['reminder_date'] !== $date || $actual['reminder_text'] !== $title) throw new \DomainException();
					}
				}
				if ($entryId !== null) $this->recordPaymentHistory($uid, $e, $date, $title);
				$saved = $this->write($uid, $id, $values);
				$this->db->commit();
				return $saved;
			}
			catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
		});
		return $this->rows($uid)[$id];
	}

	public function importCalendar(string $uid, array $current, array $fields): array {
		$oldSignature=$current['entry_signature'];
		$changedTime=(int)$current['remind_at'] !== (int)$fields['remind_at'];
		$saved=$this->save($uid,(int)$current['id'],array_replace($current,$fields,[
			'completed'=>!empty($current['completed_at']), 'archived'=>!empty($current['effective_archived_at']),
		]));
		if ($changedTime && (int)$fields['remind_at'] <= time()) $this->markNotified($uid,(int)$saved['id'],(int)$fields['remind_at']);
		if ($current['entry_id'] !== null && ($changedTime || $fields['title'] !== $current['title'])) {
			// Materialize an already effective archive before changing the timestamp it was derived from.
			$q=$this->db->getQueryBuilder();
			$result=$q->select('*')->from('cobudget_reminders')->where($q->expr()->eq('entry_id',$q->createNamedParameter((int)$current['entry_id'])))
				->andWhere($q->expr()->neq('user_id',$q->createNamedParameter($uid)))
				->andWhere($q->expr()->eq('entry_signature',$q->createNamedParameter($oldSignature)))->executeQuery();
			$peers=$result->fetchAll(); $result->closeCursor();
			foreach ($peers as $peer) {
				$at=$peer['archived_at'] ?: $this->automaticArchiveAt($peer);
				if ($at && !$peer['archived_at']) $this->write($peer['user_id'],(int)$peer['id'],['archived_at'=>(int)$at]);
			}
			// Shared title/time are canonical, but a calendar edit must not reactivate another member's closed reminder.
			$q=$this->db->getQueryBuilder();
			$q->update('cobudget_reminders')->set('title',$q->createNamedParameter($fields['title']))
				->set('remind_at',$q->createNamedParameter((int)$fields['remind_at']))
				->set('entry_signature',$q->createNamedParameter($this->signature(['reminder_date'=>$fields['remind_at'],'reminder_text'=>$fields['title']])))
				->set('revision',$q->createFunction('revision + 1'))
				->where($q->expr()->eq('entry_id',$q->createNamedParameter((int)$current['entry_id'])))
				->andWhere($q->expr()->neq('user_id',$q->createNamedParameter($uid)))
				->andWhere($q->expr()->eq('entry_signature',$q->createNamedParameter($oldSignature)));
			if ($changedTime) $q->set('notified',$q->createNamedParameter((int)$fields['remind_at']<=time(),\PDO::PARAM_BOOL));
			$q->executeStatement();
		}
		return $saved;
	}

	/** Only the shared payment fields enter its history; personal notes/status never do. */
	private function recordPaymentHistory(string $uid, array $entry, int $date, string $title): void {
		$group = bin2hex(random_bytes(8));
		$at = time();
		foreach (['reminder_date' => (string)$date, 'reminder_text' => $title] as $field => $value) {
			$old = trim((string)($entry[$field] ?? '')) ?: null;
			if ($old === $value) continue;
			$display = static fn(?string $v): string => $v === null ? '-' : ($field === 'reminder_date' ? date('d.m.Y H:i', (int)$v) : $v);
			$values = [
				'entry_id' => (int)$entry['id'], 'workspace_id' => (int)$entry['workspace_id'],
				'project_id' => $entry['project_id'] === null ? null : (int)$entry['project_id'],
				'changed_by' => $uid, 'changed_by_display_name' => $this->participants->displayName($uid),
				'changed_at' => $at, 'change_group' => $group, 'field' => $field,
				'old_value' => $old, 'new_value' => $value, 'old_display' => $display($old), 'new_display' => $display($value),
			];
			$q = $this->db->getQueryBuilder();
			$q->insert('cobudget_entry_history');
			foreach ($values as $column => $v) $q->setValue($column, $q->createNamedParameter($v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR)));
			$q->executeStatement();
		}
	}

	private function requireCurrent(string $uid, int $id, int $revision): array {
		$r = $this->records($uid)[$id] ?? null;
		if (!$r) throw new \OutOfBoundsException();
		if ((int)$r['revision'] !== $revision) throw new \DomainException();
		if ($r['entry_id'] !== null) {
			$entry = $this->payments($uid)[$r['entry_id']] ?? null;
			if (!$entry) throw new \OutOfBoundsException();
			if ($this->signature($entry) !== $r['entry_signature']) throw new \DomainException();
		}
		return $r;
	}

	public function setStatus(string $uid, int $id, string $status, int $revision): void {
		$rows = $this->rows($uid);
		$r = $rows[$id] ?? null;
		if (!$r)throw new \OutOfBoundsException();
		if (!in_array($status, ['completed', 'incomplete', 'archived', 'active'], true))throw new \InvalidArgumentException();
		if (($status === 'active' || ($status === 'incomplete' && !$r['effective_archived_at'])) && $r['remind_at'] <= time())throw new \InvalidArgumentException('Choose a future reminder time to reactivate');
		$this->locked($uid, function()use($uid, $id, $status, $revision) {
			$r = $this->requireCurrent($uid, $id, $revision);
			$values = ['revision' => $revision+1];
			if ($status === 'completed') $values['completed_at'] = $r['completed_at'] ?? time();
			elseif ($status === 'archived') $values['archived_at'] = $r['archived_at'] ?? time();
			elseif ($status === 'incomplete') $values['completed_at'] = null;
			else $values += ['completed_at' => null, 'archived_at' => null];
			// Toggling completion on an archived row must preserve its original archive date.
			if (in_array($status, ['completed', 'incomplete'], true) && !$r['archived_at']) {
				$automaticArchive = $this->automaticArchiveAt($r);
				if ($automaticArchive !== null) $values['archived_at'] = $automaticArchive;
			}
			$this->write($uid, $id, $values);
		});
	}

	public function remove(string $uid, int $id, int $revision): void {
		$rows = $this->rows($uid);
		$r = $rows[$id] ?? null;
		if (!$r)throw new \OutOfBoundsException();
		$this->locked($uid, function()use($uid, $id, $r, $revision) {
			$current = $this->requireCurrent($uid, $id, $revision);
			if ($r['entry_id'] !== null)$this->write($uid, $id, ['dismissed' => true, 'revision' => $revision+1]);
			else $this->deleteRecord($uid, $id);
		});
	}

	private function deleteRecord(string $uid, int $id): void {
		$q = $this->db->getQueryBuilder();
		$q->delete('cobudget_reminders')->where($q->expr()->eq('id', $q->createNamedParameter($id)))->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($uid)))->executeStatement();
	}

	public function markNotified(string $uid, int $id, int $date): void {
		$q = $this->db->getQueryBuilder();
		$q->update('cobudget_reminders')->set('notified', $q->createNamedParameter(true))->where($q->expr()->eq('user_id', $q->createNamedParameter($uid)))->andWhere($q->expr()->eq('id', $q->createNamedParameter($id)))->andWhere($q->expr()->eq('remind_at', $q->createNamedParameter($date)))->executeStatement();
	}

	public function users(): array {
		$q = $this->db->getQueryBuilder();
		$r = $q->selectDistinct('user_id')->from('cobudget_reminders')->executeQuery();
		$ids = array_column($r->fetchAll(), 'user_id');
		$r->closeCursor();
		return $ids;
	}
}

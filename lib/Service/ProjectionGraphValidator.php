<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

/**
 * Validates the shared-payment graph without depending on a database.
 *
 * The same rules are used for backup payloads and the persisted database
 * state so destructive operations cannot accept a graph that restore would
 * later reject (or vice versa).
 */
final class ProjectionGraphValidator {
	/** @return array<int, array{code: string, table: string, id: int, message: string}> */
	public static function validate(array $tables): array {
		$issues = [];
		$workspaces = self::rowsById($tables['cobudget_workspaces'] ?? []);
		$projects = self::rowsById($tables['cobudget_projects'] ?? []);
		$entries = self::rowsById($tables['cobudget_entries'] ?? []);
		$members = [];
		$shares = [];
		$sharesByEntry = [];

		foreach ($tables['cobudget_members'] ?? [] as $member) {
			$projectId = self::nullableId($member['project_id'] ?? null);
			$userId = trim((string)($member['user_id'] ?? ''));
			if ($projectId === null || $userId === '') {
				continue;
			}
			$key = self::memberKey($projectId, $userId);
			$members[$key] = $member;

			$workspaceId = self::nullableId($member['personal_workspace_id'] ?? null);
			$workspace = $workspaceId === null ? null : ($workspaces[$workspaceId] ?? null);
			if ($workspaceId === null || $workspace === null || trim((string)($workspace['user_id'] ?? '')) !== $userId) {
				self::issue(
					$issues,
					'invalid_member_workspace',
					'cobudget_members',
					(int)($member['id'] ?? 0),
					'Der persoenliche Workspace eines Bereichsmitglieds fehlt oder gehoert einem anderen Benutzer.'
				);
			}
		}

		foreach ($tables['cobudget_entry_shares'] ?? [] as $share) {
			$entryId = self::nullableId($share['entry_id'] ?? null);
			$userId = trim((string)($share['user_id'] ?? ''));
			if ($entryId === null || $userId === '') {
				continue;
			}
			$key = self::shareKey($entryId, $userId);
			if (isset($shares[$key])) {
				self::issue(
					$issues,
					'duplicate_share',
					'cobudget_entry_shares',
					(int)($share['id'] ?? 0),
					'Fuer eine gemeinsame Zahlung ist derselbe Benutzer mehrfach aufgeteilt.'
				);
			}
			$shares[$key] = $share;
			$sharesByEntry[$entryId][] = $share;
		}

		foreach ($projects as $projectId => $project) {
			$workspaceId = self::nullableId($project['workspace_id'] ?? null);
			if ($workspaceId === null || !isset($workspaces[$workspaceId])) {
				self::issue($issues, 'invalid_project_workspace', 'cobudget_projects', $projectId, 'Der Workspace eines Bereichs fehlt.');
			}
			$ownerId = trim((string)($project['owner_id'] ?? ''));
			if ($ownerId === '' || !isset($members[self::memberKey($projectId, $ownerId)])) {
				self::issue($issues, 'missing_project_owner_member', 'cobudget_projects', $projectId, 'Der Bereichsersteller ist nicht als Mitglied hinterlegt.');
			}
		}

		foreach ($entries as $entryId => $entry) {
			$kind = (string)($entry['entry_kind'] ?? 'personal');
			$sourceEntryId = self::nullableId($entry['source_entry_id'] ?? null);
			$projectId = self::nullableId($entry['project_id'] ?? null);
			$userId = trim((string)($entry['user_id'] ?? ''));

			if ($kind === 'shared') {
				if ($sourceEntryId !== null) {
					self::issue($issues, 'shared_has_source', 'cobudget_entries', $entryId, 'Eine gemeinsame Quellzahlung darf selbst keine Quelle besitzen.');
				}
				if ($projectId === null || !isset($projects[$projectId])) {
					self::issue($issues, 'shared_without_project', 'cobudget_entries', $entryId, 'Eine gemeinsame Quellzahlung besitzt keinen gueltigen Bereich.');
					continue;
				}
				$projectWorkspaceId = self::nullableId($projects[$projectId]['workspace_id'] ?? null);
				if (self::nullableId($entry['workspace_id'] ?? null) !== $projectWorkspaceId) {
					self::issue($issues, 'shared_workspace_mismatch', 'cobudget_entries', $entryId, 'Eine gemeinsame Quellzahlung liegt nicht im Workspace ihres Bereichs.');
				}
				if (
					!self::boolValue($entry['is_settled'] ?? false)
					&& ($userId === '' || !isset($members[self::memberKey($projectId, $userId)]))
				) {
					self::issue($issues, 'shared_payer_not_member', 'cobudget_entries', $entryId, 'Der Zahlende einer gemeinsamen Zahlung ist kein Bereichsmitglied.');
				}
				self::validateShareTotal($issues, $entryId, $entry, $sharesByEntry[$entryId] ?? []);
				continue;
			}

			if ($kind !== 'personal') {
				self::issue($issues, 'unknown_entry_kind', 'cobudget_entries', $entryId, 'Die Zahlung besitzt einen unbekannten Eintragstyp.');
				continue;
			}

			if ($sourceEntryId === null) {
				if (self::boolValue($entry['is_locked'] ?? false)) {
					self::issue($issues, 'locked_without_source', 'cobudget_entries', $entryId, 'Eine gesperrte persoenliche Zahlung besitzt keine gemeinsame Quelle.');
				}
				continue;
			}

			$source = $entries[$sourceEntryId] ?? null;
			if ($source === null || (string)($source['entry_kind'] ?? '') !== 'shared') {
				self::issue($issues, 'invalid_projection_source', 'cobudget_entries', $entryId, 'Die Quelle einer persoenlichen Projektion fehlt oder ist nicht gemeinsam.');
				continue;
			}
			$sourceProjectId = self::nullableId($source['project_id'] ?? null);
			if ($projectId !== $sourceProjectId) {
				self::issue($issues, 'projection_project_mismatch', 'cobudget_entries', $entryId, 'Persoenliche Projektion und gemeinsame Quelle gehoeren nicht zum selben Bereich.');
			}
			$share = $shares[self::shareKey($sourceEntryId, $userId)] ?? null;
			if ($share === null || self::nullableId($share['personal_entry_id'] ?? null) !== $entryId) {
				self::issue($issues, 'projection_share_mismatch', 'cobudget_entries', $entryId, 'Die persoenliche Projektion ist nicht eindeutig mit ihrem Zahlungsanteil verknuepft.');
			} elseif (self::amountCents($share) !== self::amountCents($entry)) {
				self::issue($issues, 'projection_amount_mismatch', 'cobudget_entries', $entryId, 'Persoenliche Projektion und gespeicherter Zahlungsanteil haben unterschiedliche Betraege.');
			}

			$sourceSettled = self::boolValue($source['is_settled'] ?? false);
			if (self::boolValue($entry['is_locked'] ?? false) === $sourceSettled) {
				self::issue($issues, 'projection_lock_mismatch', 'cobudget_entries', $entryId, 'Der Sperrstatus der persoenlichen Projektion passt nicht zum Abrechnungsstatus der Quelle.');
			}
			if ($sourceProjectId !== null) {
				$member = $members[self::memberKey($sourceProjectId, $userId)] ?? null;
				if ($member === null) {
					self::issue($issues, 'projection_user_not_member', 'cobudget_entries', $entryId, 'Der Benutzer einer verknuepften Projektion ist kein Bereichsmitglied.');
				} elseif (self::nullableId($entry['workspace_id'] ?? null) !== self::nullableId($member['personal_workspace_id'] ?? null)) {
					self::issue($issues, 'projection_workspace_mismatch', 'cobudget_entries', $entryId, 'Die persoenliche Projektion liegt nicht im gespeicherten Workspace des Mitglieds.');
				}
			}
		}

		foreach ($shares as $share) {
			$shareId = (int)($share['id'] ?? 0);
			$entryId = self::nullableId($share['entry_id'] ?? null);
			$source = $entryId === null ? null : ($entries[$entryId] ?? null);
			if ($source === null || (string)($source['entry_kind'] ?? '') !== 'shared') {
				self::issue($issues, 'share_without_shared_source', 'cobudget_entry_shares', $shareId, 'Ein gespeicherter Zahlungsanteil besitzt keine gemeinsame Quellzahlung.');
				continue;
			}

			$amountCents = self::amountCents($share);
			$personalEntryId = self::nullableId($share['personal_entry_id'] ?? null);
			if ($amountCents <= 0 && $personalEntryId !== null) {
				self::issue($issues, 'zero_share_has_projection', 'cobudget_entry_shares', $shareId, 'Ein Anteil von null darf keine persoenliche Zahlung besitzen.');
				continue;
			}
			if ($amountCents <= 0) {
				continue;
			}
			$sourceSettled = self::boolValue($source['is_settled'] ?? false);
			if ($personalEntryId === null && $sourceSettled) {
				// A reset user may deliberately remove their already-unlocked personal
				// row while the immutable settlement allocation remains as history.
				continue;
			}
			$personal = $personalEntryId === null ? null : ($entries[$personalEntryId] ?? null);
			if ($personal === null || (string)($personal['entry_kind'] ?? '') !== 'personal') {
				self::issue($issues, 'positive_share_without_projection', 'cobudget_entry_shares', $shareId, 'Ein positiver Zahlungsanteil besitzt keine persoenliche Projektion.');
				continue;
			}
			if (trim((string)($personal['user_id'] ?? '')) !== trim((string)($share['user_id'] ?? '')) || self::amountCents($personal) !== $amountCents) {
				self::issue($issues, 'share_projection_mismatch', 'cobudget_entry_shares', $shareId, 'Zahlungsanteil und persoenliche Projektion gehoeren nicht zu demselben Benutzer oder Betrag.');
			}
			if (!$sourceSettled && self::nullableId($personal['source_entry_id'] ?? null) !== $entryId) {
				self::issue($issues, 'open_share_detached', 'cobudget_entry_shares', $shareId, 'Eine offene gemeinsame Zahlung besitzt eine abgetrennte persoenliche Projektion.');
			}
		}

		return $issues;
	}

	public static function assertValid(array $tables): void {
		$issues = self::validate($tables);
		if ($issues === []) {
			return;
		}

		$preview = array_slice(array_map(static fn (array $issue): string => $issue['message'], $issues), 0, 3);
		$suffix = count($issues) > count($preview) ? ' (und weitere)' : '';
		throw new \RuntimeException('Inkonsistenter Zahlungs-Projektionsgraph: ' . implode(' ', $preview) . $suffix);
	}

	private static function validateShareTotal(array &$issues, int $entryId, array $entry, array $shares): void {
		if ($shares === []) {
			self::issue($issues, 'shared_without_shares', 'cobudget_entries', $entryId, 'Eine gemeinsame Zahlung besitzt keine gespeicherte Aufteilung.');
			return;
		}

		$amountTotal = 0;
		$basisPointTotal = 0;
		foreach ($shares as $share) {
			$amountTotal += self::amountCents($share);
			$basisPointTotal += max(0, (int)($share['share_basis_points'] ?? 0));
		}
		if ($amountTotal !== self::amountCents($entry)) {
			self::issue($issues, 'share_total_mismatch', 'cobudget_entries', $entryId, 'Die gespeicherten persoenlichen Anteile ergeben nicht den Gesamtbetrag der gemeinsamen Zahlung.');
		}
		if ($basisPointTotal !== 10000) {
			self::issue($issues, 'share_basis_total_mismatch', 'cobudget_entries', $entryId, 'Die gespeicherten prozentuellen Anteile ergeben nicht 100 Prozent.');
		}
	}

	/** @return array<int, array<string, mixed>> */
	private static function rowsById(array $rows): array {
		$byId = [];
		foreach ($rows as $row) {
			$id = (int)($row['id'] ?? 0);
			if ($id > 0) {
				$byId[$id] = $row;
			}
		}

		return $byId;
	}

	private static function memberKey(int $projectId, string $userId): string {
		return $projectId . '|' . $userId;
	}

	private static function shareKey(int $entryId, string $userId): string {
		return $entryId . '|' . $userId;
	}

	private static function nullableId(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}
		$id = (int)$value;

		return $id > 0 ? $id : null;
	}

	private static function amountCents(array $row): int {
		if (isset($row['amount_cents']) && is_numeric($row['amount_cents'])) {
			return abs((int)$row['amount_cents']);
		}

		return abs((int)round(((float)($row['amount'] ?? 0)) * 100));
	}

	private static function boolValue(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1' || $value === 'true';
	}

	private static function issue(array &$issues, string $code, string $table, int $id, string $message): void {
		$issues[] = [
			'code' => $code,
			'table' => $table,
			'id' => $id,
			'message' => $message,
		];
	}
}

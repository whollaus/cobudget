<?php

declare(strict_types=1);

namespace OCP\AppFramework {
	if (!class_exists(Http::class, false)) {
		final class Http {
			public const STATUS_UNAUTHORIZED = 401;
			public const STATUS_BAD_REQUEST = 400;
			public const STATUS_FORBIDDEN = 403;
			public const STATUS_INTERNAL_SERVER_ERROR = 500;
		}
	}
}

namespace OCP\AppFramework\Http {
	if (!class_exists(DataResponse::class, false)) {
		final class DataResponse {
			/** @param mixed $data */
			public function __construct(
				private $data = null,
				private int $status = 200
			) {
			}

			/** @return mixed */
			public function getData() {
				return $this->data;
			}

			public function getStatus(): int {
				return $this->status;
			}
		}
	}
}

namespace CoBudget\Tests {
	require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceAwareTrait.php';

	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\WorkspaceAwareTrait;
	use OCP\AppFramework\Http\DataResponse;

	final class WorkspaceAwareTraitProbe {
		use WorkspaceAwareTrait;

		/** @var object{getHeader: callable} */
		public object $request;
		/** @var list<int> */
		private array $ownedWorkspaceIds;
		protected ?string $userId;

		/**
		 * @param list<int> $ownedWorkspaceIds
		 */
		public function __construct(?string $userId, string $workspaceHeader = '', array $ownedWorkspaceIds = []) {
			$this->userId = $userId;
			$this->ownedWorkspaceIds = $ownedWorkspaceIds;
			$this->request = new class($workspaceHeader) {
				public function __construct(private string $workspaceHeader) {
				}

				public function getHeader(string $name): string {
					return $name === 'X-Workspace-Id' ? $this->workspaceHeader : '';
				}
			};
		}

		public function runInitWorkspace(): void {
			$this->initWorkspace();
		}

		public function currentWorkspaceId(): ?int {
			return $this->workspaceId;
		}

		public function currentHeaderError(): ?DataResponse {
			return $this->workspaceHeaderErrorResponse;
		}

		public function currentAuthError(): ?DataResponse {
			return $this->authErrorResponse();
		}

			public function requiredName(string $name, int $maxLength = 128): ?string {
				return $this->normalizeRequiredName($name, $maxLength);
			}

			public function requiredNameError(string &$name, string $message = 'Name is required'): ?DataResponse {
				return $this->validateRequiredName($name, $message);
			}

			public function requiredStringError(string &$value, string $message = 'Value is required', int $maxLength = 255): ?DataResponse {
				return $this->validateRequiredString($value, $message, $maxLength);
			}

			public function positiveIdError(int $id): ?DataResponse {
				return $this->validatePositiveId($id);
			}

			public function optionalString(?string $value, int $maxLength = 1024): string {
				return $this->normalizeOptionalString($value, $maxLength);
			}

			public function entryTypeError(string $type): ?DataResponse {
				return $this->validateEntryType($type);
			}

			public function validEntryType(string $type): bool {
				return $this->isValidEntryType($type);
			}

		/** @param mixed $amount */
		public function validAmount($amount): bool {
			return $this->isValidAmount($amount);
		}

		/** @param mixed $amount */
			public function toCents($amount): ?int {
				return $this->amountToCents($amount);
			}

			/** @param mixed $amount */
			public function amountCentsError($amount, ?int &$amountCents, bool $allowNull = false): ?DataResponse {
				return $this->validateAmountCents($amount, $amountCents, $allowNull);
			}

			/** @param mixed $cents */
			public function toAmount($cents): float {
				return $this->centsToAmount($cents);
			}

		public function toAmountString(?int $cents): ?string {
			return $this->centsToAmountString($cents);
		}

		public function centsFromRow(array $row): ?int {
			return $this->amountCentsFromRow($row);
		}

			public function normalizedAmountRow(array $row): array {
				return $this->normalizeAmountRow($row);
			}

			public function requiredTimestampError(int $timestamp): ?DataResponse {
				return $this->validateRequiredTimestamp($timestamp, 'Invalid timestamp');
			}

			public function optionalTimestampError(?int $timestamp): ?DataResponse {
				return $this->validateOptionalTimestamp($timestamp, 'Invalid optional timestamp');
			}

			public function choiceError(?string $value): ?DataResponse {
				return $this->validateOptionalChoice($value, ['personal', 'currentYear'], 'Invalid choice');
			}

			public function typedNameError(string &$name, string $type): ?DataResponse {
				return $this->validateTypedNamePayload($name, $type);
			}

			public function currencyError(string &$currency): ?DataResponse {
				return $this->validateCurrencySetting($currency);
			}

			public function defaultStartPageError(?string $defaultStartPage): ?DataResponse {
				return $this->validateDefaultStartPage($defaultStartPage);
			}

			public function entriesPerPageError(?int $entriesPerPage): ?DataResponse {
				return $this->validateEntriesPerPage($entriesPerPage);
			}

			public function splitMode(?string $splitMode): string {
				return $this->normalizeSplitMode($splitMode);
			}

			public function splitModeError(?string &$splitMode): ?DataResponse {
				return $this->validateSplitMode($splitMode);
			}

			public function memberShares(array $members): array {
				return $this->memberShareBasisPoints($members);
			}

			public function distributeCents(int $amountCents, array $shareBasisPointsByUserId): array {
				return $this->distributeAmountCents($amountCents, $shareBasisPointsByUserId);
			}

			public function entryShare(array $entry, string $userId, int $amountCents, array $shareBasisPointsByUserId): int {
				return $this->entryShareCentsForUser($entry, $userId, $amountCents, $shareBasisPointsByUserId);
			}

			public function userIds(array $userIds): array {
				return $this->normalizeUserIdList($userIds);
			}

			public function userIdError(string &$userId): ?DataResponse {
				return $this->validateRequiredUserId($userId);
			}

			protected function workspaceBelongsToUser(int $workspaceId): bool {
				return in_array($workspaceId, $this->ownedWorkspaceIds, true);
			}

		protected function findDefaultWorkspaceId(): ?int {
			return null;
		}

		protected function createDefaultWorkspace(): ?int {
			return null;
		}

		protected function assignUnscopedRowsToWorkspace(int $workspaceId): void {
		}
	}

	return [
		'Workspace header accepts only owned positive numeric ids' => function(TestRunner $t): void {
			$valid = new WorkspaceAwareTraitProbe('user-a', '12', [12]);
			$valid->runInitWorkspace();
			$t->assertSame(12, $valid->currentWorkspaceId(), 'Valid owned workspace header should become active workspace');
			$t->assertNull($valid->currentHeaderError(), 'Valid owned workspace header should not create an error response');

			$invalid = new WorkspaceAwareTraitProbe('user-a', 'abc', [12]);
			$invalid->runInitWorkspace();
			$t->assertSame(400, $invalid->currentHeaderError()?->getStatus(), 'Non-numeric workspace header should return 400');
			$t->assertSame(['error' => 'Invalid workspace id'], $invalid->currentHeaderError()?->getData(), 'Invalid workspace header should return JSON error data');

			$foreign = new WorkspaceAwareTraitProbe('user-a', '99', [12]);
			$foreign->runInitWorkspace();
			$t->assertSame(403, $foreign->currentHeaderError()?->getStatus(), 'Foreign workspace header should return 403');
			$t->assertSame(['error' => 'Workspace not found or no permission'], $foreign->currentHeaderError()?->getData(), 'Foreign workspace header should return JSON error data');
		},

		'Workspace auth errors are JSON DataResponses' => function(TestRunner $t): void {
			$anonymous = new WorkspaceAwareTraitProbe(null, '', []);
			$error = $anonymous->currentAuthError();
			$t->assertNotNull($error, 'Anonymous user should receive auth error response');
			$t->assertSame(401, $error?->getStatus(), 'Anonymous user should receive 401 status');
			$t->assertSame(['error' => 'Not authenticated'], $error?->getData(), 'Anonymous user should receive JSON error data');
		},

		'Names are trimmed, required, and length-limited' => function(TestRunner $t): void {
			$probe = new WorkspaceAwareTraitProbe('user-a');
			$t->assertSame('Privat', $probe->requiredName('  Privat  '), 'Required names should be trimmed');
			$t->assertNull($probe->requiredName('   '), 'Blank required names should be rejected');
			$t->assertSame(str_repeat('a', 8), $probe->requiredName(str_repeat('a', 12), 8), 'Required names should be truncated to max length');

			$name = '  Arbeit  ';
			$t->assertNull($probe->requiredNameError($name), 'Valid required name should not produce an error');
			$t->assertSame('Arbeit', $name, 'Required name validation should normalize by reference');

			$blankName = '  ';
			$error = $probe->requiredNameError($blankName, 'Name cannot be empty');
			$t->assertSame(400, $error?->getStatus(), 'Blank required names should return 400');
			$t->assertSame(['error' => 'Name cannot be empty'], $error?->getData(), 'Blank required names should return configured JSON error');

			$text = '  abcdef  ';
			$t->assertNull($probe->requiredStringError($text, 'Text required', 4), 'Required text should validate');
			$t->assertSame('abcd', $text, 'Required text should be trimmed and length-limited');
			$t->assertSame('abc', $probe->optionalString('  abcdef  ', 3), 'Optional strings should be trimmed and length-limited');
		},

		'Entry types and amounts are validated centrally' => function(TestRunner $t): void {
			$probe = new WorkspaceAwareTraitProbe('user-a');
			$t->assertTrue($probe->validEntryType('expense'), 'expense should be a valid entry type');
			$t->assertTrue($probe->validEntryType('income'), 'income should be a valid entry type');
			$t->assertFalse($probe->validEntryType('transfer'), 'unknown entry types should be rejected');
			$t->assertNull($probe->entryTypeError('expense'), 'Valid entry type should not produce an error response');
			$t->assertSame(['error' => 'Ungültiger Typ'], $probe->entryTypeError('transfer')?->getData(), 'Invalid entry type should use shared JSON error');

			$t->assertTrue($probe->validAmount('0'), 'Zero amount should be valid');
			$t->assertTrue($probe->validAmount('99999999.99'), 'Configured max amount should be valid');
			$t->assertFalse($probe->validAmount('-0.01'), 'Negative amount should be rejected');
			$t->assertFalse($probe->validAmount('100000000.00'), 'Amounts above configured max should be rejected');
			$t->assertFalse($probe->validAmount('not-a-number'), 'Non-numeric amount should be rejected');

			$amountCents = null;
			$t->assertNull($probe->amountCentsError('12.30', $amountCents), 'Valid amount should not produce an error response');
			$t->assertSame(1230, $amountCents, 'Amount validation should return integer cents by reference');
			$t->assertSame(['error' => 'Ungültiger Betrag'], $probe->amountCentsError('-1', $amountCents)?->getData(), 'Invalid amount should use shared JSON error');
			$t->assertNull($probe->amountCentsError(null, $amountCents, true), 'Optional amount should allow null');
			$t->assertNull($amountCents, 'Optional null amount should produce null cents');
		},

		'Split modes and member shares stay deterministic' => function(TestRunner $t): void {
			$probe = new WorkspaceAwareTraitProbe('user-a');
			$t->assertSame('project_shares', $probe->splitMode(null), 'Empty split mode should default to area shares');
			$t->assertSame('single_user', $probe->splitMode('single_user'), 'single_user split mode should be preserved');

			$mode = '';
			$t->assertNull($probe->splitModeError($mode), 'Empty split mode should validate');
			$t->assertSame('project_shares', $mode, 'Empty split mode should normalize by reference');
			$mode = 'single_user';
			$t->assertNull($probe->splitModeError($mode), 'single_user split mode should validate');
			$t->assertSame('single_user', $mode, 'single_user split mode should remain unchanged');
			$mode = 'everyone';
			$t->assertSame(['error' => 'Ungültige Aufteilung'], $probe->splitModeError($mode)?->getData(), 'Unknown split modes should return a shared JSON error');

			$t->assertSame(
				['user-a' => 3400, 'user-b' => 3300, 'user-c' => 3300],
				$probe->memberShares([
					['id' => 'user-a'],
					['user_id' => 'user-b'],
					['userId' => 'user-c'],
				]),
				'Missing member shares should be distributed as whole percentages'
			);
			$t->assertSame(
				['user-a' => 5100, 'user-b' => 4900],
				$probe->memberShares([
					['id' => 'user-a', 'share_basis_points' => 5058],
					['id' => 'user-b', 'share_basis_points' => 4942],
				]),
				'Fractional configured shares should normalize to whole percentages'
			);
			$t->assertSame(
				['user-a' => 33, 'user-b' => 33, 'user-c' => 34],
				$probe->distributeCents(100, ['user-a' => 3333, 'user-b' => 3333, 'user-c' => 3334]),
				'Cent distribution should keep the remainder on the final member'
			);
			$t->assertSame(1000, $probe->entryShare(['split_mode' => 'single_user', 'user_id' => 'user-a'], 'user-a', 1000, ['user-a' => 5000, 'user-b' => 5000]), 'Single-user entries should belong fully to the selected user');
			$t->assertSame(0, $probe->entryShare(['split_mode' => 'single_user', 'user_id' => 'user-a'], 'user-b', 1000, ['user-a' => 5000, 'user-b' => 5000]), 'Single-user entries should not count for other members');
			$t->assertSame(500, $probe->entryShare(['split_mode' => 'project_shares', 'user_id' => 'user-a'], 'user-b', 1000, ['user-a' => 5000, 'user-b' => 5000]), 'Area-share entries should use configured member percentages');
		},

		'Money helpers prefer integer cents and normalize API rows' => function(TestRunner $t): void {
			$probe = new WorkspaceAwareTraitProbe('user-a');
			$t->assertSame(1230, $probe->toCents('12.30'), 'Decimal amount should convert to cents');
			$t->assertSame(1235, $probe->toCents('12.345'), 'Decimal amount should round to cents');
			$t->assertSame(0, $probe->toCents(0), 'Zero should convert to zero cents');
			$t->assertSame(12.35, $probe->toAmount(1235), 'Cents should convert to rounded amount');
			$t->assertSame('12.35', $probe->toAmountString(1235), 'Cents should convert to canonical decimal string');
			$t->assertNull($probe->toAmountString(null), 'Null cents should stay null for optional template amounts');

			$t->assertSame(999, $probe->centsFromRow(['amount_cents' => '999', 'amount' => '1.23']), 'amount_cents should be canonical when present');
			$t->assertSame(124, $probe->centsFromRow(['amount' => '1.235']), 'Legacy amount should still be converted as fallback');

			$normalized = $probe->normalizedAmountRow(['id' => 7, 'amount_cents' => 1234, 'amount' => '99.99']);
			$t->assertSame(['id' => 7, 'amount' => 12.34], $normalized, 'API rows should expose amount and hide amount_cents');
		},

		'Timestamps, choices, settings, and user ids are validated centrally' => function(TestRunner $t): void {
			$probe = new WorkspaceAwareTraitProbe('user-a');
			$t->assertNull($probe->positiveIdError(1), 'Positive ids should be valid');
			$t->assertSame(['error' => 'Invalid id'], $probe->positiveIdError(0)?->getData(), 'Zero ids should return shared JSON error');
			$t->assertNull($probe->requiredTimestampError(1), 'Positive required timestamp should be valid');
			$t->assertSame(['error' => 'Invalid timestamp'], $probe->requiredTimestampError(0)?->getData(), 'Invalid required timestamp should return JSON error');
			$t->assertNull($probe->optionalTimestampError(null), 'Null optional timestamp should be valid');
			$t->assertSame(['error' => 'Invalid optional timestamp'], $probe->optionalTimestampError(-1)?->getData(), 'Invalid optional timestamp should return JSON error');

			$t->assertNull($probe->choiceError(null), 'Null optional choice should be valid');
			$t->assertNull($probe->choiceError('personal'), 'Allowed choice should be valid');
			$t->assertSame(['error' => 'Invalid choice'], $probe->choiceError('dashboard')?->getData(), 'Unknown choice should return JSON error');

			$name = '  Kategorie  ';
			$t->assertNull($probe->typedNameError($name, 'expense'), 'Typed name payload should validate name and type');
			$t->assertSame('Kategorie', $name, 'Typed name payload should normalize the name');
			$invalidName = 'Kategorie';
			$t->assertSame(['error' => 'Ungültiger Typ'], $probe->typedNameError($invalidName, 'transfer')?->getData(), 'Typed name payload should reject invalid type');

			$currency = '  EUR  ';
			$t->assertNull($probe->currencyError($currency), 'Short currency setting should be valid');
			$t->assertSame('EUR', $currency, 'Currency setting should be trimmed');
			$longCurrency = str_repeat('E', 17);
			$t->assertSame(['error' => 'Invalid currency'], $probe->currencyError($longCurrency)?->getData(), 'Long currency should be rejected');
			$t->assertNull($probe->defaultStartPageError('currentYear'), 'Allowed default start page should be valid');
			$t->assertNull($probe->defaultStartPageError('projects'), 'Project list default start page should be valid');
			$t->assertNull($probe->defaultStartPageError('project:42'), 'Specific project default start page should be valid');
			$t->assertSame(['error' => 'Invalid default start page'], $probe->defaultStartPageError('project:0')?->getData(), 'Invalid project default start page should be rejected');
			$t->assertSame(['error' => 'Invalid default start page'], $probe->defaultStartPageError('dashboard')?->getData(), 'Unknown default start page should be rejected');
			$t->assertNull($probe->entriesPerPageError(25), 'Allowed entry page size should be valid');
			$t->assertSame(['error' => 'Invalid entries per page'], $probe->entriesPerPageError(500)?->getData(), 'Unknown entry page size should be rejected');

			$t->assertSame(['user-a', 'user-b'], $probe->userIds([' user-a ', '', 'user-a', 'user-b']), 'User id lists should trim, deduplicate, and drop blanks');
			$userId = '  user-c  ';
			$t->assertNull($probe->userIdError($userId), 'Required user id should validate');
			$t->assertSame('user-c', $userId, 'Required user id should be normalized by reference');
		},
	];
}

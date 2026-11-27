<?php

declare(strict_types=1);

namespace OCP\AppFramework {
	if (!class_exists(Controller::class, false)) {
		class Controller {
			public mixed $request;

			public function __construct(
				public string $appName = '',
				?\OCP\IRequest $request = null,
			) {
				$this->request = $request;
			}
		}
	}

	if (!class_exists(Http::class, false)) {
		final class Http {
			public const STATUS_OK = 200;
			public const STATUS_CREATED = 201;
			public const STATUS_BAD_REQUEST = 400;
			public const STATUS_UNAUTHORIZED = 401;
			public const STATUS_FORBIDDEN = 403;
			public const STATUS_NOT_FOUND = 404;
			public const STATUS_CONFLICT = 409;
			public const STATUS_INTERNAL_SERVER_ERROR = 500;
		}
	}
}

namespace OCP\AppFramework\Http {
	if (!class_exists(Response::class, false)) {
		class Response {
		}
	}
	if (!class_exists(DataResponse::class, false)) {
		class DataResponse extends Response {
			public function __construct(
				private mixed $data = [],
				private int $status = 200,
			) {
			}

			public function getData(): mixed {
				return $this->data;
			}

			public function getStatus(): int {
				return $this->status;
			}
		}
	}
}

namespace OCP\AppFramework\Http\Attribute {
	if (!class_exists(NoAdminRequired::class, false)) {
		#[\Attribute]
		final class NoAdminRequired {
		}
	}
}

namespace OCP {
	if (!interface_exists(IRequest::class, false)) {
		interface IRequest {
		}
	}
	if (!interface_exists(IDBConnection::class, false)) {
		interface IDBConnection {
		}
	}
	if (!interface_exists(IUser::class, false)) {
		interface IUser {
		}
	}
	if (!interface_exists(IUserSession::class, false)) {
		interface IUserSession {
		}
	}
	if (!interface_exists(IConfig::class, false)) {
		interface IConfig {
			public function getAppValue(string $appName, string $key, string $default = ''): string;
		}
	}
	if (!interface_exists(IL10N::class, false)) {
		interface IL10N {
			public function t(string $text, array $parameters = []): string;
		}
	}
	if (!interface_exists(IGroupManager::class, false)) {
		interface IGroupManager {
			public function isAdmin(string $userId): bool;
		}
	}
}

namespace OCP\DB\QueryBuilder {
	if (!interface_exists(IQueryBuilder::class, false)) {
		interface IQueryBuilder {
		}
	}
}

namespace CoBudget\Tests {
	require_once dirname(__DIR__, 2) . '/lib/Controller/WorkspaceAwareTrait.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/CategoryController.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/PaymentPartnerController.php';
	require_once dirname(__DIR__, 2) . '/lib/Controller/MasterDataController.php';

	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Controller\CategoryController;
	use OCA\CoBudget\Controller\MasterDataController;
	use OCA\CoBudget\Controller\PaymentPartnerController;
	use OCP\AppFramework\Http\DataResponse;
	use OCP\IConfig;
	use OCP\IDBConnection;
	use OCP\IGroupManager;
	use OCP\IL10N;
	use OCP\IRequest;
	use OCP\IUser;
	use OCP\IUserSession;

	final class MasterDataTestDb implements IDBConnection {
		public int $begins = 0;
		public int $commits = 0;
		public int $rollBacks = 0;

		public function beginTransaction(): void {
			$this->begins++;
		}

		public function commit(): void {
			$this->commits++;
		}

		public function rollBack(): void {
			$this->rollBacks++;
		}
	}

	final class MasterDataTestRequest implements IRequest {
		public function getHeader(string $name): string {
			return '';
		}
	}

	final class MasterDataTestUser implements IUser {
		public function __construct(private string $uid) {
		}

		public function getUID(): string {
			return $this->uid;
		}

		public function getDisplayName(): string {
			return $this->uid;
		}
	}

	final class MasterDataTestUserSession implements IUserSession {
		public function __construct(private ?IUser $user) {
		}

		public function getUser(): ?IUser {
			return $this->user;
		}
	}

	final class MasterDataTestConfig implements IConfig {
		public function getAppValue(string $appName, string $key, string $default = ''): string {
			return $default;
		}
	}

	final class MasterDataTestL10n implements IL10N {
		public function t(string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf($text, $parameters);
		}
	}

	final class MasterDataTestGroupManager implements IGroupManager {
		public function __construct(private bool $admin) {
		}

		public function isAdmin(string $userId): bool {
			return $this->admin;
		}
	}

	final class RecordingCategoryController extends CategoryController {
		/** @var list<array{method: string, args: array<int, mixed>}> */
		public array $calls = [];
		/** @var list<array<string, mixed>> */
		public array $existing = [];
		public int $nextId = 101;
		public ?DataResponse $forcedError = null;
		public ?string $failOnMethod = null;

		public function adminIndex(): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => []];
			return new DataResponse($this->existing);
		}

		public function adminCreate(string $name = '', string $icon = 'Shape', string $type = 'expense'): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$name, $icon, $type]];
			if ($this->failOnMethod === __FUNCTION__ && $this->forcedError !== null) {
				return $this->forcedError;
			}
			return new DataResponse(['id' => $this->nextId++, 'name' => $name, 'icon' => $icon, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
		}

		public function adminUpdate(int $id, string $name = '', ?string $code = null, ?int $parentCategoryId = null): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id, $name, $code, $parentCategoryId]];
			if ($this->failOnMethod === __FUNCTION__ && $this->forcedError !== null) {
				return $this->forcedError;
			}
			return new DataResponse(['id' => $id, 'name' => $name, 'code' => $code, 'parent_category_id' => $parentCategoryId]);
		}

		public function adminUpdateIcon(int $id, string $icon): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id, $icon]];
			return new DataResponse(['status' => 'success']);
		}

		public function adminHide(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}

		public function adminUnhide(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}

		public function adminDestroy(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}
	}

	final class RecordingPaymentPartnerController extends PaymentPartnerController {
		/** @var list<array{method: string, args: array<int, mixed>}> */
		public array $calls = [];
		/** @var list<array<string, mixed>> */
		public array $existing = [];
		public int $nextId = 201;
		public ?DataResponse $forcedError = null;
		public ?string $failOnMethod = null;

		public function adminIndex(): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => []];
			return new DataResponse($this->existing);
		}

		public function adminCreate(string $name = '', string $type = 'expense'): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$name, $type]];
			if ($this->failOnMethod === __FUNCTION__ && $this->forcedError !== null) {
				return $this->forcedError;
			}
			return new DataResponse(['id' => $this->nextId++, 'name' => $name, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
		}

		public function adminUpdate(int $id, string $name = '', ?array $details = null): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id, $name, $details]];
			if ($this->failOnMethod === __FUNCTION__ && $this->forcedError !== null) {
				return $this->forcedError;
			}
			return new DataResponse(['id' => $id, 'name' => $name]);
		}

		public function adminHide(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}

		public function adminUnhide(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}

		public function adminDestroy(int $id): DataResponse {
			$this->calls[] = ['method' => __FUNCTION__, 'args' => [$id]];
			return new DataResponse(['status' => 'success']);
		}
	}

	$makeMasterDataHarness = static function(?string $userId, bool $isAdmin): array {
		$request = new MasterDataTestRequest();
		$db = new MasterDataTestDb();
		$session = new MasterDataTestUserSession($userId === null ? null : new MasterDataTestUser($userId));
		$config = new MasterDataTestConfig();
		$l10n = new MasterDataTestL10n();
		$groups = new MasterDataTestGroupManager($isAdmin);
		$categories = new RecordingCategoryController('cobudget', $request, $db, $session, $config, $l10n, $groups);
		$partners = new RecordingPaymentPartnerController('cobudget', $request, $db, $session, $config, $l10n, $groups);
		$controller = new MasterDataController('cobudget', $request, $db, $session, $config, $l10n, $groups, $categories, $partners);
		return [$controller, $db, $categories, $partners];
	};

	return [
		'MasterDataController::adminSave rejects unauthenticated and non-admin callers before starting a transaction' => function(TestRunner $t) use ($makeMasterDataHarness): void {
			[$anonController, $anonDb] = $makeMasterDataHarness(null, false);
			$anonResponse = $anonController->adminSave([['kind' => 'categories', 'action' => 'create', 'ref' => '@entry0', 'name' => 'Global Test', 'type' => 'expense']]);
			$t->assertSame(401, $anonResponse->getStatus(), 'Unauthenticated adminSave should return 401');
			$t->assertSame(0, $anonDb->begins, 'Unauthenticated adminSave must not begin a DB transaction');

			[$userController, $userDb] = $makeMasterDataHarness('alice', false);
			$userResponse = $userController->adminSave([['kind' => 'payment-partners', 'action' => 'update', 'id' => 5, 'name' => 'Edited']]);
			$t->assertSame(403, $userResponse->getStatus(), 'Non-admin adminSave should return 403');
			$t->assertSame(0, $userDb->begins, 'Non-admin adminSave must not begin a DB transaction');
		},

		'MasterDataController::adminSave creates, edits, hides, and deletes global categories via admin controller methods' => function(TestRunner $t) use ($makeMasterDataHarness): void {
			[$controller, $db, $categories] = $makeMasterDataHarness('admin', true);

			$createResponse = $controller->adminSave([
				['kind' => 'categories', 'action' => 'create', 'ref' => '@entry0', 'name' => 'Global Travel', 'icon' => 'Train', 'type' => 'expense'],
				['kind' => 'categories', 'action' => 'update', 'id' => '@entry0', 'name' => 'Global Travel', 'code' => '6400', 'parentCategoryId' => 0],
				['kind' => 'categories', 'action' => 'updateIcon', 'id' => '@entry0', 'icon' => 'Airplane'],
				['kind' => 'categories', 'action' => 'unhide', 'id' => '@entry0'],
			]);
			$t->assertSame(200, $createResponse->getStatus(), 'Admin global category creation should succeed');
			$t->assertSame(1, $db->begins, 'Create batch should open one transaction');
			$t->assertSame(1, $db->commits, 'Create batch should commit once');
			$t->assertSame(0, $db->rollBacks, 'Create batch should not roll back');
			$t->assertSame([
				['method' => 'adminIndex', 'args' => []],
				['method' => 'adminCreate', 'args' => ['Global Travel', 'Train', 'expense']],
				['method' => 'adminUpdate', 'args' => [101, 'Global Travel', '6400', 0]],
				['method' => 'adminUpdateIcon', 'args' => [101, 'Airplane']],
				['method' => 'adminUnhide', 'args' => [101]],
			], $categories->calls, 'Create batch should invoke global category admin methods with resolved ID');

			$categories->calls = [];
			$editResponse = $controller->adminSave([
				['kind' => 'categories', 'action' => 'update', 'id' => 101, 'name' => 'Global Business Travel', 'code' => '6410', 'parentCategoryId' => 12],
				['kind' => 'categories', 'action' => 'updateIcon', 'id' => 101, 'icon' => 'Briefcase'],
				['kind' => 'categories', 'action' => 'hide', 'id' => 101],
			]);
			$t->assertSame(200, $editResponse->getStatus(), 'Admin global category edit should succeed');
			$t->assertSame([
				['method' => 'adminUpdate', 'args' => [101, 'Global Business Travel', '6410', 12]],
				['method' => 'adminUpdateIcon', 'args' => [101, 'Briefcase']],
				['method' => 'adminHide', 'args' => [101]],
			], $categories->calls, 'Edit batch should invoke global category update, icon, and hide methods');

			$categories->calls = [];
			$deleteResponse = $controller->adminSave([
				['kind' => 'categories', 'action' => 'destroy', 'id' => 101],
			]);
			$t->assertSame(200, $deleteResponse->getStatus(), 'Admin global category delete should succeed');
			$t->assertSame([
				['method' => 'adminDestroy', 'args' => [101]],
			], $categories->calls, 'Delete batch should invoke adminDestroy');
			$t->assertSame(3, $db->commits, 'All three adminSave batches should commit');
		},

		'MasterDataController::adminSave creates, edits, hides, and deletes global payment partners and rolls back on failure' => function(TestRunner $t) use ($makeMasterDataHarness): void {
			[$controller, $db, , $partners] = $makeMasterDataHarness('admin', true);

			$createResponse = $controller->adminSave([
				['kind' => 'payment-partners', 'action' => 'create', 'ref' => '@entry0', 'name' => 'Global Utility', 'type' => 'expense'],
				['kind' => 'payment-partners', 'action' => 'update', 'id' => '@entry0', 'name' => 'Global Utility', 'details' => ['iban' => 'DE89370400440532013000', 'note' => 'Main']],
				['kind' => 'payment-partners', 'action' => 'unhide', 'id' => '@entry0'],
			]);
			$t->assertSame(200, $createResponse->getStatus(), 'Admin global payment partner creation should succeed');
			$t->assertSame([
				['method' => 'adminIndex', 'args' => []],
				['method' => 'adminCreate', 'args' => ['Global Utility', 'expense']],
				['method' => 'adminUpdate', 'args' => [201, 'Global Utility', ['iban' => 'DE89370400440532013000', 'note' => 'Main']]],
				['method' => 'adminUnhide', 'args' => [201]],
			], $partners->calls, 'Create batch should invoke global payment partner admin methods');

			$partners->calls = [];
			$editResponse = $controller->adminSave([
				['kind' => 'payment-partners', 'action' => 'update', 'id' => 201, 'name' => 'Global Utility GmbH', 'details' => ['email' => 'billing@example.com']],
				['kind' => 'payment-partners', 'action' => 'destroy', 'id' => 201],
			]);
			$t->assertSame(200, $editResponse->getStatus(), 'Admin global payment partner edit and delete should succeed');
			$t->assertSame([
				['method' => 'adminUpdate', 'args' => [201, 'Global Utility GmbH', ['email' => 'billing@example.com']]],
				['method' => 'adminDestroy', 'args' => [201]],
			], $partners->calls, 'Edit and delete batch should invoke adminUpdate and adminDestroy');

			$partners->failOnMethod = 'adminUpdate';
			$partners->forcedError = new DataResponse(['error' => 'Invalid email address'], 400);
			$failedResponse = $controller->adminSave([
				['kind' => 'payment-partners', 'action' => 'create', 'ref' => '@entry0', 'name' => 'Rollback Partner', 'type' => 'expense'],
				['kind' => 'payment-partners', 'action' => 'update', 'id' => '@entry0', 'name' => 'Rollback Partner', 'details' => ['email' => 'invalid']],
			]);
			$t->assertSame(400, $failedResponse->getStatus(), 'Sub-operation error status should be propagated');
			$t->assertSame(['error' => 'Invalid email address'], $failedResponse->getData(), 'Sub-operation error payload should be returned');
			$t->assertSame(2, $db->commits, 'Failed adminSave must not increment commits');
			$t->assertSame(1, $db->rollBacks, 'Failed adminSave must roll back the transaction');
		},
	];
}

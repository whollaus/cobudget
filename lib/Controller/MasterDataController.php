<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IGroupManager;

/** Atomic adapter for the existing, scope-checked master-data operations. */
class MasterDataController extends Controller {
	use WorkspaceAwareTrait;
	private ?string $userId;
	public function __construct(string $appName, IRequest $request,
		private IDBConnection $db,
		IUserSession $userSession, private IConfig $config, private IL10N $l10n, private IGroupManager $groupManager,
		private CategoryController $categories,
		private PaymentPartnerController $partners) {
		parent::__construct($appName, $request);
		$this->userId = $userSession->getUser()?->getUID();
		$this->initWorkspace();
	}

	#[NoAdminRequired]
	public function save(array $operations = []): DataResponse {
		if ($error = $this->authErrorResponse()) { return $error; }
		return $this->apply($operations, false);
	}

	public function adminSave(array $operations = []): DataResponse {
		if ($error = $this->requireAdmin()) { return $error; }
		return $this->apply($operations, true);
	}

	private function apply(array $operations, bool $admin): DataResponse {
		if (count($operations) > 250) {
			return $this->errorResponse('Changes could not be saved.', 400);
		}
		$references = [];
		$this->db->beginTransaction();
		try {
			foreach ($operations as $operation) {
				if (!is_array($operation)) { throw new \InvalidArgumentException('Invalid change.'); }
				$kind = $operation['kind'] ?? '';
				if (!in_array($kind, ['categories', 'payment-partners'], true)) {
					throw new \InvalidArgumentException('Invalid entry type.');
				}
				$controller = $kind === 'categories' ? $this->categories : $this->partners;
				$action = $operation['action'] ?? '';
				$resolve = static function (mixed $value) use (&$references): int {
					if (is_string($value) && str_starts_with($value, '@')) {
						if (!isset($references[$value])) { throw new \InvalidArgumentException('Invalid reference.'); }
						return $references[$value];
					}
					return (int)$value;
				};
				$id = $resolve($operation['id'] ?? 0);
				$projectId = empty($operation['projectId']) ? null : (int)$operation['projectId'];
				$name = (string)($operation['name'] ?? '');
				$type = (string)($operation['type'] ?? 'expense');
				$method = $admin ? 'admin' . ucfirst($action) : $action;
				switch ($action) {
					case 'create':
						$catalog = $admin ? $controller->adminIndex() : $controller->settingsData($projectId);
						if ($catalog->getStatus() >= 400) { $this->db->rollBack(); return $catalog; }
						foreach ($catalog->getData() as $existing) {
							if (($existing['type'] ?? '') === $type && $this->normalizeVisibleName($existing['name']) === $this->normalizeVisibleName($name)
								&& empty($operation['reuse'])) {
								$this->db->rollBack();
								return $this->errorResponse($kind === 'categories' ? 'A category with this name already exists.' : 'A payment partner with this name already exists.', 409);
							}
						}
						$args = $kind === 'categories' ? [$name, (string)($operation['icon'] ?? 'Shape'), $type] : [$name, $type];
						if (!$admin) { $args[] = $projectId; }
						$response = $controller->$method(...$args);
						break;
					case 'update':
						$response = $kind === 'categories'
							? $controller->$method($id, $name, $operation['code'] ?? null,
								isset($operation['parentCategoryId']) ? $resolve($operation['parentCategoryId']) : null)
							: $controller->$method($id, $name, $operation['details'] ?? []);
						break;
					case 'updateIcon':
						if ($kind !== 'categories') { throw new \InvalidArgumentException('Invalid change.'); }
						$response = $controller->$method($id, (string)($operation['icon'] ?? 'Shape'));
						break;
					case 'hide':
					case 'unhide':
						$response = $admin ? $controller->$method($id) : $controller->$method($id, $projectId);
						break;
					case 'destroy':
						$response = $controller->$method($id);
						break;
					default: throw new \InvalidArgumentException('Invalid change.');
				}
				if ($response->getStatus() >= 400) {
					$this->db->rollBack();
					return $response;
				}
				if ($action === 'create') {
					$reference = (string)($operation['ref'] ?? '');
					if ($reference === '' || isset($references[$reference])) { throw new \InvalidArgumentException('Invalid reference.'); }
					$references[$reference] = (int)$response->getData()['id'];
				}
			}
			$this->db->commit();
			return new DataResponse(['status' => 'success']);
		} catch (\Throwable $e) {
			$this->db->rollBack();
			return $e instanceof \InvalidArgumentException
				? $this->errorResponse('Changes could not be saved.', 400)
				: $this->loggedErrorResponse($e, 'Changes could not be saved.');
		}
	}
}

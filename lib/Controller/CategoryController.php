<?php
namespace OCA\CoBudget\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IGroupManager;
use OCP\AppFramework\Http;

class CategoryController extends Controller {
	use WorkspaceAwareTrait;

	private const DEFAULT_CATEGORIES_SEEDED_KEY = 'default_categories_seeded';
	private const DEFAULT_GLOBAL_CATEGORIES = [
		['type' => 'income', 'name' => 'Salary', 'icon' => 'Briefcase'],
		['type' => 'income', 'name' => 'Refunds', 'icon' => 'Cash'],
		['type' => 'income', 'name' => 'Sales', 'icon' => 'Cart'],
		['type' => 'income', 'name' => 'Gifts', 'icon' => 'Gift'],
		['type' => 'income', 'name' => 'Interest', 'icon' => 'Bank'],
		['type' => 'income', 'name' => 'Other income', 'icon' => 'Shape'],
		['type' => 'expense', 'name' => 'Groceries', 'icon' => 'FoodApple'],
		['type' => 'expense', 'name' => 'Restaurants and delivery', 'icon' => 'SilverwareForkKnife'],
		['type' => 'expense', 'name' => 'Personal care and cosmetics', 'icon' => 'Heart'],
		['type' => 'expense', 'name' => 'Home and furniture', 'icon' => 'Sofa'],
		['type' => 'expense', 'name' => 'Rent and utilities', 'icon' => 'Home'],
		['type' => 'expense', 'name' => 'Car and bicycle', 'icon' => 'Car'],
		['type' => 'expense', 'name' => 'Tickets and taxi', 'icon' => 'Train'],
		['type' => 'expense', 'name' => 'Leisure and hobbies', 'icon' => 'Dumbbell'],
		['type' => 'expense', 'name' => 'Shopping', 'icon' => 'Cart'],
		['type' => 'expense', 'name' => 'Health', 'icon' => 'Pill'],
		['type' => 'expense', 'name' => 'Insurance and finance', 'icon' => 'Bank'],
		['type' => 'expense', 'name' => 'Other expenses', 'icon' => 'Shape'],
	];

	private IDBConnection $db;
	private ?string $userId;
	private IConfig $config;
	private IL10N $l10n;
	private IGroupManager $groupManager;

	public function __construct(string $appName, IRequest $request, IDBConnection $db, IUserSession $userSession, IConfig $config, IL10N $l10n, IGroupManager $groupManager) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->initWorkspace();
	}

	private function requireProjectOwnerForScopedMutation(?int $projectId): ?DataResponse {
		if ($projectId !== null && !$this->projectOwnerInActiveWorkspace($projectId)) {
			return $this->errorResponse('Nur der Ersteller des Bereichs darf Bereich-Einstellungen ändern.', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function requireAdmin(): ?DataResponse {
		if ($this->userId === null) {
			return $this->errorResponse('Authentication required', Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->groupManager->isAdmin($this->userId)) {
			return $this->errorResponse('Administrator permissions required', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function ensureDefaultGlobalCategories(): void {
		if ($this->config->getAppValue('cobudget', self::DEFAULT_CATEGORIES_SEEDED_KEY, 'no') === 'yes') {
			return;
		}

		$this->db->beginTransaction();
		try {
			foreach (self::DEFAULT_GLOBAL_CATEGORIES as $category) {
				$this->seedGlobalCategory($category['name'], $category['type'], $category['icon']);
			}
			$this->db->commit();
			$this->config->setAppValue('cobudget', self::DEFAULT_CATEGORIES_SEEDED_KEY, 'yes');
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function seedGlobalCategory(string $name, string $type, string $icon): void {
		$name = $this->l10n->t($name);
		if ($this->findGlobalNameMatches('cobudget_categories', $name, $type) !== []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_categories')
			->values([
				'name' => $qb->createNamedParameter($name),
				'icon' => $qb->createNamedParameter($icon),
				'type' => $qb->createNamedParameter($type),
				'is_global' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
				'is_hidden' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
			]);
		$qb->executeStatement();
	}

	private function nullableCategoryId(mixed $value): ?int {
		if ($value === null || $value === '' || !is_numeric($value)) {
			return null;
		}

		$id = (int)$value;
		return $id > 0 ? $id : null;
	}

	private function categoryHasChildren(int $categoryId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_categories')
			->where($qb->expr()->eq('parent_category_id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$hasChildren = $result->fetch() !== false;
		$result->closeCursor();
		return $hasChildren;
	}

	private function categoryById(int $categoryId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_categories')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return is_array($row) ? $row : null;
	}

	private function boolValue(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value !== 0;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	private function normalizedCategoryIds(mixed $value): array {
		$decoded = is_array($value) ? $value : json_decode((string)($value ?: '[]'), true);
		if (!is_array($decoded)) {
			return [];
		}

		$ids = [];
		foreach ($decoded as $categoryId) {
			$id = $this->nullableCategoryId($categoryId);
			if ($id !== null) {
				$ids[$id] = $id;
			}
		}
		ksort($ids, SORT_NUMERIC);

		return array_values($ids);
	}

	private function hiddenCategoryIdsForScope(?int $projectId): array {
		if ($projectId !== null) {
			return $this->projectHiddenCategoryIds($projectId);
		}

		return $this->normalizedCategoryIds(
			$this->config->getUserValue((string)$this->userId, 'cobudget', 'hidden_categories', '[]')
		);
	}

	private function saveProjectHiddenCategoryIds(int $projectId, array $hiddenIds): bool {
		$project = $this->projectOwnerForCurrentUser($projectId);
		if ($project === null) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_projects')
			->set('hidden_category_ids', $qb->createNamedParameter(
				json_encode($this->normalizedCategoryIds($hiddenIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'
			))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter((int)$project['workspace_id'], \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner_id', $qb->createNamedParameter((string)$this->userId)));

		$qb->executeStatement();
		return true;
	}

	private function removeProjectHiddenCategoryReference(int $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'hidden_category_ids')
			->from('cobudget_projects');
		$result = $qb->executeQuery();
		$projects = $result->fetchAll();
		$result->closeCursor();

		foreach ($projects as $project) {
			$hiddenIds = $this->normalizedCategoryIds($project['hidden_category_ids'] ?? '[]');
			if (!in_array($categoryId, $hiddenIds, true)) {
				continue;
			}

			$hiddenIds = array_values(array_filter(
				$hiddenIds,
				static fn(int $hiddenId): bool => $hiddenId !== $categoryId
			));
			$update = $this->db->getQueryBuilder();
			$update->update('cobudget_projects')
				->set('hidden_category_ids', $update->createNamedParameter(json_encode($hiddenIds) ?: '[]'))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$project['id'], \PDO::PARAM_INT)));
			$update->executeStatement();
		}
	}

	private function visibleGlobalCategory(int $categoryId): ?array {
		$category = $this->categoryById($categoryId);
		if ($category === null
			|| !$this->boolValue($category['is_global'] ?? false)
			|| $this->boolValue($category['is_hidden'] ?? false)) {
			return null;
		}

		return $category;
	}

	private function parentMatchesCategoryScope(array $parent, array $category): bool {
		$categoryIsGlobal = $this->boolValue($category['is_global'] ?? false);
		$parentIsGlobal = $this->boolValue($parent['is_global'] ?? false);
		if ($categoryIsGlobal) {
			return $parentIsGlobal;
		}
		if ($parentIsGlobal) {
			$parentId = $this->nullableCategoryId($parent['id'] ?? null);
			$currentParentId = $this->nullableCategoryId($category['parent_category_id'] ?? null);
			if ($this->boolValue($parent['is_hidden'] ?? false)) {
				return $parentId !== null && $parentId === $currentParentId;
			}

			$categoryProjectId = $this->nullableCategoryId($category['project_id'] ?? null);
			if ($categoryProjectId !== null
				&& $parentId !== null
				&& in_array($parentId, $this->projectHiddenCategoryIds($categoryProjectId), true)) {
				return $parentId === $currentParentId;
			}

			return true;
		}

		$categoryWorkspaceId = $this->nullableCategoryId($category['workspace_id'] ?? null);
		$parentWorkspaceId = $this->nullableCategoryId($parent['workspace_id'] ?? null);
		if ($categoryWorkspaceId === null || $categoryWorkspaceId !== $parentWorkspaceId) {
			return false;
		}

		$categoryProjectId = $this->nullableCategoryId($category['project_id'] ?? null);
		$parentProjectId = $this->nullableCategoryId($parent['project_id'] ?? null);
		if ($categoryProjectId !== null) {
			return $categoryProjectId === $parentProjectId;
		}

		return $parentProjectId === null
			&& trim((string)($parent['user_id'] ?? '')) === (string)$this->userId;
	}

	private function validateParentCategory(?int $parentCategoryId, array $category, int $categoryId): ?DataResponse {
		if ($parentCategoryId === null) {
			return null;
		}
		if ($parentCategoryId === $categoryId) {
			return $this->errorResponse('A category cannot be its own parent category.', Http::STATUS_BAD_REQUEST);
		}
		if ($this->categoryHasChildren($categoryId)) {
			return $this->errorResponse('A main category with subcategories cannot become a subcategory.', Http::STATUS_CONFLICT);
		}

		$parent = $this->categoryById($parentCategoryId);
		if ($parent === null || !$this->parentMatchesCategoryScope($parent, $category)) {
			return $this->errorResponse('Parent category not found or not available in this context.', Http::STATUS_BAD_REQUEST);
		}
		if ($this->nullableCategoryId($parent['parent_category_id'] ?? null) !== null) {
			return $this->errorResponse('Only a main category can be selected as parent category.', Http::STATUS_BAD_REQUEST);
		}
		if ((string)($parent['type'] ?? '') !== (string)($category['type'] ?? '')) {
			return $this->errorResponse('Parent category and subcategory must have the same type.', Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	private function attachCategoryHierarchy(array $categories): array {
		if ($categories === []) {
			return [];
		}

		$categoryIds = [];
		$parentIds = [];
		$categoryById = [];
		foreach ($categories as $category) {
			$id = $this->nullableCategoryId($category['id'] ?? null);
			if ($id !== null) {
				$categoryIds[] = $id;
				$categoryById[$id] = $category;
			}
			$parentId = $this->nullableCategoryId($category['parent_category_id'] ?? null);
			if ($parentId !== null) {
				$parentIds[] = $parentId;
			}
		}

		$missingParentIds = array_values(array_diff(array_unique($parentIds), array_keys($categoryById)));
		if ($missingParentIds !== []) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'name', 'code', 'icon', 'parent_category_id')
				->from('cobudget_categories')
				->where($qb->expr()->in('id', $qb->createNamedParameter($missingParentIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $parent) {
				$parentId = (int)($parent['id'] ?? 0);
				if ($parentId > 0) {
					$categoryById[$parentId] = $parent;
				}
			}
			$result->closeCursor();
		}

		$parentIdsWithChildren = [];
		if ($categoryIds !== []) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('parent_category_id')
				->from('cobudget_categories')
				->where($qb->expr()->in('parent_category_id', $qb->createNamedParameter(array_values(array_unique($categoryIds)), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)))
				->groupBy('parent_category_id');
			$result = $qb->executeQuery();
			foreach ($result->fetchAll(\PDO::FETCH_COLUMN) as $parentId) {
				$parentIdsWithChildren[(int)$parentId] = true;
			}
			$result->closeCursor();
		}

		foreach ($categories as &$category) {
			$id = $this->nullableCategoryId($category['id'] ?? null);
			$parentId = $this->nullableCategoryId($category['parent_category_id'] ?? null);
			$parent = $parentId !== null ? ($categoryById[$parentId] ?? null) : null;
			$category['parent_category_id'] = $parentId;
			$category['parent_name'] = is_array($parent) ? (string)($parent['name'] ?? '') : null;
			$category['parent_code'] = is_array($parent) && trim((string)($parent['code'] ?? '')) !== ''
				? (string)$parent['code']
				: null;
			$category['parent_icon'] = is_array($parent) ? (string)($parent['icon'] ?? 'Shape') : null;
			$category['has_children'] = $id !== null && isset($parentIdsWithChildren[$id]);
		}
		unset($category);

		return $categories;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$this->ensureDefaultGlobalCategories();
			$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
			}
			$hiddenIds = $this->hiddenCategoryIdsForScope($projectId);

			$qb = $this->db->getQueryBuilder();
			$globalScope = $qb->expr()->andX(
				$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			);
			$localScope = $projectId !== null
				? $qb->expr()->andX(
					$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
					$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT))
				)
				: $qb->expr()->andX(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)),
					$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
					$qb->expr()->isNull('project_id')
				);
			$qb->select('*')
				->from('cobudget_categories')
				->where(
					$qb->expr()->orX(
						$globalScope,
						$localScope
					)
				);

			$result = $qb->executeQuery();
			$categories = $result->fetchAll();
			$result->closeCursor();

			// Filter out hidden categories
			$filtered = array_filter($categories, function($cat) use ($hiddenIds) {
				return !in_array((int)$cat['id'], $hiddenIds, true);
			});

			$categories = $this->addRecentUsageCounts(array_values($filtered), 'category_id', $workspaceId);
			return new DataResponse($this->attachCategoryHierarchy($categories));
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function settingsData(?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$this->ensureDefaultGlobalCategories();
			$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
			}
			$hiddenIds = $this->hiddenCategoryIdsForScope($projectId);

			$qb = $this->db->getQueryBuilder();
			$globalScope = $qb->expr()->andX(
				$qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)),
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			);
			if ($projectId !== null) {
				$qb->select('*')
					->from('cobudget_categories')
					->where(
						$qb->expr()->orX(
							$globalScope,
							$qb->expr()->andX(
								$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
								$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)),
								$qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
							)
						)
					);
			} else {
				$qb->select('*')
					->from('cobudget_categories')
					->where(
						$qb->expr()->orX(
							$globalScope,
							$qb->expr()->andX(
								$qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)),
								$qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)),
								$qb->expr()->isNull('project_id')
							)
						)
					);
			}

			$result = $qb->executeQuery();
			$categories = $result->fetchAll();
			$result->closeCursor();

			$qbUsed = $this->db->getQueryBuilder();
			$qbUsed->select('category_id')
				->from('cobudget_entries')
				->where($qbUsed->expr()->isNotNull('category_id'))
				->andWhere($qbUsed->expr()->eq('workspace_id', $qbUsed->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->groupBy('category_id');
			if ($projectId !== null) {
				$qbUsed
					->andWhere($qbUsed->expr()->eq('entry_kind', $qbUsed->createNamedParameter('shared')))
					->andWhere($qbUsed->expr()->eq('project_id', $qbUsed->createNamedParameter($projectId, \PDO::PARAM_INT)));
			} else {
				$qbUsed
					->andWhere($qbUsed->expr()->eq('entry_kind', $qbUsed->createNamedParameter('personal')))
					->andWhere($qbUsed->expr()->eq('user_id', $qbUsed->createNamedParameter($this->userId)));
			}
			$usedEntries = $qbUsed->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);

			$qbUsedTpl = $this->db->getQueryBuilder();
			$qbUsedTpl->select('category_id')
				->from('cobudget_templates')
				->where($qbUsedTpl->expr()->isNotNull('category_id'))
				->andWhere($qbUsedTpl->expr()->eq('workspace_id', $qbUsedTpl->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->groupBy('category_id');
			if ($projectId !== null) {
				$qbUsedTpl->andWhere($qbUsedTpl->expr()->eq('project_id', $qbUsedTpl->createNamedParameter($projectId, \PDO::PARAM_INT)));
			} else {
				$qbUsedTpl->andWhere($qbUsedTpl->expr()->eq('user_id', $qbUsedTpl->createNamedParameter($this->userId)));
			}
			$usedTemplates = $qbUsedTpl->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
			
			$usedCategoryIds = array_values(array_unique(array_map('intval', array_merge($usedEntries, $usedTemplates))));

			foreach ($categories as &$cat) {
				$cat['is_hidden'] = in_array((int)$cat['id'], $hiddenIds, true);
				$cat['in_use'] = in_array((int)$cat['id'], $usedCategoryIds, true);
			}

			return new DataResponse($this->attachCategoryHierarchy($categories));
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $name = '', string $icon = 'Shape', string $type = 'expense', ?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validateTypedNamePayload($name, $type)) {
				return $validationError;
			}
			$icon = $this->normalizeOptionalString($icon, 64);

			if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
				return $ownerError;
			}
			$workspaceId = $this->projectWorkspaceIdForCurrentUser($projectId);
			if ($workspaceId === null) {
				return $this->errorResponse('Area not found or not in the active workspace', Http::STATUS_FORBIDDEN);
			}

			if ($existingCategory = $this->findVisibleScopedNameMatch('cobudget_categories', $name, $workspaceId, null, $projectId, $type)) {
				return new DataResponse([
					'id' => (int)$existingCategory['id'],
					'name' => $existingCategory['name'],
					'code' => $existingCategory['code'] ?? null,
					'is_global' => (bool)$existingCategory['is_global'],
					'icon' => $existingCategory['icon'] ?? $icon,
					'type' => $type,
					'project_id' => $existingCategory['project_id'] === null ? null : (int)$existingCategory['project_id']
				]);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_categories')
				->values([
					'name' => $qb->createNamedParameter($name),
					'icon' => $qb->createNamedParameter($icon),
					'type' => $qb->createNamedParameter($type),
					'is_global' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
					'user_id' => $qb->createNamedParameter($this->userId),
					'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
					'project_id' => $projectId === null
						? $qb->createNamedParameter(null, \PDO::PARAM_NULL)
						: $qb->createNamedParameter($projectId, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_categories');
			return new DataResponse(['id' => $id, 'name' => $name, 'code' => null, 'icon' => $icon, 'type' => $type, 'is_global' => false, 'project_id' => $projectId]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $name = '', ?string $code = null, ?int $parentCategoryId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredName($name)) {
				return $validationError;
			}
			$category = $this->editableCategoryInActiveWorkspace($id);

			if (!$category) {
				return $this->errorResponse('Category not found or not editable', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$category['workspace_id'];
			$storedCode = $code === null
				? trim((string)($category['code'] ?? ''))
				: $this->normalizeOptionalString($code, 128);
			$storedParentCategoryId = $parentCategoryId === null
				? $this->nullableCategoryId($category['parent_category_id'] ?? null)
				: $this->nullableCategoryId($parentCategoryId);

			$projectId = $category['project_id'] === null || $category['project_id'] === '' ? null : (int)$category['project_id'];
			if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
				return $ownerError;
			}

			if ($this->findVisibleScopedNameMatch('cobudget_categories', $name, $workspaceId, $id, $projectId, $category['type'] ?? 'expense') !== null) {
				return $this->errorResponse('A category with this name already exists.', Http::STATUS_CONFLICT);
			}
			if ($parentCategoryId !== null) {
				if ($parentError = $this->validateParentCategory($storedParentCategoryId, $category, $id)) {
					return $parentError;
				}
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('name', $qb->createNamedParameter($name))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			if ($code !== null) {
				$qb->set('code', $qb->createNamedParameter($storedCode === '' ? null : $storedCode, $storedCode === '' ? \PDO::PARAM_NULL : \PDO::PARAM_STR));
			}
			if ($parentCategoryId !== null) {
				$qb->set(
					'parent_category_id',
					$qb->createNamedParameter(
						$storedParentCategoryId,
						$storedParentCategoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT
					)
				);
			}
			if ($projectId === null) {
				$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
					->andWhere($qb->expr()->isNull('project_id'));
			} else {
				$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$qb->executeStatement();

			$response = [
				'id' => $id,
				'name' => $name,
				'code' => $storedCode === '' ? null : $storedCode,
				'icon' => $category['icon'] ?? 'Shape',
				'type' => $category['type'] ?? 'expense',
				'is_global' => false,
				'workspace_id' => $workspaceId,
				'user_id' => $category['user_id'] ?? $this->userId,
				'project_id' => $projectId,
				'parent_category_id' => $storedParentCategoryId,
			];
			return new DataResponse($this->attachCategoryHierarchy([$response])[0]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateIcon(int $id, string $icon): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredString($icon, 'Icon is required', 64)) {
				return $validationError;
			}

			$category = $this->editableCategoryInActiveWorkspace($id);
			if (!$category) {
				return $this->errorResponse('Category not found or not editable', Http::STATUS_NOT_FOUND);
			}
			$workspaceId = (int)$category['workspace_id'];
			$projectId = $category['project_id'] === null || $category['project_id'] === '' ? null : (int)$category['project_id'];
			if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
				return $ownerError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('icon', $qb->createNamedParameter($icon))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
				->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			if ($projectId === null) {
				$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)))
					->andWhere($qb->expr()->isNull('project_id'));
			} else {
				$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function hide(int $id, ?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($projectId !== null && $projectId <= 0) {
				return $this->errorResponse('Invalid area id', Http::STATUS_BAD_REQUEST);
			}
			if ($projectId !== null && !$this->projectOwnerInActiveWorkspace($projectId)) {
				return $this->errorResponse('Only the area owner may change area settings.', Http::STATUS_FORBIDDEN);
			}
			if ($projectId !== null && $this->visibleGlobalCategory($id) === null) {
				return $this->errorResponse('Global category not found', Http::STATUS_NOT_FOUND);
			}
			if (!$this->categoryAvailableInActiveWorkspace($id, $projectId)) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}

			$hiddenIds = $this->hiddenCategoryIdsForScope($projectId);
			if (!in_array($id, $hiddenIds, true)) {
				$hiddenIds[] = $id;
				if ($projectId !== null) {
					if (!$this->saveProjectHiddenCategoryIds($projectId, $hiddenIds)) {
						return $this->errorResponse('Area settings could not be saved.', Http::STATUS_INTERNAL_SERVER_ERROR);
					}
				} else {
					$this->config->setUserValue(
						(string)$this->userId,
						'cobudget',
						'hidden_categories',
						json_encode($this->normalizedCategoryIds($hiddenIds)) ?: '[]'
					);
				}
			}
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function unhide(int $id, ?int $projectId = null): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($projectId !== null && $projectId <= 0) {
				return $this->errorResponse('Invalid area id', Http::STATUS_BAD_REQUEST);
			}
			if ($projectId !== null && !$this->projectOwnerInActiveWorkspace($projectId)) {
				return $this->errorResponse('Only the area owner may change area settings.', Http::STATUS_FORBIDDEN);
			}
			if ($projectId !== null && $this->visibleGlobalCategory($id) === null) {
				return $this->errorResponse('Global category not found', Http::STATUS_NOT_FOUND);
			}
			if (!$this->categoryAvailableInActiveWorkspace($id, $projectId)) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}

			$hiddenIds = array_values(array_filter(
				$this->hiddenCategoryIdsForScope($projectId),
				static fn(int $hiddenId): bool => $hiddenId !== $id
			));
			if ($projectId !== null) {
				if (!$this->saveProjectHiddenCategoryIds($projectId, $hiddenIds)) {
					return $this->errorResponse('Area settings could not be saved.', Http::STATUS_INTERNAL_SERVER_ERROR);
				}
			} else {
				$this->config->setUserValue(
					(string)$this->userId,
					'cobudget',
					'hidden_categories',
					json_encode($hiddenIds) ?: '[]'
				);
			}
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$category = $this->editableCategoryInActiveWorkspace($id);
			if (!$category) {
				return $this->errorResponse('Category not found or not deletable', Http::STATUS_NOT_FOUND);
			}
			if ($this->categoryHasChildren($id)) {
				return $this->errorResponse('Category has subcategories and cannot be deleted.', Http::STATUS_CONFLICT);
			}

			$workspaceId = (int)$category['workspace_id'];
			$projectId = $category['project_id'] === null || $category['project_id'] === '' ? null : (int)$category['project_id'];
			if ($ownerError = $this->requireProjectOwnerForScopedMutation($projectId)) {
				return $ownerError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('cobudget_entries')
				->where($qb->expr()->eq('category_id', $qb->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseEntries = $qb->executeQuery()->fetch();

			$qb2 = $this->db->getQueryBuilder();
			$qb2->select('id')
				->from('cobudget_templates')
				->where($qb2->expr()->eq('category_id', $qb2->createNamedParameter($id)))
				->andWhere($qb2->expr()->eq('workspace_id', $qb2->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->setMaxResults(1);
			if ($projectId === null) {
				$qb2->andWhere($qb2->expr()->eq('user_id', $qb2->createNamedParameter($this->userId)))
					->andWhere($qb2->expr()->isNull('project_id'));
			} else {
				$qb2->andWhere($qb2->expr()->eq('project_id', $qb2->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$inUseTemplates = $qb2->executeQuery()->fetch();

			if ($inUseEntries !== false || $inUseTemplates !== false) {
				return $this->errorResponse('Category is still in use and cannot be deleted. Please use the hide function instead.', Http::STATUS_CONFLICT);
			}

			// Ensure it belongs to user
			$qb3 = $this->db->getQueryBuilder();
			$qb3->delete('cobudget_categories')
				->where($qb3->expr()->eq('id', $qb3->createNamedParameter($id)))
				->andWhere($qb3->expr()->eq('workspace_id', $qb3->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
				->andWhere($qb3->expr()->eq('is_global', $qb3->createNamedParameter(false, \PDO::PARAM_BOOL)));
			if ($projectId === null) {
				$qb3->andWhere($qb3->expr()->eq('user_id', $qb3->createNamedParameter($this->userId)))
					->andWhere($qb3->expr()->isNull('project_id'));
			} else {
				$qb3->andWhere($qb3->expr()->eq('project_id', $qb3->createNamedParameter($projectId, \PDO::PARAM_INT)));
			}
			$qb3->executeStatement();
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	// ---------------------------------------------------------
	// ADMIN API
	// ---------------------------------------------------------

	private function getAdminGlobalCategory(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_categories')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
			->setMaxResults(1);

		$row = $qb->executeQuery()->fetch();
		return $row ?: null;
	}

	public function adminIndex(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$this->ensureDefaultGlobalCategories();
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('cobudget_categories')
				->where($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$result = $qb->executeQuery();
			$categories = $result->fetchAll();
			$result->closeCursor();
			return new DataResponse($this->attachCategoryHierarchy($categories));
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminCreate(string $name = '', string $icon = 'Shape', string $type = 'expense'): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validateTypedNamePayload($name, $type)) {
				return $validationError;
			}
			$icon = $this->normalizeOptionalString($icon, 64);
			$matches = $this->findGlobalNameMatches('cobudget_categories', $name, $type);
			if ($this->firstVisibleGlobalNameMatch($matches) !== null) {
				return $this->errorResponse('A global category with this name already exists.', Http::STATUS_CONFLICT);
			}
			if ($hiddenCategory = $this->firstHiddenGlobalNameMatch($matches)) {
				$id = (int)$hiddenCategory['id'];
				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_categories')
					->set('name', $qb->createNamedParameter($name))
					->set('icon', $qb->createNamedParameter($icon))
					->set('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
				$qb->executeStatement();
				return new DataResponse(['id' => $id, 'name' => $name, 'code' => $hiddenCategory['code'] ?? null, 'icon' => $icon, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
			}
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_categories')
				->values([
					'name' => $qb->createNamedParameter($name),
					'icon' => $qb->createNamedParameter($icon),
					'type' => $qb->createNamedParameter($type),
					'is_global' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
					'is_hidden' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
				]);
			$qb->executeStatement();
			$id = (int)$this->db->lastInsertId('*PREFIX*cobudget_categories');
			return new DataResponse(['id' => $id, 'name' => $name, 'code' => null, 'icon' => $icon, 'type' => $type, 'is_global' => true, 'is_hidden' => false]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminUpdate(int $id, string $name = '', ?string $code = null, ?int $parentCategoryId = null): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredName($name)) {
				return $validationError;
			}
			$category = $this->getAdminGlobalCategory($id);
			if (!$category) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}
			$storedCode = $code === null
				? trim((string)($category['code'] ?? ''))
				: $this->normalizeOptionalString($code, 128);
			$storedParentCategoryId = $parentCategoryId === null
				? $this->nullableCategoryId($category['parent_category_id'] ?? null)
				: $this->nullableCategoryId($parentCategoryId);

			$matches = $this->findGlobalNameMatches('cobudget_categories', $name, $category['type'] ?? 'expense', $id);
			if ($matches !== []) {
				return $this->errorResponse('A global category with this name already exists.', Http::STATUS_CONFLICT);
			}
			if ($parentCategoryId !== null) {
				if ($parentError = $this->validateParentCategory($storedParentCategoryId, $category, $id)) {
					return $parentError;
				}
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('name', $qb->createNamedParameter($name))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			if ($code !== null) {
				$qb->set('code', $qb->createNamedParameter($storedCode === '' ? null : $storedCode, $storedCode === '' ? \PDO::PARAM_NULL : \PDO::PARAM_STR));
			}
			if ($parentCategoryId !== null) {
				$qb->set(
					'parent_category_id',
					$qb->createNamedParameter(
						$storedParentCategoryId,
						$storedParentCategoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT
					)
				);
			}
			$qb->executeStatement();

			$response = [
				'id' => $id,
				'name' => $name,
				'code' => $storedCode === '' ? null : $storedCode,
				'icon' => $category['icon'] ?? 'Shape',
				'type' => $category['type'] ?? 'expense',
				'is_global' => true,
				'is_hidden' => (bool)($category['is_hidden'] ?? false),
				'parent_category_id' => $storedParentCategoryId,
			];
			return new DataResponse($this->attachCategoryHierarchy([$response])[0]);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminUpdateIcon(int $id, string $icon): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if ($validationError = $this->validateRequiredString($icon, 'Icon is required', 64)) {
				return $validationError;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('icon', $qb->createNamedParameter($icon))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminHide(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			if (!$this->getAdminGlobalCategory($id)) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('is_hidden', $qb->createNamedParameter(true, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminUnhide(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}

			$category = $this->getAdminGlobalCategory($id);
			if (!$category) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}

			$matches = $this->findGlobalNameMatches('cobudget_categories', (string)$category['name'], $category['type'] ?? 'expense', $id);
			if ($this->firstVisibleGlobalNameMatch($matches) !== null) {
				return $this->errorResponse('A visible global category with this name already exists.', Http::STATUS_CONFLICT);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_categories')
				->set('is_hidden', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_global', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return $this->loggedErrorResponse($e);
		}
	}

	public function adminDestroy(int $id): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			if ($validationError = $this->validatePositiveId($id)) {
				return $validationError;
			}
			if (!$this->getAdminGlobalCategory($id)) {
				return $this->errorResponse('Category not found', Http::STATUS_NOT_FOUND);
			}
			if ($this->categoryHasChildren($id)) {
				return $this->errorResponse('Category has subcategories and cannot be deleted.', Http::STATUS_CONFLICT);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('cobudget_entries')
				->where($qb->expr()->eq('category_id', $qb->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseEntries = $qb->executeQuery()->fetch();

			$qb2 = $this->db->getQueryBuilder();
			$qb2->select('id')
				->from('cobudget_templates')
				->where($qb2->expr()->eq('category_id', $qb2->createNamedParameter($id)))
				->setMaxResults(1);
			$inUseTemplates = $qb2->executeQuery()->fetch();

			if ($inUseEntries !== false || $inUseTemplates !== false) {
				return $this->errorResponse('Category is still in use and cannot be deleted.', Http::STATUS_CONFLICT);
			}

			$this->db->beginTransaction();
			try {
				$this->removeProjectHiddenCategoryReference($id);
				$qb3 = $this->db->getQueryBuilder();
				$qb3->delete('cobudget_categories')
					->where($qb3->expr()->eq('id', $qb3->createNamedParameter($id)))
					->andWhere($qb3->expr()->eq('is_global', $qb3->createNamedParameter(true, \PDO::PARAM_BOOL)));
				$qb3->executeStatement();
				$this->db->commit();
			} catch (\Throwable $transactionError) {
				$this->db->rollBack();
				throw $transactionError;
			}
			return new DataResponse(['status' => 'success']);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}
}

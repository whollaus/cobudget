const normalizedParentId = category => {
	const value = Number(category?.parent_category_id || 0)
	return Number.isInteger(value) && value > 0 ? value : null
}

const compareCategoryNames = (left, right) => String(left?.name || '').localeCompare(
	String(right?.name || ''),
	undefined,
	{ sensitivity: 'base' }
)

export const categoryParentId = category => normalizedParentId(category)

export const isSubcategory = category => normalizedParentId(category) !== null

export const categoryChoiceLabel = (category, subcategoryPrefix = '↳ ') => {
	const name = String(category?.name || '').trim()
	return isSubcategory(category) ? `${subcategoryPrefix}${name}` : name
}

export const categoryPathLabel = (category, separator = ' › ') => {
	const name = String(category?.name || '').trim()
	const parentName = String(category?.parent_name || '').trim()
	return parentName && normalizedParentId(category) !== null
		? `${parentName}${separator}${name}`
		: name
}

export const sortCategoriesHierarchically = categories => {
	const items = Array.isArray(categories) ? [...categories] : []
	const byId = new Map(items.map(category => [Number(category?.id), category]))
	const childrenByParent = new Map()
	const roots = []

	for (const category of items) {
		const parentId = normalizedParentId(category)
		if (parentId !== null && byId.has(parentId)) {
			if (!childrenByParent.has(parentId)) {
				childrenByParent.set(parentId, [])
			}
			childrenByParent.get(parentId).push(category)
		} else {
			roots.push(category)
		}
	}

	roots.sort((left, right) => {
		const leftParent = String(left?.parent_name || '')
		const rightParent = String(right?.parent_name || '')
		const parentCompare = leftParent.localeCompare(rightParent, undefined, { sensitivity: 'base' })
		return parentCompare !== 0 ? parentCompare : compareCategoryNames(left, right)
	})

	const sorted = []
	for (const root of roots) {
		sorted.push(root)
		const children = childrenByParent.get(Number(root?.id)) || []
		children.sort(compareCategoryNames)
		sorted.push(...children)
	}

	return sorted
}

export const mainCategoryOptions = (categories, currentCategory) => {
	const currentId = Number(currentCategory?.id || 0)
	const currentParentId = normalizedParentId(currentCategory)
	const currentType = String(currentCategory?.type || '')

	const options = sortCategoriesHierarchically(categories)
		.filter(category => {
			const id = Number(category?.id || 0)
			if (!id || id === currentId || normalizedParentId(category) !== null) {
				return false
			}
			if (currentType && String(category?.type || '') !== currentType) {
				return false
			}
			const hidden = category?.is_hidden === true
				|| category?.is_hidden === 1
				|| category?.is_hidden === '1'
				|| category?.is_hidden === 'true'
			return !hidden || id === currentParentId
		})

	const currentParentName = String(currentCategory?.parent_name || '').trim()
	if (currentParentId !== null
		&& currentParentName
		&& !options.some(category => Number(category?.id || 0) === currentParentId)) {
		options.unshift({
			id: currentParentId,
			name: currentParentName,
			code: currentCategory?.parent_code || null,
			icon: currentCategory?.parent_icon || 'Shape',
			type: currentType,
			is_hidden: true
		})
	}

	return options
}

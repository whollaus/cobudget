import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'

const root = new URL('../', import.meta.url).pathname
const cache = new Map()
const nativeStub = { setup: (_, { slots }) => () => Vue.h('div', [slots.trigger?.(), slots.default?.(), slots.icon?.()]) }
function load(file) {
	if (cache.has(file)) return cache.get(file)
	const source = fs.readFileSync(file, 'utf8')
	const descriptor = file.endsWith('.vue') ? parse(source).descriptor : null
	const bindings = {}
	let code = (descriptor ? descriptor.script.content : source).replace(/^import (.+) from ['"](.+)['"]\s*;?$/gm, (_, names, target) => {
		let imported
		if (target.startsWith('@nextcloud/vue/')) imported = { default: nativeStub }
		else {
			let dependency = target.startsWith('.') ? path.resolve(path.dirname(file), target) : path.join(root, 'node_modules', target)
			if (!path.extname(dependency)) dependency += '.js'
			imported = load(dependency)
		}
		if (names.startsWith('{')) for (const name of names.slice(1, -1).split(',').map(value => value.trim())) bindings[name] = imported[name]
		else bindings[names] = imported.default
		return ''
	})
	let result
	if (descriptor) {
		code = code.replace('export default', 'return')
		const component = new Function(...Object.keys(bindings), code)(...Object.values(bindings))
		component.render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
		result = { default: component }
	} else {
		const exports = [...code.matchAll(/export const (\w+)/g)].map(match => match[1])
		result = new Function(...Object.keys(bindings), code.replace(/export const /g, 'const ') + `\nreturn {${exports.join(',')}}`)(...Object.values(bindings))
	}
	cache.set(file, result)
	return result
}

const { categoryIconNames } = load(path.join(root, 'src/components/categoryIcons.js'))
const category = load(path.join(root, 'src/components/CategoryIcon.vue')).default
const picker = load(path.join(root, 'src/components/IconPicker.vue')).default
const legacyKeys = 'Shape Home Cart Car Train Flash Water Briefcase Heart Dumbbell School BabyCarriage Dog Sofa Television GamepadVariant Airplane Gift TshirtCrew Cellphone Wrench Bank Cash HelpCircle FoodApple SilverwareForkKnife Sprout Tree Cat Paw Run Bike MusicNote Pill GasStation BookOpenVariant'.split(' ')
assert.deepEqual(categoryIconNames, legacyKeys, 'All saved category IDs and picker order remain compatible')
assert.strictEqual(picker.data().icons, categoryIconNames)
for (const icon of legacyKeys) {
	const html = await renderToString(Vue.createSSRApp(category, { icon, size: 18 }))
	assert.ok(html.includes('<svg'), `Saved icon ${icon} still renders`)
	const events = []
	picker.methods.selectIcon.call({ $emit: (...args) => events.push(args) }, icon)
	assert.deepEqual(events, [['input', icon]], 'Selecting an outline persists the original stable ID')
}
for (const [icon, expected] of [['Home', 'home-outline'], ['Shape', 'shape-outline'], ['unknown', 'shape-outline'], ['__proto__', 'shape-outline']]) {
	const html = await renderToString(Vue.createSSRApp(category, { icon }))
	assert.ok(html.includes(`${expected}-icon`))
	assert.equal(picker.computed.selectedIcon.call({ value: icon }), legacyKeys.includes(icon) ? icon : 'Shape')
}
assert.strictEqual(category.components.Home, picker.components.Home, 'Category display and picker render the same glyph')
console.log('Category icons passed: saved IDs, selection payloads, real SVG rendering and unknown-icon fallback remain compatible.')

const areaIcon = load(path.join(root, 'src/components/AreaIcon.vue')).default
const withoutAreaHtml = await renderToString(Vue.createSSRApp(areaIcon, { withoutArea: true }))
const assignedAreaHtml = await renderToString(Vue.createSSRApp(areaIcon, { color: '#aabbcc' }))
assert.ok(withoutAreaHtml.includes('folder-outline-icon'), 'Unassigned payments have a distinct empty outline folder')
assert.ok(!withoutAreaHtml.includes('wallet'), 'The unassigned destination cannot look like the all-payments view')
assert.ok(assignedAreaHtml.includes('folder-icon') && assignedAreaHtml.includes('#aabbcc'), 'Named areas retain their filled, colored folder')
console.log('Area icons passed: unassigned-folder distinction and named-area colors.')

const globalScopeHtml = await renderToString(Vue.createSSRApp(areaIcon, { globalScope: true }))
assert.ok(globalScopeHtml.includes('wallet-outline-icon'), 'Administrative global defaults remain distinct from Without area')

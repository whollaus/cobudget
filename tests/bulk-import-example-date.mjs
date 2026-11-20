import assert from 'node:assert/strict'
import { resolveObjectURL } from 'node:buffer'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { createRenderer } from 'vue'
import { parse } from '@vue/compiler-sfc'

const read = file => readFileSync(new URL('../src/' + file, import.meta.url), 'utf8')
const moduleUrl = source => 'data:text/javascript,' + encodeURIComponent(source)
const { useLocalDate } = await import(moduleUrl(read('utils/useLocalDate.js')
	.replace("'vue'", JSON.stringify(import.meta.resolve('vue')))
	.replace("'./entryDefaultDate'", JSON.stringify(moduleUrl(read('utils/entryDefaultDate.js'))))))
const { downloadTextFile } = await import(moduleUrl(read('services/annualOverview.js')))
const { copyReportText } = await import(moduleUrl(read('services/reportExportActions.js').replace(/^import .+$/gm, '')))

function component(file, dependencies = {}) {
	const { descriptor } = parse(read(file))
	const script = descriptor.script.content
	const components = Object.fromEntries([...script.matchAll(/^import (\w+) from /gm)].map(([, name]) => [name, {}]))
	const bindings = { ...components, useLocalDate, ...dependencies }
	return new Function(...Object.keys(bindings), script.replace(/^import .+$/gm, '').replace('export default', 'return'))(...Object.values(bindings))
}

// Mount real setup functions, computed values and button handlers. Network loading and visual layout are outside this test.
const renderer = createRenderer({ createComment: () => ({}), insert() {}, remove() {} })
const strings = new Proxy({}, { get: (_, key) => () => key })
function mount(options, props) {
	const app = renderer.createApp({
		...options, created: undefined, mounted: undefined, watch: undefined, render: () => null,
	}, props)
	app.config.globalProperties.$texts = { labels: strings, bulkImport: strings }
	return { app, vm: app.mount({}) }
}

test('open import examples follow local midnight in the placeholder, clipboard and download', async t => {
	process.env.TZ = 'Europe/Vienna'
	// UTC stays on December 31 across this local year boundary.
	t.mock.timers.enable({ apis: ['Date', 'setTimeout'], now: new Date('2026-12-31T22:59:59.000Z') })
	globalThis.document = new EventTarget()
	let clipboard, download
	Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {
		writeText: async text => { clipboard = text },
	} })
	document.body = { appendChild() {} }
	document.createElement = tag => {
		assert.equal(tag, 'a')
		return {
			click() { download = { name: this.download, blob: resolveObjectURL(this.href) } },
			remove() {},
		}
	}

	const view = mount(component('views/BulkImportView.vue'))
	const dialog = mount(component('components/ImportFormatDialog.vue', {
		sortCategoriesHierarchically: categories => categories,
		copyReportText, downloadTextFile,
		showToast() {},
	}), { show: true, categories: [], paymentPartners: [] })
	t.after(() => {
		dialog.app.unmount()
		view.app.unmount()
		delete navigator.clipboard
		delete globalThis.document
	})

	async function checkExamples(day) {
		const placeholder = view.vm.singlePaymentPlaceholder
		const sample = JSON.parse(placeholder.slice(placeholder.indexOf('{')))
		assert.deepEqual(sample.payments.map(payment => payment.date), [day], 'single-payment placeholder')

		const displayed = dialog.vm.exampleJsonText
		assert.deepEqual(JSON.parse(displayed).payments.map(payment => payment.date), [day, day], 'both displayed examples')
		clipboard = download = undefined
		await dialog.vm.copyExampleJson()
		assert.equal(clipboard, displayed, 'clipboard contents')
		dialog.vm.downloadExampleJson()
		assert.equal(download?.name, 'cobudget-import-example.json')
		assert.deepEqual(JSON.parse(await download.blob.text()), JSON.parse(displayed), 'downloaded JSON contents')
	}

	await checkExamples('2026-12-31')
	t.mock.timers.tick(1000)
	await checkExamples('2027-01-01')
})

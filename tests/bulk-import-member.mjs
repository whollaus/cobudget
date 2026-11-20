import assert from 'node:assert/strict'
import { loadView, newImport, startImportSession } from './bulk-import-harness.mjs'

// Bob belongs to Alice's area. Only Alice may create categories and partners in it.
const { request, close } = startImportSession('bob')
const known = { category: 'SharedRent', payment_partner: 'Landlord GmbH' }
const knownRows = Array.from({ length: 51 }, (_, index) => ({
	date: '2026-01-01', amount: -12.35, description: 'Payment ' + (index + 1), ...known,
}))
const unassigned = { date: '2026-01-02', amount: -8, description: 'Unassigned' }
const packets = []
const view = loadView(async (_url, blob) => {
	const body = await blob.text()
	packets.push(JSON.parse(body))
	return { data: await request(body) }
})

function importFile(lastPayment) {
	packets.length = 0
	return newImport(view, new Blob([JSON.stringify({ payments: [...knownRows, lastPayment] })]))
}

try {
	const before = await request('memberSnapshot')
	assert.equal(before.entryCount, 0)
	const missingCategory = 'Missing category'
	const missingPartner = 'Missing partner'
	for (const missing of [
		{ category: missingCategory },
		{ payment_partner: missingPartner },
		{ category: missingCategory, payment_partner: missingPartner },
	]) {
		const vm = importFile({ ...unassigned, ...known, ...missing })
		await vm.checkAndPreview()
		assert.deepEqual(packets.map(p => [p.offset, p.payments.length, p.dryRun]), [[0, 50, true], [50, 2, true]])
		for (const name of Object.values(missing)) {
			assert.ok(vm.validationErrors.some(message => message.includes(name)), 'Missing name must be reported: ' + name)
		}
		assert.equal(vm.previewSummary, null, 'A partially valid file must have no confirmable preview')
		assert.deepEqual(vm.previewRows, [])
		await vm.confirmImport()
		assert.equal(packets.length, 2, 'Confirmation after a failed preview must send no write packet')

		// The controller must also refuse the mixed packet on the write path.
		const commit = { ...packets[1], dryRun: false }
		await assert.rejects(request(JSON.stringify(commit)), error => error.response?.status === 403)
		assert.deepEqual(await request('memberSnapshot'), before, 'Rejected file must leave entries, shares and lookup records untouched')
	}

	const vm = importFile(unassigned)
	await vm.checkAndPreview()
	assert.deepEqual(vm.validationErrors, [])
	assert.equal(vm.previewRows.length, 52)
	await vm.confirmImport()
	assert.deepEqual(vm.validationErrors, [])
	const stored = await request('memberSnapshot')
	const category = before.categories.find(row => row.name === known.category && row.project_id === 10)
	const partner = before.partners.find(row => row.name === known.payment_partner && row.project_id === 10)
	assert.ok(category && partner)
	assert.deepEqual(stored.originals, [...knownRows, unassigned].map(row => ({
		user_id: 'bob', project_id: 10, description: row.description,
		category_id: row.category ? category.id : null,
		payment_partner_id: row.payment_partner ? partner.id : null,
	})))
	for (const key of ['categories', 'partners']) {
		assert.deepEqual(stored[key].filter(row => row.project_id === 10), before[key].filter(row => row.project_id === 10))
	}
	assert.equal(stored.shareCount, 52 * 3)
	assert.equal(stored.entryCount, 52 * 4)
	console.log('Area member import passed: unknown names reject the full file; existing and unassigned payments persist.')
} finally {
	close()
}

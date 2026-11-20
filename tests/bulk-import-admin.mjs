import assert from 'node:assert/strict'
import { loadView, newImport, startImportSession } from './bulk-import-harness.mjs'

const { request, close } = startImportSession('alice') // Owner of area 10.
const category = 'Weekly groceries'
const partner = 'Corner shop'
const payments = Array.from({ length: 117 }, (_, index) => ({
	date: '2026-01-01', amount: -12.35, description: 'Purchase ' + (index + 1),
	category: index % 2 ? category.toLowerCase() : category,
	payment_partner: index % 2 ? partner.toUpperCase() : partner,
}))
const file = new Blob([JSON.stringify({ payments })])
const writes = []
let lostPacket
const view = loadView(async (_url, blob) => {
	const body = await blob.text()
	const packet = JSON.parse(body)
	const data = await request(body)
	if (!packet.dryRun) {
		writes.push([packet.offset, data.importedCount, data.createdCategoriesCount, data.createdPaymentPartnersCount])
		if (!lostPacket) {
			lostPacket = body
			throw new Error('Lost confirmation after creating area lookups')
		}
	}
	return { data }
})

function areaRows(rows) {
	return rows.filter(row => row.project_id === 10)
}

try {
	const before = await request('memberSnapshot')
	const initial = newImport(view, file)
	await initial.checkAndPreview()
	assert.deepEqual(initial.validationErrors, [])
	assert.deepEqual(initial.newCategories.map(item => item.name), [category])
	assert.deepEqual(initial.newPaymentPartners.map(item => item.name), [partner])
	assert.deepEqual(await request('memberSnapshot'), before, 'Preview must not create lookup records')

	await initial.confirmImport()
	assert.ok(initial.validationErrors.includes('Lost confirmation after creating area lookups'))
	assert.deepEqual(writes, [[0, 33, 1, 1]])
	const interrupted = await request('memberSnapshot')
	const createdCategory = areaRows(interrupted.categories).find(row => row.name === category)
	const createdPartner = areaRows(interrupted.partners).find(row => row.name === partner)
	assert.ok(createdCategory && createdPartner)
	assert.deepEqual(areaRows(interrupted.categories), [...areaRows(before.categories), createdCategory])
	assert.deepEqual(areaRows(interrupted.partners), [...areaRows(before.partners), createdPartner])
	assert.equal(interrupted.originals.length, 33)

	const resumed = newImport(view, file)
	await resumed.checkAndPreview()
	assert.deepEqual(resumed.validationErrors, [])
	assert.equal(resumed.skippedCount, 33)
	assert.equal(resumed.previewRows.length, 84)
	assert.deepEqual(resumed.newCategories, [])
	assert.deepEqual(resumed.newPaymentPartners, [])
	await resumed.confirmImport()
	assert.deepEqual(resumed.validationErrors, [])
	assert.deepEqual(writes.slice(1), [[33, 17, 0, 0], [50, 33, 0, 0], [83, 17, 0, 0], [100, 17, 0, 0]])

	const completed = await request('memberSnapshot')
	assert.deepEqual(areaRows(completed.categories), areaRows(interrupted.categories))
	assert.deepEqual(areaRows(completed.partners), areaRows(interrupted.partners))
	assert.deepEqual(completed.originals, payments.map(({ description }) => ({
		user_id: 'alice', project_id: 10, description,
		category_id: createdCategory.id, payment_partner_id: createdPartner.id,
	})))
	const replay = await request(lostPacket)
	assert.equal(replay.skippedCount, 33)
	assert.equal(replay.importedCount, 0)
	assert.deepEqual(await request('memberSnapshot'), completed, 'Replaying the unacknowledged packet must leave the database unchanged')
	console.log('Area admin import passed: repeated names across preview, commit, interruption and replay reuse one area category and one partner.')
} finally {
	close()
}

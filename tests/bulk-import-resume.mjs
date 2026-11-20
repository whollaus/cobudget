import assert from 'node:assert/strict'
import { loadView, newImport, startImportSession } from './bulk-import-harness.mjs'

function verifyDatabase({ rows, entryCount, shareCount }, count) {
	assert.equal(entryCount, count * 4, 'Exactly one shared original and three personal entries per file row')
	assert.equal(shareCount, count * 3)
	assert.equal(rows.length, count * 3)
	const keys = new Set()
	const originals = new Set()
	const personalIds = new Set()
	for (let i = 0; i < count; i++) {
		// At 12.35, the half-cent alternates between the 50% and 30% members.
		const expected = [
			['alice', 1, 5000, i % 2 === 0 ? 618 : 617],
			['bob', 2, 3000, i % 2 === 0 ? 370 : 371],
			['carol', 4, 2000, 247],
		]
		const first = rows[3 * i]
		assert.ok(first.import_key)
		keys.add(first.import_key)
		originals.add(first.original)
		for (const [member, [user, workspace, basisPoints, cents]] of expected.entries()) {
			const row = rows[3 * i + member]
			assert.equal(row.description, 'Payment ' + (i + 1))
			assert.equal(row.original, first.original)
			assert.equal(row.original_cents, 1235)
			assert.deepEqual([row.user_id, row.workspace_id, row.share_basis_points, row.share_cents, row.personal_cents],
				[user, workspace, basisPoints, cents, cents], 'Incorrect allocation for payment ' + (i + 1))
			assert.ok(row.personal > 0)
			personalIds.add(row.personal)
		}
	}
	assert.equal(keys.size, count)
	assert.equal(originals.size, count)
	assert.equal(personalIds.size, count * 3)
}

const { request, close } = startImportSession('alice')

try {
	const precisionView = loadView(async (url, blob) => ({ data: await request(await blob.text()) }))
	for (const amount of ['-1.2300001', '-1.2299999', '-99999999.990000001', '-1230000000000000001e-18']) {
		const row = '{"date":"2026-01-01","\\u0061mount":' + amount + '}'
		const view = newImport(precisionView, new Blob(['{"payments":[' + row + ']}']))
		await view.checkAndPreview()
		assert.match(view.validationErrors.join(' '), /Invalid amount/)
		const packet = '{"importId":"' + 'a'.repeat(64) + '","offset":0,"payments":[' + row + '],"dryRun":true}'
		await assert.rejects(request(packet), e => e.response?.status === 400, 'Raw packets must also reject fractional cents')
	}
	assert.equal((await request('snapshot')).entryCount, 0)
	const exact = newImport(precisionView, new Blob(['{"payments":[{"date":"2026-01-01","amount":-12300e-4,"description":"quoted \\"amount\\":1.2345"}]}']))
	await exact.checkAndPreview()
	assert.deepEqual(exact.validationErrors, [])
	assert.equal(exact.parsedPayments[0].amount, -1.23, 'Exact exponent notation keeps its numeric replay identity')
	assert.equal(exact.parsedPayments[0].description, 'quoted "amount":1.2345')
	const payments = Array.from({ length: 5000 }, (_, i) => ({ date: '2026-01-01', amount: -12.35, description: 'Payment ' + (i + 1) }))
	const file = new Blob([JSON.stringify({ payments })])
	const writes = []
	const previews = []
	let lostPacket
	const view = loadView(async (url, blob) => {
		assert.equal(url, '/apps/cobudget/api/entries/import')
		const body = await blob.text()
		const packet = JSON.parse(body)
		const data = await request(body)
		if (packet.dryRun) {
			previews.push([packet.offset, packet.payments.length])
			assert.equal(data.commitLimit, 33)
		} else {
			writes.push([packet.offset, packet.payments.length])
			assert.equal(data.importedCount, packet.payments.length)
			if (packet.offset === 900 && !lostPacket) {
				lostPacket = body
				throw new Error('Lost confirmation after commit')
			}
		}
		return { data }
	})
	const first = newImport(view, file)
	await first.checkAndPreview()
	assert.deepEqual(first.validationErrors, [])
	const previewBlocks = Array.from({ length: 100 }, (_, i) => [i * 50, 50])
	assert.deepEqual(previews, previewBlocks)
	assert.equal(first.previewRows.length, 5000)
	await first.confirmImport()
	assert.ok(first.validationErrors.includes('Lost confirmation after commit'))
	assert.equal(writes.length, 37)
	assert.deepEqual(writes.at(-1), [900, 33])

	const interrupted = await request('snapshot')
	verifyDatabase(interrupted, 933)
	const resumed = newImport(view, file)
	await resumed.checkAndPreview()
	assert.deepEqual(resumed.validationErrors, [])
	assert.deepEqual(previews, [...previewBlocks, ...previewBlocks])
	assert.equal(resumed.skippedCount, 933)
	assert.deepEqual(resumed.previewRows.map(row => row.rowNumber), Array.from({ length: 4067 }, (_, i) => 934 + i))
	await resumed.confirmImport()
	assert.deepEqual(resumed.validationErrors, [])
	assert.deepEqual(writes[37], [933, 17], 'The first resumed packet finishes the interrupted preview block')
	const expectedWrites = Array.from({ length: 100 }, (_, i) => [[50 * i, 33], [50 * i + 33, 17]]).flat()
	assert.deepEqual(writes, expectedWrites)

	const replay = await request(lostPacket)
	assert.equal(replay.importedCount, 0)
	assert.equal(replay.skippedCount, 33)
	const completed = await request('snapshot')
	verifyDatabase(completed, 5000)
	assert.deepEqual(completed.rows.slice(0, interrupted.rows.length), interrupted.rows, 'Previously saved IDs and allocations survive resumption and replay')
	console.log('Bulk import passed: 33/17 packet resumption, 5,000 originals and 15,000 personal allocations verified in SQLite.')
} finally {
	close()
}

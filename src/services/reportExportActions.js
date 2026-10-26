import axios from './http'
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { generateRemoteUrl } from '@nextcloud/router'
import { downloadTextFile, encodeNextcloudPath, nextcloudCopyName, nextcloudFilePath } from './annualOverview'
import { buildReportExport } from './reportExport'
import { showToast } from './notifications'

export async function copyReportText(contents) {
	// Start the clipboard operation during the click, even when saved payments
	// still need to be read. Safari otherwise loses the required user activation.
	if (navigator.clipboard?.write && typeof ClipboardItem !== 'undefined') {
		const blob = Promise.resolve(contents).then(text => new Blob([text], { type: 'text/plain' }))
		// A permission rejection may happen before the asynchronous data arrives.
		blob.catch(() => {})
		await navigator.clipboard.write([new ClipboardItem({ 'text/plain': blob })])
		return
	}
	const text = await contents
	if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(text); return }
	const focused = document.activeElement, selection = document.getSelection()
	const ranges = selection ? Array.from({ length: selection.rangeCount }, (_, index) => selection.getRangeAt(index).cloneRange()) : []
	const field = document.createElement('textarea')
	field.value = text; field.setAttribute('readonly', ''); Object.assign(field.style, { position: 'fixed', insetInlineStart: '-10000px', top: '0' })
	document.body.appendChild(field)
	try { field.select(); if (!document.execCommand('copy')) throw new Error('Clipboard access denied') }
	finally { field.remove(); focused?.focus?.({ preventScroll: true }); if (selection) { selection.removeAllRanges(); ranges.forEach(range => selection.addRange(range)) } }
}

export async function saveReportExport(filename, contents, texts) {
	let folder
	try {
		folder = await getFilePickerBuilder(texts.annual.saveInNextcloud()).setMultiSelect(false).allowDirectories(true)
			.setMimeTypeFilter(['httpd/unix-directory']).addButton({ label: texts.annual.saveHere(), variant: 'primary', callback() {} }).build().pick()
	} catch (error) { if (error?.name === 'FilePickerClosed') return; throw error }
	const uid = getCurrentUser()?.uid
	if (!uid) throw new Error('Missing Nextcloud user')
	for (let copy = 0; copy < 100; copy++) {
		const storedName = nextcloudCopyName(filename, copy), path = nextcloudFilePath(folder, storedName)
		try {
			await axios.put(generateRemoteUrl(`dav/files/${encodeURIComponent(uid)}${encodeNextcloudPath(path)}`), contents, { headers: { 'Content-Type': 'text/csv; charset=utf-8', 'If-None-Match': '*' } })
			showToast(texts.annual.savedInNextcloud(storedName)); return
		} catch (error) { if (error?.response?.status !== 412) throw error }
	}
	throw new Error('No free Nextcloud file name')
}

export async function deliverReportExport(action, data, context, texts) {
	let filename = context.filename
	const contents = Promise.resolve(data).then(sections => {
		if (context.section) {
			const suffix = String(sections[0]?.title || '').normalize('NFKD').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-|-$/g, '').slice(0, 100)
			filename = filename.replace(/\.csv$/, `-${suffix}.csv`)
		}
		return buildReportExport({ metadata: context.section ? [] : context.metadata, sections, includeSectionTitles: !context.section }, action === 'copy' ? '\t' : ';')
	})
	if (action === 'copy') { await copyReportText(contents); showToast(texts.common.copiedToClipboard()); return }
	const csv = await contents
	if (action === 'save') await saveReportExport(filename, csv, texts)
	else { downloadTextFile(filename, csv); showToast(texts.common.exportCsvCreated()) }
}

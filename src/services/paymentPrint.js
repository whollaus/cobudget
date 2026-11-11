import { formatCalendarLabel } from '../utils/formatDate'
import { formatMoney as defaultFormatMoney, formatSignedMoney as defaultFormatSignedMoney, getAppLocale } from '../utils/formatMoney'

const escapeHtml = value => String(value ?? '')
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#39;')

const printStyles = `
	@page { size: A4 landscape; margin: 10mm; }
	* { box-sizing: border-box; }
	body { margin: 0; color: #222; background: #fff; font: 9pt/1.4 Arial, sans-serif; }
	h1 { margin: 0 0 3mm; font-size: 17pt; line-height: 1.25; }
	h2 { margin: 4mm 0 2mm; font-size: 12pt; break-after: avoid; }
	.report-metadata { display: grid; grid-template-columns: max-content minmax(0, 1fr) max-content minmax(0, 1fr); gap: 1mm 4mm; margin: 0 0 4mm; padding: 0 0 3mm; border-bottom: 0.3mm solid #ccc; font-size: 8pt; }
	.report-metadata dt, .report-metadata dd { margin: 0; padding: 0; overflow-wrap: anywhere; }
	.report-metadata dt { color: #555; }
	.report-details { margin: 0 0 5mm; font-size: 9pt; overflow-wrap: anywhere; }
	table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0 0 4mm; font: inherit; font-size: 8.5pt; }
	caption { text-align: start; font-size: 11pt; font-weight: bold; padding: 1.5mm 0; break-after: avoid; white-space: normal; }
	thead { display: table-header-group; break-inside: avoid; break-after: avoid; }
	tfoot { display: table-row-group; }
	.report-compact-table { break-inside: avoid; }
	tr { break-inside: avoid; }
	th, td { padding: 0.9mm 0.5mm; border-bottom: 0.2mm solid #ddd; text-align: end; vertical-align: middle; white-space: nowrap; }
	th:first-child, tbody th { text-align: start; white-space: normal; overflow-wrap: anywhere; }
	tbody th { font-weight: normal; }
	thead th { background: #eee; font-size: 7pt; }
	td { font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
	.annual-year-total, .annual-total th, .annual-total td { font-weight: bold; }
	.annual-total { background: #f3f3f3; }
	.annual-child th { padding-inline-start: 4mm; }
	.negative-balance { color: #a00; }
	.annual-month-empty { color: #888; }
	.annual-empty-message { padding: 2mm; text-align: center; }
	.report-summary { display: flex; gap: 4mm; margin: 0 0 4mm; break-inside: avoid; }
	.report-summary-item { display: flex; align-items: baseline; justify-content: space-between; gap: 3mm; flex: 1; padding: 2mm 3mm; border: 0.2mm solid #ddd; border-radius: 2mm; }
	.report-summary-label { color: #555; font-size: 8.5pt; }
	.report-summary-amount { font-size: 14pt; white-space: nowrap; }
	.report-section { min-width: 0; }
	.report-payment-list th, .report-payment-list td,
	.report-settlement-balances th, .report-settlement-balances td,
	.report-settlement-repayments th, .report-settlement-repayments td { white-space: normal; text-align: start; }
	.report-settlement-balances th.num, .report-settlement-balances td.num,
	.report-settlement-repayments th.num, .report-settlement-repayments td.num { text-align: end; white-space: nowrap; }
	.report-settlement-balances th:first-child, .report-settlement-balances th:nth-child(3) { width: 30%; }
	.report-settlement-repayments th:last-child { width: 28%; }
	.report-payment-list thead th:first-child { width: 22mm; }
	.report-payment-list thead th:nth-child(2) { width: 30%; }
	.report-payment-list thead th.col-amount, .report-payment-list .col-amount { width: 29mm; text-align: end; white-space: nowrap; }
	.report-payment-list thead th.col-paid-by, .report-payment-list .col-paid-by { width: 32mm; }
	.report-payment-list .col-date { white-space: nowrap; }
	.payment-print-meta { color: #555; font-size: 8pt; }
	.group-section-row { break-after: avoid; page-break-after: avoid; }
	.group-section-row th { background: #eee; font-weight: bold; }
	.group-section-row .group-meta { color: #555; font-weight: normal; font-size: 8pt; }
	.subtotal-row th, .subtotal-row td { font-weight: bold; }
	.month-subtotal-row { background: #f3f3f3; }
	.report-section p { margin: 2mm 0 4mm; }
	@media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
`

function resolveEntryTimestamp(entry, { isFutureFilter = false, nowSeconds = Math.floor(Date.now() / 1000) } = {}) {
	const date = Number(entry?.date || 0)
	const recurrenceNextDate = Number(entry?.recurrence_next_date || 0)
	if (isFutureFilter && date <= nowSeconds && recurrenceNextDate > nowSeconds) {
		return recurrenceNextDate
	}
	return date || recurrenceNextDate || 0
}

function dateFromTimestamp(timestamp) {
	if (!timestamp) {
		return null
	}
	const date = new Date(Number(timestamp) * 1000)
	return Number.isNaN(date.getTime()) ? null : date
}

function formatPrintDate(timestamp, locale = getAppLocale()) {
	const date = dateFromTimestamp(timestamp)
	if (!date) {
		return '–'
	}
	return date.toLocaleDateString(locale, {
		day: '2-digit',
		month: '2-digit',
		year: 'numeric',
	})
}

function resolvePersonalAmountCents(entry, amountResolver) {
	if (typeof amountResolver === 'function') {
		const resolved = Number(amountResolver(entry))
		if (Number.isFinite(resolved)) {
			return Math.round(Math.abs(resolved) * 100)
		}
	}
	if (entry?.personal_amount_cents !== undefined && entry?.personal_amount_cents !== null) {
		const cents = parseInt(entry.personal_amount_cents, 10)
		if (Number.isFinite(cents)) {
			return Math.abs(cents)
		}
	}
	const fallback = Number(entry?.personal_amount ?? entry?.amount ?? 0)
	return Number.isFinite(fallback) ? Math.round(Math.abs(fallback) * 100) : 0
}

function resolveFullAmountCents(entry) {
	if (entry?.amount_cents !== undefined && entry?.amount_cents !== null) {
		const cents = parseInt(entry.amount_cents, 10)
		if (Number.isFinite(cents)) {
			return Math.abs(cents)
		}
	}
	const fallback = Number(entry?.amount ?? 0)
	return Number.isFinite(fallback) ? Math.round(Math.abs(fallback) * 100) : 0
}

function resolveSignedEntryCents(entry, amountResolver) {
	const magnitude = resolvePersonalAmountCents(entry, amountResolver)
	return entry?.type === 'income' ? magnitude : -magnitude
}

function buildEntryLabelNotes(entry, { texts = {}, labelSettings = {}, includeSettled = true } = {}) {
	const notes = []
	if (labelSettings.important !== false && entry?.is_important) {
		notes.push(texts.labels?.important?.() || 'Wichtig')
	}
	if (labelSettings.review !== false && entry?.needs_review) {
		notes.push(texts.labels?.review?.() || 'Prüfen')
	}
	if (labelSettings.fixedCosts !== false && entry?.is_fixed_cost) {
		notes.push(texts.labels?.fixedCosts?.() || 'Fixkosten')
	}
	if (labelSettings.childRelated !== false && entry?.is_child_related) {
		notes.push(texts.labels?.children?.() || 'Kinder')
	}
	if (labelSettings.subscriptions !== false && entry?.is_subscription) {
		notes.push(texts.labels?.subscription?.() || 'Abo')
	}
	if (labelSettings.taxRelevant !== false && entry?.is_tax_relevant) {
		notes.push(texts.labels?.taxRelevant?.() || 'Steuerrelevant')
	}
	if (labelSettings.insurance !== false && entry?.is_insurance) {
		notes.push(texts.labels?.insurance?.() || 'Versicherung')
	}
	if (entry?.recurrence_interval && entry.recurrence_interval !== 'none') {
		notes.push(texts.entry?.recurrenceActive?.() || 'Wiederkehrend')
	} else if (entry?.recurrence_parent_id) {
		notes.push(texts.entry?.createdFromRecurrence?.() || 'Aus Wiederholung erstellt')
	}
	if (includeSettled && entry?.is_settled) {
		notes.push(texts.entry?.settled?.() || 'Abgerechnet')
	}
	return notes
}

export function buildPaymentPrintGroups(entries = [], {
	sortBy = 'date',
	isFutureFilter = false,
	amountResolver = null,
	locale = getAppLocale(),
} = {}) {
	const list = Array.isArray(entries) ? entries : []
	if (sortBy !== 'date' || list.length === 0) {
		return list.map((entry, index) => ({
			type: 'entry',
			key: `entry-${entry?.id ?? index}`,
			entry,
		}))
	}

	const summaries = {}
	list.forEach(entry => {
		const date = dateFromTimestamp(resolveEntryTimestamp(entry, { isFutureFilter }))
		if (!date) {
			return
		}
		const yearKey = `year-${date.getFullYear()}`
		const monthKey = `month-${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
		const cents = resolvePersonalAmountCents(entry, amountResolver)
		;[yearKey, monthKey].forEach(key => {
			if (!summaries[key]) {
				summaries[key] = {
					incomeCents: 0,
					expenseCents: 0,
					balanceCents: 0,
					count: 0,
				}
			}
			summaries[key].count += 1
			if (entry?.type === 'income') {
				summaries[key].incomeCents += cents
				summaries[key].balanceCents += cents
			} else {
				summaries[key].expenseCents += cents
				summaries[key].balanceCents -= cents
			}
		})
	})

	const rows = []
	let currentYearGroup = null
	let currentMonthGroup = null

	const pushClosingRow = group => {
		if (!group) {
			return
		}
		rows.push({
			type: 'group-closing',
			key: `${group.key}-closing`,
			level: group.level,
			label: group.label,
			summary: summaries[group.key] || { incomeCents: 0, expenseCents: 0, balanceCents: 0, count: 0 },
		})
	}

	list.forEach((entry, index) => {
		const date = dateFromTimestamp(resolveEntryTimestamp(entry, { isFutureFilter }))
		if (!date) {
			rows.push({
				type: 'entry',
				key: `entry-${entry?.id ?? index}`,
				entry,
			})
			return
		}

		const yearKey = `year-${date.getFullYear()}`
		const monthKey = `month-${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
		const yearLabel = date.toLocaleDateString(locale, { year: 'numeric' })
		const monthLabel = formatCalendarLabel(date, { month: 'long' })
		const monthHeaderLabel = formatCalendarLabel(date, { month: 'long', year: 'numeric' })

		const yearChanged = !currentYearGroup || currentYearGroup.key !== yearKey
		const monthChanged = yearChanged || !currentMonthGroup || currentMonthGroup.key !== monthKey

		if (monthChanged && currentMonthGroup) {
			pushClosingRow(currentMonthGroup)
			currentMonthGroup = null
		}
		if (yearChanged && currentYearGroup) {
			pushClosingRow(currentYearGroup)
			currentYearGroup = null
		}

		if (yearChanged) {
			currentYearGroup = {
				key: yearKey,
				level: 'year',
				label: yearLabel,
			}
		}
		if (monthChanged) {
			currentMonthGroup = {
				key: monthKey,
				level: 'month',
				label: monthLabel,
				headerLabel: monthHeaderLabel,
			}
			rows.push({
				type: 'group-header',
				key: `${monthKey}-header`,
				label: monthHeaderLabel,
				summary: summaries[monthKey] || { incomeCents: 0, expenseCents: 0, balanceCents: 0, count: 0 },
			})
		}

		rows.push({
			type: 'entry',
			key: `entry-${entry?.id ?? index}`,
			entry,
		})
	})

	pushClosingRow(currentMonthGroup)
	pushClosingRow(currentYearGroup)

	return rows
}

export function createPaymentsPrintDocument({
	title = '',
	periodLabel = '',
	filterLabels = [],
	metadataRows = null,
	settlement = null,
	entries = [],
	currency = 'EUR',
	enableIncomes = true,
	enableProjects = true,
	showSummaryCards = true,
	groupByDate = true,
	showPaidBy = false,
	payerNameResolver = null,
	typeFilter = 'all',
	isFutureFilter = false,
	sortBy = 'date',
	projects = [],
	labelSettings = {},
	amountResolver = null,
	formatMoney = null,
	formatSignedMoney = null,
	texts = {},
	locale = getAppLocale(),
} = {}) {
	const list = Array.isArray(entries) ? entries : []
	const moneyFormatter = typeof formatMoney === 'function'
		? value => formatMoney(value)
		: value => defaultFormatMoney(value, currency)
	const signedMoneyFormatter = typeof formatSignedMoney === 'function'
		? value => formatSignedMoney(value)
		: value => defaultFormatSignedMoney(value, currency)

	const projectList = Array.isArray(projects) ? projects : []
	const projectMap = new Map(projectList.map(project => [String(project.id), project]))
	const showAreaColumn = Boolean(enableProjects && (projectList.length > 0 || list.some(entry => entry?.project_id)))

	let totalIncomeCents = 0
	let totalExpenseCents = 0
	list.forEach(entry => {
		const cents = resolvePersonalAmountCents(entry, amountResolver)
		if (entry?.type === 'income') {
			totalIncomeCents += cents
		} else {
			totalExpenseCents += cents
		}
	})
	const totalBalanceCents = totalIncomeCents - totalExpenseCents

	const pageTitle = [
		'CoBudget',
		title || texts.payments?.title?.() || 'Zahlungen',
		periodLabel,
	].filter(Boolean).join(' – ')
	const headingTitle = settlement ? (title || pageTitle) : pageTitle

	const defaultMetadataRows = [
		[texts.annual?.selectedPeriod?.() || 'Ausgewählter Zeitraum', periodLabel],
		[
			texts.annual?.amountScope?.() || 'Angezeigte Beträge',
			[texts.annual?.personalShare?.() || 'Eigener Anteil', currency].filter(Boolean).join(' · '),
		],
		[texts.annual?.paymentCount?.() || 'Anzahl Zahlungen', list.length.toLocaleString(locale)],
		filterLabels.length
			? [texts.annual?.filterSummary?.() || 'Zahlungsfilter', filterLabels.join(' · ')]
			: null,
	]
	const resolvedMetadataRows = (Array.isArray(metadataRows) ? metadataRows : defaultMetadataRows)
		.filter(row => row && row[1] !== undefined && row[1] !== null && String(row[1]) !== '')

	const metadataHtml = resolvedMetadataRows.map(([label, value]) => `
		<dt>${escapeHtml(label)}</dt>
		<dd>${escapeHtml(value)}</dd>
	`).join('')

	const summaryCards = []
	if (showSummaryCards && !settlement) {
		if (enableIncomes && typeFilter !== 'expense') {
			summaryCards.push({
				label: texts.dashboard?.incomes?.() || 'Einnahmen',
				value: signedMoneyFormatter(totalIncomeCents / 100),
				negative: false,
			})
		}
		if (typeFilter !== 'income') {
			summaryCards.push({
				label: texts.dashboard?.expenses?.() || 'Ausgaben',
				value: moneyFormatter(totalExpenseCents === 0 ? 0 : -(totalExpenseCents / 100)),
				negative: totalExpenseCents > 0,
			})
		}
		if (enableIncomes && typeFilter === 'all') {
			summaryCards.push({
				label: texts.dashboard?.balance?.() || 'Saldo',
				value: signedMoneyFormatter(totalBalanceCents / 100),
				negative: totalBalanceCents < 0,
			})
		}
	}

	const summaryHtml = summaryCards.length
		? `<div class="report-summary">${summaryCards.map(card => `
			<div class="report-summary-item">
				<span class="report-summary-label">${escapeHtml(card.label)}</span>
				<strong class="report-summary-amount${card.negative ? ' negative-balance' : ''}">${escapeHtml(card.value)}</strong>
			</div>
		`).join('')}</div>`
		: ''

	const renderSettlementBalancesSection = () => {
		if (!settlement) {
			return ''
		}
		const balances = Array.isArray(settlement.balances) ? settlement.balances : []
		if (balances.length === 0) {
			return ''
		}
		const formatSharePercent = basisPoints => (Number(basisPoints) / 100).toLocaleString(locale, {
			maximumFractionDigits: 2,
		})
		const rowsHtml = balances.map(balance => {
			const memberName = String(balance?.displayName || balance?.userId || '').trim() || '–'
			const paidAmount = balance?.paid !== undefined && balance?.paid !== null
				? Number(balance.paid)
				: Number(balance?.paidCents || 0) / 100
			const fairShareAmount = balance?.fairShare !== undefined && balance?.fairShare !== null
				? Number(balance.fairShare)
				: Number(balance?.fairShareCents || 0) / 100
			const balanceAmount = balance?.balance !== undefined && balance?.balance !== null
				? Number(balance.balance)
				: Number(balance?.balanceCents || 0) / 100
			const sharePercent = balance?.shareBasisPoints !== undefined && balance?.shareBasisPoints !== null
				? ` (${formatSharePercent(balance.shareBasisPoints)}%)`
				: ''
			const hasIncomeDetails = Boolean(balance?.receivedCents || balance?.incomeShareCents)
			const receivedAmount = balance?.received !== undefined && balance?.received !== null
				? Number(balance.received)
				: Number(balance?.receivedCents || 0) / 100
			const incomeShareAmount = balance?.incomeShare !== undefined && balance?.incomeShare !== null
				? Number(balance.incomeShare)
				: Number(balance?.incomeShareCents || 0) / 100
			const paidMeta = hasIncomeDetails
				? `<div class="payment-print-meta">${escapeHtml(texts.areaDetail?.received?.() || 'Erhalten')}: ${escapeHtml(moneyFormatter(receivedAmount))}</div>`
				: ''
			const shareMeta = hasIncomeDetails
				? `<div class="payment-print-meta">${escapeHtml(texts.areaDetail?.incomeShare?.() || 'Einnahmenanteil')}: ${escapeHtml(moneyFormatter(incomeShareAmount))}</div>`
				: ''
			const formattedBalance = `${balanceAmount > 0 ? '+' : ''}${moneyFormatter(balanceAmount)}`
			return `
				<tr class="settlement-balance-print-row">
					<td class="col-member">${escapeHtml(memberName)}</td>
					<td class="num col-paid">${escapeHtml(moneyFormatter(paidAmount))}${paidMeta}</td>
					<td class="num col-share">${escapeHtml(`${moneyFormatter(fairShareAmount)}${sharePercent}`)}${shareMeta}</td>
					<td class="num col-balance${balanceAmount < 0 ? ' negative-balance' : ''}"><strong>${escapeHtml(formattedBalance)}</strong></td>
				</tr>
			`
		}).join('')

		return `
			<section class="report-section">
				<table class="report-settlement-balances report-compact-table">
					<caption>${escapeHtml(texts.settlements?.balanceBeforeSettlement?.() || 'Mitgliedersalden vor der Abrechnung')}</caption>
					<thead>
						<tr>
							<th scope="col" class="col-member">${escapeHtml(texts.memberBalances?.member?.() || 'Mitglied')}</th>
							<th scope="col" class="num col-paid">${escapeHtml(texts.memberBalances?.paid?.() || 'Bezahlt')}</th>
							<th scope="col" class="num col-share">${escapeHtml(texts.memberBalances?.ownShare?.() || 'Eigener Anteil')}</th>
							<th scope="col" class="num col-balance">${escapeHtml(texts.memberBalances?.balance?.() || 'Saldo')}</th>
						</tr>
					</thead>
					<tbody>${rowsHtml}</tbody>
				</table>
			</section>
		`
	}

	const renderSettlementRepaymentsSection = () => {
		if (!settlement) {
			return ''
		}
		const transfers = Array.isArray(settlement.transfers) ? settlement.transfers : []
		const sectionTitle = texts.settlements?.repayments?.() || 'Vorgeschlagene Rückzahlungen'
		if (transfers.length === 0) {
			return `
				<section class="report-section">
					<h2>${escapeHtml(sectionTitle)}</h2>
					<p>${escapeHtml(texts.settlements?.noRepaymentNeeded?.() || 'Keine Rückzahlung erforderlich.')}</p>
				</section>
			`
		}
		const rowsHtml = transfers.map(transfer => {
			const fromName = String(transfer?.fromDisplayName || transfer?.fromUserId || '').trim() || '–'
			const toName = String(transfer?.toDisplayName || transfer?.toUserId || '').trim() || '–'
			const transferAmount = transfer?.amount !== undefined && transfer?.amount !== null
				? Number(transfer.amount)
				: Number(transfer?.amountCents || 0) / 100
			return `
				<tr class="settlement-repayment-print-row">
					<td class="col-from">${escapeHtml(fromName)}</td>
					<td class="col-to">${escapeHtml(toName)}</td>
					<td class="num col-amount"><strong>${escapeHtml(moneyFormatter(transferAmount))}</strong></td>
				</tr>
			`
		}).join('')

		return `
			<section class="report-section">
				<table class="report-settlement-repayments report-compact-table">
					<caption>${escapeHtml(sectionTitle)}</caption>
					<thead>
						<tr>
							<th scope="col" class="col-from">${escapeHtml(texts.repayments?.from?.() || 'Von')}</th>
							<th scope="col" class="col-to">${escapeHtml(texts.repayments?.to?.() || 'An')}</th>
							<th scope="col" class="num col-amount">${escapeHtml(texts.repayments?.amount?.() || 'Betrag')}</th>
						</tr>
					</thead>
					<tbody>${rowsHtml}</tbody>
				</table>
			</section>
		`
	}

	const dateColumnLabel = isFutureFilter
		? (texts.entry?.tablePlannedPayment?.() || 'Geplante Zahlung')
		: (texts.entry?.tableDate?.() || 'Datum')
	const labelColspan = 4 + (showAreaColumn ? 1 : 0)
	const columnCount = labelColspan + 1 + (showPaidBy ? 1 : 0)
	const tableCaption = settlement
		? (texts.settlements?.entries?.() || texts.payments?.title?.() || 'Zahlungen')
		: (title || texts.payments?.title?.() || 'Zahlungen')

	const renderAreaCell = entry => {
		if (!showAreaColumn) {
			return ''
		}
		if (!entry?.project_id) {
			return `<td class="col-area">${escapeHtml(texts.annual?.withoutArea?.() || texts.entry?.personalAssignment?.() || 'Ohne Bereich')}</td>`
		}
		const project = projectMap.get(String(entry.project_id))
		const areaName = project?.name || entry?.project_name || `${texts.common?.unknownArea?.() || 'Bereich'} #${entry.project_id}`
		const personalCents = resolvePersonalAmountCents(entry, amountResolver)
		const fullCents = resolveFullAmountCents(entry)
		const shareDiffers = fullCents > 0 && fullCents !== personalCents
		const shareMeta = shareDiffers
			? `<div class="payment-print-meta">${escapeHtml(texts.entry?.totalAmount?.(moneyFormatter(fullCents / 100)) || `Gesamt: ${moneyFormatter(fullCents / 100)}`)}</div>`
			: ''
		return `<td class="col-area">${escapeHtml(areaName)}${shareMeta}</td>`
	}

	const renderPaidByCell = entry => {
		if (!showPaidBy) {
			return ''
		}
		const resolvedPayer = typeof payerNameResolver === 'function'
			? payerNameResolver(entry)
			: (entry?.paid_by_display_name || entry?.user_display_name || entry?.paid_by_user_id || entry?.user_id || '')
		const payerLabel = String(resolvedPayer || '').trim() || '–'
		return `<td class="col-paid-by">${escapeHtml(payerLabel)}</td>`
	}

	const renderEntryRow = entry => {
		const timestamp = resolveEntryTimestamp(entry, { isFutureFilter })
		const formattedDate = formatPrintDate(timestamp, locale)
		const description = String(entry?.description || '').trim()
		const descriptionHtml = description
			? `<div class="payment-description-main">${escapeHtml(description)}</div>`
			: ''
		const labelNotes = buildEntryLabelNotes(entry, {
			texts,
			labelSettings,
			includeSettled: !settlement,
		})
		const metaHtml = labelNotes.length
			? `<div class="payment-print-meta">${escapeHtml(labelNotes.join(' · '))}</div>`
			: ''
		const categoryLabel = String(entry?.category_name || '').trim() || '–'
		const partnerLabel = String(entry?.paymentPartner || '').trim() || '–'
		const signedCents = resolveSignedEntryCents(entry, amountResolver)
		const formattedAmount = enableIncomes && entry?.type === 'income'
			? signedMoneyFormatter(Math.abs(signedCents) / 100)
			: moneyFormatter(-(Math.abs(signedCents) / 100))

		return `
			<tr class="entry-print-row">
				<td class="col-date">${escapeHtml(formattedDate)}</td>
				<td class="col-description">
					${descriptionHtml}
					${metaHtml}
				</td>
				<td class="col-category">${escapeHtml(categoryLabel)}</td>
				<td class="col-partner">${escapeHtml(partnerLabel)}</td>
				${renderAreaCell(entry)}
				<td class="num col-amount${signedCents < 0 ? ' negative-balance' : ''}">${escapeHtml(formattedAmount)}</td>
				${renderPaidByCell(entry)}
			</tr>
		`
	}

	const formatSummaryAmount = summary => {
		if (typeFilter === 'income') {
			return {
				text: signedMoneyFormatter((summary?.incomeCents || 0) / 100),
				negative: false,
			}
		}
		if (!enableIncomes || typeFilter === 'expense') {
			const expenseCents = summary?.expenseCents || 0
			return {
				text: moneyFormatter(expenseCents === 0 ? 0 : -(expenseCents / 100)),
				negative: expenseCents > 0,
			}
		}
		const balanceCents = summary?.balanceCents || 0
		return {
			text: signedMoneyFormatter(balanceCents / 100),
			negative: balanceCents < 0,
		}
	}

	const rows = buildPaymentPrintGroups(list, {
		sortBy: groupByDate === false ? 'ungrouped' : sortBy,
		isFutureFilter,
		amountResolver,
		locale,
	})

	const bodyHtml = rows.map(row => {
		if (row.type === 'group-header') {
			const countText = texts.entry?.groupPaymentCount?.(row.summary.count) || `${row.summary.count}`
			return `
				<tr class="group-section-row">
					<th scope="colgroup" colspan="${columnCount}">
						${escapeHtml(row.label)} <span class="group-meta">· ${escapeHtml(countText)}</span>
					</th>
				</tr>
			`
		}
		if (row.type === 'group-closing') {
			const closingLabel = texts.entry?.groupClosingTitle?.(row.label) || `${row.label} gesamt`
			const amount = formatSummaryAmount(row.summary)
			const rowClass = row.level === 'year' ? 'subtotal-row annual-total' : 'subtotal-row month-subtotal-row'
			return `
				<tr class="${rowClass}">
					<th scope="row" colspan="${labelColspan}">${escapeHtml(closingLabel)}</th>
					<td class="num col-amount${amount.negative ? ' negative-balance' : ''}">${escapeHtml(amount.text)}</td>
					${showPaidBy ? '<td class="col-paid-by"></td>' : ''}
				</tr>
			`
		}
		return renderEntryRow(row.entry)
	}).join('')

	const grandTotalSummary = {
		incomeCents: totalIncomeCents,
		expenseCents: totalExpenseCents,
		balanceCents: totalBalanceCents,
		count: list.length,
	}
	const grandTotalAmount = formatSummaryAmount(grandTotalSummary)
	const grandTotalLabel = `${texts.analytics?.total?.() || 'Gesamt'} (${texts.entry?.groupPaymentCount?.(list.length) || list.length})`

	const contentHtml = list.length > 0
		? `
			<section class="report-section">
				<table class="report-payment-list${rows.length <= 12 ? ' report-compact-table' : ''}">
					<caption>${escapeHtml(tableCaption)}</caption>
					<thead>
						<tr>
							<th scope="col" class="col-date">${escapeHtml(dateColumnLabel)}</th>
							<th scope="col" class="col-description">${escapeHtml(texts.entry?.tableDescription?.() || 'Beschreibung')}</th>
							<th scope="col" class="col-category">${escapeHtml(texts.entry?.tableCategory?.() || 'Kategorie')}</th>
							<th scope="col" class="col-partner">${escapeHtml(texts.entry?.tablePaymentPartner?.() || 'Von / An')}</th>
							${showAreaColumn ? `<th scope="col" class="col-area">${escapeHtml(texts.analytics?.area?.() || 'Bereich')}</th>` : ''}
							<th scope="col" class="num col-amount">${escapeHtml(texts.entry?.tableAmount?.(currency) || 'Betrag')}</th>
							${showPaidBy ? `<th scope="col" class="col-paid-by">${escapeHtml(texts.entry?.tablePaidBy?.() || 'Bezahlt hat')}</th>` : ''}
						</tr>
					</thead>
					<tbody>${bodyHtml}</tbody>
					<tfoot>
						<tr class="annual-total grand-total-row">
							<th scope="row" colspan="${labelColspan}">${escapeHtml(grandTotalLabel)}</th>
							<td class="num col-amount${grandTotalAmount.negative ? ' negative-balance' : ''}">${escapeHtml(grandTotalAmount.text)}</td>
							${showPaidBy ? '<td class="col-paid-by"></td>' : ''}
						</tr>
					</tfoot>
				</table>
			</section>
		`
		: `<section class="report-section">${settlement ? `<h2>${escapeHtml(tableCaption)}</h2>` : ''}<p class="annual-empty-message">${escapeHtml(settlement ? (texts.settlements?.noEntries?.() || 'Keine Zahlungen') : (texts.common?.noFilteredEntriesTitle?.() || texts.common?.noEntriesTitle?.() || 'Keine Zahlungen'))}</p></section>`

	return `<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>${escapeHtml(pageTitle)}</title>
	<style>${printStyles}</style>
</head>
<body>
	<h1>${escapeHtml(headingTitle)}</h1>
	<dl class="report-metadata">${metadataHtml}</dl>
	${summaryHtml}
	${renderSettlementBalancesSection()}
	${renderSettlementRepaymentsSection()}
	${contentHtml}
</body>
</html>`
}

export async function printPaymentsOverview(doc, options = {}) {
	if (!doc?.body) {
		throw new Error('Print document is unavailable')
	}
	const win = doc.defaultView || window
	doc.getElementById('cobudget-payments-print-frame')?.remove()

	const frame = doc.createElement('iframe')
	frame.id = 'cobudget-payments-print-frame'
	frame.title = options.title || ''
	frame.setAttribute('aria-hidden', 'true')
	frame.tabIndex = -1
	Object.assign(frame.style, {
		position: 'fixed',
		left: '-10000px',
		top: '0',
		width: '277mm',
		height: '1600px',
		border: '0',
		pointerEvents: 'none',
	})
	doc.body.appendChild(frame)

	try {
		const printWindow = frame.contentWindow
		const printDocument = frame.contentDocument || printWindow?.document
		if (!printWindow || !printDocument) {
			throw new Error('Print frame is unavailable')
		}

		printDocument.open()
		printDocument.write(createPaymentsPrintDocument(options))
		printDocument.close()

		await (printDocument.fonts?.ready || Promise.resolve())
		await new Promise(resolve => win.requestAnimationFrame(() => win.requestAnimationFrame(resolve)))
		frame.style.height = `${Math.max(printDocument.documentElement?.scrollHeight || 0, printDocument.body?.scrollHeight || 0, 1200)}px`
		await new Promise(resolve => win.requestAnimationFrame(resolve))

		printWindow.addEventListener('afterprint', () => win.setTimeout(() => frame.remove(), 0), { once: true })
		printWindow.focus()
		printWindow.print()
	} catch (error) {
		frame.remove()
		throw error
	}
}

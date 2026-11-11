import assert from 'node:assert/strict'
import fs from 'node:fs'
const load = file => import(`data:text/javascript;charset=utf-8,${encodeURIComponent(fs.readFileSync(new URL(`../${file}`, import.meta.url), 'utf8'))}`)
const summary = await load('src/services/paymentSummary.js')
const annual = await load('src/services/annualOverview.js')
const now = new Date(2026, 9, 29, 12)
const base = { search: '', type: 'all', status: 'all', tags: 'all', timeRange: 'all', hasReminder: 'all', hasAttachment: 'all', projectId: null, categoryId: null, paymentPartnerId: null, hashtagId: null, includeFuturePayments: false }
assert.equal(summary.defaultPaymentPeriod({}, base).timeRange, 'currentYear')
assert.equal(summary.defaultPaymentPeriod({ timeRange: 'all' }, base).timeRange, 'all')
assert.equal(summary.defaultPaymentPeriod({}, base, true).timeRange, 'all', 'Drilldowns do not inherit a default year')
assert.equal(summary.defaultPaymentPeriod({}, { ...base, tags: 'future' }).timeRange, 'all', 'The planned-payments shortcut still includes later years')
const filters = { ...base, search: 'Strom', projectId: 4, narrowLabels: 'important,review', amountMin: '12.50', hasAttachment: 'false' }
const calendar = summary.paymentPickerFilters(filters, { timeRange: 'calendar-selection', periodYears: [2026, 2025], periodMonths: [2, 1] })
assert.equal(calendar.periodYears, '2025,2026')
assert.equal(calendar.periodMonths, '1,2')
assert.equal(summary.paymentCalendarIncludesMonth(calendar, new Date(2025, 1, 1)), true)
assert.equal(summary.paymentCalendarIncludesMonth(calendar, new Date(2026, 2, 1)), false)
assert.equal(summary.paymentCalendarIncludesMonth(calendar, new Date(2024, 0, 1)), false)
assert.equal(summary.paymentSummaryTarget('balance', calendar, now).filters.periodYears, '2025,2026')
assert.deepEqual(summary.paymentPickerFilters(calendar, { timeRange: 'all' }), { ...filters, dateFrom: null, dateTo: null })
const year = summary.paymentYearFilters(filters, 2024, now)
assert.equal(summary.paymentPeriodYear(year, now), 2024)
assert.equal(new Date(year.dateTo * 1000).getDate(), 31)
for (const key of ['search', 'projectId', 'narrowLabels', 'amountMin', 'hasAttachment']) assert.equal(year[key], filters[key])
const all = summary.paymentYearFilters(year, 'all', now)
assert.equal(all.timeRange, 'all'); assert.equal(all.dateFrom, null); assert.equal(all.search, 'Strom')
const month = summary.paymentPeriodBounds({ timeRange: 'currentMonth' }, now)
assert.equal(new Date(month.dateFrom * 1000).getDate(), 1)
assert.equal(new Date(month.dateTo * 1000).getDate(), 31, 'Inclusive local month end spans daylight saving correctly')
assert.equal(summary.paymentPeriodYear({ ...base, timeRange: 'custom', ...month }, now), 'custom')
const current = summary.paymentYearFilters({ ...base, search: 'Strom', projectId: 4 }, 2026, now)
const report = summary.paymentSummaryTarget('balance', current, now)
assert.equal(report.name, 'annual-overview')
assert.equal(report.query.search, 'Strom'); assert.equal(report.query.projectId, '4')
assert.equal(report.query.dateFrom, '2026-01-01'); assert.equal(report.query.dateTo, '2026-12-31')
const monthReport = summary.paymentSummaryTarget('current-month', current, now)
assert.equal(monthReport.query.dateFrom, '2026-10-01'); assert.equal(monthReport.query.dateTo, '2026-10-31')
for (const special of [{ narrowProjects: '4,8' }, { hasAttachment: 'false' }, { categoryId: 8 }, { type: 'income' }, { includeFuturePayments: true }, { status: 'settled' }, { timeRange: 'all' }]) {
 const target = summary.paymentSummaryTarget('balance', { ...current, ...special }, now)
 assert.equal(target.name, 'payments', 'Unsupported report filters must remain effective in a payment drilldown')
 for (const [key,value] of Object.entries(special)) assert.equal(target.filters[key], value)
 assert.equal(target.filters.search, 'Strom')
}
const income = summary.paymentSummaryTarget('income', year, now)
assert.equal(income.filters.type, 'income'); assert.equal(income.filters.hasAttachment, 'false'); assert.equal(income.filters.dateFrom, year.dateFrom)
const label = summary.paymentSummaryTarget('review', { ...year, tags: 'fixedCost' }, now)
assert.equal(label.filters.tags, 'review'); assert.equal(label.filters.narrowLabels, year.narrowLabels)
assert.deepEqual(JSON.parse(label.filters.reportFilters), [{ dimension: 'labels', id: 'fixedCost', direct: true }])
const future = summary.paymentSummaryTarget('planned', { ...year, tags: 'important' }, now)
assert.equal(future.filters.tags, 'future'); assert.equal(future.filters.dateFrom, year.dateFrom)
assert.equal(JSON.parse(future.filters.reportFilters)[0].id, 'important')
// A query must survive every report hop and appear in exports/chips, not only the filter input.
const selection = annual.annualSelection({ ...report.query, search: 'Strom & Wärme' })
assert.equal(annual.annualRouteQuery(selection).search, 'Strom & Wärme')
assert.equal(annual.annualRequestParams(selection, { comparison: 'year' }).search, 'Strom & Wärme')
assert.equal(annual.annualFilterChips(selection).find(chip => chip.key === 'search').label, '“Strom & Wärme”')
assert.equal(annual.annualSelection({ ...selection, search: '' }).search, undefined)
const payment = annual.annualPaymentRoute(selection, { id: 'total', type: 'expense' }, { dateFrom: month.dateFrom, dateTo: month.dateTo })
assert.equal(payment.query.search, 'Strom & Wärme')
console.log('Payment summary periods, filter-preserving links and report-search round trips passed.')

for (const range of ['current-year', 'last-year', 'current-month', 'last-month', 'last-12-months', 'year', 'custom']) {
 const picked = { timeRange: range, ...annual.reportRange({ timeRange: range, year: 2024, dateFrom: '2026-10-24', dateTo: '2026-10-26' }, now) }
 const converted = summary.paymentPickerFilters(filters, picked)
 assert.equal(converted.search, filters.search)
 assert.equal(converted.projectId, filters.projectId)
 assert.equal(converted.hasAttachment, filters.hasAttachment)
 const back = summary.paymentPickerSelection(converted, now)
 assert.equal(back.dateFrom, picked.dateFrom, `${range}: local start date survives`)
 assert.equal(back.dateTo, picked.dateTo, `${range}: inclusive local end date survives`)
}
assert.equal(summary.paymentPickerSelection(base, now).timeRange, 'all')
assert.deepEqual(summary.paymentPickerFilters(filters, {timeRange:'all'}), {...filters, timeRange:'all', dateFrom:null,dateTo:null})
assert.equal(summary.paymentPickerFilters(filters, {timeRange:'custom', dateFrom:'2026-02-30', dateTo:'2026-03-01'}), filters)
console.log('Shared period picker: presets, custom dates, DST bounds and retained payment filters passed.')

assert.equal(summary.paymentPeriodIsCurrentMonth({...base, timeRange:'currentMonth'}, now), true)
assert.equal(summary.paymentPeriodIsCurrentMonth({...base, timeRange:'custom', ...month}, now), true, 'A custom full-current-month range also hides the duplicate card')
for (const timeRange of ['all','currentYear','lastMonth','lastYear']) assert.equal(summary.paymentPeriodIsCurrentMonth({...base, timeRange}, now), false)
assert.equal(summary.paymentPeriodIsCurrentMonth({...base, timeRange:'custom', dateFrom:month.dateFrom + 86400, dateTo:month.dateTo}, now), false)

for (const timeRange of ['currentMonth', 'lastMonth']) assert.equal(summary.paymentPeriodIsSingleMonth({ timeRange }, now), true)
const customMonth = (from, to) => summary.paymentPeriodIsSingleMonth({ timeRange: 'custom', dateFrom: new Date(from).getTime() / 1000, dateTo: new Date(to).getTime() / 1000 }, now)
assert.equal(customMonth('2024-02-01T00:00:00', '2024-02-29T23:59:59'), true, 'Leap-year February has no duplicate average')
assert.equal(customMonth('2026-03-01T00:00:00', '2026-03-31T23:59:59'), true, 'Calendar months remain one month across DST')
assert.equal(customMonth('2026-09-05T00:00:00', '2026-09-20T23:59:59'), true, 'A partial month also has an identical monthly average')
assert.equal(customMonth('2026-09-30T00:00:00', '2026-10-01T23:59:59'), false)
assert.equal(customMonth('2025-09-01T00:00:00', '2026-09-30T23:59:59'), false)
for (const timeRange of ['all', 'currentYear', 'lastYear']) assert.equal(summary.paymentPeriodIsSingleMonth({ timeRange }, now), false)
assert.equal(summary.paymentPeriodIsSingleMonth({ timeRange: 'custom', dateFrom: month.dateFrom, dateTo: null }, now), false)
console.log('Monthly average visibility passed: presets, custom months, partial months and cross-month/year boundaries.')

const budget = (id, period, year, month = 0) => ({ id, period, evaluation: {
 period_start: Date.UTC(year, month, 1) / 1000,
 period_end: Date.UTC(year, month + (period === 'month' ? 1 : 12), 1) / 1000,
} })
const goals = [budget(1, 'year', 2026), budget(2, 'month', 2026, 9), budget(3, 'month', 2026, 8), budget(4, 'year', 2025)]
const matching = filters => summary.paymentPeriodBudgetGoals(goals, filters, now).map(goal => goal.id)
assert.deepEqual(matching({timeRange:'currentMonth'}), [2], 'A month excludes annual goals and other months')
assert.deepEqual(matching({timeRange:'lastMonth'}), [3], 'Historical selections never show current evaluations')
assert.deepEqual(matching({timeRange:'currentYear'}), [1, 2, 3], 'A year includes evaluations fully inside that year')
assert.deepEqual(matching({timeRange:'lastYear'}), [4])
assert.deepEqual(matching({timeRange:'all'}), [1, 2, 3, 4])
assert.deepEqual(matching({timeRange:'custom', ...month}), [2])
assert.deepEqual(matching({timeRange:'custom', dateFrom:month.dateFrom+86400, dateTo:month.dateTo}), [], 'Partial periods do not claim a full budget evaluation')
assert.deepEqual(summary.paymentPeriodBudgetGoals([{period:'month', evaluation:{}}], base, now), [], 'Missing evaluations are not displayed')
console.log('Budget summary periods passed: matching months/years, historical dates, full-period containment and empty matches.')

const group = { level: 'month', dateFrom: new Date(2026, 9, 1).getTime()/1000, dateTo: new Date(2026, 10, 1).getTime()/1000-1 }
const groupTarget = summary.paymentGroupReportTarget(group, { ...base, timeRange: 'custom', dateFrom: new Date(2026,9,8).getTime()/1000, dateTo: new Date(2026,9,20).getTime()/1000, search:'Rent', type:'expense', tags:'important', categoryId:3 }, {}, now)
assert.equal(groupTarget.query.dateFrom, '2026-10-08')
assert.equal(groupTarget.query.dateTo, '2026-10-20')
assert.equal(groupTarget.query.period, 'day')
assert.equal(groupTarget.query.search, 'Rent')
assert.deepEqual(JSON.parse(groupTarget.query.reportFilters), [
 {dimension:'type',id:'expense',direct:true}, {dimension:'labels',id:'important',direct:true}, {dimension:'categories',id:'3',direct:true},
])
const areaGroupTarget = summary.paymentGroupReportTarget(group, base, {projectId:4,entryScope:'shared'}, now)
assert.equal(areaGroupTarget.query.projectId, '4')
assert.equal(areaGroupTarget.query.entryScope, 'shared')
for (const unsupported of [{status:'active'}, {hasAttachment:'false'}, {recurring:'true'}, {narrowProjects:'1,2'}, {narrowCategories:'2,4'}]) {
 assert.equal(summary.paymentGroupReportTarget(group, {...base,...unsupported}, {}, now), null, 'Unrepresentable filters must not silently broaden the report')
}
assert.equal(summary.paymentGroupReportTarget(group, {...base, narrowProjects:'0'}, {}, now).query.reportFilters, '[{"dimension":"projects","id":"0","direct":true}]')
assert.equal(summary.paymentGroupReportTarget(group, {...base,timeRange:'custom',dateFrom:group.dateTo+1,dateTo:group.dateTo+1000}, {}, now), null)
console.log('Payment period reports passed: clipped dates, type/label/category conjunctions, area perspective and unsupported-filter guards.')

// Creation labels track the same income context as the form's default type.
const paymentView = fs.readFileSync(new URL('../src/views/TransactionsView.vue', import.meta.url), 'utf8')
const creationGetter = key => new Function(paymentView.match(new RegExp(`${key}\\(\\) \\{([\\s\\S]*?)\\n\\t\\t\\}`))[1])
const defaultType = creationGetter('defaultEntryType'), actionLabel = creationGetter('newPaymentActionLabel')
assert.ok(paymentView.includes('class="creation-label-desktop">{{ $texts.common.newItem() }}'))
for (const [type, routeFilter, tags, enabled, expected] of [
 ['all', undefined, 'all', true, 'New payment'],
 ['all', 'income', 'all', true, 'New income'],
 ['income', undefined, 'all', true, 'New income'],
 ['expense', undefined, 'all', true, 'New payment'],
 ['income', 'income', 'future', true, 'Plan payment'],
 ['income', 'income', 'all', false, 'New payment'],
]) {
 const state = {filters:{type,tags},$route:{query:{filter:routeFilter}},$enableIncomes:enabled,$texts:{common:{income:()=> 'Income',expense:()=> 'Expense',payment:()=> 'Payment'},entry:{newIncome:()=> 'New income',planPayment:()=> 'Plan payment'},areaDetail:{newPayment:()=> 'New payment'}}}
 state.defaultEntryType=defaultType.call(state)
 assert.equal(actionLabel.call(state), expected)
 if (expected === 'New income') assert.equal(state.defaultEntryType,'income')
}
console.log('Income creation labels passed: route/type filters, default type, planning precedence and the income feature gate.')

const { upcomingOpenReminders } = await load('src/services/reminderSummary.js')
const originalZone = process.env.TZ
for (const zone of ['Europe/Vienna', 'America/New_York', 'Pacific/Auckland', 'UTC']) {
 process.env.TZ = zone
 for (const day of [new Date(2026,10,5,12), new Date(2026,2,25,12), new Date(2026,9,20,12), new Date(2026,11,27,12)]) {
  const at = (offset,h=9,m=0,s=0)=>new Date(day.getFullYear(),day.getMonth(),day.getDate()+offset,h,m,s).getTime()/1000
  const row=(id,offset,extra={})=>({id,title:`Reminder ${id}`,remind_at:at(offset),status:'active',keep_open:true,...extra})
  const rows=[row(4,10,{remind_at:at(10,23,59,59)}),row(3,0),row(2,-1),row(1,-40),row(5,11,{remind_at:at(11,0)}),row(6,-1,{completed_at:at(-1),status:'completed'}),row(7,-1,{archived_at:at(-1),status:'archived'}),row(8,-15,{keep_open:false}),row(9,-2,{effective_archived_at:at(-1)}),row(10,-2,{remind_at:null})]
  assert.deepEqual(upcomingOpenReminders(rows,day).map(r=>r.id),[1,2,3,4],`${zone}: overdue, inclusive day ten, closed/archived and live automatic archival`)
  assert.equal(rows[0].id,4,'Selection does not reorder API state')
  assert.deepEqual(upcomingOpenReminders([row(2,1),row(1,1)],day).map(r=>r.id),[1,2],'Stable ordering for equal dates')
  const expiry=at(-14)
  assert.equal(upcomingOpenReminders([row(1,-14,{keep_open:false})],new Date((expiry+14*86400-1)*1000)).length,1)
  assert.equal(upcomingOpenReminders([row(1,-14,{keep_open:false})],new Date((expiry+14*86400)*1000)).length,0)
 }
}
if (originalZone === undefined) delete process.env.TZ; else process.env.TZ=originalZone
const cardGetter = new Function('upcomingOpenReminders','BellOutlineIcon','return function() {' + paymentView.match(/reminderSummaryCard\(\) \{([\s\S]*?)\n\t\t\}/)[1] + '}')(upcomingOpenReminders,{})
const cardState={$enableReminders:true,summaryReminders:[{id:1,title:'Oldest',status:'active',keep_open:true,remind_at:now.getTime()/1000-86400},{id:2,title:'Today',status:'active',keep_open:true,remind_at:now.getTime()/1000},{id:3,title:'Next',status:'active',keep_open:true,remind_at:now.getTime()/1000+86400}],reminderSummaryNow:now.getTime(),$texts:{reminders:{sidebar:()=> 'Reminders'}},formatDate:timestamp=>String(timestamp)}
const card=cardGetter.call(cardState)
assert.equal(card.value,3)
assert.deepEqual(card.details.map(d=>d.label),['Oldest','Today'])
assert.deepEqual(card.route,{name:'reminders'},'Card opens overview without selecting an entry')
assert.equal(cardGetter.call({...cardState,$enableReminders:false}),null)
assert.equal(cardGetter.call({...cardState,summaryReminders:[]}),null)
console.log('Reminder summary passed: inclusive ten local days, DST/year boundaries, open-only counts, archival, earliest two, overview navigation and feature gate.')

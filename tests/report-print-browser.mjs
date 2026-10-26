// Optional local layout check: PLAYWRIGHT_MODULE points to an installed Playwright.
// Generate fixtures with COBUDGET_REPORT_FIXTURES_DIR=/tmp/report node tests/annual-overview.mjs.
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright')
const output = path.resolve(process.argv[2])
const fixtures = JSON.parse(fs.readFileSync(path.join(output, 'fixtures.json'), 'utf8'))
const read = file => fs.readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const script = read('src/services/annualOverviewPrint.js').replace(/export /g, '') + '\nwindow.makeReport = createAnnualPrintDocument;'
const chartStyles = read('src/components/AnnualOverviewChart.vue').split('<style scoped>')[1].split('</style>')[0]
const metadata = [['Zeitraum', '1. Januar 2026 – 31. Dezember 2026'], ['Bereich', 'Layouttest mit Testdaten'], ['Beträge', 'Persönlicher Anteil · EUR'], ['Zahlungsfilter', 'Lebensmittel · Urlaub']]
const payments = { columns: ['Datum', 'Zahlung', 'Kategorie', 'Zahlungspartner', 'Zahlungsart', 'Betrag'], rows: Array.from({ length: 75 }, (_, index) => ['23.10.2026', `Gespeicherte Zahlung ${index + 1} mit einer längeren Beschreibung für den Seitenumbruch`, 'Lebensmittel', 'Geschäft & Partner', 'Ausgaben', `${index + 1},01 €`]) }
const browser = await chromium.launch({ headless: true })
try {
 const page = await browser.newPage({ viewport: { width: 1123, height: 794 } })
 for (const name of ['overview', 'forecast', 'detail', 'top']) {
  const html = name === 'top' ? fixtures.topSection : fixtures[name]
  await page.setContent(`<!doctype html><html lang="de"><head><style>:root { --cobudget-text:#222; --cobudget-text-muted:#666; --cobudget-border:#ddd; --cobudget-font-sm:14px; --cobudget-font-xs:12px; --cobudget-surface:#fff; } ${chartStyles}</style></head><body><main id="source">${html}</main></body></html>`)
  if (name === 'top') await page.evaluate(html => { const template = document.createElement('template'); template.innerHTML = html; document.querySelector('.report-top-payments-table').replaceWith(template.content.firstElementChild) }, fixtures.topTable)
  await page.addScriptTag({ content: script })
  if (name === 'detail') {
   assert.equal(await page.evaluate(() => { try { window.makeReport(document.getElementById('source'), { title: 'Pending report' }); return null } catch (error) { return error.code } }), 'REPORT_LOADING', 'A partially loaded section cannot silently disappear from a complete-looking report')
   await page.locator('.report-detail-section:has([role="status"]):not(.report-payments)').evaluateAll(sections => sections.forEach(section => section.remove()))
  }
  const result = await page.evaluate(({ metadata, payments, name }) => {
   const source = document.getElementById('source')
   const options = { title: `CoBudget – Auswertung · ${name}`, metadata, months: ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'], ...(name === 'detail' ? { payments } : {}) }
   const report = window.makeReport(source, options)
   return { html: '<!doctype html>' + report.documentElement.outerHTML, tables: report.querySelectorAll('table').length, charts: report.querySelectorAll('.report-chart svg').length, payments: report.querySelectorAll('.report-payment-list tbody tr').length, text: report.body.textContent, controls: report.querySelectorAll('button, select, input, [role="tab"]').length, topRows: [...report.querySelectorAll('.report-top-payment-row')].map(row => [...row.cells].map(cell => cell.textContent)) }
  }, { metadata, payments, name })
  assert.equal(result.controls, 0, 'Reports contain content instead of live controls')
  if (name === 'top') { assert.equal(result.tables, 1); assert.deepEqual(result.topRows.map(row => row.slice(1)), [['Hotel <script>', 'Travel', 'Hotel & Spa', '40.01'], ['', 'Food', 'Restaurant', '10.00']], 'Printing preserves the separate description, category, from/to and amount columns') }
  else assert.equal(result.charts, 1, 'Only the active development/forecast chart is printed')
  if (name === 'detail') assert.equal(result.payments, 75, 'The payment report includes all loaded rows')
  await page.setContent(result.html)
  await page.emulateMedia({ media: 'print' })
  fs.writeFileSync(path.join(output, `${name}.html`), result.html)
  await page.pdf({ path: path.join(output, `${name}.pdf`), preferCSSPageSize: true, printBackground: true })
  await page.screenshot({ path: path.join(output, `${name}.png`), fullPage: true })
  console.log(`${name}: ${result.tables} tables, ${result.charts} charts, ${result.payments} complete payment rows`)
 }
} finally { await browser.close() }

# CoBudget Test Plan

Dieses Verzeichnis ist der Startpunkt fuer automatisierte Backend-Tests. Die App hat weiterhin kein PHPUnit-/Nextcloud-Test-Bootstrap im Repo; deshalb ist die erste Suite bewusst dependency-frei gehalten und prueft die wichtigsten Backend-Vertraege direkt mit PHP.

## Aktuell ausfuehrbar

```sh
php tests/php/run.php
npm run test:php
php tests/static-security.php
npm run test:frontend-smoke
npm test
```

## Nextcloud 33–35 compatibility

### Annual overview integration

Run against a local Nextcloud with CoBudget and an existing test account:

```sh
php tests/integration/annual-overview.php --nextcloud=/path/to/local/server --user=admin
```

The test creates isolated fixtures inside a transaction and always rolls them
back. It exercises actual controller/database queries for personal shares,
shared and solo areas, settlements, more than one page of payments, category
hierarchies, unassigned payments, future dates, scope-specific year choices and unauthorized access. No
notifications are sent. Forecast checks cover saved future payments plus
scheduled recurring occurrences, monthly placement, counts, grouping and amount
filters. Shared personal forecasts additionally cover stored residual cents,
workspace isolation, personal tag IDs, zero shares, former participants,
read-only access and replacing a forecast with a saved occurrence without
double counting. Payment-list drilldowns deliberately include saved payments only.
The fast suites additionally cover timezone boundaries,
cent arithmetic and filter/pagination/return-route round trips.

Annual detail tests cover counts across SQL groups, personal/shared scopes and
category hierarchies; empty-period shares, calendar-month averages, signed balance
rounding and exact modal drilldowns. On desktop and mobile, check that amount
buttons open the detail modal without navigating, Escape/close restores table
focus, and Show payments opens the matching filtered list. Row labels must keep
their direct undated links. Also check printed amounts after the button change.

For annual overview printing, verify the top-right printer action with a narrow
viewport and a horizontally scrolled table. Check A4 landscape/PDF output for all
12 months and the yearly total, long labels, complete amounts, repeated headings
on multiple pages and a single final total row. Wider views print in blocks of at
most 12 amount columns. Each later block starts on a new page and repeats the
label column and the total. The whole-year daily overview prints one calendar
month at a time. Amounts stay on one line. The print header must match the
year, scope, grouping and current-year cutoff. Sorting, expanded children and
disabled-income settings should match the visible tables. Printing or cancelling
must leave the original table and its navigation links unchanged.

### Personal Talk integration

Run the real Talk delivery tests on a local/disposable server with CoBudget and
Talk enabled (Talk 23–25). The explicit flag allows creation of temporary
accounts, shared-area payments and chats; the test removes its fixtures afterward.

```sh
php tests/integration/talk.php --nextcloud=/path/to/nextcloud --create-test-users
```

This covers default opt-out, idempotent setup, actual payment creation and bot
delivery, independent bell/Talk preferences, actor exclusion, foreign tokens,
extra participants, explicit reconnection, deleted chats and unauthenticated
settings endpoints. No messages are sent to existing accounts.

CI additionally installs Nextcloud 33/PHP 8.2, 34/PHP 8.2 and 35/PHP 8.3
in isolated SQLite instances. Each job enables CoBudget (running all app
migrations), loads its OCC commands, checks background-job registration and
data integrity, and disables/re-enables the app. These are server integration
smoke tests; they do not cover browser interaction or populated server upgrades.

`npm run test:frontend-smoke` also runs `tests/fetch-json.mjs` against the actual
HTTP service and installed Nextcloud auth/router libraries with simulated DOM,
storage and network boundaries. It verifies CSRF token lookup and rotation
without private globals while retaining workspace headers and token overrides.

See [the compatibility review](../docs/nextcloud-35-compatibility.md) for findings,
local validation results and remaining test coverage.

`tests/php/run.php` ist der schnelle Backend-Regressionslauf. Er prueft:

- Dashboard-Monats-/Jahresgruppen und Tabellen-Zwischensummen verwenden dieselbe Zeitzone wie die im Browser angezeigten Zahlungsdaten, einschliesslich Monats-/Jahreswechseln und wiederkehrenden Zahlungen.

- Routen zeigen auf existierende Controller-Methoden und haben eindeutige Verb-/URL-Paare.
- User-Daten-API-Methoden liefern JSON-Fehler und pruefen Auth-/Workspace-Header-Fehler.
- `WorkspaceAwareTrait` validiert Workspace-Header, Pflichtnamen, Typen, Betraege und Integer-Cents.
- Entry-, Projekt-, Kategorie-, Empfaenger-, Workspace- und Cron-Vertraege bleiben workspace-scoped.
- Kritische Operationen bleiben transaktional.

`tests/static-security.php` prueft weiterhin grob, ob die zentralen Workspace-Guards, strikte `X-Workspace-Id`-Fehler, User-/Workspace-Scope bei kritischen Controllern, Transaktionen und `amount_cents`-Migrationen im Code vorhanden sind.

Der Frontend-Smoke-Test prueft ohne Browser-Setup, ob die Vue-Routen lazy geladen werden, das globale Zahlungsmodal erst bei Nutzung geladen wird, keine veralteten `::v-deep`-Selectoren mehr im Sourcecode stehen, keine grossen `@nextcloud/vue`-Barrel-Imports zurueckkommen und die native Nextcloud-App-Shell erhalten bleibt.

## Prioritaet 1: Workspace-Isolation

- User A kann keine Eintraege, Projekte, Kategorien oder Empfaenger von User B lesen, aendern oder loeschen.
- Ein aktiver Workspace A darf keine IDs aus Workspace B fuer Eintraege oder Projektoperationen verwenden.
- Projektoperationen schlagen fehl, wenn das Projekt nicht im aktiven Workspace liegt.
- Workspace-Loeschung loescht nur Daten des authentifizierten Users im betroffenen Workspace.

## Prioritaet 2: Referenz- und Nutzungsregeln

- Kategorie-/Empfaenger-Loeschung ist blockiert, wenn die ID im aktuellen Workspace verwendet wird.
- Globale Kategorien und Empfaenger sind nutzbar, aber nicht als persoenliche Eintraege loeschbar.

## Prioritaet 3: Kritische Operationen

- Projektanlage schreibt Projekt und Mitglieder atomar.
- Projektloeschung entfernt Mitglieder und Projekt atomar.
- Projektabrechnung markiert nur Eintraege des aktiven Workspace als abgerechnet.
- Wiederkehrende Jobs erzeugen Folgeeintraege mit der bestehenden `workspace_id`.
- Geldwerte werden intern ueber `amount_cents` als Integer-Cents gespeichert und berechnet.

## Manuelle Verifikation

- Zwei Workspaces erstellen.
- In Workspace A Eintrag, Kategorie, Empfaenger und Projekt anlegen.
- Zu Workspace B wechseln und pruefen, dass A-Daten nicht sichtbar oder nutzbar sind.
- Projektmitgliedschaft und Abrechnung testen.
- Einen nicht-default Workspace loeschen und pruefen, dass der andere Workspace unveraendert bleibt.

### Annual month comparisons

`npm test` covers modal-only prior-month/prior-year comparison rows, localized difference rows,
exact ±5% arrow thresholds, missing history versus known empty months, zero
baselines, elapsed-day cutoffs and leap-month boundaries. The rollback-only
`tests/integration/annual-overview.php` also checks comparison queries across year
boundaries and isolation between personal, shared, solo and foreign scopes.

Browser verification: open a monthly amount in Annual overview, check both available
comparison rows and their dates and amounts in the modal,
and use Tab/Shift+Tab, Enter/Space and Escape for its actions. Check first-history months and annual
totals show no month-comparison rows. Check 390px and desktop layouts in light and
dark themes; tooltips must remain within the viewport and months scroll inside
the table while category and annual-total columns remain fixed.

### Insurance label

`tests/integration/insurance.php` checks payment creation and editing, boolean
filters, dashboard/sidebar counts, CSV column alignment, settings toggles,
workspace isolation, authentication and compatibility with older backup rows.
All fixture database writes are rolled back, including on failure. Run against
an updated local instance with an existing local test account:

```sh
php tests/integration/insurance.php --nextcloud=/path/to/nextcloud/server --user=admin
```

The frontend smoke checks cover the insurance route shortcut, retained filter
and unsaved changes when only the insurance checkbox changes. In the browser,
verify the payment badge and sidebar entry appear after labelling a payment;
disabling Insurance in Settings hides the controls without erasing labels.

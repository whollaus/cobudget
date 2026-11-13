# CoBudget Features

CoBudget helps individuals, couples, families and housemates track everyday
finances and share costs inside Nextcloud.
This describes the current 0.8.8 beta development state; see the
[changelog](CHANGELOG.md) for changes and the [README](README.md) for installation.

## Focus and data model

CoBudget organizes finances around payments, personal shares and shared areas.
Personal views count your own costs; shared areas retain the total, the payer and
balances between members. Workspaces separate independent sets of data.
CoBudget does not connect to bank accounts, synchronize bank transactions or import
bank statements. Reports summarize recorded payments; they do not represent
reconciled bank account balances. Recurring rules create app records, and settlements
record how shared costs are balanced; neither initiates a bank transfer.
See [the CoBudget approach](docs/concept.md) for an example.

## Navigation and filters

- Navigate between Payments, Income, Expenses, Planned, Areas, Budget goals,
  Reports and Settings using the sidebar or the page-title menu.
- Payment title menus offer label presets. Area and budget pages instead list
  their existing areas or goals; report and settings menus omit label presets.
- Create records beside the desktop title or through mobile creation actions.
  Empty areas and budget lists explain how to create the first record.
- Payments, area payments and Reports share responsive filter toolbars. Shortcuts
  appear when space permits; the full menu retains applicable filters on small screens.
- Combine search, areas, hierarchical categories, payment partners, labels, tags,
  receipt status and inclusive amount bounds where supported by the view.
  Multiple values in one filter are alternatives; different filters apply together.
- Select several years and any combination of months, with month choices in two
  columns. Each selected month applies to every selected year. Custom inclusive
  dates and relative/all-time periods are also available.
- The complete current year is the default period. Other selections show Period
  with an active dot; removable chips retain the exact dates and selected filters.
- Active chips appear above payment tables and directly above Development/Forecast
  in Reports. Filter menus also mark affected sections.
- Without area is distinct from All payments. Hide area controls when no active
  areas are available; retain existing area assignments when editing older payments.

## Payments

- Record income or expenses with date, amount, currency, category, payment partner,
  description, area and optional receipts. Enter a new category or partner in the
  lookup and create it when saving the payment.
- Use Important, Review, Fixed costs, Children, Subscription, Tax relevant and
  Insurance labels, and `#tags` in descriptions. Enable only the labels you need.
- Sort and filter payments, inspect period totals, export CSV (download, save in
  Nextcloud, or copy up to 1,000 payments to the clipboard), print all filtered
  payments across pages, jump to category or payment partner settings from the
  list menu, edit, copy or delete where permitted, and inspect payment history.
- Tables show payer avatars, category symbols and label indicators. An assigned
  area's color marks the left edge; areas without a custom color use Nextcloud blue.
  Unassigned rows keep their normal background.
- Open shared payments show a group icon. Its tooltip identifies the area, original
  total, personal share and unsettled status; personal totals count only that share.
- Mobile rows place sharing icons before amounts and omit the three-dot menu.
  Tap a row to open the editor; history is in the editor or a direct action on
  read-only rows. Desktop retains the action menu.
- Category suggestions use repeated payment-partner habits without overwriting a
  manual choice. Copying a payment retains its area and starts with today's date.
- Payment totals use displayed local calendar dates. Summary links preserve
  supported filters; budget summaries appear only when their complete evaluation
  period is within the selected range.
- Forms focus the amount, preserve failed drafts and guard unsaved changes.
  Receipts, Repeat and Reminder are directly visible compact rows; controls reveal
  the relevant details. Repeat and Reminder use disclosure arrows; collapsing
  preserves their settings. Both start collapsed; blue dots indicate configured
  settings. Choose Once in the interval list to disable repeating, or clear the
  reminder date and save to remove the reminder.

## Reminders

- Open reminders show Today or N days ago alongside their date; overdue open rows have a subtle highlight. Completed and archived reminders retain their status colors.
- Payment forms offer the same reminder date, title, description and action labels. Expand or collapse the reminder with its arrow; collapsing does not disable it. Completion and archive controls stay in the Reminders module. Payment-form saves preserve the chosen automatic-archive setting (enabled by default for new reminders) and save payment and personal reminder fields together.

- Remind me later in the row menu offers Tomorrow or In one week at 09:00, plus Choose date in the editor. Quick actions preserve notes, labels and archival preferences and update linked payment reminders and calendars.
- Pending calendar synchronization appears beside the affected reminder date. Retry directly from the warning icon without opening Settings or changing reminder drafts.
- New or rescheduled reminders use 09:00 on the selected local calendar day.
- Hide the reminder module, navigation, payment controls and reminder filter through
  Settings → Features → Reminders. Existing reminders, delivery preferences and
  calendar synchronization stay active; editing a payment preserves hidden reminder data.
- Payments starts with a reminder summary when open reminders are overdue or due
  through the next ten calendar days: total count and the two earliest titles/dates.
  The card opens the reminder overview and excludes completed/archived items.
- **Reminders** in navigation opens the **Reminders** table. Create independent
  items or open payment-linked reminders; edit in the right sidebar on desktop.
  Each row offers Edit reminder and Delete reminder in its action menu, with
  the existing discard guard and deletion confirmation.
  A new form opens automatically on desktop entry; direct links open their existing
  reminder. The New action stays available in Reminders and Payments.
- The default overview includes open and completed reminders; archived items are
  hidden, including completed archived items. Individual status switches allow
  any combination, so completing an open item keeps it visible by default.
  Open reminders come first, followed by completed and archived reminders; each
  group starts with the earliest reminder date, with month/year headings like
  November 2026 when the visible page contains multiple months. Table headers are not sortable.
  A Status column shows Open, Completed and Archived, including simultaneous
  completion/archival; outlined blue, green and gray tags with icons distinguish the states,
  and tooltips show their dates.
- The form starts with Remind me on, followed by Title, multiline Description and action
  labels (Cancel contract, Renew, Review, Pay). Choose these four reminder labels
  with icon/text checkbox buttons like payment labels; the table shows them as
  outlined tags with matching icons, like Nextcloud Files. Inherited payment labels
  appear only in the table as fixed icons with tooltips, never in the reminder
  form; they are changed on the payment. Show payment and Show reminder buttons
  with arrows provide navigation between linked records.
- Search and filter by status, date, label and payment link. The date range stays
  in the Filter menu. Label switches offer only the four reminder action labels;
  All labels is the default, and multiple selections match any selected label. Combine independent
  switches for reminders without payment, payments without an area and payments
  from an area; all three are included by default. Completion
  is personal, including in shared areas. Ordinary reminders automatically enter
  the archive 14 days after their reminder time, or 14 days after completion
  for completed items. **Archive automatically** can be switched off for either
  state. The status is calculated when loading entries and refreshed every minute
  in the open overview, independently of cron. Existing reminders have compact
  Reminder completed and Reminder archived switches, applied together when saving. Manual archiving and deletion are also available.
- New standalone reminders have no payment selector. Payment-linked reminders
  show a fixed link to their original payment in its workspace. Put deadlines and
  cancellation details in the description. Existing deadline data is preserved.
- Linked title/time remain payment fields; description, action labels and status
  belong to the individual user. Deleting a personal reminder preserves the
  payment and other members' reminders. A newly scheduled payment reminder
  appears again. Removing the payment or membership removes its linked reminder.
- Private calendars synchronize reminder title, description and time in both directions.
  Independent edits merge automatically; conflicts offer a value choice per field.
  Deleting a calendar event archives only your reminder and suppresses recreation
  until explicit future reactivation. Calendar outages or deletion of an entire
  calendar do not archive reminders. Shared calendars remain export-only.
- Calendar integration includes explicit reminder notes and any previously saved deadline.
  Completing or archiving removes the alarm but retains an existing event;
  deletion removes the managed event. Reactivation requires a future time.
  Private calendar edits synchronize as described above; shared calendars remain
  export-only. Nextcloud Tasks are not synchronized. The overview spans the user's workspaces.

## Planned payments and reminder delivery

- Optionally synchronize reminders to a writable Nextcloud calendar of your choice,
  with an alert at the reminder time. Changes and removals in CoBudget follow into
  that calendar. Each member chooses their own target; calendar access controls
  who can see the reminder text. The Calendar column is hidden without a writable
  event calendar. Calendar delivery is separate from Nextcloud, Talk and email.

- Optionally notify when the first future payment becomes due and for each
  generated repetition. Nextcloud, private Talk and email channels are independent,
  all off by default, in a compact notification settings table. These messages
  do not execute bank transfers.

- Schedule future payments and repeating series with an interval, multiplier and
  optional inclusive end date. Planned shows the future first payment before
  later repetitions, rather than skipping directly to the next recurrence.
- Recurring generation becomes due from 06:00 server time on the scheduled day,
  including existing series previously timed for 09:00. The next Nextcloud cron
  run creates the occurrence; a working cron schedule is required.
- Series retain their history as the current recurrence moves to the newest
  payment. Missed runs catch up through later job executions.
- Stopping an already booked payment's repetition keeps its recorded history.
  Deleting a future first payment removes that payment and its planned series.
- Choose reminder delivery via Nextcloud (default on), Talk and email (both
  default off) independently, including without areas. Talk uses the private CoBudget chat
  and includes reminder text, amount and due date. Shared reminders respect
  each active member's preferences. Mobile push depends on Nextcloud/Talk and
  device settings.
- The separate Talk option for new shared payments continues to cover payments
  created by other members; automatic recurrence creation does not send those
  messages. Due reminders are handled independently.

See [Operating CoBudget](docs/operations.md) for cron diagnostics and Talk setup.

## Areas and shared payments

- Use solo areas as personal groupings without member balances or settlement.
  Add the first additional member only while there are no personal payments in
  the area, avoiding retroactive ambiguity about their allocation.
- Create shared areas, choose members and default percentages, select the payer
  and allocate a payment to members. Area lists use natural A–Z ordering;
  archived areas remain separate and retain their historical data.
- Each shared payment stores its exact allocation snapshot. Default share changes
  do not rewrite history. Indivisible cents are balanced cumulatively across
  payments instead of always favoring the same person.
- Each positive member share becomes a personal payment in that member's persisted
  Basis workspace. Shared totals and personal projections are never counted together
  on the same financial surface.
- Before settlement, authorized members edit the shared source. Its area stays
  fixed; payer and allocation can be changed. Personal projections remain locked
  and synchronize with source edits, including their own amount history.
- Only the original creator or area owner can delete an open shared payment.
  Deletion removes the source and its locked personal projections together.
- Settlement records balances and repayments, freezes the shared source and
  releases personal shares. Each user can then edit or delete their own released
  payment independently; this cannot rewrite the settlement or another user's share.
  The original area remains origin metadata, and payer/split controls are hidden.
- Review open member balances, suggested repayments and immutable settlement
  history, and export settled payments as CSV (download, save in Nextcloud, or
  copy up to 1,000 payments to the clipboard) from the settlements action menu.
  The settlement confirmation uses all open shared payments, regardless
  of current table filters or pagination.
- Member, default-share and archival changes require all open shared payments to
  be settled first. Ownership can be transferred to an active member.
- Deleted accounts become former participants in history. Ownership transfers to
  an active member; new shared payments and recurrences pause until the former
  participant is removed after settling outstanding payments.

## Categories and payment partners

- Manage income and expense categories and partners in availability tables across
  Without area and named areas. Categories support a main category and one subcategory
  level, symbols and optional numbers.
- Personal, area-specific and administrator-managed global records retain their
  permissions. Identity and availability changes use explicit Save/Cancel and
  save multi-area changes atomically; hidden historical records remain intact.
- Optional advanced master-data display exposes numbers in payment choices;
  disabling it retains stored values. Personal, area and global editing still
  follow their respective permissions.
- Advanced settings separately edit area-specific category numbers or partner
  contact, address, bank and note details. Unsaved markers identify changed areas;
  the independent Save covers all marked scopes and preserves failed drafts.
- Payment selectors show authorized, available choices with category hierarchy
  and income/expense groups. Private partner details are not exposed in payment lists.

## Workspaces

Create separate data pools, switch between them and hide workspaces from the quick
switcher. Workspace administration belongs to the current user and uses dedicated
Add/Edit dialogs. Shared personal projections retain their persisted Basis workspace;
changing the selected workspace does not move another member's financial data.

## Budget goals

- Create flexible or fixed goals with a name, amount and period; inspect progress,
  forecast, buffer and historical snapshots in the independent Budget goals module.
- Combine area, category and label criteria. Without area means unassigned payments;
  the all-area wildcard is separate. No criteria includes all applicable expenses.
- Goals count personal amounts, including the user's stored area shares.
- New goals save explicitly. Existing detail fields save on selection or field
  blur/Enter; criteria save when added or removed. Failed saves retain drafts and
  offer retry; deletion requires confirmation.

## Reports

- Choose dates independently of quarterly, monthly, weekly or daily grouping.
  Non-adjacent selected months stay out of gaps, totals, comparisons and forecasts.
- Explore income, expenses and balances using Development charts, period tables,
  averages, comparisons, composition and the largest saved payments.
- **Every new visit starts with Development.** Choosing Forecast persists while
  changing filters or dates within Reports; leaving Reports and returning resets
  it. Completed periods offer Development only.
- Personal reports count saved personal amounts. One shared area can switch
  between Personal share and Entire area; multiple selected areas use personal
  amounts. Dates, filters and the financial perspective follow drilldowns and exports.
- Group categories or payment partners with equivalent names across areas and
  expand their area subtotals. This does not merge the stored records.
- Open totals, subjects or composition rows for nested details with the same filters.
  Inspect matching saved payments and edit them where existing permissions allow.
- Include saved future payments and projected recurring occurrences when requested.
  Forecast separates actual amounts, known plans and additional statistical estimates;
  estimates do not create payments or recurrence rules.
- Print the current overview or detail as an A4 landscape report, save PDF through
  the browser, export CSV to a device or Nextcloud Files, or copy to a spreadsheet.
  Whole reports and section exports have deliberately different scope.

See [Report calculations and exports](docs/reports.md) for date boundaries,
forecast assumptions, averages, personal/shared amounts and export limits.

## Receipts

Upload receipts with a payment and store them in Nextcloud Files. Choose the
storage folder and optional year/month organization. Existing receipts collapse
behind their count; pending uploads remain visible until saving succeeds.

Shared receipts use physical per-user copies. While a payment is open, attachments
follow the source; after settlement, each personal copy is independent. Optional
receipt deletion follows payment permissions. Backups retain receipt paths but do
not embed the files themselves.

## Settings, backup and administration

- General preferences, folder choices, area names/colors and backup schedules save
  automatically with validation and retry. New records, allocation changes and
  management dialogs retain explicit saving where changes form one operation.
- Create personal exports manually or on a schedule in Nextcloud Files. Restore
  them only into an empty CoBudget user state, remapping the source account and
  keeping imported shared amounts as independent personal payments.
- Administrators can create full backups, restore with confirmation and map user
  IDs for server transfers. Full backups must belong to an administrator; restore
  creates a safety backup and validates shared-payment relationships transactionally.
  App requests and jobs cannot overlap a backup or restore; an already active
  operation prevents maintenance startup. Retry when it finishes and reload open
  tabs after restoration. See the
  [recovery procedure](docs/operations.md#backup-and-recovery).
- Backup archives store receipt paths, not receipt files. Keep Nextcloud Files
  backups through your existing Nextcloud Files backup solution. Receipt-file
  backup remains outside CoBudget. See [backup commands](docs/architecture.md#backups).
- Administrator settings manage global categories/partners, integrity checks,
  supported repairs and data-quality warnings. Destructive actions have explicit
  confirmations; integrity checks do not repair data unless requested.
- Bell notifications, optional personal Talk and email notifications have independent
  preferences for supported events. Talk messages omit descriptions/receipts, remain in chat history
  after a payment changes and are not retried after delivery failures.

## Appearance and accessibility

CoBudget supports light, dark and system appearance independently of Nextcloud's
theme, including sidebars, dialogs, popovers, folder pickers, tooltips and notifications.
Native Nextcloud controls provide consistent states and keyboard focus. Tables,
filters and forms adapt to narrow screens; report tables preserve pinned context
and horizontal scrolling. Failed requests keep visible feedback and retry actions.

Calendar headings follow the interface language; numeric dates and amounts follow
the Nextcloud locale. The [design system](docs/design-system.md) records component,
typography, theme, keyboard and responsive conventions.

## Keyboard shortcuts

| Shortcut | Action |
| --- | --- |
| Ctrl/Command+S | Save the open payment, with normal validation. |
| Ctrl/Command+N | Open a new payment, retaining unsaved-change confirmation. |
| Alt+N | Alternative for a new payment when the browser reserves Ctrl/Command+N. |
| Ctrl/Command+P | Use CoBudget's printing action while in Reports. |
| Escape | Dismiss the active supported menu/dialog or editor through its normal close behavior. |

The Settings page lists shortcuts before Reset all. Shortcuts respect the active
app context, nested dialogs and pending operations; browsers may reserve some keys.

### Optional email notifications

In Settings → Notifications, enable Email independently for reminders, due planned
or recurring payments, new shared-area payments and settlements. All email choices
start disabled. Delivery uses the account's current Nextcloud email address and
the administrator-configured outgoing mail service; no separate mail account or
recipient address is entered in CoBudget. A missing valid profile address disables
activation and shows a setup hint.

Reminder emails contain the personal reminder title/notes and a link. Other events
use a brief summary without payment descriptions or receipts. Scheduled delivery
runs with Nextcloud background jobs; failed reminder/planned-payment emails retry
without repeating successful channels. Shared-area event emails are attempted
after commit; failures are logged without rolling back the payment or settlement.

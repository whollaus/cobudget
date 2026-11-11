# CoBudget Design System

This is the maintained design contract for existing UI and new modules.
Repository documentation is English; visible strings remain localized. This
file describes shared rules and intentional exceptions, not a record of past
implementation steps or proof that every state has been tested.

## How to use these rules

1. Choose the element's role, then reuse its component and semantic tokens.
   Pixel values below are current defaults, not constants to copy into views.
2. Prefer native Nextcloud components. Check the installed API and import from
   direct paths such as `@nextcloud/vue/components/NcButton`.
3. Change shared rules at their source and inspect every affected consumer,
   including admin settings and teleported UI. Avoid global overrides of native
   buttons, fields, headings or SVGs to fix a single screen.
4. Preserve the [deliberate variants](#deliberate-cobudget-variants). Incidental
   local CSS is not an approved exception. Record new shared behavior here in
   the same change, with its scope and verification limits.

Keep financial, permission and persistence invariants in
[architecture.md](architecture.md), report calculations and export scope in
[reports.md](reports.md), commands in [development.md](development.md) and the
[test guide](../tests/README.md). Refer to those sources instead of duplicating
backend rules here.

## Shared implementation sources

Both [main.js](../src/main.js) and [settings.js](../src/settings.js) load these
styles in this order:

| Source | Responsibility |
| --- | --- |
| [tokens.css](../src/styles/tokens.css) | Theme resolution, semantic colors, typography, dimensions and shell/toolbar rules |
| [surfaces.css](../src/styles/surfaces.css) | Shared card and surface treatments |
| [status.css](../src/styles/status.css) | Metadata and financial badges |
| [controls.css](../src/styles/controls.css) | Opt-in fields, focus, interaction states and tooltips |
| [dialogs.css](../src/styles/dialogs.css) | Dialog chrome, scrolling and mobile overlay rules |

Use these components rather than creating another local version:

| Need | Reference |
| --- | --- |
| Shell, page title and creation action | [App](../src/App.vue), [AppPageHeader](../src/components/AppPageHeader.vue) |
| Existing custom action variants | [CbButton](../src/components/CbButton.vue), [CbIconButton](../src/components/CbIconButton.vue), [CbSplitButton](../src/components/CbSplitButton.vue) |
| Payment editor and lookups | [AddEntryModal](../src/components/AddEntryModal.vue), [EntryTypeField](../src/components/EntryTypeField.vue), [EntryLookupSelect](../src/components/EntryLookupSelect.vue) |
| Payment rows | [EntryTable](../src/components/EntryTable.vue), [EntryDescriptionCell](../src/components/EntryDescriptionCell.vue), [EntryAmountCell](../src/components/EntryAmountCell.vue), [EntryAreaCell](../src/components/EntryAreaCell.vue) |
| Payment/report filters | [PaymentFilterBar](../src/components/PaymentFilterBar.vue), [TableFilters](../src/components/TableFilters.vue), [AnnualOverviewFilters](../src/components/AnnualOverviewFilters.vue), [PaymentFilterSelect](../src/components/PaymentFilterSelect.vue) |
| Period and title menus | [ReportPeriodPicker](../src/components/ReportPeriodPicker.vue), [titleNavigation](../src/services/titleNavigation.js) |
| Report choices and scrolling | [ReportSelect](../src/components/ReportSelect.vue), [ReportComparisonPicker](../src/components/ReportComparisonPicker.vue), [DraggableScroller](../src/components/DraggableScroller.vue), [ReportScrollControls](../src/components/ReportScrollControls.vue) |
| Settings lists and master data | [SettingsList](../src/components/SettingsList.vue), [SettingsItemActions](../src/components/SettingsItemActions.vue), [MasterDataSettings](../src/components/MasterDataSettings.vue) |
| Identity, symbols and balances | [MemberIdentity](../src/components/MemberIdentity.vue), [MemberSearch](../src/components/MemberSearch.vue), [CategoryIcon](../src/components/CategoryIcon.vue), [AreaIcon](../src/components/AreaIcon.vue), [MemberBalanceList](../src/components/MemberBalanceList.vue), [RepaymentList](../src/components/RepaymentList.vue) |
| Confirmation, transitions and focus | [ConfirmModal](../src/components/ConfirmModal.vue), [ModalActions](../src/components/ModalActions.vue), [DialogTransition](../src/components/DialogTransition.vue), [dialogFocus](../src/services/dialogFocus.js) |
| Popovers and hints | [AnchoredPopover](../src/components/AnchoredPopover.vue), [TableTooltip](../src/components/TableTooltip.vue), [DisabledActionHint](../src/components/DisabledActionHint.vue) |

## Theme, typography and visual roles

### Independent appearance

CoBudget's light, dark and system settings must work even when Nextcloud uses
another theme. Scope overrides to `#content.app-cobudget` and CoBudget-owned
portals; leave the global Nextcloud header, account menu and search unchanged.
Resolve foreground/background pairs together, including secondary text,
translucent navigation/sidebar surfaces, separators and disabled controls.

- Set native `color-scheme` for date fields and other browser controls. Keep the
  amount field and selected text readable in both themes; selection needs its
  own foreground as well as the light/dark primary tint.
- Theme `NcSelect` fields and detached options together. Native popovers use
  `cobudget-menu-popover`; action items use the existing CoBudget/report menu
  classes. Native dialogs use `cobudget-native-dialog`, custom ones
  `cobudget-dialog`. Nested dialogs and color-picker popovers need the same scope.
- Use [folderPicker](../src/services/folderPicker.js) for Files folder selection.
  Set its themed container before building the picker; include nested New folder
  UI and clean up on success, cancellation or failure.
- Use [notifications](../src/services/notifications.js) for native toasts. Resolve
  the message container's palette before rendering and clean it up on removal
  or failure; adding a class after Toastify renders is too late.
- Use `currentColor` icons and scoped semantic tokens. Avoid hardcoded white
  panels, legacy background-image icons and blanket SVG fill overrides.

### Type and spacing

Use role tokens from `tokens.css`; native component typography takes precedence
inside native controls. Do not create per-screen letter-spacing or boldness.

| Role | Current default / rule |
| --- | --- |
| Body, native field values, table headers | 15 px; regular weight |
| Page navigation title | Native element text, 15 px / element weight (normally 500) |
| Section / subheading | 20 px / 15 px, shared heading weight (normally 600) |
| Caption and secondary compact metadata | 12 px |
| Report metric / compact metric | 22 px / 18 px; report mobile metrics use 18 px |
| Content / section / settings-heading gap | 12 / 24 / 48 px, through role tokens |
| Standard / compact card padding | 20 / 16 px |
| Narrow form | 900 px maximum width with shared inset and responsive overrides |
| Standard field / compact clickable target | 44 px / native clickable area (normally 34 px) |
| Mobile target | At least 44 px; compact controls expand at the mobile breakpoint |

Use shared section-heading classes and one source of spacing between adjacent
sections; avoid adding child margins to an existing container gap. Legacy
compact custom-field labels have their own role; do not apply their small muted
style to native form labels or payment-editor labels.

### Icons, colors and badges

Prefer outline MDI icons for navigation and actions. Income/expense choices use
Plus/Minus; trends belong in summaries. Delete uses `TrashCanOutline`, visibility
uses Eye/EyeOff. Use [CoBudgetIcon](../src/components/CoBudgetIcon.vue) for the app
symbol, not a separately colored image. Decorative icons are hidden from
assistive technology; icon-only actions have localized accessible names.

Area folders are a deliberate filled-icon exception: configured color or the
primary blue fallback, with `FolderOutline` for Without area. Storage folder
choices use `FolderOpenOutline`. Keep category identifiers stable through
[categoryIcons](../src/components/categoryIcons.js); unknown symbols have an
outline fallback. The icon picker restores focus to the selected choice after
its focus trap is ready.

Keep income/success and expense/error meaning distinct from chart-series or
area identity. Payment status colors retain their meanings: important yellow,
review red, tax purple; other label icons use their existing semantic palette.
Do not recolor user-selected areas or budget thresholds as generic UI status.

Use native `NcChip` for removable filters and workspace/member metadata where
already established. Compact financial/status badges retain their dedicated
`status.css` treatment. Circular category symbols, avatars and progress bars
are role-specific shapes; ordinary buttons use native element radii.

## Fields, actions and feedback

### Forms and actions

Use native `NcInputField`, `NcSelect`, `NcFormBox`, `NcFormBoxSwitch` and
`NcRadioGroup` for their supported roles. External form labels use
`cobudget-form-group`/`cobudget-form-label` without extra native-label offset.
Keep visible associated labels, `aria-invalid` and error descriptions.

Existing custom fields opt in with `form-control` and `data-cobudget-field`
(and the compact attribute where needed). They retain the shared border,
radius, height and invalid-state rules. Native field/select wrappers use
`cobudget-native-field`/`cobudget-native-select`: one inset outline, strengthened
on hover/focus, without doubling the native border. Invalid states take priority;
disabled fields retain native behavior. Custom controls use the shared focus
helper, including an inset variant when an outer ring would be clipped.

`NcButton` appearance uses `variant`, while `type="submit"` is the HTML type.
Do not use `type="primary"` or `native-type`. Completion actions use primary;
Cancel, Close and Back use tertiary; destructive actions use error. Existing
`CbButton` variants remain available on their established surfaces; do not
introduce new local copies of their styles. A final Save action has no Plus icon.

Busy controls are disabled, suppress hover and prevent repeat submission in
handlers as well as markup. Show retryable inline errors without discarding
input. Native note cards communicate information, warning and error roles;
avoid manually recoloring them or duplicating a message in several places.

### Hints and interaction states

Native controls own their hover, selected, disabled and keyboard behavior.
Custom interactions use the shared hover/active/selected tokens, rather than
reusing a persistent selection color as hover. Tables use resolved opaque
backgrounds so pinned cells and status tints remain consistent.

Tooltips share the CoBudget surface and normal 15 px text, weight 400 and line
height 1.5, including nested text. Use
[tooltipVisibility](../src/services/tooltipVisibility.js) for delayed pointer
hints (400 ms opening, 100 ms closing); keyboard focus and explicit activation
remain immediate. Clear timers on leave, dismissal and unmount. Passive hints
on an existing link or option must not add a second tab stop.

`DisabledActionHint` wraps a genuinely disabled action with a focusable,
accessible explanation. Use it for permission, lifecycle or confirmation
blockers; do not enable a destructive button just to make its tooltip reachable.
Avoid duplicate static blocker cards when the hint already explains the same
reason. Keep independent errors, retry actions and necessary scope descriptions.

## Shell and navigation

Reuse native `NcContent`, `NcAppNavigation`, `NcAppContent` and `NcAppSidebar`.
The desktop navigation is 300 px wide; the center fills the available space.
Only an open sidebar adds the shared end gutter. Keep native scroller backgrounds
consistent so collapsed navigation does not leave a light strip. The navigation
toggle keeps its native control/state with the shared DockLeft mask.

`AppPageHeader` aligns title, Back and toggle on the same native clickable-height
axis. Mobile titles have no extra block padding. Report periods or context lines
may occupy their own rows; preserve visible focus rings and long-label wrapping.

Desktop creation goes after the sidebar toggle and before the title, using the
shared creation slot and grid spacing. Payments, areas and budget goals show
“New”, with a context-specific tooltip/name, and open the form directly. Mobile
uses the existing floating action, native radius, scroll contraction and
safe-area/pagination clearance. Preserve the scoped coarse-pointer focus-return
exception; do not remove keyboard focus rings elsewhere.

### Destinations and title menus

- Sidebar payment destinations are Payments, Income, Expenses and Planned as
  siblings. Feature availability controls them; an empty result alone does not
  remove navigation. Labels are not separate sidebar destinations.
- Main destinations follow as Areas, Budget goals, Reports, Settings. Areas show
  active children; an empty group has neither collapse arrow nor lower divider.
- Payments' title menu includes enabled label presets without a “Quick selection”
  heading. Area and budget title menus omit those presets, list their contextual
  records directly below the corresponding destination and add a divider only
  when that list exists. A currently viewed archived area can remain reachable.
- Reports and Settings reuse title navigation without label presets. Navigation
  remains available when the sidebar is visible; the period filter is a separate
  toolbar control. Do not restore the older title/period toggle behavior.
- Title-trigger hover is the native light primary tint. Its open state and the
  current menu destination use neutral gray. Scope this Files-style exception
  to title navigation, with `aria-current` for the selected destination.

Menus use bounded scrolling, natural order and focus return. Route changes keep
unsaved-change guards authoritative; pending navigation cannot bypass them.
Use `titleNavigation` for destination/period transfer rather than forwarding
unrelated filters. Workspace selection uses trigger-width rows, an active check
and Base marker, focuses the active row, and persists a different workspace
before reloading. Choosing the current workspace simply closes the menu.

## Filters and period selection

Payments, area payments and reports share the filter presentation. Responsive
shortcuts depend on available content width, including an open sidebar, not only
viewport width. When shortcuts disappear, their choices remain in the full
Filter menu. The payment Review shortcut requires the feature and either a
positive review count or an already active review filter.

`AnchoredPopover` follows moving triggers while toolbars resize or scroll.
Reposition only when anchor geometry changes, without remounting the panel or
losing input focus; dispose animation frames and listeners. Use the shared
320 px filter panel and padding tokens within viewport bounds. Native popovers
own the outer surface; do not add another shaded box inside them.

### Choices and application

- Use `PaymentFilterSelect` for category/partner hierarchy, unassigned choices,
  grouped income/expense choices and labels. The main filter flows use its
  native switches; reuse the component's checkbox mode only where requested by
  its consumer. Retain hierarchy and search context rather than flattening it.
- Multiple values within a dimension combine as alternatives; dimensions combine
  as restrictions. Whole income/expense groups can coexist with individual
  values from the other group. “All labels” clears label restrictions.
- Without area has ID `0`, distinct from an unrestricted scope. Hide area filters
  when there are no active areas, including an archived-only catalog; previously
  selected restrictions must still be removable through chips. Hide unavailable
  choices without silently clearing selections.
- Submenus use Back and consume both Escape phases before returning focus. Most
  multi-selections apply immediately and stay open. Search uses the existing
  debounce and Enter/blur flush; reset cancels pending work. Preserve focused
  amount drafts and cancel stale updates rather than resurrecting cleared values.
- Availability and counts come from the complete matching dataset, not the
  visible page. An active/open receipt or reminder filter stays removable even
  if it produces no results. Treat zero as a valid amount bound.

### Periods and active-filter chips

`ReportPeriodPicker` serves the payment, area and report calendar flows. Keep at
least one year selected; show recent years first and expand older years when
needed. Months form two chronological columns. No individual month restriction
means all months; removing the final selected month restores that state.
Non-adjacent years/months apply immediately. Custom inclusive date ranges retain
explicit Apply and cancellation of unapplied drafts.

The default current full year is shown as its year number. Other selections show
“Period” and a blue active dot, with an accessible description/tooltip of the
actual selection. An equivalent complete-current-year selection is not marked
active. Do not confuse this payment/report default with an area's All time reset.

Active chips use native `NcChip`, an appropriate icon from
[filterChipIcons](../src/services/filterChipIcons.js), native rounding and text,
wrapping between chips and ellipsis/full-label hints within a chip. Avoid custom
button-like pill sizing. Accent dots are non-interactive and have an accessible
text equivalent; defaults do not show one.

- Payments: chips follow the summary and precede the table.
- Areas: use `PaymentFilterBar` in chips-only mode above the payment table.
- Reports: chips follow the grouping/breakdown/future-payment controls and
  precede Development/Forecast, rather than preceding the whole toolbar.
- Use equal top/bottom chip spacing (seven grid units) without an additional
  summary margin. Removing a chip changes only its dimension. Report reset
  clears filter drafts while retaining period, area scope, grouping and future
  selection; payment/area reset uses its configured default period.

## Tables and summaries

### Shared table treatment

`EntryTable` is the common payment table, including report top payments and
historical payment lists. Management, member, criteria and settlement tables
reuse the flat treatment: no outer frame or rounded panel, neutral body-sized
regular headers, separators between rows and no decorative header letter spacing.
Keep each table's specialized columns, pinning and mobile transformation.

Payment headers are 44 px and desktop rows 40 px by default. Use native-sized
sort icons with reserved space, `aria-sort` and keyboard-operable header actions;
read-only headers must not imply sorting. Totals use the main surface and a top
separator, without a doubled line from the preceding row. Annotations use the
caption role; static balance/repayment rows do not acquire clickable hover.

Full-width views use shared inset variables. Mobile payment rows can bleed to
page edges while headings and pagination retain the normal gutter; nested dialog
and top-payment tables disable that bleed. Desktop sticky headers use a bounded
scroller with pagination outside; mobile keeps page scrolling.

### Payment row anatomy

Column fitting uses [paymentColumnWidths](../src/utils/paymentColumnWidths.js):
measure actual visible names, headings, icons and sort space, preserve useful
description width, then shrink secondary columns. Fixed date/amount/payer/action
roles stay stable. Batch layout reads before writes and dispose resize/font
observers. Do not replace this with unrelated per-screen widths.

Desktop labels precede the description as compact icon chips; overflow has a
count and a tooltip with all label names. Mobile shows the prioritized status
chip/count beside the category area without clipping its badge. Keep exported
labels independent of visual truncation.

The 5 px left marker belongs only to an assigned area, using its configured
color or primary fallback. Unassigned payments continue the row background,
including selected/status-tinted rows; labels never supply the marker color.
Deduct marker width from first-cell padding so dates align. Keep area hints
keyboard-accessible without contaminating exported date text.

Payer avatars use the shared identity helper and safe former-member fallback,
with full-name hints. The optional payer column follows the amount and is omitted
in consumers that do not need it. Totals and closing rows account for its width.

Amounts use regular signed text and income/expense colors. Shared-payment and
recurrence icons sit immediately **before the amount in one non-wrapping line**,
including mobile. Reminder, receipt and settlement metadata may occupy a separate
line. Shared hints explain area, original total, personal share and unsettled
state using canonical source amounts; omit unavailable totals rather than
estimating them from a percentage.

Mobile category/title, partner and description retain a clear hierarchy: native
body title, muted secondary text, no empty lines, bounded category/description
wrapping and truncated long partners. Align category/status/amount to the first
text band even when a description adds lines. Preserve the measured layout and
its observer cleanup.

There is **no mobile three-dot menu**. A writable row opens the editor, where
history and permitted deletion are available. Read-only rows keep a direct
history action below the amount; desktop retains the native action menu and
copy action. History always uses the original selected payment ID.

Keep chronological grouping, continuation headings and stable row keys without
mutating or deduplicating source entries. Month/day styling must preserve status
meaning. Closing totals use the complete filtered set, not the current page.
A closing-row report link is available only when its scope can be represented
exactly; otherwise show the reason rather than opening a broader report.
Historical closings remain read-only, with passive amount hints.

Pagination uses native tertiary Previous/Next buttons, normal native text and
arrow slots, with contained, symmetric hover areas. Next uses the native reversed
icon alignment. Center the range information and retain layout space for an
unavailable direction. Mobile targets and floating-action clearance still apply.

### Summaries

Reuse [MobileFinanceSummary](../src/components/MobileFinanceSummary.vue) and
[ReportSummary](../src/components/ReportSummary.vue). Summary cards are unframed,
with shared widths, gaps and icon alignment; report metrics intentionally use a
different density. Desktop summary links have neutral hover, no extra arrow and
no activation after a drag. Touch uses native horizontal swiping.

[Payment summaries](../src/services/paymentSummary.js) must represent the exact
payment scope, falling back to payment calculations when reports cannot express
it. Show current-month figures only when today belongs to the period, avoid a
duplicate exact-month statistic and hide monthly averages for a single selected
month. Budget summaries require a complete evaluation period; do not show current
budgets as historical figures. Area-summary report links preserve the area's
financial perspective and intended All time scope.

## Payment sidebar

The editor uses native `NcAppSidebar`, kept mounted while its `open` state drives
the native lifecycle. Preserve form data through closing, disable interaction
while leaving and avoid delayed callbacks clearing a newly opened editor. Use
the native sidebar transition, not the dialog transition wrapper.

Desktop shows Payment type in the native info slot and a visually hidden
[EntrySidebarHeading](../src/components/EntrySidebarHeading.vue) naming the aside.
At the sidebar's mobile breakpoint (1024 px), use a concise header with persistent
Save/Close and put the single type field first in the scrolling body. When income
is disabled, retain a plain contextual title instead of a redundant selector.

### Primary fields and optional features

- The labeled type field owns Plus/Minus and income/expense colors; the amount
  stays neutral, with currency inside its calculator field and no inner segment.
  Date/amount share a row when their own container fits them and stack otherwise.
  Native date fields retain required/minimum constraints and local-date parsing.
- Initially focus/select the amount only after opening and loading. Use
  `preventScroll`, adjust only the form scroller and never steal focus from a
  confirmation or busy/closed editor. Type changes preserve the amount, clear
  only dependent choices and restore focus after the native selector closes.
- Editor group labels use native body/element typography, small label-to-control
  spacing and shared dense group gaps. Description is a native field. Hide the
  Labels caption when no labels are available.
- Label and direct-area choices use normal native value text, field surfaces and
  a single inset border; selected state uses primary or contrast-safe area color.
  Use [areaColor](../src/utils/areaColor.js) rather than passing unresolved CSS
  variables to a hex-color helper. Mobile choices retain 44 px targets.
- Receipts, Repeat and Reminder are always discoverable as compact rows without
  an enclosing disclosure box. Align their icon centers and text starts with the
  left label choices, and use the receipt button's native hover consistently.
  Receipt actions use a wide tertiary Paperclip button. Repeat and Reminder use
  matching wide tertiary buttons with planned/bell icons, an expanding chevron
  and `aria-expanded`. Show the shared blue `cobudget-filter-dot` before the
  chevron when an interval other than Once or a reminder date is configured,
  independently of whether the details are expanded.
- Saved receipts collapse behind “1 receipt” / “2 receipts”, without “linked”.
  Pending uploads open the list; errors remain visible when collapsed. Keep
  filenames regular weight and the list transparent, with remove actions aligned
  to the switches below. Receipt removal still requires its normal confirmation.
- Expanding recurrence or reminder details scrolls them into view only
  when needed without moving keyboard focus. Initial data loading does not
  auto-scroll. Opening or collapsing either section changes no saved fields.
  Choose Once in the recurrence interval selector to disable repeating; new
  payments start with Once. Both sections always start collapsed, including
  when editing payments with existing recurrence/reminder settings.
  Group the expanded payment recurrence and reminder fields in matching rounded
  fieldsets using the shared `planning-fields` class, without
  an outer inset or border, using a translucent theme surface and standard
  content padding on all sides. Do not add an outer gap between the disclosure
  row and either box; the top padding supplies the spacing above the fields.
  Keep Show reminder beneath the box, separated by the standard field gap.
  Use Remind me on, Title, Description and the four action labels. Completion
  and archive switches belong only to the Reminders module; payment forms
  preserve those states. The payment editor omits automatic archival, preserves the existing personal
  choice and enables it only for new reminders. Clear the reminder date and save to remove a payment reminder;
  no separate removal button is needed. Keep text and labels locally while
  changing the date so incomplete keyboard edits do not discard them. Respect reduced motion.

### Area, lookup and member selection

Few available areas use direct choices; larger catalogs use `NcSelect`. Keep the
responsive one-/two-column layout and the current area's editable context. After
a **successful** empty active-area load, new/unassigned payments hide the field
and use Without area automatically. A failed load must not clear a selection;
an existing assigned payment retains its area context, including archived areas.

`EntryLookupSelect` preserves stable IDs, category hierarchy, non-selectable
frequent/group headings and scoped availability. Parent context is shown when
needed, without repeating an adjacent parent heading. Optional numbers are
reactive opt-in display preferences; hiding them must not erase stored values.
Inline New/Add creates a local draft only; typing or searching does not create
records until the payment is saved. Keep detached menus inside the visible
sidebar/keyboard viewport and dispose positioning listeners.

Member selection uses native searchable choices and `MemberIdentity`, retaining
safe historical identities without requesting removed users' account data.
Required choices are non-clearable and validated. Closed choices remain compact;
open options can wrap. Historical inactive members are not new selectable payers.
Keep the “Use area split” choice and permission-specific allocation controls.

### Drafts, deletion and planned entries

Query-only changes to filters, sorting and pagination do not overwrite the
editor's independent draft. Path/area changes keep unsaved-change guards.
Refreshing a filtered list without the edited row is not evidence of deletion.

The footer is outside the scrolling body, aligned to its gutter, with Cancel and
Save and a conditional destructive action. Deletion permission is based on the
original row, separately from edit permission. Future-date, delete, receipt and
discard confirmations use native dialogs; protect pending writes, capture their
target and ignore stale view updates after navigation. A late successful write
may refresh shared data but must not close or remove another editor's record.

Open shared payments keep their area fixed and show permitted payer/split
controls. Released personal shares keep the original area, hide allocation
controls and explain that changes affect only that participant. Preserve source
identity for history and do not reconstruct permission from the displayed share.

Planned rows distinguish the future first payment from the next recurrence.
Use canonical original/due dates and preserve an existing timestamp on edit;
new future recurring payments use the editor's 06:00 local-calendar first-date
preview. Recurrence hints use the API's effective `recurrence_due_date`, formatted
in the viewer's timezone, rather than the legacy stored next-date timestamp.
Do not recalculate server recurrence timing in the display. See
[recurring-entry rules](architecture.md#recurring-entry-rules) and
[operations](operations.md) for timing and cron behavior.

## Reports

Use the filter and table rules above, with report-specific components for scope,
comparison and section controls. Financial interpretation, non-contiguous dates,
forecast assumptions and export contents are defined in [reports.md](reports.md).

- `ReportSelect` uses native single-choice actions with typed values and closes
  on selection. Comparison choices stay open for multiple selections, show their
  count and hide unavailable choices. Do not coerce distinct option values.
  Text-only menu rows have no empty icon gutter and use regular native text.
- Development/Forecast and related section tabs use lightweight heading text,
  selected weight, tab semantics and Arrow/Home/End keyboard behavior. Mobile
  targets are 44 px. Use the shared section/content gaps without double margins.
- Each visit to Reports starts with Development. Keep the selected tab through
  filter/period remounts within that visit via the account-scoped helper; do not
  restore a previous visit's Forecast tab. This includes detail charts and
  navigation within Reports; persistent section-collapse preferences remain
  independent.
- `ReportSectionMenu` owns section collapse/export actions; chart-mode controls
  remain separate. Header exports use the native menu and CSV destinations;
  Print calls CoBudget printing with its normal loading/availability guards.
- Hierarchical rows share [reportTree.css](../src/styles/reportTree.css). Reserve
  disclosure space only for expandable structures, keep connectors aligned and
  avoid empty gutters for flat rows. Area subtotals retain category hierarchy;
  a lone Without area subtotal is not an artificial disclosure. Only actual
  category rows receive category symbols.
- Report-row hover lines/backgrounds cover all cells, including pinned cells;
  hover decoration must not become a persistent focus/selection border.
- Period tables share one synchronized `DraggableScroller`. Scroll controls show
  only for overflow and account for pinned columns, RTL and whole period widths.
  Mouse drag requires a threshold and suppresses the resulting click; native
  touch scrolling and child controls keep their own behavior. Dispose observers.
- Printing has an isolated light paper layout, repeated headers and all matching
  detail payments, not only the current page. Do not import the dark shell's CSS
  wholesale. Keep print and CSV scope aligned with the displayed filters.

## Settings and master data

### General preferences and workspaces

[SettingsView](../src/views/SettingsView.vue) keeps general/notification/receipt
preferences in one column and features/labels in the other. Labels follow Features
with shared heading spacing, not an extra divider. Native form-box switches own
their descriptions and styling. Notification preferences use a flat event/channel table with native
`NcCheckboxRadioSwitch` checkboxes, visible Nextcloud/Talk column headings,
explicit event-and-channel accessible names and 44 px targets. Event labels wrap
at mobile widths; unsupported channels display a named dash. Hide the entire Talk
column and its setup hints when Talk is unavailable for the account; retain
load errors with retry and do not clear stored channel preferences. Reminder and planned
payment rows remain visible without areas; shared-area rows retain their feature
gates. Each preference saves independently. Reconnecting the private chat preserves
all Talk opt-ins. Show the Calendar column only when the account has a writable
local event calendar. Only the Reminders row supports it. Enabling without a saved
target opens the native `NcSelect`; persist only after an explicit calendar choice.
The target row appears below the table, with retryable sync errors. At narrow
widths the channel columns keep 44 px targets while event labels wrap.
Related navigation buttons, such as Talk setup,
remain outside the switch label. Feature-navigation return markers and
master-data section jumps (`categories`, `payment-partners`) are one-shot and
applied only after successful data loading.

General preferences save immediately, on selection or the existing blur/Enter
boundary. Validate, serialize writes, disable conflicting actions, and roll back
failed persisted values, including a live appearance preview. Keep independent
export/settings queues and avoid full reloads that discard drafts or focus.

Use native folder rows with the current path and the shared Files picker. Save
only after a changed valid folder is selected; cancellation makes no write. Keep
storage scope and writable-folder restrictions, and disable conflicting folder
actions while picking. Backup schedule fields retain their own text drafts and
validation; creation uses committed valid settings, never an unsaved field value.

`SettingsList` provides borderless rows, separators, wrapping names and actions
that move below content in narrow containers. Workspace Base/Active markers are
non-removable native chips; selection is tertiary and Add is secondary with Plus.
Names use explicit Save and dirty-close guards; visibility saves immediately.
Protect the Base workspace and show contextual reasons through disabled hints.

Personal reset/restore dialogs require fresh eligibility previews and exact
confirmation tokens in handlers as well as controls. Unknown/loading eligibility
is blocked, failed checks are retryable, and server conflicts update the reason.
Keep restore inspection/safety-export and scope warnings. Refresh eligibility
when its context changes without clearing unrelated drafts.

### Categories and payment partners

`MasterDataSettings` uses a flat availability matrix with a sticky identity
column, a Without area column and consistent category disclosure/indentation.
Income and expense sections use shared small headings. Global markers need an
accessible explanation; do not add generic warnings to every editable row.

Identity and availability belong to an explicit Save/Cancel `NcModal`. Use native
name/parent fields, the shared icon picker and responsive availability switches.
Save requires a valid dirty draft. Pending writes block closing; failures retain
input. After a committed write with failed refresh, allow reload/recovery without
resubmitting the mutation. Explain global restrictions only where they apply.
Deletion covers the logical record's eligible occurrences, not just the visible
area; the disabled reason must reflect the complete permission/usage result.

Advanced settings open a separate native `NcDialog`, with available area scopes
in a tinted navigation column and mobile `NcSelect`. Active styling follows the
Files settings variant, with per-area dirty markers. Do not use an all-sections
`NcAppSettingsDialog` or bring back the former inline advanced form.

- Advanced drafts are isolated from the main identity/availability draft. Only
  existing enabled editable scopes are offered; new records must be saved first.
- Saving advanced details updates that baseline without committing, undoing or
  later resending unrelated main-dialog edits. Cancel discards only local advanced
  changes. Guard dirty close/Escape/backdrop, including nested-dialog ordering.
- Partner detail groups use native form-box disclosure buttons, retain collapsed
  drafts and concise summaries, and preserve identifiers/leading zeros as text.
  Use native email validation and scope-specific read-only warnings.
- Mobile editors have a scrolling body and fixed header/footer, equal-width
  Cancel/Save actions and an accessible icon-only Delete where necessary. Native
  controls retain padding when labels/icons change; avoid clipped focus rings.

### Administrator settings

[AdminSettings](../src/components/AdminSettings.vue) reuses native fields and note
cards, responsive columns and the shared settings rows. Keep scoped fixes for
Nextcloud admin-section paragraph margins; do not globally reset paragraphs.
Data-quality actions and results use semantic components, not manual status CSS.

Full backups clearly disclose all-user scope. Storage owner selection uses safe
native identity choices; folder selection/manual path behavior depends on that
owner's scope. Schedule writes are serialized/coalesced without overwriting
independent failed drafts. Backup creation requires committed valid settings in
both button and handler. Keep per-backup restore/download/delete actions and OCC
technical details in their disclosure, rather than duplicating paths or commands
throughout the page. Preserve native guarded restore confirmation.

## Areas, settlements and budget goals

### Overviews and forms

[ProjectList](../src/views/ProjectList.vue) and
[BudgetGoalsView](../src/views/BudgetGoalsView.vue) use unframed single-column rows,
neutral hover, separators outside the hover surface and keyboard activation.
Names use the heading role; mobile rows reach the page edges while content keeps
the shared gutter. Retain wrapping metrics and native progress semantics rather
than nesting extra cards. Budget progress caps the visual bar but retains the
actual value and threshold meaning in its accessible description.

Area rows show relevant membership/share and balance information, omitting
irrelevant personal-area metadata. Archived rows describe their historical state,
not a future default split. The archived disclosure has expanded/control
semantics without lowering all text opacity. No active areas shows native
`NcEmptyContent` with the folder symbol and creation action; archived content can
remain below it without an extra “find archived areas below” message. Loading
failures are not empty states.

[ProjectCreateView](../src/views/ProjectCreateView.vue) is a narrow dedicated form
with draft name/color/members and share preview. Before persistence it has no
lifecycle actions or stored-area scope. Keep validation, route/unload discard
guards, pending protection and navigation refresh after success.

[ProjectSettingsView](../src/views/ProjectSettingsView.vue) starts with name/color,
without a redundant General heading. Name blur/Enter and color changes save their
committed values independently; preserve newer typing when earlier requests
complete. Member percentages retain their own explicit Save flow. A personal
area shows its 100% information without a redundant split table or action.

Shared member tables use `MemberIdentity`, safe former-member placeholders and
native Admin/Former chips. Keep percentage totals aligned with the percentage
column and show validation without duplicate labels. Equal/remainder actions are
tertiary. Partial member-add failures retain unsuccessful choices. Ownership
transfer is an explicit confirmation with member selection, not an immediate
selection-side mutation. Lifecycle availability comes from current server
previews; refresh it without overwriting form drafts and revalidate at submission.

[BudgetGoalFormView](../src/views/BudgetGoalFormView.vue) uses shared heading and
field roles. New goals save the whole draft explicitly; existing identity fields
use their established independent save boundaries. Criteria actions must use
committed identity values. Keep Without area distinct from unrestricted scope,
retain invalid/failed drafts, and block choices until their catalogs are valid.
An empty criterion set explains its scope through a note card. Mobile criteria
become labeled rows with accessible delete actions rather than a horizontal form.
Goal deletion is a separate trailing section and preserves its payments. Prevent
pointer Cancel from accidentally triggering a blur-save, without breaking
keyboard cancellation.

### Area details and settlement history

[ProjectDetail](../src/views/ProjectDetail.vue) keeps existing payment content
mounted during refresh, with inline errors/retry. Use shared desktop section gaps
and compact mobile gaps. Show the settlement-history action only when history
exists; its count and mobile icon remain accessible.

`MemberBalanceList` presents paid amount, own share, balance and the default
percentage. Do not label that percentage as the actual share of all historical
payments. Mobile identities and values reflow without horizontal scrolling;
optional income details have a tertiary disclosure, initially collapsed.
`RepaymentList` uses From/To/Amount columns, wrapping names and no clickable hover.

Settlement confirmation uses native `NcDialog` and
[SettlementSummary](../src/components/SettlementSummary.vue): area, creator,
complete open shared-payment count and total. Its scope is independent of table
filters/pagination and includes the relevant future payments. Summary and
repayments retain the saved currency where applicable. Capture area/permissions,
block conflicting writes, retain errors for retry and make Cancel non-mutating.

[ProjectSettlementsView](../src/views/ProjectSettlementsView.vue) uses a flat
semantic history table, keyboard-accessible detail actions and stacked mobile
metadata. Details focus the summary initially, then show member balances,
repayments and payments. Lazy payment pages cache successes and retry failed
offsets. Keep loading/error states visible even before a count is known. Historical
balances remain labeled as recorded values. Nested payment history returns focus
to the settlement dialog; historical `EntryTable` rows are read-only with the
appropriate desktop/mobile history action.

## Dialogs, motion and focus

Prefer native `NcDialog`/`NcModal`, with the shared theme and chrome classes.
Use the standard title spacing, accessible close button, contained scrolling and
mobile safe areas. Ordinary confirmations have end-aligned wrapping actions;
full mobile editors retain their equal-width Cancel/Save layout. `NcDialog`
content with additional controls uses an explicit default slot rather than
assuming a `message` prop will render beside it. Pending protection uses the
supported `no-close` API, not an invented `can-close` prop.

Destructive, future-date and discard confirmations keep their semantic variants
and exact-token requirements. Initial confirmation focus targets Cancel where
appropriate. Read-only history can close during loading; stale responses must
not reopen it. [EntryHistoryModal](../src/components/EntryHistoryModal.vue) keeps
its accessible horizontally scrollable, multiline history table inside native
dialog behavior, without a second backdrop, focus trap or arbitrary z-index.

`DialogTransition` owns the teleported host; condition the host rather than
prematurely removing its child. Native/custom modal entry/exit timing is shared,
including Vue-version class compatibility. Closing snapshots are inert and hidden
from assistive technology while leaving; do not delay model updates to fake a
transition. Reduced motion removes delays. Popovers/subpanel entry use their
short shared fade/scale; custom hover transitions affect colors/borders, never
layout, focus or every property. Sidebars keep their native motion separately.

[dialogFocus](../src/services/dialogFocus.js) captures pointer/keyboard origins
and scroll parents. Use the native return-focus hook with `preventScroll` and
suppress duplicate automatic returns. Retry temporarily disabled targets after
leave, handle recreated controls by stable identity, and use a nearby row/section
when the original disappeared. Restore only after the native close lifecycle;
never steal focus from another active control or restore into a different route.
Refresh lists in place, and clean up observers/listeners. This applies to nested
settings, history, confirmation and picker flows alike.

## Reminders

`RemindersView` uses the shared page header/navigation, native filters, a semantic
payment-like table and a lazy native `ReminderSidebar`. The sidebar is called
**Reminders**, as is the overview. The title always opens the shared module
navigation. Filters share the payment toolbar row, native menu choices, active
indicators and removable chips; narrower containers collapse shortcuts into the
complete Filter menu. The date range is available only in the Filter menu, not
as a toolbar shortcut. Labels use multiple native switches with All labels
(empty selection) as the default, including unlabelled reminders. Offer only
Cancel contract, Renew, Review and Pay; selected labels match any own reminder
label, never inherited payment labels. Label chips/reset restore All labels.
Fields have external labels,
including title and multiline notes. Inherited payment labels use the read-only
`PaymentLabelList` icon badges and tooltips in the table only, never in the
reminder editor. In the table, own action labels use `NcChip` with their matching
`NavigationIcon`, no close action and the Files system-tag treatment: transparent
background, neutral theme border/text, 32 px pill height and 5 px/10 px padding.
Keep action labels distinct from inherited payment icons, including Review when
both are present. Only Cancel contract, Renew, Review and Pay are selectable in
the editor, using icon/text checkbox buttons sharing `label-toggles.css` with
the payment editor. Never send inherited labels in the reminder write payload. The editor begins with Remind me on beside the native close action, followed
by Title and Description, without a repeated visible Reminders heading. Initial
focus goes to the date after the sidebar opening transition. Match the
payment sidebar label roles, 16 px field gaps, native input interiors and desktop
transparent surface. Use `ModalActions` so Cancel remains beside Save; deletion
stays in the separate leading slot. Completion has an accessible checkbox;
clicking a row or using Enter/Space on it opens editing. Titles remain plain
regular text. Reminder rows use the payment selected-row background and desktop leading accent
while that reminder is open in the editor. Selection follows accepted editor
changes, remains unchanged when discarding is cancelled, and clears for new
drafts or when the editor closes.
Columns are Completed (centered heading and checkbox), Status,
Remind me on (date only), Title, Labels and a trailing action menu. For open
reminders, append Today or N days ago below the date; compare browser-local
calendar dates so midnight and DST work correctly. Earlier-day open reminders
use a subtle warning-tinted row and warning text for the relative date, with
selected-row styling taking precedence. Completed/archived reminders retain their
existing colors and omit urgency text. Refresh relative dates on focus/load and
the existing minute timer. A small warning-colored calendar icon beside the date
appears only when that reminder's calendar synchronization or cleanup is pending.
Use an icon-only native button with a passive `TableTooltip` on hover and keyboard
focus, an accessible retry label and a loading indicator. Stop row activation
from this control; serialize retries, refresh status in place and report continued
failure instead of claiming success. Keep unsaved sidebar drafts untouched. Reuse
`NcActions`/`NcActionButton` and payment menu classes for desktop Edit/Delete.
Hide the row menu in the compact mobile layout; tapping the row opens its editor,
which retains deletion and date editing. For open reminders, offer a native Remind me later submenu with Tomorrow,
In one week and Choose date. Quick actions use 09:00 browser-local time relative
to today; disable choices that would not move the existing reminder later. Choose
date opens and focuses the existing sidebar date field and requires Save. Route
quick actions through the sidebar's pending/dirty-draft guard, keep failed drafts,
and preserve all unrelated saved reminder fields. Completed/archived items do not
offer quick postponement. Stop row activation from nested actions; route deletion through the
sidebar draft guard and confirmation before making a delete request. Status shows Open, Completed,
Archived or both Completed and Archived. Use shared `data-cobudget-badge` roles:
info for Open, success for Completed and neutral for Archived. Render these
as 32 px outlined `NcChip` pills matching action tags, with state icons and
theme-aware text/border colors and soft blue, green or gray backgrounds. Status
borders use the same single 1 px outline and padding as action tags. Open uses
Nextcloud primary blue in light mode and the readable info text role in dark mode. Combined states keep separate tags,
wrapping within the cell. Use `TableTooltip` for completion and
archive dates, including the effective date of automatic archival. Headers are
not interactive. Keep the fixed open/completed/archived order, then ascending
reminder date and ID, before pagination. Within each status group, add month/year
headings matching payment month headings only when the visible page contains
more than one distinct calendar month/year. A single month needs no heading, even
across different statuses. Headings do not count towards page size. Default to open and completed, excluding archived even when also completed.
Use `PaymentFilterSelect` with multiple native switches for status selection;
an empty selection shows no rows. Reset restores open and completed. The Payment
link filter uses the same multiple switches: Without payment, Payment without
area and Payment from an area. These groups are mutually exclusive; all are
selected by default. An empty selection shows no rows; removing its chip or
resetting filters selects all three again. Empty
lists offer a creation hint and action; filtered empty lists offer filter reset.
Date-only editing schedules new or rescheduled reminders at 09:00 local time.
On desktop module entry, open a fresh reminder once after initial loading unless
a deep link or an explicit user action already chooses the editor. Refreshes must
not reopen a manually closed form. Mobile starts with the overview. The Payments
and Reminders New actions remain visible while their editor is open; New starts
a fresh draft through the normal discard guard, rather than closing the editor. Below 700 px of available content width, group reminder rows under local date
headings (weekday, day, short month; include other years), matching Payments.
Hide desktop month headings and repeated dates inside rows. Keep the existing
open/completed/archived sort order and restart date headings at status or page
boundaries. Show Today/N days ago beside the open group's heading. Place the
checkbox on the left, the regular-weight title (up to two lines) and one-line
description in the middle, and status badges aligned right. Hide the three-dot
menu. Labels follow under the text; a pending-calendar retry stays accessible
under the status. Preserve full details in the editor and theme-aware backgrounds.
Reserve mobile end padding so the floating New action cannot cover the final row.
Mobile reminder rows use the same muted-surface blend as payment rows (65% muted
surface mixed with the base surface); overdue and selected colors take precedence.
Below the desktop sidebar breakpoint (1025 px), the reminder editor uses a
separate New reminder/Edit reminder header with the native close control. Move
the date field into the scrollable form above Title, rendering only one date
input across viewport changes. Desktop keeps the date beside the close action.
The mobile sidebar takes the screen width. Filters must shrink with the available content
width when the desktop sidebar opens.

Reminder delete/discard confirmations use the same native `NcDialog`, shared
standard-dialog chrome and action spacing as Payments. Delete uses the native
error button variant; preserve the explanation of personal deletion scope.

Save, delete and archive preserve pending/dirty guards and retained errors. Closing
or navigating away from a draft requires discard confirmation. Use native tertiary buttons with a trailing right arrow for Show reminder in
the payment editor and Show payment in the reminder editor. Preserve the linked
record and workspace targets plus unsaved-change guards; omit explanatory
linked-payment copy from the reminder form. The calendar warning action opens a native standard dialog when fields conflict.
Show both values per field with native radio choices, no preselected winner; preserve
errors and require a fresh token after concurrent changes. The editor shows a time
field for imported non-09:00 times and preserves that clock when changing the date.
The calendar settings remain in the existing notification preferences. Use role
colors/native controls for light and app-only dark mode, including detached menus.

Reminder sidebar status controls use the same transparent `NcFormBoxSwitch` rows,
20 px outline icons and hover treatment as payment Repeat/Reminder controls.
Automatic archive inversely maps to `keep_open`; completion and manual archive
are shown only for existing items and are committed with the form.

## Keyboard shortcuts

[keyboardShortcuts](../src/services/keyboardShortcuts.js) handles Ctrl/Command+S
for the open payment/reminder, Ctrl/Command+N or Alt+N for a new reminder in
Reminders (a new payment elsewhere), and
Ctrl/Command+P for CoBudget printing in Reports. Reuse normal validation,
permissions, pending and discard guards. New means create, not toggle the current
editor; Print outside Reports remains a browser action.

Ignore composition, repeated keydown, unrelated extra modifiers and focus in
external/nested UI. A shortcut must not submit a form behind a dialog. Native
Escape handling remains intact, and listeners follow app/view lifetimes.
[KeyboardShortcuts](../src/components/KeyboardShortcuts.vue) lists scope and
browser-reserved limitations with native `NcKbd` and platform-aware labels before
Reset all in Settings.

## Deliberate CoBudget variants

These exceptions are scoped product decisions. Their detailed rules live in the
sections above; do not use them to justify unrelated per-module styling.

| Variant | Purpose and retained shared rules |
| --- | --- |
| Payment sidebar and calculator field | Dense entry workflow, native sidebar lifecycle, labeled type control and currency/value together; shared field outline, theme and mobile header/footer |
| Hierarchical/compound lookups | Preserve category structure, scoped choices and deferred inline creation; native selection, spacing, focus and keyboard behavior |
| Compact payment rows and label icons | Scan many payments while retaining ownership, sharing and status; regular body text, accessible hints and specialized mobile layout |
| Report text tabs and metric density | Keep analytical sections readable without settings-style segments; tab semantics, native focus, shared headings and touch targets |
| Full-width reports and bounded desktop tables | Preserve data space, pinned columns and local horizontal scrolling; mobile page scrolling and gutter rules still apply |
| Narrow forms and full mobile dialog bodies | Fit editing tasks rather than report grids; shared title, actions, contained scrolling and theme |
| Category circles, avatars, progress bars and area colors | Convey identity or financial meaning; contrast and textual/accessibility equivalents remain required |
| Title-navigation open/current colors | Match Files' neutral open/selected menu treatment; scoped to title navigation, preserving keyboard and current-destination semantics |
| Touch creation-trigger focus return | Avoid the established stale ring on coarse pointers; never generalize to keyboard focus |
| Print layout | Readable paper output with repeated headers and full report scope, independently of app appearance |

## Verification and maintenance

For a shared design change or new module:

- Identify affected components, role tokens, consumers and deliberate variants
  before adding CSS. Use localized strings and logical alignment.
- Check normal, hover, focus, selected, invalid, disabled and busy states;
  keyboard activation, Escape, nested overlays, focus return and unsaved drafts.
- Check light/dark/system, especially **CoBudget dark + Nextcloud light** and the
  reverse, including sidebars, detached selects, menus, nested dialogs, folder
  pickers, toasts, tooltips, selected text and the admin entry point.
- Check narrow mobile (for example 390 px), 768/769 px, the sidebar's 1024 px
  boundary and desktop. Include narrow containers inside wide pages, long
  translations, real hit areas, overflowing content and clipped focus rings.
- Check empty, loading, failed and populated states. Preserve sticky columns,
  sidebar motion, mobile navigation/action clearance, reduced motion and print
  layout where affected. Use a real touch device for touch-specific changes.
- Run relevant checks from the [test guide](../tests/README.md) and
  [AGENTS.md](../AGENTS.md), build/update local instances for code changes, and
  inspect the affected rendered surfaces. A successful build or source review
  alone does not establish visual correctness or supported-version compatibility.
- Report fixture and verification limits explicitly. Historical results belong
  in [verification history](verification-history.md), not as current guarantees
  in this design contract.
- Update this guide in its existing topic section when a shared rule changes;
  avoid appending a second competing rule. Record a new exception's role, user
  benefit, scope/source, retained shared rules, responsive/theme/keyboard behavior
  and verification performed or remaining.

For documentation-only changes, check links, anchors and `git diff --check`.
No frontend build, local app update or version bump is needed.

### Reminder summary in Payments

The first summary card uses the existing desktop stat card and mobile period-card
styles. It links to the account-wide reminder overview and shows the open count
and up to two earliest titles/dates, independent of payment filters. Hide it when
empty or when the reminder feature is disabled; truncate long titles while keeping
dates visible.

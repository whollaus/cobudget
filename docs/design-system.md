# CoBudget Design System

Use this guide for changes to existing UI and for new modules. It records the
shared rules and deliberate CoBudget variants established in October 2026.
Repository documentation is in English; visible UI strings remain localized.

## How to use these rules

1. Choose the role of the element (field, action, metadata, metric, navigation,
   table, dialog), then reuse the corresponding component or style below.
2. Use the named CoBudget token rather than copying its current pixel or color
   value. Values in this guide describe the current defaults, not constants to
   repeat in components. Nextcloud theme and clickable-area values can vary.
3. Preserve the [documented exceptions](#deliberate-cobudget-variants). A local
   hardcoded value or old override is not, by itself, an intentional exception.
4. Change a shared rule at its source and check every consumer, including admin
   settings and teleported UI. Do not fix one screen by globally restyling all
   Nextcloud buttons, inputs, headings or SVGs.
5. When a genuinely different interaction needs a new variant, record its
   purpose, scope, source component and validation here in the same change.
   Prefer one reusable variant over repeated module-specific overrides.

This guide is the maintained design contract. CSS and component files implement
it; update both when a rule changes. Historical design audits and screenshots
are evidence for specific states, not alternative rule sets. A verification
checklist is not a claim that every state has already been tested.

## Sources and component selection

| Source | Responsibility |
| --- | --- |
| [tokens.css](../src/styles/tokens.css) | Theme resolution, semantic colors, typography, spacing, dimensions and shared shell/toolbar rules |
| [surfaces.css](../src/styles/surfaces.css) | Standard, compact and flush card surfaces |
| [status.css](../src/styles/status.css) | Metadata and compact financial badges |
| [controls.css](../src/styles/controls.css) | Opt-in native form fields, neutral secondary actions, custom focus and interactive-card states |
| [dialogs.css](../src/styles/dialogs.css) | Dialog headers, close actions, contained scrolling and mobile dialog/menu values |

Both [main.js](../src/main.js) and [settings.js](../src/settings.js) load these
styles in the order shown above. Keep shared styles available to both entry
points. Dialogs/popovers may be teleported outside the app content container;
app-descendant selectors alone are insufficient for them.

| Need | Reuse / reference |
| --- | --- |
| Application shell and payment sidebar | Native `NcContent`, `NcAppNavigation`, `NcAppContent`, `NcAppSidebar`; see [App.vue](../src/App.vue) and [AddEntryModal.vue](../src/components/AddEntryModal.vue) |
| Page heading, back action and creation action | [AppPageHeader](../src/components/AppPageHeader.vue) |
| Standard action | `NcButton` or [CbButton](../src/components/CbButton.vue), according to the existing surface |
| Icon-only action | [CbIconButton](../src/components/CbIconButton.vue) with a localized `aria-label` |
| Save with additional choices | [CbSplitButton](../src/components/CbSplitButton.vue) |
| Dialog footer / confirmation | [ModalActions](../src/components/ModalActions.vue), [ConfirmModal](../src/components/ConfirmModal.vue) |
| Expense/income settings selector | [CbTypeTabs](../src/components/CbTypeTabs.vue), `v-model` and localized `label` |
| Settings rows and their actions | [SettingsList](../src/components/SettingsList.vue), [SettingsItemActions](../src/components/SettingsItemActions.vue) |
| Category symbol / symbol selection | [CategoryIcon](../src/components/CategoryIcon.vue), [IconPicker](../src/components/IconPicker.vue) |
| Member selection | [MemberSearch](../src/components/MemberSearch.vue) |
| Payment list and financial labels | [EntryTable](../src/components/EntryTable.vue), [EntryDescriptionCell](../src/components/EntryDescriptionCell.vue), [EntryAmountCell](../src/components/EntryAmountCell.vue) |
| Report section navigation and menus | [ReportDevelopment](../src/components/ReportDevelopment.vue), [ReportPaymentSection](../src/components/ReportPaymentSection.vue), [ReportSectionMenu](../src/components/ReportSectionMenu.vue) |
| Tooltip and horizontal scrolling | [TableTooltip](../src/components/TableTooltip.vue), [DraggableScroller](../src/components/DraggableScroller.vue) |

Import Nextcloud components from direct paths, such as
`@nextcloud/vue/components/NcButton`. Check the installed component API before
changing props. In the current integration, `NcButton` appearance uses
`variant="primary"`, `variant="secondary"`, `variant="tertiary"` or
`variant="error"`; `type="submit"` is the HTML button type. Do not reintroduce
appearance through `type="primary"` or the obsolete `native-type` prop.

## Fields, buttons and interaction states

### Form fields

Use `class="form-control" data-cobudget-field` on custom native input, select
and textarea elements. Use `data-cobudget-field="compact"` for dense settings
forms. Native Nextcloud field components retain their own implementation.

| Role | Token / current default |
| --- | --- |
| Standard field height | `--cobudget-control-height`: 44 px |
| Compact field height | `--cobudget-control-height-compact`: Nextcloud clickable area, fallback 34 px |
| Field border / radius | `--cobudget-control-border`: 2 px / `--cobudget-control-radius`: normally 8 px |
| Field text / label | `--cobudget-font-body`: normally 15 px / `--cobudget-font-label`: 12 px |
| Mobile field minimum | `--cobudget-mobile-touch-size`: 44 px at widths up to 768 px |

Labels use normal letter spacing. Always associate a visible label with its
field, or supply a localized accessible name where the layout deliberately
omits the label. Textareas grow vertically and have an 84 px minimum height.

```vue
<label for="item-name">{{ $texts.common.name() }}</label>
<input id="item-name" v-model="name" class="form-control"
    data-cobudget-field="compact" type="text" required>
```

`data-cobudget-field` supplies field styling, not the label styling or validation
logic. Use `--cobudget-font-label` and `--cobudget-text-muted` for the label;
connect error text using `aria-describedby` and set `aria-invalid="true"` for
invalid fields. Focus changes the existing 2 px border to
`--cobudget-control-focus-border` (the theme's main text color). Do not add an
outer outline or shadow frame: it doubles the border and can be clipped by
scroll containers. Invalid fields keep their error border during focus/hover. Do not override the shared border with a permanent local
`!important` declaration that hides focus or validation.

### Actions and states

- Primary: main completion action, primary surface plus its paired text color,
  no extra decorative shadow. Secondary: neutral muted surface, stronger neutral
  hover. Ghost/tertiary: transparent until interaction. Destructive actions use
  the error palette and an explicit action label.
- `CbButton` variants are `primary`, `secondary`, `ghost`, `danger`,
  `danger-outline` and `soft`; `soft` is a specialized tinted variant, not the
  default secondary action. Sizes are `normal`, `compact`, `large`.
- Normal/compact buttons use `--cobudget-button-height` (native clickable area,
  fallback 34 px); large buttons use 54 px. CoBudget buttons and icon buttons
  use the shared 44 px mobile minimum. Do not assume every native button is
  resized automatically: inspect its rendered target on mobile.
- Hover is temporary. Selection persists independently, with text weight,
  border, background or the appropriate pressed/selected semantics. Disabled
  and busy controls cannot activate and do not receive enabled hover styling.
- Custom button, link and menu keyboard focus uses `--cobudget-focus-outline` (2 px in the theme's
  main text color) and `--cobudget-focus-offset` (2 px). Add
  `cobudget-focusable` to custom controls. Native Nextcloud controls retain
  their own accessible focus unless a scoped, documented variant applies.
- Use an inset outline (`outline-offset: -2px`) where an overflow container
  would clip an outer outline, such as table rows, scroll regions or disclosure
  headers. Do not replace visible focus with hover alone.
- Prefer real buttons and links. A non-native interactive card must expose its
  role, keyboard focus and equivalent keyboard activation. Decorative icons
  use `aria-hidden="true"`; tooltips do not replace accessible names.

## Surfaces, spacing, tables and dialogs

| Role | Shared implementation / current default |
| --- | --- |
| Card | `data-cobudget-card`, 1 px border, 8 px radius, 20 px padding, no base shadow |
| Compact / flush card | `data-cobudget-card="compact"`: 16 px padding; `"flush"`: 0 |
| Interactive card | Add `cobudget-interactive-card` and `cobudget-focusable`: muted hover, primary border, shared small hover shadow |
| Content / card gap | `--cobudget-content-gap`: 12 px / `--cobudget-card-gap`: 16 px |
| Section / settings section gap | `--cobudget-section-gap`: 24 px / `--cobudget-settings-section-gap`: 32 px |
| Narrow form layout | `--cobudget-form-inset`: 28 px / `--cobudget-form-max-width`: 900 px; keep owning view's mobile rules |
| Table | `--cobudget-table-radius`, `--cobudget-table-header-background`, `--cobudget-table-row-hover`, `--cobudget-table-sort-hover` |
| Table padding | `--cobudget-table-cell-padding`: 4 × 10 px; `--cobudget-table-payment-padding`: 0 × 10 px; `--cobudget-table-detail-padding`: 12 × 16 px |
| Menu | `--cobudget-menu-padding`: 8 px; `--cobudget-menu-item-radius`: 8 px; item minimum native clickable area, 44 px mobile |
| Dialog | `--cobudget-dialog-radius`: currently 8 px; shared `--cobudget-dialog-shadow` and `--cobudget-dialog-backdrop` |
| Dialog inset / title / close | 24 px inset (20 px mobile), 20 px title, 44 px close target with 24 px icon |

Spacing is derived from Nextcloud's 4 px grid where practical. Use role tokens
for shared surfaces rather than a new local 14/18/22 px gap. Native popovers,
menus and dialogs can have different container radii; do not globally replace
their styling. The legacy `radius-lg` name is not a promise of a 16 px radius.

```vue
<article data-cobudget-card="compact">
    <h3 class="example-card-title">{{ title }}</h3>
    <span data-cobudget-badge="info">{{ statusLabel }}</span>
</article>
```

The card attribute styles the surface only; set the heading margin and
`font-size: var(--cobudget-font-card-title)` in its layout. Do not add hover or
pointer cursors to passive metric cards. Keep table widths, sorting, sticky
columns and scroll ownership in the table/view that implements them.

Use `SettingsList` for shared list behavior. Its 440 px container breakpoint
places actions below content even inside a narrow desktop column. Names and
badges may wrap without squeezing a short status into individual letters.

Custom dialog chrome uses `cobudget-dialog`, `cobudget-dialog-header`,
`cobudget-dialog-close cobudget-focusable` and `ModalActions`. These classes
are styling hooks, not a dialog implementation: preserve accessible naming,
focus trapping/return, Escape, busy guards and close behavior. Use the existing
components as references. The owning component still applies the dialog
surface/radius/shadow tokens and controls width and body scrolling.
`cobudget-dialog--contained` limits the whole dialog to `100dvh - 32px` with
vertical scrolling; do not add it to layouts that already own body scrolling.
The `cobudget-dialog-header--padded` variant supports a separate header/body.

Menus need outside-click closing, visible keyboard focus and appropriate
selection semantics. Reuse `CbSplitButton` for action menus with Arrow/Home/End
navigation and Escape focus return. Member-search results use native buttons,
Tab/Enter and Escape. Do not label every popup as a menu: checkbox filters and
hierarchical lookups have different interaction semantics.

Search/filter contents in `TableFilters` stay transparent. The native
`NcPopover` or mobile filter sheet owns the background, radius and shadow;
do not add a nested muted/gray card behind the fields. This keeps payment and
area filters on the same theme-aware Nextcloud surface. `TableFilters` owns
its padding and column gap, both `--cobudget-content-gap` (12 px). Popover
wrappers add no padding, and fields add no block margins, so the visible inset
matches the distance between fields.

### Payment labels, area choices and planning disclosure

Payment-label toggles share `--cobudget-badge-radius` and the 1 px border of
financial table labels; their larger text and padding remain appropriate for
editing. Unselected payment labels and area choices use the native
`NcRadioGroupButton` palette: `--color-primary-element-light`, its `-text`
foreground and `-hover` feedback. Checked labels use the solid primary fill
and its paired text/hover colors. These are independent checkboxes, not a
single-choice radio group; do not introduce the deprecated
`NcCheckboxRadioSwitch` button variant for them. Prefer `NcRadioGroup` and
`NcRadioGroupButton` for new ordinary single-choice groups. The payment area
grid remains a custom control for its per-area colors and compact icon layout.
The planning summary highlights its complete clickable header independently
of its open/closed state. Manually opening it moves focus to the section and
scrolls the payment sidebar body to reveal the content. Use `preventScroll`
for focus and scroll only the owning body, preserving the page/shell position.
Respect reduced motion and avoid focusing a text field on mobile. Automatic
expansion when loading an existing payment does not trigger this movement.

Selected area choices use opaque fills, so the sidebar background cannot tint
them. My finances and areas without a custom hex color use the active Nextcloud
accent (`--cobudget-primary`), paired with `--cobudget-primary-text` and the
primary hover color. Never pass CSS variables to a hex-only color resolver.
Explicit area colors use `getAreaSelectionPalette`: the stored color is the
solid fill, and black or white is chosen for readable text and icon contrast.
Hover moves the fill away from the text luminance, maintaining contrast.
Keep `getAreaColorPalette` unchanged for pale table badges. Unselected folder
icons retain the area color; selected icons inherit the contrast color.

Area names and payment-label toggles share 12 px, weight 600 and normal letter
spacing, with foreground colors taken from their current selection palette.
Lookup group headings share the form-label style (12 px, weight 400, muted
text, normal letter spacing and sentence case), including Frequently used and
All payment partners. Selection is always derived from `aria-checked` or the
checkbox state, not from focus or hover.

These hover effects are restricted to `(hover: hover) and (pointer: fine)` so
touch taps do not leave misleading hover fills. Keep keyboard focus visible
independently. See [AddEntryModal](../src/components/AddEntryModal.vue).

## Typography, icons and semantic color

Prefer these role names in new styles; older `font-xs`, `font-md`, `font-lg`
and similar tokens remain compatibility aliases and can denote different
roles. In particular, `font-xs` currently means 10 px, not ordinary helper text.

| Role | Token (prefix `--cobudget-`) | Current size |
| --- | --- | --- |
| Page title / mobile page title | `font-page-title` / `font-page-title-mobile` | 26 / 20 px |
| Section / subheading / card title | `font-section-title` / `font-subheading` / `font-card-title` | 22 / 18 / 18 px |
| Dialog title | `dialog-title-size` | 20 px |
| Body / caption / form label | `font-body` / `font-caption` / `font-label` | 15 / 12 / 12 px |
| Metric / compact metric | `font-metric` / `font-metric-compact` | 22 / 18 px |
| Metadata / compact financial badge | `font-badge` / `font-badge-compact` | 12 / 10 px |
| Inline / action / close icon | `icon-inline` / `icon-action` / `icon-close` | 16 / 20 / 24 px |

Use the existing Material Design icon family and native loading indicators.
Keep icon shape and size consistent for the same action. Show/hide uses Eye /
EyeOff, not Plus for restoring visibility. Symbol previews, avatars and the
mobile creation icon have their own sizes; a 16 px glyph does not justify a
16 px standalone button target.

Use `--cobudget-text`, `text-muted`, `surface`, `surface-muted`, `surface-strong`
and `border`/`border-strong` for ordinary UI. Pair primary backgrounds with
`--cobudget-primary-text`; pair information accents with `--cobudget-info-text`
and `--cobudget-info-background`. Do not use a primary fill color as arbitrary
text on a dark surface. Theme resolution belongs in `tokens.css`, including
explicit light/dark and system modes, not copied module palettes.

Badges use `data-cobudget-badge="info|neutral|success|warning|error|tax|custom"`.
Their shared geometry is 4 px radius, 2 × 6 px padding, 1 px border and weight
600. `custom` supplies geometry only; the area-color logic supplies its colors.
Use `data-cobudget-badge-size="compact"` only for the established financial
labels, not ordinary form help or metadata. Text/icon meaning must remain
available independently of color.

Existing financial mapping: Important → warning; Review → error; Tax → tax;
Fixed costs, Subscription, Children and Insurance → info; area labels → custom.
Global → info, hidden/former participant → neutral, active workspace → success.
Income/positive amounts use success; expenses/negative amounts use error.
Keep financial meaning distinct from chart-series identity.

## Deliberate CoBudget variants

These are scoped product decisions, not permission to create unrelated visual
variants in every module.

| Variant and reason | Scope / reference | Preserve when extending |
| --- | --- | --- |
| Neutral secondary actions reduce competing primary accents | [CbButton](../src/components/CbButton.vue), [controls.css](../src/styles/controls.css) | Shared neutral hover/disabled rules; use primary for the main action |
| Headerless desktop payment sidebar keeps entry fields aligned with the working area | [AddEntryModal](../src/components/AddEntryModal.vue), [AppPageHeader](../src/components/AppPageHeader.vue) | Native sidebar lifecycle and mobile header; existing fixed action footer and its contextual cancel styling |
| Compound amount/type input reads as one field | [AddEntryModal](../src/components/AddEntryModal.vue) | Mouse/trackpad hover and focus within the field color the complete outer border with the expense/income accent (red for expenses, green for income). Keep one border with no additional ring on the type segment or inner amount input. Restrict hover to fine pointers and preserve the semantic fill/text colors in light and dark themes. |
| Report text tabs keep information-dense section headings light | [ReportDevelopment](../src/components/ReportDevelopment.vue), [ReportPaymentSection](../src/components/ReportPaymentSection.vue) | Selected weight, native tab semantics/keyboard handling, mobile 44 px targets; do not replace them with settings segments |
| Hierarchical and compound lookups preserve category structure and inline creation | [AddEntryModal](../src/components/AddEntryModal.vue), [AnnualOverviewFilters](../src/components/AnnualOverviewFilters.vue) | Indentation, selected state and lookup behavior; reuse menu spacing/focus without flattening the hierarchy |
| Compact financial tables and 10 px labels support scanning many payments | [EntryTable](../src/components/EntryTable.vue), [EntryDescriptionCell](../src/components/EntryDescriptionCell.vue) | Amount alignment, specialized column widths, compact label order, ownership/status indicators; no blanket body-size replacement |
| Reports and mobile summaries use different metric density | [ReportSummary](../src/components/ReportSummary.vue), [MobileFinanceSummary](../src/components/MobileFinanceSummary.vue) | Report amounts 22 px desktop / 18 px mobile; compact finance metrics 18 px; horizontal cards keep their smaller inset |
| Full-width mobile reports and bounded desktop tables preserve usable data space | [AnnualOverviewTable](../src/components/AnnualOverviewTable.vue), [EntryTable](../src/components/EntryTable.vue), [App.vue](../src/App.vue) | Mobile square report corners, pinned columns, local horizontal scrolling; desktop table scroll must not capture mobile page scrolling |
| Narrow forms differ from full-width reports and card grids | [BudgetGoalFormView](../src/views/BudgetGoalFormView.vue), [ProjectSettingsView](../src/views/ProjectSettingsView.vue) | Form max-width/inset, responsive overrides and shared content gaps |
| Some dialogs use full-screen mobile bodies | [EntryHistoryModal](../src/components/EntryHistoryModal.vue), [ProjectList](../src/views/ProjectList.vue) | Their width/scroll behavior; shared title, close target and theme tokens still apply |
| Round category symbols, user avatars, progress bars and mobile creation actions convey specific roles | [CategoryIcon](../src/components/CategoryIcon.vue), [IconPicker](../src/components/IconPicker.vue), [BudgetGoalsView](../src/views/BudgetGoalsView.vue), [AppPageHeader](../src/components/AppPageHeader.vue) | Circular/pill shapes and specialized preview/FAB sizes; ordinary controls remain on control-radius tokens |
| Area colors and chart/budget-progress palettes encode identity or financial thresholds | [EntryDescriptionCell](../src/components/EntryDescriptionCell.vue), [AnnualOverviewChart](../src/components/AnnualOverviewChart.vue), [BudgetGoalsView](../src/views/BudgetGoalsView.vue) | Do not recolor chart series or user-selected area colors as generic UI status; verify contrast and labels |
| Touch focus return has a narrowly scoped exception | [AppPageHeader](../src/components/AppPageHeader.vue) | Existing coarse-pointer/mobile creation-trigger rule avoids a stale returned ring; do not generalize it to keyboard controls |
| Print reports have their own paper layout | [reportExport.js](../src/services/reportExport.js) | Readable light paper colors, repeated table headers and pagination; do not import the dark app shell wholesale |

## Verification and maintenance

For a new module or a shared design change:

- Identify the shared components, tokens and any exception used before adding
  local styles. Keep new strings translatable and use logical alignment where
  applicable. Do not mix feature logic into the shared styling files.
- Check normal, hover, focus, selected, invalid, disabled and busy states that
  apply. Test keyboard activation, Escape and focus return in overlays.
- Check explicit light/dark and system themes, including teleported menus and
  the admin entry point. Inspect foreground/background pairs together.
- Check a narrow viewport (for example 390 px), the 768/769 px boundary and
  desktop (for example 1200 px). Also check narrow columns inside wide pages.
  Inspect real hit areas, long translated labels, overflow and focus clipping.
- Check empty, loading, error and populated states where available. Do not
  represent an empty fixture or source-only review as a populated browser test.
- Preserve sticky headers/columns, payment sidebar transitions, mobile bottom
  navigation, creation-button clearance and print layout when affected.
  Respect reduced-motion preferences for new transitions.
- Run `npm run test:frontend-smoke` for Vue/deep-selector changes and
  `git diff --check`. From the workspace root run
  `./scripts/update-local.sh cobudget` to build and update the linked local
  Nextcloud 34/35 instances. Use `npm run build` for a standalone checkout.
  Follow [AGENT.md](../AGENT.md) for additional checks when other code changes.
- A successful build does not prove visual correctness. Inspect the affected
  rendered surfaces, report fixture limitations, and use a real touch device
  when touch-specific behavior changes. Verify compatibility with supported
  Nextcloud versions; local Nextcloud 35 source alone does not establish 33/34
  behavior.
- Update this guide when introducing/changing a shared role or deliberate
  exception. Update public feature documentation and the changelog when the
  visible behavior changes. For documentation-only work, check links and
  formatting; a new frontend build or app version is unnecessary.

When recording an exception, include: **role**, **user benefit**, **scope and
source**, **shared rules still used**, **responsive/theme/keyboard behavior**,
and **verification performed or remaining**. Do not copy a historical workaround
into a new module without confirming that it still applies.

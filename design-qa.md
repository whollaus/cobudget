# Design QA

- Source visual truth: `/var/folders/q0/fv5ktypn05g_4kx5xrbnrksc0000gn/T/ai-chat-attachment-11834058153482612437.png`
- Design direction: remove all visible desktop sidebar header controls, align the date field with the top of the middle work surface, add a secondary `Abbrechen` action to the fixed footer, and toggle the sidebar when the active row or new-payment action is selected again
- Target viewport: wide desktop; supplied source crop `562 × 808` pixels at native density
- Implementation screenshot: unavailable because no in-app browser is connected in this session
- Implementation pixel dimensions, CSS viewport and density: unavailable
- State: light theme, payments view, plain new-payment sidebar open
- Primary interactions tested in a browser: unavailable
- Browser console errors checked: unavailable

## Full-view comparison evidence

The source crop was opened at original resolution. It shows the isolated dock icon consuming a full top row and pushing the date field below the middle panel's primary action. A browser-rendered implementation could not be captured, so the revised alignment and footer composition cannot be compared in one visual input. Source checks, interaction-policy tests and the production build are not substitutes for rendered evidence.

## Focused-region comparison evidence

The source's sidebar top edge, first field and fixed footer were inspected closely. Focused implementation captures for the headerless desktop top edge, the two footer actions and the create/edit toggle states are unavailable for the same browser-connection reason.

## Findings

- **[P2] Desktop alignment and footer density remain visually unverified**
  - Location: desktop payment sidebar top edge and fixed footer.
  - Evidence: the implementation removes the custom dock control, takes the native header out of desktop flow, uses the shared 16px content inset above the date field, and adds the existing secondary cancel action beside save. No rendered screenshot is available.
  - Impact: live Nextcloud styles could still produce a small vertical mismatch or make the two footer buttons wrap at the narrowest desktop sidebar width.
  - Fix: capture the refreshed desktop new and edit states, compare them beside the source crop, and tune only the top inset or footer button spacing if visible drift remains.

- **[P2] Runtime toggle and focus behavior remain browser-unverified**
  - Location: new-payment button, selected table row and sidebar close transition.
  - Evidence: automated policy checks cover repeat-new, repeat-row, row-to-row, new-to-edit, edit-to-new, closed and copy states; no live browser was available to inspect focus return, row highlight removal or transition flicker.
  - Impact: Nextcloud focus management or asynchronous row resolution could still create a noticeable interaction edge case.
  - Fix: exercise the full toggle matrix with pointer and keyboard in the rendered app and inspect the console.

## Required fidelity surfaces

- Fonts and typography: no desktop sidebar heading remains; form and button typography are unchanged, but rendered wrapping is unverified.
- Spacing and layout rhythm: the desktop header and top icon no longer reserve space; the date starts at the shared content inset; live alignment is unverified.
- Colors and visual tokens: `Abbrechen` uses the existing theme-aware secondary button and `Speichern` remains primary; resolved theme colors are unverified.
- Image quality and asset fidelity: no raster assets or new icons are required; the obsolete dock icon dependency was removed from this component.
- Copy and content: `Abbrechen` uses the existing translated common label; payment-form copy is otherwise unchanged.

## Comparison history

- Pass 1: the wider payment-layout revision was built; visual comparison was blocked because no in-app browser was available.
- Pass 2: the desktop title and X were replaced with an icon-only sidebar control; visual comparison remained blocked.
- Pass 3: the desktop header control was removed, the first field was moved up, cancel was added to the fixed footer, and the complete toggle-state policy was implemented and tested. Post-fix browser evidence is still unavailable.

## Implementation checklist

- Capture the refreshed wide desktop create state.
- Capture the matching edit state with a selected row.
- Verify repeated new and repeated selected-row clicks close the sidebar and clear selection.
- Verify different targets switch in place without closing the sidebar.
- Verify mobile retains the native header and close control.
- Check keyboard focus return and browser console output.

final result: blocked

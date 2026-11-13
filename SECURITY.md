# Security Policy

CoBudget handles private financial data and remains in beta. App Store publication
and automated tests do not imply an independent security audit. Keep Nextcloud and
CoBudget updated, maintain backups and avoid relying on CoBudget as the only record
of critical financial data.

## Supported versions

During beta, fixes target the latest published release and current `main` branch.
Older versions do not receive separate maintenance. `main` is development code,
not a stable release. Report the affected version even if you cannot safely update
or reproduce the issue on a newer version.

The 0.x beta supports Nextcloud 33–35. Starting with the first stable 1.0 release,
Nextcloud 34 is the minimum; Nextcloud 33 will not receive CoBudget 1.x support.

## Reporting a vulnerability

Use [GitHub Issues](https://github.com/whollaus/cobudget/issues) to contact the
maintainer. For a sensitive vulnerability, open only a minimal request to arrange
private contact; **do not publish exploit details, credentials, financial records
or receipt files in a public issue**. A dedicated private reporting channel is not
currently documented by this project.

Once an appropriate channel is agreed, useful information includes:

- CoBudget and Nextcloud versions, or the affected commit.
- A description of the impact and steps using invented data.
- Whether authentication or administrator access is needed.
- Whether the issue affects shared areas, workspaces, receipts, exports or restore.
- Relevant error messages with personal data and secrets removed.

You do not need a fix or prior permission to report a security concern. This is a
single-maintainer project; no fixed response or remediation time is promised.
Please allow investigation and a fix before publishing sensitive technical details.

## Scope and technical references

Relevant concerns include cross-user/workspace access, shared-payment permissions,
receipt access, backup/restore, administrator and OCC actions, spreadsheet exports
and data integrity. Issues in Nextcloud itself or another app should also be reported
to the responsible project.

The maintained implementation rules are in [Architecture](docs/architecture.md),
including shared-payment projections, settlement, transaction boundaries, account
deletion and optional Talk delivery. [Operating CoBudget](docs/operations.md)
documents restore limits and operational requirements. Neither document is a
certification that all possible vulnerabilities have been excluded.

Optional calendar reminders copy the reminder title, explicit reminder description,
optional deadline and timestamp to the selected
writable calendar, with a link to CoBudget and an alarm at the start. Calendar
access rules apply; shared-calendar readers may see that reminder. Payment amounts,
payment descriptions and attachments are not copied automatically. Personal
reminder notes may also appear in the user’s notification; independent reminder
notes are included in opted-in private Talk delivery. Completion and notes are
scoped to the authenticated account, including shared-payment reminders. Calendar targets and synchronization
mappings are server-local and excluded from financial backups. Failed cleanup is
retried while access remains available; revoked calendar access is never bypassed.

Opt-in email notifications use the current primary email address of the enabled
Nextcloud account and the server-configured mail transport. CoBudget accepts no
custom recipient. Personal reminder titles/notes and brief event summaries may
leave Nextcloud through this channel; payment descriptions and receipts are not
included. Delivered copies remain in the recipient mailbox after CoBudget data is
changed or deleted. Transport errors are logged without message content or addresses.

Backup and restore use exclusive native Nextcloud app-data locks; regular app
requests, jobs, data-management commands and user deletion participate in shared
admission. New requests receive HTTP 503 during maintenance. File locking must be
enabled, coherently configured across nodes and have a TTL longer than the operation.
External SQL changes are outside this guard. Receipt files remain protected by the
installation's Nextcloud Files backup solution, not by CoBudget archive contents.

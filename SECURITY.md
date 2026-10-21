# Security Policy

CoBudget is published in the Nextcloud App Store and remains a beta project.

Security issues should be reported through GitHub Issues:

https://github.com/whollaus/cobudget/issues

## Supported Versions

During the beta phase, only the latest published release and the current `main` branch are considered supported. Older releases should be updated before reporting a vulnerability.

## Reporting A Vulnerability

Please include:

- A short description of the issue.
- Affected CoBudget version or commit.
- Affected Nextcloud version.
- Steps to reproduce.
- Whether the issue requires an authenticated user.
- Whether shared areas, backups, restore, file attachments or admin settings are involved.

Please do not include real financial data, private receipt files or production credentials in reports.

## Scope

Security-sensitive areas include:

- Workspace and user isolation.
- Shared-area membership and creator permissions.
- Backup and restore.
- OCC commands.
- Receipt file access.
- CSV export.
- Admin settings.
- Data integrity repair actions.

## Restore Resource Limits

CoBudget limits compressed backup size, total uncompressed size, individual JSON size and ZIP compression ratio before restoring an archive. Administrators can override the conservative defaults in Nextcloud's `config.php` when larger legitimate backups are required:

```php
'cobudget.restore_max_archive_bytes' => 33554432,
'cobudget.restore_max_uncompressed_bytes' => 67108864,
'cobudget.restore_max_json_bytes' => 33554432,
'cobudget.restore_max_compression_ratio' => 200,
```

Only positive integer values are accepted; invalid values fall back to the defaults shown above.

## Full Backup Storage

System-wide full backups contain CoBudget data and user settings for every referenced account. CoBudget therefore only permits a Nextcloud administrator as the Files owner for full-backup creation, listing, download, deletion and restore. Personal exports remain stored in the respective user's Files and contain only that user's normalized personal perspective.

## User Enumeration

Shared-area user search and direct member additions both honor Nextcloud's `shareapi_allow_share_dialog_user_enumeration` system setting. When enumeration is disabled, CoBudget does not accept guessed user IDs through the member API and does not disclose whether a submitted account exists.

## Atomic Mutations

Multi-row payment and budget mutations use database transactions. Notifications are sent only after a successful commit. Nextcloud Files operations cannot participate in database transactions, so CoBudget stores or removes receipt metadata atomically, cleans up failed uploads, and performs physical file deletion only after the related database commit.

## Request Limits

CoBudget bounds payment list page sizes and offsets on the server. Expensive authenticated endpoints such as payment search, CSV export, analytics, user search, and personal-export inspection use Nextcloud's native per-user rate limiting and return HTTP 429 when the configured request budget is exceeded.

Filtered payment totals and date-group summaries are streamed from the database and are not returned as an unbounded hidden result set alongside paginated rows.

## Membership Integrity

Each Nextcloud user can occur only once in an area. The `0.2.0` install schema contains a database-level unique index on `(project_id, user_id)` that protects concurrent member-add requests.

## Optional Talk Delivery

Personal Talk delivery is opt-in per authenticated account. Setup endpoints never accept another user ID or an arbitrary destination token. The native Talk adapter creates a private, unlisted, read-only conversation with one regular user participant and the CoBudget bot. Before every send it checks the room ownership marker, visibility, federation/SIP settings, all attendees (including dormant guests) and bot bindings. Talk's account/group restrictions are respected. Missing or changed destinations are not recreated during payment creation.

Only active shared-area members other than the creator receive messages. Talk and bell notification preferences are independent. Descriptions and receipts are excluded; user-controlled names are escaped to avoid chat mentions/Markdown injection. No external webhook or user app password is used. The app-owned bot has no receive/webhook feature. Talk messages are separate retained copies; turning off notifications, resetting CoBudget or deleting a payment does not remove existing Talk history. Transport failures do not roll back financial writes and logs omit payment contents.

## Current Status

The App Store publication does not imply a completed independent security audit. CoBudget is not yet recommended as the only system of record for critical financial data; use regular backups and keep Nextcloud and CoBudget updated.

# CoBudget REST API v1 (Beta)

The API lets external scripts and integrations work with CoBudget through personal
API keys. The API has been in Beta since CoBudget 1.3.0. Parameters, response fields and
behavior may change while the API is in Beta. Check [the changelog](../CHANGELOG.md)
before updating an integration.

## Enable access and create a key

1. As a Nextcloud administrator, configure the 64 KiB request limit at the public
   web server or reverse proxy. Check **Administration settings → Overview** for
   the CoBudget result. **Administration settings → CoBudget** includes the web
   server guide for your hosting provider. [Web server configuration](api-request-limits.md)
2. In **Administration settings → CoBudget → API access (Beta)**, enable API
   access for all users. The instance-wide switch starts off. Changing it
   requires Nextcloud password confirmation.
3. In **CoBudget → Settings → Import and API → API access (Beta)**, enable personal API access
   with Nextcloud password confirmation. Choose **New API key**, name the
   integration and select its workspace, permissions and optional expiration.
4. Copy the key when it is shown and store it as a secret in your integration.
   CoBudget stores a hash and shows a masked preview in the key list.
5. Open the key’s **API endpoints** dialog to see examples for its current
   permissions. It includes cURL examples and downloads in OpenAPI JSON and
   `.http` formats. Supply the key separately in the client and replace
   placeholders such as payment IDs before sending requests.

Each key has one workspace for its lifetime. Use separate keys for integrations
that need different permissions. Pause a key to suspend it and delete it to
revoke it permanently. CoBudget uses Nextcloud password confirmation for key
management. Disabling either API switch blocks key use for new requests. Check
the key list and its active states when turning access back on. Keys are excluded
from CoBudget backups and exports. After restoring, enable access again as
needed and create new keys. [Backup and recovery](operations.md#backup-and-recovery)

## Connect over HTTPS

The API base URL is the Nextcloud base including any installation subpath,
followed by `/index.php/apps/cobudget/api/v1`. Where the server supports clean
URLs, `/apps/cobudget/api/v1` also works. Use the exact URL shown in the endpoints
dialog.

Send the key in `Authorization: Bearer <key>` or `X-CoBudget-Token: <key>`. Use
one header per request. Keep keys out of URLs, logs and example files. Use a
HTTP client without an authenticated Nextcloud browser session. Nextcloud login
cookies, Basic authentication and Nextcloud app passwords do not authenticate
these endpoints. The key determines the workspace.

A first request can retrieve the account, workspace and available areas. Set
`COBUDGET_API_KEY` from your secret store and replace the example host.

```sh
curl --fail-with-body \
  -H "Authorization: Bearer ${COBUDGET_API_KEY}" \
  -H 'Accept: application/json' \
  'https://cloud.example.com/index.php/apps/cobudget/api/v1/me'
```

Send a JSON object with `Content-Type: application/json` for POST and PATCH.
Send GET and DELETE without a body. The JSON body limit is **65,536 bytes**.

## Permissions and endpoints

Payment permissions can be granted separately for personal payments and each
active area. The four actions are Read, Create, Edit and Delete. A key that may
create or edit a payment but cannot read that target receives only
`{"entry":{"id":123}}` for a successful write. Starting or extending a recurrence
also requires Create. An existing payment stays in its current area.

| Method and path | Purpose and access |
| --- | --- |
| `GET /me` | Account and bound workspace, plus available areas and key grants. Currency and feature settings require Settings Read. |
| `GET /areas` | Active areas accessible to the key. |
| `GET /categories`, `GET /payment-partners` | Lookup values for personal payments or an allowed area. Pass its numeric ID as `project_id`. Omit it or use `0` for personal lookups. |
| `GET /entries` | List payments visible to the key. Supports area, payment type, date, category, partner, text, sorting and pagination filters. |
| `GET /entries/{id}` | Read one payment. Requires Read for its target. |
| `POST /entries` | Create a payment. Requires Create for its target. Returns HTTP 201 on success. |
| `PATCH /entries/{id}` | Update fields on a payment. Requires Edit for its target. |
| `DELETE /entries/{id}` | Delete a payment. Requires Delete for its target. |
| `GET /reports/summary` | Payment totals for a preset period or a custom date range. |
| `GET /reports/annual` | Annual totals. Select a year with `year` (1970–2099). |
| `GET /budgets` | Budget goals and their current progress. |
| `GET /settings` | Read the account settings exposed by the API. Requires Settings Read. |
| `PATCH /settings` | Update selected account settings, such as currency, appearance and page size. Requires Settings Edit. Returns the updated field names. |

Reports and budget goals require the Reports Read scope **and** read access to
personal payments and all active areas in the bound workspace. Settings access
is account-wide and limited to an allowlist. Instance administration, key
management, backups and receipt-file uploads are handled through Nextcloud and
CoBudget’s signed-in interface.

### Payments and reports

- To list payments, use `project_id=0` for personal payments or an area ID to
  filter by area. The default `scope=canonical` combines personal payments and
  authorized shared source payments. `scope=personal` selects the personal
  perspective. Filter with `type` (`all`, `expense`, `income`), `date_from`
  and `date_to` (Unix seconds), `category_id`, `payment_partner_id` or
  `search`. Use `limit` (1–250, default 50) and `offset` (0–1,000,000,
  default 0) and read `total` from the result. Sort with `sort_by` (`date`,
  `amount`, `id`) and `sort_dir` (`asc`, `desc`). The default is newest first.
- For creates, supply an amount and the target area ID for shared payments. Use
  `amount_cents` as a positive integer with `type` set to `expense` or `income`
  when you want exact amounts. A date can be a calendar date in the account’s
  timezone or a Unix timestamp in seconds. Send only the fields to update in
  PATCH. The API supports categories and payment partners by ID or name,
  descriptions, shared-area payer and split options, labels, recurrence and
  reminders. Use the endpoint examples for your key or consult the
  [payment field definitions](../lib/Service/ApiV1EntriesService.php).
- Settings writes accept only the documented values for
  `currency`, `default_start_page`, `entries_per_page` and `theme_mode`,
  plus the [allowlisted Boolean preferences](../lib/Service/ApiV1SettingsService.php).
- Summary report periods are `current-month`, `last-month`, `current-year`
  and `last-year`. For a custom range, send both `date_from` and `date_to`
  as Unix timestamps in seconds. The maximum range is 3,660 days (366 days
  multiplied by 10). Lists and reports exclude future payments by default. Set
  `include_future=true` where supported to include them.

### HTTP responses

| Status | Meaning |
| --- | --- |
| 400 | An invalid parameter, malformed JSON or missing required data. |
| 401 | Missing or invalid key, expired or paused key, or API access disabled. |
| 403 | Insufficient permissions or an operation blocked by CoBudget settings. |
| 404 | Resource unavailable to the key or absent. |
| 409 | Conflict with current payment state or target. |
| 413 | Request content exceeds the size limit. The web server can return its own response. |
| 415 | Unsupported request content type. |
| 429 | Rate or authentication-attempt limit reached. Follow `Retry-After` when provided. |
| 503 | CoBudget is temporarily busy, for example during a backup or restore. Follow `Retry-After` when provided. |

CoBudget errors include an `error` field in the JSON response. The rate limits
are 180 requests per minute per client IP plus separate per-key limits of 120
for reads, 60 for writes and 20 for reports and budget goals. For hosting
requirements and security reporting, see the [security policy](../SECURITY.md).

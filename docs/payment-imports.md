# Importing payments

**English** · [Deutsch](payment-imports.de.md) · [Français](payment-imports.fr.md) · [Español](payment-imports.es.md)

Enable **Settings → Features → Payment imports** to use either import method. It is off by default. Turning it off pauses folder imports and keeps their settings and progress. Active folders resume when you turn it back on.

Use **Bulk import** for a one-off batch or set up a folder for regular imports. Both use the same JSON format. Your account is the payer for expenses and the recipient for income. In a shared area, CoBudget applies the area’s usual split.

## File format

Put one object per payment in a `payments` array. Only `date` and `amount` are required. A file can contain up to 5,000 payments and 5 MiB (5,242,880 bytes). Amounts use your account currency. An automatic import keeps the account currency from its first successful preview packet for the whole file, even if you change the setting before it finishes. Income and future dates must be enabled in your settings if you want to import them.

This example records a grocery expense and a salary payment. Replace the dates and names with your own. In the app, **Import format** lets you copy or download an example with today’s local date.

```json
{
  "payments": [
    {
      "date": "2026-11-19",
      "amount": -42.75,
      "category": "Groceries",
      "payment_partner": "Corner Market",
      "description": "Weekly shopping #household",
      "review": 1
    },
    {
      "date": "2026-11-19",
      "amount": 1850.00,
      "category": "Salary",
      "payment_partner": "Employer",
      "description": "November salary"
    }
  ]
}
```

| Field | Meaning |
| --- | --- |
| `date` | Payment date in `YYYY-MM-DD` format. |
| `amount` | Nonzero amount with up to two decimal places. Use a decimal point. Negative for expenses, positive for income. |
| `category` | Category name for the payment type, up to 128 characters. |
| `payment_partner` | Payee or payer name for the payment type, up to 128 characters. |
| `description` | Text up to 512 characters, including hashtags such as `#household`. |
| `important` | Important payment. |
| `review` | Needs review. |
| `fixed_cost` | Fixed cost (expenses only). |
| `children` | Child-related payment. |
| `subscription` | Subscription (expenses only). |
| `tax_relevant` | Tax-relevant payment. |
| `insurance` | Insurance payment. |

Each label from `important` to `insurance` accepts `1` (on) or `0` (off). Omitted labels default to off. CoBudget applies the labels enabled in your settings.

## Manual bulk import

1. Open **Bulk import**. Choose **Without area** for personal payments or select a shared area.
2. Select your JSON file or paste its contents, then select **Show preview**.
3. Review the amounts, dates and any new categories or payment partners. Confirm the import to book the payments.

For personal payments, or if you own the shared area, CoBudget can create missing categories and payment partners. Other area members must use existing names for that payment type or leave the fields out. An unknown name during validation rejects the whole file. **Import format** lists the names available for your destination.

After an interruption, preview the same data again for the same destination. The preview shows the payments still to be booked.

## Automatic folder import

1. Open **Settings → Automatic imports**. Choose a Nextcloud Files folder on the row for your destination. Settings prepares `CoBudget/Import` as a starting point. Use a separate folder for each destination. A paused import keeps its folder reserved.
2. Choose an interval (every 5 or 15 minutes, hourly or daily), set notifications (off, errors only or after every import), then enable **Active**. Once an import is active, you can select Nextcloud, email or personal Talk under **Settings → Notifications**, depending on server availability.
3. Place complete `.json` files directly in the chosen folder. Nextcloud background jobs check it at the configured interval. To check it immediately, select the play button next to **Active**. Large batches can span several runs.
4. Hover over, focus or tap the timestamp under **Last run** for details. A red icon marks an error or a waiting file. Completed files move to `Verarbeitet` and rejected files to `Fehler` inside the import folder. Select the filename to open the file in a new browser tab.

Folder imports require Nextcloud file locking. A file that cannot be read or moved stays in the inbox. If a source changes during booking and CoBudget cannot read that version under a lock, new deliveries in that folder wait until it can check for copies. If only the move is blocked, CoBudget can still identify the changed data, reject its copies and import other files.

Archiving an area hides its folder import and pauses processing. Restoring the area restores the previous settings. An enabled rule resumes at its next check.

Files uploaded or changed during a check are picked up by a subsequent check. You can keep uploading while an earlier batch is being processed. If sync changes the payment data as a booked file moves to `Verarbeitet`, CoBudget sends the changed file to `Fehler` and rejects further copies. After an interruption, copies of those changed payments are still rejected, even if you moved the archived file to another folder.

If the archive already contains a file with the same name, both files are kept. If you correct a rejected file before it moves to `Fehler`, CoBudget checks the corrected file on a later scan. After an interruption, a different file that now occupies the planned archive path keeps its name and remains eligible for import. This also applies when sync overwrites the archived file in place and Nextcloud keeps its file ID.

### Filenames, duplicates and corrections

Choose a fresh filename **before uploading each new batch**, for example `payments-2026-11-20.json`. If you change a payment date in an already imported file and upload it under the same name, CoBudget rejects the changed file. The count in the message refers to payments booked from the original. A copy renamed after this rejection remains blocked. To correct a booked payment, edit it in CoBudget. If you rename a file in the inbox while CoBudget is booking its payments, both the original and the new filename are reserved.

If validation rejects a draft before any payments are booked, you can fix it and reuse its filename. A later filename conflict still blocks copies of that particular version.

Uploading an identical batch again leaves the booked payments intact, even under a new filename in the same account, workspace and destination. Field order within a payment can change. Keep the payment rows in their original order for a retry. For overlapping exports, remove previously supplied payments before uploading a new batch.

If a category or payment partner disappears after booking has started, **Last run** shows the waiting file, the missing name and the booked count. Other files continue. Ask the area owner to restore the original name. CoBudget then resumes the saved file and books the remaining payments and shares.

If the input folder was deleted and recreated at the same path, select it again in **Settings → Automatic imports**. Once the import is active, CoBudget can finish the saved payments even if the replacement folder is empty.

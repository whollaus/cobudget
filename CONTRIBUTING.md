# Contributing To CoBudget

CoBudget is currently maintained as an early alpha Nextcloud app.

## Development Setup

Install dependencies:

```sh
npm ci
```

Build frontend assets:

```sh
npm run build
```

Run tests:

```sh
npm run test
```

## Project Structure

- `appinfo/` Nextcloud app metadata, routes and registration.
- `lib/` PHP backend, controllers, services, migrations, cron jobs and settings.
- `src/` Vue frontend source.
- `js/` built frontend assets.
- `templates/` Nextcloud templates.
- `l10n/` translations.
- `tests/` PHP, static-security and frontend smoke tests.

## Coding Notes

- Use public Nextcloud APIs.
- Keep user and workspace isolation explicit.
- Use `amount_cents` as the canonical money field.
- Keep shared-area permission checks creator-aware.
- Use transactions for critical multi-step mutations.
- Prefer Nextcloud CSS variables and CoBudget design tokens.
- Keep user-facing strings translatable.
- Do not expose raw internal exception messages to clients.

## Release Checklist

Before creating a release package:

1. Run the test suite.
2. Build frontend assets.
3. Review `appinfo/info.xml`.
4. Update `CHANGELOG.md`.
5. Create a release archive that contains the top-level `cobudget/` folder.
6. Exclude development-only files from the release archive.
7. Sign the app before App Store publication.

Official Nextcloud App Store publication will require signed release archives.

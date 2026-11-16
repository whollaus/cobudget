# Development

Please read [CONTRIBUTING.md](../CONTRIBUTING.md) before proposing code changes.
Use Node 24 and a separate Nextcloud 34 or 35 development instance with sample
data. Run these commands from the app repository:

```sh
npm ci
npm test
npm run build
```

Place or link the repository in Nextcloud's `custom_apps/cobudget` directory and
enable it with `occ app:enable cobudget`. Use a PHP CLI supported by your Nextcloud
version. Production bundles are committed in `js/`.

See [tests](../tests/README.md) for integration checks,
[architecture](architecture.md) for data and permission rules, and the
[design system](design-system.md) for UI conventions.

## Build and test

`npm run release` builds and checks an unsigned `cobudget.tar.gz` in the parent
directory. Documentation, screenshots, tests and development tools are excluded.
Public releases follow the [signed release process](../RELEASING.md).

For a fresh-install test, prefer a new disposable Nextcloud instance. The optional
`scripts/development/reset-installation.php` tool permanently removes CoBudget's
installation state and all users' app data. Run it without confirmation to preview
the affected data; it requires a disabled app and Nextcloud maintenance mode for
deletion. Use it only on disposable test installations.

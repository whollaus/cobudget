# Screenshots

These screenshots are documentation assets for GitHub and the published Nextcloud App Store listing.

They are intentionally kept in the repository but excluded from installable release archives. The installable Nextcloud app package should only contain runtime files such as `appinfo/`, `css/`, `img/`, `js/`, `lib/`, `l10n/`, `templates/`, and `composer.json`.

The matching `appstore-preview-*.jpg` files are optimized previews for
Nextcloud's integrated app list. Keep each preview in sync with its full-size
screenshot.

## Compatibility aliases

The Nextcloud App Store may continue to serve screenshot metadata from an
older published release. The following files therefore intentionally remain
available as compatibility aliases and must not be removed:

- `screenshot1.jpg` to `screenshot4.jpg`
- `screenshot1_small.jpg` to `screenshot4_small.jpg`

They currently mirror the latest versioned screenshots and previews. These
aliases keep older App Store metadata functional while new releases use unique,
versioned URLs for cache busting.

The Nextcloud App Store image proxy caches screenshots by source URL and may
continue serving old content after a file is replaced. Therefore every visual
screenshot update must use a new revision in both filenames and `info.xml`.
Changing the image content under an existing URL is not sufficient.

Naming convention for future updates:

- `screenshot-v0.2.10-1.jpg` / `appstore-preview-v0.2.10-1.jpg` - payment overview
- `screenshot-v0.2.10-2.jpg` / `appstore-preview-v0.2.10-2.jpg` - shared areas
- `screenshot-v0.2.10-3.jpg` / `appstore-preview-v0.2.10-3.jpg` - analytics
- `screenshot-v0.2.10-4.jpg` / `appstore-preview-v0.2.10-4.jpg` - settings

For the next screenshot refresh, replace `v0.2.10` with a new unique revision
in the filenames and update the URLs in `appinfo/info.xml` and `README.md`.

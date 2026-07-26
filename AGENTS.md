# CoBudget agent instructions

## Version bumps

When the user asks to increase the CoBudget version without naming a target,
increase the current semantic version by one patch release.

Update all of these manual version sources:

1. `appinfo/info.xml`: the `<version>` value.
2. `package.json`: the root `"version"` value.
3. `package-lock.json`: both the top-level `"version"` value and
   `packages[""].version`.
4. `CHANGELOG.md`: add the new version as the first release section using
   `## [VERSION] - YYYY-MM-DD` and summarize the release changes.

Do not edit an embedded version in generated JavaScript by hand. Run
`npm run build` after updating the manual sources; the build writes the package
version into `js/cobudget-main.js` and refreshes the tracked frontend bundles.

After every version bump:

1. Run `npm test`.
2. Run `npm run build`.
3. Run `git diff --check`.
4. Search the repository for both the new and previous version, excluding
   `.git`, `node_modules` and `vendor`.
5. Confirm that the active new version occurs in `CHANGELOG.md`,
   `appinfo/info.xml`, `package.json`, both root locations in
   `package-lock.json`, and `js/cobudget-main.js`.
6. Confirm that the previous version remains only where it is historical,
   normally in `CHANGELOG.md`. Older version strings in screenshot filenames
   and URLs are intentional unless the screenshots are being replaced.

Do not create a commit, tag, release archive, signature, push or GitHub release
unless the user explicitly requests that additional release action.

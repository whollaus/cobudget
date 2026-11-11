# Contributing To CoBudget

CoBudget is primarily developed and maintained by one person and is still in
beta. To keep development and long-term maintenance manageable, the maintainer
keeps responsibility for architecture, design, priorities and implementation.

Feedback, bug reports and testing are very welcome. I am especially grateful for
clear bug reports: finding and understanding problems is currently more helpful
to the project than additional code contributions. You do not need to write code
or know how to fix a problem to make a valuable contribution.

## Report A Bug

Please use [GitHub Issues](https://github.com/whollaus/cobudget/issues). Check
whether the problem has already been reported; additional reproduction details
or confirmation on another setup are useful in an existing issue.

Include as much of the following as you can:

- Your CoBudget and Nextcloud versions.
- Browser and operating system, and whether you are using desktop or mobile.
- The steps that lead to the problem.
- What you expected to happen and what actually happened.
- Whether it happens every time or only occasionally.
- Relevant screenshots, error messages or short log excerpts.

For calculations, shared payments or settlements, a small example with invented
amounts and the expected result is particularly helpful. For appearance problems,
mention whether CoBudget and Nextcloud use light, dark or automatic mode.

Incomplete reports are welcome too. Describe what you observed even if you
cannot yet reproduce it reliably. Please remove personal financial information,
user details, receipt contents and credentials from screenshots and logs.

For security issues, follow [SECURITY.md](SECURITY.md).

## Help With Testing

Trying a new version and checking whether a reported problem is fixed helps
prepare CoBudget for a stable 1.0 release. Test with sample data in a separate
instance where possible, and keep backups before updating an existing installation.

Share the version and setup you tested, the steps you followed, and the result
in the relevant issue. Confirmation that a fix works is useful, as are reports
of regressions, unclear wording, translation errors and mobile or keyboard
usability problems.

## Suggest An Improvement

Suggestions are welcome through GitHub Issues. Explain the problem you are
trying to solve and how it affects your use of CoBudget. A concrete example is
more useful than a proposed implementation alone.

The maintainer decides which changes fit the app's scope and maintenance
capacity. A suggestion does not guarantee implementation or a delivery date.

## Code Contributions And Pull Requests

**Please discuss code contributions before starting work or opening a pull
request.** Open an issue describing the proposed change and wait for explicit
agreement from the maintainer on its scope. An existing issue alone is not an
invitation to implement it.

This applies to fixes, features, refactoring, documentation and translation pull
requests. Reporting a bug, sharing feedback or testing a fix needs no prior
agreement. Pull requests opened without prior agreement may be closed without
review so that development remains manageable.

For an agreed contribution, keep the change focused on the agreed scope, follow
the project documentation and explain how it was tested. Agreement to work on a
change does not guarantee that the resulting pull request will be merged.

## Local Development And Reference Documents

For local testing or an agreed code contribution, run these commands from the
app repository root:

```sh
npm ci
npm test
npm run build
```

Running the app itself requires a Nextcloud development instance. See the
[README](README.md#development) for development context and [tests/README.md](tests/README.md)
for test requirements and integration checks.

- [Architecture](docs/architecture.md): stack, data model, permissions, recurring payments, backups and migrations.
- [Design system](docs/design-system.md): native components, visual conventions, accessibility and UI verification.
- [AGENTS.md](AGENTS.md): coding-assistant workflow, required checks and documentation rules.
- [Release process](RELEASING.md): packaging and signing; releases are handled by the maintainer.

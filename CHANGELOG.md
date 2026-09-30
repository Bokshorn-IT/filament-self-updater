# Changelog

All notable changes to `filament-self-updater` will be documented in this file.

## v1.0.1 - 2026-10-01

- The update page shows the last 2000 lines of a log instead of 200, and says how many earlier lines are left out and which file holds them

## v1.0.0 - 2026-09-30

- Update page showing the installed and the newest version, with install and check actions
- Versions from GitHub tags or releases, paged through in full, prereleases opt-in
- Updates run as a queued job, one at a time, with the step and the log shown live on the page
- Files dropped by a release are removed on the next update
- Post-update commands with their output in the update's log
- `UpdateInstalled` and `UpdateFailed` events
- Optional dashboard widget
- English and German translations

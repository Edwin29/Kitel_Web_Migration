# Phase 4 — Calendar publishing

The public `schedule` page uses the existing `calendar` module, its `default` skin, and the shared About layout. The repository is the source of truth; run `scripts/sync-dev.ps1` for a dry run and `scripts/sync-dev.ps1 -Apply` to copy it to the local Rhymix installation. The script does not sync the database.

## Implemented scope

- Monthly grid from `CalendarModel::getEventList()`, including today, empty month, and events spanning several days or months.
- Previous/next month links, the current-period popover, and Today use `cal_year` and `cal_month`. The old `y`/`m` query names did not reach the module reliably in local Rhymix. The popover changes only its candidate year in JavaScript; selecting a month submits the existing server route.
- Event bars link to the matching details in the monthly summary. The summary shows dates, location, category, and description from the existing event fields.
- The add, edit, and management links are shown only when the module's `manage` grant is present. Rhymix grants protect the admin routes. Update and delete queries are also scoped to the current calendar `module_srl`.
- The admin form keeps the existing fields and POST action. Its obsolete `procFilter(this)` submit handler was removed because it raised `filter_func is not a function` in the current runtime; native required fields and server validation remain.
- Event insert/update and delete now accept POST only. Delete uses a confirmation-backed form instead of a GET link. The missing `insertEvent` ruleset declaration was removed; the controller validates nonblank title, real start/end dates, and chronological order before any database write. A blank end date becomes the start date, while an earlier end date is rejected.

The Figma reference shows a weekly view, but the current public backend provides only the monthly grid. The monthly/weekly segmented control presents weekly as disabled with an accessible "준비 중" explanation. No weekly dates or sample data are presented as working functionality. A real weekly view requires a separate Calendar backend extension and product decision.

## Local verification

At `http://127.0.0.1:8888/schedule`, anonymous monthly view, month selection, previous/next navigation, year rollover, empty month, today's date, event detail, and 1440/390 width were checked. Anonymous access to the admin form returned 403. With the local admin account, add, modify, and delete were checked using a temporary event spanning September 29 to October 2, 2026. It appeared on both months' grids and summaries, then was removed. The existing August 25 event was retained. No QA fixture is stored in the repository.

The final Phase 4 correction was checked in the local development database with a disposable event and member account. Insert, modify, confirmation cancellation, and POST delete succeeded. GET delete returned 405; anonymous and nonmanager POST delete returned 403. Direct HTTP submissions with a blank title, impossible start/end dates, or end before start produced no calendar rows. The disposable event and member account were removed after verification.

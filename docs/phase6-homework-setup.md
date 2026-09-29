# Phase 6 — Homework publishing

`custom_modules/homework` remains the standard Rhymix module for Study > 과제게시판. The repository is the source of truth. Preview `scripts/sync-dev.ps1 -DryRun`, then run `scripts/sync-dev.ps1 -Apply` for the local Rhymix runtime; neither command syncs the database.

## Implemented

- The member list shows real tasks, deadlines, and the submitter's own submission state. Empty and past-deadline states use existing task data.
- The detail screen uses the shared 1160px container, the task description, an in-page submit/resubmit form, and a full submission list only when `view_all` is granted. The submission form retains the existing `content` and `Filedata` fields; no fake title field was added because the backend has no submission title. The deadline/countdown banner is currently hidden by `show_deadline_banner = false`; its markup, styles, and date calculation remain available.
- The technical-department dashboard adds a selected-task summary, counts submitted/missing junior members, and shows their submissions. The original all-task × all-junior matrix remains below it. Counts use the same junior-member rows and submission map as the matrix, not a separate sample dataset.
- The public task list shows a dashboard link only to members with the module's `create` grant (technical department and admin). A manager can also open the current task's summary from its detail page. The dashboard route retains its own server-side `create` grant check.
- Task management uses the existing create grant. Task create/update and submit accept POST only. The former task-delete control and route have been replaced by a POST-only display switch; existing tasks and submissions remain stored. Invalid deadline dates and cross-module task updates are rejected before saving.
- When a submission replaces an attachment, the previous physical file is removed after a successful DB update. Task deletion removes its submissions and their attached files. Downloads retain the controller's owner-or-`view_all` check.

The Figma detail and submit references are `176:2574` and `176:2575`; the technical summary is `41:631`. The Figma submission form shows a title-like field that has no backend equivalent. The current detail route contains the submission form instead of creating a separate route. Mock pagination/search from the static Figma screen was not presented as functioning controls.

## Local verification of the original Phase 6 implementation

At `http://127.0.0.1:8888/homework`, an admin created, edited, and deleted a temporary task. A temporary junior account viewed the task, submitted text and an attachment, downloaded its own file, and resubmitted with a replacement attachment. The junior view did not expose the admin's submission or the `view_all` list; a direct request for the admin attachment returned 403. The admin dashboard counted the junior as submitted and retained the all-task matrix. As a full member, the QA account could read the submission list and download its file, but had no submit form and received 403 for the technical dashboard. Assigned only to the technical-department group, it could open the dashboard. The task and QA member were deleted after testing, and the fixture's remaining files were checked and removed. An admin GET request to task delete returned 405. Browser console/page errors were absent. The 1440px and 390px page widths were checked; the table remains horizontally scrollable on narrow screens. The final responsive polish belongs to Phase 11.

The local development database still contains the pre-existing `품평회 과제 1차` task and its submission. This work did not change its data or grants. Anonymous `/homework` remains denied by the module's list grant.

## Shared list surface and task visibility

The short-page white surface now uses the shared `kitel-list-surface` marker in the layout. Current generic News/Seminar lists, Sharing, Exhibition, and both Homework lists opt in; board details and write screens do not. The common `kitel-write-action` button is used by generic board writing and **새 과제 추가**.

Tasks have an `is_visible` flag, default `Y` for existing and new rows. Technical department/admin can switch ON/OFF from the management list. OFF tasks remain in management and dashboard views, including their submissions, but are excluded from the member list and denied on direct member detail, submit, image, and submission-download routes. The former delete action is no longer registered in `conf/module.xml`; no task or submission is removed by toggling. The member list uses descending `task_srl` order, so newest assignments appear first.

`scripts/update-homework-dev.php` previews and applies the additive visibility column only to the guarded local development database. Existing rows were preserved as `Y`. Local QA temporarily switched the pre-existing task OFF and ON: the junior list hid and then restored it, direct junior detail was denied, admin detail and dashboard remained available, and no browser errors occurred. The temporary QA member was removed. News, Seminar, Sharing, Exhibition, and Homework list pages loaded without horizontal overflow at 1440px; their footer began at or below the 900px viewport edge.

## Revised task and submission contract (2026-09-30)

- The submission card spans the same content width as the task description. The task list has a separate submit/edit column. A submission can be revised after the deadline; `updateSubmission` sets `regdate` again, and the controller recalculates `is_late` from the current deadline and this save time.
- Junior members see only `미제출` or `제출완료`, including after a late revision. Only the technical department dashboard displays on-time/late status and the last modified date and time. The general detail's `view_all` table also uses neutral `제출완료` wording.
- Technical staff can edit the task title and description, attach one description image, configure a comma-separated list of allowed submission file extensions, and add up to 20 labeled answer boxes. Each box can be vertically resized in the authoring form, and its height and stable ID are stored with the task. The title is shown as task guidance; the box captures a junior member's answer. Existing tasks and submissions remain valid without extra boxes.
- The image route checks the homework `list` grant. Accepted image MIME types are JPEG, PNG, WebP, and GIF. Submission extension restrictions are checked on the server; the file input `accept` attribute is only a convenience. Leaving the extension setting blank preserves the former unrestricted behavior.
- New fields use `homework_task.answer_fields`, `allowed_extensions`, `description_image` and `homework_submission.answers`. New installs use the XML schemas; an existing Rhymix install can apply the module update. For the known local development database, `php scripts/update-homework-dev.php` previews the additive columns and `php scripts/update-homework-dev.php --apply` adds them after verifying the development root, DB, and homework module. Existing rows are preserved. Sync source files with `scripts/sync-dev.ps1` separately.

## Task list pagination follow-up

The member task list and the technical management list each use the same server-side `getTaskPage` query, ten tasks per page, ordered by task ID descending. The dashboard still loads all tasks for its full submission matrix. The management list keeps a white content region through the bottom of a short desktop viewport so the grey footer does not begin immediately after the last table row. This is scoped to the homework management list.

Local QA temporarily added tasks to reach 11 total: both lists showed 10 rows on page 1 and one on page 2, with working next-page links. An out-of-range page request resolved to the last valid page. The temporary tasks were deleted. With the original seven tasks, the footer started at viewport y=900 at 1920, 1440, and 1024 widths with a 900px-high browser window; no horizontal overflow was observed at 390px.

## Dashboard entry follow-up

The task list now displays **제출현황 대시보드** when the viewer has `create`, and task detail displays **이 과제 제출현황** for the same grant. Both links lead to the existing admin dashboard; the detail link selects its current task. Local browser checks confirmed the links and navigation for admin and a technical-department QA account, while a full member saw neither link. The QA account was removed after the check.

## Dashboard visual demo fixture (2026-09-30)

The submitted/missing summary cards are scaled to about 75% of their previous desktop width and height, with their label and number sizes reduced proportionally. On a narrow viewport the cards retain a fluid width and stack as before.

`scripts/phase6-dashboard-fixture-dev.php` creates local development data only. It checks the exact `D:/rhymix_dev/www/rhymix` root, `rhymix_dev` database on loopback port 3307, homework module 149, and 준회원 group 3. Dry-run is the default. The fixture adds one **hidden** `[P6DEMO] 과제 제출 시연` task, four marked junior accounts with random undisclosed passwords, and two example submissions to that task. The other two accounts remain unsubmitted. Existing tasks and submissions are not modified. Hidden status keeps the demo task off the member-facing assignment list while it remains available in the technical dashboard. The demo accounts are intended for visual QA, not login.

```powershell
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --apply
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --cleanup
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --cleanup --apply
```

Cleanup checks the exact fixture markers and refuses to remove the demo task if another account has submitted to it. As of this local preview, the fixture is installed: the default dashboard shows **2 submitted / 2 missing**. To remove it, preview and apply the cleanup commands above. The repository contains the repeatable script, not user data or database exports.

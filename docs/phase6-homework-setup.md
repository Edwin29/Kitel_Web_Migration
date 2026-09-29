# Phase 6 — Homework publishing

`custom_modules/homework` remains the standard Rhymix module for Study > 과제게시판. The repository is the source of truth. Preview `scripts/sync-dev.ps1 -DryRun`, then run `scripts/sync-dev.ps1 -Apply` for the local Rhymix runtime; neither command syncs the database.

## Implemented

- The member list shows real tasks, deadlines, and the submitter's own submission state. Empty and past-deadline states use existing task data.
- The detail screen uses the shared 1160px container, the task description, an in-page submit/resubmit form, and a full submission list only when `view_all` is granted. The submission form retains the existing `content` and `Filedata` fields; no fake title field was added because the backend has no submission title. The deadline/countdown banner is currently hidden by `show_deadline_banner = false`; its markup, styles, and date calculation remain available.
- The technical-department dashboard adds a selected-task summary, counts submitted/missing junior members, and shows their submissions. The original all-task × all-junior matrix remains below it. Counts use the same junior-member rows and submission map as the matrix, not a separate sample dataset.
- The public task list shows a dashboard link only to members with the module's `create` grant (technical department and admin). A manager can also open the current task's summary from its detail page. The dashboard route retains its own server-side `create` grant check.
- Task management uses the existing create grant. Task create/update and submit accept POST only. Task delete is a confirmation-backed POST form; its GET route now returns 405. Invalid deadline dates and cross-module task updates are rejected before saving.
- When a submission replaces an attachment, the previous physical file is removed after a successful DB update. Task deletion removes its submissions and their attached files. Downloads retain the controller's owner-or-`view_all` check.

The Figma detail and submit references are `176:2574` and `176:2575`; the technical summary is `41:631`. The Figma submission form shows a title-like field that has no backend equivalent. The current detail route contains the submission form instead of creating a separate route. Mock pagination/search from the static Figma screen was not presented as functioning controls.

## Local verification

At `http://127.0.0.1:8888/homework`, an admin created, edited, and deleted a temporary task. A temporary junior account viewed the task, submitted text and an attachment, downloaded its own file, and resubmitted with a replacement attachment. The junior view did not expose the admin's submission or the `view_all` list; a direct request for the admin attachment returned 403. The admin dashboard counted the junior as submitted and retained the all-task matrix. As a full member, the QA account could read the submission list and download its file, but had no submit form and received 403 for the technical dashboard. Assigned only to the technical-department group, it could open the dashboard. The task and QA member were deleted after testing, and the fixture's remaining files were checked and removed. An admin GET request to task delete returned 405. Browser console/page errors were absent. The 1440px and 390px page widths were checked; the table remains horizontally scrollable on narrow screens. The final responsive polish belongs to Phase 11.

The local development database still contains the pre-existing `품평회 과제 1차` task and its submission. This work did not change its data or grants. Anonymous `/homework` remains denied by the module's list grant.

## Revised task and submission contract (2026-09-30)

- The submission card spans the same content width as the task description. The task list has a separate submit/edit column. A submission can be revised after the deadline; `updateSubmission` sets `regdate` again, and the controller recalculates `is_late` from the current deadline and this save time.
- Junior members see only `미제출` or `제출완료`, including after a late revision. Only the technical department dashboard displays on-time/late status and the last modified date and time. The general detail's `view_all` table also uses neutral `제출완료` wording.
- Technical staff can edit the task title and description, attach one description image, configure a comma-separated list of allowed submission file extensions, and add up to 20 labeled answer boxes. Each box can be vertically resized in the authoring form, and its height and stable ID are stored with the task. The title is shown as task guidance; the box captures a junior member's answer. Existing tasks and submissions remain valid without extra boxes.
- The image route checks the homework `list` grant. Accepted image MIME types are JPEG, PNG, WebP, and GIF. Submission extension restrictions are checked on the server; the file input `accept` attribute is only a convenience. Leaving the extension setting blank preserves the former unrestricted behavior.
- New fields use `homework_task.answer_fields`, `allowed_extensions`, `description_image` and `homework_submission.answers`. New installs use the XML schemas; an existing Rhymix install can apply the module update. For the known local development database, `php scripts/update-homework-dev.php` previews four additive columns and `php scripts/update-homework-dev.php --apply` adds them after verifying the development root, DB, and homework module. Existing rows are preserved. Sync source files with `scripts/sync-dev.ps1` separately.

## Dashboard entry follow-up

The task list now displays **제출현황 대시보드** when the viewer has `create`, and task detail displays **이 과제 제출현황** for the same grant. Both links lead to the existing admin dashboard; the detail link selects its current task. Local browser checks confirmed the links and navigation for admin and a technical-department QA account, while a full member saw neither link. The QA account was removed after the check.

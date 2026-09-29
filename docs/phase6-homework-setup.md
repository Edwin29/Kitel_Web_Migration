# Phase 6 — Homework publishing

`custom_modules/homework` remains the standard Rhymix module for Study > 과제게시판. The repository is the source of truth. Preview `scripts/sync-dev.ps1 -DryRun`, then run `scripts/sync-dev.ps1 -Apply` for the local Rhymix runtime; neither command syncs the database.

## Implemented

- The member list shows real tasks, deadlines, and the submitter's own submission state. Empty and past-deadline states use existing task data.
- The detail screen uses the shared 1160px container, the task description, an in-page submit/resubmit form, and a full submission list only when `view_all` is granted. The submission form retains the existing `content` and `Filedata` fields; no fake title field was added because the backend has no submission title. The deadline/countdown banner is currently hidden by `show_deadline_banner = false`; its markup, styles, and date calculation remain available.
- The technical-department dashboard adds a selected-task summary, counts submitted/missing junior members, and shows their submissions. The original all-task × all-junior matrix remains below it. Counts use the same junior-member rows and submission map as the matrix, not a separate sample dataset.
- The public task list shows a dashboard link only to members with the module's `create` grant (technical department and admin). A manager can also open the current task's summary from its detail page. The dashboard route retains its own server-side `create` grant check.
- Task management uses the existing create grant. Task create/update and submit accept POST only. The former task-delete control and route have been replaced by a POST-only display switch; existing tasks and submissions remain stored. Invalid deadline dates and cross-module task updates are rejected before saving.
- When a submission replaces an attachment, the previous physical file is removed after a successful DB update. The current task management UI uses show/hide instead of deletion. Downloads retain the controller's owner-or-`view_all` check.

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
- Technical staff author one or more equal question-and-answer sections. The first section and up to 20 added sections use the same prompt editor and vertically resizable answer-box preview. The first answer remains in the existing submission `content` column; later answers keep stable IDs in `answers`, so existing submissions remain readable. The editor accepts inline images by paste, drag and drop, or its image button, and task guidance displays those images in the corresponding question. The former standalone description-image control and top-level list of extra answer titles are no longer shown. Older tasks, including their single legacy image, remain readable and are converted to the inline presentation when edited.
- Inline image files are validated as JPEG, PNG, WebP, or GIF (at most 3 MB each and ten per task), stored outside the web root, and referenced by relative task-image controller URLs in sanitized HTML. The image route checks the homework `list` grant and that the filename is referenced by that task. Submission extension restrictions are checked on the server; the file input `accept` attribute is only a convenience. Leaving the extension setting blank preserves the former unrestricted behavior.
- New fields use `homework_task.answer_fields`, `allowed_extensions`, `description_image` and `homework_submission.answers`. New installs use the XML schemas; an existing Rhymix install can apply the module update. For the known local development database, `php scripts/update-homework-dev.php` previews the additive columns and `php scripts/update-homework-dev.php --apply` adds them after verifying the development root, DB, and homework module. Existing rows are preserved. Sync source files with `scripts/sync-dev.ps1` separately.

## Task list pagination follow-up

The member task list and the technical management list each use the same server-side `getTaskPage` query, ten tasks per page, ordered by task ID descending. The dashboard still loads all tasks for its full submission matrix. The management list keeps a white content region through the bottom of a short desktop viewport so the grey footer does not begin immediately after the last table row. This is scoped to the homework management list.

Local QA temporarily added tasks to reach 11 total: both lists showed 10 rows on page 1 and one on page 2, with working next-page links. An out-of-range page request resolved to the last valid page. The temporary tasks were deleted. With the original seven tasks, the footer started at viewport y=900 at 1920, 1440, and 1024 widths with a 900px-high browser window; no horizontal overflow was observed at 390px.

## Dashboard entry follow-up

The task list now displays **제출현황 대시보드** when the viewer has `create`, and task detail displays **이 과제 제출현황** for the same grant. Both links lead to the existing admin dashboard; the detail link selects its current task. Local browser checks confirmed the links and navigation for admin and a technical-department QA account, while a full member saw neither link. The QA account was removed after the check.

## Dashboard visual demo fixture (2026-09-30)

The submitted/missing summary cards are scaled to about 75% of their previous desktop width and height, with their label and number sizes reduced proportionally. On a narrow viewport the cards retain a fluid width and stack as before.

`scripts/phase6-dashboard-fixture-dev.php` creates local development data only. It checks the exact `D:/rhymix_dev/www/rhymix` root, `rhymix_dev` database on loopback port 3307, homework module 149, and 준회원 group 3. Dry-run is the default. The fixture adds one **hidden** `[P6DEMO] 과제 제출 시연` task, eight marked junior accounts with `99기_데모*` nicknames and random undisclosed passwords, two on-time submissions, and two late submissions. One on-time submission has a downloadable sample text attachment. The other four accounts remain unsubmitted. Existing tasks and submissions are not modified. Hidden status keeps the demo task off the member-facing assignment list while it remains available in the technical dashboard. The demo accounts are intended for visual QA, not login.

```powershell
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --apply
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --cleanup
D:/rhymix_dev/php/php.exe scripts/phase6-dashboard-fixture-dev.php --cleanup --apply
```

Cleanup checks the exact fixture markers and refuses to remove the demo task if another account has submitted to it. As of this local preview, the fixture is installed: the default dashboard shows **2 submitted / 4 missing / 2 late**. To remove it, preview and apply the cleanup commands above. The repository contains the repeatable script, not user data or database exports.

## Dashboard matrix and status filtering

The three summary cards share the full 1160px content width and filter the member list to on-time, missing, or late rows. Each row shows the member's stored Rhymix `nick_name`, task title, status, last submission time, and an attachment download link when one exists. The username is never substituted for the nickname; existing accounts are not renamed by this feature. A submission's `regdate` is rewritten on every save, so the dashboard compares that last edit timestamp with the selected task's deadline at render time. If no deadline exists, a submitted row is on time. The stored `is_late` value is retained for backend compatibility but is not the dashboard's status source.

The all-task matrix displays six junior members per page in a fixed-height, horizontally scrollable region with pagination below it. Pagination is based on the filtered member rows, while the nickname search filters rows and the task-title search filters columns. Its search form uses the same shared pill-shaped search component as the generic board skin. The dashboard fetches every page of the Rhymix junior-group query before building counts and matrix pages; the default Rhymix group query otherwise returns only its first page.

## Question editor correction (2026-09-30)

The technical authoring form now presents each prompt directly above its matching answer-box preview. Added sections have the same status and layout as the first section; the junior detail displays the same sequence, with one answer textarea beneath each prompt. Question text and inline images are stored in sanitized task HTML, while answer text and existing permissions still use the original homework submission backend. This changes the authoring presentation without changing the dashboard's last-edit status calculation.

Local browser QA created a temporary two-question task, inserted an image with the image button, then pasted and dropped images into the second prompt on subsequent edits. All three image requests returned `image/png` and rendered at natural width 1 in the browser. A junior fixture account saw two aligned prompt/answer sections and saved both answers through the original submit action. The temporary login password was restored and the temporary task, its submission, and QA image files were removed after verification.

## Phase 6 final security correction (2026-09-30)

### Output policy

Rhymix v1 templates default to `autoescape=false`. Every Homework skin/admin HTML template now declares `<config autoescape="on" />`. Task titles, member nicknames, submission text, saved answer text, filenames, and attribute values are escaped for their HTML context. CSS preserves line breaks in submission summaries. The two explicit `|noescape` exceptions are the technical author's **sanitized** question HTML (needed for inline images) and Rhymix's generated grant editor. Submission answers and attachment filenames are plain text; they are never rendered as HTML. The dashboard's search value now relies on template autoescape rather than a separate manual escape call.

The local QA submission used these exact strings in the content: `<script>alert(1)</script>`, `</textarea><script>alert(1)</script>`, and `"><img src=x onerror=alert(1)>`; the filename also contained the third payload. Rhymix rejected the direct malicious HTTP form request as `ERR_UNSAFE_VAR`, so the exact strings were placed only into a marked **local QA database row** to exercise the render boundary. Before the correction, opening the row triggered JavaScript dialogs. After the correction, the junior's resubmit textarea and filename link, a full member's submission list and attachment link, and the technical dashboard showed text only: no dialogs or injected script/image elements. Compiled v1 templates were inspected to confirm `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` for these values.

### Attachment storage and migration

The old `files/attach/homework/{module_srl}/.htaccess` contained `Require all denied`, but PHP's built-in server ignores `.htaccess`. A marked QA attachment could be downloaded anonymously from its direct static URL before the correction. Submission files and task prompt images now live under a private directory **outside** both the Rhymix root and the active document root. By default this is `dirname(RX_BASEDIR, 2)/kitel-homework-private/{module_srl}/`; `KITEL_HOMEWORK_PRIVATE_ROOT` can select another absolute private root. Runtime path checks reject a private root inside either public root and reject storage symlinks. Submission filenames are checked before filesystem lookup. Submitted executable-looking files have no web URL; downloads still go through `procHomeworkDownloadSubmission` and its owner/`view_all` check. Task images still go through `procHomeworkTaskImage` and its task reference/list checks.

For the **local development database only**, `scripts/migrate-homework-attachments-dev.php` checks the exact Rhymix root, database and module, inventories DB-referenced submission files and task images, and supports three sequential modes:

```powershell
D:/rhymix_dev/php/php.exe scripts/migrate-homework-attachments-dev.php --dry-run
D:/rhymix_dev/php/php.exe scripts/migrate-homework-attachments-dev.php --copy
# Sync repository code, verify controller downloads, then remove verified public copies:
D:/rhymix_dev/php/php.exe scripts/migrate-homework-attachments-dev.php --purge-public
```

The copy/purge steps verify SHA-256 before removing a public file. The current development runtime migrated three existing submission files and found no old task images. The demo fixture script now writes its sample attachment to the same private storage. A production rollout must provision a private directory outside the actual web server's `DocumentRoot` and any alias, back up and hash-verify legacy files, and remove public copies after controller verification; the development migration script refuses any non-development database. Do not deploy the code against unmigrated legacy files, because the controller intentionally has no public-directory fallback.

### Security and regression QA

- Controller download: submission owner `200`, a full member with `view_all` `200`, unrelated junior `403`; administrator `200`.
- Anonymous and logged-in direct static URL: `404` after removing public copies. A local PHP-like upload was stored privately (Rhymix renamed `.php` to `.phps`); its controller download returned bytes without execution, while the static URL returned `404`. A task prompt image loaded through its controller (`200`), and its static URL returned `404`.
- Junior submit/resubmit worked. Saving without a new file retained the prior stored filename and download; uploading a replacement changed the filename and removed the prior private file. The technical dashboard loaded and the task authoring image/edit path worked. The management display switch went OFF then ON without deleting the task. There is no task delete route in the current Phase 6 UX.
- The marked QA task, submission, uploaded test files, and private prompt image were removed. Temporary passwords and the test full-member group were restored; the existing dashboard demonstration fixture remains intact.

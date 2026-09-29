# Phase 2 generic board setup

The repository skin is `custom_skins/board/kitel_generic/`. It is shared by the local `news` board (module 115, three menu categories) and `seminar` board (module 131). The gallery, sharing, and suggestion skins remain separate owner phases.

## Development installation

1. Run `scripts/sync-dev.ps1 -DryRun`, then `scripts/sync-dev.ps1 -Apply` to copy the repository skin into `D:\rhymix_dev\www\rhymix\modules\board\skins\kitel_generic`.
2. Run `D:\rhymix_dev\php\php.exe scripts/configure-phase2-boards-dev.php` to preview the two DB setting changes. Add `--apply` to set `skin=kitel_generic` and `is_skin_fix=Y` for those exact boards. The script checks the expected development root, DB identity, module IDs, mids, and type before updating.
3. Rebuild the local Rhymix cache through the admin cache reset action. A skin setting change can otherwise continue rendering the cached default skin.
4. Preview and apply `D:\rhymix_dev\php\php.exe scripts/configure-phase2-descriptions-dev.php [--apply]`. This sets only the known development News/Seminar module descriptions and the three News category descriptions. The skin reads category description first, then module description; no board-specific subtitle is embedded in markup. Rebuild the Rhymix cache after applying.
5. Sync `custom_modules/kitelboardguard`, then preview and apply `D:\rhymix_dev\php\php.exe scripts/configure-phase2-file-guard-dev.php [--apply]`. Rebuild the Rhymix cache. The local-only script registers one `file.downloadFile` before-trigger. For another environment, install the module through Rhymix's module lifecycle after reviewing its permissions there; do not run this development DB script against another server.

No production DB configuration is part of this script. In another environment, select **KITEL 공통 게시판** for the appropriate boards through Rhymix admin after reviewing grants and categories there.

## Data and permission contracts

- The skin uses standard board list, document, category, search, pagination, editor, attachment, and comment objects. Write, edit, delete, and comment controls follow Rhymix grants and document permissions.
- News menu items filter the same board by category. The board heading uses the current category title; the global section navigation supplies those category links once.
- News list is public and writing is admin only in the current development grants. Rhymix intercepts a denied News detail before the board skin runs. The shared layout leaves Rhymix's general login, permission, and not-found responses intact. It does not infer access state from `mid`, document URL, or `document_srl`.
- Seminar access follows its board grants. A denied list shows Rhymix's permission/login response, distinct from the skin's empty and search-empty states.
- The actual Rhymix editor is used for document and comment forms. Attachment URLs, file sizes, comment reply routes, and post actions remain connected to Rhymix handlers.
- Rhymix's stock `procFileDownload` checks configured download groups but does not automatically apply this board's `view` grant or comment secrecy to a known file URL. `kitelboardguard` applies the current board's access/list/view grant and the target document/comment visibility at the download trigger. It affects only boards using `kitel_generic`. The comment template independently hides file metadata for deleted and inaccessible comments.

## Reproducible local QA fixture

`scripts/phase2-qa-fixture.mjs` uses the real board forms and editor against only `http://127.0.0.1:8888`. It first runs the development DB identity guard. Install `playwright-core` outside the repository and set `KITEL_PLAYWRIGHT_PACKAGE` to that package's `package.json` if the default local QA installation is unavailable. Set `KITEL_QA_USER` and `KITEL_QA_PASSWORD` to a development administrator account.

```powershell
node scripts/phase2-qa-fixture.mjs create
node scripts/phase2-qa-fixture.mjs cleanup
```

Create records 30 posts across News/Seminar, including a notice, category rows, long HTML, an image, uploaded document file, enough rows for page 2, an uploaded secret comment, and a nested reply. The fixture marker and document IDs are stored only at `D:\rhymix_dev\qa-fixtures\phase2-board.json`; cleanup deletes recorded posts through Rhymix and removes that manifest. `augment` adds the comment fixtures to an older post-only manifest. The guarded `scripts/phase2-qa-access-dev.php` changes only an identified QA comment status or a `p2qa_` test account's junior/full/pending group for permission checks. Do not use these helpers with migrated or production data.

## Correction-pass browser QA (2026-09-30)

- Anonymous: News list 200, existing News detail 403 with login-required message, nonexistent News document 404 with not-found message, Seminar list 403, homework protected page 403. A logged-in pending-group fixture account received 403 with insufficient-permission text for News detail and Seminar list.
- Junior fixture group: News detail and Seminar list 200, Seminar write 403. Full fixture group: Seminar list and write 200. News admin write remained available.
- Real rows: notice, category badges, comment count, 20 normal rows plus notice on page 1, page 2, and disabled First/Back or Next/Last at the appropriate boundary. Search retained category/target/keyword across pagination; a zero-result query now shows its message even while a notice row remains.
- Detail/write/comments: HTML link, long text, loaded image, attachment upload/display/download, create and modify, comment create/reply/modify/delete, and reply depth were exercised. A reply route error caused by an absent `$oDocument` in `_header.html` was fixed.
- Attachment permission: before the trigger, an anonymous direct News file URL returned 200 despite detail 403. After the trigger, anonymous document download returned 403, an allowed junior returned 200, and a secret comment download returned 403 to that junior. Deleted and admin-deleted comment states hid the file metadata and returned 403 for direct download.
- Phase 1 shell remained present with no JavaScript page errors. With populated rows, News had no horizontal overflow at 1920, 1440, 1024, or 390px after moving the category badge into the mobile title cell.
- All generated QA posts, comments, file records, and the pending-group test account were deleted after QA. The two board descriptions, three category descriptions, and download trigger remain as development configuration.

## Legacy dependency note

`board.default.css` remains a temporary dependency for inherited helper forms; disabling it in a browser changed write-form sizing slightly without removing the main KITEL structure. Most `board.default.js` selectors do not match the new list/detail/write UI, and its SNS plugin is unused. Trackback is no longer exposed in the detail or write UI, and unused `title_image`/`title_alt` settings were removed. Tag and guest homepage compatibility remain pending product decisions.

## Local QA performed

Admin: list, write, detail, board search match/empty, attachment upload/display, comment and reply creation, edit/delete links. Anonymous: News list and category filtering, hidden write action, denied News detail with locked explanation, denied Seminar list. All temporary QA posts and comments were deleted after verification.

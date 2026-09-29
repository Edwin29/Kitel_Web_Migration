# Phase 2 generic board setup

The repository skin is `custom_skins/board/kitel_generic/`. It is shared by the local `news` board (module 115, three menu categories) and `seminar` board (module 131). The gallery, sharing, and suggestion skins remain separate owner phases.

## Development installation

1. Run `scripts/sync-dev.ps1 -DryRun`, then `scripts/sync-dev.ps1 -Apply` to copy the repository skin into `D:\rhymix_dev\www\rhymix\modules\board\skins\kitel_generic`.
2. Run `D:\rhymix_dev\php\php.exe scripts/configure-phase2-boards-dev.php` to preview the two DB setting changes. Add `--apply` to set `skin=kitel_generic` and `is_skin_fix=Y` for those exact boards. The script checks the expected development root, DB identity, module IDs, mids, and type before updating.
3. Rebuild the local Rhymix cache through the admin cache reset action. A skin setting change can otherwise continue rendering the cached default skin.

No production DB configuration is part of this script. In another environment, select **KITEL 공통 게시판** for the appropriate boards through Rhymix admin after reviewing grants and categories there.

## Data and permission contracts

- The skin uses standard board list, document, category, search, pagination, editor, attachment, and comment objects. Write, edit, delete, and comment controls follow Rhymix grants and document permissions.
- News menu items filter the same board by category. The board heading uses the current category title; the global section navigation supplies those category links once.
- News list is public and writing is admin only in the current development grants. Rhymix intercepts a denied News detail before the board skin runs. The shared layout identifies a denied News document request and adds a locked-content explanation above Rhymix's existing login or permission message. It does not bypass the grant or replace the login form.
- Seminar access follows its board grants. A denied list shows Rhymix's permission/login response, distinct from the skin's empty and search-empty states.
- The actual Rhymix editor is used for document and comment forms. Attachment URLs, file sizes, comment reply routes, and post actions remain connected to Rhymix handlers.

## Local QA performed

Admin: list, write, detail, board search match/empty, attachment upload/display, comment and reply creation, edit/delete links. Anonymous: News list and category filtering, hidden write action, denied News detail with locked explanation, denied Seminar list. All temporary QA posts and comments were deleted after verification.

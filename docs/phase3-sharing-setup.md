# Phase 3 Sharing/community presentation

The Sharing board (`mid=sharing`, local module 133) continues to use the standard Rhymix board backend. The shared `kitel_generic` skin now selects a community list presentation only for the Sharing list. Detail, write, comments, search query, pagination data, and permission checks remain the Phase 2 board implementation. No community document table or synthetic feed data was added.

## Development installation

1. Run `scripts/sync-dev.ps1 -DryRun` and review mappings/conflicts. Then run `scripts/sync-dev.ps1 -Apply` to copy repository files to the local Rhymix runtime. The script does not delete target files and backs up overwritten files.
2. Run `D:\rhymix_dev\php\php.exe scripts/configure-phase3-sharing-dev.php` to preview the Sharing board settings. Add `--apply` to set `skin=kitel_generic` and `is_skin_fix=Y` only when module 133 is the `sharing` board. It also preserves existing list columns and adds `summary` and `voted_count`; Rhymix selects `content` for summary only when that list column is configured. The guard accepts only the known development root and database. It does not alter grants.
3. Rebuild Rhymix's local cache using the admin cache reset action after changing the skin setting.

In another environment, select the shared skin through Rhymix admin after checking that environment's board identity and grants. The development script refuses another database.

## Presentation and data

- `CommunityList` is `_community_list.html`; `CommunityPostRow` is `_community_row.html`; `CommunityAside` is `_community_aside.html`. Their styles are scoped to `.kitel-community` in `kitel-community.css`.
- The feed uses Rhymix `$notice_list`, `$document_list`, `$page_navigation`, `search_target`, and `search_keyword`. Both list presentations include the same `_pagination.html`. Comment and recommendation counts come from each document. A secret document's excerpt is replaced with a locked message.
- The Figma media rectangles are placeholders. The aside currently shows useful board guidance and a search shortcut; it does not invent images or article metadata. A future owner can replace these slots with configured media/content without changing the board backend.
- The current local board is empty. Its empty and search-empty states are deliberate. A temporary post can be made through the real board form for QA and deleted afterward.

## Permission contract

The existing local Rhymix grants allow `list`, `view`, `write_document`, and `write_comment` for groups 4, 108, 109, 111, and 112 (정회원 and higher). Anonymous and 준회원 users are denied by Rhymix before the skin runs. The skin only displays write controls when `$grant->write_document` is true; this is an additional UI condition, not the authorization mechanism.

## Local verification (2026-09-30)

- Synced from the repository with `sync-dev.ps1`; its dry-run reported no conflict. Rebuilt the Rhymix cache after the local board setting change.
- Anonymous, 가입대기, and 준회원 requests to `/sharing` returned 403. A temporary 정회원 account received 200 for both list and write form. The temporary account was deleted through Rhymix admin.
- An admin created a temporary post through the real board editor. The community list displayed its title and HTML-derived excerpt; detail used the shared board template. Board-local search returned the post for a matching term and the search-empty state for a missing term. The temporary post was deleted through the board action.
- At 1920, 1440, 1024, and 390 pixels, the page had no horizontal overflow. The full-width title band aligned to the viewport at each width. No JavaScript console or page errors appeared during the authenticated functional run.
- News still returned its public list. Anonymous Seminar remained denied by its pre-existing grant.

# Phase 1 shared layout setup

The source files are in `custom_layouts/kitel_site/`. Apply them to the development Rhymix installation with `scripts/sync-dev.ps1 -DryRun`, then `scripts/sync-dev.ps1 -Apply`. The sync script copies the layout to `D:\rhymix_dev\www\rhymix\layouts\kitel_site` and does not copy database settings.

In the Rhymix admin UI, select **KITEL 공통 레이아웃** for the PC site design. Open that layout's detailed settings and set **전체 메뉴(GNB)** to **Main Menu**. These two choices are stored in the Rhymix database; repeat them in any other installation. The layout renders the four public top-level items from that menu. The existing `Welcome` home entry is represented by the logo link. `Else` or `기타` is displayed as `지원`.

The search form submits to Rhymix's integrated search action (`IS`). The current development database has no target modules selected in the integrated search settings, so a submitted query reaches Rhymix but displays its configuration error. Select target modules in the Rhymix admin integrated search settings before accepting search results.

The layout keeps page content in `{$content|noescape}`. Phase 1 does not replace page, board, member, or module templates.

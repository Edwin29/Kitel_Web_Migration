# Phase 1 shared layout setup

The source files are in `custom_layouts/kitel_site/`. Apply them to the development Rhymix installation with `scripts/sync-dev.ps1 -DryRun`, then `scripts/sync-dev.ps1 -Apply`. The sync script copies the layout to `D:\rhymix_dev\www\rhymix\layouts\kitel_site` and does not copy database settings.

In the Rhymix admin UI, select **KITEL 공통 레이아웃** for the PC site design. Open that layout's detailed settings and set **전체 메뉴(GNB)** to **Main Menu**. These two choices are stored in the Rhymix database; repeat them in any other installation. The layout renders the four public top-level items from that menu. The home entry is identified by its `index` mid, independently of its display name, and represented by the logo link. `Else` or `기타` is displayed as `지원`.

The search form submits to Rhymix's integrated search action (`IS`). The local development installation is configured for document results from the public exhibition board (module 129) only. The existing RC filter project is returned by a search for `RC`; an unmatched query shows the normal empty result state. Additional boards must be reviewed for read permissions before being added. `scripts/configure-search-dev.php` previews by default and accepts `--apply` only for the expected local development environment.

The layout keeps page content in `{$content|noescape}`. Phase 1 does not replace page, board, member, or module templates.


## Phase 1 correction contracts

- Layout resets and focus styles apply to `kitel-chrome` regions only. `$content` retains its skin/widget link, input and box-sizing rules; shared `kitel-*` primitives remain opt-in.
- Rhymix error responses can retain the requested module identity. Navigation checks the rendered HTTP status and system-message context as well as account/search actions before assigning an active section.
- Header and MegaMenu use the same fluid grid tracks. All four GNB links control the same panel and report its open state. Escape returns focus to the originating GNB without reopening the panel.
- Section spacing is owned by the layout: News/Study use horizontal navigation; About/support use banner and sidebar, with support's compact sidebar gap. Body skins do not compensate for shell offsets.
- Both DB setup scripts reject any root other than `D:/rhymix_dev/www/rhymix` and any DB identity other than loopback port `3307`, database `rhymix_dev`, prefix `rx_`, user `rhymix`, before connecting. Dry-run remains the default. This pass changes no menu/search records.

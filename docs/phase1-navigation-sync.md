# Phase 1 navigation data sync

The current local Rhymix `Main Menu` (menu 48) is the single navigation source for the Header, MegaMenu, mobile menu, section navigation, and Footer. The layout reads `$GNB->list` for every surface. It does not contain a second menu definition.

## Verified local data before sync (2026-09-29)

- `Kitel News` (menu item 116) had no children in `rx_menu_item` or generated menu cache `files/cache/menu/48.php`.
- The `news` board (module 115) had no categories in `rx_document_categories`.
- About children were `kitelinfo`, `staff`, `rule`, `schedule`.
- Study children were `exhibition`, `seminar`, `sharing`, `storage`, `homework`.

These differences are menu data, not a Header/MegaMenu/Footer renderer defect. The target order comes from `docs/publishing-handoff/KITEL_IA_v3_Draft.md`: About `kitelinfo > staff > schedule > rule`; News `동아리 내 경사 > 최근 진행 행사 보고 > 일정 공고`; Study `storage > homework > exhibition > seminar > sharing`.

## Apply to the local development instance

From the repository root, using the local PHP executable:

```powershell
& D:\rhymix_dev\php\php.exe scripts\sync-navigation-dev.php
& D:\rhymix_dev\php\php.exe scripts\sync-navigation-dev.php --apply
```

The first command previews. The second updates the local DB only: it orders existing About/Study items, creates three News categories and their menu shortcuts, and clears the matching generated menu/category caches. It checks the expected menu shape and refuses unexpected children or categories. A JSON snapshot of affected rows is written to `D:\rhymix_dev\sync-backups` before changes. It never writes to the repository or a non-local database. The script is idempotent for the expected shape.

For layout code, preview and apply `scripts/sync-dev.ps1` separately. That file sync does not manage Rhymix menu or category records. News menu links resolve to the `news` board filtered by the created category IDs. News board list/detail permission and category display remain Phase 2 work.

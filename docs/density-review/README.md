# KITEL design density recalibration

Reviewed at 1920 × 1080 on the local Rhymix runtime on 2026-09-30. The News, Seminar, and Sharing boards each contained six temporary posts created through the actual board editor. Screenshots show an administrator session; its extra “게시글 관리” link shifts the table slightly lower than for a regular member. The temporary posts were removed after capture.

## Diagnosis and reference

The existing News/Study horizontal section navigation occupied 189px below the 80px header. A further 104–114px content gap preceded a 45px board heading, 24px subtitle, and 50px heading-to-toolbar gap. These sizes made sense with an expressive page, but pushed the first 75px table row to 656–666px. The Sharing feed began at 748px because its introduction added more vertical space.

The [Hanwha Vision News Hub](https://www.hanwhavision.com/ko/news-events/news-hub?tab=solution-insight) uses a prominent title, tabs, whitespace, and large image cards. Its card content has enough visual weight to balance the top area. KITEL's list rows and forms have a different scale, so the same vertical proportions are not reused. The navy, Pretendard, underline navigation, restrained color, and broad content width remain.

## Density model

- **Expressive:** Home, About, Exhibition/gallery, and image or editorial pages. Existing section/banner spacing remains the baseline.
- **Productive:** Standard board pages including News, Seminar, and Sharing; Archive, Homework, Account, and integrated search. Productive pages share a compact horizontal section navigation and reduced heading/content spacing.
- The layout selects the variant from the rendered Rhymix module and excludes the gallery skin from standard board classification. This is a presentation decision; it does not change permissions, routes, or board data.

The shared 8px-based spacing scale and productive title/subtitle tokens live in `custom_layouts/kitel_site/css/tokens.css`. The layout sets the density class and variant spacing variables. Generic Board and Sharing consume those variables; their backend templates and board actions remain intact.

## First viewport measurements

| Page | Header + section bottom before → after | Page title / description before → after | Toolbar or search top before → after | First row top before → after | Fully visible rows before → after |
|---|---:|---|---:|---:|---:|
| News | 269 → 145px | 45 / 24 → 32 / 16px | 530 → 279px | 666 → 416px | 5 → 6 |
| Seminar | 269 → 145px | 45 / 24 → 32 / 16px | 520 → 279px | 656 → 416px | 5 → 6 |
| Sharing | 269 → 145px | band title 30px unchanged | 393 → 189px | 748 → 458px | 3 → 6 |
| About | banner bottom 260px unchanged | — | — | — | — |

The global header stayed at 80px. News and Seminar table headers now start at 365px; the actual post list begins at 416px. Sharing retains its full-width band and introduction, with posts beginning at 458px. About's before/after screenshots are pixel-identical.

## 1920 × 1080 screenshots

| Page | Before | After |
|---|---|---|
| News | [before-news.png](before-news.png) | [after-news.png](after-news.png) |
| Seminar | [before-seminar.png](before-seminar.png) | [after-seminar.png](after-seminar.png) |
| Sharing | [before-sharing.png](before-sharing.png) | [after-sharing.png](after-sharing.png) |
| About | [before-about.png](before-about.png) | [after-about.png](after-about.png) |

Raw browser measurements are in [before-metrics.json](before-metrics.json) and [after-metrics.json](after-metrics.json). The [390px Sharing capture](after-sharing-390.png) records the wrapped section navigation and feed.

## Verification and remaining concerns

At 390, 768, 1024, 1440, and 1920px, News, Seminar, Sharing, About, Exhibition, Archive, and Homework had no horizontal document overflow or browser console errors. About and Exhibition remained expressive; Archive and Homework received the productive shell. The board templates and grants were not changed.

Archive, Homework, and Account still have body-specific typography and spacing to address in their owner phases. Sharing's aside currently contains guidance cards because Figma media blocks are placeholders; real media content remains a product/content decision. Detailed mobile polish remains Phase 11. Future screens should choose a density class from their content type rather than copy a page's pixel values; Calendar needs that decision when its Phase 4 layout is implemented.

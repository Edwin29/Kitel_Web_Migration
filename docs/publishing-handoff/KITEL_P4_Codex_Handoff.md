# KITEL P4 — Codex Implementation Handoff

Status: **Implementation-ready baseline**
Target repository: `Edwin29/Kitel_Web_Migration`
Figma source: `https://www.figma.com/design/0VXi5IY64M8jRJOsc1kX2p/KITELwebsite--%25EB%25B3%25B5%25EC%2582%25AC-?node-id=0-1&p=f`
Date baseline: 2026-09-29

---

# 1. 이 문서의 역할

이 문서는 Figma 목업을 Rhymix 기반 KITEL 신규 홈페이지 코드로 옮길 때 Codex가 따라야 할 **실행 계약**이다.

Codex의 목표는 “Figma를 HTML로 복사”하는 것이 아니다.

목표:

1. 현재 Rhymix 기능과 권한 로직을 유지한다.
2. Figma 디자인을 시각 기준으로 사용한다.
3. 반복 화면을 공통 구조로 구현한다.
4. 현재 임시 skin CSS를 최신 디자인 foundation으로 교체한다.
5. 반응형은 구현 결과를 보면서 전문적으로 설계한다.
6. Figma와 backend가 충돌하면 backend contract와 최신 IA를 우선하고 차이를 명시한다.

---

# 2. 읽어야 할 문서 순서

Codex는 구현 전에 다음 문서를 순서대로 읽는다.

1. `KITEL_IA_v3_Draft.md`
2. `KITEL_P0_Design_Foundation.md`
3. `KITEL_P1_Shared_Layout_Spec.md`
4. `KITEL_P2_Generic_Board_State_Spec.md`
5. `KITEL_P3_Custom_Screens_Exception_Spec.md`
6. repository:
   - `README.md`
   - `docs/roadmap.md`
   - `docs/new-site-ia.md`
   - `docs/board-feature-specs.md`
   - `docs/frontend-skin-architecture.md`

우선순위 충돌 시:

```text
현재 사용자 확인 사항
> 최신 Figma
> roadmap.md
> new-site-ia.md
> board-feature-specs.md
> designer-brief.md
> legacy XE UI
```

단, **기능/권한/데이터 contract는 실제 현재 코드가 최종 확인 대상**이다.

---

# 3. Repository 작업 전 필수 reconnaissance

코드를 수정하기 전에 실제 worktree를 조사한다.

반드시 확인:

```text
- 현재 branch / git status
- 실제 Rhymix 설치 위치
- custom_modules / custom_skins / custom_widgets가 운영 코드에 어떻게 연결되는지
- layout directory 위치
- 기존 custom layout 존재 여부
- 공통 CSS/JS entrypoint 존재 여부
- Pretendard 로딩 방식
- 현재 board skin 설정
- 현재 module mid / grant 설정
- 회원 login/signup template override 방법
```

주의:

GitHub 저장소의 `kitel_web/xe/`에는 현재 전체 Rhymix 트리가 들어 있지 않을 수 있다.
따라서 handoff 문서에 없는 경로를 추측해서 파일을 생성하지 말고 **실제 Codex 작업 환경을 먼저 확인한다.**

---

# 4. Figma 사용 규칙

## Primary design source

File key:

```text
0VXi5IY64M8jRJOsc1kX2p
```

Figma MCP를 사용할 수 있다면 구현하려는 화면의 정확한 node를 다시 읽는다.

### 중요

Figma design-context가 생성하는 React/Tailwind는 **최종 코드가 아니다.**

금지:

```text
- React를 그대로 도입
- Tailwind를 새 dependency로 설치
- absolute coordinate를 그대로 CSS에 복사
- temporary Figma asset URL을 production 코드에 남김
```

해야 할 것:

```text
Figma geometry/style
→ Rhymix HTML template
→ project CSS/JS
```

---

# 5. 주요 Figma node map

| Node | 역할 |
|---|---|
| `176:861` | Home assembled page |
| `176:863` | Desktop header |
| `6:111` | Desktop mega menu |
| `81:499` | Mobile navigation concept |
| `81:585` | Mobile Home/layout direction |
| `176:1015` | Footer |
| `176:1043` | About → 키텔 소개 |
| `176:1156` | About → 임원진 소개 |
| `176:1341` | About → 일정 |
| `176:1731` | About → 회칙 |
| `176:1781` | News → 최근 진행 행사 보고 |
| `176:1958` | News → 일정 공고 |
| `176:2135` | News → 동아리 내 경사 |
| `176:2355` | Seminar board list |
| `176:2573` | Generic board detail/comments |
| `176:2574` | Homework detail/submission list |
| `176:2575` | Homework submit visual |
| `176:2945` | Exhibition gallery |
| `178:1123` | Suggestions |
| `178:1234` | Contact Us |
| `178:1318` | Generic board write |
| `178:1518` | Rental entry page |
| `64:334` | Sharing/community board |
| `41:631` | Homework technical-department dashboard |
| `19:166` | Login |
| `148:897` | Signup |
| `46:842` | Signup complete |
| `51:1279` | My Page |
| `6:93` | Hero source |

Do not implement:
- `34:213`
- `41:314`

These are currently unused experimental `게시글 / 사진` tab templates.

---

# 6. Design foundation contract

Core:

```text
Font: Pretendard

Primary:          #191970
Text primary:     #1C1F25
Text muted:       #A9AEB3
Primary soft:     #EEF1FB
Surface subtle:   #F5F5F5
Footer:           #F0F0F0
Border default:   #E9E9E9
Row border:       #E2E8F0

Main content max: 1160px
Header height:    80px
```

Typography baseline:

```text
14 / 16 / 20 / 24 / 32 / 40 / 45 / 60px
```

Radius baseline:

```text
4px pagination
5px form/utilitarian
20px pill/card
```

Do not proliferate arbitrary one-off values unless visual matching genuinely requires them.

---

# 7. Architecture boundary

## Layout layer

Owns:

```text
SiteHeader
MegaMenu
Global search entry
Account actions
Main shell
SiteFooter
Mobile global navigation
```

## Board skin layer

Owns:

```text
SectionNavigation
PageHeading
Board list
Board detail
Board write
Comments
Search
Pagination
Locked/empty/permission states
```

## Custom module/widget layer

Owns:

```text
Calendar
Archive
Homework
Exhibition specialization
Suggestions exception UI
Home widgets
```

---

# 8. Recommended implementation order

## Phase 0 — Safety / environment

Do before UI work:

```text
[ ] git status clean or understood
[ ] create/use implementation branch
[ ] identify actual Rhymix root
[ ] run current site locally
[ ] record existing routes/pages that work
[ ] do not change data schema during pure skin phase
```

---

## Phase 1 — Foundation + shared layout

Implement first:

```text
tokens.css
typography/base CSS
main container
header
mega menu
footer
section navigation
page heading
common buttons/inputs/search
```

Use existing layout if present; otherwise create a project-appropriate custom Rhymix layout after inspecting conventions.

Acceptance:
- desktop header matches Figma closely
- mega menu usable with mouse and keyboard
- `Else` displayed as **지원**
- no absolute 1920-layout dependency
- footer IA matches header IA

---

## Phase 2 — Generic board skin

Target:

```text
Kitel News
Seminar
generic detail
generic write
comments
search
pagination
states
```

Implement reusable skin/partials before duplicating pages.

Acceptance:
- List / Detail / Write render real Rhymix data
- Write button respects grants
- edit/delete respects document permission
- News list visible to public
- News body locked for users without detail permission
- empty/search-empty/permission states distinct
- attachments render correctly
- comment/reply still works

---

## Phase 3 — Sharing/community presentation

Backend remains standard board where feasible.

Implement:
```text
CommunityList
CommunityPostRow
CommunityAside
```

Reuse:
```text
Detail
Write
Comment
Permission
Search backend
```

Acceptance:
- `64:334` visual intent retained
- 정회원 read/write restriction preserved
- no duplicate community-specific document backend

---

## Phase 4 — Calendar

Files to inspect:
```text
custom_modules/calendar/calendar.view.php
custom_modules/calendar/skins/default/list.html
custom_modules/calendar/tpl/event_list.html
custom_modules/calendar/tpl/event_form.html
```

First pass:
- redesign existing month view only
- preserve prev/next navigation
- preserve `weeks`, events, today state
- admin controls only under manage permission

Do not silently fake week view.
If implementing Figma week view, make it a separate backend extension and document it.

---

## Phase 5 — Archive

Files:
```text
custom_modules/archive/archive.view.php
custom_modules/archive/skins/default/list.html
```

Preserve:
```text
breadcrumb
folder navigation
folder create
upload
download
delete
empty folder
grant checks
```

No final Figma page is authoritative here.
Use P0/P1 visual language rather than inventing an unrelated UI.

---

## Phase 6 — Homework

Files:
```text
custom_modules/homework/homework.view.php
custom_modules/homework/skins/default/index.html
custom_modules/homework/skins/default/view.html
custom_modules/homework/homework.admin.view.php
custom_modules/homework/tpl/task_list.html
custom_modules/homework/tpl/task_form.html
custom_modules/homework/tpl/dashboard.html
```

Member UI:
- task list
- task detail
- own submit/re-submit
- full submissions for authorized members

Technical-department admin:
- preserve existing task × junior matrix
- add/integrate Figma per-task summary rather than replacing matrix

Never expose other juniors' submissions to junior members.

---

## Phase 7 — Exhibition

Existing:
```text
custom_skins/board/kitel_gallery/
```

Preserve:
- gallery data
- uploaded-image extraction
- pending/approval status
- search/pagination/write lifecycle

Policy:
- no comments in final exhibition UX
- admin approval
- 정회원 write
- public approved gallery

Do not interpret year-filter-looking Figma controls as real features unless backend support exists.

---

## Phase 8 — Suggestions

Goal:

```text
one page
├─ inline composer
└─ own suggestions list / staff-scoped list
```

Policy:
- 정회원 writes
- 준회원 cannot write
- author sees own
- 임원진 sees all
- anonymous hides identity even from 임원진 UI
- admin can trace through backend/log where necessary

Figma `전체 공개 / 비공개` selector conflicts with current policy.
Do not implement it blindly.

---

## Phase 9 — Home widgets

Preserve widget-page architecture.

Existing custom widgets:

```text
custom_widgets/hero_banner
custom_widgets/upcoming_events
```

Use standard Rhymix Content widget for eligible preview sections.

Hero baseline:
- single banner
- no carousel unless explicitly extended
- dynamic image/headline/subtext/CTA
- current Figma image content is placeholder

---

## Phase 10 — Account screens

Target Figma:
- Login `19:166`
- Signup `148:897`
- Signup Complete `46:842`
- My Page `51:1279`

Do not build custom auth logic.
Skin/override Rhymix member flows.

Before changing signup:
- inspect current active member fields
- preserve real required fields
- treat Figma field list as visual target, not schema authority

Security:
- never display existing password value
- use proper password-change flow

---

## Phase 11 — Responsive implementation

This is intentionally delegated to implementation phase.

Requirements:
- desktop Figma = primary fidelity target
- mobile Figma = direction reference
- do not proportionally shrink everything
- use reflow/recomposition
- exact breakpoints chosen from real layout breakage
- mobile touch controls not copied at too-small Figma sizes

Minimum QA viewports:

```text
390
768
1024
1440
1920
```

Suggested transformations:
- GNB → drawer/sheet
- multi-column → fewer columns / stack
- board table → stacked rows when needed
- footer columns → stack
- forms → fluid width
- community → mobile feed
```

---

# 9. Backend contract: never break silently

Preserve condition/data APIs unless intentionally refactoring with tests.

Board:
```text
$document_list
$notice_list
$page_navigation
$grant
$oDocument
$oComment
$category_list
search_target
search_keyword
```

Calendar:
```text
weeks
weekday_names
cur_year/cur_month
prev_*
next_*
is_manager
```

Archive:
```text
cur_folder_srl
breadcrumb
folders
files
is_manager
```

Homework:
```text
tasks
is_submitter
can_view_all
my_submission
all_submissions
is_past_deadline
rows
```

---

# 10. Legacy UI: logic vs presentation

Do not assume every legacy skin UI should survive.

Candidates for removal from visible UI unless confirmed:

```text
old SNS share controls
trackback
tag search
guest homepage field
legacy decorative assets
```

Rule:

> preserve required backend behavior, not obsolete legacy presentation.

---

# 11. Image/assets policy

Current mockup images are placeholders.

Therefore:
- do not embed their subject matter as semantics
- use replaceable data/config slots
- preserve container ratio/crop/radius
- use local production assets, not temporary Figma URLs
- only truly structural icons/logo should become static assets

Recommended semantic naming:

```text
HeroMedia
ArticleCover
FeatureMedia
GalleryThumbnail
ExecutiveProfileImage
```

---

# 12. Responsive delegation brief

Codex/Claude may design responsive behavior, but must follow:

```text
1. inspect mobile Figma first
2. implement desktop accurately
3. progressively reduce viewport
4. introduce breakpoints where layout actually breaks
5. preserve hierarchy, readable text, touch targets
6. prefer reflow over scale
7. validate at minimum 390/768/1024/1440/1920
```

If design decisions are ambiguous:
- prefer conventional polished web behavior
- record the decision in implementation notes
- do not invent business functionality

---

# 13. Visual QA loop

For each major screen:

```text
Figma screenshot
→ implementation screenshot
→ compare
→ fix
```

Check:

```text
[ ] max content width
[ ] alignment
[ ] header height
[ ] typography family/weight/size
[ ] spacing hierarchy
[ ] borders/radius/shadows
[ ] active/inactive navigation
[ ] real data overflow
[ ] long title
[ ] empty state
[ ] permission state
[ ] buttons only when authorized
[ ] image crop behavior
[ ] mobile reflow
```

Do not chase 1–2px discrepancies before structural layout and data states are correct.

---

# 14. Functional regression checklist

After visual changes:

```text
[ ] Login works
[ ] Signup works
[ ] Logout works
[ ] My Page works
[ ] board search works
[ ] pagination works
[ ] document read works
[ ] write works under correct grant
[ ] edit/delete permissions work
[ ] comments/replies work where enabled
[ ] News public list + protected body works
[ ] Calendar month navigation works
[ ] Calendar admin CRUD works
[ ] Archive folder navigation works
[ ] Archive upload/download works
[ ] Homework submit/re-submit works
[ ] Junior visibility restriction works
[ ] Homework admin matrix works
[ ] Exhibition approval visibility works
[ ] Suggestion privacy/anonymous behavior works
[ ] Home widgets still read live data
```

---

# 15. Stop conditions / ask before proceeding

Codex should pause and ask rather than guess when:

```text
- schema migration seems necessary
- current code conflicts materially with latest IA
- a Figma control implies unsupported backend functionality
- deleting a backend feature appears necessary
- permissions are ambiguous
- production member field configuration differs significantly from Figma
- implementing week calendar requires non-trivial backend extension
- rental system internals would need redesign rather than entry-point styling
```

Pure layout/CSS decisions do not require asking every time.

---

# 16. Definition of done

The publishing pass is done only when:

```text
- major Figma desktop screens are represented
- shared layout is consistent
- real Rhymix data drives all dynamic screens
- permission-sensitive UI is correct
- generic board code is not unnecessarily duplicated
- custom modules retain functionality
- placeholders are replaceable
- responsive behavior works across target widths
- no temporary Figma asset URLs remain
- visual QA completed
- functional regression checklist passes
```

---

# 17. Deliverables expected from Codex

Codex should leave:

```text
1. production code changes
2. concise IMPLEMENTATION_NOTES.md
   - files changed
   - responsive decisions
   - known deviations from Figma
   - backend/design gaps encountered
3. screenshots or QA evidence for representative pages
4. no undocumented schema or permission changes
```

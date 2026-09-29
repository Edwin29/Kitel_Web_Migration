# KITEL P1 — Shared Layout / Component Specification

상태: Working baseline  
목적: Codex 구현 전에 공통 레이아웃과 공용 컴포넌트의 책임·상태·Figma 대응을 고정한다.  
전제: 반응형의 **정확한 breakpoint 및 세부 재배치 규칙은 구현 단계에서 결정**한다. 이 문서는 데스크톱 기준 구조와 반응형 원칙만 정의한다.

---

# 1. 공통 화면 구조

```text
SiteLayout
├─ SiteHeader
│  ├─ BrandLogo
│  ├─ PrimaryNav
│  ├─ SearchControl
│  └─ AccountActions
├─ MegaMenu                # desktop expanded navigation
├─ PageShell
│  ├─ SectionNavigation    # About / News / Study / 지원 하위 메뉴
│  └─ PageContent
└─ SiteFooter
```

페이지별 콘텐츠는 이 공통 Layout을 복제하지 않고 `PageContent` 위치에 삽입한다.

---

# 2. Desktop layout baseline

## 2.1 Main container

Figma 기준:

```text
Reference viewport: 1920px
Main content max width: 1160px
Centered
```

구현 원칙:

```css
.kitel-container {
  width: min(1160px, calc(100% - 48px));
  margin-inline: auto;
}
```

- `left: 380px` 같은 Figma 절대 좌표를 코드에 하드코딩하지 않는다.
- 페이지별 콘텐츠가 1160px보다 좁아야 할 경우 내부 컴포넌트에서 별도 max-width를 둔다.
- Header/Footer의 전체 배경은 full-bleed, 실제 내용만 container 기준으로 정렬한다.

---

# 3. SiteHeader

## 3.1 Figma reference

주요 reference:
- desktop header: `176:863`
- source/component 계열: `Component 8`
- mobile direction reference: `81:585`

Desktop Figma 기준:

```text
height: 80px
logo: 70 × 70
nav item reference width: 147px
nav gap: 36px
search: 255 × 40, radius 20px
right action gap: 16px
GNB font: Pretendard Regular 20px
```

## 3.2 Information structure

최신 IA 기준 top-level navigation:

```text
About
Kitel News
Study
지원
```

Figma에 남아 있는 `Else` 텍스트는 구현 시 `지원`으로 교체한다.

## 3.3 Responsibility

`SiteHeader`는 다음만 책임진다.

- 사이트 로고 및 Home 이동
- 최상위 메뉴
- 검색 진입
- 로그인 상태별 account action
- MegaMenu 열기/닫기 트리거
- 현재 top-level section 표시

게시판별 하위 탭/페이지 제목은 Header 책임이 아니다.

## 3.4 State

### Logged out

Figma에 명확히 존재:

```text
Search
Login
Signup
Language/Globe
```

### Logged in

Figma에 별도 완성 variant는 확인되지 않음.

구현 계약:

- `Login` / `Signup` 영역을 로그인 상태용 action slot으로 교체 가능해야 함.
- 실제 label/구성은 기존 Rhymix 세션 UI 및 구현 시점 UX 검토 후 결정.
- 최소 기능:
  - My Page 진입
  - Logout
- 레이아웃은 account action 개수가 바뀌어도 깨지지 않게 flex 기반으로 구성.

### Current section

현재 top-level section은 underline / text emphasis 등으로 식별할 수 있게 한다.

---

# 4. MegaMenu

## 4.1 Figma reference

`6:111`

Desktop Figma에 이미 전체폭 mega menu가 존재한다.

```text
panel height: 300px
4 columns
columns aligned with primary nav
vertical separators
```

내용:

```text
About
├─ 키텔 소개
├─ 임원진 소개 및 인사말
├─ 일정
└─ 회칙

Kitel News
├─ 동아리 내 경사
├─ 최근 진행 행사 보고
└─ 일정 공고

Study
├─ 자료실
├─ 과제 게시판
├─ 작품전시회 게시판
├─ 세미나 게시판
└─ 공유 게시판

지원
├─ 건의사항
├─ Contact Us
└─ 기자재 대여 시스템
```

## 4.2 Interaction contract

Desktop:

- top-level GNB hover 또는 keyboard focus 시 MegaMenu 표시
- MegaMenu 내부로 pointer가 이동해도 닫히지 않음
- header + panel 영역을 벗어나면 닫힘
- Escape로 닫을 수 있어야 함
- keyboard tab navigation 가능해야 함
- 현재 페이지 링크는 active styling 가능

Exact delay/animation은 구현 단계에서 결정한다.

## 4.3 권한에 따른 메뉴 노출

기본 원칙:

- 접근 권한이 없는 메뉴는 `grants`만 막고 링크를 무조건 노출하는 방식보다, 가능한 경우 메뉴 레벨에서도 숨긴다.
- 단, Kitel News는 비회원도 목록 접근 가능하므로 메뉴 표시.
- Study 내 자료실/공유 게시판처럼 회원 제한이 강한 항목은 Rhymix 메뉴 권한 정책과 맞춰 노출 여부 결정.
- `지원 > 건의사항`은 준회원에게 작성 권한이 없으므로 메뉴 노출 정책을 기존 IA/운영 정책에 맞춘다.

이 부분은 실제 Rhymix menu grant 구현과 연결한다.

---

# 5. SearchControl

## 5.1 Desktop visual

```text
255 × 40
radius 20px
24px search icon
background or outlined variant
```

## 5.2 Behavior

- Header search는 사이트 전역 검색 진입점
- `Enter` 및 search icon으로 submit 가능
- 검색 결과 페이지는 별도 PageContent로 렌더링
- 게시판 내부 search control과 전역 search는 시각적으로 유사해도 기능은 분리한다.

Component 분리:

```text
GlobalSearch
BoardSearch
```

공통 primitive(`SearchField`)는 재사용 가능.

---

# 6. AccountActions

## Logged out
- Login
- Signup

## Logged in
- My Page
- Logout
- 필요 시 사용자명/프로필 affordance는 구현 단계에서 추가 가능

원칙:

- account action을 개별 absolute-positioned text로 구현하지 않는다.
- session state에 따라 동일 slot에서 자연스럽게 교체되는 flex group으로 만든다.

---

# 7. Mobile navigation direction

정확한 responsive spec은 구현 단계로 유보하지만, Figma `81:499`, `81:585`에서 다음 방향은 확인됨.

- hamburger navigation
- primary content single-column
- footer vertical stacking
- desktop GNB 숨김

구현 원칙:

```text
Desktop MegaMenu
        ↓
Mobile Drawer / Sheet
```

Mobile drawer에서는 top-level navigation을 accordion 형태로 확장 가능하게 설계하는 것을 기본 방향으로 한다.

Figma의 16px hamburger, 20px 높이 검색창처럼 지나치게 작은 control 크기는 그대로 복제하지 않는다.
실제 touch target은 구현 단계에서 접근성을 고려해 확보한다.

---

# 8. SectionNavigation

Header와 별개로 각 section page의 상단에 하위 navigation이 존재한다.

예: Study

```text
Study
────────
자료실 | 과제 게시판 | 작품전시회 게시판 | 세미나 게시판 | 공유 게시판
```

Figma 관찰:

- section title: 24px Medium
- inactive submenu: muted
- active submenu: primary text
- section title underline 있음

## Responsibility

`SectionNavigation`:

- section title
- current subsection 표시
- subsection 이동

페이지 본문 제목(`세미나 게시판`, `동아리 내 경사`)은 별도의 `PageHeading`.

### Component API concept

```text
SectionNavigation
  section = "Study"
  items = [...]
  active = "seminar"
```

정적 HTML 복붙이 아니라 메뉴 데이터를 받아 렌더링 가능한 구조 권장.

---

# 9. PageHeading

게시판/기능 본문의 큰 제목 영역.

Figma board reference:

```text
title: 45px Medium
tracking: -0.03em
description: 24px Light / muted
```

예:

```text
세미나 게시판
세미나 관련 내용이 올라오는 게시판
```

공통화 가능한 대상:

- Kitel News 3종
- 세미나
- 일부 Study page
- 관리 화면의 section heading

---

# 10. SiteFooter

## 10.1 Figma reference

`176:1015`

Desktop:

```text
height reference: 579px
background: #F0F0F0
content x alignment: main container
top padding reference: 120px
```

4개 sitemap column:

```text
About
Kitel News
Study
지원
```

Footer는 상단 GNB의 IA와 동일해야 한다.

## 10.2 Footer policy

- Figma의 `Else` → `지원`
- sitemap 데이터를 Header/MegaMenu와 별도로 하드코딩하지 않는 방향 권장
- 동일 navigation configuration을 재사용해 메뉴 불일치를 방지
- copyright 영역은 별도 `FooterMeta`
- footer link의 14/16px 혼재는 구현 시 semantic style로 정규화

---

# 11. Shared navigation data

Codex 구현 시 Header / MegaMenu / Footer / Mobile drawer가 같은 navigation source를 사용하도록 설계한다.

개념:

```text
navigation
├─ About
│  └─ children[]
├─ Kitel News
│  └─ children[]
├─ Study
│  └─ children[]
└─ 지원
   └─ children[]
```

Rhymix가 실제 메뉴 트리를 제공할 수 있다면 이를 우선 사용한다.

**중요:** JS 객체를 별도로 만들어 Rhymix 메뉴 설정과 이중 관리하지 않는다.
가능하면 Rhymix menu data → 여러 presentation에서 공통 소비하는 방향을 우선한다.

---

# 12. Shared UI primitives

P1에서 공통 primitive로 고정할 대상:

```text
Container
PrimaryButton
CompactButton
SearchField
TextInput
SoftInput
CategoryBadge
Pagination
MediaSlot
PageHeading
SectionNavigation
```

Composite:

```text
SiteHeader
MegaMenu
AccountActions
MobileNavigation
SiteFooter
BoardToolbar
```

이 단계에서 `Card`, `BoardRow`처럼 페이지 본문 중심 컴포넌트도 foundation은 정의하되,
상세 계약은 다음 Generic Board spec에서 확정한다.

---

# 13. Layer ownership in Rhymix

## Layout layer

담당:

```text
Header
MegaMenu
Footer
Global Search entry
Global navigation
Main content shell
```

즉 공통 Rhymix layout에서 관리.

## Skin/module layer

담당:

```text
SectionNavigation
PageHeading
Board/content UI
Calendar
Archive
Homework
Gallery
Suggestions
```

단, SectionNavigation을 layout에서 생성할지 skin에서 생성할지는 실제 Rhymix 메뉴 데이터 접근 구조를 보고 Codex가 결정하되,
**표현 컴포넌트의 디자인은 하나로 공유**한다.

---

# 14. Figma → component mapping

| Figma Node | Component/Role |
|---|---|
| `176:863` | SiteHeader desktop |
| `6:111` | MegaMenu desktop |
| `81:499` | mobile navigation concept |
| `81:585` | mobile Home/layout direction |
| `176:1015` | SiteFooter |
| `176:2438` | Section title style |
| `176:2458` | PageHeading title |
| `176:2459` | PageHeading description |
| `19:166` | Login content screen; not global layout |

---

# 15. Responsive status

Responsive 상세 설계는 **구현 단계로 명시적으로 유보**한다.

현재 확정하는 것은 다음 원칙뿐:

- desktop은 Figma 기준에 높은 시각 충실도
- mobile Figma는 방향성 참고
- absolute-scale 방식 금지
- layout reflow 허용
- header → mobile navigation 전환
- multi-column → stacked layout 전환
- exact breakpoint는 실제 browser QA로 결정

따라서 Codex는 breakpoint 숫자를 임의로 "디자인 요구사항"이라고 주장하지 말고,
구현 결과가 깨지는 지점을 기준으로 선택하고 QA에서 조정해야 한다.

---

# 16. P1 완료 기준

- [x] SiteLayout 책임 정의
- [x] Header 구조 정의
- [x] Desktop MegaMenu 확인 및 계약 정의
- [x] Global Search 분리
- [x] Account state slot 정의
- [x] SectionNavigation 정의
- [x] PageHeading 정의
- [x] Footer sitemap 구조 정의
- [x] Shared navigation source 원칙 정의
- [x] Rhymix layout vs skin 책임 구분
- [x] Mobile 방향성만 유지하고 responsive 상세는 구현 단계로 유보

---

# 17. 다음 단계 — P2

**Generic Board / State Specification**

다음에서 확정할 것:

- Board List
- Board Detail
- Board Write
- Comment
- Pagination
- Search
- Kitel News locked state
- Empty state
- Permission denied
- 게시판별 권한 차이에 따른 UI 노출
- 공유게시판의 community presentation과 generic board 데이터 재사용 경계

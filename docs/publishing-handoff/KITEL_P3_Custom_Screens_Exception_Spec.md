# KITEL P3 — Custom Screens / Exception Specification

상태: Working baseline  
목적: 표준 게시판 공통 규칙(P2)으로 해결되지 않는 화면을 **Figma ↔ 현재 Rhymix 구현 ↔ 권한 ↔ 상태 ↔ interaction hook** 기준으로 1:1 매핑한다.

기준:
- Figma copy: `0VXi5IY64M8jRJOsc1kX2p`
- `docs/new-site-ia.md`
- `docs/board-feature-specs.md`
- `docs/frontend-skin-architecture.md`
- 현재 `custom_modules/`, `custom_widgets/`, `custom_skins/`

---

# 1. P3 분류

```text
Custom / Exception
├─ Calendar
├─ Archive
├─ Homework
│  ├─ Member-facing
│  └─ Technical-department admin
├─ Exhibition
├─ Suggestions
├─ Home widget composition
└─ Account
   ├─ Login
   ├─ Signup
   ├─ Signup Complete
   └─ My Page
```

공통 원칙:

1. 기존 backend 기능을 Figma 모양에 맞추기 위해 제거하지 않는다.
2. Figma에 존재하지만 backend가 지원하지 않는 상호작용은 자동으로 구현된 것으로 간주하지 않는다.
3. backend에 존재하지만 Figma에 빠진 상태는 구현 명세에서 별도로 유지한다.
4. Figma의 절대 좌표는 최종 CSS layout contract가 아니다.
5. 이미지 콘텐츠는 placeholder이며 slot geometry만 참고한다.

---

# 2. Calendar — About > 일정

## 2.1 Figma

대표 full page:
- `176:1341` / Frame 62

확인된 UI 방향:

```text
About side navigation

일정

[연도] [월] [월간]
[이전/다음 navigation]
[월간 calendar grid]
[+ 추가]  # 권한 사용자

월간 일정 요약 / 목록

[연도] [월] [주간]
[주간 calendar]
[+ 추가]

주간 일정 요약 / 목록
```

Figma에서는:
- 60px/70px 수준의 큰 월 표시
- 월간/주간 selector
- 날짜 cell
- event bar
- 행사 목록
- 추가 버튼

이 확인됨.

## 2.2 현재 backend

Public:
- `custom_modules/calendar/calendar.view.php`
- `custom_modules/calendar/skins/default/list.html`

Admin:
- `custom_modules/calendar/tpl/event_list.html`
- `custom_modules/calendar/tpl/event_form.html`

현재 public controller가 제공하는 핵심 context:

```text
cur_year
cur_month
month_title
prev_year
prev_month
next_year
next_month
weeks
weekday_names
is_manager
```

각 day:

```text
day
date
is_today
events[]
```

event form:

```text
title
start_date
end_date
location
category
description
```

## 2.3 Permission

```text
전체 방문자    조회
admin          등록 / 수정 / 삭제
```

현재 구현은 grant의 `manage`를 `is_manager`로 전달.

## 2.4 구현 계약

공개 Calendar screen은 Figma visual language를 적용하되:

- 실제 `weeks` / `day->events` 데이터를 사용
- 오늘 날짜 강조
- 이전/다음 달 navigation 유지
- `is_manager`일 때만 관리/추가 affordance 노출
- event를 클릭했을 때 상세 내용을 볼 수 있는 interaction 필요
  - 현재 public skin은 event link가 `#`이므로 실제 상세 UX는 구현 시 보완 대상

## 2.5 상태

필수:

```text
Month with events
Month with no events
Today
Multi-day event
Admin controls visible
Read-only visitor
```

## 2.6 중요한 구현 gap

### Figma
`월간 / 주간` 두 presentation을 명확히 암시.

### 현재 backend
public view는 **월간 grid만 구현**되어 있음.

따라서 Codex는 Figma만 보고 주간 기능이 이미 backend에 있다고 가정하면 안 된다.

처리 원칙:

```text
P3 handoff 시점:
- Month view = 구현 가능한 확정 범위
- Week view = backend extension 필요 표시
```

주간 뷰를 실제로 살릴 경우 Calendar module의 view/model/controller 작업이 추가된다.

---

# 3. Archive — Study > 자료실

## 3.1 역할

NAS Drive 스타일의 정회원 전용 파일 브라우저.

## 3.2 Figma 상태

현재 확인한 완성형 Figma 페이지 중 **자료실 전용 production mockup은 명확히 매핑되지 않았다.**

따라서 자료실은:
- P0/P1 디자인 시스템
- Study SectionNavigation
- 현재 backend 기능 구조

를 기준으로 Codex가 스타일을 적용하고,
시각 QA 단계에서 별도 refinement 대상으로 둔다.

즉 Figma에 없는 UI를 임의로 화려하게 재설계하지 않는다.

## 3.3 Backend

- `custom_modules/archive/archive.view.php`
- `custom_modules/archive/skins/default/list.html`

Context:

```text
cur_folder_srl
breadcrumb[]
folders[]
files[]
is_manager
```

Folder:
```text
folder_srl
name
regdate
```

File:
```text
file_srl
source_filename
size_label
regdate
download URL/action
```

현재 기능:

```text
root/subfolder navigation
breadcrumb
folder creation
file upload
file download
file delete
recursive folder delete
empty folder
```

## 3.4 Permission

현재 설계:
- 정회원 이상 접근
- manage grant 보유자가 생성/업로드/삭제

정확한 삭제 공동관리 정책은 운영 정책과 backend grant를 우선.

## 3.5 화면 구조 계약

```text
ArchivePage
├─ SectionNavigation(Study)
├─ PageHeading(자료실)
├─ ArchiveToolbar
│  ├─ NewFolder
│  └─ Upload
├─ Breadcrumb
└─ ArchiveList
   ├─ FolderRow
   └─ FileRow
```

## 3.6 상태

```text
Root folder
Nested folder
Empty folder
Files + folders
Upload available
Read-only state(if future grant changes)
Upload error
Delete confirmation
```

## 3.7 Interaction hooks

절대 제거하지 말 것:

```text
dispArchiveIndex
folder_srl
procArchiveInsertFolder
procArchiveUploadFile
procArchiveDownloadFile
procArchiveDeleteFile
procArchiveDeleteFolder
```

---

# 4. Homework — Study > 과제게시판

과제는 일반 게시판 skin이 아니라 custom module이다.

---

## 4.1 과제 목록

### Backend

- `custom_modules/homework/homework.view.php`
- `custom_modules/homework/skins/default/index.html`

Context:

```text
tasks[]
is_submitter
can_view_all
```

준회원일 경우 각 task:
```text
my_status
  미제출
  제출완료
  제출완료(지각)
```

### UI 계약

```text
AssignmentList
├─ SectionNavigation
├─ PageHeading
└─ TaskRows
   ├─ title
   ├─ deadline
   └─ myStatus (submitter only)
```

### 상태

```text
No assignments
Open assignment
Submitted
Late submitted
Past deadline
```

---

## 4.2 과제 상세

### Figma
- `176:2574` / Frame 69

확인된 visual:

```text
D-10
과제 이름
[제출하기]

제출 과제 목록
[search]
[list]
```

### Backend
- `dispHomeworkView()`
- `skins/default/view.html`

Context:

```text
task
my_submission
all_submissions
is_submitter
can_view_all
is_past_deadline
```

### Permission-dependent presentation

#### 준회원

```text
과제 내용
내 제출 상태
내 제출/재제출 UI
```

다른 준회원 제출물 노출 금지.

#### 정회원 이상

```text
과제 내용
전체 제출물 열람
```

#### 기술부/admin

일반 조회 + 별도 관리 기능.

## 4.3 과제 제출

### Figma
- `176:2575` / Frame 70

Visual:

```text
D-day / task title
과제 제출

title-like input area
attachment
content editor
[등록]
```

### Backend reality

현재 `view.html` 안에서 submitter form이 렌더링되는 구조:

```text
content textarea
Filedata
submit / resubmit
```

Figma처럼 별도 페이지로 보이지만 backend는 현재 detail view 내부 form 중심이다.

### 구현 판단

Codex는 두 선택 중 현재 backend를 최소 변경하는 쪽을 우선:

```text
A. Figma visual을 동일 detail route 내부의 submission section으로 표현
B. 별도 submission route가 꼭 필요하면 controller/view extension
```

라우트 분리는 backend 변경이므로 단순 CSS 작업으로 처리하지 않는다.

---

# 5. Homework — 기술부 관리 화면

## 5.1 Figma

- `41:631`

사용자 확인:
**과제게시판의 관리자/기술부용 대시보드이며 필수.**

Figma visual:

```text
D-10 + 과제명

제출 인원        21
미제출 인원      21

제출 과제 목록
[rows]
```

즉 **특정 과제 중심 summary dashboard**에 가깝다.

## 5.2 Backend

- `homework.admin.view.php`
- `tpl/dashboard.html`

현재 backend dashboard는:

```text
과제 × 준회원 제출현황 matrix
```

Context:

```text
tasks[]
rows[]
```

각 row:
```text
member
cells[]  # task별 submission/null
```

상태:
```text
미제출
제출
지각
```

## 5.3 중요한 Figma ↔ backend 차이

둘은 같은 목적이지만 presentation level이 다르다.

```text
Figma:
특정 과제 summary
- 제출 수
- 미제출 수
- 해당 과제 제출 목록

Backend:
전체 과제 × 전체 준회원 matrix
```

따라서 Codex가 단순 CSS로 `dashboard.html`을 Figma처럼 바꾸면
**backend가 가진 matrix 기능을 잃을 수 있다.**

## 5.4 권장 통합

기능 손실 없이:

```text
HomeworkAdmin
├─ TaskSummaryView     # Figma 41:631
└─ SubmissionMatrix    # 현재 backend dashboard
```

또는 한 페이지 안에서:

```text
[selected task summary]
[submission matrix / 전체 현황]
```

형태.

정확한 최종 UX는 구현 단계에서 backend와 함께 조정하되,
**matrix 기능 삭제 금지**.

## 5.5 Permission

`homework.admin.view.php` 자체가 기술부 전용 관리 뷰로 작성되어 있다.

접근:
- 기술부
- admin

일반 임원진/정회원/준회원에게 노출하지 않는다.

## 5.6 추가 admin 화면

현재 backend:

```text
task_list.html
task_form.html
dashboard.html
```

Task form fields:

```text
title
deadline
description / 제출 양식 안내
```

Figma에 완전한 대응 mockup이 없더라도 기능은 유지해야 한다.

---

# 6. Exhibition — Study > 작품전시회

## 6.1 Figma

- `176:2945` / Frame 71

Visual:

```text
작품전시회 게시판
year selector/list header
gallery card grid

card:
image
title
author
date
```

Figma bitmap은 placeholder이며 실제 작품 이미지가 dynamic하게 들어간다.

## 6.2 Backend

표준 board + custom skin:
- `custom_skins/board/kitel_gallery/list.html`
- `_read.html`
- `write_form.html`

현재 list already supports:

```text
image extraction from uploaded files
title
plain-text description
author
date
pending/secret status
pagination
search
write
```

## 6.3 Product policy

고정 fields:

```text
작품명
작품 사진
간단 설명
제안서
```

Flow:

```text
정회원 작성
→ 검토중
→ admin 승인
→ 전체공개
```

- 기술부: 조사/운영 관련 권한
- 최종 승인: admin
- 댓글 기능: 사용하지 않음

## 6.4 Codex implementation rule

현재 gallery skin에 `_comment.html`, `comment_form.html` 파일이 존재하더라도
**신규 작품전시회 UI에 댓글 기능을 노출하지 않는다.**

Generic board에서 재사용 가능한 것:
- pagination
- search
- attachment primitives
- write form primitives

전용:
- GalleryCard
- pending state
- fixed exhibition fields
- approval/review affordance

## 6.5 States

```text
Public approved
Pending / review
Empty gallery
No image fallback
Write form
Review/admin state
```

## 6.6 Figma와 backend 차이

Figma card는 이미지 중심으로 충분히 맞는다.
현재 backend gallery skin도 card grid이므로 구조적 충돌은 작다.

다만:
- Figma의 연도 필터처럼 보이는 UI가 실제 backend에 존재하는지는 별도 확인 필요
- 없는 filter를 단순 디자인 때문에 가짜 UI로 만들지 않는다

---

# 7. Suggestions — 지원 > 건의사항

## 7.1 Figma

- `178:1123` / Frame 72

사용자 확인된 최신 UX:

```text
SuggestionPage
├─ Inline composer
└─ 내가 한 건의
```

즉 별도 `write` page로 이동하지 않는다.

Figma visual:

```text
건의사항
게시글 쓰기
category
title
editor
등록

공개 설정
- 전체 공개
- 비공개

내가 한 건의
- 분류
- 제목
- 날짜
- 답변여부
```

## 7.2 Backend policy

기존 설계:

```text
정회원 작성
준회원 작성 불가
본인 글 본인만 재조회
임원진 전체 조회
anonymous option
DB에는 작성자 유지
익명 선택 시 임원진에게도 익명
admin 로그로만 필요 시 식별
```

Rhymix board의 상담 기능/익명 기능으로 해결 가능하다는 검증 기록 존재.

## 7.3 중요 mismatch

Figma의:

```text
전체 공개 / 비공개
```

는 현재 확정 정책과 정확히 맞지 않는다.

건의사항은 본질적으로 **비공개 운영 채널**이다.

따라서 Codex는 이 radio group을 그대로 구현하지 않는다.

우선 계약:

```text
public/private selector → 제거 또는 정책에 맞는 control로 대체
anonymous checkbox      → 필요
```

Figma의 category select는 board category를 실제 사용할 경우 유지 가능.
카테고리 정책이 없다면 selector를 빈 UI로 만들지 않는다.

## 7.4 Composite

```text
SuggestionComposer
├─ Category(optional)
├─ Title
├─ Anonymous
├─ Editor
└─ Submit

MySuggestionList
├─ category
├─ title
├─ date
└─ responseStatus
```

## 7.5 Role-specific list

정회원:
```text
본인 건의만
```

임원진:
```text
전체 건의
```

admin:
```text
전체 + 관리
```

UI에서 같은 list component를 사용하되 dataset scope가 권한별로 다름.

---

# 8. Home — Widget composition

Home은 하나의 고정 HTML 페이지가 아니라 **Rhymix widget page**로 유지한다.

---

## 8.1 Hero

### Figma
- `6:93` / `slideimage`
- assembled Home에도 포함

Visual:
- full-width media
- large headline
- subtext
- down arrow
- Figma에는 좌측 carousel indicator처럼 보이는 dots 존재

### Backend
- `custom_widgets/hero_banner`
- `hero_banner.class.php`
- `skins/default/hero_banner.html`

현재 args/context:

```text
headline
subtext
bg_image
cta_text
cta_url
```

### Important mismatch

현재 backend 문서/코드는 **캐러셀 없이 단일 고정 배너**로 명시되어 있음.

따라서 Figma의 여러 dot를 보고 carousel backend를 새로 만들지 않는다.

P3 baseline:
- single hero
- dynamic background
- headline/subtext/optional CTA
- image replaceable
- scroll-down affordance는 frontend-only로 추가 가능

---

## 8.2 Upcoming Events

Backend:
- `custom_widgets/upcoming_events`

Args:

```text
event_count
days_ahead
calendar_module_srl
```

Context:

```text
events[]
calendar_url
```

Current UI data:

```text
start month/day
event title
location
```

States:

```text
events available
no upcoming events
```

Figma Home의 일정 관련 카드/section에 이 widget 데이터를 연결한다.

---

## 8.3 Latest News / Exhibition highlight

Backend strategy:
- Rhymix Content widget 재사용

UI:
- Home Figma의 card/feature areas를 skinning

원칙:
- 데이터 개수/section 순서를 markup에 하드코딩하지 않는다.
- widget configuration으로 가능한 값은 admin setting 유지.

---

## 8.4 Home visual sections

Figma에는:
- 공지사항
- 과제현황
- large feature card
- small feature cards
- 3-card section
- section titles

등 다양한 placeholder section이 있음.

모든 placeholder를 새 backend 기능으로 해석하지 않는다.

Home 구현 시:
1. 실제 존재하는 widget/data source를 먼저 매핑
2. 대응 backend가 없는 purely promotional block만 static/configurable content로 처리
3. placeholder image/content는 운영자가 교체 가능하도록 설계

---

# 9. Account — Login

## Figma
- `19:166`

Visual:

```text
Login panel
ID
PW
로그인 유지
ID/PW 찾기
회원가입
Login button
```

## Backend

신규 custom module이 아니라 Rhymix core member/session 기능에 연결.

Codex rule:
- Figma form만 새로 만들고 독자적인 login logic을 만들지 않는다.
- Rhymix login action / CSRF / validation / redirect contract 유지.
- 기존 core form field name/action을 먼저 확인 후 마크업 적용.

## States

```text
default
invalid credentials
validation error
remember login
logged-in redirect
```

---

# 10. Account — Signup

## Figma
- `148:897`

보이는 fields:

```text
이름
닉네임
기수
이메일
비밀번호
회원가입
```

## Existing migration context

Legacy member custom fields로:
- 기수
- 전화번호
- 주소

가 확인된 바 있음.

### Important rule

Figma에 없는 field가 backend membership schema에 존재한다고 해서
Codex가 임의로 삭제하면 안 된다.

반대로 과거 필드라는 이유만으로 UI에 자동 추가하지도 않는다.

구현 시:
1. 현재 Rhymix member signup configuration 확인
2. 실제 필수/활성 fields를 authoritative source로 사용
3. Figma styling을 field renderer에 적용

`기수`는 동아리 운영상 핵심 field로 유지 대상으로 본다.

---

# 11. Account — Signup Complete

## Figma
- `46:842`

Visual:

```text
success icon
"22기 OOO님
회원가입이 성공적으로 완료되었습니다"

[Home]
[My Page]
```

이 화면은 성공 state.

주의:
가입 직후 계정이 `가입대기` 상태일 수 있으므로
실제 copy가 "즉시 모든 기능 사용 가능"을 암시하지 않도록
회원 승인 workflow와 맞춰야 한다.

필요 시:

```text
회원가입이 완료되었습니다.
승인 후 회원 기능을 이용할 수 있습니다.
```

같은 상태 copy를 구현 단계에서 적용.

---

# 12. Account — My Page

## Figma
- `51:1279`

Visual:

```text
마이페이지

내 정보
회원명
닉네임
기수
이메일
비밀번호
[수정] [저장]

최근에 쓴 글
[board row]
```

## Contract

`MyProfileForm`
- editable vs read-only state 분리
- password는 기존 값을 화면에 그대로 노출하면 안 됨
- 변경 flow로 처리

`RecentPosts`
- 현재 로그인 사용자 기준
- generic BoardRow primitive 재사용 가능

## Security rule

비밀번호 field는 Figma처럼 "현재 비밀번호 값을 보여주는 input"으로 구현하지 않는다.
Rhymix의 password change semantics를 따른다.

---

# 13. Custom-screen state matrix

| Screen | Core states |
|---|---|
| Calendar | events / empty / today / admin |
| Archive | root / nested / empty / upload / error |
| Homework list | empty / unsubmitted / submitted / late |
| Homework detail | submitter / viewer-all / deadline-passed |
| Homework admin | summary / matrix / no tasks / no juniors |
| Exhibition | approved / pending / empty / no-image |
| Suggestion | compose / own-list / staff-list / anonymous |
| Hero | content / no-config |
| Upcoming events | list / empty |
| Login | default / error |
| Signup | default / validation / success |
| My Page | view / edit / save error / recent-posts empty |

---

# 14. Backend interaction hooks — preserve list

## Calendar
```text
dispCalendarIndex
dispCalendarAdminContent
dispCalendarAdminForm
procCalendarAdminInsertEvent
procCalendarAdminDeleteEvent
```

## Archive
```text
dispArchiveIndex
procArchiveInsertFolder
procArchiveUploadFile
procArchiveDownloadFile
procArchiveDeleteFile
procArchiveDeleteFolder
```

## Homework
```text
dispHomeworkIndex
dispHomeworkView
procHomeworkSubmit

dispHomeworkAdminContent
dispHomeworkAdminForm
dispHomeworkAdminDashboard
procHomeworkAdminInsertTask
```

## Board/Exhibition/Suggestion
Rhymix board actions / grants / document/comment lifecycle 유지.

## Widgets
```text
hero_banner args
upcoming_events args
Rhymix content widget config
```

---

# 15. Gaps requiring implementation-time handling

아래는 디자인 문제가 아니라 **Figma와 현재 backend 사이의 차이**다.

## Gap A — Calendar week view
Figma O / backend public implementation X.

## Gap B — Homework admin presentation
Figma = per-task summary  
backend = task × junior matrix.

둘 다 가치가 있으므로 기능 손실 없이 통합.

## Gap C — Homework submission route
Figma = 별도 작성 화면처럼 표현  
backend = task detail 안 submit form.

필요하면 backend route extension.

## Gap D — Archive mockup
backend O / 명확한 Figma final screen 미확인.

P0/P1 system으로 구현 후 QA.

## Gap E — Suggestion public/private
Figma control이 current policy와 충돌.
정책 우선.

## Gap F — Hero carousel indicators
Figma visual 암시 O / backend carousel X.
single hero를 baseline으로 유지.

## Gap G — Account fields
Figma field set와 legacy/current Rhymix member schema가 완전히 일치한다고 가정 금지.

---

# 16. Codex 실행 원칙

P3 화면 작업 시 Codex는 각 화면에 대해 반드시:

```text
1. Figma target node 확인
2. 현재 backend view/template 확인
3. 전달되는 variable/context 목록 확인
4. permission condition 확인
5. 기존 action/form name 보존
6. 마크업/CSS 교체
7. 상태별 렌더 확인
8. desktop visual 비교
9. 이후 responsive 설계/QA
```

순으로 진행.

금지:

- Figma를 보고 fake data로 backend를 대체
- 기존 action을 JS mock handler로 바꿈
- 관리자 기능 삭제
- hidden permission state를 모든 사용자에게 표시
- Figma placeholder를 실제 콘텐츠로 하드코딩
- custom module을 일반 board로 임의 변환

---

# 17. P3 완료 상태

- [x] Calendar Figma/backend mapping
- [x] Archive backend/UI contract
- [x] Homework user-facing mapping
- [x] Homework technical-department admin mapping
- [x] Exhibition mapping
- [x] Suggestion mapping
- [x] Home widget mapping
- [x] Login mapping
- [x] Signup mapping
- [x] Signup complete mapping
- [x] My Page mapping
- [x] Figma/backend gaps identified
- [x] interaction hook preservation rules

---

# 18. 다음 단계 — P4 Codex Handoff Package

다음 단계는 새 디자인 분석이 아니라 지금까지의 문서를 하나의 구현 계약으로 묶는 작업이다.

포함 대상:

```text
IA v3
P0 Design Foundation
P1 Shared Layout
P2 Generic Board / States
P3 Custom Screens
Figma node map
GitHub target file map
Implementation order
Do / Don't rules
Visual QA checklist
Responsive delegation brief
```

P4 결과물은 Codex 새 세션/프로젝트에 그대로 넣을 수 있는 **실행용 handoff prompt + implementation checklist** 형태로 만든다.

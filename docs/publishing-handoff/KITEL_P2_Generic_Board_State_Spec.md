# KITEL P2 — Generic Board / State Specification

상태: Working baseline  
목적: Codex 구현 전에 Rhymix 표준 게시판 계열의 공통 화면 구조와 상태 분기를 확정한다.  
기준: 최신 Figma + `new-site-ia.md` + `board-feature-specs.md` + 현재 `kitel_gallery` 스킨 구조

---

# 1. 범위

이 문서에서 공통화 대상으로 보는 화면:

```text
BoardList
BoardDetail
BoardWrite
CommentList
CommentComposer
BoardSearch
Pagination
BoardLockedState
BoardEmptyState
PermissionDeniedState
```

직접 적용 대상:

- Kitel News 3종
- 세미나 게시판
- 일반적인 board 계열
- 공통 상세/작성 화면

부분 재사용 대상:

- 공유 게시판
- 작품전시회
- 건의사항
- 과제 관련 보조 게시판

완전 별도 대상:

- 자료실
- 캘린더
- 과제 custom module 핵심 화면

---

# 2. 공통 게시판 아키텍처

```text
BoardPage
├─ SectionNavigation
├─ PageHeading
├─ BoardToolbar
│  ├─ WriteAction
│  └─ BoardSearch
├─ BoardList
├─ Pagination
└─ BoardState
```

상세:

```text
BoardDetailPage
├─ SectionNavigation
├─ PostHeader
├─ AttachmentList
├─ PostBody
├─ PostActions
├─ CommentComposer
└─ CommentList
```

작성:

```text
BoardWritePage
├─ SectionNavigation
├─ WriteHeader
├─ CategorySelect
├─ TitleField
├─ AttachmentField
├─ Editor
├─ Visibility/OptionControls
└─ SubmitActions
```

---

# 3. Board List

## 3.1 Figma reference

대표:
- `176:2355` — 세미나 게시판
- `176:1781` / `176:1958` / `176:2135` — Kitel News
- 공통 row instance: `Group 68`

Figma 관찰:

```text
page title: 45px Medium
page description: 24px Light / muted
content width: 1160px
search: 255 × 40
write button: 92 × 40 pill
header row: 약 51px
content row: 75px
pagination: 36px
```

## 3.2 Desktop row fields

기본 공통:

```text
제목
작성자
날짜
조회수
분류
```

게시판별 필요 없는 열은 숨길 수 있다.

예:
- News: 제목 / 날짜 / 조회수 / 분류
- Seminar: 제목 / 날짜 / 조회수 / 분류
- 어떤 board는 작성자 노출 가능

## 3.3 Component responsibility

`BoardRow`는 아래 데이터만 받는다.

```text
title
author
date
views
category
isNotice
isLocked
commentCount
href
```

특정 board name에 종속된 markup을 넣지 않는다.

## 3.4 Rhymix mapping

기본적으로 다음과 대응:

```text
title        → $document->getTitleText()
author       → $document->getNickName()
date         → $document->getRegdate(...)
views        → readed_count
category     → category_srl / category_list
comments     → comment_count
notice       → notice_list or is_notice
```

현재 gallery skin의 `document_list`, `notice_list`, `$grant` 사용 패턴은 유지 가능.

---

# 4. Board Toolbar

구조:

```text
BoardToolbar
├─ WriteButton   # 권한 있을 때만
└─ BoardSearch
```

## WriteButton

Figma:
- primary pill
- 92 × 40 reference
- primary blue

표시 조건:

```text
$grant->write_document
```

또는 각 custom module에서 동등한 permission flag.

권한이 없으면 disabled button을 보여주는 것보다 **기본적으로 숨긴다**.

단, UX상 로그인 유도 목적이 있는 화면은 구현 단계에서 별도 상태 가능.

## BoardSearch

- 전역 검색과 분리
- 현재 board 안에서만 검색
- Rhymix `search_target`, `search_keyword` 재사용
- 모바일 상세 동작은 구현 단계로 유보

---

# 5. Pagination

Figma 기준:

```text
height: 36px
border: #E9E9E9
radius: 4px
gap: 6px
```

Rhymix의:

```text
$page_navigation
$page
$page_no
last_page
```

를 그대로 사용.

원칙:
- 페이지당 항목 개수와 pagination 계산은 백엔드 유지
- 퍼블리싱은 표현만 교체
- `First / Back / 숫자 / Next / Last` 구조 지원

---

# 6. Board Detail

## 6.1 Figma reference

`176:2573`

Figma 구조:

```text
게시글 제목
────────────────
작성자    날짜    조회수    댓글
────────────────
첨부파일
────────────────

본문 영역

댓글
[ 댓글 입력 ]

[ 댓글 ]
[ 답글 ]
```

## 6.2 PostHeader

필드:

```text
title
author
date
views
commentCount
category(optional)
```

Figma title:
- 약 30px Medium

meta:
- 16px Light

## 6.3 AttachmentList

Figma에 별도 첨부파일 row 존재.

Rhymix:
```text
$oDocument->hasUploadedFiles()
$oDocument->getUploadedFiles()
```

원칙:
- 첨부 없음 → 영역 자체 숨김
- 첨부 있음 → 파일명, 크기 등 표시
- 다운로드 로직은 기존 Rhymix 사용

## 6.4 PostBody

Figma의 회색 영역은 **본문 placeholder**로 해석한다.

실제 구현:

```text
$oDocument->getContent(false)
```

로 렌더링.

중요:
- Figma bitmap/placeholder를 본문 디자인으로 고정하지 않는다.
- HTML content, 이미지, 링크, 표 등이 들어와도 레이아웃이 견딜 수 있어야 함.

---

# 7. Post Actions

기본:

```text
목록
수정
삭제
```

Rhymix 조건:

```text
$oDocument->isEditable()
$grant->manager
```

등 기존 로직 유지.

디자인:
- primary / secondary action hierarchy 적용
- 모든 사용자에게 수정/삭제 UI가 보이면 안 됨

---

# 8. Comments

## 8.1 Figma reference

`176:2573`

댓글 구성:

```text
작성자
날짜
본문
답글달기
```

답글은 indentation/reply icon으로 계층 표현.

## 8.2 CommentComposer

Figma:
- muted soft input
- 약 964 × 80 reference
- radius 20px

실제 Rhymix에서는 editor/component 구조가 존재할 수 있으므로:
- Figma text box를 단순 `<input>`으로 대체하지 않는다.
- 기존 comment editor 기능을 유지하고 outer styling을 맞춘다.

## 8.3 Rhymix mapping

현재 `comment_form.html`의:

```text
$oComment->getEditor()
document_srl
comment_srl
parent_srl
notify_message
is_secret
```

구조 유지.

## 8.4 Visibility

댓글 기능 자체가 비활성인 board:
- CommentComposer 숨김
- CommentList 숨김

작품전시회는 현재 정책상 댓글/피드백 기능 없음.

---

# 9. Board Write

## 9.1 Figma reference

`178:1318`

구조:

```text
게시글 쓰기                [등록]
────────────────────────────

분류 선택
제목 입력
첨부파일

[본문 editor]

공개 설정
○ 전체 공개
○ 비공개
```

## 9.2 Figma ↔ Rhymix mapping

```text
분류        → category_srl
제목        → title
첨부파일    → Rhymix editor/file upload
본문        → $oDocument->getEditor()
공개 설정   → status / secret option
등록        → submit
```

## 9.3 Important rule

Figma의 공개 설정 UI를 **모든 board에 강제로 적용하지 않는다.**

board별 policy가 다름:

- News: admin이 작성, 공개범위는 게시판 정책으로 고정
- Seminar: 일반 게시글
- Suggestion: 익명 정책과 본인/임원진 조회 정책이 핵심
- Exhibition: `SECRET` 상태가 승인 workflow에 사용될 수 있음

따라서 Write screen은:

```text
Common fields
+
Board-specific option slot
```

으로 설계한다.

---

# 10. Board-specific permission matrix

## 10.1 Kitel News

### List
- 비로그인: O
- 가입대기: O
- 준회원 이상: O

### Detail body
- 비로그인: X
- 가입대기: X
- 준회원 이상: O

### Write
- admin only

### Required special state
**Locked detail**

---

## 10.2 Seminar

### Read
- 준회원 이상

### Write
- 정회원 이상

### Data extension
- 발표자
- 발표일자
- 발표자료
- 참고자료

표준 board + extra vars.

---

## 10.3 Sharing / Community

### Read
- 정회원 이상

### Write
- 정회원 이상

### Backend
가능하면 표준 board data model 사용.

### Presentation
일반 table-style list와 별도.

---

# 11. Kitel News Locked State

기존 기능 명세에서 명확히 요구됨.

## 조건

```text
목록은 볼 수 있음
글 제목 클릭 가능
본문 읽기 권한 없음
```

## 화면

`BoardDetail`의 header까지는 유지 가능.

본문 위치에:

```text
LockedContentPanel
├─ Lock/Info icon
├─ "로그인 후 확인할 수 있습니다."
├─ Login CTA
└─ Signup CTA(optional)
```

## 중요

Rhymix의 기본 password/secret document UI와 혼동하지 않는다.

Kitel News는:
- 작성자가 password를 건 비밀글이 아니라
- board level의 **view permission restriction**

이다.

따라서:
`!$grant->view` 혹은 실제 board grant 결과에 맞는 상태로 처리.

Figma에 완성된 Locked state는 현재 확인되지 않았으므로
**P2에서 기능 계약만 정의하고 시각 세부는 Codex 구현/QA에서 기존 style system에 맞춘다.**

---

# 12. Permission Denied State

목록 자체 접근권한이 없는 경우:

예:
- 비회원이 Study > 공유 게시판 직접 URL 접근
- 준회원이 정회원 전용 자료 접근

화면:

```text
PermissionState
├─ page context(optional)
├─ 설명
└─ Login 또는 이전 페이지 action
```

원칙:
- 빈 게시판처럼 보이게 하지 않는다.
- "글이 없습니다"와 "권한 없음"은 명확히 구분.

---

# 13. Empty State

## Standard Board

```text
등록된 게시글이 없습니다.
```

권한이 있으면:
```text
[첫 글 작성]
```

action 가능.

## Sharing

초기 오픈 시 빈 게시판일 가능성이 문서에 명시되어 있음.

따라서 community UI에서도 빈 상태를 별도 디자인해야 한다.

예:

```text
아직 공유된 글이 없습니다.
프로젝트, 스터디 자료, 정보를 공유해보세요.
[글쓰기]
```

정확한 copy는 구현 단계에서 다듬을 수 있음.

---

# 14. Search Empty State

검색을 실행했으나 결과 0:

```text
검색 결과가 없습니다.
검색어를 변경해보세요.
[검색 초기화]
```

일반 Empty와 분리.

---

# 15. Loading / Error State

Rhymix 서버 렌더 중심이라 SPA 수준 skeleton이 필수는 아니다.

그러나 다음은 고려:

- Ajax/동적 기능 추가 시 loading
- 파일 업로드 중
- comment submit
- search
- custom community interaction

P2에서는 시각 component 계약만:

```text
InlineLoading
InlineError
FormError
```

정도로 두고 세부는 구현 단계.

---

# 16. Sharing Board — Community Presentation

## Figma reference

`64:334`

이 화면은 일반 board table보다 **feed/community layout**에 가깝다.

관찰:

```text
상단 title bar: "커뮤니티 게시판"
search
profile/intro area
post list width ~705px
post row height ~75px
right media/widget column
comment icon/count
like icon/count
```

Post item:

```text
제목: 16px Medium
preview: 14px Regular / muted
comment count
reaction count
```

## 설계 판단

공유게시판은:

```text
Standard board backend
+
CommunityList presentation
```

으로 본다.

즉 아래는 재사용:

```text
BoardDetail
BoardWrite
Comment system
Permission logic
Search data
Pagination/data fetching
```

아래는 별도:

```text
CommunityList
CommunityPostRow
CommunityAside
```

이렇게 하면 UI 차이를 유지하면서 board logic을 이중 구현하지 않는다.

---

# 17. Exhibition boundary

작품전시회는 현재 `kitel_gallery` skin에서 이미:

```text
list.html
_read.html
write_form.html
comment_form.html
```

표준 board skin 구조를 기반으로 구현되어 있음.

그러나 제품 정책은:
- gallery list
- fixed fields
- approval
- comments 없음

이므로 최종 퍼블리싱에서:

```text
Generic Board primitive 일부
+
Exhibition-specific presentation
```

으로 본다.

특히 현재 기존 gallery skin의 comment 관련 파일이 존재한다고 해서
최종 UI에서 댓글을 노출하면 안 된다.

---

# 18. Suggestion boundary

건의사항은 backend상 표준 board 기능을 최대한 재사용하지만,
최신 UX는 일반 board flow와 다름.

```text
SuggestionPage
├─ inline write form
└─ my suggestion list
```

따라서 P2 Generic Board의 `BoardWritePage`를 그대로 route로 사용하지 않는다.

재사용 가능한 것:
- form primitives
- editor
- anonymous setting
- list row primitives

별도 composite:
- `SuggestionComposer`
- `MySuggestionList`

상세 spec은 Custom/exception pass에서 다룬다.

---

# 19. Rhymix template preservation rules

Codex 구현 시 삭제하면 안 되는 핵심 로직:

```text
$document_list
$notice_list
$page_navigation
$grant
$oDocument
$oComment
$category_list
$search_option
search_target
search_keyword
document_srl
category_srl
```

Conditional examples:

```text
$oDocument->isEditable()
$oDocument->isAccessible()
$oDocument->hasUploadedFiles()
$grant->manager
$grant->view
```

중요:
현재 legacy/default skin이 가진 불필요 기능은 그대로 노출할 필요 없음.
예:
- SNS sharing
- trackback
- tag UI
- guest homepage field

최종 사이트 요구사항에 없는 기능은 Codex가 **무조건 시각적으로 복제하지 말고**, 백엔드 필요성 확인 후 제거/숨김 후보로 분류한다.

---

# 20. UI simplification recommendation

기존 `kitel_gallery`는 오래된 XE/Rhymix 기본 skin 요소를 많이 계승함.

예:

```text
Twitter
Facebook
Delicious
Trackback
Tag search
Guest homepage
```

현재 KITEL 신규 디자인/IA에 필수로 정의된 요소가 아니다.

따라서 Codex handoff에서:

**"기존 템플릿의 모든 UI를 보존하는 것이 목표가 아니라,
필요한 기능 로직을 보존하고 최신 Figma UI에 맞게 표시 범위를 정리한다."**

를 명시한다.

---

# 21. Figma node mapping

| Node | Role |
|---|---|
| `176:2355` | Generic board list example (Seminar) |
| `176:1781` | News list — event report |
| `176:1958` | News list — schedule notice |
| `176:2135` | News list — club celebration |
| `176:2573` | Generic board detail + comments |
| `178:1318` | Generic board write |
| `64:334` | Sharing community list |
| `176:2945` | Exhibition gallery list |

---

# 22. Codex component target

권장 conceptual components:

```text
BoardPageShell
BoardToolbar
BoardSearch
BoardTable
BoardRow
BoardMeta
CategoryBadge
Pagination

PostHeader
AttachmentList
PostBody
PostActions

CommentComposer
CommentList
CommentItem

WriteForm
CategorySelect
TitleField
AttachmentField
EditorShell
BoardOptionSlot

LockedContentPanel
PermissionDenied
EmptyState
SearchEmptyState

CommunityList
CommunityPostRow
CommunityAside
```

실제 Rhymix에서 component system을 어떤 파일 경계로 나눌지는
Codex가 현행 skin/layout 구조를 분석해 정한다.

중복 HTML include/partial로 구현할 수 있다면 이를 우선.

---

# 23. Responsive status

P1 결정 유지:

- exact breakpoint는 구현 단계로 유보
- desktop Figma fidelity 우선
- mobile은 content reflow
- board table은 모바일에서 stacked list로 변환 가능
- community list는 mobile feed 형태로 자연스럽게 전환
- detail/write는 width fluid화

---

# 24. P2 완료 조건

- [x] Generic Board List 구조
- [x] Toolbar/Search/Pagination
- [x] Generic Detail
- [x] Attachments
- [x] Comments/reply
- [x] Generic Write
- [x] Board-specific option slot
- [x] News locked state
- [x] Permission denied
- [x] Empty/Search-empty
- [x] Sharing community UI 경계
- [x] Exhibition generic-board 경계
- [x] Suggestion generic-board 경계
- [x] Rhymix 핵심 변수/조건 보존 규칙
- [x] legacy UI 제거 후보 식별

---

# 25. 다음 단계 — P3

**Custom Screens / Exception Specification**

대상:

```text
Calendar
Archive
Homework
Exhibition
Suggestion
Home widgets
Account screens
```

각 화면마다:
- Figma node
- 현재 backend template
- 권한
- 필수 데이터
- 상태
- 퍼블리싱 시 유지해야 할 interaction hook

을 1:1로 매핑한다.

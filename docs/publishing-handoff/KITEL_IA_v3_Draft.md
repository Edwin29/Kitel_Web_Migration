# KITEL 신규 홈페이지 IA v3 — Figma/기존 설계 통합본

상태: **Working Draft**
작성 기준: 2026-09-29 현재 대화에서 확인된 최신 요구사항 + `Kitel_Web_Migration` 레포 문서 + 최신 Figma 목업

## 0. 문서 우선순위

설계 충돌이 있을 경우 아래 순서로 최신성을 판단한다.

1. 현재 사용자 확인 사항
2. 최신 Figma 목업에서 확인되는 실제 화면 구조
3. `docs/roadmap.md`
4. `docs/new-site-ia.md`
5. `docs/board-feature-specs.md`
6. `docs/designer-brief.md`
7. 레거시 XE 사이트 구조

이 문서는 기존 문서를 폐기하지 않고, **현재 퍼블리싱 기준으로 해석을 통합**하기 위한 문서다.

---

# 1. 최상위 IA

```text
KITEL
├─ Home
├─ About
│  ├─ 키텔 소개
│  ├─ 임원진 소개 및 인사말
│  ├─ 일정
│  └─ 회칙
├─ Kitel News
│  ├─ 동아리 내 경사
│  ├─ 최근 진행 행사 보고
│  └─ 일정 공고
├─ Study
│  ├─ 자료실
│  ├─ 과제게시판
│  ├─ 작품전시회 게시판
│  ├─ 세미나 게시판
│  └─ 공유 게시판
├─ 지원
│  ├─ 건의사항
│  ├─ Contact Us
│  └─ 기자재 대여 시스템
└─ Account
   ├─ Login
   ├─ Signup
   ├─ Signup Complete
   └─ My Page
```

> Figma의 `Else` 표기는 최신 문서 기준으로 **지원**으로 교체한다.

---

# 2. 권한 모델

## 2.1 회원 등급

```text
admin
임원진
정회원
준회원
특별회원(졸업생) ≈ 정회원
가입대기
비로그인 방문자
```

## 2.2 부서 태그

회원등급과 별개로 중첩 적용한다.

- 정보부
- 기술부
- 섭외부
- 총무부
- 미화부

특별 권한이 필요한 부서:

- **정보부**: 사이트 전체 관리, admin 계정 인수인계
- **기술부**: 과제 출제/관리, 과제 제출현황, 작품전시회 운영 관련 권한

---

# 3. 화면/기능별 IA

## 3.1 Home

### 역할
공개 랜딩 페이지.

### 구성
- Header / GNB
- Hero banner
- 공지/과제 현황 또는 주요 콘텐츠
- 최신 소식
- 작품전시회 하이라이트
- 일정 미리보기
- 기타 소개 섹션
- Footer

### 구현 분류
- 공통 Layout
- Rhymix 위젯
- 정적 섹션 혼합

### 이미지 정책
모든 현재 Figma 이미지는 **플레이스홀더**다.
이미지를 교체해도 레이아웃이 유지되는 슬롯으로 구현한다.

---

## 3.2 About

### 키텔 소개
- 전체공개
- 정적 페이지
- admin 수정

### 임원진 소개 및 인사말
- 전체공개
- 프로필/인사말 표시
- admin 수정

### 일정
- 전체공개
- 실제 Calendar custom module 사용
- 월/주 보기
- 이벤트 상세
- 등록/수정/삭제: admin
- Figma는 정적 그림이 아니라 실제 캘린더 데이터 렌더링용 스킨으로 사용

### 회칙
- 전체공개
- 정적 페이지
- admin 수정

---

# 4. Kitel News

세 하위 메뉴는 **동일한 표준 게시판 계열**을 재사용한다.

- 동아리 내 경사
- 최근 진행 행사 보고
- 일정 공고

## 권한

- 목록/제목: 전체공개
- 본문: 준회원 이상
- 작성/수정/삭제: admin

## 필요한 화면

```text
NewsBoardList
NewsBoardDetail
NewsBoardWrite
NewsLockedDetail
```

### 잠금 상태
비로그인/가입대기 사용자가 글을 열면:
- 제목/목록은 보임
- 본문 대신 "로그인 후 확인 가능" 상태 표시

---

# 5. Study

## 5.1 자료실

### 역할
기존 NAS Drive의 정회원 자료 공유 기능을 홈페이지로 이전.

### 권한
- 정회원 이상만 접근
- 폴더 생성 / 업로드 / 다운로드 / 관리

### 화면
표준 게시판이 아니라 **파일 브라우저형 커스텀 화면**.

```text
ArchiveBrowser
├─ Breadcrumb
├─ FolderList
├─ FileList
├─ Upload
└─ FolderCreate
```

### 현재 상태
백엔드 custom module 구현 완료.
퍼블리싱에서는 NAS Drive와 유사한 파일 탐색 UX를 적용.

---

## 5.2 과제게시판

### 역할
준회원 선발 과정의 과제 출제/제출/관리.

### 사용자별 권한

#### 준회원
- 과제 목록 조회
- 제출
- 자신의 제출물만 조회

#### 정회원 이상
- 과제 목록 조회
- 전체 제출물 조회

#### 기술부
- 과제 생성
- 제출 양식 설정
- 제출현황 확인
- 과제 운영

#### admin
- 전체 관리 가능

### 화면

```text
AssignmentList
AssignmentDetail
AssignmentSubmit
AssignmentSubmissionDetail
AssignmentAdminDashboard
AssignmentFormEditor
```

### Figma 확인
- 일반 과제 상세 + 제출물 목록 존재
- 과제 제출 작성 화면 존재
- 관리자/기술부용 제출 현황 화면 존재

### 중요
`AssignmentAdminDashboard`는 **admin 전용이 아니라 기술부 운영 화면**이다.

---

## 5.3 작품전시회 게시판

### 역할
작품 공개 갤러리.

### 작성 고정 필드
- 작품명
- 작품 사진
- 간단 설명
- 제안서 파일

### 권한
- 읽기: 전체공개
- 작성: 정회원
- 작품 운영: 기술부
- 최종 게시 승인: admin

### 상태
```text
작성
→ 검토중
→ 승인
→ 공개
```

### 화면
```text
ExhibitionGallery
ExhibitionDetail
ExhibitionWrite
ExhibitionReview
```

댓글/피드백 기능은 만들지 않는다.

---

## 5.4 세미나 게시판

### 역할
세미나 발표자료 아카이브.

### 권한
- 읽기: 준회원 이상
- 쓰기: 정회원

### UI
일반적인 표형 게시판 사용.

```text
BoardList
BoardDetail
BoardWrite
```

---

## 5.5 공유 게시판

### 역할
정회원 내부 커뮤니티.

과거의 X-File / Drone / RaffeeMachine / IOelec 등 분산된 프로젝트 공간 역할을 흡수.

### 권한
- 읽기: 정회원
- 쓰기: 정회원

### UI
Figma의 `64:334` 화면을 기준으로 **커뮤니티형 리스트 UI** 사용.

일반 표형 게시판과 모양은 다르지만:
- 상세 화면
- 작성 화면
- 댓글
- 게시글 데이터 모델

등은 공통 게시판 인프라를 재사용할 수 있다.

---

# 6. 지원

## 6.1 건의사항

### 최신 UX
기존 문서의 "목록 → 별도 글쓰기" 패턴보다 현재 Figma/사용자 확인을 우선한다.

페이지 진입 즉시:

```text
SuggestionPage
├─ SuggestionForm
└─ MySuggestionList
```

### 권한
- 작성: 정회원
- 준회원: 작성 불가
- 본인 글: 본인 조회
- 임원진: 전체 조회 가능
- admin: 전체 관리

### 익명
기존 설계의 익명 작성 요구사항 유지.

### 확인 필요
Figma에는 `공개 설정: 전체 공개 / 비공개`가 보이지만,
최신 요구사항은 "본인의 건의 목록은 본인만 조회"다.

따라서 퍼블리싱 단계에서는 이 UI를 그대로 구현하지 말고,
백엔드 정책과 맞춰 **익명 선택/공개 정책을 재정리**한다.

---

## 6.2 Contact Us
- 전체공개
- 정적 안내 페이지
- admin 수정

## 6.3 기자재 대여 시스템
- 기존 독립 앱 유지
- 신규 홈페이지는 진입점 제공
- 인증 어댑터만 Rhymix 기준으로 이관
- 별도 앱 내부 UI는 이번 메인 스킨 퍼블리싱 범위와 분리 가능

---

# 7. Account

```text
Login
Signup
SignupComplete
MyPage
```

회원가입 시 동아리 확장 필드:
- 기수
- 전화번호
- 주소

실제 노출/필수 여부는 회원가입 UX 단계에서 별도 확인.

---

# 8. 공통 화면 템플릿

## 8.1 Site Layout

```text
SiteLayout
├─ Header
│  ├─ Logo
│  ├─ GNB
│  ├─ Search
│  └─ AccountActions
├─ PageContent
└─ Footer
```

---

## 8.2 Generic Board

### List
```text
BoardList
├─ PageHeader
├─ Toolbar
├─ Rows
├─ Search
└─ Pagination
```

### Detail
```text
BoardDetail
├─ PostHeader
├─ Attachments
├─ Body
└─ Comments
```

### Write
```text
BoardWrite
├─ Category
├─ Title
├─ Attachments
├─ Body
└─ Submit
```

대상:
- Kitel News
- 세미나 게시판
- 일부 과제 관련 보조 목록

공유게시판은 데이터 구조는 공유하되 리스트 표현은 별도.

---

# 9. Figma 매핑

## 완성 페이지/핵심 프레임

| Node | 역할 |
|---|---|
| `176:861` | Home |
| `176:1043` | About → 키텔 소개 |
| `176:1156` | About → 임원진 소개 |
| `176:1341` | About → 일정 |
| `176:1731` | About → 회칙 |
| `176:1781` | Kitel News → 최근 진행 행사 보고 |
| `176:1958` | Kitel News → 일정 공고 |
| `176:2135` | Kitel News → 동아리 내 경사 |
| `176:2355` | Study → 세미나 게시판 |
| `176:2573` | 공통 게시글 상세 |
| `176:2574` | 과제 상세 + 제출물 목록 |
| `176:2575` | 과제 제출 작성 |
| `176:2945` | 작품전시회 게시판 |
| `178:1123` | 지원 → 건의사항 |
| `178:1234` | 지원 → Contact Us |
| `178:1318` | 공통 게시글 작성 |
| `178:1518` | 지원 → 기자재 대여 |
| `64:334` | Study → 공유 게시판 |
| `41:631` | 과제 기술부/관리자 대시보드 |

## 보류 디자인

`34:213` + `41:314`
- 게시글 / 사진 탭을 분리하는 재사용형 실험 포맷
- 현재 실제 라우트 없음
- 우선 퍼블리싱 대상에서 제외
- 향후 필요 시 재사용

---

# 10. 퍼블리싱 분류

## A. 공통 Layout
- Header
- Footer
- GNB
- Search
- Login/Signup/MyPage 상태

## B. 표준 Rhymix Board Skin
- Kitel News
- 세미나
- 공통 상세
- 공통 글쓰기

필요 파일 개념:
```text
list.html
_read.html
write_form.html
comment_form.html
CSS
JS (필요 최소한)
```

## C. Custom module/page skin
- Calendar
- Archive
- Homework
- Exhibition gallery
- Suggestion combined page
- Home widgets

---

# 11. 이미지/미디어 구현 정책

Figma에 들어 있는 현재 이미지들은 최종 콘텐츠가 아니다.

따라서:

- 현재 비트맵의 내용으로 컴포넌트 이름을 짓지 않는다.
- 이미지 교체가 가능하도록 데이터 기반으로 렌더링한다.
- 크롭/비율/Radius/Overlay 등 **시각 규칙만 Figma에서 가져온다.**
- `object-fit: cover` 또는 동등한 동작을 기본으로 고려한다.

권장 이름:
- HeroMedia
- ArticleCover
- GalleryThumbnail
- ExecutiveProfileImage
- FeatureMedia

---

# 12. 현재 IA에서 남은 작은 결정

IA 자체는 퍼블리싱 착수 가능한 수준으로 확정된 것으로 본다.

남은 것은 IA 재설계가 아니라 세부 정책이다.

1. 건의사항 Figma의 `전체 공개 / 비공개` UI를 유지할지, 익명 선택 UI로 대체할지
2. 작품전시회 간단설명 글자수 제한
3. 작품 제안서 공개 범위
4. 과제 재제출/지각 제출 UI
5. 자료실 미리보기/저장용량 정책
6. 공유게시판 세부 카테고리
7. 캘린더 반복일정/색상 카테고리

이 항목들은 퍼블리싱을 막지 않으며 상태/필드 확장 포인트로 남긴다.

---

# 13. 퍼블리싱 착수 기준

다음 순서로 진행한다.

### P0 — Design foundation
- Colors
- Typography
- Spacing
- Radius
- Border
- Button/Input states
- Responsive width rules

### P1 — Shared layout
- Header
- GNB
- Footer
- Content container

### P2 — Generic board skin
- List
- Detail
- Write
- Comments
- Locked state

### P3 — Static pages
- About
- Contact Us

### P4 — Custom screens
- Calendar
- Archive
- Homework
- Exhibition
- Suggestions
- Community

### P5 — Account screens
- Login
- Signup
- Signup Complete
- My Page

### P6 — Responsive + states
- Mobile
- Empty
- Permission denied
- Locked
- Loading/Error
- Admin/technical-department controls

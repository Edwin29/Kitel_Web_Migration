# Phase 11 새 세션 핸드오프 — 반응형 구현

기준일: 2026-10-01. 기준 구현: `codex/kitel-publishing-phase10` 최신 커밋(초기 구현 `eb58931` 이후 final correction 포함). Phase 10의 완료 기록은 `docs/phase10-account-setup.md`를 확인한다. 같은 세션 또는 새 세션에서 먼저 Git 상태와 원격 최신 커밋을 확인한다. 이 문서는 Phase 11 시작을 위한 현재 상태 요약이며, Phase 11 구현이나 QA 완료 기록이 아니다.

## 문서 우선순위

1. 이 문서와 각 Phase의 최신 적용 기록(`docs/phase*-setup.md`, `docs/phase9-activity-extension.md`, `docs/phase5-archive-deferred.md`)
2. 현재 repository 코드와 로컬 Rhymix의 실제 렌더·설정
3. `docs/publishing-handoff/KITEL_P4_Codex_Handoff.md`의 Phase 11 및 final QA, `KITEL_P1_Shared_Layout_Spec.md`의 모바일 방향, `KITEL_P0_Design_Foundation.md`, `KITEL_Implementation_Checklist.md`
4. `README.md`, `docs/roadmap.md`, 기타 초기 분석 문서는 배경 자료

초기 handoff와 roadmap의 과거 가정은 이후 제품 결정이나 현재 코드보다 우선하지 않는다. 특히 Phase 5 Archive는 `DEFERRED — PRODUCT / MIGRATION POLICY UNRESOLVED`이며, News의 `최근 진행 행사 보고`는 `KITEL 활동`으로 변경되었다. 건의사항의 익명 제출은 사용하지 않고, 작품전시회의 기술부 공개 스위치는 작품 전체에 적용된다. 최상위 About / Kitel News / Study / 지원 메뉴는 링크가 아닌 목차다. Phase 10 회원가입은 홈페이지·블로그 대신 필수 전화번호와 `기수`를 사용하고 닉네임 안내는 `기수_이름`이다. SMS 인증은 아직 없다.

`docs/roadmap.md`에는 퍼블리싱을 디자이너가 진행할 예정이라는 오래된 문장과 운영 웹 PHP 버전의 서로 충돌하는 표기가 남아 있다. Phase 11 범위나 배포 호환성을 그 한 문장/표만으로 판단하지 않는다. 배포 서버 설정을 변경하는 작업은 Phase 11 범위가 아니다.

## 저장소·실행 구조

- Source of truth: `D:\Projects\Kitel_Web_Migration`. 구현은 이 repository에서만 한다.
- 개발 실행본: `D:\rhymix_dev\www\rhymix`; `custom_modules`, `custom_skins`, `custom_widgets`, `custom_layouts`를 `scripts/sync-dev.ps1`로 대응 Rhymix 위치에 동기화한다. 기본 실행은 dry-run이며 `-Apply` 전에 충돌과 대상 매핑을 읽는다. 삭제는 하지 않는다.
- 로컬 사이트: `http://127.0.0.1:8888/` (이 문서 작성 시 HTTP 200 확인). PHP 내장 서버와 MariaDB는 수동 프로세스이며, 재시작 절차는 `docs/dev-environment.md`에 있다. 이 주소를 새 세션에서 다시 확인한다. 앱 미리보기 포트와 혼동하지 않는다.
- PHP: `D:\rhymix_dev\php\php.exe`; 개발 DB 포트는 3307. 설정 스크립트는 정확한 개발 root/DB를 검사하지만 기본 dry-run으로만 먼저 실행한다. 운영 DB/서버는 이 단계에서 수정하지 않는다.
- 기존 로컬 관리자 암호 등 오래된 문서의 계정 정보는 유효하다고 가정하지 않는다. 비밀값을 새 handoff나 커밋에 넣지 않는다.

현재 브랜치에서 `docs/rental-field-survey-20260909/` 및 `kitel_web/xe/` 아래 큰 미추적 파일 묶음이 보인다. Phase 11과 별개의 기존 작업물이다. `git clean`, 전체 `git add .`, 재귀 삭제를 사용하지 말고 Phase 11 파일만 명시적으로 stage한다.

## Phase 11 범위와 출발점

Figma: `https://www.figma.com/design/0VXi5IY64M8jRJOsc1kX2p/KITELwebsite--%25EB%25B3%25B5%25EC%2582%25AC-?node-id=0-1&p=f`. 모바일 방향은 `81:499`(navigation), `81:585`(layout). 데스크톱 Figma는 시각 기준, 모바일 Figma는 방향 기준이다. React/Tailwind 산출물을 복사하지 않고 Rhymix HTML template와 CSS/JS에 맞춘다.

첫 작업은 구현된 화면의 **현재 실제 브라우저 상태를 390 / 768 / 1024 / 1440 / 1920 폭에서 기록**하는 것이다. 필요한 경우 실제 모바일 UA도 사용한다. 수평 넘침, 겹침, 잘린 글/이미지, 작동하지 않는 메뉴·검색·폼을 재현한 뒤 공통 원인부터 수정한다. breakpoint 숫자는 Figma 좌표에서 고정하지 않고 레이아웃이 깨지는 지점에서 정한다. 화면을 일률적으로 축소하지 않고 열·순서·정보 밀도를 재구성한다.

우선 조사할 구현 파일:

| 영역 | 주요 파일 |
|---|---|
| 공통 Header, MegaMenu, SectionNavigation, Footer | `custom_layouts/kitel_site/layout.html`, `css/layout.css`, `css/components.css`, `css/tokens.css`, `js/layout.js` |
| Home | `custom_widgets/hero_banner/skins/default/hero_banner.css`, `custom_widgets/home_dashboard/skins/default/home_dashboard.css`, `custom_widgets/upcoming_events/skins/default/upcoming_events.css` |
| News / Seminar / Sharing / Suggestion board | `custom_skins/board/kitel_generic/kitel-board.css`, `kitel-activity.css`, `kitel-community.css`, `kitel-suggestion.css` |
| Exhibition | `custom_skins/board/kitel_gallery/kitel-gallery.css` |
| Homework / Calendar | `custom_modules/homework/skins/default/homework.css`, `custom_modules/homework/tpl/homework-admin.css`, `custom_modules/calendar/skins/default/calendar.css`, `custom_modules/calendar/tpl/calendar-admin-form.css` |
| Login / Signup / My Page | `custom_skins/member/kitel_member/css/kitel-member.css` |

이미 각 파일에 일부 `@media` 규칙이 있다. 전부 교체하기 전에 실제 깨지는 위치와 공통 토큰·레이아웃 구조를 확인한다. `$content` 내부 skin 스타일을 공통 layout CSS가 침범하지 않게 하고, Rhymix 변수·loop·condition·action·form·permission/grant 로직은 보존한다. Archive는 정책 보류 중이므로 새 UI/권한을 Phase 11에서 설계하지 않는다. About/Contact Us 등 미작성 본문도 Phase 11에서 임의로 채우지 않는다.

## 검증 매트릭스

- 폭: 390 / 768 / 1024 / 1440 / 1920. 대표 화면마다 DOM 수평 넘침뿐 아니라 시각적 겹침·내용 잘림과 브라우저 콘솔 오류를 확인한다.
- 공통: 비로그인·로그인 Header, 모바일 메뉴 열기/닫기와 키보드 Escape·focus, MegaMenu, section navigation의 News/Study 가로형과 About/지원 배너·세로형, Footer, 검색.
- Home: Hero 첫 화면 높이, 공지/과제, KITEL 활동의 editorial 배치, 새로운 작품 카드, 바로가기, 일정. 로컬 `[DEMO]` 활동 데이터는 운영 데이터가 아니며 관리 파일은 `docs/phase9-activity-extension.md`를 따른다.
- 게시판: News 목록/상세/쓰기, Seminar, Sharing, Suggestion, Exhibition 2열 모바일 카드 및 연도·공개 스위치, 댓글·첨부·검색·페이지네이션. Anonymous/권한 없는 상태도 확인한다.
- 커스텀: Homework 제출 폼·대시보드 표, Calendar 월간 화면·관리 폼. 표는 작은 화면에서 조작 가능해야 한다.
- 계정: 로그인, 전화번호·기수 가입 폼, 가입 완료, My Page, 정보 수정. 실제 Android UA에서 KITEL 공통 셸이 렌더되는지도 확인한다.
- 접근성 기본: focus-visible, 터치 대상 크기, aria-expanded/accessible name, 키보드 조작, reduced motion. 기능 QA는 시각 QA와 별도로 기록한다.

테스트 계정·게시글·파일 fixture는 로컬에만 만들고 정리 경로를 기록한다. 보호된 콘텐츠나 첨부파일의 권한은 반응형 수정으로 우회하지 않는다. `docs/phase6-homework-setup.md`의 출력 escaping과 다운로드 경로, Phase 2의 locked/not-found 계약을 유지한다.

## 새 세션 첫 순서

1. 이 파일과 P4의 Phase 11, P1의 모바일 방향, Phase별 적용 기록을 읽는다. Figma 모바일 노드와 현재 데스크톱 화면을 확인한다.
2. `git status --short --branch`, `git log -1 --oneline`으로 기준점을 확인한다. Phase 10 원격 브랜치 최신을 확인한 뒤 `codex/kitel-publishing-phase11` 브랜치를 만든다. 기존 미추적 자료는 그대로 둔다.
3. 로컬 사이트 응답과 실제 Rhymix root를 확인하고 `scripts/sync-dev.ps1 -DryRun`으로 repository↔runtime 차이를 읽는다. 충돌이 있으면 원인을 조사한 뒤 동기화한다.
4. 위 검증 매트릭스로 baseline 스크린샷·문제 목록을 만든다. 구조적 오류부터 공통 파일에서 수정하고 페이지별로 회귀 확인한다.
5. 수정은 repository에서 수행하고 별도 sync로 로컬에 적용한다. 5개 폭과 기능·권한 상태를 재검증하고 반응형 결정·남은 차이·스크린샷을 문서화한다.
6. Phase 단위 작업 후 커밋하고 HTTPS 원격 브랜치로 푸시한다. Archive 제품 정책이나 데이터 이관, 운영 배포는 자동으로 진행하지 않는다.

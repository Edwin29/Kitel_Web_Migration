# Phase 9 — Home 위젯 구성

## 구조

`index`(로컬 `module_srl=49`)는 Rhymix `WIDGET` 페이지를 유지한다. 페이지에는 기존 `hero_banner`, 새 `home_dashboard`, 기존 `upcoming_events` 위젯을 배치한다. 화면 본문을 공통 layout에 하드코딩하지 않는다. repository의 `scripts/phase9-home-page.html`이 위젯 배치 원본이며 `scripts/configure-phase9-home-dev.php`가 현재 Hero/일정 위젯의 관리자 설정 태그를 보존해 로컬 페이지에 적용한다. 기존 Rhymix 기본 환영 위젯은 화면 배치에서 제외하되, 원본 문서와 백업은 보존한다.

- Hero: 관리자가 설정하는 단일 배너의 headline/subtext/image/CTA를 그대로 사용한다. Figma 배경 비트맵은 임시 이미지이므로 코드에 고정하지 않았다. 배경 이미지가 없으면 남색 계열 CSS 배경을 사용한다. 캐러셀을 만들지 않았으며, Figma의 반복 dot도 표시하지 않는다. Header 아래 실제 시작 위치를 측정하여 히어로가 첫 화면 하단까지 채워지게 한다.
- 공지사항: News의 최신 공개 문서 제목만 표시한다. News의 상세 권한은 기존 board가 판정한다.
- 과제현황: Homework의 기존 grant가 목록을 허용하는 로그인 회원에게만 표시 중인 과제 제목을 읽는다. 제출 권한이 있으면 본인의 제출 여부만 추가한다. 비로그인 사용자에게 과제 제목을 노출하지 않는다.
- KITEL 활동: News 카테고리 170의 공개 글을 공지사항/과제현황 아래, 작품 카드 위에 표시한다. 공지사항 카드에서는 활동 카테고리를 제외한다. 썸네일은 작성자가 선택한 이미지이며, 공개 소개가 없을 때 본문 일부는 News 읽기 권한이 있는 회원에게만 보인다. 세부 설정은 [활동 확장](phase9-activity-extension.md)에 기록했다.
- 작품: 작품전시회의 공개 상태와 문서 접근 권한을 확인한 뒤 최신 작품 3건을 표시한다. 이미지는 Phase 7의 권한 검사 이미지 경로를 재사용한다. Figma의 임시 카드 이미지를 데이터 의미로 사용하지 않는다.
- 일정: 기존 `upcoming_events` 위젯이 `calendar` 모델에서 가져온 일정과 빈 상태를 새 카드 스타일로 표시한다.

기본 Rhymix Content 위젯 대신 `home_dashboard`를 사용한 이유는 과제 접근 권한과 작품전시회의 일괄 공개 상태를 미리보기에서도 같은 규칙으로 적용하기 위해서다. 카드 데이터는 새 가짜 구조로 저장하지 않고 각 기존 모듈에서 매 요청 읽는다. Headline·이미지·일정 대상 등의 위젯 설정은 관리자에서 계속 수정 가능하다.

## 로컬 개발환경 적용

1. `scripts/sync-dev.ps1`의 dry-run을 확인하고 `-Apply`로 repository의 위젯을 로컬 Rhymix에 복사한다.
2. `D:\rhymix_dev\php\php.exe scripts/configure-phase9-home-dev.php`로 Home 식별과 기존 위젯 구성을 확인한다. 기본 동작은 dry-run이다.
3. 같은 명령에 `--apply`를 붙이면 `index/49`의 desktop/mobile 위젯 페이지 구성만 갱신한다. 적용 전에 기존 `content`와 `mcontent`를 `D:\rhymix_dev\phase9-home-backups`에 저장한다. 스크립트는 `dev-environment-guard.php`가 식별한 로컬 DB만 허용하며 운영 DB에는 실행하지 않는다.
4. Rhymix 캐시를 재생성하고 Home을 확인한다. 운영 이관 시에는 해당 환경의 module_srl과 Home 페이지 설정을 별도 확인해야 한다.

## 검증

- 1920, 1440, 1024, 390 폭에서 Home이 200으로 렌더링되고 수평 overflow와 브라우저 페이지 오류가 없었다. 900px 높이 데스크톱에서 Header 80px 다음 히어로 820px, 844px 높이 모바일 기기 UA에서 Header 44px 다음 히어로 800px이었다.
- 스크롤 화살표는 다음 Home 콘텐츠로 이동했다. Hero CTA는 실제 `/kitelinfo`로 이동하며 Home 브라우저 제목은 설정된 headline이다.
- 로그인 회원의 과제 목록, 비로그인 과제 제목 비노출, 공개 작품 3건 및 이미지 응답을 확인했다. News와 미래 일정이 없는 로컬 상태에서는 각 빈 상태가 보인다.
- 로컬 일정 fixture를 임시로 등록해 실제 일정 카드와 장소를 확인한 뒤 삭제했다. HTML 형태의 일정 제목은 HTML/JS로 실행되지 않고 텍스트로 표시됐다. 테스트 계정의 임시 비밀번호도 원복했다.

## 후속 범위

Figma Home의 일부 카드 문구·이미지는 placeholder이다. News와 실제 일정 콘텐츠가 이관되면 동일한 위젯이 데이터로 채워진다. Rhymix의 별도 모바일 레이아웃 자체를 KITEL 공통 Header/Footer와 통일하는 작업과 세부 모바일 다듬기는 Phase 11 범위다.

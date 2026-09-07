# 로컬 Rhymix 개발 환경

작성일: 2026-08-19
목적: D드라이브에 구축한 로컬 Rhymix 개발 환경의 구성과 재시작 방법을 기록. C드라이브 용량 부족(5.2GB만 여유)으로 전부 D드라이브에 설치.

## 구성

| 구성요소 | 버전 | 경로 |
|---|---|---|
| PHP | 8.2.33 (NTS) | `D:\rhymix_dev\php` |
| MariaDB | 12.3.2 | `D:\rhymix_dev\mariadb` |
| Rhymix | 2.1.36 | `D:\rhymix_dev\www\rhymix` |

포트: PHP 내장 서버 `127.0.0.1:8888`, MariaDB `127.0.0.1:3307`(기본 3306이 아님, 다른 MySQL/MariaDB와 충돌 방지).

DB 접속 정보:
- DB명: `rhymix_dev`
- 계정: `rhymix` / `rhymixDevLocal!2026` (호스트 `127.0.0.1`)
- root: 비밀번호 없음 (로컬 전용, 외부 접속 불가 — `bind-address=127.0.0.1`)
- 테이블 접두사: `rx_`

Rhymix 관리자 계정(설치 시 생성, 개발용 임시 값):
- 아이디: `admin` / 비밀번호: `DevAdminPass!2026` / 이메일: `dev@kitel.local`

## 설치 확인 완료

- PHP 확장(mysqli, pdo_mysql, gd, mbstring, curl, openssl, zip, fileinfo, intl, exif) 전부 정상 로드
- Rhymix 설치 마법사에서 DB 연결 및 관리자 계정 생성까지 정상 완료, 기본 웰컴 페이지("Hello, World! Welcome to Rhymix") 로딩 확인
- `rx_member` 테이블에 admin 계정 생성 확인

## 시작 / 종료 방법

**서비스 시작** (Git Bash 기준, 각각 백그라운드로 실행):

```bash
# MariaDB
cd /d/rhymix_dev/mariadb/bin
nohup ./mysqld.exe --defaults-file=D:/rhymix_dev/mariadb/data/my.ini > /d/rhymix_dev/mariadb/mysqld.log 2>&1 &
disown

# PHP 내장 서버 (Rhymix 문서 루트)
cd /d/rhymix_dev/www/rhymix
nohup /d/rhymix_dev/php/php.exe -c /d/rhymix_dev/php/php.ini -S 127.0.0.1:8888 > /d/rhymix_dev/php_server.log 2>&1 &
disown
```

**접속**: 브라우저에서 http://127.0.0.1:8888

**종료**: 작업 관리자에서 `mysqld.exe`, `php.exe` 프로세스 종료 또는:
```bash
taskkill //F //IM mysqld.exe
taskkill //F //IM php.exe
```

> 참고: 이 서비스들은 Windows 서비스로 등록되지 않은 **수동 실행 프로세스**입니다. PC를 재부팅하면 자동으로 다시 시작되지 않으므로, 다음 세션에서 작업을 이어가려면 위 시작 명령을 다시 실행해야 합니다.

## 알려진 제약

- `mod_rewrite` 미지원 (PHP 내장 서버라 Apache 모듈 없음) — Rhymix 설치/사용에는 지장 없으나 짧은 주소(pretty URL) 기능은 비활성. 필요해지면 Apache로 전환 검토.
- 이 환경은 git 저장소(`D:\Projects\Kitel_Web_Migration`) 바깥의 `D:\rhymix_dev\`에 있어 **git 추적 대상 아님**. 실제 커스텀 개발(모듈/스킨 코드)을 시작하면 이 저장소 안으로 옮기거나 별도 관리 방식을 정해야 함.

## 가설 검증 결과 (2026-08-19)

이 환경을 구축한 목적이었던 [board-feature-specs.md](board-feature-specs.md)의 기술 가설 3가지를 실제로 검증함. 방법: 관리자로 로그인해 테스트 게시판(`mid=test_board`)을 만들고 게시판 관리 화면(권한 관리 / 게시판 정보 > 고급 설정)을 직접 확인, 필요한 부분은 `modules/board`의 실제 소스(`board.view.php`, `conf/module.xml`)로 동작을 재확인.

| # | 가설 | 결과 |
|---|---|---|
| 1 | Kitel News: grants가 "목록 보기"/"본문 읽기"를 분리 지정 가능한가 | **✅ 확인.** 권한 관리 화면에 "목록"과 "열람"이 별도 항목으로 존재, 각각 전체공개/로그인회원/선택 그룹 등으로 독립 설정 가능. 커스텀 개발 불필요 |
| 2 | 과제게시판: "준회원 본인 글만" 같은 행 단위 열람 제한이 가능한가 | **✅ 확인, 예상보다 쉬움.** 게시판 고급 설정의 **"상담 기능"** 체크박스 하나로 해결 — "관리 권한이 없는 회원은 자신이 쓴 글만 보이도록" 하는 게 표준 기능. "상담글 열람" 권한을 정회원 이상 그룹에 부여하면 전체 열람도 가능. `board.view.php`에서 `consultation` 플래그로 문서 목록/열람을 필터링하는 로직 확인 |
| 3 | 캘린더/자료실/작품전시회 갤러리 — 커스텀 모듈 난이도 | **가설대로 커스텀 모듈 필요.** 번들 모듈에 캘린더·파일브라우저류 없음. Rhymix 모듈은 `conf/module.xml` + `model/view/controller.php`(+admin) + `schemas/` + `queries/` + `skins/`로 구성되는 정형화된 구조(`poll` 모듈로 실물 확인) — 학습 필요하지만 패턴은 명확. `kitel_rental_*`처럼 모듈 시스템 밖 독립 앱으로 만드는 대안도 이미 이 프로젝트에서 검증됨 |

부수적으로 발견한 것: 건의사항의 "임원진에게도 익명" 요구사항도 게시판 고급 설정의 **"익명 사용" + "관리자 익명 제외"(체크 해제)** 조합으로 표준 기능만으로 해결됨 (커스텀 개발 불필요).

상세 반영 내용은 [board-feature-specs.md](board-feature-specs.md) 각 섹션의 "✅ Rhymix 구현 검증" 표시 참고.

## 사이트 뼈대 구축 (2026-08-19)

가설 검증에 이어, [new-site-ia.md](new-site-ia.md)에 확정된 메뉴/권한을 실제 로컬 Rhymix에 반영해 사이트 뼈대를 만듦(테스트용 `test_board`는 삭제, Rhymix 기본 데모 게시판 Free Board/Q&A/Notice도 정리).

### 회원 그룹 (8개)
기본 3개(관리그룹/준회원/정회원)에 5개 추가: 임원진(group_srl 108), 특별회원(109), 가입대기(110, 신규가입 기본그룹으로 지정), 정보부(111), 기술부(112) — 정보부/기술부는 [new-site-ia.md](new-site-ia.md) 1-2의 "부서 태그" 개념 그대로 별도 그룹으로 구현(회원이 임원진+기술부처럼 다중 그룹 소속 가능).

### 메뉴 구조
```
About (문서 페이지) ─ 키텔 소개 / 임원진 소개 및 인사말 / 회칙
Kitel News (게시판)
Study ─ 과제게시판 / 작품전시회 게시판 / 세미나 게시판 / 공유 게시판 (전부 게시판)
기타 ─ 건의사항(게시판) / Contact Us(문서 페이지) / 기자재 대여 시스템(외부 페이지, 플레이스홀더)
```
캘린더(일정)와 자료실은 커스텀 모듈 개발 전이라 이번 단계에서는 메뉴를 만들지 않음(2·3단계에서 모듈 완성 후 추가 예정).

### 게시판별 권한 설정 (module_grants)

| mid | 목록/열람 | 글쓰기 | 비고 |
|---|---|---|---|
| news (Kitel News) | 목록 전체공개 / 열람 준회원 이상 | admin만 | |
| seminar (세미나) | 준회원 이상 | 정회원 이상 | |
| sharing (공유) | 정회원 이상 | 정회원 이상 | |
| suggestion (건의사항) | 정회원 이상(=작성자+임원진 계열) | 정회원 이상 | **상담 기능 ON**, 상담글열람=임원진/정보부/기술부, **익명 사용 ON + 관리자 익명 제외 OFF** |
| assignment (과제게시판) | 준회원 이상(상담기능으로 준회원은 본인 글만) | 준회원(3) + 기술부(112) | **상담 기능 ON**, 상담글열람=정회원 이상(전체 열람) |
| exhibition (작품전시회) | 전체공개(비로그인 포함) | 정회원 이상 | 승인 워크플로우(공개/비밀 상태 활용)는 미구현, 다음 단계 과제 |

"정회원 이상"은 정회원·특별회원·임원진·정보부·기술부(group_srl 4,108,109,111,112), "준회원 이상"은 여기에 준회원(3)을 더한 집합으로 통일 적용(가입대기 그룹은 항상 제외).

### 확인된 제약 / 후속 과제
- 메뉴 자체의 노출 권한(`메뉴 편집 > 권한`)은 이번에 건드리지 않음 — 게시판 접근은 막히지만 준회원에게 건의사항 메뉴 링크 자체는 보일 수 있음. 필요시 메뉴 레벨 권한도 맞춰 제한할 것.
- ~~작품전시회의 검토중→승인→공개 워크플로우, 과제게시판의 제출현황 대시보드, 캘린더/자료실 커스텀 모듈은 아직 미착수.~~ → 캘린더는 아래에서 완료. 나머지는 계속 남은 과제.

## 첫 커스텀 모듈: 캘린더 (2026-08-19)

`modules/calendar`를 처음부터 만들어 About > 일정 메뉴(`mid=schedule`)에 설치·검증까지 완료. 소스는 `D:\rhymix_dev\www\rhymix\modules\calendar`와 이 저장소의 [custom_modules/calendar](../custom_modules/calendar)(git 추적용 사본) 양쪽에 있음 — **로컬 인스턴스를 다시 만들 때는 `custom_modules/calendar`를 `D:\rhymix_dev\www\rhymix\modules\`로 복사해서 사용.**

### 구조
표준 Rhymix 모듈 스캐폴드를 그대로 따름:
```
calendar/
├── conf/{info.xml, module.xml}     # 모듈 메타정보 + grants(list/manage) + actions 선언
├── calendar.class.php               # 부모 클래스, moduleInstall() 훅
├── calendar.model.php                # getEventList / getEvent
├── calendar.view.php                 # dispCalendarIndex — 월 그리드 뷰 (공개)
├── calendar.admin.view.php           # dispCalendarAdminContent/Form/GrantInfo (관리자)
├── calendar.admin.controller.php     # procCalendarAdminInsertEvent/DeleteEvent
├── schemas/calendar_event.xml        # DB 테이블 정의 (rx_calendar_event)
├── queries/*.xml                     # getEventList/getEvent/insert/update/delete
├── skins/default/{skin.xml,list.html}  # 공개 화면 스킨 — 디자이너가 새 스킨 폴더로 교체 가능
└── tpl/*.html                        # 관리자 화면 템플릿(스킨 대상 아님)
```

### 모듈 설치 시 필수 스텝 (신규 커스텀 모듈 공통)
1. 위 파일 구조로 `modules/{module}/` 생성
2. 메뉴 편집에서 "메뉴 추가"로 붙이면(직접 클릭이든 API 호출이든) `xe_modules` 행과 메뉴 항목은 생기지만 **DB 스키마는 아직 안 만들어짐**
3. 반드시 아래를 한 번 호출해야 `schemas/*.xml`이 실제 테이블로 생성되고 `moduleInstall()`이 실행됨:
   ```
   POST / 
   module=install
   act=procInstallAdminInstall
   module_name={module}
   _rx_csrf_token={현재 페이지의 CSRF 토큰}
   ```
   (admin으로 로그인한 브라우저 세션에서, 페이지 내 `input[name="_rx_csrf_token"]` 값을 그대로 사용)

### 검증 완료
- 공개 월 그리드 화면: 월 이동, 오늘 날짜 강조, 날짜별 이벤트 표시 정상
- 관리자 화면: 일정 등록 폼 제출 → `rx_calendar_event`에 저장 → 목록에 반영 → 공개 화면에도 반영, 전 과정 확인
- 권한 관리 화면: module.xml의 `list`/`manage` grant가 게시판과 동일한 공용 권한 UI로 자동 생성됨 (`getModuleGrantHTML` 재사용)

## 두 번째 커스텀 모듈: 자료실 (2026-08-19)

`modules/archive`를 만들어 Study > 자료실 메뉴(`mid=storage`)에 설치·검증 완료. 소스는 `D:\rhymix_dev\www\rhymix\modules\archive`와 [custom_modules/archive](../custom_modules/archive) 양쪽에 있음.

### 구조
캘린더와 동일한 스캐폴드 + 파일 업로드 처리:
```
archive/
├── conf/{info.xml, module.xml}       # grants(list/manage) + actions
├── archive.class.php                  # moduleInstall() 훅
├── archive.model.php                   # getFolderList/getFolder/getFolderPath(브레드크럼)/getFileList/getFile
├── archive.view.php                    # dispArchiveIndex — 폴더 트리 탐색 화면 (공개)
├── archive.controller.php              # 폴더 생성/삭제, 파일 업로드/다운로드/삭제
├── archive.admin.view.php              # dispArchiveAdminGrantInfo (권한 설정만, 콘텐츠 관리는 공개 화면에서)
├── schemas/{archive_folder.xml, archive_file.xml}  # rx_archive_folder, rx_archive_file
├── queries/*.xml
├── skins/default/{skin.xml, list.html}
└── tpl/*.html
```

파일은 `files/attach/archive/{module_srl}/{file_srl}_{원본파일명}`에 저장하고, 그 디렉터리에 `.htaccess`(`Require all denied`)를 둬서 URL로 직접 접근하지 못하게 막음 — 다운로드는 반드시 `procArchiveDownloadFile` 컨트롤러를 거쳐야 함(권한 체크 + 다운로드 수 카운트). 이 `.htaccess`는 Apache 전용이라 **PHP 내장 서버(현재 로컬 환경)에서는 실제로 적용되지 않음** — 운영 서버(Apache 기반 Synology)에 배포하면 그때부터 실효.

### ⚠️ 새로 발견한 이슈: GET 링크로 트리거하는 컨트롤러 액션
"삭제", "다운로드"처럼 `<a href>` 링크로 호출하는 컨트롤러 액션은 기본적으로 GET 요청이 거부됨(`이 요청에 사용할 수 없는 HTTP 메소드입니다`, `ModuleHandler.class.php:378`). module.xml의 해당 `<action>`에 `method="GET|POST"`를 명시해야 함 — Rhymix 코어의 `modules/file/conf/module.xml`도 `procFileDownload`에 동일하게 `method="GET|POST"`를 씀. **캘린더의 `procCalendarAdminDeleteEvent`도 같은 문제가 있어서 함께 고쳤음** (이전엔 삭제 버튼을 실제로 눌러서 검증하지 않아 놓쳤던 부분).

### 검증 완료
폴더 생성 → 하위 폴더 진입(브레드크럼 확인) → 파일 업로드 → 다운로드(파일명 인코딩·본문 내용 확인) → 파일 삭제(물리 파일+DB 행 모두 제거 확인) → 폴더 삭제(재귀 삭제 확인) → 권한 화면에서 정회원 이상 그룹으로 grants 설정, 전 과정 실제로 테스트함.

## 세 번째 커스텀 모듈: 과제게시판/homework (2026-08-19)

`modules/homework`를 만들어 Study > 과제게시판 메뉴(`mid=homework`)에 설치·검증 완료. 소스는 [custom_modules/homework](../custom_modules/homework).

### ⚠️ 기존 접근(게시판 상담 기능 재사용)을 실제로 뒤집은 사례
과제게시판은 원래 board 모듈(mid=`assignment`)에 상담 기능을 켜서 구현했었음(board-feature-specs.md §4 기존 내용). 그런데 실제로 만들어서 확인해보니, **상담 기능은 "본인이 쓴 글만 보이게" 하는 기능이라 기술부가 올린 과제 공지글도 준회원 눈에는 안 보이는 문제**가 있었음 — 이 기능은 건의사항처럼 "모두 비공개, 본인 것만" 상황에는 맞지만, "출제자 공개글 + 제출자 비공개 제출물"처럼 두 종류가 섞인 경우엔 안 맞음. 데이터가 없는 걸 확인하고(0건) 기존 board를 삭제한 뒤, `assignment`와 `submission`을 처음부터 별도 테이블로 분리한 `homework` 모듈로 다시 만듦 — 이게 애초에 board-feature-specs.md가 제시했던 데이터 모델과 일치함.

### 구조
캘린더·자료실과 같은 스캐폴드 + 대시보드 화면:
```
homework/
├── conf/{info.xml, module.xml}         # grants(list/submit/view_all/create) + actions
├── homework.class.php
├── homework.model.php                   # getTaskList/getTask, getSubmission*, getJuniorMembers(준회원 그룹 조회 — 코어의 member.getMemberListWithinGroup 쿼리 재사용)
├── homework.view.php                    # dispHomeworkIndex(목록), dispHomeworkView(상세+제출)
├── homework.controller.php              # procHomeworkSubmit(upsert 제출), procHomeworkDownloadSubmission
├── homework.admin.view.php              # 과제관리/대시보드/권한 (기술부 전용, permission="create")
├── homework.admin.controller.php        # 과제 등록/수정/삭제
├── schemas/{homework_task.xml, homework_submission.xml}
├── queries/*.xml
├── skins/default/{index.html, view.html}  # 공개 화면, 디자이너 스킨 교체 대상
└── tpl/*.html                            # 관리자 화면(과제관리·대시보드·권한)
```

### 대시보드 구현 방식
`dispHomeworkAdminDashboard`에서 (1) 전체 과제 목록, (2) 준회원 그룹 전원(`member.getMemberListWithinGroup` 재사용), (3) 전체 제출물을 각각 조회한 뒤, 제출물을 `"{task_srl}:{member_srl}"` 키로 매핑해 준회원×과제 매트릭스를 PHP에서 조립. 템플릿은 행(준회원)을 loop, 각 행 안에서 `$row->cells`(과제 순서와 동일하게 정렬된 제출물 배열, 없으면 null)를 loop해서 제출/지각/미제출을 셀 단위로 표시.

### 검증 완료
과제 출제 → 공개 목록에 노출(+ 준회원 관점 "내 제출 상태" 표시) → 제출(텍스트+첨부파일) → 상세 화면에 "이전 제출" 표시 → **재제출 시 upsert로 덮어써지는 것 확인** → 첨부파일 다운로드(본인/view_all 권한자만 가능하도록 이중 체크) → 대시보드가 준회원 그룹 멤버 기준으로 정확히 매트릭스를 그리는지(테스트용으로 admin을 준회원 그룹에 임시 추가해 확인 후 원상복구) 확인함.

### ⚠️ 템플릿 문법 실수 하나 더 발견
`class="x"|cond="y"`(속성값 조건부 적용) 문법을 **엘리먼트 전체를 조건부로 감추는 용도로 잘못 사용**해서, 마감일 안내 문구 두 줄이 조건과 무관하게 항상 둘 다 렌더링되는 버그가 있었음. 엘리먼트 자체를 조건부로 켜고 끄려면 태그에 `cond="..."`를 직접 붙여야 함(파이프 없이) — 캘린더·자료실 템플릿에서는 이미 올바르게 썼던 패턴인데 이번에 새로 쓰다가 헷갈려서 재발. 실제 화면을 렌더링해보고서야 발견함 — 템플릿 조건문은 코드만 보고 넘어가지 말고 항상 브라우저로 확인 필요.

## 네 번째 커스텀 화면: 작품전시회 (2026-08-19)

과제게시판과 반대로, 이번엔 **board 표준기능(비밀글 상태 + 관리권한)이 실제로 3단계 승인 워크플로우에 맞는지 먼저 검증한 뒤** 문제없음을 확인하고 커스텀 스킨만 얹는 방식으로 진행함. 대상: exhibition 게시판(module_srl=129), 스킨은 [custom_skins/board/kitel_gallery](../custom_skins/board/kitel_gallery)(배포 경로 `modules/board/skins/kitel_gallery`).

### 검증한 가설: board의 비밀글(SECRET) 상태 + 관리권한(manager grant)으로 "정회원 제출 → 기술부 검토 → admin 승인 → 공개" 워크플로우가 되는가
`document.item.php::isGranted()`를 코드로 먼저 확인(공개 아니면 (a) site admin, (b) 글쓴이 본인, (c) 모듈 manager 권한 + `moderate:document` scope만 접근 가능)한 뒤, 실제로 재현:

1. exhibition 게시판에 `비밀글` 상태 옵션 켜고, 확장변수(`작품 사진` type=file 필수, `제안서` type=file 필수) 추가
2. 게시판 권한: 열람/목록은 전체공개(guest), 글쓰기는 정회원 이상, **관리(manager)는 기술부(group_srl=112)만**로 설정 — 이게 기술부의 "검토" 권한을 만드는 핵심
3. 테스트 계정 `testjunior`(정회원, member_srl=154)로 로그인해 `status=SECRET`으로 글+두 파일 제출
4. **글쓴이 본인**: 자기 글의 내용·첨부파일 정상 열람 확인
5. **guest(로그아웃 상태)**: 상세 페이지에서 "비밀번호를 입력하세요" 문구만 보이고 내용·첨부는 안 보임 확인. (문서에 password를 애초에 설정한 적이 없어 `password` 컬럼이 NULL인데, `MemberModel::isValidPassword()`가 `!$hashed_password`면 무조건 false를 반환하므로 빈 비밀번호로 우회되는 취약점은 없음 — 코드로 확인함)
6. **기술부 계정** `testtech`(정회원 아님, group_srl=112에만 배정, member_srl=155)로 로그인 → manager grant를 통해 SECRET 문서의 내용·첨부·수정/삭제 버튼까지 정상 노출 확인 (글쓴이가 아닌데도 접근 가능 = manager 경로가 실제로 작동)
7. admin으로 로그인해 글쓰기 폼(`dispBoardWrite`)에서 상태 라디오를 `SECRET → PUBLIC`으로 바꿔 제출 → DB에서 `status='PUBLIC'`으로 반영됨 확인 (이게 "승인" 액션)

결론: **board 표준기능만으로 이 워크플로우가 정확히 됨** — 과제게시판과 달리 별도 모듈 전환 불필요.

### 발견한 제약: 게시판 목록은 비밀글 제목을 누구에게나 보여줌
`document.item.php::getTitle()`은 `isAccessible()` 체크 없이 항상 실제 제목을 반환하고, 기본 list.html 스킨은 이를 그대로 출력함 — 즉 guest도 게시판 목록에서 SECRET 문서의 **제목과 작성자 닉네임**은 볼 수 있음(내용·첨부파일만 막힘). 실제로 로그아웃 상태에서 재현 확인함. 커스텀 갤러리 스킨에서는 `list.html` 자체에서 `$document->isAccessible()`이 false인 문서를 카드 배열에서 아예 제외해서 해결함(제목도 안 보이게).

### kitel_gallery 스킨 구조
`default` 스킨 전체를 복사한 뒤 `list.html`만 카드 그리드로 교체(글쓰기/상세보기/댓글 등 나머지 화면은 기본 스킨 그대로 재사용 — 과제게시판 노트에 적었던 "목록 스타일만 다르면 된다"는 원래 가설대로).

카드 렌더링 로직(`list.html`):
- `$document_list`/`$notice_list`를 순회하며 `isAccessible()`인 것만 카드 배열에 담음(guest에게는 심사중 글이 아예 안 보임)
- 각 카드는 `$document->get('status')==='SECRET'` 여부로 "심사중" 배지 카드 / 일반 카드 두 분기로 나눠 렌더(글쓴이·기술부·admin처럼 `isAccessible()`이 true인 사람에게는 심사중 글도 보이지만, 승인 전인지 구분할 수 있어야 하므로 accessible 여부가 아니라 status로 배지를 결정 — 안 그러면 기술부 눈에도 승인/미승인 구분이 안 됨, 실제로 이 버그를 만들었다가 고침)
- 썸네일: 확장변수(`작품 사진`)로 올라간 파일은 `upload_target_type='ev:doc'`로 저장되어 `$document->getUploadedFiles()`(본문 삽입 파일만 반환, `upload_target_type='doc'` 전용)로는 안 잡힘 → `FileModel::getFiles($document->document_srl, [], 'file_srl', true, 'ev:doc')`를 템플릿에서 직접 호출해 mime_type이 `image/`로 시작하는 첫 파일을 썸네일로 사용
- 간단설명: `$document->getContentPlainText(60)` 사용. 단, board 목록 쿼리는 성능상 기본적으로 `content` 컬럼을 안 가져옴(제목·작성자 등 요약 컬럼만) — 게시판 관리자 설정의 "목록 항목"에 `summary`를 추가해야 `content`가 함께 조회됨. 관리자 UI(멀티오더 위젯)를 자동화하기 번거로워 `rx_module_part_config` 테이블의 직렬화된 배열에 `summary` 항목을 직접 추가함.

### 스킨 활성화 시 주의
게시판의 `skin` 컬럼만 바꿔서는 반영 안 됨 — `is_skin_fix='N'`(사이트 기본 스킨 따라감, 기본값)이면 `skin` 컬럼 값 자체가 무시됨. `is_skin_fix='Y'`로 같이 바꿔야 함. 그리고 `files/cache/module_info`, `files/cache/template_compiled`, `files/cache/store`를 지워야 변경사항이 바로 반영됨(모듈설정 캐시+ 컴파일된 템플릿 캐시).

### 테스트 데이터
`testjunior`(정회원, 글쓴이 역할), `testtech`(기술부, 검토자 역할) 계정과 테스트 제출물(document_srl=162)은 데모용으로 남겨둠(캘린더의 데모 이벤트와 같은 방침) — 최종 상태는 `status=PUBLIC`(승인 완료)으로 맞춰서 갤러리에 카드 1개가 정상 노출되는 상태로 둠.

## 첫 번째 위젯: 히어로 배너 (2026-08-19)

`widgets/hero_banner`로 제작, 메인페이지(mid=`index`, module_srl=49, `page` 모듈)에 삽입 완료. 소스는 [custom_widgets/hero_banner](../custom_widgets/hero_banner). 캘린더·자료실·과제게시판·작품전시회는 전부 게시판/모듈 수준이었던 반면, 이건 Rhymix 위젯 시스템을 처음 실제로 다룬 사례라 위젯 특유의 매커니즘을 정리해둠.

### 위젯은 모듈보다 훨씬 가벼움
스키마(DB 테이블) 없음, `moduleInstall()` 같은 설치 절차 없음 — `widgets/{name}/` 아래에 `conf/info.xml`(제목·설명·`extra_vars`) + `{name}.class.php`(`WidgetHandler`를 상속하는 `proc($args)` 메서드 하나) + `skins/{skin}/{name}.html`만 있으면 즉시 위젯 목록에 나타나고 페이지에 삽입 가능. `extra_vars`도 모듈 grant/skin 설정과 완전히 같은 선언 방식(`<var id type name description>`)이라 관리자 설정 폼이 자동 생성됨 — 여기서도 `type="image"`를 그대로 선언해서 이미지 업로드 필드를 얻음.

### 페이지에 위젯을 넣는 실제 방식
Rhymix의 `page` 모듈은 콘텐츠를 `rx_modules.content` 컬럼(longtext)에 원본 HTML로 그대로 저장하고, 그 안에 위젯이 있으면 `<img class="zbxe_widget_output" widget="{위젯이름}" {extra_var키}="{값}" ... />` 형태의 특수 `<img>` 태그로 인코딩되어 있음(`widgetController::procWidgetGenerateCode()`가 생성하는 정확한 포맷, 실제 기존 `widgetContent` 위젯 삽입 결과를 DB에서 읽어 포맷을 확인함). 렌더링 시 `WidgetController::transWidgetCode()`가 이 태그를 실제 위젯 출력으로 치환. 관리자 화면 에디터로 위젯을 끌어넣는 것과 동일한 결과이므로, 이번엔 이 태그를 직접 조립해 `content` 컬럼 맨 앞에 붙이는 방식으로 히어로 배너를 배치함(값이 이미 알려진 데모 문구라 에디터 조작보다 빠름).

### 검증: 관리자 화면에서 실제로 편집 가능한지 확인
admin으로 로그인해 페이지 편집 화면(`dispPageAdminContentModify`)에 들어가 보니 히어로 배너가 CKEditor 안에서 편집 가능한 블록(코너 핸들 + 톱니바퀴/미리보기/삭제 버튼 툴바)으로 인식됨. 톱니바퀴를 누르면 `conf/info.xml`에 선언한 필드(헤드라인/서브 텍스트/배경이미지/버튼 문구/버튼 링크) 그대로, 설명 문구까지 포함해서 자동 생성된 설정 폼이 뜸 — SQL로 심어놓은 게 아니라 실제 관리자 UI로 편집 가능함을 확인함(스크린샷으로 확인, 실제 제출은 하지 않고 빠져나옴 — 중복 삽입 방지).

### 스타일
캐러셀(자동 전환) 없이 고정 배경 이미지 위에 헤드라인/서브텍스트/CTA 버튼을 얹는 단일 배너. 배경 이미지가 없으면 단색(`#1a1a1a`)으로 대체. 다른 커스텀 화면들과 마찬가지로 임시 인라인 스타일이며 디자이너 스킨 교체 대상.

### 참고: XEDITION 레이아웃 자체의 캐러셀과는 다른 것
현재 쓰고 있는 XEDITION 테마 레이아웃에는 페이지 콘텐츠와 별개로 상단에 자체 슬라이드 캐러셀("SHARING, PUBLISHING...")이 이미 있어서, 지금 화면엔 레이아웃 캐러셀 + 우리 히어로 배너 위젯이 위아래로 겹쳐 보임. 레이아웃 자체는 디자이너 스킨으로 통째로 교체될 예정이라 신경 쓸 필요 없음 — 그냥 지금 로컬 화면에서 두 개가 같이 보이는 이유를 남겨둠.

## 두 번째 위젯: 다가오는 일정 (2026-08-19)

`widgets/upcoming_events`로 제작, 히어로 배너 바로 아래에 삽입. 소스는 [custom_widgets/upcoming_events](../custom_widgets/upcoming_events).

### 캘린더 모듈 재사용
새 쿼리를 안 짜고 [custom_modules/calendar](../custom_modules/calendar)의 `CalendarModel::getEventList($module_srl, $range_start, $range_end)`를 그대로 호출 — 오늘부터 `days_ahead`(기본 90일) 이내로 범위를 잡아 조회한 뒤 `start_date` 기준 정렬 + `event_count`(기본 5개)만큼 자름. 대상 캘린더는 content 위젯의 `module_srls`와 동일한 `<type>module_srl_list</type>` extra_var로 선택(관리자 화면에서 모듈 선택 UI 자동 생성, 값은 쉼표구분 module_srl 문자열로 넘어옴 — content 위젯 소스에서 파싱 방식 확인 후 동일하게 `explode(',', ...)` 처리).

### 검증
로컬 캘린더(module_srl=141, mid=schedule)에 있는 데모 일정(8/25 신입 부원 환영회)이 미리보기 카드(월/일 배지 + 제목 + 장소)로 정확히 뜨는 것, "전체 일정 보기" 링크가 실제 캘린더 페이지(`/schedule`)로 연결되고 거기서도 같은 일정이 8/25에 표시되는 것까지 확인함. 페이지 편집 화면에서도 히어로 배너와 동일하게 편집 가능한 블록으로 인식됨.

이걸로 board-feature-specs.md §12에서 식별했던 신규 개발 항목(히어로 배너, 다가오는 일정) 2개 모두 완료.

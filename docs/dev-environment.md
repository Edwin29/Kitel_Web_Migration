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

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

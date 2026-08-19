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

## 다음 확인 사항

이 환경은 [board-feature-specs.md](board-feature-specs.md)의 "Rhymix 구현 메모" 가설을 검증하기 위해 구축함. 다음 단계로 확인할 것:

1. 게시판 모듈 grants가 "목록 보기"와 "본문 읽기"를 분리 지정할 수 있는지 (Kitel News 잠금 UX)
2. 비밀글 기능을 응용해 "준회원 본인 글만" 같은 행 단위 열람 제한이 가능한지 (과제게시판)
3. 캘린더/자료실/작품전시회 갤러리처럼 표준 스킨을 벗어나는 커스텀 모듈 개발이 실제로 어느 정도 난이도인지

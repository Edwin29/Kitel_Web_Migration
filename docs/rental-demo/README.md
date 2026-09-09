# rental_demo — 스크린샷/체험용 복제 환경

기자재 대여 시스템을 **운영에 영향 없이** 실제로 조작해 볼 수 있는 복제본.
스크린샷 수집·기능 시연에 쓴다.

## 접속

```
https://kitel.kw.ac.kr/rental_demo/public/
```

- **로그인 불필요.** 자동으로 `정보부장`(관리자)으로 로그인된 상태다. 모든 화면에 바로 접근된다.
- 운영본은 `https://kitel.kw.ac.kr/rental_dev/public/` — **이 경로는 건드리지 말 것.**

## 안전성 (검증됨)

- `mode = local`: 저장소는 `database/local_data.json` **파일 하나**. MySQL 에 연결하지 않는다
  (`db_connect()` 는 로컬 모드에서 호출되지 않음 — 코드상 확인).
- 데모에서 대여·반납·등록·폐기·가져오기를 **아무리 해도** 바뀌는 건 그 JSON 파일과
  데모 전용 PHP 세션(`KITELDEMO`)뿐이다.
- `docs/rental-demo/isolation_test.php` 로 실측 검증함:
  데모에서 대여 1건 실행 → 데모 JSON 은 반영, **운영 DB(`kitel_rental_*`)는 완전히 그대로**.

## 들어 있는 데이터

운영 스냅샷 (2026-09-09 기준, 읽기 전용 export):

| | 수 |
|---|---|
| 카테고리 | 87 (개별 70 / 개수 17) |
| 기자재 | 562 (실제 이름·라벨·QR 코드 그대로) |
| 묶음 | 36 |
| 대여 이력 | 44 + **합성 3건**(첫 화면이 비지 않도록 추가, 그중 1건 연체) |
| 처리 로그 | 최근 500건 (운영은 3,472건이지만 파일 크기·로드 속도상 잘라냄. 로그 화면은 5페이지 분량) |

첫 접속 시 대시보드: 전체 기자재 542 · 대여 중 3 · 연체 1.

## UI 동일성

레이아웃·CSS·JS·화면 구조는 **운영과 100% 동일**하다 (렌더링 코드에 mode 분기 없음, 외부 CDN 없음).

**단 하나의 예외 — 회원 이름.** 로컬 모드는 XE 회원 테이블을 못 읽으므로,
아래 화면에서 처리자/회원 이름이 실제 이름 대신 **`회원{번호}`** 로 나온다:

- 처리 로그 (`admin/logs.php`)
- 대시보드 "최근 처리 로그", 기자재 이력의 처리자 칸
- 회원 검색 결과 (`admin/loan_new.php`, `admin/member_history.php`)

레이아웃은 픽셀 단위로 같고 그 자리의 **문자열만** 다르다.
스크린샷에서 이 부분이 필요하면 별도 처리 필요 (이번 범위에서는 제외).

## 화면 목록

| 구분 | URL (접두: `.../rental_demo/public/`) |
|---|---|
| 홈 | `index.php` |
| 기자재 현황 | `catalog.php` |
| QR 스캔 | `scan.php?mode=rent` / `scan.php?mode=return` — **카메라 UI 는 모바일 User-Agent 일 때만** 표시됨 |
| 대여 목록 / 반납 목록 | `rent_list.php` / `return_list.php` |
| 기자재 상세 (QR 도착지) | `item.php?code=EQ-XXXXXXXX` |
| 개수 관리 카테고리 대여 | `category.php?id=N` |
| 내 대여 / 내 기록 | `my.php` / `history.php` |
| 관리자 대시보드 | `admin/index.php` |
| 기자재 관리 | `admin/items.php` (폐기 필터: `?status=retired`) |
| 새 물품 추가 / CSV 탭 | `admin/item_new.php` / `admin/item_new.php?tab=csv` |
| 기자재 상세/수정/이력 | `admin/item_detail.php?item_id=N` 등 |
| 카테고리 관리 | `admin/categories.php` |
| 대여 현황 | `admin/loans.php` (연체: `?status=overdue`) |
| 관리자 대여 등록 | `admin/loan_new.php` |
| 사용자 기록 | `admin/member_history.php?member_srl=N` |
| 처리 로그 | `admin/logs.php` |
| 권한 그룹 / 네트워크 점검 | `admin/permissions.php` / `admin/network_check.php` |
| QR 보기/인쇄 | `admin/qr.php?item_id=N`, `admin/qr_print.php?item_ids=1,2,3`, `admin/category_qr.php?category_id=N`, `admin/bundle_qr.php?bundle_id=N` |

전 화면 200 응답 확인됨 (QR 4개 포함 — NAS 에 qrencode 설치되어 있음).

## 초기 상태로 되돌리기

데모에서 뭔가 잔뜩 만들었다가 깨끗한 상태로 스크린샷을 다시 찍고 싶을 때:

```sh
cp /volume1/kitel_web/xe/rental_demo_seed.json \
   /volume1/kitel_web/xe/rental_demo/database/local_data.json
```

`rental_demo_seed.json` 은 export 직후의 원본 시드다.

## 완전 철거

```sh
rm -rf /volume1/kitel_web/xe/rental_demo
rm -f  /volume1/kitel_web/xe/rental_demo_seed.json
```

이게 전부다. 운영본·DB·설정 어디에도 흔적이 없다.

## 재구축

```sh
# 1. 시드 재생성 (운영 DB 읽기 전용)
/usr/local/bin/php74 docs/rental-demo/export_demo_seed.php /tmp/local_data.json

# 2. 배치 (deploy.sh 는 /tmp/demo_config.php, /tmp/demo_bootstrap.php, /tmp/local_data.json 을 참조)
#    docs/rental-demo/demo-config.php → /tmp/demo_config.php
#    docs/rental-demo/demo-bootstrap.php → /tmp/demo_bootstrap.php
sh docs/rental-demo/deploy.sh
```

## 이 디렉터리의 파일

| 파일 | 용도 |
|---|---|
| `export_demo_seed.php` | 운영 `kitel_rental_*` → `local_data.json` (읽기 전용). 로그 500건 캡, 합성 대여 3건 추가 |
| `demo-config.php` | 데모 전용 `app/config.php` (mode=local, 호스트 허용, IP 제한 해제, 경로) |
| `demo-bootstrap.php` | 데모 전용 `app/bootstrap.php` (세션명 `KITELDEMO` 로 운영과 분리) |
| `deploy.sh` | NAS 에서 트리 복제 + 위 파일 배치 + 권한 + 문법 검사 |
| `isolation_test.php` | 데모 대여 실행 → 운영 DB 불변 확인 |

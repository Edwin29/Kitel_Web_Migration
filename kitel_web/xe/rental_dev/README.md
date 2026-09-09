# KITEL 기자재 대여 시스템

광운대학교 전자연구회(KITEL) 동아리방 기자재를 QR 라벨로 관리하고, 홈페이지 계정으로
대여·반납하는 독립 PHP 앱입니다.

- 기존 XE(XpressEngine) 코어와 `xe_member*` 테이블은 **수정하지 않습니다.** 회원 정보는
  읽기만 하고, 대여 데이터는 전부 `kitel_rental_*` 전용 테이블에 저장합니다.
- 운영 웹은 **PHP 7.4** 위에서 돕니다. (`/usr/bin/php` = 8.1.9는 DSM 범용 CLI 바이너리로
  이 앱을 서비스하지 않으며 `pdo_mysql`도 없습니다. CLI 작업은 `/usr/local/bin/php74`.)
- 현재 라이브 경로: **`https://kitel.kw.ac.kr/rental_dev/public/`**
  (`/rental`은 안정화 후 전환할 최종 경로. 지금은 인증 사전조사 스크립트만 있습니다.)

> **소스·변경 이력:** 이 코드와 문서는 GitHub에서 관리합니다 →
> [`Edwin29/Kitel_Web_Migration` · `kitel_web/xe/rental_dev`](https://github.com/Edwin29/Kitel_Web_Migration/tree/main/kitel_web/xe/rental_dev)
> NAS(`/volume1/kitel_web/xe/rental_dev`)에 있는 이 파일은 **배포 산출물**입니다. 수정은 저장소에서 하고 §9의 배포 절차로 반영하세요.
> 편입·진단 수정 PR: [#3](https://github.com/Edwin29/Kitel_Web_Migration/pull/3)

---

## 1. 동작 모드

`app/config.php`의 `mode`(`KITEL_RENTAL_MODE` 환경변수)로 갈립니다.

| 모드 | 저장소 | 용도 |
|---|---|---|
| `local` | `database/local_data.json` (파일 기반 fake DB, 자동 생성) | 로컬 개발·테스트 |
| 그 외(`production`) | XE의 `files/config/db.config.php`를 읽어 얻은 MariaDB에 PDO 연결 | 운영 |

- 기본값은 `production`입니다. 환경변수를 넣기 어려운 PHP-FPM 환경을 전제로 한 선택입니다.
- `local` 모드는 안전장치가 있어, `localhost`/`127.0.0.1`이 아닌 호스트에서 열리면 500으로
  막힙니다 (`app/helpers.php` `enforce_local_mode_safety`).
- 인증도 모드에 따라 갈립니다. `local`은 `config.php`의 `fake_user`(정보부장, member_srl 4),
  운영은 XE 세션(`Context::get('logged_info')`)에서 현재 회원을 읽습니다 — `app/auth.php`.

---

## 2. 디렉터리 구조

```
rental_dev/
├── public/              웹에서 직접 접근되는 진입점
│   ├── *.php             일반 사용자 화면 (대여/반납/스캔/내 기록)
│   ├── admin/*.php       관리자 화면 (require_admin)
│   ├── assets/           style.css, jsQR.js, xlsx.full.min.js (모두 로컬 벤더링)
│   └── .htaccess         화이트리스트 접근 제어 (아래 §7)
├── app/                  공통 로직. 웹에서 직접 접근 불가(.htaccess Require all denied)
├── database/             schema / seed / 마이그레이션 / 로컬 fake DB
└── tools/                CLI 전용 스크립트 (설치·점검·마이그레이션)
```

### 운영 배치

이 저장소는 `app/`·`database/`를 웹 루트(`public/`) 옆에 두되, 각 폴더의 `.htaccess`로 직접
접근을 차단하는 구조입니다. 현재 운영은 전체를 통째로 배치합니다.

```
/volume1/kitel_web/xe/rental_dev/          이 저장소 전체
    → 라이브 URL: https://kitel.kw.ac.kr/rental_dev/public/
```

`public/_bootstrap.php`가 `app/`을 찾는 순서:
`KITEL_RENTAL_APP_DIR` → `../app` → `./_app` → `dirname(public)/app`.
따라서 위 배치에서는 환경변수 없이 `../app`으로 자동 해석됩니다.

> `app/`을 웹 루트 밖으로 완전히 빼고 싶다면 `KITEL_RENTAL_APP_DIR`로 절대경로를 지정하세요.

---

## 3. `app/` 구성

| 파일 | 역할 |
|---|---|
| `bootstrap.php` | 세션 시작(웹만) + 아래 모듈 로드 |
| `config.php` | 전 설정. 환경변수 우선, 없으면 기본값 |
| `helpers.php` | `e()` `csrf_*` `flash` 날짜 헬퍼, 신뢰 네트워크 판정, 장바구니(세션) 헬퍼 |
| `db.php` | XE `db.config.php`에서 PDO 생성, `xe_table()` 프리픽스, **`db_transaction()` / `db_begin/commit/rollback` (중첩 안전)** |
| `storage.php` | `rental_load()` — 카테고리·기자재·번들 등 **개수가 유한한 것만** 적재. `loans`/`logs`는 통째로 읽지 않음(§5). 페이지네이션 헬퍼(`rental_page`, `rental_query_url`) |
| `auth.php` | 현재 회원, XE 회원 조회/검색, **처리자 이름 표시·프리페치·필터 해석** |
| `permissions.php` | `kitel_rental_allowed_groups` 기반 `user_is_admin` / `user_can_borrow`, `require_admin` |
| `logger.php` | `add_log()` + **로그 조회 계층**(`log_query` `log_recent` `log_actions`, 필터→SQL) |
| `qr.php` | 서버의 `qrencode`를 `proc_open`으로 호출해 SVG QR 생성 |
| `categories.php` | 카테고리 CRUD, 개별(unique)/개수(bulk) 관리 전환, **이름 변경 시 기자재 라벨 재생성** |
| `bundles.php` | 묶음(카테고리 그룹) CRUD, 일괄 이동/삭제(한 트랜잭션) |
| `items.php` | 기자재 CRUD, 자동 라벨/`public_code`, **필터/정렬 계층**, 상태변경·폐기(소프트 삭제), 일괄 처리 |
| `loans.php` | 대여/반납, 묶음 대여(한 트랜잭션·`related_loan_id`로 그룹), **대여 조회 계층**(`loan_query` 등), 반납 후 상태 지정 |
| `import.php` | **CSV 가져오기 — 해석 / 계획 수립(미리보기) / 적용**. §6 |
| `export.php` | CSV 내보내기 — 화면 필터를 그대로 물려받아 스트리밍 |
| `layout.php` | 공통 헤더/푸터, 관리자 사이드바, `render_pagination()` |

---

## 4. 데이터 모델

`database/schema.sql` 기준. 전부 `kitel_rental_` 프리픽스.

| 테이블 | 요점 |
|---|---|
| `categories` | 기자재 분류. `tracking_mode`가 `unique`(개별)면 물건마다 행 하나, `bulk`(개수)면 화면에는 개수만. `max_per_user`로 1인당 대여 한도. `due_days`로 카테고리별 대여 기간(NULL이면 `default_due_days`). `next_serial`은 다음 라벨 번호 |
| `items` | **개체 하나 = 행 하나.** `label`(예: `니퍼-2`, 카테고리명 비정규화 저장), `public_code`(`EQ-XXXXXXXX`, QR URL), `status`, `is_active`(폐기하면 0) |
| `loans` | 대여 1건. `borrower_*`는 스냅샷, `actual_user_*`는 실사용자(준회원 대신 정회원이 빌리는 경우), `related_loan_id`는 묶음 대여의 그룹 키(그 묶음의 첫 `loan_id`) |
| `bundles` / `bundle_categories` | 관련 카테고리 묶음. 스캔·대여 화면에서 함께 다루기 위함 |
| `allowed_groups` | 어느 XE `group_srl`이 대여 권한(`user`)·관리 권한(`admin`)을 갖는지. 기본 seed: 3=정회원(user), 1=관리그룹(admin) |
| `logs` | 모든 상태 변경 이력. **FK 없음**(폐기·삭제와 무관하게 보존). `actor_member_srl`, `before/after_status`, `memo`(상태변경·폐기·강제반납은 사유 필수) |

### 개체(item) vs 수량(quantity)

시스템은 **개체 단위**입니다 — 물건마다 QR·상태·이력이 따로 있습니다. 반면 동방 물품현황표는
**수량 단위**(예: "니퍼 8개")입니다. 이 차이 때문에 CSV 가져오기에 "추가/맞추기" 모드를 둡니다(§6).

---

## 5. 조회 계층 (성능)

`loans`·`logs`는 사용할수록 무한히 커지는 테이블입니다(운영 기준 로그 3,000건 이상).
예전에는 매 요청마다 전 테이블을 PHP 배열로 올려 필터링했으나, 지금은:

- `loan_query()` / `log_query()` — 필터를 SQL `WHERE`로 내리고 `LIMIT`으로 페이지 단위 반환.
  정렬은 이 계층에서 **최신 우선**으로 확정하므로 화면에서 `array_reverse()` 하지 않습니다.
- 대여 목록의 페이지 단위는 "행"이 아니라 "묶음"이라, 개수 관리 묶음이 페이지 경계에서
  갈라지지 않습니다.
- `$state['loans']` / `$state['logs']`에는 표식 객체가 들어 있어, 옛 방식으로 직접 순회하면
  조용히 빈 배열이 아니라 예외로 실패합니다.

---

## 6. CSV 가져오기 / 내보내기

### 내보내기 → 편집 → 가져오기 왕복

`admin/export.php?type=items`로 받은 CSV(`item_id` 포함)를 그대로 다시 가져올 수 있습니다.

- `item_id` 있는 행 → **수정**(위치·특이사항만). 카테고리·상태는 CSV로 바꾸지 않으며,
  값이 다르면 미리보기에 "무시됨"으로 표시됩니다.
- `item_id` 빈 행 → **신규 추가**.
- `item_id` 열은 지우거나 바꾸지 마세요 — 유일한 식별자입니다.

### 수량 CSV (`category,quantity,location,condition_note`)

동방 물품현황표용. `admin/item_new.php`의 CSV 탭에서 `.xlsx`를 끌어다 놓으면
현황표 양식(물품명/현재 수량 블록 반복)을 인식해 자동으로 채웁니다.

두 가지 모드:

| 모드 | `quantity`의 의미 | 언제 |
|---|---|---|
| **추가(add)** | 그 개수만큼 **새로 만듦** | 신규 입고 |
| **맞추기(sync)** | **목표 총 개수**. 현재보다 적으면 폐기 후보, 많으면 추가 | 현황표를 갱신해 다시 넣을 때 |

같은 시트를 "추가"로 두 번 넣으면 개수가 두 배가 됩니다. 보유 현황을 나타내는 시트라면
**맞추기**가 맞습니다.

### 6-3. 카테고리 관리 옵션 일괄 적용

**기자재 > 카테고리 관리**에서 카테고리를 체크한 뒤 상단 바에서 한 번에 적용한다.

| 항목 | 비움 | `0` | 양수 |
|---|---|---|---|
| 관리 방식 | 변경 안 함 | — | 개별/개수 중 선택 |
| 1인당 제한 | 변경 안 함 | 제한 해제 | 그 개수로 제한 |
| **대여 기간(일)** | 변경 안 함 | 기본값(`default_due_days`) 사용 | 그 일수로 지정 |

- **비워 둔 칸은 건드리지 않는다.** 대여 기간만 바꾸려면 나머지를 비워두면 된다.
  (예전에는 관리 방식이 항상 함께 덮어써져서, 1인당 제한만 바꾸려 해도 개별/개수 관리가 같이 바뀌었다.)
- 전체가 하나의 트랜잭션이며, `category.tracking` 로그에 무엇이 어떻게 바뀌었는지 남는다.
- 대여 기간은 대여 시점에 계산되어 그 대여 건의 `due_at`에 확정된다.
  **이미 나간 대여의 반납 예정일은 나중에 기간을 바꿔도 변하지 않는다.**
- 사용자 화면(기자재 상세 · 카테고리 · 대여 목록)에 적용될 기간과 반납 예정일이 표시된다.
- 관리자 대여 등록(`admin/loan_new.php`)에서는 날짜를 직접 지정해 이 설정을 덮어쓸 수 있다.

### 공통

- 가져오기는 **항상 미리보기 후 적용**입니다. 추가·수정·폐기·새 카테고리·건너뛴 행을
  먼저 보여주고, 폐기는 별도 체크박스로 동의해야 반영됩니다.
- 적용은 전체가 **한 트랜잭션**입니다 — 도중 실패하면 아무것도 반영되지 않습니다.
- 대여 중인 기자재는 폐기 대상에서 제외되고 사유와 함께 표시됩니다.

---

## 7. 보안

- **접근 제어** — `public/.htaccess`가 화이트리스트입니다. 기본 전부 거부, 실제 진입점만
  `Require all granted`. `admin/`·`assets/`는 각자의 `.htaccess`로 필요한 것만 다시 엽니다.
  새 진입점 파일을 추가하면 해당 `.htaccess` 목록에도 넣어야 열립니다.
  (vhost에 `AllowOverride All`이 걸려 있어 `.htaccess`가 실제로 적용됩니다.)
- **`app/`·`database/`** — `Require all denied`. 웹에서 직접 접근 불가.
- **권한** — 모든 관리자 화면 첫 줄 `require_admin()`, 대여 화면 `require_borrow_permission()`.
- **CSRF** — 모든 상태 변경 POST에 `csrf_input()` + `require_post()`.
- **신뢰 네트워크** — 대여·반납은 기본적으로 동방 와이파이(`trusted_network_cidrs`)에서만
  허용합니다. `REMOTE_ADDR`을 쓰되, `trusted_proxies`에 등록된 프록시를 거친 요청에 한해서만
  `X-Forwarded-For`를 신뢰합니다(헤더 위조 우회 차단). 기본값은 프록시 목록이 비어 있어
  `REMOTE_ADDR`만 사용합니다.
- 외부 스크립트(jsQR, SheetJS)는 CDN이 아니라 `public/assets/`에 벤더링되어 있습니다.

---

## 8. 설정 (`app/config.php` / 환경변수)

| 환경변수 | 기본값 | 설명 |
|---|---|---|
| `KITEL_RENTAL_MODE` | `production` | `local`이면 파일 DB + fake auth |
| `KITEL_RENTAL_APP_DIR` | (없음) | `app/`을 웹 루트 밖에 둘 때 절대경로 |
| `KITEL_RENTAL_BASE_URL` | `/rental_dev/public` | 앱 내부 링크의 접두 경로 |
| `KITEL_RENTAL_CANONICAL_URL` | `https://kitel.kw.ac.kr/rental_dev/public` | QR에 넣을 절대 URL |
| `KITEL_XE_ROOT` | `/volume1/kitel_web/xe` | XE 루트 (db.config.php·Context 로드용) |
| `KITEL_RENTAL_QR_BACKEND` / `_QRENCODE_PATH` | `qrencode` | QR 생성기 |
| `KITEL_RENTAL_REQUIRE_QR_ON_RETURN` | `0` | `1`이면 기자재 QR 상세 화면에서만 반납 가능 |

`default_due_days`(기본 7일)는 **카테고리에 개별 설정이 없을 때만** 쓰인다.
관리자 화면 **기자재 > 카테고리 관리**의 일괄 적용 바에서 카테고리별 대여 기간을 지정할 수 있다(§6-3).
| `KITEL_RENTAL_REQUIRE_TRUSTED_NETWORK` | (켜짐) | `0`이면 IP 제한 해제 |
| `KITEL_RENTAL_TRUSTED_CIDRS` | `192.168.1.0/24` | 동방 와이파이 대역 (콤마로 여러 개). **실측 필요 — §10** |
| `KITEL_RENTAL_TRUSTED_PROXIES` | (없음) | 앞단 프록시 주소. 비어 있으면 XFF 무시 |

환경변수를 넣기 어려우면 `app/config.php`의 해당 기본값을 직접 수정합니다.

---

## 9. 운영 절차

### 배포 (스테이징 → 검증 → 교체)

```sh
# NAS: /volume1/kitel_web/xe 에서
tar -czf rental_dev.backup-$(date +%Y%m%d-%H%M%S).tar.gz rental_dev   # 1. 백업
# 2. 새 트리를 rental_dev.new 로 업로드 (nested .htaccess 포함되게)
/usr/local/bin/php74 -l $(find rental_dev.new -name '*.php')          # 3. 문법 검사
chmod -R a+rX rental_dev.new
mv rental_dev rental_dev.previous && mv rental_dev.new rental_dev     # 4. 교체
# 5. https://127.0.0.1/rental_dev/public/... 로 -H "Host: kitel.kw.ac.kr" 확인
#    (plain http는 301 리다이렉트되므로 https로 확인)
```

롤백은 `rental_dev.previous` 또는 백업 tar.

### DB 설치 / 마이그레이션 (CLI 전용, `KITEL_RENTAL_INSTALL=1` 필수)

```sh
KITEL_RENTAL_MODE=production KITEL_RENTAL_INSTALL=1 /usr/local/bin/php74 tools/install.php          # schema만
KITEL_RENTAL_MODE=production KITEL_RENTAL_INSTALL=1 /usr/local/bin/php74 tools/install.php --seed   # + 권한/카테고리 seed
```

seed는 반복 실행해도 중복 생성되지 않습니다(`ON DUPLICATE KEY UPDATE`).
`database/migrations/`의 개별 마이그레이션(번들·개수관리·display_no)은 **운영 DB에 이미 적용됨.**

### 사전 점검

```sh
/usr/local/bin/php74 tools/check.php
```

필수 파일 존재 여부 + (운영 모드면) DB 연결·`kitel_rental_*` 테이블·`qrencode`를 확인하고
exit code로 알립니다. 모드는 앱과 동일하게 `config('mode')`로 판단합니다.

---

## 10. 미결 사항

- **신뢰 네트워크 CIDR 실측** — `trusted_network_cidrs` 기본값 `192.168.1.0/24`가 동방
  와이파이와 맞는지 확인되지 않았습니다. 관리자 **설정 > 네트워크 점검**(`admin/network_check.php`)
  페이지를 동방과 외부에서 각각 열어 `판정에 사용된 IP` / `REMOTE_ADDR` / `X-Forwarded-For`를
  비교한 뒤 확정합니다. 프록시를 거치는 구성이면 `trusted_proxies`도 함께 설정.
- **XE 인증 어댑터** — `app/auth.php`의 운영 경로는 XE `Context`에 의존합니다. Rhymix 이전 시
  세션 체계(로드맵 8번)에 맞게 교체가 필요합니다.
- **정책 기본값**
  - 반납 예정일: 카테고리의 `due_days`, 없으면 `default_due_days`(기본 7일).
    사용자 경로는 서버가 계산하며 클라이언트가 보낸 날짜를 신뢰하지 않는다.
  - 반납 후 기자재 상태: 기본 `available`. 반납 폼/강제 반납에서 `broken` 등으로 지정 가능.
  - 임원진 `group_srl=40508`: 기본 관리자 권한에 미포함.
  - 라벨 번호: 폐기 후 빈 번호를 재사용합니다(`니퍼-2`를 폐기하고 새로 만들면 다시 `니퍼-2`).
    QR `public_code`는 매번 새로 발급되므로 시스템은 구분하지만, 이력을 사람이 읽을 때는
    같은 이름의 다른 물건이 섞입니다 — 의도된 동작.

# KITEL 기자재 대여 시스템

기존 XE 홈페이지의 `/rental` 경로에 올려 사용할 독립 PHP 앱입니다. 운영 XE 코어와 회원 테이블은 수정하지 않고, 기자재 대여 데이터는 `kitel_rental_*` 전용 테이블로 분리하는 것을 기준으로 합니다.

## 현재 구현 범위

- PHP 7.4 호환 앱 구조
- 로컬 개발용 fake auth
- 운영 전환용 XE auth 어댑터 자리
- 운영 모드의 `kitel_rental_allowed_groups` 기반 권한 판별
- 관리자 권한 그룹 설정 화면
- 카테고리 기반 자동 라벨 생성
- public_code 자동 생성
- 검증된 서버 내부 `qrencode` 기반 SVG QR 생성 및 라벨 출력 화면
- 대여/반납 처리
- 선택형 QR 확인 기반 반납
- 내 대여 목록/기록
- 관리자 대시보드
- 기자재/카테고리/관리자 대여/대여/연체/로그 관리 화면
- 카테고리명/설명/다음 번호/활성 여부 수정
- 기자재 위치/특이사항/관리자 메모 수정 화면
- 기자재별 대여/반납 기록과 처리 로그 조회
- 사용자별 현재 대여/전체 대여 기록 조회
- 기자재 상태 변경/폐기 처리 사유 필수 로그
- CSV 붙여넣기 기반 초기 기자재 가져오기
- 관리자 대여 등록 시 XE 회원 조회
- 전체 대여 현황 상태/기간/사용자/기자재 필터
- 처리 로그 액션/처리자/기간/대상/메모 필터
- 기자재/전체 대여/미반납/연체/로그 CSV 내보내기
- MySQL용 schema/seed 초안
- 운영 전 사전 점검 스크립트
- 운영 DB 설치/시드 적용 CLI 스크립트

로컬 개발 모드는 `database/local_data.json` 파일을 자동 생성해 동작합니다. DSM 운영 반영 전에는 `database/schema.sql`과 `database/seed.sql`을 적용하고, `app/config.php`의 환경 값을 운영에 맞게 조정하세요.

운영 모드에서는 `KITEL_RENTAL_MODE=production`처럼 `local`이 아닌 값을 사용하면 XE DB 설정을 읽어 `kitel_rental_*` 테이블에 저장합니다. 로컬 모드는 파일 기반 fake DB, 운영 모드는 PDO/MySQL 기반 저장소로 동작하도록 분리했습니다.

## 폴더 구조

```text
public/          웹에서 접근되는 파일
public/admin/    관리자 화면
app/             공통 로직, 인증, 데이터 처리
database/        schema, seed, 로컬 개발 데이터
```

DSM 업로드 시 `public/` 안의 파일을 `/volume1/kitel_web/xe/rental` 또는 `/rental_dev`에 배치하고, `app/`과 `database/`는 가능하면 웹 루트 밖에 둡니다. 웹 루트 안에 둘 경우 직접 접근 차단 설정이 필요합니다.

권장 배치 예시는 다음과 같습니다.

```text
/volume1/kitel_web/xe/rental_dev/        public/ 안의 파일
/volume1/kitel_web/kitel_rental_app/     app/ 안의 파일
/volume1/kitel_web/kitel_rental_data/    database/ 안의 schema/seed/로컬 데이터
```

이 경우 웹 서버 환경 변수에 다음을 지정합니다.

```text
KITEL_RENTAL_APP_DIR=/volume1/kitel_web/kitel_rental_app
KITEL_RENTAL_MODE=production
KITEL_RENTAL_BASE_URL=/rental_dev
KITEL_RENTAL_CANONICAL_URL=https://kitel.kw.ac.kr/rental_dev
KITEL_XE_ROOT=/volume1/kitel_web/xe
KITEL_RENTAL_QR_BACKEND=qrencode
KITEL_RENTAL_QRENCODE_PATH=qrencode
KITEL_RENTAL_REQUIRE_QR_ON_RETURN=0
```

환경 변수 지정이 어렵다면, 개발 검증 중에는 `app/` 폴더를 `/rental_dev/_app`으로 복사할 수도 있습니다. 이때 `_app/.htaccess`가 직접 접근을 차단하는지 반드시 확인하세요. 안정화 뒤 `/rental`에 반영할 때는 `KITEL_RENTAL_BASE_URL`과 `KITEL_RENTAL_CANONICAL_URL`을 `/rental` 기준으로 바꿉니다.

## 운영 전 확인할 것

- XE 로그인 세션에서 `Context::get('logged_info')`가 정상 동작하는지 확인
- `xe_member_group_member` 기준 정회원/관리그룹 판별 확인
- `kitel_rental_allowed_groups` seed 적용 여부 확인
- `public_code` QR URL이 `https://kitel.kw.ac.kr/rental/item.php?code=...` 형태로 열리는지 확인
- QR 인쇄 화면의 SVG QR이 실제 모바일 카메라에서 스캔되는지 확인
- 운영 서버에 `qrencode`를 설치하고 `php tools/check.php`에서 `QR_BACKEND`가 OK인지 확인
- 상태 변경 POST에 CSRF가 적용되는지 확인
- 관리자 강제 반납과 상태 변경 사유가 로그에 남는지 확인
- 운영 서버 PHP 7.4에서 전체 PHP 문법 검사를 실행
- 운영 서버에서 `php tools/check.php` 사전 점검 실행
- 운영 서버에서는 `KITEL_RENTAL_MODE=production`을 명시하고, 환경 변수 누락 시 외부 호스트에서 local mode가 차단되는지 확인

## DB 설치

운영 DB 설치는 CLI에서만 실행합니다. 실수 방지를 위해 `KITEL_RENTAL_INSTALL=1`이 없으면 아무 작업도 하지 않습니다.

```text
KITEL_RENTAL_MODE=production KITEL_RENTAL_INSTALL=1 php tools/install.php
KITEL_RENTAL_MODE=production KITEL_RENTAL_INSTALL=1 php tools/install.php --seed
```

첫 번째 명령은 `database/schema.sql`만 적용합니다. `--seed`를 붙이면 권한 그룹과 기본 카테고리 seed도 함께 적용합니다. seed는 반복 실행해도 같은 그룹/카테고리를 중복 생성하지 않도록 구성했습니다.

## 아직 결정이 필요한 정책

- 기본 반납 예정일: 현재 로컬 MVP는 7일 뒤를 기본값으로 둡니다.
- 반납 후 상태: 현재 MVP는 즉시 `available`로 되돌립니다.
- 반납 시 QR 확인: 현재 기본값은 false이며 `KITEL_RENTAL_REQUIRE_QR_ON_RETURN=1`로 켤 수 있습니다. 이 값을 켜면 `내 대여 목록`에서는 바로 반납할 수 없고, 기자재 QR 상세 화면에서만 반납 처리됩니다.
- 임원진 `group_srl=40508` 권한: 현재 기본 관리자 권한에 포함하지 않습니다.
- QR 출력 크기: 현재 A4 인쇄용 라벨 그리드로 제공합니다.

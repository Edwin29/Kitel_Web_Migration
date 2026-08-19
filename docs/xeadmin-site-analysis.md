# 기존 사이트(XEadmin/XpressEngine) 분석

작성일: 2026-08-19
대상: `kitel_web/` (NAS Synology에서 실제로 회수한 운영 소스 + DB 백업)

## 1. 개요

- 사이트: KITEL(전자연구회) 동아리 홈페이지 (`kitel.kw.ac.kr`, 광운대학교) — 메인페이지 콘텐츠에 "전자연구회 홈페이지"라는 문구가 있어 정식 명칭으로 추정됨
- 엔진: **XpressEngine(XE)** 기반. 팀 내에서 "XEadmin"으로 불리지만 실제로는 XE 코어 + 커스텀 확장 구조.
- Rhymix는 XpressEngine에서 파생된 엔진이라 모듈/스킨 구조, DB 테이블 네이밍(`xe_` prefix 등)이 유사해 마이그레이션 난이도는 완전 이종 엔진 대비 낮은 편.
- 사이트는 약 15년 전에 구축되어 자세한 인수인계 문서가 없고, 현재 담당자도 일부 기능(특히 출석 관리)의 실사용 여부만 파악하고 있는 상태.

## 2. 소스 구성 (`kitel_web/`)

```
kitel_web/
├── backup_20260417.sql   # 최신 운영 DB 백업 (61MB, 현재 기준 자료)
├── localhost.sql         # 2019-05 로컬 개발용 덤프 (구버전, 참고용)
├── config/, env/, cache/, phpMyAdmin/   # ⚠️ 아래 참고 — xe/ 내부 것과 별개, 용도 확인 필요
├── files/                 # 비어있음 — 실제 업로드 경로 아님 (7번 참고)
└── xe/                    # XE 코어 + 모듈/레이아웃/애드온 + 커스텀 앱 — 실제 운영 소스
    ├── modules/           # 표준 XE 모듈만 존재 (board, comment, member, document, page 등)
    ├── addons/            # autolink, captcha, member_extra_info, resize_image 등
    ├── layouts/            # default, root_basic_layout, sketchbook5, user_layout, xedition
    ├── widgets/
    ├── files/              # 실제 업로드/캐시/설정 디렉터리 (7번 참고)
    ├── rental/            # 운영 반영용 대여 시스템 진입점 + 사전 점검 스크립트
    └── rental_dev/        # 대여 시스템 개발 소스 (아래 5번 참고)
```

> ⚠️ **경로 정정**: 업로드 파일 저장 위치는 `kitel_web/files/`가 아니라 **`kitel_web/xe/files/`**임 (최초 분석 시 착오 있었음, 정정 완료).
>
> `kitel_web/` 최상위의 `config/`, `env/`, `cache/`는 `xe/files/` 하위의 동명 폴더와 **내용이 다름**(예: `db.config.php`의 DB 계정, `default_url` 스킴, `env/`의 XE 버전 번호가 서로 다름 — 최상위 `env/`는 `1.7`, `xe/files/env/`는 `1.11`). 최상위 폴더는 이전 버전/이관 과정에서 남은 잔재로 추정되며, 실제 운영 소스는 `xe/` 하위 기준으로 보는 것이 맞음. 정확한 용도는 동아리 측에 재확인 필요.
>
> **DSM Web Station 설정으로 재확인**: `kitel_web` PHP 서비스의 Document root가 `kitel_web/xe`로 설정되어 있어, `xe/`가 실제 서비스 중인 문서 루트임이 확인됨.

### Git 추적 범위

- 저장소 루트는 `D:\Projects\Kitel_Web_Migration` 그대로 유지하고, `.gitignore`를 다음과 같이 구성함: `kitel_web/` 전체는 기본 제외하되 `kitel_web/xe/`(실제 서비스 소스)만 추적 대상으로 재포함, 그 안에서도 `kitel_web/xe/files/`(업로드·캐시·첨부파일 ~10GB, 개인정보 가능성)는 다시 제외.
- `kitel_web/backup_20260417.sql`, `localhost.sql`, 최상위 `config/env/cache/phpMyAdmin/` 잔재는 계속 git 추적 대상 아님.
- `xe/rental_dev/`에 있던 중첩 `.git`은 확인 결과 진짜 서브모듈이 아니라 **이 저장소(Edwin29/Kitel_Web_Migration)와 완전히 동일한 커밋**(`22458cc`)을 가리키는 중복 클론이었음. 내용 손실 없이 삭제하고 `rental_dev`를 상위 저장소의 일반 파일로 편입함.

## 3. 사이트 내비게이션 구조 (IA)

`xe_menu_item`(공개 메뉴, `menu_srl=61`) 기준 실제 GNB 트리. 관리자 메뉴(`menu_srl=46684`)는 제외.

```
Main (mainpage)                              — page 모듈, "전자연구회 홈페이지" 커스텀 HTML
KITEL (kitelinfo)
├── 키텔소개 (kitelinfo)                      — page
│   ├── 키텔 연혁 (history)                   — page
│   ├── 키텔 활동 (activity)                  — page
│   └── 키텔 일상 (kitel_daily)               — page
├── 연구분야 (field)                          — page
├── 회칙 (rule)                               — page
├── 임원진소개 (staff)                        — page
├── 일정 (schedulepage)                       — page (별도로 board 모듈 'schedule'도 존재)
├── 회장인사말 (chairman)                     — page
├── 키텔송 (kitelsong)                        — page
└── 임원진 전용 (admingroup)                  — 🔒 group_srl 1(관리그룹), 40508(임원진)만 접근
    ├── 게시판 (adminboard)                   — board, 🔒 동일 제한
    └── Drop Box (외부 링크, 새창)

Community (freeboard)
├── 공지사항 (notice)
├── 자유게시판 (freeboard)
├── 익명게시판 (anonymous)
├── 건의사항 (qna)
└── 출석게시판 (attendance)                   — ⚠️ board 모듈. 아래 6번 항목의 미사용 attendance 커스텀 모듈과는 별개(단순 게시판으로 추정, 재확인 필요)

Media (photo)
├── 사진게시판 (photo)
└── 영상게시판 (movie)

X - File (xfile)                             — page 랜딩 + 게시판 하나(xfile_board)를 카테고리별 웹진(webzine) 뷰로 나눠 보여주는 구조
├── 1. 신입생 기초 (xfile_board?category=40656&listStyle=webzine)
├── X-file 전체보기 (xfile_board 전체)
├── 2. Analog / 3. Digital / 4. C language / 5. AVR / 6. Applications / 7. Embedded (동일 게시판의 카테고리별 뷰)

Project (project)                             — page
├── 완료된 프로젝트 (completedproject)         — page
│   ├── 36기 강다혜 (dahye2012)                — page, 개인 프로젝트 페이지로 추정(레거시 항목일 가능성)
│   └── KSP 2011 (ksp2011)                     — page
├── Drone (wp_2014)                            — board
└── IOelec (page_sWPE62)                       — page
    └── RaffeeMachine (board_sAKp20)           — board

Study (study)                                 — page
├── 일반자료실 (pds)
├── 강좌게시판 (lecture)
├── 축제게시판 (festival)
├── 품평회게시판 (event)
├── C언어 과제 게시판 (board_sBos03)
└── 과제게시판 (assignment)

Contact us (contactsus)                       — page
```

- **board(게시판) vs page(정적 페이지) 구분이 뚜렷함**: 소개/연혁/회칙/인사말 등은 전부 `page` 모듈(고정 HTML), 실제 사용자 글쓰기가 필요한 곳만 `board`. Rhymix IA 설계 시에도 이 구분을 유지하는 게 자연스러움.
- **X-File 메뉴**는 게시판 하나(`xfile_board`)를 카테고리(`category` 파라미터)별로 잘라 여러 메뉴 항목으로 노출하는 패턴 — 일종의 커리큘럼 아카이브. Rhymix에서는 카테고리 필터 링크나 카테고리별 게시판 분리로 재설계 검토 필요.
- **임원진 전용 메뉴**(`admingroup`)는 `group_srls` 필드로 `1,40508` 그룹만 접근 가능하게 제한되어 있음 — 회원 등급 기반 접근 제어가 실제로 쓰이고 있음 (4번 참고).
- `완료된 프로젝트 > 36기 강다혜` 같은 개인명 메뉴 항목은 오래된 학기별 활동 아카이브로 보이며, 신규 사이트에 그대로 가져갈지 동아리 측 확인 필요.

## 4. 회원 등급 체계

`xe_member_group` 기준 실제 운영 중인 회원 그룹:

| group_srl | 이름 | 설명 |
|---|---|---|
| 1 | 관리그룹 | 홈페이지 관리(전체 관리자) |
| 40508 | 임원진 | 동아리 임원진 — 위 IA의 "임원진 전용" 메뉴 접근 권한과 직결, `rental_dev` README에도 언급된 그룹 |
| 3 | 정회원 | 동아리 정회원 |
| 2 | 준회원 | 동아리 준회원 |
| 41424 | 특별회원 | 동아리 졸업회원 |
| 40407 | 가입대기 | 신규 가입 시 기본 배정되는 대기 그룹(승인 전) |

Rhymix에서도 최소 "관리자 / 임원진 / 정회원 / 준회원 / 졸업회원 / 가입대기" 6단계 등급 체계와, 메뉴·게시판 단위의 그룹별 접근 제어 기능이 필요함.

## 5. 사이트 구성 (게시판 / 레이아웃)

`xe_modules` 테이블 기준 실제 운영 중인 게시판(모듈 = board):

| mid | 이름 |
|---|---|
| notice | 공지사항 |
| freeboard | 자유게시판 |
| pds | 일반자료실 |
| photo | 사진게시판 |
| movie | 영상게시판 |
| qna | 건의사항 |
| anonymous | 익명게시판 |
| lecture | 강좌게시판 |
| assignment | 과제게시판 |
| festival | 축제게시판 |
| event | 품평회게시판 |
| schedule | 일정 |
| xfile_board | X-file Board |
| wp_2014 | 드론 프로젝트 |
| board_sBos03 | C언어 과제 게시판 |
| board_sAKp20 | RaffeeMachine |
| attendance | 출석게시판 (3번 IA 참고) |
| adminboard | 게시판 (임원진 전용) |

- 대부분 `sketchbook5` 스킨(PC) / `sketchbook5Mobile`(모바일) 사용.
- 레이아웃은 5종 존재(`default`, `root_basic_layout`, `sketchbook5`, `user_layout`, `xedition`) — 실제 어떤 레이아웃이 메인에 쓰이는지는 `xe_menu`/`xe_modules.layout_srl` 매핑으로 추가 확인 필요.
- 디자인은 신규로 교체 예정이므로, 이 목록은 **"어떤 게시판/기능이 존재했는지"** 확인용이지 스킨 자체를 재사용할 필요는 없음.

## 6. 커스텀 확장: 기자재 대여 시스템 (`xe/rental_dev`)

XE 코어와 완전히 분리된 **독립 PHP 앱**으로 이미 상당히 완성되어 있음.

- 전용 테이블(`kitel_rental_*`)만 사용, XE 회원/코어 테이블은 건드리지 않음. 인증만 XE 로그인 세션(`Context::get('logged_info')`, `xe_member_group_member`)에 의존.
- 기능: 기자재/카테고리 관리, 대여·반납, QR 라벨 생성(SVG, `qrencode`), 관리자 대시보드, 대여 이력/연체 관리, CSV 가져오기/내보내기, 권한 그룹 기반 접근 제어.
- 로컬 개발 모드(JSON 파일 기반 fake DB)와 운영 모드(PDO/MySQL)가 분리되어 있고, 운영 배치 경로(`/rental`)와 개발 경로(`/rental_dev`)를 환경변수로 구분.
- **Rhymix 마이그레이션과 무관하게 별도로 유지/이식해야 하는 자산.** 인증 어댑터만 Rhymix 세션 체계에 맞게 교체하면 재사용 가능해 보임.
- README에 명시된 "임원진 `group_srl=40508`" 참조가 4번 항목의 실제 임원진 그룹과 정확히 일치함 — 대여 시스템 관리자 권한을 임원진 그룹과 연동할 계획이었던 것으로 보임(현재는 기본 관리자 권한에 미포함 상태).

## 7. 회원(Member) 커스텀 필드

기본 XE 가입 필드 외 동아리 전용 확장 필드:

- `notation` — 기수
- `phone` — 핸드폰 번호
- `address` — 주소

Rhymix 회원가입 폼 설계 시 동일 필드 이식 필요 (기수 필드는 동아리 운영에 필수적으로 보임).

## 8. DB에는 있으나 사실상 미사용/소스 없는 기능

`backup_20260417.sql`에는 테이블과 설정 데이터가 남아 있지만, `xe/modules/`에는 해당 모듈 소스가 전혀 없는 기능들이 있음:

- `attendance` (출석 관리 — weekly/monthly/yearly 통계 테이블 포함, 데이터량 큼). 3번 IA의 "출석게시판"(`mid=attendance`, board 모듈)과는 이름만 같은 별개 기능으로 보이며, 실제 사용 여부는 확인 필요.
- `socialxe` (소셜 로그인/공유 연동)
- `kin` (지식iN 스타일 Q&A)
- `issue`, `issues_*` (이슈 트래커)
- `project`, `projects` (프로젝트 관리) — 3번 IA의 "Project" 메뉴(page 모듈 기반)와는 별개의 XE 코어 프로젝트 관리 모듈
- `join_extend` (가입 확장 — 초대/쿠폰)
- `wizardxe`

확인 결과 이 소스코드가 별도로 존재하는 것이 아니라 **애초에 회수 대상에 포함되지 않았으며, 특히 출석 관리 기능은 실제로 전혀 사용되지 않는 것으로 파악됨.** 마이그레이션 범위에서 제외하고 데이터는 참고용 아카이브로만 남기는 방향이 합리적으로 보임 (필요 시 사용 여부를 동아리 측에 재확인).

## 9. 파일(첨부/업로드) 관련

실제 업로드/캐시 디렉터리는 **`xe/files/`**임 (최초 분석 시 최상위 `kitel_web/files/`로 잘못 파악했던 부분 정정, 2번 참고). NAS에서 회수하여 실제 소스로 채워 넣었고, **`attach/`(게시글 첨부파일, 용량 약 10GB)만 제외**하고 나머지는 실제 내용이 들어있음.

`xe/files/` 하위 폴더 구조 (2026-08-19 기준):

| 폴더 | 크기 | 내용 |
|---|---|---|
| `attach/` | (제외) | 게시글 첨부파일 — 용량(~10GB) 문제로 이 워크스페이스에는 미포함. 실제 이관 작업 시에만 NAS에서 별도로 가져와야 함 |
| `cache/` | 46M | 모듈/애드온/문서분류/언어 등 XE 런타임 캐시 — 재생성 가능, 이관 불필요 |
| `config/` | 6K | `db.config.php` 등 실제 운영 설정 (최상위 `kitel_web/config/`와는 내용이 다름 — XE가 런타임에 실제로 참조하는 쪽은 이 위치) |
| `env/` | 3K | XE 설치 버전(`1.11`) 등 환경 정보 |
| `faceOff/` | 228K | XE Layout 모듈의 **레이아웃 편집기(FaceOff) 미리보기 캐시** — `modules/layout`의 `faceoff.js` 기능과 연결된 언어별 캐시(`{module_srl}/{lang}.cache.php`). 정상적인 XE 코어 캐시로 확인됨 |
| `member_extra_info/` | 1M | `addons/member_extra_info` 애드온의 회원 추가정보 저장 데이터(이미지, 쪽지 플래그 등) |
| `ruleset/` | 6K | 로그인/회원가입 폼 검증 규칙 XML |
| `site_design/` | 4K | 사이트 디자인 설정(`design_0.php`) |
| `thumbnails/` | 37M | 이미지 썸네일 캐시 — 재생성 가능 |

- 캐시성 폴더(`cache/`, `thumbnails/`, `faceOff/`)는 Rhymix에서 새로 생성되므로 마이그레이션 대상이 아님.
- 실제로 이관을 검토해야 할 대상은 `attach/`(첨부파일 원본)와 `member_extra_info/`(회원 추가 데이터) 정도.

## 10. 기타 참고 사항

- 구글 애널리틱스(UA-69507041-1)가 `module` 설정의 `htmlFooter`에 하드코딩되어 있음 — Rhymix 이전 시 GA4 등으로 교체 검토 필요.
- `backup_20260417.sql`은 Synology NAS의 MariaDB에서 `mysqldump`로 추출된 것으로 보이며, MariaDB 시스템 테이블(`mysql` 스키마 등)이 함께 포함되어 있음 — 실제 이관 시에는 `xe_*`, `kitel_rental_*` 등 서비스 테이블만 선별 필요.
- `localhost.sql`(2019년 로컬 개발 덤프)은 `backup_20260417.sql` 대비 구버전 데이터라 현재 상태 분석에는 참고 우선순위가 낮음.
- `xe/app/`은 `xe/rental_dev/app/`과 동일한 내용으로, 운영 서버 웹 루트 안에 실제로 배포된 대여 시스템 백엔드 로직임. README는 "웹 루트 밖에 두거나 직접 접근 차단 필요"를 권장하는데, 실제로는 웹 루트 안에 있지만 `xe/app/.htaccess`에 `Require all denied`가 설정되어 있어 직접 접근은 차단된 상태로 확인됨.

## 11. 다음 단계 제안

1. Rhymix 로컬 개발 환경 구축 (PHP 버전 호환성 확인 — 기존 XE는 PHP 5.x/7.x대 기준 코드가 섞여 있어 Rhymix 요구 버전과 비교 필요)
2. 게시판/회원 데이터 마이그레이션 스크립트 설계 (`xe_documents`, `xe_comments`, `xe_member`, `xe_member_group_member` 등 → Rhymix 스키마)
3. `xe/files/attach/` (첨부파일 원본, ~10GB)을 NAS에서 확보하여 `xe_files` 메타데이터와 매핑 검증
4. `rental_dev` 인증 어댑터를 Rhymix 세션 체계에 맞게 교체하는 방안 설계
5. 디자이너와 함께 신규 IA(정보구조)/디자인 확정 — 3번 내비게이션 트리를 기준으로 유지/통폐합할 메뉴·게시판 결정 (특히 X-File 웹진형 카테고리 구조, 오래된 개인/학기별 아카이브 페이지 존치 여부)

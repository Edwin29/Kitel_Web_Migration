# NAS 장애 조사 (CPU 과부하 / 권한 구조 / 메일 전송)

작성일: 2026-09-07
조사 방식: Tailscale SSH로 운영 NAS(Synology DS218, DSM 7.2.2)에 직접 접속해 실측. 세 가지 보고된 문제를 각각 조사함.

---

## 1. CPU 99% / 서버 다운

### 하드웨어 현황
Synology **DS218**: ARM 쿼드코어 4개, **RAM 1.8GB**(교체/증설 불가 모델). 저장소는 volume1(SSD, Crucial MX500 1TB), volume2(HDD, 4TB 7200rpm).

### 실측 결과 (2026-09-07 접속 시점)
```
load average: 1.95, 2.01, 2.00   (4코어 기준 평균 50% 상시 사용)
Mem:   1.8Gi total / 503Mi used / 160Mi free / 1.2Gi buff-cache
Swap:  2.0Gi total / 894Mi used   ← 스왑을 상시 45%나 쓰고 있음
```
php-fpm 워커 4개가 동시에 각각 35~49% CPU를 쓰고 있어(합산 150~200%), 트래픽이 조금만 몰려도 4코어를 다 채우는 상황.

### 원인 진단: 소프트웨어 과밀 설치
`synopkg list`로 확인한 결과, 이 1.8GB짜리 기기에 다음이 **전부 동시에 설치되어 상시 구동 중**:

- **SynoFinder** (Elasticsearch 기반 전체 검색 인덱서) — `synoelasticd`, `fileindexd` 등, 자료실 실사(311GB, roadmap.md 2-4번)를 포함한 전체 공유폴더를 대상으로 인덱싱
- **Synology Photos** — 얼굴 인식(`synofoto-face-extraction`), 인물 클러스터링(`synofoto-person-clustering`), 위치정보(`synofoto-geocoding`), 썸네일 생성 등 AI 파이프라인이 데몬으로 상시 대기 + 전용 **PostgreSQL** 인스턴스
- **Synology Drive** — 동기화 엔진(`syncd`), 자체 **Redis** 서버, 클라우드 워커
- **DirectoryServer(LDAP)**, **HyperBackup**, **AntiVirus**, **DNS Server**, **WebDAVServer**, **HybridShare**, **ActiveInsight**, **LogCenter**
- PHP 세 버전(7.4/8.0/8.2)과 Node.js **여섯 버전**(v12/14/16/18/20/22)이 전부 패키지로 설치되어 있음(각 패키지가 자기 버전을 요구해서 누적된 것으로 보임)
- 여기에 MariaDB(사이트 DB)까지 — **최소 3개의 서로 다른 DB 엔진**(MariaDB, SynoFinder용 PostgreSQL, Photos용 PostgreSQL)이 동시에 떠 있음

이 정도 소프트웨어 스택은 원래 훨씬 강력한 기종(RAM 4~8GB 이상)을 전제로 함. 지금 상시 스왑 45%를 쓰고 있다는 건 **평상시에도 이미 물리 메모리를 초과해서 돌아가고 있다는 뜻** — 사용자 트래픽, Photos 재인덱싱(사진 업로드 직후), Drive 동기화, 백업, 바이러스 검사 등이 겹치는 순간 스왑이 폭증하고, 이때 리소스 모니터에는 CPU 99%로 보임(스왑 I/O 대기와 페이지 폴트 처리가 CPU 사용량으로 잡히는 게 리눅스의 흔한 특징 — 실제로는 "메모리 부족"인데 화면엔 "CPU 문제"로 나타남). "특정 프로그램 삭제"로 빈도가 줄었다는 증언과도 정확히 들어맞음(메모리 압박이 줄어든 것).

### 소프트웨어적으로 해결 가능한가 — 예, 부분적으로
하드웨어(RAM) 자체는 못 늘리는 기종이지만, **당장 시도할 수 있는 것들**:

| 조치 | 예상 효과 | 비고 |
|---|---|---|
| **SynoFinder 비활성화 또는 인덱싱 대상 축소** | 큼 | 지금은 전체 공유폴더(311GB+)를 인덱싱 대상으로 삼고 있을 가능성 — 실제로 "파일 이름/내용 검색"을 자주 쓰는지 확인 후, 안 쓰면 완전 제거 권장 |
| **Synology Photos AI 기능(얼굴인식/인물클러스터링) 끄기** | 중~큼 | 검색 편의 기능일 뿐 필수 아님, 99GB 사진 라이브러리 대상 AI 처리가 상시 대기 중인 것 자체가 부담 |
| **php-fpm 워커 수 제한** | 중 | 현재 설정 확인 필요 — 워커 수를 낮추면 개별 요청은 느려지지만 스왑까지 밀려나는 전면 다운은 줄어듦(느린 것과 죽는 것 중 느린 쪽을 선택) |
| **HyperBackup/AntiVirus 스케줄을 새벽 시간대로 분산, 겹치지 않게 조정** | 중 | 지금 스케줄이 서로 겹치는지 DSM Control Panel에서 직접 확인 필요(SSH로는 스케줄 세부 내용 확인 제한적) |
| **미사용 Node.js 버전 정리** | 소 | 6개 버전이 각각 디스크/설치 오버헤드를 차지, CPU엔 큰 영향 없지만 정리 대상 |
| **swappiness 값 조정** | 소~중 | 커널이 스왑을 더 늦게 쓰도록 튜닝 가능(단, 잘못 만지면 OOM kill 위험이 커짐 — 신중히) |

**근본 해결은 아님**: 위 조치들은 여유를 만들어줄 뿐, "여러 서비스가 동시에 몰리면 다시 부족해질" 근본 구조는 그대로임. Rhymix 이전 후 신규 사이트를 이 NAS에 그대로 얹을지, 아니면 신규 사이트만이라도 별도로 분리 호스팅할지는 배포 전환 계획(roadmap.md 9번) 논의 시 함께 고려할 가치가 있음.

---

## 2. 권한 구조 (NAS Drive / Photos / DSM / 홈페이지)

### 실측한 구조: 서로 동기화 안 되는 3개의 독립된 신원 체계

1. **DSM 로컬 계정** (5개): `admin`, `khcha`(39기 차강현, 관리자로 표기), `psh`(38기 박상현), `supervisor`, `sshadmin`
2. **LDAP 디렉터리 서버**(`DirectoryServer` 패키지, `dc=kitel,dc=kw,dc=ac,dc=kr`) — 동아리 등급별 그룹 4개 존재: `administrators`, `정회원`, `졸업생`, `임원단`. DSM이 이 LDAP을 실제로 신뢰(join)하고 있어서 공유폴더 ACL에 `정회원@kitel.kw.ac.kr` 같은 이름을 직접 쓸 수 있는 상태 — **NAS Drive/Photos/File Station 등 파일 서비스 권한은 이 LDAP 그룹 기준으로 관리되는 게 맞음**
3. **홈페이지(XE)** — `xe_member`/`xe_member_group` DB 테이블로 완전히 독립. LDAP과 **어떤 연결도 없음**. 회원이 홈페이지에서 정회원이어도 LDAP 쪽 `정회원` 그룹에 자동으로 들어가지 않고, 반대도 마찬가지 — **수작업으로 양쪽을 따로 관리해야 하는 구조**.

### 실제 ACL을 열어본 결과 — 이미 뒤섞여 있음
`kitel_web`, `document`, `임원단` 공유폴더의 ACL을 직접 조회해보니, 한 폴더 안에 다음이 전부 섞여 있음:
- LDAP 그룹(`정회원@kitel.kw.ac.kr`, `임원단@kitel.kw.ac.kr`, `졸업생@kitel.kw.ac.kr`, `administrators@kitel.kw.ac.kr`)
- 로컬 DSM 사용자(`khcha`, `admin`, `supervisor`, `sshadmin`)
- **패키지가 자동 생성한 서비스 계정으로 보이는 항목들**(`user:Chat`, `user:office`, `user:PDFViewer`, `user:SynologyDrive`)이 `임원단` 폴더에 개별적으로 rwx 권한을 갖고 있음 — 사람이 의도적으로 부여했다기보다 DSM UI에서 실수로 클릭했거나 패키지 설치 시 자동 추가됐을 가능성이 높음

### 왜 "한쪽을 바꾸면 다른 쪽이 조용히 마비"되는지
DSM의 "공유폴더 권한" 화면은 겉보기엔 사용자별 읽기/쓰기/거부 3단 토글이지만, 실제로는 그 뒤에서 File Station/SMB/WebDAV/Synology Drive/Photos 각각에 대해 **별도의 ACL 항목을 동시에 재작성**함. 위에서 확인했듯 한 폴더에 10~14개의 개별 ACL 항목이 뒤섞여 있는 상태에서, 관리자가 "이 사람 하나만" 고치려고 UI를 조작해도 실제로는 그 폴더에 연결된 여러 서비스의 권한이 한꺼번에 재계산됨 — 그래서 의도하지 않은 서비스가 갑자기 막히는 현상이 재현 가능한 구조로 보임.

### 재정립 방향 제안
1. **원칙 확정**: "파일 서비스(NAS Drive/Photos/File Station)는 LDAP 그룹 기준, 홈페이지는 xe_member 기준" — 이 둘을 억지로 통합하려 하지 말고, **각 체계의 소스오브트루스를 명확히 분리해서 문서화**하는 게 현실적(완전 통합은 XE→LDAP 연동 개발이 필요한 큰 작업이라 이번 마이그레이션 범위 밖으로 보임)
2. **ACL 정리**: `임원단` 등 핵심 폴더의 개별 서비스 계정(Chat/office/PDFViewer/SynologyDrive) 권한이 의도된 것인지 점검 후 불필요하면 제거
3. **변경 전 스냅샷 습관화**: 공유폴더 권한을 바꾸기 전에 `synoacltool -get <경로>` 결과를 저장해두면, 사고 발생 시 "롤백"이 아니라 "정확히 뭐가 바뀌었는지 diff"로 원인을 바로 찾을 수 있음(이번처럼 원인 모른 채 롤백만 하는 상황 방지)
4. **신규 Rhymix 사이트도 동일한 함정 주의**: Rhymix 역시 자체 회원 테이블(`rx_member`)을 쓰므로, LDAP과 통합하지 않는 이상 이 구조적 분리는 마이그레이션 후에도 그대로 이어짐 — 다만 최소한 "그런 구조다"라는 걸 팀이 인지하고 있으면 이번 같은 사고는 줄어듦

---

## 3. 메일 전송 안 됨 (비밀번호 찾기 인증메일 등)

### 원인 확정: 인증 토큰 만료, 갱신 안 됨

XE의 메일 발송 경로를 추적한 결과:

```
XE Mail.class.php (PHPMailer 래핑) → PHP mail() → sendmail_path(/usr/bin/ssmtp -t) → Gmail SMTP
```

`/etc/ssmtp/ssmtp.conf`는 비어있고(2018년부터 0바이트), 실제 설정은 `root` 전용 홈 디렉터리(`/root/.ssmtp/ssmtp.conf`)에 있어 일반 조사 계정으로는 내용을 볼 수 없었음. 하지만 **직접 테스트 발송을 해본 결과** 원인이 명확히 드러남:

```
[<-] 220 smtp.gmail.com ESMTP ...
[->] AUTH XOAUTH2 dXNlcj13b25jaGVvbDA4MDNAZ21haWwuY29tAWF1dGg9QmVhcmVy...
[<-] 334 eyJzdGF0dXMiOiI0MDAiLCJzY2hlbWVzIjoiQmVhcmVyIiwic2NvcGUiOiJodHRwczovL21haWwuZ29vZ2xlLmNvbS8ifQ==
ssmtp: Authorization failed
```

디코딩하면 `{"status":"400", ...}` — **Google이 "이 OAuth2 액세스 토큰은 만료/무효"라고 응답**하는 표준 에러. 즉:

- 메일 릴레이 계정은 **개인 Gmail 계정**(OAuth2로 인증)으로 설정되어 있음
- OAuth2 액세스 토큰은 보통 **1시간**이면 만료되고, 계속 쓰려면 refresh_token으로 주기적으로 재발급받아야 함
- **ssmtp 자체에는 OAuth2 토큰을 자동 갱신하는 기능이 없음**(2000년대에 만들어진 아주 단순한 도구라 애초에 OAuth2 개념이 없던 시절 것)
- 시스템 전체를 뒤져봤지만 토큰을 갱신해주는 cron/스크립트를 찾지 못함 — 즉 **누군가 최초 설정 시 토큰을 한 번 수동으로 넣어뒀고, 그 토큰이 만료된 뒤로는 계속 실패만 하고 있는 상태**로 보임(일시적 장애가 아니라 구조적으로 고쳐지지 않는 상태)

### 해결 방법
ssmtp는 OAuth2를 제대로 지원하지 못하므로, OAuth2를 계속 쓰는 방향은 권장하지 않음. 다음 중 택1:

1. **Gmail 앱 비밀번호(App Password)로 전환** (가장 간단) — Google 계정에서 2단계 인증을 켜고 앱 비밀번호를 발급받아 `ssmtp.conf`에 `AuthUser`/`AuthPass`로 넣으면 끝. 갱신 걱정 없음(비활성화하지 않는 한 만료 안 됨). 단, 여전히 "개인 Gmail 계정에 사이트 메일 발송을 의존"하는 구조는 유지됨.
2. **동아리 전용 발신 메일 서비스로 교체** (권장) — 예: Gmail Workspace(학교/동아리 도메인 있으면), 또는 SendGrid/Mailgun/Amazon SES 같은 무료 티어가 있는 트랜잭션 메일 서비스. 개인 계정에 의존하지 않아 인수인계 시에도 안전함. Rhymix도 표준 SMTP 발송을 지원하므로 마이그레이션 시점에 같이 정리하기 좋은 타이밍.
3. **Rhymix 자체 메일 설정 활용** — Rhymix는 관리자 화면에서 SMTP 서버(호스트/포트/계정)를 설정할 수 있어(레거시 XE의 `useSMTP()`/`useGmailAccount()`와 동일 계열 기능), 마이그레이션 시 여기서 앱 비밀번호 기반 SMTP를 새로 설정하면 이 문제 자체가 해소됨.

**주의**: 실제 수정(비밀번호 재발급, `ssmtp.conf` 교체 등)은 `root` 권한이 필요하고 Google 계정 소유자의 동의가 있어야 하는 작업이라 이번 조사에서는 진행하지 않음 — 원인 확인까지만 완료.

---

## 요약

| 문제 | 원인 | 소프트웨어로 해결 가능? |
|---|---|---|
| CPU 99%/다운 | 1.8GB RAM 기기에 SynoFinder·Photos AI·Drive·LDAP·백업·AV 등 무거운 패키지 과밀 설치, 상시 스왑 45% 사용 | 부분적 — 불필요 기능 정리로 여유 확보 가능, 근본 해결은 하드웨어 한계 |
| 권한 구조 혼란 | DSM/LDAP(파일 서비스)와 XE(홈페이지)가 완전히 분리된 신원 체계, ACL에 서비스 계정까지 뒤섞여 있음 | 예 — 원칙 확정 + ACL 정리로 개선 가능, 완전 통합은 별도 개발 필요 |
| 메일 미전송 | 개인 Gmail OAuth2 토큰 만료, 갱신 메커니즘 없음(구조적 고장) | 예 — 앱 비밀번호 또는 전용 메일 서비스로 교체하면 즉시 해결 |

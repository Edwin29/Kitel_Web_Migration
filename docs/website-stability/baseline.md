# 홈페이지 안정성의 추가 관측

2026-10-03 19:54~20:01 KST 읽기 전용 조사. [조사 계획](../website-stability-investigation-plan.md)의 장애 기준선 확보 단계에 해당한다. 실제 504와 동일 시간대의 요청 추적은 아직 확보하지 못했다. 서비스 설정·코드·DB 데이터 변경, 재시작, HTTP 요청 생성, 지속 수집기 설치는 하지 않았다.

## 자연 부하 표본

| 시각과 범위 | 결과 | 해석 |
|---|---|---|
| 19:54:16 | load average 7.65 / 10.00 / 9.72 | 앞선 오후 관측 이후에도 실행 대기 징후가 남음 |
| 19:55:11 종료, 8.26초 | CPU user 61.1%, system 8.5%, idle 29.9%, iowait 0.1% | 이번 표본은 약 70% 사용. 앞선 약 100% 포화와 달라 고정된 부하로 볼 수 없음 |
| 같은 표본 | 가용 RAM 878,128KiB, 약 858MiB. swap in/out 모두 0 pages | swap 잔량은 있지만 이 구간에서 왕복이나 메모리 고갈은 관측되지 않음 |
| 같은 표본 | 상위 PHP 워커 3개 각각 CPU 53.5%, 52.3%, 47.9%; MariaDB 16.9% | 프로세스 CPU는 한 코어 100% 기준. 전체 CPU 비율과 직접 더하지 않음 |
| 19:58:02 종료, 8.02초 | 별도 CPU 누적값 차이에서 idle 약 50%, iowait 0% | DB 표본 시간에도 부하가 더 낮아짐 |

조사용 Python·SSH·DB 조회도 자원을 사용했다. Python은 첫 표본의 한 코어 기준 약 1.8%였으며 관측은 짧게 종료했다. 정상 피크 처리량, 시간대별 평균, 장애 때의 메모리 상태를 이 표본으로 대신하지 않는다.

## DB 표본

PHP 7.4 CLI와 기존 XE 접속 설정으로 연결하고 `START TRANSACTION READ ONLY` 안에서 SHOW/SELECT만 실행한 뒤 ROLLBACK했다. 홈페이지 코어를 실행하지 않았고, 비밀번호·계정 식별자·쿼리 원문은 출력하거나 저장하지 않았다. MyISAM 자체의 쓰기 방지를 트랜잭션에 의존하지 않았으며 실행문을 읽기 전용으로 제한했다.

| 지표 | 8.02초 증가량 |
|---|---:|
| Questions | 461, 약 57.5회/초 |
| Key_read_requests | 56,319 |
| Key_reads | 12,182 |
| Key_reads / Key_read_requests | 약 21.6% |
| Table_locks_immediate | 465 |
| Table_locks_waited | 0 |
| Select_scan | 149 |
| Created_tmp_disk_tables | 5 |
| Slow_queries | 0 |

GLOBAL STATUS는 MariaDB 전체 누적 통계다. 다른 DB와 관측 쿼리도 포함할 수 있으므로 홈페이지 한 요청의 비용이나 사이트 전용 수치로 해석하지 않는다. 캐시 바깥 읽기는 OS 캐시에 적중할 수 있으며 실제 디스크 접근률이 아니다. 앞선 20.6% 표본과 비슷한 경향이 재관측됐지만, 응답 시간 개선 효과는 측정하지 않았다.

2초 간격의 5회 PROCESSLIST 표본에서 관측 연결을 제외한 가시 연결은 모두 Sleep이었고 실행 중 쿼리를 포착하지 못했다. 짧은 쿼리는 표본 사이에 끝날 수 있고 계정의 조회 권한에 따른 가시 범위도 있으므로, DB 병목이 없다는 결론은 아니다. 쿼리 원문은 서버 내에서 키워드 존재 여부만 분류하고 밖으로 내보내지 않았다.

### 실효 설정

| 설정 | 값 |
|---|---|
| key_buffer_size | 16,384 bytes |
| performance_schema | OFF |
| slow_query_log | OFF |
| long_query_time | 10초 |
| query_cache_type | OFF |
| query_cache_size | 1,048,576 bytes |
| max_connections | 151 |
| table_open_cache | 4,000 |

설정값의 존재와 실제 기능 사용을 구분한다. query_cache_size 값만으로 쿼리 캐시가 켜졌다고 판단하지 않으며, 이번 조사에서 활성화를 권고하거나 수행하지 않았다. Slow_queries=0도 짧은 쿼리의 누적 비용을 배제하지 않는다.

### 현재 홈페이지 DB의 테이블 메타데이터

| 엔진 | 테이블 수 | 데이터 크기 | 인덱스 크기 |
|---|---:|---:|---:|
| MyISAM | 130 | 98,334,865 bytes | 25,717,760 bytes, 약 24.5MiB |
| InnoDB | 7 | 540,672 bytes | 589,824 bytes |

큰 인덱스는 방문 통계 `xe_counter_log` 약 14.3MiB, 댓글 약 2.8MiB, 문서 약 2.6MiB 순이다. 방문 통계 테이블의 행 수 메타데이터는 593,229건이다. 이 크기는 실시간으로 자주 읽는 범위와 같지 않으며 캐시 권장 용량을 곧바로 확정하지 않는다. InnoDB 행 수 메타데이터는 근사값이므로 과거 정확한 COUNT와 대조해 데이터 삭제를 추정하지 않는다.

## 최신 장애를 연결하지 못한 이유

현재 홈페이지 nginx 설정의 access/error 출력 경로는 각각 WebStation의 `nginx_access_log`, `nginx_error_log`다. 조회 시 두 파일은 0 bytes였다. 같은 폴더의 access SQLite 파일 수정 시각은 9월 24일 18:26, error SQLite는 9월 24일 22:26이며, Apache error SQLite는 10월 1일 22:35였다. 해당 경로에서 최신 요청을 확인할 자료를 확보하지 못했다.

이것만으로 로그 저장 고장을 확정하지 않는다. 파일 회전, 실행 프로세스가 실제로 연 파일, 별도 수집 경로 등은 추가 확인 대상이다. 기존 SQLite 관측은 immutable 읽기로 WAL을 포함하지 않는 한계도 있다.

PHP-FPM 로그는 10월 3일 19:53까지 갱신돼 있었지만 `system:log`, 0660 권한 때문에 현재 SSH 계정으로 읽을 수 없었다. 시스템 로그와 운영 PHP 프로세스의 `/proc/<pid>/maps`도 접근 거부였다. 권한 변경이나 sudo 인증 우회는 하지 않았다.

FPM 설정은 dynamic, max_children=12, start_servers=2, min_spare_servers=1, max_spare_servers=3이었다. 확인한 pool 파일에 요청별 access log, slowlog, status 설정은 없었다. 따라서 현재 요청 URL·응답 시간·대기열·느린 코드 경로를 동시에 연결하지 못했다. OPcache의 운영 프로세스 내 적재 여부도 이 경로에서는 확정하지 못했다.

## 다음 단계의 조건

1. 기존 PHP 로그에서 관측 시간대와 사용자가 겪은 504 시간대의 max_children·종료·오류를 제한적으로 확인한다. 관리자 제공 또는 승인된 읽기 경로가 필요하다.
2. 실제 nginx 로그 수집 경로와 갱신 상태를 확인한다. 최신 요청 로그가 없다면 수집 항목·비용·보존 기간·복구 절차를 제시한 뒤 설정 변경 승인을 받는다.
3. OPcache·MyISAM 캐시·XE 선택형 캐시는 [검증 후보](cache-verification.md)에 따라 분리된 환경에서 한 항목씩 비교한다.

현재 결과는 조사 진전이며 안정화 완료가 아니다. 실제 504의 원인 연결은 열린 항목으로 유지한다.

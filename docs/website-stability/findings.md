# 홈페이지 내부 개선 후보 1차 탐색

기록일: 2026-10-03. [조사 계획](../website-stability-investigation-plan.md) 수립 이후 사용자 승인으로 시작한 읽기 전용 탐색 결과다. 서비스 설정·코드·DB 데이터 변경, 서비스 재시작, HTTP 부하 재현은 하지 않았다. 여기 적힌 개선 효과는 아직 실험으로 측정하지 않았다.

## 현재 판단

장비 교체를 결정하기 전에 확인할 구체적인 내부 개선 후보가 발견됐다. PHP OPcache 적재, XE 객체 캐시, MyISAM 인덱스 캐시, 반복 검색 처리 비용을 우선 비교한다. 이 결과는 현재 NAS의 충분한 처리 용량이나 모든 504 해결을 증명하지 않는다.

## 확인한 설정과 코드

### PHP OPcache 적재

systemd의 홈페이지 FPM 서비스는 `/var/packages/PHP7.4/target/misc/php-fpm.ini`를 읽고, profile의 `conf.d`와 `/run/php-fpm/conf.d`를 추가로 읽는다. 서비스 drop-in은 없었다. `opcache.enable=1` 값은 있지만 해당 경로의 설정에서 `zend_extension` 적재 지시를 확인하지 못했다.

이 FPM 바이너리·기본 ini·scan directory를 동일하게 지정하고 별도의 `-m` 프로세스를 한 번 실행했다. 새 daemon이나 웹 요청을 시작하지 않는 모듈 목록 확인이며 exit 0, stderr 없음, **Zend OPcache 미적재**였다. 실제 운영 master의 메모리를 읽거나 OPcache hit ratio를 조회한 결과는 아니다. 따라서 현재 구성으로 시작할 때의 미적재는 확인됐고, 실제 pool의 최종 상태는 관리 화면 또는 승인된 진단으로 확인한다.

OPcache는 PHP 코드의 반복 파싱·컴파일 비용을 줄이는 기능이다. 현재 CPU 병목의 개선 후보로 우선순위가 높지만 전체 PHP 실행·DB 조회를 캐시하는 기능은 아니다. [PHP 공식 설명](https://www.php.net/manual/en/book.opcache.php)

후속 검증은 활성화 전후의 동일 요청 구성에서 CPU/요청, 지연, 메모리, cache hit와 갱신 동작을 비교한다. 운영 적용에는 FPM 재적용 영향이 있으므로 지금 수행하지 않았다.

### XE 캐시

운영 `db.config.php`의 `use_object_cache`, `use_template_cache`가 미설정이었다. 확인한 `CacheHandler`는 이 값에 따라 handler를 선택하며, 일반 객체 캐시 설정이 없는 경우의 경로가 존재한다. 일부 코드의 강제 file cache와 컴파일된 template 파일은 별개로 존재하므로 사이트의 모든 캐시가 꺼졌다고 표현하지 않는다.

우선 캐시 가능한 공통 데이터와 현재 query 수를 확인하고, XE가 지원하는 방식 중 운영 부담이 작은 후보를 검증한다. 회원별 접근·비공개 자료·로그인 상태를 공용 캐시에 섞지 않는다. PHP OPcache와 XE 객체 캐시는 각각 따로 비교한다.

### MyISAM 인덱스 캐시

운영 DB metadata에서 `xe_documents`, `xe_comments`, `xe_member`, `xe_tags`는 **MyISAM**이었다. 앞서 확인한 InnoDB buffer pool 16MiB를 이 테이블들의 직접 개선 대상으로 취급해서는 안 된다.

`key_buffer_size`의 실제 값은 **16,384 bytes, 16KiB**였다. 별도 5초 자연 트래픽 표본에서 다음 변화가 확인됐다.

| 지표 | 5초 변화 |
|---|---:|
| Questions | 463, 약 92.5 queries/s |
| Key_read_requests | 64,910 |
| Key_reads | 13,368 |
| Table_locks_immediate | 475 |
| Table_locks_waited | 0 |
| Slow_queries | 0 |

이 표본의 Key_reads / Key_read_requests는 약 20.6%다. MyISAM key buffer 바깥의 읽기 요청 비율이며 OS page cache가 처리할 수 있으므로 물리 디스크 접근률로 해석하지 않는다. Questions에는 조사용 상태 조회도 소량 포함된다. 기존 slow query 기준이 10초이고 로그도 꺼져 있으므로 Slow_queries=0은 빠른 쿼리만 존재한다는 증거가 아니다.

문서·댓글·회원·태그 네 테이블의 인덱스 metadata 합계는 약 5.6MiB다. 전체 MyISAM 인덱스와 실제 hot set을 확인한 뒤 작은 단계의 캐시 확대 후보를 검증할 수 있다. RAM 배분과 다른 서비스 영향을 측정하기 전 임의의 대용량 설정을 정하지 않는다. 온라인 엔진 변환·테이블 최적화·인덱스 생성은 이 탐색에서 수행하지 않았다.

[MyISAM key buffer 공식 설명](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/system-variables/optimizing-key_buffer_size), [MyISAM 잠금 특성](https://mariadb.com/docs/server/server-usage/storage-engines/myisam-storage-engine/myisam-overview)

### 검색의 반복 처리

운영과 로컬 레거시 사본의 다음 네 파일은 SHA256이 일치했다. 로컬 소스를 읽어 운영 코드에 대한 추정을 검증할 수 있는 범위다.

| 파일 | SHA256 |
|---|---|
| modules/integration_search/integration_search.view.php | 7098955388a9c89c73564352c16f2bcf881980d3ed2f1b7f8cebc75689863fe8 |
| modules/integration_search/integration_search.model.php | b5ea4d440bb7a31654842e23160f0caa7e9cf1e40a8f146379ba7265c13f7f8f |
| modules/document/queries/getDocumentList.xml | fd4362061b3897eb3e3838750150e3b69ea9ba12f66798512e92abd69aea6f7d |
| classes/cache/CacheHandler.class.php | 4e8500d50823f7b87f93bf880fda1f9d213466c2fe871eaf80cfbbbc4c15ea81 |

통합 검색은 where가 특정 분기로 정해지지 않으면 문서·댓글·트랙백·이미지·파일 검색을 함께 수행한다. 문서 제목·본문·태그 조건에는 LIKE 검색이 있다. 과거 로그의 반복 검색과 결합해 조사할 후보지만, 어떤 분기가 현재 시간을 가장 많이 쓰는지는 아직 측정하지 않았다. 검색 query 자체를 운영에서 반복 실행하거나 EXPLAIN ANALYZE로 재현하지 않았다.

웹 root에 robots.txt 파일이 없었다. 가상 응답이나 상위 계층 정책은 별도 확인이 필요하며, robots 파일 하나로 과부하가 해결된다고 판단하지 않는다.

## 조사 순서 변경

1. **먼저 비교할 항목:** OPcache 적재 및 XE 객체 캐시, MyISAM key buffer, 요청별 검색 비용.
2. **동시에 확보할 근거:** 최신 504와 PHP queue/slow trace의 시간 연결. 현재 권한으로 읽을 수 없는 로그의 제한적 조회 경로 필요.
3. **계속 유지할 대안:** 내부 개선 후에도 성능·안정성 목표가 충족되지 않거나 지원·복구·인수인계가 어려우면 웹·DB 분리 또는 새 NAS 검토.

장비 교체를 조사 결론으로 선결정하지 않는다. 캐시의 미사용 또는 작은 설정도 전체 병목의 확정 원인으로 과장하지 않는다. 비교 실험 전에는 개선률이나 필요한 신규 RAM 용량을 제시하지 않는다.

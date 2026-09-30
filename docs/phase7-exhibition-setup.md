# Phase 7 — 작품전시회

## 구현 범위

- `exhibition` 표준 Rhymix board(개발 DB의 `module_srl=129`)를 유지하고 `kitel_gallery` 스킨으로 목록·상세·출품 화면을 표시한다.
- 카드는 작품 사진, 두 줄 제목, 쉼표로 구분한 팀원 정보를 표시한다. 마우스 hover 또는 키보드 focus 때 사진 위에 반투명 흰색 설명을 겹친다.
- 연도 제어는 실제 게시물 등록 연도(`regdate`)로 목록을 조회한다. 검색과 페이지네이션도 그 연도에 한정한다.
- 기술부의 board `manager` 권한으로 전시 **전체**를 공개/미공개 전환한다. 작품별 평가·승인 상태는 없다. 기존 board document, extra vars, editor, 파일 업로드·다운로드 계약을 사용한다.

## 개발 실행본 적용

1. repository에서 `scripts/sync-dev.ps1`을 dry-run으로 확인한 뒤 `-Apply`로 동기화한다.
2. `D:\rhymix_dev\php\php.exe scripts/configure-phase7-exhibition-dev.php`로 변경 예고를 확인한다.
3. 같은 명령에 `--apply`를 붙인다. 스크립트는 `D:\rhymix_dev\www\rhymix`의 지정된 개발 DB, `exhibition`/129/`kitel_gallery` identity를 확인한다. 기존 필수 파일 변수 `photo`, `proposal`을 보존하고 텍스트 변수 `team_members`, `project_info`를 추가한다.
4. Rhymix 캐시를 다시 빌드한다. 기존 공개 상태를 일괄 정렬할 때는 기술부 계정으로 목록 상단 스위치를 한 번 전환한다.

원래의 전시 작품 한 건은 보존한다. 카드 사진은 원본이 1×1 검정 fixture이므로 이미지 자체는 최종 콘텐츠가 아니다. 기존 작품에는 팀원·한 줄 소개 값이 없으면 작성자와 본문 요약을 임시 대체 표시한다.

### 화면 검증용 작품 9건

현재 로컬 개발 DB에는 별도의 작품 9건을 추가해 두었다. 제목·팀원·소개·사진·제안서는 모두 시연용이며 운영 데이터가 아니다. `scripts/phase7-gallery-fixture-dev.mjs`는 기본 dry-run 설정 검사 뒤 일반 Rhymix 출품 폼으로만 게시물을 작성한다. manifest는 repository 밖 `D:\rhymix_dev\qa-fixtures\phase7-gallery-demo.json`에 기록된다. `KITEL_QA_USER`/`KITEL_QA_PASSWORD` 환경변수와 개발용 Playwright 실행환경을 준비한 뒤 `node scripts/phase7-gallery-fixture-dev.mjs create`로 생성하고, `cleanup`으로 manifest에 기록된 아홉 건만 삭제할 수 있다. 스크립트는 기존 작품이나 운영 DB를 대상으로 실행되지 않도록 개발 DB 검증을 거친다.

## 공개 상태와 권한

전시 전역 상태는 `kitelboardguard`의 module part config에 저장한다. 기술부/관리자의 `manager` grant만 POST `procKitelboardguardSetExhibitionVisibility`를 호출할 수 있다. 전환 시 해당 board의 모든 document status를 `PUBLIC` 또는 `SECRET`으로 맞춘다. 신규·수정 작품도 저장 trigger가 같은 상태를 적용한다. 비공개 상태에서 익명 방문자는 목록 카드와 상세 본문을 볼 수 없다. 작성자는 자기 작품, 관리자 권한자는 관리 대상 작품을 확인할 수 있다.

사진과 제안서 등 전시 첨부파일은 `D:\rhymix_dev\kitel-private\exhibition`에 저장한다. `scripts/migrate-exhibition-files-dev.php`는 기존 공개 웹 경로 파일을 그 위치로 이동하는 개발 전용, 기본 dry-run 도구다. 브라우저의 직접 static URL은 사용할 수 없고 사진 표시 및 첨부 다운로드는 각각 권한 검사를 거친 controller를 통한다. `file.insertFile` 후 trigger가 신규 업로드를 private 저장소로 이동한다. 운영 환경에는 이 private 경로와 쓰기 권한을 별도로 준비해야 한다.

## 검증 기록 (2026-09-30, 개발 서버)

- 익명 공개 목록·상세·사진 로딩, 이전 연도 빈 목록, 검색 대상/키워드 유지, 1920/1440/1024/390에서 수평 overflow 없음.
- 기술부 스위치의 AJAX 전환/애니메이션 확인. 미공개 후 익명 카드 0건, 직접 상세 locked, 사진/첨부 controller 403. 다시 공개하면 목록 복원.
- 준회원의 전역 공개 변경 POST는 403. 준회원 출품 수정과 새 팀원·소개 필드 저장/카드 표시 확인.
- 댓글 작성 차단, 기존 Rhymix 문서 편집·삭제 흐름, private 파일 이동과 direct static URL 차단 확인.
- 검증용 게시물 4건 및 관련 DB/file/extra var 행을 삭제하고, 임시 계정 password hash를 원복했다.

## 후속 화면 조정 (2026-09-30)

- 불필요한 작품별 심사 안내를 출품 페이지에서 제거했다. 확장변수 입력·파일 선택·CKEditor 외곽과 첨부 영역을 갤러리 스킨 범위에서 테마화했다.
- 작품 카드 마지막 행 아래 100px 여백을 두었다. 모바일 연도 바는 56px 높이로 축소했고, 390px에서 카드 두 개를 한 행에 배치한다.
- 별도 9건 fixture를 남겨 목록 밀도와 이미지 로딩을 검증했다. 모바일 390px에서 2열(각 166px), 1440px에서 4열, 가로 overflow와 browser console error가 없는 것을 확인했다.

## 이후 데이터 작업

기존 작품의 팀원·한 줄 소개는 실제 운영 데이터를 확인하여 채운다. `photo`와 `proposal` 파일 필드는 기존 board 설정을 유지한다. 전시 전체 공개 시점은 기술부가 결정한다.

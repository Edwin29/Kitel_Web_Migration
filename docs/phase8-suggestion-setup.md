# Phase 8 — 건의사항

## 화면과 정책

- `suggestion`은 기존 Rhymix `board`(개발 DB `module_srl=135`)와 상담 기능을 유지한다. 공통 `kitel_generic` 스킨의 작성·상세·댓글 기능을 재사용한다.
- `/suggestion`에 작성 폼과 `내가 한 건의`를 함께 표시한다. 임원진에게만 `전체 건의 보기` 버튼을 보여준다.
- 전체 목록은 `?mid=suggestion&suggestion_view=staff`에서 공통 게시판 목록 포맷으로 표시한다. 서버의 `consultation_read` 또는 `manager` grant가 없는 사용자는 이 화면에 접근할 수 없다.
- 정회원 작성, 준회원 작성 불가. 작성자는 자기 글만 재조회한다. 임원진은 전체 글과 첨부파일을 열람하고 댓글로 답변한다. 답변여부는 운영진만 댓글을 작성할 수 있는 grant를 이용하여 댓글 수로 판정한다.
- 익명 기능은 2026-09-30 사용자 결정에 따라 사용하지 않는다. Figma의 전체공개/비공개 선택도 표시하지 않는다. 문서 DB status의 `PUBLIC`은 게시판 내부 상태이며, 실제 열람은 module access와 상담 기능으로 제한한다. RSS는 비활성화한다.
- 첨부파일은 웹 루트 밖 `D:\rhymix_dev\kitel-private\suggestion`에 저장한다. 다운로드 컨트롤러에도 동일한 작성자·임원진 권한을 적용한다. 일반 작성자에게 다른 사람의 첨부파일이 노출되지 않도록 `kitelboardguard`가 검사한다.
- 과거 `qna`의 익명 게시물은 새 정책을 적용해 작성자를 드러내면 안 된다. 기존 데이터 이관은 노출·보존 정책을 정한 뒤 별도로 진행하며 이번 Phase에는 포함하지 않는다.

## 로컬 개발환경 적용

1. repository에서 `scripts/sync-dev.ps1`을 실행해 변경을 확인한 후 `-Apply`로 개발 Rhymix에 복사한다.
2. `D:\rhymix_dev\php\php.exe scripts/configure-phase8-suggestion-dev.php`로 개발 DB의 `suggestion/135`, 기존 grants, 상담 설정을 확인한다.
3. 같은 명령에 `--apply`를 붙이면 스킨을 지정하고 RSS·익명을 끄며 건의 저장 trigger를 등록한다. 스크립트는 로컬 개발 DB만 허용한다. 운영 DB에는 실행하지 않는다.
4. 기존 공개 첨부파일이 있다면 `scripts/migrate-suggestion-files-dev.php`의 dry-run을 확인하고 `--apply`로 private 저장소에 이관한다. 새 업로드는 등록 시 자동으로 private 저장소로 이동한다. 운영환경도 별도 private 경로와 쓰기 권한을 준비해야 한다.
5. Rhymix 캐시를 재생성한다.

## 검증

- 정회원이 인라인 Rhymix editor에서 건의를 작성하고 본인 목록·상세에 접근함을 확인했다. 운영진 답변 댓글 뒤 목록 상태가 `답변대기`에서 `답변완료`로 바뀌었다.
- 기술부는 본인 건의 목록 외에 버튼으로 전체 목록에 진입해 다른 작성자의 건의를 읽었다. 다른 정회원은 전체 목록을 열 수 없고 다른 사람의 문서 상세도 열 수 없었다.
- 준회원과 익명 사용자의 게시판 목록·상세는 403이었다. 테스트 게시물과 댓글은 일반 삭제 경로로 정리하고 임시 계정 권한·비밀번호를 원복했다.
- 첨부 fixture의 controller 다운로드: 작성자·기술부 200, 다른 정회원·익명 403. 직접 static URL은 private 이관 전 200이었고 이관 후 모두 404였다. 신규 업로드도 즉시 private 경로에 저장됨을 확인했다. 검증용 게시물·파일 행은 삭제했다.
- Rhymix editor에서 이미지를 첨부해 본문에 자동 삽입한 뒤 저장했으며, 작성자에게 이미지 controller URL이 200으로 표시됐다. 검증 게시물·이미지는 삭제했다.
- 댓글 상태를 `DENY`로 변조한 직접 요청을 시험했다. Rhymix가 trigger 전에 `comment_status`를 저장용 `commentStatus`로 복사하므로, 두 필드를 모두 `ALLOW`로 고정한다. 재시험 문서는 `ALLOW`로 저장됐고 임시 문서는 삭제했다.

# KITEL 게시글 편집기 도구 모음

일반 게시글의 Rhymix CKEditor 기능과 첨부 업로드는 유지하면서, 복잡한 기본 도구 모음 대신 Rhymix의 공식 `simple` 도구 모음을 사용한다. 적용 범위는 `news`, `seminar`, `sharing`, `suggestion`, `exhibition`의 데스크톱 게시글 본문 편집기다. 댓글, 모바일 편집기, 페이지 관리자 편집기 설정은 변경하지 않는다.

로컬 개발환경에서는 `D:\rhymix_dev\php\php.exe scripts/configure-board-editor-dev.php`로 변경 대상을 먼저 확인하고, `--apply`로 적용한다. 스크립트는 `dev-environment-guard.php`로 개발 DB를 확인하고 각 board의 `mid`, `module_srl`, skin을 검증한다. 운영 DB에는 실행하지 않는다. 적용 후 Rhymix 캐시를 재생성해야 화면에 반영된다.

2026-09-30 로컬 검증: 건의사항 편집기 상단 버튼 43개가 15개로 줄었고, 본문 편집 영역과 사진·파일 첨부 영역이 유지됐다. Footer의 2026년 및 `49th Woncheol Wang` 문구가 표시됐으며 브라우저 페이지 오류는 없었다.

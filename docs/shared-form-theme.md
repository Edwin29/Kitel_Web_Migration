# KITEL 공통 작성 폼 테마

작품 등록 화면의 항목명에 Rhymix 기본 게시판 CSS의 돋움 글꼴이 적용되던 문제를 기준으로 작성 폼의 글꼴과 입력 컨트롤을 통일했다. 공통 스타일은 `custom_layouts/kitel_site/css/form-theme.css`에 있다.

- 작성 화면의 최상위 요소에 `kitel-form-surface`를 추가한다. 이 클래스 밖의 `$content`나 Rhymix 관리자 전체에는 스타일을 강제하지 않는다.
- 항목명과 범례는 Pretendard를 사용한다. 텍스트/날짜 입력, 선택, textarea, 파일 선택, Rhymix CKEditor와 첨부 패널은 동일한 경계선·배경·radius를 사용한다.
- 페이지별 배치, 필드 간격, 버튼 배치는 해당 skin/module CSS가 소유한다. `kitel-form-surface`는 기존 form action, hidden input, editor, upload, grant를 바꾸지 않는다.
- 새 Rhymix 기반 작성 화면을 만들 때 이 클래스를 재사용하고, 필요한 구조만 해당 skin에서 추가한다. 기본 Rhymix UI가 이미 있는 모든 화면에 전역 선택자를 적용하지 않는다.

현재 적용 범위: 작품전시회 등록, News/Seminar 공통 게시판 글쓰기와 댓글 작성·수정, Homework 준회원 제출과 기술부 과제 작성, Calendar 관리자 일정 작성·수정. Archive는 Phase 5 보류 상태이며 회원 계정 본문은 Phase 10에서 다룬다.

개발 환경 브라우저 확인: 작품명 등 전시 등록 항목명, Homework 답변/파일 첨부, 기술부 과제 작성, Calendar 일정 작성의 항목명에 Pretendard가 적용된다. 공통 CSS가 Header, Footer 또는 임의의 게시글 본문 요소에 침범하지 않는지도 확인한다.

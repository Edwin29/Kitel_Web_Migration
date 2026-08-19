# 남은 작업 총정리

작성일: 2026-08-19
목적: 지금까지 나온 모든 문서([xeadmin-site-analysis.md](xeadmin-site-analysis.md) · [new-site-ia.md](new-site-ia.md) · [board-feature-specs.md](board-feature-specs.md) · [frontend-skin-architecture.md](frontend-skin-architecture.md) · [designer-brief.md](designer-brief.md) · [dev-environment.md](dev-environment.md))에 흩어진 "다음 단계" / "열린 질문" / "미착수"를 한 곳에 모음.

## 1. 커스텀 개발 — 아직 안 만든 것

캘린더([custom_modules/calendar](../custom_modules/calendar)), 자료실([custom_modules/archive](../custom_modules/archive)), 과제게시판(homework, [custom_modules/homework](../custom_modules/homework)) 완성. 같은 패턴으로 만들면 되는 나머지:

| 항목 | 난이도 | 참고 |
|---|---|---|
| ~~자료실 커스텀 모듈~~ | ~~중~~ | **완료** — board-feature-specs.md §3 |
| ~~과제게시판 제출현황 대시보드~~ | ~~중~~ | **완료** — board-feature-specs.md §4. 기존 board(상담기능) 방식은 준회원이 기술부 공지를 못 보는 결함이 있어 폐기하고, task/submission을 분리한 별도 모듈로 재구축 |
| ~~작품전시회 갤러리 커스텀 스킨 + 승인 워크플로우~~ | ~~중~~ | **완료** — board-feature-specs.md §5. 과제게시판과 반대로 이번엔 board 표준기능(비밀글 상태+관리권한)이 실제로 승인 워크플로우에 맞음을 4개 역할(작성자/guest/기술부/admin)로 검증 후 커스텀 스킨(카드 그리드)만 제작 — [custom_skins/board/kitel_gallery](../custom_skins/board/kitel_gallery) |
| 메인페이지 — 히어로 배너 위젯(admin 편집 가능, 전환 없음) | 소 | board-feature-specs.md §12 |
| 메인페이지 — 다가오는 일정 미리보기 위젯 | 소 | 캘린더 model 재사용, board-feature-specs.md §12 |
| 메인페이지 — 최신 소식/작품전시회 하이라이트 | **불필요** | Rhymix 기본 "Content 위젯"으로 설정만 하면 됨 |

## 2. 디자이너 협업

- 디자이너에게 [designer-brief.md](designer-brief.md) 공유 완료, 레퍼런스([한화비전](https://www.hanwhavision.com/ko))도 반영됨. Figma 작업 진행 상황 확인 필요.
- 결과물 나오면: (B) 표준 게시판 스킨(list/view/write html) 퍼블리싱 반영, (C) 커스텀 화면(캘린더 등)에 디자인 마크업 이식.
- 캘린더는 지금 임시 스타일(인라인 CSS)로 되어 있어 디자이너 스킨으로 교체 예정.

## 3. 설계 확정이 필요한 열린 질문

빠르게 결정하면 좋은 것들 (급하진 않지만 만들면서 계속 걸림):

- **작품전시회**: 사진 최대 장수, 간단설명 글자수 제한, 제안서 파일 공개 범위(전체공개 vs 회원전용), 보완요청 알림 방식
- **과제게시판**: 재제출/마감 지각 처리 방식, 채점결과 기록 여부, 과제 유형 카테고리 분류 필요 여부
- **자료실**: 파일/폴더 삭제·이동 권한(본인 것만 vs 정회원 공동관리), 저장용량 제한, 미리보기 지원 여부
- **캘린더**: 반복 일정 지원 여부, 카테고리 색상 구분, RSVP 확장 가능성
- **메인페이지**: 히어로 배너 정확한 필드 구성, 섹션별(소식/일정/전시회) 노출 개수, 섹션 순서
- **"기타" 메뉴 정식 이름**: 추후 별도 회의 예정이라 명시됨
- **공유게시판** 세부 운영 기획: 현재 빈 게시판으로만 오픈, 세부 카테고리 등은 미정

## 4. 마무리 안 된 시스템 설정

- **메뉴 레벨 노출 권한 미설정**: 게시판 자체 접근 권한(grants)은 다 설정했지만, 메뉴 링크 자체의 노출 권한은 손대지 않음 — 예를 들어 준회원에게 건의사항 메뉴 "링크"는 보일 수 있음(눌러도 막히긴 함). dev-environment.md에 기록된 후속 과제.

## 5. 데이터 마이그레이션 (미착수)

- 게시판/회원 데이터 이관 스크립트 설계: `xe_documents`, `xe_comments`, `xe_member`, `xe_member_group_member` 등 → Rhymix 스키마
- `xe/files/attach/`(첨부파일 원본, ~10GB) NAS에서 확보 후 `xe_files` 메타데이터와 매핑 검증
- NAS Drive(Synology Drive) 자료를 신규 자료실로 일괄 이관 — UI/구조를 NAS Drive와 유사하게 만들기로 확정됨(자료실 모듈 개발과 함께 고려)

## 6. 기자재 대여 시스템 연동

- `rental_dev`의 인증 어댑터를 Rhymix 세션 체계(`Context::get('logged_info')` 등)로 교체
- 신규 사이트 "기타" 메뉴 하위에 진입점 배치(기능 자체는 유지)

## 7. 배포/운영 전환 (아직 논의 안 됨)

- **PHP 버전 확인 필요**: 로컬 개발은 PHP 8.2.33 기준으로 진행했는데, 기존 NAS 운영 서버는 PHP 7.4였음(DSM Web Station 설정 기준, [xeadmin-site-analysis.md](xeadmin-site-analysis.md) 참고). Rhymix는 "PHP 7.4 이상"만 요구하지만, 실제 운영 서버에 배포할 때 PHP 버전을 올릴지(권장) 그대로 7.4로 맞출지 결정 필요.
- 로컬 개발 환경(`D:\rhymix_dev`, PHP 내장 서버 + MariaDB)을 실제 Synology NAS 운영 환경으로 옮기는 절차는 아직 설계 안 됨.
- Google Analytics(UA 코드) → GA4 교체 여부 (기존 사이트 분석에서 발견된 항목).
- 콘텐츠 문구 정리: About 하위 정적 페이지(키텔소개/연혁/활동/연구분야/회칙 등)의 실제 텍스트는 아직 플레이스홀더 상태.

## 우선순위 제안

1. **디자이너 Figma 진행 상황 확인** (병렬 진행 중이므로 블로킹 아님)
2. ~~자료실~~ → ~~과제 대시보드~~ → ~~작품전시회 갤러리~~ 커스텀 개발 완료. 남은 건 메인페이지 소형 위젯 2개(히어로 배너, 다가오는 일정 미리보기)뿐
3. 위 항목들 개발하면서 걸리는 **§3 열린 질문들을 그때그때 확정**
4. 커스텀 개발이 어느 정도 끝났으니 **데이터 마이그레이션 스크립트** 설계 착수 가능
5. **대여 시스템 인증 연동**은 언제든 독립적으로 진행 가능
6. **배포 전환 계획**(PHP 버전, 운영 서버 이전)은 개발이 어느 정도 마무리된 시점에 확정

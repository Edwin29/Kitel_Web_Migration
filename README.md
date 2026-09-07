# Kitel_Web_Migration

광운대학교 전자연구회(KITEL) 동아리 홈페이지(`kitel.kw.ac.kr`)를 XEadmin(XpressEngine) 기반에서 Rhymix 기반으로 마이그레이션하는 프로젝트. 엔진 교체와 함께 디자인·정보구조(IA)·일부 기능도 새로 설계함.

## 📍 전체 진행 현황

**[docs/roadmap.md](docs/roadmap.md)** — 게시판/기능별 상세 작업 현황, 매크로 단계, 리스크와 다음 우선순위를 한 곳에 정리한 문서. 프로젝트 상태를 파악하려면 여기부터 보면 됨.

## 문서 구성 (`docs/`)

| 문서 | 내용 |
|---|---|
| [roadmap.md](docs/roadmap.md) | **전체 로드맵** — 게시판·기능별 상세 현황, 리스크, 우선순위 |
| [xeadmin-site-analysis.md](docs/xeadmin-site-analysis.md) | 기존 XEadmin 사이트 분석(IA, 회원등급, 게시판 목록, 대여 시스템 등) |
| [new-site-ia.md](docs/new-site-ia.md) | 신규 사이트 정보구조·회원등급·권한 설계 |
| [board-feature-specs.md](docs/board-feature-specs.md) | 게시판/기능별 상세 명세 및 Rhymix 구현 검증 기록 |
| [frontend-skin-architecture.md](docs/frontend-skin-architecture.md) | 프론트엔드/스킨/백엔드 연결 구조, 디자이너 협업 경계 |
| [designer-brief.md](docs/designer-brief.md) | 디자이너 전달용 브리프 |
| [dev-environment.md](docs/dev-environment.md) | 로컬 개발 환경 구축 기록, 커스텀 모듈/위젯 구현 노트 |
| [data-migration-plan.md](docs/data-migration-plan.md) | 데이터 마이그레이션 스크립트 설계 |
| [remaining-work.md](docs/remaining-work.md) | 항목별 세부 미착수/열린 질문 메모 |

## 저장소 구성

```
custom_modules/   # Rhymix 커스텀 모듈 (캘린더, 자료실, 과제게시판)
custom_skins/     # Rhymix 표준 모듈용 커스텀 스킨 (작품전시회 갤러리)
custom_widgets/   # Rhymix 커스텀 위젯 (히어로 배너, 다가오는 일정)
docs/             # 설계/분석/로드맵 문서
kitel_web/xe/     # 기존 XEadmin 운영 소스 (첨부파일 제외, .gitignore 참고)
```

## 현재 상태 요약

캘린더·자료실·과제게시판·작품전시회·메인페이지 위젯 등 커스텀 백엔드 개발은 완료됨. 데이터 마이그레이션은 설계 완료, 실제 스크립트 구현 전. 디자인은 스킨 시안 완료, 목업 진행 중. 상세는 [roadmap.md](docs/roadmap.md) 참고.

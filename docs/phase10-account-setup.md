# Phase 10 — KITEL Account skin

## 범위와 데이터 원칙

Rhymix `member` 모듈의 인증, 가입, CSRF, ruleset, 수정, 비밀번호 재확인, 탈퇴, 회원 메뉴를 그대로 사용한다. `custom_skins/member/kitel_member`는 기본 스킨의 모든 보조 화면을 보존하고 Login, Signup, Signup Complete, My Page와 관련 편집 화면에 KITEL 표현을 적용한다. 별도 인증 컨트롤러나 계정 데이터 사본은 없다. `scripts/sync-dev.ps1`이 이 스킨을 로컬 Rhymix `modules/member/skins/kitel_member`로 동기화한다.

Figma 기준은 Login `19:166`, Signup `148:897`, Signup Complete `46:842`, My Page `51:1279`이다. 절대 1920px 위치 대신 공통 1160px 폭과 유동 그리드를 쓴다. 로그인은 570px 카드, 가입·마이페이지는 공통 navy 배너와 본문을 사용한다. Figma의 체크 아이콘 SVG를 가입 완료 화면에 배치했다. 기존 사이트 로고는 공통 레이아웃의 자산을 재사용한다.

## 현재 개발환경 회원 필드

가입 설정의 식별자는 `user_id`이며 `email_address`도 사용한다. 필수 기본 필드는 아이디, 이메일, 이름, 닉네임, 비밀번호와 비밀번호 확인이다. 요청에 따라 전화번호를 필수 가입 필드로 활성화하고 `homepage`, `blog`는 가입·정보 수정 폼에서 비활성화했다. 기존 회원의 저장값과 Rhymix 필드 정의는 삭제하지 않는다. 닉네임 아래에는 `기수_이름` 형식 캡션을 표시한다. 전화번호는 향후 문자 인증에 사용할 수 있도록 저장하는 단계이며 문자 발송·본인 확인은 아직 구현하지 않는다. `birthday`와 메일링·쪽지 허용, CAPTCHA 및 향후 약관은 기존 Rhymix 렌더러에 남긴다. Figma에 있는 `기수`는 설정에 없었으므로 Rhymix 확장 가입 필드 `generation`(`기수`, 필수 text)로 추가했다. 기존 회원의 이 값은 비어 있을 수 있다.

로그인 폼의 `procMemberLogin`, 가입 폼의 `procMemberInsert`와 `@insertMember` ruleset, 회원정보 수정의 비밀번호 재확인과 `procMemberModifyInfo`, 비밀번호 변경의 `procMemberModifyPassword`를 유지한다. 비밀번호 해시나 현재 비밀번호 값은 마이페이지에 표시하지 않는다. 최근 글은 현재 로그인 회원의 공개 문서 5건을 Rhymix `DocumentModel`에서 조회한다. 전체 작성 글 링크는 기존 `dispMemberOwnDocument`로 연결한다. 스크랩·저장함·댓글·쪽지·친구 등 기존 회원 기능은 KITEL 스타일의 보조 메뉴로 계속 접근할 수 있다.

가입 완료는 현재 개발 설정 `enable_confirm=N`에서 가입 성공 후 Rhymix가 자동 로그인하는 계약을 사용한다. 폼의 `success_return_url`은 `dispMemberInfo&signup_complete=1`로 향하며, 그 페이지는 가입 완료 메시지와 Home/My Page 링크를 표시한다. 메일 확인이 켜진 환경에서는 Rhymix 코어가 로그인 화면과 확인 메일 안내로 보내므로 즉시 이용을 약속하는 완료 화면을 강제하지 않는다. 회원 승인 대기 가능성을 완료 문구에 반영했다.

## 로컬 개발환경 적용

1. `scripts/sync-dev.ps1 -Apply`로 repository 스킨을 로컬 실행본에 동기화한다. dry-run으로 매핑과 충돌을 먼저 볼 수 있다.
2. `D:\rhymix_dev\php\php.exe scripts/configure-phase10-member-dev.php`를 실행해 대상 설정을 확인한다. 기본은 dry-run이다.
3. 같은 명령에 `--apply`를 붙여 정확한 `D:\rhymix_dev\www\rhymix`와 `rhymix_dev:3307` DB에만 데스크톱 스킨, 확인된 KITEL 공통 레이아웃 `166`, 모바일 `/USE_RESPONSIVE/` 스킨 모드와 `mlayout_srl=-2`(PC 공통 레이아웃 재사용), 기수 필드를 설정한다. Rhymix `member` 모듈의 layout 설정도 함께 맞춘다. 설정과 기존 모듈/기수 행은 `D:\rhymix_dev\phase10-member-backups`에 저장된다.
4. Rhymix 관리 화면의 **캐시파일 재생성**을 실행한다. 기존 캐시에는 기본 member 스킨 설정이 남아 있을 수 있다.

운영 DB에는 이 개발용 스크립트를 사용하지 않는다. 운영 적용 시에는 Rhymix 회원 설정에서 같은 skin과 확장 필드를 별도 검토한다.

## 실제 브라우저 QA

- 1440px·390px: Login, Signup, My Page 렌더와 가로 넘침 없음, 콘솔 오류 없음.
- 관리자 로그인과 로그아웃, 잘못된 비밀번호의 Rhymix 오류 대화상자 확인.
- 로컬 임시 계정으로 실제 가입, 기수 저장, 자동 로그인, 가입 완료 화면과 SVG 로드 확인. 테스트 계정은 Rhymix 탈퇴 경로로 정리했고 DB에 남지 않았다.
- 기수가 빈 가입 요청은 거부됐다. 가입 폼은 현재 필수 필드에 브라우저 `required`도 적용한다.
- 마이페이지에서 실제 작성 글 5건 조회와 수정/비밀번호 변경 링크 확인. 정보 수정 전에 코어 비밀번호 재확인을 거쳐 수정 form이 열린다.
- 비밀번호 기존 값을 표시하지 않는다. 익명에서 회원 전용 화면은 코어 권한 정책을 따른다.

## 남은 제품 결정과 범위

- `birthday`의 향후 KITEL 노출 여부는 회원정보 정책 결정이 필요하다. `homepage`와 `blog`는 가입·수정 UI에서 제외했지만 기존 저장값은 보존한다.
- 전화번호를 이용한 실제 문자 발송·인증 정책과 서비스 연동은 추후 결정한다. 필수 입력값이라고 해서 번호 소유가 검증된 것은 아니다.
- `기수`의 허용 형식(예: `49기`), 닉네임 `XX기_OOO` 규칙과 기존 회원의 기수 보완 방법은 별도 운영 정책이다. 현재는 Rhymix 필수 text로 저장한다.
- 로그인 실패는 Rhymix 기본 오류 대화상자를 사용한다. 인증 응답과 오류 문구는 정상이며, 향후 인라인 오류 표현으로 바꿀 수 있다.
- 모바일 최종 세부 간격은 Phase 11에서 다듬는다.

## 전화번호 필드 변경 QA

- 개발 DB의 Rhymix 가입 필드 설정에서 `homepage`, `blog`를 비활성화하고 `phone_number`를 필수·비공개로 활성화했다. 국가 기본값은 대한민국(+82)이다. 기존 회원의 홈페이지·블로그 저장값은 삭제하지 않았다.
- 실제 가입 화면에서 홈페이지·블로그 입력란이 없고 전화번호와 닉네임 아래 `기수_이름` 캡션이 표시됨을 확인했다. 국가와 번호 입력란 모두 브라우저에서 필수 입력이다.
- 로컬 임시 계정으로 국내 번호를 입력하여 가입·완료 화면·마이페이지 저장 표시를 확인했다. 해당 임시 계정은 Rhymix 회원 탈퇴 경로로 정리했다.
- Rhymix 코어는 국내 전화번호 형식을 서버에서도 검사한다. 형식에 맞지 않는 번호는 가입되지 않았다. 문자 발송과 소유자 인증은 현재 구현 범위가 아니므로, 저장된 번호를 인증된 번호로 취급하지 않는다.
- 실제 Android 모바일 UA의 390px 화면에서도 KITEL 모바일 공통 헤더·푸터와 계정 스킨이 렌더되며 가로 overflow가 없음을 확인했다. 세부 모바일 시각 조정은 Phase 11 범위다.

# KITEL P0 — Design Foundation / Codex Handoff

Status: Working baseline  
Scope: Figma design system extraction + current Rhymix codebase alignment  
Figma copy: `0VXi5IY64M8jRJOsc1kX2p`

---

## 1. 목적

이 문서는 Figma 목업을 실제 Rhymix 스킨/레이아웃으로 구현하기 전에 Codex가 공통 디자인 규칙을 먼저 고정하도록 하기 위한 기준 문서다.

중요 원칙:

- Figma의 절대 좌표를 그대로 코드로 옮기지 않는다.
- Figma가 반환하는 React/Tailwind 코드는 **참고용 디자인 컨텍스트**일 뿐이다.
- 실제 구현은 현재 프로젝트의 **Rhymix HTML 템플릿 + 일반 CSS/JS** 구조에 맞춘다.
- 현재 Figma에 들어간 래스터 이미지는 모두 교체 가능한 placeholder로 본다.
- 화면마다 임의의 색/여백/폰트를 새로 만들지 않고 아래 foundation token을 우선 사용한다.

---

# 2. 현재 코드베이스 상태

현재 커스텀 모듈의 기본 스킨은 기능 검증용 임시 스타일에 가깝다.

확인한 파일:

- `custom_modules/calendar/skins/default/list.html`
- `custom_modules/archive/skins/default/list.html`
- `custom_modules/homework/skins/default/index.html`
- `custom_modules/homework/skins/default/view.html`

현재 특징:

- 각 파일 내부에 `<style>`이 직접 포함됨
- 색/spacing/radius가 화면별로 하드코딩됨
- 공통 design token 계층 없음
- 캘린더/자료실/과제 UI는 기능 검증에는 충분하지만 최신 Figma 디자인과는 별개

따라서 Codex 구현 시 **기존 임시 CSS를 디자인 기준으로 삼지 않는다.**
기존 템플릿의 Rhymix 변수/조건/loop 구조는 유지하면서 마크업/스타일을 교체하는 방향이 맞다.

---

# 3. Color foundation

## 3.1 Figma에서 실제 Variable로 확인된 핵심 색

| Semantic token 제안 | Figma 값 | 원본 이름 | 용도 |
|---|---:|---|---|
| `--color-primary` | `#191970` | 블루 | 핵심 브랜드 컬러, 버튼, badge |
| `--color-text-primary` | `#1C1F25` | 블랙 | 주요 본문/내비게이션 |
| `--color-text-muted` | `#A9AEB3` | 연한글씨 | 설명, 비활성 메뉴, meta |
| `--color-primary-soft` | `#EEF1FB` | 연한블루 | 연한 강조 배경 |
| `--color-white` | `#FFFFFF` | White | 기본 surface |

## 3.2 반복적으로 관찰되는 보조 색

| Token 제안 | 값 | 용도 |
|---|---:|---|
| `--color-surface-subtle` | `#F5F5F5` | 검색창, form field, 보조 패널 |
| `--color-surface-footer` | `#F0F0F0` | footer 배경 |
| `--color-border-default` | `#E9E9E9` | pagination/form 경계 |
| `--color-border-row` | `#E2E8F0` | 게시판 row divider |
| `--color-text-secondary` | `#626262` | 보조 text |
| `--color-text-footer` | `#666666` | footer copyright |
| `--color-link-active` | `#1E2F6D` | footer/선택 상태 일부 |
| `--color-placeholder-media` | `#D9D9D9` | Figma placeholder media only |

### 주의

`#D9D9D9`은 실제 브랜드 컬러가 아니라 이미지 placeholder 색으로 본다.
최종 UI에서는 이미지가 없을 때의 skeleton/fallback 용도로만 사용할 수 있다.

---

# 4. Typography

## 4.1 기본 폰트

**Primary font: Pretendard**

Figma의 주요 화면에서 확인된 weight:

- Light
- Regular
- Medium
- SemiBold

Figma 일부 pagination component에 `Inter 14px` variable이 존재하지만, 사이트 전체 typography는 Pretendard가 중심이다.

**구현 원칙:** pagination 하나 때문에 Inter를 별도로 도입하지 말고, 특별한 이유가 없다면 Pretendard로 통일한다.

## 4.2 타입 스케일

실제 Figma에서 반복 확인된 크기를 semantic scale로 정리한다.

| Token 제안 | Size | Weight 예시 | 관찰 용도 |
|---|---:|---|---|
| `--text-xs` | 14px | Regular/Medium | footer link, 작은 CTA |
| `--text-sm` | 16px | Light/Regular/Medium | board meta, submenu |
| `--text-md` | 20px | Regular/Medium | GNB, board row title, button |
| `--text-lg` | 24px | Light/Medium | section nav, subtitle |
| `--text-card-title` | 32px | SemiBold | home feature card |
| `--text-page-title` | 45px | Medium | board page title |
| `--text-display-sm` | 40px | SemiBold | home section heading |
| `--text-display-lg` | 60px | SemiBold | large home heading |

### Letter spacing

게시판 대제목 45px에서 `-1.35px`가 확인됨.
이는 약 `-0.03em`.

제안:

```css
--tracking-heading: -0.03em;
```

### Line-height

- 일반 UI 텍스트: `normal`
- card/body description: 약 `1.4`
- footer copyright: `1.5`

---

# 5. Radius

관찰된 radius를 목적별로 정리한다.

| Token | Value | 사용 |
|---|---:|---|
| `--radius-xs` | 4px | pagination |
| `--radius-sm` | 5px | input, form section, badge |
| `--radius-md` | 20px | pill button, search field |
| `--radius-card` | 20px | home cards/media cards |

현재 디자인은 크게 두 계열이다.

- **5px:** form / utilitarian UI
- **20px:** pill / card / visual UI

임의로 8/12/16px 등의 새 radius를 추가하지 않는다.

---

# 6. Shadow

## Card shadow

Home card에서 확인:

```css
box-shadow: 2px 12px 10px rgba(0, 0, 0, 0.15);
```

## Floating panel / Login

Login card에서 확인:

```css
box-shadow: 2px 12px 20px rgba(0, 0, 0, 0.15);
```

제안 token:

```css
--shadow-card: 2px 12px 10px rgba(0,0,0,.15);
--shadow-panel: 2px 12px 20px rgba(0,0,0,.15);
```

---

# 7. Core geometry

## 7.1 Desktop canvas

Figma 주요 화면 기준:

```text
Desktop reference width: 1920px
Primary content width: 1160px
Centered outer margin at 1920: 380px
```

구현에서는 `margin-left: 380px`처럼 고정하지 않는다.

권장:

```css
.kitel-container {
  width: min(1160px, calc(100% - 48px));
  margin-inline: auto;
}
```

정확한 mobile/tablet padding은 Responsive pass에서 별도 확정한다.

## 7.2 Header

```text
Height: 80px
Nav item width reference: 147px
Nav gap: 36px
Search: 255 × 40
Search radius: 20px
Right-side gap: 16px
Logo reference size: 70 × 70
GNB font: Pretendard Regular 20px
```

Figma의 `Else` 표시는 구현 시 최신 IA 기준 **지원**으로 교체한다.

## 7.3 Footer

```text
Reference height: 579px
Background: #F0F0F0
Content begins at desktop x=380
Top padding reference: 120px
Top category title: 24px
Links: 14–16px
Copyright: 16px / line-height 1.5
```

Footer의 메뉴 구조는 GNB IA와 일치시킨다.

---

# 8. Component foundation

## 8.1 Primary pill button

관찰:

```text
Height: 40px
Radius: 20px
Background: #191970
Text: white
Typical text: 20px Medium
```

대표:
- Login
- 쓰기

작은 CTA variant:

```text
Height: 36px
Radius: 20px
Text: 14px Medium
Horizontal padding: ~12px
```

## 8.2 Search field

```text
Width reference: 255px
Height: 40px
Radius: 20px
Background variant: #F5F5F5
Outline variant: 1px #A9AEB3
Icon: 24px
```

## 8.3 Form field

Login:

```text
Height: 60px
Radius: 5px
Border: 1px #1C1F25
Placeholder: 16px muted
```

Board write:

```text
Height: 50px
Radius: 5px
Background: #F5F5F5
Large placeholder text: 24px Light
```

두 형태를 하나로 억지 통일하지 않고:

- `input-standard`
- `input-soft`

두 variant로 관리한다.

## 8.4 Board row

관찰:

```text
Height: 75px
Bottom border: #E2E8F0
Title: 20px Medium
Meta: 16px Light
Category badge: primary #191970 / white / radius 5
```

## 8.5 Pagination

```text
Height: 36px
Radius: 4px
Border: #E9E9E9
Gap: 6px
Base text/icon: 14px
```

## 8.6 Home media card

```text
Card width reference: 373px
Card height reference: ~434.5px
Card radius: 20px
Shadow: --shadow-card
Title: 24px SemiBold
Body: 16px Light, line-height 1.4
CTA: 36px pill
```

이미지는 반드시 dynamic media slot으로 처리한다.

---

# 9. Spacing policy

Figma에는 absolute positioning으로 인해 많은 임의 수치가 존재한다.
이를 그대로 spacing token으로 만들지 않는다.

Foundation spacing은 실제 반복값 중심으로 단순화한다.

제안 scale:

```text
4   micro
6   pagination gap
8   compact
12  control padding
16  common gap
20  content gap
24  section-inner
36  header nav gap
40  control / vertical unit
48  section compact
80  header height / major rhythm
120 section top reference
```

CSS token 예:

```css
--space-1: 4px;
--space-2: 6px;
--space-3: 8px;
--space-4: 12px;
--space-5: 16px;
--space-6: 20px;
--space-7: 24px;
--space-8: 36px;
--space-9: 40px;
--space-10: 48px;
--space-11: 80px;
--space-12: 120px;
```

**중요:** 이 scale은 구현 편의를 위한 normalization이다.
Figma의 모든 좌표를 token으로 만들라는 뜻이 아니다.

---

# 10. Media policy

현재 Figma bitmap은 임시 이미지다.

따라서 구현은 다음처럼 데이터 기반 슬롯으로 만든다.

```text
HeroMedia
ArticleCover
FeatureMedia
GalleryThumbnail
ExecutiveProfileImage
```

원칙:

- URL/파일 교체 가능
- layout geometry는 유지
- `object-fit: cover` 기본
- crop 영역은 container가 결정
- placeholder 이미지 내용을 component semantic으로 사용 금지

예:
- `LeafImage` ❌
- `SpaceBanner` ❌
- `GalleryThumbnail` ✅

---

# 11. Codex implementation rules

Codex에게 반드시 전달할 규칙:

1. Figma MCP의 React/Tailwind 출력물을 그대로 복사하지 않는다.
2. Tailwind를 새 dependency로 설치하지 않는다.
3. Rhymix 템플릿 구조를 유지한다.
4. 기능 검증용 기존 inline CSS는 새 디자인 기준으로 교체한다.
5. 공통 token CSS를 먼저 만든 후 페이지 CSS가 이를 참조하게 한다.
6. desktop Figma absolute coordinate를 CSS absolute layout으로 재현하지 않는다.
7. 공통 container / flex / grid 중심으로 재구성한다.
8. 이미지 placeholder는 dynamic slot으로 만든다.
9. 기존 권한/loop/cond 로직은 디자인 변경 과정에서 제거하지 않는다.
10. Figma의 `Else`는 최신 IA 기준 `지원`으로 노출한다.
11. 모든 새 화면을 페이지별 독립 CSS 복붙 방식으로 만들지 않는다.
12. Generic Board의 List / Detail / Write / Comments는 가능한 한 공통화한다.

---

# 12. 권장 CSS foundation 파일

구현 시 아래와 비슷한 구조를 권장한다.

```text
assets/css/
├─ tokens.css
├─ reset.css
├─ typography.css
├─ layout.css
├─ components.css
└─ utilities.css   # 꼭 필요한 최소 범위
```

또는 Rhymix layout/skin 배치 구조에 맞게 파일 위치를 조정하되,
**token 정의는 한 곳에 존재**해야 한다.

예상 `tokens.css` 시작점:

```css
:root {
  --color-primary: #191970;
  --color-primary-soft: #eef1fb;

  --color-text-primary: #1c1f25;
  --color-text-secondary: #626262;
  --color-text-muted: #a9aeb3;

  --color-surface: #ffffff;
  --color-surface-subtle: #f5f5f5;
  --color-surface-footer: #f0f0f0;

  --color-border-default: #e9e9e9;
  --color-border-row: #e2e8f0;

  --radius-xs: 4px;
  --radius-sm: 5px;
  --radius-md: 20px;
  --radius-card: 20px;

  --shadow-card: 2px 12px 10px rgba(0,0,0,.15);
  --shadow-panel: 2px 12px 20px rgba(0,0,0,.15);

  --container-main: 1160px;

  --font-family-base: "Pretendard", sans-serif;

  --text-xs: 14px;
  --text-sm: 16px;
  --text-md: 20px;
  --text-lg: 24px;
  --text-card-title: 32px;
  --text-display-sm: 40px;
  --text-page-title: 45px;
  --text-display-lg: 60px;

  --tracking-heading: -0.03em;
}
```

이 코드는 최종 구현물이 아니라 **Codex가 시작할 foundation contract**다.

---

# 13. P0에서 확인된 정리 필요 사항

## Figma 내부 inconsistency

### Pretendard vs Inter
일부 Figma component variable에 Inter가 존재하지만 주요 화면은 Pretendard다.
→ 구현은 Pretendard 중심으로 정규화.

### 동일 역할에 여러 gray
`#A9AEB3`, `#626262`, `#666666`, `#313131` 등이 섞여 있다.
→ 의미별 token으로 정리하고 새 색을 화면마다 직접 쓰지 않는다.

### 14px / 16px footer link 혼재
Figma Footer의 About 하위 일부는 14px, 다른 메뉴는 16px.
→ 시각적으로 의도된 차이가 아니라면 구현 전에 한 규칙으로 정규화하는 것을 권장.
현재 baseline은 16px을 기본 submenu로 보고, 14px은 compact variant로 유지한다.

---

# 14. P0 완료 조건

현재 기준으로 다음은 완료:

- [x] Primary colors 추출
- [x] Typography family/weight/size 추출
- [x] Radius 추출
- [x] Shadow 추출
- [x] Desktop main container 추출
- [x] Header geometry 추출
- [x] Footer geometry 추출
- [x] Button/Input/Search 기본 규칙 추출
- [x] Board row/pagination 기본 규칙 추출
- [x] Media placeholder 정책 반영
- [x] 현재 Rhymix 임시 skin 구조와 충돌점 확인

다음 단계:

**P1 — Shared Layout Specification**
- Header state
- GNB dropdown/navigation behavior
- logged-in / logged-out state
- footer structure
- content container rules
- responsive behavior

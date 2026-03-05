# WithPlan - 프로젝트 레퍼런스

## 기본 정보

- **도메인:** `withplan.deurim.com` | **서버:** Synology + PHP 8.4 + Apache 2.4 + MariaDB
- **Document Root:** `/withplan/public/` (`.env`, `config/`, `vendor/` 웹 노출 방지)
- **CSS_VERSION:** `4.2.3` → `app/includes/header.php` 상수. CSS/JS 수정 시 반드시 increment
  - 패치(마지막 자리): 미세 수정 | 마이너(중간): 주요 변경 | 메이저(첫 자리): 전면 개편
  - 최근 업데이트: 설정 페이지 전면 개선 (4.2.0) + QR 기능 추가 (4.2.1~4.2.3)

---

## URL 패턴

```
/                          랜딩 (공개)
/my                        오너 대시보드 (Google OAuth 필요)
/new                       여행 생성 (Google OAuth 필요)
/{trip_code}/              user_id 입력 폼 → /{trip_code}/{user_id}/ 리다이렉트
/{trip_code}/{user_id}/    홈 (PIN 인증 필요)
  /schedule  /budget  /checklist  /notes  /settings
```

- 존재하지 않는 trip_code/user_id → `/` 로 조용히 리다이렉트 (존재 여부 노출 금지)
- `/settlement` → `budget#settlement` 탭으로 리다이렉트
- `/todo` → `checklist` 할일 탭으로 리다이렉트

---

## 인증 패턴

**오너:** Google OAuth (`league/oauth2-google`) → `owners` 테이블 upsert → `/my` 접근
**멤버:** PIN 6자리 (bcrypt). `pin_hash IS NULL` → PIN 설정 화면. 세션 1일 / 7일 유지 체크박스
**PIN 브루트포스:** 3회 실패 → 5분 잠금 (`pin_attempts` 테이블, IP+trip_code+user_id 조합)

---

## DB 테이블 현황

`owners` / `trips`(active_currencies) / `users`(is_owner, pin_hash)
`schedule_days` / `schedule_items`(end_time, is_all_day, memo, google_maps_url, category)
`expenses`(expense_time) / `dutch_splits` / `incomes`(type: budget/refund/other, income_time)
`checklists`(assigned_to: comma-sep) / `checklist_completions`(개인별 완료)
`todos`(assigned_to: comma-sep) / `todo_completions`(개인별 완료)
`shared_notes` / `contact_submissions` / `pin_attempts`
`trip_exchange_rates`(rate TTS, rate_adjustment 카드조정, cash_rate 현금환전, cash_exchanger_id)

> DB 직접 조작 금지. 스키마 변경 → `database/init.sql` 수정 후 사용자에게 phpMyAdmin 실행 요청.

---

## 구현 패턴

### 파일 분리
- `app/pages/` → 페이지 PHP | `app/api/` → API (기능별 파일 분리)
- `public/assets/css/pages/` + `public/assets/js/pages/` → 페이지별 CSS/JS
- 공통: `common.css` / `common.js` | 함수 50줄↑ 분리 | 파일 200줄↑ 분리 필수

### 공통 헤더/푸터
- `app/includes/header.php`: `$pageCss`, `$bodyClass`, `$headExtra`, `$pageJsExtra` 변수로 커스텀
- `app/includes/page_header.php`: 멤버 페이지 공통 헤더 partial (more_vert 드롭다운)
- `app/includes/footer.php`: 하단 네비 홈/일정/지출/체크/메모 + `$pageJsExtra` 배열 지원 (다중 JS 로드)

### 모달
- Sheet 모달 (`translateY(100%)→0` 슬라이드업). `hidden`/`visible` 클래스 기반
- `requestAnimationFrame`으로 `hidden` 제거 후 `visible` 추가. 닫을 때 250ms timeout
- `WP.confirm()` Promise 기반 삭제 확인 (브라우저 `confirm()` 사용 금지)
- ESC 키 / 오버레이 클릭 닫기 필수

### 탭 구조
- `page-tabs` / `page-tab-btn` / `tab-pane` 클래스 통일 (common.css)
- `tab-pane { display:none }` / `tab-pane.active { display:block }`
- 하단 네비 버튼이 해당 페이지일 때 탭 전환 (redirect 대신 JS toggle)

### API
- 응답: `{"success": bool, "data": ..., "message": "..."}` JSON 통일
- CSRF 토큰: 모든 POST/PUT/DELETE에 삽입, 세션 토큰과 비교 검증
- XSS: 출력 시 `htmlspecialchars()` / JS에서 `WP.escapeHtml()` | SQL Injection: PDO prepared statements

### 공통 헬퍼 함수
- `WP.escapeHtml(str)` - HTML 이스케이프 (JS용, textContent 기반)
- `formatDateRangeKorean(start, end)` - 여행 기간 포맷 (D-day/여행일차/완료 표시)

---

## 페이지 구현 현황

### ✅ 설정 페이지 (`/{trip_code}/{user_id}/settings`)
**v4.2.3 완성**
- 섹션 순서: 내 정보 → 보안 → 멤버 → 여행정보 → 환율 → 로그아웃 → 앱정보
- **내 정보**: 표시 이름 인라인 편집 (Sheet 모달), ID 표시
- **보안**: PIN 변경, 멤버 PIN 초기화 (오너 전용)
- **멤버**: 아바타 + 이름 + 뱃지, 링크 공유 (Web Share / 클립보드), QR 코드 표시, 삭제 (오너 전용), 일괄 공유 (오너 전용)
- **여행정보**: 제목/설명/목적지/기간 (한국어 포맷 D-day/여행일차), 여행 코드 + QR, 수정 (오너 전용)
- **환율**: 통화 선택 칩 + 조정값 + 현금환전률 (settings-rates.js로 분리)
- **로그아웃**: 세션 종료 버튼
- **앱정보**: 버전 표시

### ✅ 랜딩 페이지 (`/`)
**QR 스캔 기능 추가 (v4.2.2~v4.2.3)**
- 플로팅 바: 여행 코드 입력 + QR 스캔 버튼 (항상 표시)
- CTA 섹션: 여행 코드 입력 + QR 스캔 버튼
- QR 모달: html5-qrcode로 전체화면 카메라 스캔, trip_code 추출 자동 리다이렉트

---

## 홈 대시보드 상태별 뷰

`tripPhase`: `before` / `during` / `after` / `no_date`
여행 전: 준비물·할일 완료율, 마감 임박 할일, 1일차 일정 미리보기
여행 중: 오늘 일정 타임라인 (1분마다 갱신), 오늘 지출, 최근 메모
여행 후: 총 지출, 멤버별 지출 바 차트, 완료 현황

---

## 환율/정산 패턴

- `trip_exchange_rates`: 통화별 TTS + 카드조정환율 + 현금환전환율 + 환전자 저장
- 정산 시 카드(rate+adjustment) vs 현금(cash_rate) 환율 구분 적용
- `trips.active_currencies`: 사용 통화 선택 (KRW 항상 활성, 나머지 10개 외화 선택)
- 정산: 전체/날짜별/필터(전체/카드/현금) 뷰 지원

---

## 개발 규칙

- **AJAX 우선:** 폼 submit 페이지 리로드 금지. `fetch()` + DOM 부분 업데이트
- **에러 메시지 한국어:** `"Failed to fetch"` 등 기술 메시지 직접 노출 금지
- **환경 분기:** `APP_ENV=production`에서 스택 트레이스 노출 절대 금지
- **민감 정보:** `.env`에서만 로드. PHP 하드코딩 금지
- **보안 헤더:** `.htaccess`에 `X-Frame-Options DENY`, `camera=(self)` 등 헤더 설정
  - `camera=(self)`: 같은 도메인에서 카메라 접근 허용 (QR 스캔 기능용)

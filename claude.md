# WithPlan - Claude Code 작업 지시서

## 프로젝트 개요

가족/소규모 그룹의 여행 계획 수립, 준비 자료 공유, 예산 관리, 더치페이 정산을 위한 모바일 우선 웹 앱.

- **도메인:** `withplan.deurim.com`
- **서버:** Synology WebStation + PHP 8.4 (Apache 2.4, FPM 온 디멘드 모드)
- **DB:** MariaDB + phpMyAdmin (Synology 내장)
- **설정:** `.env` 파일로 민감 정보 관리

---

## 기술 스펙

### 레이아웃 원칙
- **모바일 우선 설계** (기준 너비: 390px)
- PC 접속 시: 모바일 화면 그대로 중앙 정렬, 좌우 여백 처리 (max-width: 480px, margin: 0 auto)
- 별도 PC 레이아웃 없음

### URL 구조
```
withplan.deurim.com/
withplan.deurim.com/{trip_code}/
withplan.deurim.com/{trip_code}/{user_id}/
withplan.deurim.com/{trip_code}/{user_id}/schedule
withplan.deurim.com/{trip_code}/{user_id}/budget
withplan.deurim.com/{trip_code}/{user_id}/checklist
withplan.deurim.com/{trip_code}/{user_id}/todo
withplan.deurim.com/{trip_code}/{user_id}/settlement
withplan.deurim.com/{trip_code}/{user_id}/members
withplan.deurim.com/{trip_code}/{user_id}/notes
withplan.deurim.com/contact
withplan.deurim.com/
withplan.deurim.com/{trip_code}/
```

- `/{trip_code}/` 로 접근 시: user_id 입력 폼만 표시 → 입력 즉시 `/{trip_code}/{user_id}/` 로 리다이렉트 (PIN은 거기서 처리)
- `trip_code`: 여행 생성 시 서버에서 자동 생성 (영문+숫자 8자리, 수정 불가)
- `user_id`: 플랜 생성자가 직접 지정하는 문자열 ID (예: `dad`, `mom`, `jimin`)

### 인증

#### 오너 (여행 생성자)
- **Google OAuth 2.0** 로그인은 `/my` 관리 대시보드 전용
- `/my` 에서 여행 생성, 멤버 추가/삭제, 여행 수정/삭제 등 관리 기능만 수행
- 여행 생성 시 오너 본인의 user_id와 display_name도 함께 지정 (`users` 테이블에 `is_owner=1`로 저장)
- 실제 여행 플랜 접근(`/{trip_code}/{user_id}/`)은 일반 멤버와 동일하게 URL + PIN 방식으로 진입
- 오너도 최초 접근 시 PIN 설정 필요 (일반 멤버와 동일한 흐름)
- Composer 패키지: `league/oauth2-google` 사용
- OAuth 콜백 URL: `withplan.deurim.com/auth/google/callback`

#### 모든 멤버 (오너 포함)
- `/{trip_code}/{user_id}/` URL로 직접 접근 (user_id 아는 경우)
- `/{trip_code}/` URL로 접근 시 user_id 입력 폼만 표시 → `/{trip_code}/{user_id}/` 로 리다이렉트 후 PIN 처리
- **최초 접근 시:** PIN 미설정 상태(DB에 NULL) → PIN 설정 화면으로 이동 (6자리 숫자, 입력+확인)
- **이후 접근 시:** 세션 유효하면 바로 입장, 세션 만료 시 PIN 입력 화면으로 이동
- PIN은 DB에 bcrypt 해시로 저장
- PIN 변경: 각 페이지 내 설정 메뉴에서 현재 PIN 확인 후 변경 가능
- 세션 유효 시간: 기본 **1일**
- PIN 입력 화면에 **"이 기기에서 7일간 유지" 체크박스** 제공. 체크 시 세션 유효 시간 7일로 연장 (`session_set_cookie_params()` 활용)

### .env 파일 구조 (예시 제공, 실제 값은 사용자가 입력)
```
APP_ENV=production
DB_HOST=localhost
DB_NAME=withplan
DB_USER=withplan_user
DB_PASS=your_password
SESSION_SECRET=random_secret_string
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://withplan.deurim.com/auth/google/callback
SMTP_HOST=your_smtp_host
SMTP_USERNAME=your_smtp_username
SMTP_PASSWORD=your_smtp_password
SMTP_PORT=587
SMTP_FROM_EMAIL=support@deurim.com
SMTP_FROM_NAME=WithPlan
```

---

## DB 설계

> **중요:** Claude Code는 DB에 직접 접근하지 않는다.
> 모든 테이블 생성 SQL은 `database/init.sql` 파일 하나로 저장한다.
> DB명은 `withplan` (이미 생성되어 있음). 사용자가 phpMyAdmin 콘솔에서 해당 파일을 직접 실행한다.
>
> `database/init.sql` 파일 구조:
> ```sql
> USE withplan;
>
> -- 각 테이블 CREATE TABLE 문 순서대로
> -- (외래키 의존성 고려하여 순서 정렬)
> ```
>
> 파일 생성 후 사용자에게 안내: "phpMyAdmin에서 `withplan` DB 선택 후 SQL 탭에서 `database/init.sql` 내용을 붙여넣고 실행해주세요."

### 테이블 목록

#### `owners` - Google OAuth 오너 계정
```sql
CREATE TABLE owners (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  google_id     VARCHAR(100) NOT NULL UNIQUE,
  email         VARCHAR(200) NOT NULL,
  display_name  VARCHAR(100),
  last_ip       VARCHAR(45) DEFAULT NULL COMMENT 'Cloudflare CF-Connecting-IP 우선, 없으면 REMOTE_ADDR',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `trips` - 여행 기본 정보
```sql
CREATE TABLE trips (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL UNIQUE,
  owner_google_id VARCHAR(100) NOT NULL,
  title       VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL COMMENT '여행 플랜 상세 설명',
  destination VARCHAR(100),
  start_date  DATE,
  end_date    DATE,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `users` - 여행 참여자
```sql
CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  display_name VARCHAR(50) NOT NULL,
  pin_hash    VARCHAR(255) DEFAULT NULL COMMENT '최초 접근 시 설정, 설정 전 NULL',
  is_owner    TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_trip_user (trip_code, user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `schedule_days` - 일자별 일정
```sql
CREATE TABLE schedule_days (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  day_number  INT NOT NULL,
  date        DATE,
  title       VARCHAR(100),
  note        TEXT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `schedule_items` - 일정 내 세부 항목
```sql
CREATE TABLE schedule_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  day_id      INT NOT NULL,
  trip_code   VARCHAR(8) NOT NULL,
  time        VARCHAR(10),
  content     VARCHAR(200) NOT NULL,
  location    VARCHAR(100),
  sort_order  INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `budget_categories` - 예산 카테고리
```sql
CREATE TABLE budget_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  name        VARCHAR(50) NOT NULL,
  planned_amount DECIMAL(12,0) DEFAULT 0,
  currency    VARCHAR(3) DEFAULT 'KRW',
  sort_order  INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `expenses` - 실지출 내역
```sql
CREATE TABLE expenses (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  category_id INT,
  paid_by     VARCHAR(30) NOT NULL,
  amount      DECIMAL(12,0) NOT NULL,
  currency    VARCHAR(3) DEFAULT 'KRW',
  description VARCHAR(200),
  expense_date DATE,
  is_dutch     TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `dutch_splits` - 더치페이 분담 내역
```sql
CREATE TABLE dutch_splits (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  expense_id  INT NOT NULL,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  amount      DECIMAL(12,0) NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `checklists` - 준비물 체크리스트
```sql
CREATE TABLE checklists (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  category    VARCHAR(50),
  item        VARCHAR(100) NOT NULL,
  assigned_to VARCHAR(30),
  is_done     TINYINT(1) DEFAULT 0,
  sort_order  INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `todos` - To-Do (예약 등 해야 할 것들)
```sql
CREATE TABLE todos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  title       VARCHAR(100) NOT NULL,
  detail      TEXT,
  assigned_to VARCHAR(30),
  due_date    DATE,
  is_done     TINYINT(1) DEFAULT 0,
  sort_order  INT DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `shared_notes` - 공유 메모/자료
```sql
CREATE TABLE shared_notes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  author_id   VARCHAR(30) NOT NULL,
  title       VARCHAR(100),
  content     TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `contact_submissions` - 문의/제안 접수 기록
```sql
CREATE TABLE contact_submissions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(200) NOT NULL,
  category    ENUM('general', 'bug', 'feature') NOT NULL,
  content     TEXT NOT NULL,
  ip          VARCHAR(45),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### `pin_attempts` - PIN 입력 실패 횟수 (브루트포스 방어)
```sql
CREATE TABLE pin_attempts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45) NOT NULL,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  attempts    INT DEFAULT 1,
  locked_until DATETIME DEFAULT NULL COMMENT '잠금 해제 시각, NULL이면 잠금 없음',
  last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ip_user (ip, trip_code, user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 기능 명세

### 0. 랜딩 페이지 (`/`)
- 로그인 불필요, 누구나 접근 가능
- **디자인 톤:** 유려하고 깔끔한 풀페이지 스크롤. 하와이 느낌의 청록/코랄 계열 그라디언트 활용. 여백 충분히
- **구성 섹션:**
  1. **Hero 섹션** - 서비스 핵심 한 줄 소개 + Google 로그인 CTA 버튼 (오너용)
  2. **기능 소개 섹션** - 일정 관리 / 예산 관리 / 더치페이 정산 / 체크리스트 등 주요 기능 카드 형태로 소개
  3. **계획 접속 섹션** - "초대받으셨나요?" 안내 문구 + trip_code 입력 폼 + 확인 버튼
     - trip_code 입력 후 확인 → `/{trip_code}/` 로 리다이렉트 (user_id 입력은 거기서 처리)
     - 존재하지 않는 trip_code 입력 시 인라인 에러 메시지 표시
  4. **Footer** - 문의/제안 링크(`/contact`), 간단한 서비스 설명
- Google 로그인 버튼 클릭 시 `/auth/google` 로 이동
- 이미 Google 로그인된 상태면 로그인 버튼 대신 "내 여행 관리 →" 버튼으로 대체 (`/my`로 이동)

### 1. 여행 생성 (`/new`)
- Google OAuth 2.0 로그인 후에만 접근 가능 (비로그인 시 Google 로그인 페이지로 리다이렉트)
- 로그인 완료 후 여행 생성 폼 표시
- 입력: 여행 제목, 상세 설명(선택), 목적지, 날짜, 오너 본인의 user_id, display_name
- 오너 user_id는 `users` 테이블에 `is_owner=1`로 저장. PIN은 미설정(NULL) 상태로 생성되며 첫 접속 시 설정
- `trip_code` 서버에서 자동 생성 (중복 확인 후 발급)
- 생성 완료 후 `/{trip_code}/{user_id}/` 로 리다이렉트
- 생성된 URL을 복사 공유할 수 있도록 클립보드 복사 버튼 제공
- `/my` 페이지에서 본인이 생성한 여행 목록 조회, 수정(제목/설명/목적지/날짜), 삭제 가능 (Google 로그인 유지 필요)

### 2. 멤버 관리 (`/members`)
- Google 로그인된 오너만 접근 가능
- 새 멤버 추가: user_id + display_name 입력 (PIN은 멤버가 최초 접근 시 직접 설정)
- 멤버 목록 조회
- 멤버 삭제 (오너 제외)
- 각 멤버의 접속 URL 복사 기능 (복사 시 `/{trip_code}/{user_id}/` 형태로 클립보드에 저장)

### 3. 오너 관리 대시보드 (`/my`)
- Google OAuth 로그인 후에만 접근 가능
- `/auth/google` : Google OAuth 로그인 시작
- `/auth/google/callback` : OAuth 콜백 처리, 세션 저장 후 `/my`로 리다이렉트
- `/auth/logout` : Google 세션 삭제
- `/my` : **관리 전용 대시보드**
  - 본인이 생성한 여행 목록 조회
  - 여행 생성/수정/삭제
  - 멤버 추가/삭제 및 접속 URL 복사
  - 각 여행의 PIN 설정 현황 확인 (누가 아직 PIN 미설정인지)
  - 여행 플랜 자체(일정/예산 등) 조회/편집은 `/{trip_code}/{user_id}/`로 별도 접근

### 4. 홈 대시보드 (`/{trip_code}/{user_id}/`)
- 여행 제목 + 상세 설명 표시
- 여행 D-day 카운트다운
- 주요 수치 요약: 총 예산 대비 지출 비율, 완료된 To-Do 수, 체크리스트 완료율
- 하단 네비게이션 바: 일정 / 예산 / 체크리스트 / To-Do / 정산 탭

### 5. 일정표 (`/schedule`)
- Day 1, Day 2... 카드 형태로 표시
- 날짜 + 제목 + 메모 편집 가능
- 각 Day 하위에 시간대별 항목 추가/수정/삭제/순서 변경 (드래그 or 위아래 버튼)
- 항목: 시간, 내용, 장소(선택)
- 전체 Day 수는 여행 기간에 맞게 자동 생성, 수동 추가도 가능

### 6. 예산 관리 (`/budget`)
두 개의 탭으로 구분:

#### 탭 1: 예산 계획
- 카테고리별 예산 입력 (항공, 숙박, 식비, 교통, 액티비티, 쇼핑, 기타 등 - 커스텀 가능)
- 통화 선택 (KRW / USD)
- 카테고리별 계획 금액 vs 실지출 합계 비교 바 차트
- 총합 요약

#### 탭 2: 지출 입력
- 지출 추가: 카테고리, 결제자(user_id), 금액, 통화, 설명, 날짜
- 더치페이 여부 체크박스
  - 더치페이: 참여 인원 선택 후 금액 자동 분배 (균등 or 수동 입력)
  - 더치페이 아님: 해당 결제자 단독 지출로 기록
- 지출 목록: 날짜순 정렬, 수정/삭제 가능
- 간단한 USD↔KRW 환율 입력 필드 (직접 입력, 자동 환산)

### 7. 체크리스트 (`/checklist`)
- 카테고리별 그룹핑 (서류, 의류, 상비약, 전자기기, 다이빙 장비 등 - 커스텀)
- 항목별 체크박스 + 담당자 표시
- 항목 추가/삭제
- 완료 항목은 하단으로 이동 또는 흐리게 처리
- 전체 완료율 표시

### 8. To-Do 리스트 (`/todo`)
- 예약/준비해야 할 것들 관리
- 항목: 제목, 상세, 담당자, 마감일, 완료 여부
- 마감일 기준 정렬, D-day 표시
- 기한 초과 항목 빨간색 강조
- 완료 토글

### 9. 정산 (`/settlement`)
- 각 멤버별 지출 합계 vs 부담해야 할 금액 비교
- "누가 누구에게 얼마를 줘야 하는가" 최소 이체 수로 계산하여 표시
- **통화 혼용 처리:** KRW/USD 지출이 섞인 경우, 예산 관리 페이지에서 입력한 환율을 기준으로 전체를 KRW로 통합하여 단일 통화 정산. 환율 미입력 시 통화별 분리 표시
- 정산 완료 체크 기능 (체크 시 해당 항목 취소선)

### 10. 공유 메모 (`/notes`) - 선택 메뉴
- 자유 형식 메모 작성 (현지 맛집, 유용한 링크, 숙소 정보 등)
- 작성자 + 작성일 표시
- 전체 멤버 열람 가능, 작성자만 수정/삭제

### 11. 문의 및 제안 (`/contact`)
- 로그인 불필요, 누구나 접근 가능한 공개 페이지
- 입력 항목: 이름, 이메일, 문의 유형(일반 문의 / 버그 신고 / 기능 제안), 내용
- 제출 시 PHPMailer로 `support@deurim.com` 으로 메일 발송
  - 발신자: `.env`의 `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME`
  - 제목: `[WithPlan 문의] {문의유형} - {이름}`
  - 본문: 이름, 이메일, 유형, 내용 포함 HTML 템플릿
  - ReplyTo: 제출자 이메일 (답장 시 바로 제출자에게 발송)
- SMTP 설정은 `.env`에서 로드 (host, username, password, port, from_email, from_name)
- 발송 성공/실패 여부 사용자에게 안내

---

## UI/UX 가이드

### 디자인 톤
- 여행 설렘을 담은 밝고 경쾌한 톤
- 기본 컬러: 하와이 느낌의 청록/코랄 계열 추천 (또는 변수로 처리하여 커스텀 가능)
- 폰트: 시스템 폰트 우선, 한국어 최적화

### 모바일 UX
- 하단 탭 네비게이션 (5개 주요 메뉴)
- 터치 친화적 버튼 크기 (최소 44px)
- 입력 시 키보드 올라오는 것 고려한 레이아웃
- 스크롤은 최대한 한 방향(세로)으로
- 로딩 상태 표시

### 실시간 사용 고려
- 지출 입력 폼은 최대한 빠르게 접근 가능하도록 (홈에서 1탭)
- 입력 항목 최소화, 필수 항목만 강제
- 오프라인 대비: 입력 실패 시 명확한 에러 메시지

---

## 파일 구조 (권장)

```
/withplan/                     # 프로젝트 루트 (웹 직접 접근 불가)
  /database/
    init.sql                   # 전체 테이블 생성 SQL (phpMyAdmin에서 직접 실행)
  .env                         # 환경변수 (웹 노출 금지)
  /config/
    db.php                     # DB 연결 설정 (웹 노출 금지)
  /app/
    auth.php                   # Google OAuth 인증 처리 (login/callback/logout)
    helpers.php                # 유틸 함수 (trip_code 생성, IP 감지 등)
    /api/
      contact.php
      trips.php
      schedule.php
      budget.php
      checklist.php
      todo.php
      settlement.php
      members.php
      notes.php
    /pages/
      home.php
      schedule.php
      budget.php
      checklist.php
      todo.php
      settlement.php
      members.php
      notes.php
      pin_change.php
      my.php                   # 오너 여행 목록
    /includes/
      header.php               # 공통 헤더, 네비게이션 (CSS_VERSION 상수 관리)
      footer.php
      auth_check.php           # 세션 인증 미들웨어
    /views/
      errors/
        404.php
        403.php
        500.php
  /vendor/                     # Composer (웹 직접 접근 차단)
  /public/                     # ★ 웹 서버 Document Root (이 디렉토리만 공개)
    .htaccess                  # URL rewrite + 보안 헤더 + vendor/ 차단
    index.php                  # 프론트 컨트롤러 (display_errors=0, 라우팅)
    new.php                    # 여행 생성 페이지 (Google 로그인 필요)
    contact.php                # 문의 및 제안 페이지 (공개)
    /assets/
      css/
        main.css               # ?v=CSS_VERSION 으로 캐시 버스팅
        pages/
          landing.css            # 랜딩 페이지 전용 CSS
      js/
        main.js
```

> **CRITICAL:** Synology WebStation 가상 호스트의 Document Root를 반드시 `/withplan/public/` 으로 설정할 것. 프로젝트 루트로 설정하면 `.env`, `config/`, `vendor/` 등이 웹에 노출됨.

---

## Composer 의존성

프로젝트 루트에서 아래 명령어로 설치:

```bash
composer require vlucas/phpdotenv
composer require bramus/router
composer require league/oauth2-google
composer require phpmailer/phpmailer
```

### 패키지 용도

- **vlucas/phpdotenv** - `.env` 파일 로드 (DB 접속 정보, 세션 시크릿 등)
- **bramus/router** - URL 라우팅 (`/{trip_code}/{user_id}/...` 패턴 처리)
- **league/oauth2-google** - Google OAuth 2.0 로그인 처리
- **phpmailer/phpmailer** - SMTP 이메일 발송 (문의/제안 접수)

> PHP PDO, bcrypt(`password_hash`), 세션 등은 PHP 내장 기능 사용. 그 외 추가 패키지 필요 시 composer require로 설치하고 사용자에게 명령어 안내.

### autoload 로드 (`config.php` 상단에 추가)
```php
// public/index.php 기준 (Document Root가 public/이므로 한 단계 상위)
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
```

---

## 추가 구현 지침

1. **존재하지 않는 trip_code / user_id 접근 처리:**
   - `/{trip_code}/` 진입 시 DB에 해당 trip_code 없으면 → 메인 페이지(`/`)로 리다이렉트
   - `/{trip_code}/{user_id}/` 진입 시 trip_code 또는 user_id가 DB에 없으면 → 메인 페이지(`/`)로 리다이렉트
   - 에러 메시지 없이 조용히 리다이렉트 (존재 여부 노출 방지)
2. **trip_code 생성 로직:** `bin2hex(random_bytes(4))` 등으로 8자리 고유 코드 생성, DB 중복 확인 후 재시도
3. **멤버 PIN 흐름:** 최초 접근 시 `pin_hash IS NULL` 확인 → PIN 설정 페이지로 리다이렉트 → 설정 완료 후 세션 저장. 세션 만료 후 재접근 시 PIN 입력 → `password_verify()` 검증 후 세션 재발급. "이 기기에서 7일간 유지" 체크 여부에 따라 세션 만료 시간 분기 처리
4. **Google OAuth 흐름:** `league/oauth2-google`으로 인증 후 `google_id` + `email`을 `owners` 테이블에 upsert, 세션에 저장
5. **URL 라우팅:** `index.php`에서 `$_SERVER['REQUEST_URI']`를 파싱하여 해당 page include
6. **API 응답:** JSON 형태로 통일, `{"success": true/false, "data": ..., "message": "..."}`
7. **XSS 방지:** 출력 시 `htmlspecialchars()` 필수
8. **SQL Injection 방지:** PDO prepared statements 사용
9. **환율:** 고정값 직접 입력 방식 (외부 API 연동 없음)
10. **.htaccess:** URL rewrite로 `index.php`로 모든 요청 라우팅
11. **display_errors 비활성화:** `public/index.php` 최상단에 `ini_set('display_errors', 0);` 추가. `APP_ENV=development`일 때만 에러 상세 출력, `production`에서는 커스텀 에러 페이지로 대체
12. **Post-Login Redirect:** Google OAuth 로그인 시작 전 현재 URL을 `$_SESSION['redirect_after_login']`에 저장 → 콜백 완료 후 해당 URL로 복귀 (없으면 `/my`). `/`로 시작하는 내부 URL만 허용, `//`로 시작하는 URL은 차단
13. **Login IP 추적:** 오너 로그인 시 `CF-Connecting-IP` 헤더 우선 확인 → 없으면 `X-Forwarded-For` → 없으면 `REMOTE_ADDR` 순으로 IP 감지 후 `owners.last_ip`에 저장
14. **CSS 버전 관리:** `header.php`에 `const CSS_VERSION` 상수 정의. 모든 CSS/JS 링크에 `?v=<?= CSS_VERSION ?>` 파라미터 추가. 버전 관리 규칙:
    - **패치 버전 (마지막 자리)**: UI 작은 수정, 버그 픽스, 스타일 미세 조정 등 자주 올림 (2.0.4 → 2.0.5)
    - **마이너 버전 (중간 자리)**: 특정 페이지/기능의 대폭 리디자인, 새로운 컴포넌트 추가 등 큰 변화 (2.0.x → 2.1.0)
    - **메이저 버전 (첫 자리)**: 전체 디자인 시스템 개편, 구조적 변화 등 완전 전면 개편 (2.x.x → 3.0.0)

---

## 작업 순서 (권장)

1. ✅ `database/init.sql` 생성 (모든 테이블 CREATE 문 포함, USE withplan; 시작) → 사용자에게 phpMyAdmin 실행 안내
2. ✅ `.env` 예시 파일 생성
3. ✅ `config.php`, `helpers.php` 기초 세팅 (helpers/auth.php, helpers/trip.php, helpers/security.php 분리 완료)
4. ✅ 인증 시스템 (Google OAuth 세션, 멤버 PIN 설정/검증, 세션 관리)
5. ✅ 랜딩 페이지 (`/`) - Hero, 기능 소개, 계획 접속 폼, Footer
6. ✅ 여행 생성 페이지 (`new.php`)
7. ✅ 홈 대시보드
8. ✅ 하단 네비게이션 + 공통 레이아웃
9. ✅ 일정표
10. ✅ 예산 관리 (계획 + 지출 입력)
11. ✅ 체크리스트
12. ✅ To-Do
13. ✅ 정산
14. ✅ 멤버 관리
15. ✅ 공유 메모
16. ✅ 문의 및 제안 페이지
17. 전체 UI 다듬기

> **구현 현황 (2026-03-03 기준):** 1~16단계 완료. 추가 구현된 페이지: `privacy.php`(개인정보처리방침), `terms.php`(이용약관), `enter_user.php`(user_id 입력 화면). 커스텀 에러 페이지(403, 404, 500) 구현 완료.
> 체크리스트 검색(키워드) 및 필터링(상태/카테고리/담당자 드롭다운) 기능 추가.
> `/todo` 라우트 추가 및 체크리스트·할일 페이지 분리. 하단 네비 "체크" 버튼이 두 페이지 간 토글, 상단 탭으로도 전환 가능.
> 체크리스트·할일 담당자 복수 선택 기능 추가 (토글 버튼 UI, comma-separated 저장). 개인별 완료 체크 기능 추가: `checklist_completions`, `todo_completions` 테이블로 사용자별 완료 상태 독립 관리. 담당자별 완료 현황(✓/미완료) 뱃지 표시. CSS_VERSION 1.9.0.
> 공유 메모 목록 UI를 체크리스트 스타일 컴팩트 행으로 변경. 클릭 시 전체 내용 펼침/접기. 메모·할일 상세 내용의 URL 자동 링크 변환(`linkify` PHP/JS 함수, http 없는 도메인도 지원). 확장된 메모 블록 간 여백 문제 수정. CSS_VERSION 1.9.5.
> `.page-header` 플로팅 스타일 개선: `position: sticky` + `backdrop-filter: blur(16px)` 반투명 유리 효과, 하단 청록→코랄 그라디언트 하이라이트 테두리(`::after`). `overflow: hidden` → `overflow: clip` 변경으로 PC뷰에서도 sticky 정상 동작. CSS_VERSION 2.0.2.
> `/my` 페이지 모달: 체크리스트·할일과 동일한 디자인/모션 적용. `translateY(100%)` → `translateY(0)` 슬라이드업 애니메이션(0.25s), visible/hidden 클래스 기반, requestAnimationFrame 최적화. PC에서도 모바일과 동일하게 하단에서 올라옴 (중앙 정렬 제거). ESC 키 닫기 지원. CSS_VERSION 2.0.3.
> 뒤로가기 버튼: 네비게이션 바가 없는 모든 페이지에 뒤로가기 버튼 추가. `my.php`→`/`, `new.php`→`/my`, `contact.php`→`/`. `.back-link` 스타일을 common.css로 통합 (frosted glass 헤더에 맞는 다크 텍스트). `home.php` 더보기 메뉴에서 공유 메모 항목 제거 (하단 네비에 이미 있음). CSS_VERSION 2.0.4.
> 헤더 LiquidGlass 리디자인: `--header-height: 50px`, `blur(20px) saturate(180%)`, `inset 0 1px 0 rgba(255,255,255,0.6)` 상단 빛 반사 효과. 모든 멤버 페이지에 `more_vert` 드롭다운 메뉴(설정 링크) 추가. `page-header-row`/`page-header-left`/`header-more-wrap`/`header-dropdown` 구조. `common.js`에 `toggleHeaderMenu()`, `_showModal()`/`_hideModal()` 공용 함수 이동.
> 설정 페이지(`settings.php`): 여행 정보(오너 수정 가능), 멤버 관리(오너: 추가/삭제/URL복사), PIN 변경, 내 정보. 멤버 세션 + `is_owner` 기반 권한 확인. API: `api/pin_change.php`, `api/trips/update.php`, `api/members/manage.php`.
> 정산→예산 통합: `budget.php` 3탭 구조(예산 계획|지출 내역|정산). Settlement JS를 `budget.js`에 통합, lazy loading. settlement.css를 budget.css에 병합. `settlement.php`는 `budget#settlement`로 리다이렉트.
> 예산 FAB + 수입 기능: 지출 내역 탭에 FAB 2개(수입: 초록, 지출: 코랄). `incomes` 테이블 신규. `api/budget/incomes.php` CRUD. sheet 모달로 모든 모달 교체. 수입/지출 합쳐서 날짜순 표시. `database/init.sql`에 incomes 테이블 추가.
> 홈 "더보기" 카드 제거(정산→예산탭, 멤버→설정에서 접근). `checklist.js`/`todo.js`에서 중복 `_showModal`/`_hideModal` 제거. CSS_VERSION 2.1.0.
> 예산 축소 + 일정 리디자인 + 네비 변경: `budget_categories` 테이블 삭제, 예산 계획 탭 제거(지출 내역+정산 2탭만 유지), "예산"→"지출" 라벨 변경. 하단 네비 순서: 홈/일정/지출/체크/메모. `page_header.php` partial 추출(멤버 페이지 7개 공통 헤더). 일정 페이지 전면 리디자인: 날짜 스크롤바, 타임라인 뷰, Google Maps 외부 연결, FAB, sheet 모달, 카테고리 시스템(meal/transport/accommodation/sightseeing/shopping/other), 스와이프 일차 전환. `schedule_items`에 `end_time`, `is_all_day`, `memo`, `google_maps_url`, `category` 컬럼 추가. CSS_VERSION 3.0.0.
> 지출 탭 UI 통일: 지출·체크·할일 페이지 탭 스타일 동일하게 통합(`page-tabs`/`page-tab-btn` 공유 CSS in common.css). 지출 내역 ↔ 정산 탭 전환. 하단 네비 "지출" 버튼: 페이지 내 탭 토글(redirect 대신 JS toggle). `footer.php`에서 budget 페이지일 때만 `navBudget` id 설정, `budget.js`에서 intercept해 `switchTab()` 호출. CSS_VERSION 3.0.1.
> 삭제 확인 모달: 모든 삭제 버튼(지출/수입/체크/할일/일정/메모/멤버/여행)에서 브라우저 기본 `confirm()` 대신 추가/수정 모달과 동일한 sheet 모달 사용. `WP.confirm()` Promise 기반 개선, ESC/오버레이 클릭 닫기 지원. 메시지 개행(\n) CSS `white-space: pre-wrap` 지원. CSS_VERSION 3.0.6.

---

## 보안 처리

- **CSRF 토큰:** 모든 POST 폼에 CSRF 토큰 삽입. 세션에 저장된 토큰과 비교 검증
- **`vendor/` 접근 차단:** `.htaccess`에서 `vendor/` 디렉토리 외부 접근 차단
  ```apache
  RewriteRule ^vendor/ - [F,L]
  ```
- **`/new` 스팸 방지:** Google OAuth 로그인 필수이므로 무분별한 생성 원천 차단
- **멤버 URL 보안:** trip_code(8자리) + user_id 조합은 브루트포스 실질적으로 불가. 단, URL 노출 주의 안내 문구 표시
- **PIN 브루트포스 방어:**
  - IP + trip_code + user_id 조합으로 `pin_attempts` 테이블에 실패 횟수 기록
  - **3회 실패 시 5분간 잠금** (`locked_until = NOW() + 5분`)
  - 잠금 중 접근 시 남은 잠금 시간 표시 (예: "3분 후 다시 시도해주세요")
  - PIN 입력 성공 시 해당 IP의 시도 횟수 초기화
  - 잠금 확인은 PIN 검증 전에 먼저 수행
- **XSS 방지:** 출력 시 `htmlspecialchars()` 필수
- **SQL Injection 방지:** PDO prepared statements 사용
- **보안 헤더 (.htaccess):** 아래 헤더를 `public/.htaccess`에 추가
  ```apache
  Header always set X-Frame-Options "DENY"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
  ```
- **커스텀 에러 페이지:** `app/views/errors/` 에 404.php, 403.php, 500.php 구성. `APP_ENV=development`일 때는 스택 트레이스 표시, `production`에서는 사용자 친화적 메시지만 표시. `public/index.php`에서 예외 catch 후 해당 뷰 렌더링

---

## Claude Code 개발 원칙

- **페이지 새로고침 최소화 (AJAX 우선):** 모든 데이터 조작(지출 추가, 체크리스트 토글, To-Do 완료 등)은 `fetch()` API 기반으로 구현. DOM만 부분 업데이트. 폼 submit으로 페이지 리로드하는 방식 금지
- **에러 메시지 한국어화:** fetch 실패 시 `"Failed to fetch"` 등 기술적 메시지를 사용자에게 그대로 노출 금지. `"네트워크 연결을 확인해주세요."` 등 한국어 안내 메시지로 변환
- **환경 분기:** `APP_ENV` 값으로 개발/운영 동작 분기. 운영환경에서는 절대 스택 트레이스 노출 금지
- **CSS 수정 시:** 반드시 `header.php`의 `CSS_VERSION` 상수를 버전 관리 규칙에 따라 increment (패치: 자주, 마이너: 큰 변화, 메이저: 전면 개편). 브라우저 캐시 강제 갱신
- **기능별 파일 분리 (유지보수 우선):** 기능 단위로 파일을 최대한 잘게 쪼갤 것. 하나의 파일이 하나의 역할만 담당하도록 설계. 구체적인 원칙은 아래와 같음

  - **API:** 기능별로 파일 분리. 예: `api/budget.php` 하나에 모든 예산 로직을 넣지 말고 `api/budget/plan.php` / `api/budget/expense.php` / `api/budget/settlement.php` 로 분리
  - **Pages:** 각 페이지는 독립 파일. 페이지 내 섹션이 복잡해지면 `includes/` 하위 partials로 추가 분리. 예: `pages/budget/plan.php`, `pages/budget/expense.php`
  - **JS:** 페이지별 JS 파일 분리 (`assets/js/pages/budget.js`, `assets/js/pages/checklist.js` 등). 공통 유틸 함수는 `assets/js/utils.js`로 별도 분리
  - **CSS:** 페이지별 CSS 파일 분리 (`assets/css/pages/budget.css`, `assets/css/pages/checklist.css` 등). 공통 스타일은 `assets/css/common.css`
  - **Helpers:** 기능별 헬퍼 함수는 `app/helpers/` 하위에 분리. 예: `helpers/auth.php`, `helpers/trip.php`, `helpers/settlement.php`
  - **함수/클래스 크기 기준:** 단일 함수가 50줄 초과 시 분리 검토. 한 파일이 200줄 초과 시 분리 필수
  - **새 기능 추가 시:** 기존 파일에 무작정 append하지 말고 신규 파일 생성 후 `require_once`로 연결

---

## 주의 사항

- DB 직접 조작 절대 금지. SQL 문은 항상 사용자에게 출력하여 직접 실행 요청
- 민감 정보(DB 접속, 세션 시크릿)는 반드시 `.env`에서 로드
- PHP 파일에 DB 접속 정보 하드코딩 금지
- Synology WebStation 환경에서 mod_rewrite는 활성화되어 있다고 가정하고 진행. `.htaccess`로 모든 요청을 `index.php`로 라우팅
- 웹 서버: Apache 2.4. Nginx는 WebStation에서 nginx.conf 직접 편집 불가로 `.htaccess` 기반 라우팅 사용 불가하여 Apache 채택
# WithPlan

여행 멤버들과 함께 일정, 지출, 체크리스트, 메모를 공유하는 여행 플래너 웹 앱입니다.

## 주요 기능

- **일정 관리** - 날짜별 일정 타임라인, Google Maps 연동, 카테고리 분류
- **지출/정산** - 다중 통화 지원, 환율 관리(카드/현금), 더치페이 정산
- **체크리스트** - 준비물·할일 목록, 멤버별 개인 완료 체크
- **공유 메모** - 여행 멤버 전체 공유 노트
- **홈 대시보드** - 여행 전/중/후 상태별 맞춤 뷰

## 기술 스택

- **서버:** PHP 8.4 + Apache 2.4 + MariaDB (Synology NAS)
- **인증:** Google OAuth 2.0 (오너) / PIN 6자리 bcrypt (멤버)
- **주요 라이브러리:** `league/oauth2-google`, GuzzleHTTP, PHPMailer

## 프로젝트 구조

```
withPlan/
├── public/              # Document Root (웹 공개 영역)
│   ├── index.php        # 프론트 컨트롤러
│   └── assets/
│       ├── css/pages/   # 페이지별 CSS
│       └── js/pages/    # 페이지별 JS
├── app/
│   ├── pages/           # 페이지 렌더링 PHP
│   ├── api/             # REST API 엔드포인트
│   ├── includes/        # 공통 헤더/푸터/인증
│   └── helpers/         # 유틸리티 함수
├── config/              # 설정 파일 (웹 비공개)
├── database/
│   └── init.sql         # DB 스키마
└── .env                 # 환경변수 (웹 비공개)
```

## URL 구조

```
/                          랜딩 페이지 (공개)
/my                        오너 대시보드 (Google OAuth 필요)
/new                       여행 생성 (Google OAuth 필요)
/{trip_code}/              멤버 ID 입력
/{trip_code}/{user_id}/    홈 대시보드 (PIN 인증 필요)
  /schedule                일정
  /budget                  지출/예산
  /checklist               체크리스트
  /notes                   메모
  /settings                설정
```

## 설치 및 배포

### 요구 사항

- PHP 8.4+
- Apache 2.4 (mod_rewrite 활성화)
- MariaDB 10.x+
- Composer

### 설치

```bash
# 의존성 설치
composer install

# 환경변수 설정
cp .env.example .env
# .env 파일에 DB 접속 정보, Google OAuth 키 등 입력

# DB 초기화 (phpMyAdmin에서 실행)
database/init.sql
```

### Apache VirtualHost 설정

Document Root를 반드시 `public/` 디렉토리로 지정해야 합니다.

```apache
DocumentRoot /path/to/withPlan/public
```

## 인증 흐름

### 오너 (여행 생성자)
1. `/my` 접속 → Google OAuth 로그인
2. `owners` 테이블 upsert → 대시보드 진입

### 멤버
1. `/{trip_code}/` 접속 → user_id 입력
2. PIN 6자리 입력 (최초 접속 시 PIN 설정)
3. 3회 실패 시 5분 잠금

## 개발 가이드

- CSS/JS 수정 시 `app/includes/header.php`의 `CSS_VERSION` 상수 increment 필수
- API 응답 형식: `{"success": bool, "data": ..., "message": "..."}`
- 모든 POST 요청에 CSRF 토큰 포함
- 페이지 리로드 없이 `fetch()` + DOM 업데이트 방식 사용
- DB 스키마 변경은 `database/init.sql` 수정 후 phpMyAdmin에서 적용

## 라이선스

개인 프로젝트 - 무단 배포 금지

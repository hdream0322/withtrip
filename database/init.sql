USE withplan;

-- 1. owners - Google OAuth 오너 계정
CREATE TABLE IF NOT EXISTS owners (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  google_id     VARCHAR(100) NOT NULL UNIQUE,
  email         VARCHAR(200) NOT NULL,
  display_name  VARCHAR(100),
  last_ip       VARCHAR(45) DEFAULT NULL COMMENT 'Cloudflare CF-Connecting-IP 우선, 없으면 REMOTE_ADDR',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. trips - 여행 기본 정보
CREATE TABLE IF NOT EXISTS trips (
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

-- 3. users - 여행 참여자
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  display_name VARCHAR(50) NOT NULL,
  pin_hash    VARCHAR(255) DEFAULT NULL COMMENT '최초 접근 시 설정, 설정 전 NULL',
  is_owner    TINYINT(1) DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_trip_user (trip_code, user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4. schedule_days - 일자별 일정
CREATE TABLE IF NOT EXISTS schedule_days (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  day_number  INT NOT NULL,
  date        DATE,
  title       VARCHAR(100),
  note        TEXT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. schedule_items - 일정 내 세부 항목
CREATE TABLE IF NOT EXISTS schedule_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  day_id          INT NOT NULL,
  trip_code       VARCHAR(8) NOT NULL,
  time            VARCHAR(10),
  end_time        VARCHAR(10) DEFAULT NULL,
  is_all_day      TINYINT(1) DEFAULT 0,
  content         VARCHAR(200) NOT NULL COMMENT '일정 제목',
  location        VARCHAR(100),
  memo            TEXT DEFAULT NULL,
  google_maps_url VARCHAR(500) DEFAULT NULL,
  category        ENUM('meal','transport','accommodation','sightseeing','shopping','other') DEFAULT NULL,
  sort_order      INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 6. expenses - 실지출 내역
CREATE TABLE IF NOT EXISTS expenses (
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

-- 8. dutch_splits - 더치페이 분담 내역
CREATE TABLE IF NOT EXISTS dutch_splits (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  expense_id  INT NOT NULL,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  amount      DECIMAL(12,0) NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 9. checklists - 준비물 체크리스트
CREATE TABLE IF NOT EXISTS checklists (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  category    VARCHAR(50),
  item        VARCHAR(100) NOT NULL,
  assigned_to VARCHAR(30),
  is_done     TINYINT(1) DEFAULT 0,
  sort_order  INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 10. todos - To-Do
CREATE TABLE IF NOT EXISTS todos (
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

-- 11. shared_notes - 공유 메모/자료
CREATE TABLE IF NOT EXISTS shared_notes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  author_id   VARCHAR(30) NOT NULL,
  title       VARCHAR(100),
  content     TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 12. contact_submissions - 문의/제안 접수 기록
CREATE TABLE IF NOT EXISTS contact_submissions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(200) NOT NULL,
  category    ENUM('general', 'bug', 'feature') NOT NULL,
  content     TEXT NOT NULL,
  ip          VARCHAR(45),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 13. pin_attempts - PIN 브루트포스 방어
CREATE TABLE IF NOT EXISTS pin_attempts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45) NOT NULL,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  attempts    INT DEFAULT 1,
  locked_until DATETIME DEFAULT NULL COMMENT '잠금 해제 시각, NULL이면 잠금 없음',
  last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ip_user (ip, trip_code, user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 14. checklist_completions - 체크리스트 개인별 완료 기록
-- assigned_to 다중 담당자 지원: checklists.assigned_to VARCHAR 200으로 확장 필요
-- ALTER TABLE checklists MODIFY assigned_to VARCHAR(200) DEFAULT NULL;
CREATE TABLE IF NOT EXISTS checklist_completions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  checklist_id INT NOT NULL,
  trip_code    VARCHAR(8) NOT NULL,
  user_id      VARCHAR(30) NOT NULL,
  completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cl_completion (checklist_id, user_id),
  INDEX idx_trip_code (trip_code)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 15. todo_completions - 할일 개인별 완료 기록
-- assigned_to 다중 담당자 지원: todos.assigned_to VARCHAR 200으로 확장 필요
-- ALTER TABLE todos MODIFY assigned_to VARCHAR(200) DEFAULT NULL;
CREATE TABLE IF NOT EXISTS todo_completions (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  todo_id   INT NOT NULL,
  trip_code VARCHAR(8) NOT NULL,
  user_id   VARCHAR(30) NOT NULL,
  completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_todo_completion (todo_id, user_id),
  INDEX idx_trip_code (trip_code)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 16. incomes - 수입 내역
CREATE TABLE IF NOT EXISTS incomes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_code   VARCHAR(8) NOT NULL,
  user_id     VARCHAR(30) NOT NULL,
  amount      DECIMAL(12,0) NOT NULL,
  currency    VARCHAR(3) DEFAULT 'KRW',
  type        ENUM('budget','refund','other') NOT NULL DEFAULT 'other',
  description VARCHAR(200),
  income_date DATE,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_trip_code (trip_code)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

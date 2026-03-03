<?php
/**
 * WithPlan 프론트 컨트롤러
 * 모든 요청이 여기로 라우팅됨
 */

// 에러 표시 설정
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Composer autoload + .env 로드
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 개발환경에서만 에러 표시
if ($_ENV['APP_ENV'] === 'development') {
    ini_set('display_errors', 1);
}

// 공통 파일 로드
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/includes/auth_check.php';

// HTTPS 감지 (Cloudflare 프록시 뒤에서도 정상 작동)
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"https"') !== false);

// 세션 쿠키 파라미터 설정 (session_start 전에 호출 필수)
session_set_cookie_params([
    'lifetime' => 86400, // 1일
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isSecure,
    'httponly'  => true,
    'samesite' => 'Lax',
]);
session_name('withplan_session');
session_start();

// 요청 URI 파싱
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/') ?: '/';

try {
    // --- 인증 라우트 ---
    if ($requestUri === '/auth/google') {
        handleGoogleLogin();
        exit;
    }

    if ($requestUri === '/auth/google/callback') {
        handleGoogleCallback();
        exit;
    }

    if ($requestUri === '/auth/logout') {
        handleLogout();
        exit;
    }

    // --- /my 오너 대시보드 ---
    if ($requestUri === '/my') {
        requireOwnerAuth();
        require_once __DIR__ . '/../app/pages/my.php';
        exit;
    }

    // --- /new 여행 생성 ---
    if ($requestUri === '/new') {
        requireOwnerAuth();
        require_once __DIR__ . '/../app/pages/new.php';
        exit;
    }

    // --- /contact 문의 및 제안 ---
    if ($requestUri === '/contact') {
        require_once __DIR__ . '/../app/pages/contact.php';
        exit;
    }

    // --- /terms 이용약관 ---
    if ($requestUri === '/terms') {
        require_once __DIR__ . '/../app/pages/terms.php';
        exit;
    }

    // --- /privacy 개인정보처리방침 ---
    if ($requestUri === '/privacy') {
        require_once __DIR__ . '/../app/pages/privacy.php';
        exit;
    }

    // --- API 라우트 ---
    if (preg_match('#^/api/(.+)$#', $requestUri, $m)) {
        $apiPath = $m[1];
        $apiFile = __DIR__ . '/../app/api/' . str_replace('..', '', $apiPath) . '.php';

        if (file_exists($apiFile)) {
            require_once $apiFile;
            exit;
        }

        jsonResponse(false, null, '존재하지 않는 API입니다.', 404);
    }

    // --- /{trip_code} 접근 (user_id 입력 폼) ---
    if (preg_match('#^/([a-f0-9]{8})$#', $requestUri, $m)) {
        $tripCode = $m[1];
        $db = getDB();
        $trip = getTripByCode($db, $tripCode);

        if (!$trip) {
            header('Location: /');
            exit;
        }

        require_once __DIR__ . '/../app/pages/enter_user.php';
        exit;
    }

    // --- /{trip_code}/{user_id}/... 멤버 페이지 ---
    if (preg_match('#^/([a-f0-9]{8})/([a-zA-Z0-9_-]+)(?:/([a-z_-]*))?$#', $requestUri, $m)) {
        $tripCode = $m[1];
        $userId   = $m[2];
        $page     = $m[3] ?? '';

        $db = getDB();

        // trip_code / user_id 유효성 검증
        $trip = getTripByCode($db, $tripCode);
        if (!$trip) {
            header('Location: /');
            exit;
        }

        $user = getTripUser($db, $tripCode, $userId);
        if (!$user) {
            header('Location: /');
            exit;
        }

        // PIN 인증 처리 (set_pin / enter_pin 액션 포함)
        $action = $_GET['action'] ?? '';

        if ($action === 'set_pin' || $action === 'enter_pin') {
            require_once __DIR__ . '/../app/pages/pin.php';
            exit;
        }

        // PIN 인증 확인
        requireMemberAuth($tripCode, $userId);

        // 페이지 라우팅
        $tripTitle = $trip['title'];
        $pageMap = [
            ''           => 'home',
            'schedule'   => 'schedule',
            'budget'     => 'budget',
            'checklist'  => 'checklist',
            'todo'       => 'todo',
            'settlement' => 'settlement',
            'members'    => 'members',
            'notes'      => 'notes',
        ];

        if (!isset($pageMap[$page])) {
            http_response_code(404);
            require_once __DIR__ . '/../app/views/errors/404.php';
            exit;
        }

        $currentPage = $pageMap[$page];
        $pageFile = __DIR__ . '/../app/pages/' . $currentPage . '.php';

        if (file_exists($pageFile)) {
            require_once $pageFile;
            exit;
        }

        http_response_code(404);
        require_once __DIR__ . '/../app/views/errors/404.php';
        exit;
    }

    // --- 루트 페이지 ---
    if ($requestUri === '/') {
        require_once __DIR__ . '/../app/pages/landing.php';
        exit;
    }

    // --- 404 ---
    http_response_code(404);
    require_once __DIR__ . '/../app/views/errors/404.php';

} catch (Throwable $e) {
    if ($_ENV['APP_ENV'] === 'development') {
        throw $e;
    }

    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    require_once __DIR__ . '/../app/views/errors/500.php';
}

<?php
/**
 * 푸시 구독 등록/해제 API
 * POST   /api/push/subscribe - 구독 등록
 * DELETE /api/push/subscribe - 구독 해제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

$categories = ['schedule', 'budget', 'checklist', 'todo', 'note', 'member'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode = trim($input['trip_code'] ?? '');
    $userId   = trim($input['user_id'] ?? '');
    $endpoint = trim($input['endpoint'] ?? '');
    $p256dh   = trim($input['p256dh'] ?? '');
    $auth     = trim($input['auth'] ?? '');

    if (empty($tripCode) || empty($userId) || empty($endpoint) || empty($p256dh) || empty($auth)) {
        jsonResponse(false, null, '필수 정보가 누락되었습니다.', 400);
    }

    if (!isMemberAuthenticated($tripCode, $userId)) {
        jsonResponse(false, null, '인증이 필요합니다.', 401);
    }

    // 구독 등록 (endpoint 중복 시 업데이트)
    $stmt = $db->prepare(
        'INSERT INTO push_subscriptions (trip_code, user_id, endpoint, p256dh, auth)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE trip_code = VALUES(trip_code), user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)'
    );
    $stmt->execute([$tripCode, $userId, $endpoint, $p256dh, $auth]);

    // 카테고리별 설정 초기화 (없는 것만 추가)
    $stmtInsert = $db->prepare(
        'INSERT IGNORE INTO push_settings (trip_code, user_id, category, enabled) VALUES (?, ?, ?, 1)'
    );
    foreach ($categories as $cat) {
        $stmtInsert->execute([$tripCode, $userId, $cat]);
    }

    jsonResponse(true, null, '알림이 활성화되었습니다.');
}

if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $endpoint = trim($_GET['endpoint'] ?? '');

    if (empty($endpoint)) {
        jsonResponse(false, null, '필수 정보가 누락되었습니다.', 400);
    }

    $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
    $stmt->execute([$endpoint]);

    jsonResponse(true, null, '알림이 비활성화되었습니다.');
}

jsonResponse(false, null, '지원하지 않는 요청입니다.', 405);

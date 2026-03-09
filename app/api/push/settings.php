<?php
/**
 * 푸시 알림 카테고리 설정 API
 * GET /api/push/settings?trip_code=xxx&user_id=xxx - 설정 조회
 * PUT /api/push/settings - 카테고리별 on/off 업데이트
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    $userId   = $_GET['user_id'] ?? '';

    if (empty($tripCode) || empty($userId)) {
        jsonResponse(false, null, '필수 파라미터가 누락되었습니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT category, enabled FROM push_settings WHERE trip_code = ? AND user_id = ?'
    );
    $stmt->execute([$tripCode, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['category']] = (int) $row['enabled'];
    }

    jsonResponse(true, ['settings' => $settings]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode = trim($input['trip_code'] ?? '');
    $userId   = trim($input['user_id'] ?? '');
    $category = trim($input['category'] ?? '');
    $enabled  = (int) ($input['enabled'] ?? 0);

    $validCategories = ['schedule', 'budget', 'checklist', 'todo', 'note', 'member'];

    if (empty($tripCode) || empty($userId) || !in_array($category, $validCategories, true)) {
        jsonResponse(false, null, '잘못된 요청입니다.', 400);
    }

    if (!isMemberAuthenticated($tripCode, $userId)) {
        jsonResponse(false, null, '인증이 필요합니다.', 401);
    }

    $stmt = $db->prepare(
        'UPDATE push_settings SET enabled = ? WHERE trip_code = ? AND user_id = ? AND category = ?'
    );
    $stmt->execute([$enabled, $tripCode, $userId, $category]);

    jsonResponse(true, null, '설정이 저장되었습니다.');
}

jsonResponse(false, null, '지원하지 않는 요청입니다.', 405);

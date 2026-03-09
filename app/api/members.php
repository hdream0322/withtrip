<?php
/**
 * 멤버 API
 * GET    /api/members?trip_code=xxx         - 멤버 목록
 * POST   /api/members                       - 멤버 추가
 * DELETE /api/members?trip_code=xxx&user_id=xxx - 멤버 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requireOwnerAuth();

    $tripCode = $_GET['trip_code'] ?? '';
    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $db = getDB();
    $googleId = getOwnerGoogleId();

    // 소유권 검증
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ? AND owner_google_id = ?');
    $stmt->execute([$tripCode, $googleId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    $members = getTripMembers($db, $tripCode);

    // pin_hash는 존재 여부만 전달
    $result = array_map(function ($m) {
        return [
            'user_id'      => $m['user_id'],
            'display_name' => $m['display_name'],
            'pin_hash'     => $m['pin_hash'] !== null,
            'is_owner'     => (bool) $m['is_owner'],
            'created_at'   => $m['created_at'],
        ];
    }, $members);

    jsonResponse(true, $result);
}

if ($method === 'POST') {
    requireOwnerAuth();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode    = trim($input['trip_code'] ?? '');
    $userId      = trim($input['user_id'] ?? '');
    $displayName = trim($input['display_name'] ?? '');

    if (empty($tripCode) || empty($userId) || empty($displayName)) {
        jsonResponse(false, null, '모든 필드를 입력해주세요.', 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $userId)) {
        jsonResponse(false, null, 'ID는 영문, 숫자, _, -만 사용할 수 있습니다.', 400);
    }

    $db = getDB();
    $googleId = getOwnerGoogleId();

    // 소유권 검증
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ? AND owner_google_id = ?');
    $stmt->execute([$tripCode, $googleId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    // 중복 확인
    $existing = getTripUser($db, $tripCode, $userId);
    if ($existing) {
        jsonResponse(false, null, '이미 존재하는 ID입니다.', 409);
    }

    $stmt = $db->prepare(
        'INSERT INTO users (trip_code, user_id, display_name) VALUES (?, ?, ?)'
    );
    $stmt->execute([$tripCode, $userId, $displayName]);

    jsonResponse(true, null, '멤버가 추가되었습니다.');
}

if ($method === 'DELETE') {
    requireOwnerAuth();

    $tripCode = $_GET['trip_code'] ?? '';
    $userId   = $_GET['user_id'] ?? '';

    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    if (empty($tripCode) || empty($userId)) {
        jsonResponse(false, null, '여행 코드와 사용자 ID가 필요합니다.', 400);
    }

    $db = getDB();
    $googleId = getOwnerGoogleId();

    // 소유권 검증
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ? AND owner_google_id = ?');
    $stmt->execute([$tripCode, $googleId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    // 오너는 삭제 불가
    $user = getTripUser($db, $tripCode, $userId);
    if (!$user) {
        jsonResponse(false, null, '존재하지 않는 멤버입니다.', 404);
    }

    if ($user['is_owner']) {
        jsonResponse(false, null, '오너는 삭제할 수 없습니다.', 400);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE trip_code = ? AND user_id = ?');
    $stmt->execute([$tripCode, $userId]);

    jsonResponse(true, null, '멤버가 삭제되었습니다.');
}

<?php
/**
 * 표시 이름 변경 API
 * PUT /api/members/update_profile
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    jsonResponse(false, null, '허용되지 않는 메서드입니다.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    jsonResponse(false, null, '잘못된 요청입니다.', 403);
}

$tripCode    = trim($input['trip_code'] ?? '');
$userId      = trim($input['user_id'] ?? '');
$displayName = trim($input['display_name'] ?? '');

if (empty($tripCode) || empty($userId) || empty($displayName)) {
    jsonResponse(false, null, '표시 이름을 입력해주세요.', 400);
}

if (mb_strlen($displayName) > 50) {
    jsonResponse(false, null, '표시 이름은 50자 이내로 입력해주세요.', 400);
}

// 본인 인증 확인
if (!isMemberAuthenticated($tripCode, $userId)) {
    jsonResponse(false, null, '인증이 필요합니다.', 401);
}

$db = getDB();

$user = getTripUser($db, $tripCode, $userId);
if (!$user) {
    jsonResponse(false, null, '사용자를 찾을 수 없습니다.', 404);
}

$stmt = $db->prepare('UPDATE users SET display_name = ? WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$displayName, $tripCode, $userId]);

jsonResponse(true, null, '표시 이름이 변경되었습니다.');

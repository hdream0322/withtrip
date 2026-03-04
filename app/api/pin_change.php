<?php
/**
 * PIN 변경 API
 * PUT /api/pin_change
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    jsonResponse(false, null, '허용되지 않는 메서드입니다.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    jsonResponse(false, null, '잘못된 요청입니다.', 403);
}

$tripCode   = trim($input['trip_code'] ?? '');
$userId     = trim($input['user_id'] ?? '');
$currentPin = $input['current_pin'] ?? '';
$newPin     = $input['new_pin'] ?? '';

if (empty($tripCode) || empty($userId) || empty($currentPin) || empty($newPin)) {
    jsonResponse(false, null, '모든 필드를 입력해주세요.', 400);
}

// 멤버 인증 확인
if (!isMemberAuthenticated($tripCode, $userId)) {
    jsonResponse(false, null, '인증이 필요합니다.', 401);
}

// 새 PIN 유효성 검사
if (!preg_match('/^\d{6}$/', $newPin)) {
    jsonResponse(false, null, '새 PIN은 6자리 숫자여야 합니다.', 400);
}

$db = getDB();

// 사용자 조회
$user = getTripUser($db, $tripCode, $userId);
if (!$user) {
    jsonResponse(false, null, '사용자를 찾을 수 없습니다.', 404);
}

// 현재 PIN 확인
if (!password_verify($currentPin, $user['pin_hash'])) {
    jsonResponse(false, null, '현재 PIN이 올바르지 않습니다.', 400);
}

// 새 PIN 저장
$newHash = password_hash($newPin, PASSWORD_BCRYPT);
$stmt = $db->prepare('UPDATE users SET pin_hash = ? WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$newHash, $tripCode, $userId]);

jsonResponse(true, null, 'PIN이 변경되었습니다.');

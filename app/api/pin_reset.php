<?php
/**
 * PIN 초기화 API (오너 전용)
 * PUT /api/pin_reset
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    jsonResponse(false, null, '허용되지 않는 메서드입니다.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    jsonResponse(false, null, '잘못된 요청입니다.', 403);
}

$tripCode        = trim($input['trip_code'] ?? '');
$requesterUserId = trim($input['requester_user_id'] ?? '');
$targetUserId    = trim($input['target_user_id'] ?? '');

if (empty($tripCode) || empty($requesterUserId) || empty($targetUserId)) {
    jsonResponse(false, null, '필수 파라미터가 누락되었습니다.', 400);
}

// 요청자 인증 확인
if (!isMemberAuthenticated($tripCode, $requesterUserId)) {
    jsonResponse(false, null, '인증이 필요합니다.', 401);
}

$db = getDB();

// 요청자가 오너인지 확인
$requester = getTripUser($db, $tripCode, $requesterUserId);
if (!$requester || !$requester['is_owner']) {
    jsonResponse(false, null, '권한이 없습니다.', 403);
}

// 대상 사용자 확인
$target = getTripUser($db, $tripCode, $targetUserId);
if (!$target) {
    jsonResponse(false, null, '존재하지 않는 멤버입니다.', 404);
}

// 오너 PIN은 초기화 불가
if ($target['is_owner']) {
    jsonResponse(false, null, '오너의 PIN은 초기화할 수 없습니다. PIN 변경을 이용해주세요.', 400);
}

// PIN 초기화 (NULL로 설정)
$stmt = $db->prepare('UPDATE users SET pin_hash = NULL WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$tripCode, $targetUserId]);

// 잠금 해제
$stmt = $db->prepare('DELETE FROM pin_attempts WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$tripCode, $targetUserId]);

jsonResponse(true, null, 'PIN이 초기화되었습니다.');

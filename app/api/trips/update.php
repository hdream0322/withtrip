<?php
/**
 * 여행 정보 수정 API
 * PUT /api/trips/update
 * 멤버 세션 + is_owner 체크로 권한 확인
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    jsonResponse(false, null, '허용되지 않는 메서드입니다.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    jsonResponse(false, null, '잘못된 요청입니다.', 403);
}

$tripCode = trim($input['trip_code'] ?? '');
$userId   = trim($input['user_id'] ?? '');
$title    = trim($input['title'] ?? '');

if (empty($tripCode) || empty($userId) || empty($title)) {
    jsonResponse(false, null, '필수 항목을 입력해주세요.', 400);
}

// 멤버 인증 확인
if (!isMemberAuthenticated($tripCode, $userId)) {
    jsonResponse(false, null, '인증이 필요합니다.', 401);
}

$db = getDB();

// 오너 여부 확인
$user = getTripUser($db, $tripCode, $userId);
if (!$user || !$user['is_owner']) {
    jsonResponse(false, null, '권한이 없습니다.', 403);
}

// 여행 정보 업데이트
$stmt = $db->prepare(
    'UPDATE trips SET title = ?, description = ?, destination = ?, start_date = ?, end_date = ? WHERE trip_code = ?'
);
$stmt->execute([
    $title,
    trim($input['description'] ?? '') ?: null,
    trim($input['destination'] ?? '') ?: null,
    $input['start_date'] ?: null,
    $input['end_date'] ?: null,
    $tripCode,
]);

jsonResponse(true, null, '여행 정보가 수정되었습니다.');

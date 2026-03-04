<?php
/**
 * 멤버 관리 API (멤버 세션 + is_owner 기반)
 * POST   /api/members/manage — 멤버 추가
 * DELETE /api/members/manage — 멤버 삭제
 *
 * Google OAuth 대신 멤버 세션의 is_owner 플래그로 권한 확인
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode        = trim($input['trip_code'] ?? '');
    $newUserId       = trim($input['user_id'] ?? '');
    $displayName     = trim($input['display_name'] ?? '');
    $requesterUserId = trim($input['requester_user_id'] ?? '');

    if (empty($tripCode) || empty($newUserId) || empty($displayName) || empty($requesterUserId)) {
        jsonResponse(false, null, '모든 필드를 입력해주세요.', 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $newUserId)) {
        jsonResponse(false, null, 'ID는 영문, 숫자, _, -만 사용할 수 있습니다.', 400);
    }

    // 멤버 인증 확인
    if (!isMemberAuthenticated($tripCode, $requesterUserId)) {
        jsonResponse(false, null, '인증이 필요합니다.', 401);
    }

    $db = getDB();

    // 요청자가 오너인지 확인
    $requester = getTripUser($db, $tripCode, $requesterUserId);
    if (!$requester || !$requester['is_owner']) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    // 중복 확인
    $existing = getTripUser($db, $tripCode, $newUserId);
    if ($existing) {
        jsonResponse(false, null, '이미 존재하는 ID입니다.', 409);
    }

    $stmt = $db->prepare('INSERT INTO users (trip_code, user_id, display_name) VALUES (?, ?, ?)');
    $stmt->execute([$tripCode, $newUserId, $displayName]);

    jsonResponse(true, null, '멤버가 추가되었습니다.');
}

if ($method === 'DELETE') {
    $tripCode        = $_GET['trip_code'] ?? '';
    $targetUserId    = $_GET['user_id'] ?? '';
    $requesterUserId = $_GET['requester_user_id'] ?? '';

    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    if (empty($tripCode) || empty($targetUserId) || empty($requesterUserId)) {
        jsonResponse(false, null, '필수 파라미터가 누락되었습니다.', 400);
    }

    // 멤버 인증 확인
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

    if ($target['is_owner']) {
        jsonResponse(false, null, '오너는 삭제할 수 없습니다.', 400);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE trip_code = ? AND user_id = ?');
    $stmt->execute([$tripCode, $targetUserId]);

    jsonResponse(true, null, '멤버가 삭제되었습니다.');
}

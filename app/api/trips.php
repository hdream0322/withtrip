<?php
/**
 * 여행 API
 * POST /api/trips - 새 여행 생성
 * PUT /api/trips - 여행 수정
 * DELETE /api/trips - 여행 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    requireOwnerAuth();

    $input = json_decode(file_get_contents('php://input'), true);

    // CSRF 검증
    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $title           = trim($input['title'] ?? '');
    $description     = trim($input['description'] ?? '');
    $destination     = trim($input['destination'] ?? '');
    $startDate       = $input['start_date'] ?? null;
    $endDate         = $input['end_date'] ?? null;
    $ownerUserId     = trim($input['owner_user_id'] ?? '');
    $ownerDisplayName = trim($input['owner_display_name'] ?? '');

    // 유효성 검증
    if (empty($title)) {
        jsonResponse(false, null, '여행 제목을 입력해주세요.', 400);
    }

    if (empty($ownerUserId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $ownerUserId)) {
        jsonResponse(false, null, '유효한 참여자 ID를 입력해주세요.', 400);
    }

    if (empty($ownerDisplayName)) {
        jsonResponse(false, null, '표시 이름을 입력해주세요.', 400);
    }

    $db = getDB();
    $googleId = getOwnerGoogleId();

    // trip_code 생성
    $tripCode = generateTripCode($db);

    // 빈 날짜는 NULL로
    $startDate = !empty($startDate) ? $startDate : null;
    $endDate   = !empty($endDate) ? $endDate : null;

    $db->beginTransaction();

    try {
        // trips 테이블에 저장
        $stmt = $db->prepare(
            'INSERT INTO trips (trip_code, owner_google_id, title, description, destination, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tripCode, $googleId, $title, $description ?: null, $destination ?: null, $startDate, $endDate]);

        // 오너를 users 테이블에 추가
        $stmt = $db->prepare(
            'INSERT INTO users (trip_code, user_id, display_name, is_owner)
             VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([$tripCode, $ownerUserId, $ownerDisplayName]);

        $db->commit();

        jsonResponse(true, [
            'trip_code' => $tripCode,
            'user_id'   => $ownerUserId,
        ], '여행이 생성되었습니다.');

    } catch (Exception $e) {
        $db->rollBack();

        if ($_ENV['APP_ENV'] === 'development') {
            throw $e;
        }

        jsonResponse(false, null, '여행 생성에 실패했습니다.', 500);
    }
}

if ($method === 'PUT') {
    requireOwnerAuth();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode    = $input['trip_code'] ?? '';
    $title       = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $destination = trim($input['destination'] ?? '');
    $startDate   = $input['start_date'] ?? null;
    $endDate     = $input['end_date'] ?? null;

    if (empty($title)) {
        jsonResponse(false, null, '여행 제목을 입력해주세요.', 400);
    }

    $db = getDB();
    $googleId = getOwnerGoogleId();

    // 소유권 검증
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ? AND owner_google_id = ?');
    $stmt->execute([$tripCode, $googleId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    $startDate = !empty($startDate) ? $startDate : null;
    $endDate   = !empty($endDate) ? $endDate : null;

    $stmt = $db->prepare(
        'UPDATE trips SET title = ?, description = ?, destination = ?, start_date = ?, end_date = ?
         WHERE trip_code = ?'
    );
    $stmt->execute([$title, $description ?: null, $destination ?: null, $startDate, $endDate, $tripCode]);

    jsonResponse(true, null, '여행 정보가 수정되었습니다.');
}

if ($method === 'DELETE') {
    requireOwnerAuth();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode = $input['trip_code'] ?? '';
    $db = getDB();
    $googleId = getOwnerGoogleId();

    // 소유권 검증
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ? AND owner_google_id = ?');
    $stmt->execute([$tripCode, $googleId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, null, '권한이 없습니다.', 403);
    }

    $db->beginTransaction();

    try {
        // 관련 데이터 삭제 (외래키 없으므로 수동 삭제)
        $tables = ['dutch_splits', 'expenses', 'incomes', 'schedule_items', 'schedule_days',
                    'checklists', 'checklist_completions', 'todos', 'todo_completions', 'shared_notes', 'pin_attempts', 'users'];

        foreach ($tables as $table) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE trip_code = ?");
            $stmt->execute([$tripCode]);
        }

        $stmt = $db->prepare('DELETE FROM trips WHERE trip_code = ?');
        $stmt->execute([$tripCode]);

        $db->commit();
        jsonResponse(true, null, '여행이 삭제되었습니다.');

    } catch (Exception $e) {
        $db->rollBack();

        if ($_ENV['APP_ENV'] === 'development') {
            throw $e;
        }

        jsonResponse(false, null, '여행 삭제에 실패했습니다.', 500);
    }
}

<?php
/**
 * 일정 항목 API
 * GET    /api/schedule/items - 항목 조회
 * POST   /api/schedule/items - 항목 추가
 * PUT    /api/schedule/items - 항목 수정
 * DELETE /api/schedule/items - 항목 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    $dayId    = $_GET['day_id'] ?? null;

    if ($dayId) {
        $stmt = $db->prepare(
            'SELECT * FROM schedule_items WHERE day_id = ? AND trip_code = ? ORDER BY is_all_day DESC, time ASC, sort_order ASC'
        );
        $stmt->execute([$dayId, $tripCode]);
    } else {
        $stmt = $db->prepare(
            'SELECT * FROM schedule_items WHERE trip_code = ? ORDER BY sort_order ASC, time ASC'
        );
        $stmt->execute([$tripCode]);
    }

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, ['items' => $items]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $dayId      = (int) ($input['day_id'] ?? 0);
    $tripCode   = $input['trip_code'] ?? '';
    $time       = trim($input['time'] ?? '');
    $endTime    = trim($input['end_time'] ?? '');
    $isAllDay   = (int) ($input['is_all_day'] ?? 0);
    $content    = trim($input['content'] ?? '');
    $location   = trim($input['location'] ?? '');
    $memo       = trim($input['memo'] ?? '');
    $mapsUrl    = trim($input['google_maps_url'] ?? '');
    $category   = $input['category'] ?? null;

    if (empty($content)) {
        jsonResponse(false, null, '제목을 입력해주세요.', 400);
    }

    // sort_order 계산
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM schedule_items WHERE day_id = ?');
    $stmt->execute([$dayId]);
    $sortOrder = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO schedule_items (day_id, trip_code, time, end_time, is_all_day, content, location, memo, google_maps_url, category, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $dayId, $tripCode,
        $time ?: null, $endTime ?: null, $isAllDay,
        $content, $location ?: null,
        $memo ?: null, $mapsUrl ?: null, $category ?: null,
        $sortOrder
    ]);

    try {
        $reqUserId = $input['user_id'] ?? '';
        $reqUser = $reqUserId ? getTripUser($db, $tripCode, $reqUserId) : null;
        $reqName = $reqUser ? $reqUser['display_name'] : '';
        queuePushNotification($db, $tripCode, null, $reqUserId, '새 일정',
            ($reqName ? $reqName . '님이 ' : '') . '\'' . $content . '\' 일정 추가',
            '/' . $tripCode . '/{USER_ID}/schedule', 'schedule');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true, ['id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id         = (int) ($input['id'] ?? 0);
    $tripCode   = $input['trip_code'] ?? '';
    $dayId      = isset($input['day_id']) ? (int) $input['day_id'] : null;
    $time       = trim($input['time'] ?? '');
    $endTime    = trim($input['end_time'] ?? '');
    $isAllDay   = (int) ($input['is_all_day'] ?? 0);
    $content    = trim($input['content'] ?? '');
    $location   = trim($input['location'] ?? '');
    $memo       = trim($input['memo'] ?? '');
    $mapsUrl    = trim($input['google_maps_url'] ?? '');
    $category   = $input['category'] ?? null;

    if (empty($content)) {
        jsonResponse(false, null, '제목을 입력해주세요.', 400);
    }

    $sql = 'UPDATE schedule_items SET time = ?, end_time = ?, is_all_day = ?, content = ?, location = ?, memo = ?, google_maps_url = ?, category = ?';
    $params = [$time ?: null, $endTime ?: null, $isAllDay, $content, $location ?: null, $memo ?: null, $mapsUrl ?: null, $category ?: null];

    if ($dayId !== null) {
        $sql .= ', day_id = ?';
        $params[] = $dayId;
    }

    $sql .= ' WHERE id = ? AND trip_code = ?';
    $params[] = $id;
    $params[] = $tripCode;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    try {
        $reqUserId = $input['user_id'] ?? '';
        $reqUser = $reqUserId ? getTripUser($db, $tripCode, $reqUserId) : null;
        $reqName = $reqUser ? $reqUser['display_name'] : '';
        queuePushNotification($db, $tripCode, null, $reqUserId, '일정 수정',
            ($reqName ? $reqName . '님이 ' : '') . '일정을 수정했습니다',
            '/' . $tripCode . '/{USER_ID}/schedule', 'schedule');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true);
}

if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    $stmt = $db->prepare('DELETE FROM schedule_items WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    try {
        $reqUserId = $_GET['user_id'] ?? '';
        queuePushNotification($db, $tripCode, null, $reqUserId, '일정 삭제',
            '일정이 삭제되었습니다',
            '/' . $tripCode . '/{USER_ID}/schedule', 'schedule');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true);
}

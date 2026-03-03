<?php
/**
 * 일정 항목 API
 * POST   /api/schedule/items - 항목 추가
 * PUT    /api/schedule/items - 항목 수정
 * DELETE /api/schedule/items - 항목 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $dayId    = (int) ($input['day_id'] ?? 0);
    $tripCode = $input['trip_code'] ?? '';
    $time     = trim($input['time'] ?? '');
    $content  = trim($input['content'] ?? '');
    $location = trim($input['location'] ?? '');

    if (empty($content)) {
        jsonResponse(false, null, '내용을 입력해주세요.', 400);
    }

    // sort_order 계산
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM schedule_items WHERE day_id = ?');
    $stmt->execute([$dayId]);
    $sortOrder = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO schedule_items (day_id, trip_code, time, content, location, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$dayId, $tripCode, $time ?: null, $content, $location ?: null, $sortOrder]);

    jsonResponse(true, ['id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($input['id'] ?? 0);
    $tripCode = $input['trip_code'] ?? '';
    $time     = trim($input['time'] ?? '');
    $content  = trim($input['content'] ?? '');
    $location = trim($input['location'] ?? '');

    if (empty($content)) {
        jsonResponse(false, null, '내용을 입력해주세요.', 400);
    }

    $stmt = $db->prepare(
        'UPDATE schedule_items SET time = ?, content = ?, location = ?
         WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([$time ?: null, $content, $location ?: null, $id, $tripCode]);

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

    jsonResponse(true);
}

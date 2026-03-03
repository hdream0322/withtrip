<?php
/**
 * 일정 Day API
 * POST   /api/schedule/days - Day 추가
 * PUT    /api/schedule/days - Day 수정
 * DELETE /api/schedule/days - Day 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode = $input['trip_code'] ?? '';
    $title    = trim($input['title'] ?? '');
    $date     = $input['date'] ?? null;

    // 다음 day_number 계산
    $stmt = $db->prepare('SELECT COALESCE(MAX(day_number), 0) + 1 FROM schedule_days WHERE trip_code = ?');
    $stmt->execute([$tripCode]);
    $nextDayNumber = (int) $stmt->fetchColumn();

    $date = !empty($date) ? $date : null;

    $stmt = $db->prepare(
        'INSERT INTO schedule_days (trip_code, day_number, date, title) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$tripCode, $nextDayNumber, $date, $title ?: null]);

    jsonResponse(true, ['id' => $db->lastInsertId(), 'day_number' => $nextDayNumber]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($input['id'] ?? 0);
    $tripCode = $input['trip_code'] ?? '';
    $title    = trim($input['title'] ?? '');
    $note     = trim($input['note'] ?? '');

    $stmt = $db->prepare(
        'UPDATE schedule_days SET title = ?, note = ? WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([$title ?: null, $note ?: null, $id, $tripCode]);

    jsonResponse(true);
}

if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    // 하위 아이템도 삭제
    $stmt = $db->prepare('DELETE FROM schedule_items WHERE day_id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    $stmt = $db->prepare('DELETE FROM schedule_days WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    jsonResponse(true);
}

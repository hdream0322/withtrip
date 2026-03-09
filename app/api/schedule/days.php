<?php
/**
 * 일정 Day API
 * GET    /api/schedule/days - Day 목록 조회
 * POST   /api/schedule/days - Day 추가
 * PUT    /api/schedule/days - Day 수정
 * DELETE /api/schedule/days - Day 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';

    // 여행 기간이 설정되어 있으면 날짜 범위에 맞게 schedule_days 자동 생성
    $tripStmt = $db->prepare('SELECT start_date, end_date FROM trips WHERE trip_code = ?');
    $tripStmt->execute([$tripCode]);
    $tripData = $tripStmt->fetch(PDO::FETCH_ASSOC);

    if ($tripData && $tripData['start_date'] && $tripData['end_date']) {
        $start = new DateTime($tripData['start_date']);
        $end   = new DateTime($tripData['end_date']);

        // 기존 날짜 목록
        $existStmt = $db->prepare('SELECT date FROM schedule_days WHERE trip_code = ? AND date IS NOT NULL');
        $existStmt->execute([$tripCode]);
        $existingDates = array_column($existStmt->fetchAll(PDO::FETCH_ASSOC), 'date');

        $current = clone $start;
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            if (!in_array($dateStr, $existingDates)) {
                $diff = (int)$start->diff($current)->days + 1;
                // day_number가 이미 있으면 date만 업데이트, 없으면 INSERT
                $checkStmt = $db->prepare('SELECT id FROM schedule_days WHERE trip_code = ? AND day_number = ?');
                $checkStmt->execute([$tripCode, $diff]);
                if ($checkStmt->fetch()) {
                    $db->prepare('UPDATE schedule_days SET date = ? WHERE trip_code = ? AND day_number = ? AND date IS NULL')
                       ->execute([$dateStr, $tripCode, $diff]);
                } else {
                    $db->prepare('INSERT INTO schedule_days (trip_code, day_number, date) VALUES (?, ?, ?)')
                       ->execute([$tripCode, $diff, $dateStr]);
                }
            }
            $current->modify('+1 day');
        }
    }

    $stmt = $db->prepare('SELECT * FROM schedule_days WHERE trip_code = ? ORDER BY day_number ASC');
    $stmt->execute([$tripCode]);
    $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, ['days' => $days]);
}

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

<?php
/**
 * To-Do API
 * GET    /api/todo?trip_code=xxx       - 목록 조회
 * POST   /api/todo                     - 항목 추가
 * PUT    /api/todo                     - 항목 수정 (토글 포함)
 * DELETE /api/todo?trip_code=xxx&id=xx - 항목 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT * FROM todos WHERE trip_code = ?
         ORDER BY is_done ASC, due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
    );
    $stmt->execute([$tripCode]);
    $items = $stmt->fetchAll();

    // 통계
    $total = count($items);
    $done = 0;
    foreach ($items as $item) {
        if ((int) $item['is_done'] === 1) {
            $done++;
        }
    }

    jsonResponse(true, [
        'items' => $items,
        'total' => $total,
        'done'  => $done,
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode   = trim($input['trip_code'] ?? '');
    $title      = trim($input['title'] ?? '');
    $detail     = trim($input['detail'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');
    $dueDate    = trim($input['due_date'] ?? '');

    if (empty($tripCode) || empty($title)) {
        jsonResponse(false, null, '제목을 입력해주세요.', 400);
    }

    // 마지막 sort_order + 1
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM todos WHERE trip_code = ?');
    $stmt->execute([$tripCode]);
    $nextOrder = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO todos (trip_code, title, detail, assigned_to, due_date, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $tripCode,
        $title,
        $detail ?: null,
        $assignedTo ?: null,
        $dueDate ?: null,
        $nextOrder,
    ]);

    $newId = $db->lastInsertId();

    // 새로 생성된 항목 반환
    $stmt = $db->prepare('SELECT * FROM todos WHERE id = ?');
    $stmt->execute([$newId]);
    $newItem = $stmt->fetch();

    jsonResponse(true, $newItem, '할 일이 추가되었습니다.');
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($input['id'] ?? 0);
    $tripCode = trim($input['trip_code'] ?? '');

    if (empty($id) || empty($tripCode)) {
        jsonResponse(false, null, '잘못된 요청입니다.', 400);
    }

    // 토글만 요청 (is_done 필드만 존재)
    if (isset($input['is_done']) && !isset($input['title'])) {
        $isDone = (int) $input['is_done'];

        $stmt = $db->prepare(
            'UPDATE todos SET is_done = ? WHERE id = ? AND trip_code = ?'
        );
        $stmt->execute([$isDone, $id, $tripCode]);

        jsonResponse(true, null, $isDone ? '완료 처리되었습니다.' : '미완료로 변경되었습니다.');
    }

    // 전체 수정
    $title      = trim($input['title'] ?? '');
    $detail     = trim($input['detail'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');
    $dueDate    = trim($input['due_date'] ?? '');

    if (empty($title)) {
        jsonResponse(false, null, '제목을 입력해주세요.', 400);
    }

    $stmt = $db->prepare(
        'UPDATE todos SET title = ?, detail = ?, assigned_to = ?, due_date = ? WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([
        $title,
        $detail ?: null,
        $assignedTo ?: null,
        $dueDate ?: null,
        $id,
        $tripCode,
    ]);

    jsonResponse(true, null, '할 일이 수정되었습니다.');
}

if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    if (empty($id) || empty($tripCode)) {
        jsonResponse(false, null, '잘못된 요청입니다.', 400);
    }

    $stmt = $db->prepare('DELETE FROM todos WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    jsonResponse(true, null, '할 일이 삭제되었습니다.');
}

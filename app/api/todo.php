<?php
/**
 * To-Do API
 * GET    /api/todo?trip_code=xxx&user_id=xxx  - 목록 조회
 * POST   /api/todo                            - 항목 추가
 * PUT    /api/todo                            - 항목 수정 / 완료 토글
 * DELETE /api/todo?...                        - 항목 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    $userId   = $_GET['user_id'] ?? '';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT * FROM todos WHERE trip_code = ?
         ORDER BY due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
    );
    $stmt->execute([$tripCode]);
    $items = $stmt->fetchAll();

    // 현재 사용자의 완료 항목 ID 목록
    $myCompletedIds = [];
    if ($userId) {
        $stmt = $db->prepare(
            'SELECT todo_id FROM todo_completions WHERE trip_code = ? AND user_id = ?'
        );
        $stmt->execute([$tripCode, $userId]);
        $myCompletedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // 전체 담당자별 완료 현황 (todo_id => [완료한 user_id 목록])
    $stmt = $db->prepare(
        'SELECT todo_id, user_id FROM todo_completions WHERE trip_code = ?'
    );
    $stmt->execute([$tripCode]);
    $completionMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $tid = (int) $row['todo_id'];
        $completionMap[$tid][] = $row['user_id'];
    }

    $total = count($items);
    $done  = count($myCompletedIds);

    jsonResponse(true, [
        'items'          => $items,
        'total'          => $total,
        'done'           => $done,
        'myCompletedIds' => $myCompletedIds,
        'completionMap'  => $completionMap,
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
    $assignedTo = trim($input['assigned_to'] ?? '');  // comma-separated
    $dueDate    = trim($input['due_date'] ?? '');

    if (empty($tripCode) || empty($title)) {
        jsonResponse(false, null, '제목을 입력해주세요.', 400);
    }

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
    $stmt  = $db->prepare('SELECT * FROM todos WHERE id = ?');
    $stmt->execute([$newId]);
    $newItem = $stmt->fetch();

    if ($assignedTo) {
        try {
            $targetUsers = explode(',', $assignedTo);
            $reqUserId = $input['user_id'] ?? '';
            $addUser = getTripUser($db, $tripCode, $reqUserId);
            $addUserName = $addUser ? $addUser['display_name'] : $reqUserId;
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '새 할일',
                $addUserName . '님이 \'' . $title . '\' 추가',
                '/' . $tripCode . '/{USER_ID}/checklist', 'todo');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

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

    // 완료 토글 요청 (is_done 필드 존재, title 필드 없음)
    if (isset($input['is_done']) && !isset($input['title'])) {
        $isDone = (int) $input['is_done'];
        $userId = trim($input['user_id'] ?? '');

        if (empty($userId)) {
            jsonResponse(false, null, '사용자 정보가 필요합니다.', 400);
        }

        if (!isMemberAuthenticated($tripCode, $userId)) {
            jsonResponse(false, null, '인증이 필요합니다.', 401);
        }

        if ($isDone) {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO todo_completions (todo_id, trip_code, user_id) VALUES (?, ?, ?)'
            );
            $stmt->execute([$id, $tripCode, $userId]);
        } else {
            $stmt = $db->prepare(
                'DELETE FROM todo_completions WHERE todo_id = ? AND trip_code = ? AND user_id = ?'
            );
            $stmt->execute([$id, $tripCode, $userId]);
        }

        // 해당 아이템의 완료자 목록 반환 (UI 즉시 업데이트용)
        $stmt = $db->prepare(
            'SELECT user_id FROM todo_completions WHERE todo_id = ? AND trip_code = ?'
        );
        $stmt->execute([$id, $tripCode]);
        $completedUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 완료 시에만 알림 (해제 시 노이즈 방지, 할당된 사용자에게만)
        if ($isDone) {
            try {
                $stmt2 = $db->prepare('SELECT * FROM todos WHERE id = ? AND trip_code = ?');
                $stmt2->execute([$id, $tripCode]);
                $todoItem = $stmt2->fetch();
                if ($todoItem && $todoItem['assigned_to']) {
                    $completeUser = getTripUser($db, $tripCode, $userId);
                    $completeUserName = $completeUser ? $completeUser['display_name'] : $userId;
                    $targetUsers = explode(',', $todoItem['assigned_to']);
                    queuePushNotification($db, $tripCode, $targetUsers, $userId, '할일 완료',
                        $completeUserName . '님이 \'' . $todoItem['title'] . '\' 완료',
                        '/' . $tripCode . '/{USER_ID}/checklist', 'todo');
                }
            } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
        }

        jsonResponse(true, ['completedUsers' => $completedUsers],
            $isDone ? '완료 처리되었습니다.' : '미완료로 변경되었습니다.');
    }

    // 항목 수정
    $title      = trim($input['title'] ?? '');
    $detail     = trim($input['detail'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');  // comma-separated
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

    if ($assignedTo) {
        try {
            $targetUsers = explode(',', $assignedTo);
            $reqUserId = $input['user_id'] ?? '';
            $editUser = getTripUser($db, $tripCode, $reqUserId);
            $editUserName = $editUser ? $editUser['display_name'] : $reqUserId;
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '할일 수정',
                $editUserName . '님이 \'' . $title . '\' 수정',
                '/' . $tripCode . '/{USER_ID}/checklist', 'todo');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

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

    // 삭제 전 항목 정보 조회 (알림용)
    $stmt = $db->prepare('SELECT * FROM todos WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);
    $delItem = $stmt->fetch();

    $stmt = $db->prepare('DELETE FROM todos WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    // 관련 완료 기록도 삭제
    $stmt = $db->prepare('DELETE FROM todo_completions WHERE todo_id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    if ($delItem && $delItem['assigned_to']) {
        try {
            $reqUserId = $_GET['user_id'] ?? '';
            $delUser = getTripUser($db, $tripCode, $reqUserId);
            $delUserName = $delUser ? $delUser['display_name'] : $reqUserId;
            $targetUsers = explode(',', $delItem['assigned_to']);
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '할일 삭제',
                $delUserName . '님이 \'' . $delItem['title'] . '\' 삭제',
                '/' . $tripCode . '/{USER_ID}/checklist', 'todo');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

    jsonResponse(true, null, '할 일이 삭제되었습니다.');
}

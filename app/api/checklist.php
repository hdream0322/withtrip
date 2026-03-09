<?php
/**
 * 체크리스트 API
 * GET    /api/checklist?trip_code=xxx&user_id=xxx  - 목록 조회
 * POST   /api/checklist                            - 항목 추가
 * PUT    /api/checklist                            - 항목 수정 / 완료 토글
 * DELETE /api/checklist?...                        - 항목 삭제
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
        'SELECT * FROM checklists WHERE trip_code = ? ORDER BY category ASC, sort_order ASC, id ASC'
    );
    $stmt->execute([$tripCode]);
    $items = $stmt->fetchAll();

    // 현재 사용자의 완료 항목 ID 목록
    $myCompletedIds = [];
    if ($userId) {
        $stmt = $db->prepare(
            'SELECT checklist_id FROM checklist_completions WHERE trip_code = ? AND user_id = ?'
        );
        $stmt->execute([$tripCode, $userId]);
        $myCompletedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // 전체 담당자별 완료 현황 (checklist_id => [완료한 user_id 목록])
    $stmt = $db->prepare(
        'SELECT checklist_id, user_id FROM checklist_completions WHERE trip_code = ?'
    );
    $stmt->execute([$tripCode]);
    $completionMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $cid = (int) $row['checklist_id'];
        $completionMap[$cid][] = $row['user_id'];
    }

    // 카테고리별 그룹핑
    $grouped = [];
    foreach ($items as $item) {
        $cat = $item['category'] ?: '기타';
        $grouped[$cat][] = $item;
    }

    $total = count($items);
    $done  = count($myCompletedIds);

    jsonResponse(true, [
        'items'          => $items,
        'grouped'        => $grouped,
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
    $category   = trim($input['category'] ?? '');
    $item       = trim($input['item'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');  // comma-separated user_id 목록

    if (empty($tripCode) || empty($item)) {
        jsonResponse(false, null, '항목 이름을 입력해주세요.', 400);
    }

    $stmt = $db->prepare(
        'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM checklists WHERE trip_code = ? AND (category = ? OR (category IS NULL AND ? = ""))'
    );
    $stmt->execute([$tripCode, $category ?: null, $category]);
    $nextOrder = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO checklists (trip_code, category, item, assigned_to, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $tripCode,
        $category ?: null,
        $item,
        $assignedTo ?: null,
        $nextOrder,
    ]);

    $newId = $db->lastInsertId();
    $stmt  = $db->prepare('SELECT * FROM checklists WHERE id = ?');
    $stmt->execute([$newId]);
    $newItem = $stmt->fetch();

    if ($assignedTo) {
        try {
            $targetUsers = explode(',', $assignedTo);
            $reqUserId = $input['user_id'] ?? '';
            $addUser = getTripUser($db, $tripCode, $reqUserId);
            $addUserName = $addUser ? $addUser['display_name'] : $reqUserId;
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '새 준비물',
                $addUserName . '님이 \'' . $item . '\' 추가',
                '/' . $tripCode . '/{USER_ID}/checklist', 'checklist');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

    jsonResponse(true, $newItem, '항목이 추가되었습니다.');
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

    // 완료 토글 요청 (is_done 필드 존재, item 필드 없음)
    if (isset($input['is_done']) && !isset($input['item'])) {
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
                'INSERT IGNORE INTO checklist_completions (checklist_id, trip_code, user_id) VALUES (?, ?, ?)'
            );
            $stmt->execute([$id, $tripCode, $userId]);
        } else {
            $stmt = $db->prepare(
                'DELETE FROM checklist_completions WHERE checklist_id = ? AND trip_code = ? AND user_id = ?'
            );
            $stmt->execute([$id, $tripCode, $userId]);
        }

        // 해당 아이템의 완료자 목록 반환 (UI 즉시 업데이트용)
        $stmt = $db->prepare(
            'SELECT user_id FROM checklist_completions WHERE checklist_id = ? AND trip_code = ?'
        );
        $stmt->execute([$id, $tripCode]);
        $completedUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 완료 시에만 알림 (해제 시 노이즈 방지, 할당된 사용자에게만)
        if ($isDone) {
            try {
                $stmt2 = $db->prepare('SELECT * FROM checklists WHERE id = ? AND trip_code = ?');
                $stmt2->execute([$id, $tripCode]);
                $checkItem = $stmt2->fetch();
                if ($checkItem && $checkItem['assigned_to']) {
                    $completeUser = getTripUser($db, $tripCode, $userId);
                    $completeUserName = $completeUser ? $completeUser['display_name'] : $userId;
                    $targetUsers = explode(',', $checkItem['assigned_to']);
                    queuePushNotification($db, $tripCode, $targetUsers, $userId, '준비물 체크',
                        $completeUserName . '님이 \'' . $checkItem['item'] . '\' 완료',
                        '/' . $tripCode . '/{USER_ID}/checklist', 'checklist');
                }
            } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
        }

        jsonResponse(true, ['completedUsers' => $completedUsers],
            $isDone ? '완료 처리되었습니다.' : '미완료로 변경되었습니다.');
    }

    // 항목 수정
    $category   = trim($input['category'] ?? '');
    $item       = trim($input['item'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');  // comma-separated

    if (empty($item)) {
        jsonResponse(false, null, '항목 이름을 입력해주세요.', 400);
    }

    $stmt = $db->prepare(
        'UPDATE checklists SET category = ?, item = ?, assigned_to = ? WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([
        $category ?: null,
        $item,
        $assignedTo ?: null,
        $id,
        $tripCode,
    ]);

    if ($assignedTo) {
        try {
            $targetUsers = explode(',', $assignedTo);
            $reqUserId = $input['user_id'] ?? '';
            $editUser = getTripUser($db, $tripCode, $reqUserId);
            $editUserName = $editUser ? $editUser['display_name'] : $reqUserId;
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '준비물 수정',
                $editUserName . '님이 \'' . $item . '\' 수정',
                '/' . $tripCode . '/{USER_ID}/checklist', 'checklist');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

    jsonResponse(true, null, '항목이 수정되었습니다.');
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
    $stmt = $db->prepare('SELECT * FROM checklists WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);
    $delItem = $stmt->fetch();

    $stmt = $db->prepare('DELETE FROM checklists WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    // 관련 완료 기록도 삭제
    $stmt = $db->prepare('DELETE FROM checklist_completions WHERE checklist_id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    if ($delItem && $delItem['assigned_to']) {
        try {
            $reqUserId = $_GET['user_id'] ?? '';
            $delUser = getTripUser($db, $tripCode, $reqUserId);
            $delUserName = $delUser ? $delUser['display_name'] : $reqUserId;
            $targetUsers = explode(',', $delItem['assigned_to']);
            queuePushNotification($db, $tripCode, $targetUsers, $reqUserId, '준비물 삭제',
                $delUserName . '님이 \'' . $delItem['item'] . '\' 삭제',
                '/' . $tripCode . '/{USER_ID}/checklist', 'checklist');
        } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }
    }

    jsonResponse(true, null, '항목이 삭제되었습니다.');
}

<?php
/**
 * 체크리스트 API
 * GET    /api/checklist?trip_code=xxx       - 목록 조회
 * POST   /api/checklist                     - 항목 추가
 * PUT    /api/checklist                     - 항목 수정 (토글 포함)
 * DELETE /api/checklist?trip_code=xxx&id=xx - 항목 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT * FROM checklists WHERE trip_code = ? ORDER BY is_done ASC, category ASC, sort_order ASC, id ASC'
    );
    $stmt->execute([$tripCode]);
    $items = $stmt->fetchAll();

    // 카테고리별 그룹핑
    $grouped = [];
    foreach ($items as $item) {
        $cat = $item['category'] ?: '기타';
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $item;
    }

    // 통계
    $total = count($items);
    $done = 0;
    foreach ($items as $item) {
        if ((int) $item['is_done'] === 1) {
            $done++;
        }
    }

    jsonResponse(true, [
        'items'   => $items,
        'grouped' => $grouped,
        'total'   => $total,
        'done'    => $done,
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
    $assignedTo = trim($input['assigned_to'] ?? '');

    if (empty($tripCode) || empty($item)) {
        jsonResponse(false, null, '항목 이름을 입력해주세요.', 400);
    }

    // 같은 카테고리의 마지막 sort_order + 1
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

    // 새로 생성된 항목 반환
    $stmt = $db->prepare('SELECT * FROM checklists WHERE id = ?');
    $stmt->execute([$newId]);
    $newItem = $stmt->fetch();

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

    // 토글만 요청 (is_done 필드만 존재)
    if (isset($input['is_done']) && !isset($input['item'])) {
        $isDone = (int) $input['is_done'];

        $stmt = $db->prepare(
            'UPDATE checklists SET is_done = ? WHERE id = ? AND trip_code = ?'
        );
        $stmt->execute([$isDone, $id, $tripCode]);

        jsonResponse(true, null, $isDone ? '완료 처리되었습니다.' : '미완료로 변경되었습니다.');
    }

    // 전체 수정
    $category   = trim($input['category'] ?? '');
    $item       = trim($input['item'] ?? '');
    $assignedTo = trim($input['assigned_to'] ?? '');

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

    $stmt = $db->prepare('DELETE FROM checklists WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    jsonResponse(true, null, '항목이 삭제되었습니다.');
}

<?php
/**
 * 예산 카테고리 API
 * GET    /api/budget/categories?trip_code=xxx - 카테고리 목록 (실지출 합계 포함)
 * POST   /api/budget/categories - 카테고리 추가
 * PUT    /api/budget/categories - 카테고리 수정
 * DELETE /api/budget/categories - 카테고리 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// GET - 카테고리 목록 조회 (실지출 합계 포함)
if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT bc.*,
                COALESCE(SUM(e.amount), 0) AS spent_amount
         FROM budget_categories bc
         LEFT JOIN expenses e ON e.category_id = bc.id AND e.trip_code = bc.trip_code
         WHERE bc.trip_code = ?
         GROUP BY bc.id
         ORDER BY bc.sort_order ASC, bc.id ASC'
    );
    $stmt->execute([$tripCode]);
    $categories = $stmt->fetchAll();

    // 총합 계산
    $totalPlanned = 0;
    $totalSpent = 0;
    foreach ($categories as &$cat) {
        $cat['planned_amount'] = (int) $cat['planned_amount'];
        $cat['spent_amount'] = (int) $cat['spent_amount'];
        $totalPlanned += $cat['planned_amount'];
        $totalSpent += $cat['spent_amount'];
    }
    unset($cat);

    jsonResponse(true, [
        'categories'    => $categories,
        'total_planned' => $totalPlanned,
        'total_spent'   => $totalSpent,
    ]);
}

// POST - 카테고리 추가
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode      = $input['trip_code'] ?? '';
    $name          = trim($input['name'] ?? '');
    $plannedAmount = (int) ($input['planned_amount'] ?? 0);
    $currency      = $input['currency'] ?? 'KRW';

    if (empty($name)) {
        jsonResponse(false, null, '카테고리 이름을 입력해주세요.', 400);
    }

    if (!in_array($currency, ['KRW', 'USD'], true)) {
        jsonResponse(false, null, '지원하지 않는 통화입니다.', 400);
    }

    // sort_order 계산
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM budget_categories WHERE trip_code = ?');
    $stmt->execute([$tripCode]);
    $sortOrder = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO budget_categories (trip_code, name, planned_amount, currency, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$tripCode, $name, $plannedAmount, $currency, $sortOrder]);

    jsonResponse(true, [
        'id'   => (int) $db->lastInsertId(),
        'name' => $name,
    ], '카테고리가 추가되었습니다.');
}

// PUT - 카테고리 수정
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id            = (int) ($input['id'] ?? 0);
    $tripCode      = $input['trip_code'] ?? '';
    $name          = trim($input['name'] ?? '');
    $plannedAmount = (int) ($input['planned_amount'] ?? 0);
    $currency      = $input['currency'] ?? 'KRW';

    if (empty($name)) {
        jsonResponse(false, null, '카테고리 이름을 입력해주세요.', 400);
    }

    if (!in_array($currency, ['KRW', 'USD'], true)) {
        jsonResponse(false, null, '지원하지 않는 통화입니다.', 400);
    }

    $stmt = $db->prepare(
        'UPDATE budget_categories SET name = ?, planned_amount = ?, currency = ?
         WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([$name, $plannedAmount, $currency, $id, $tripCode]);

    jsonResponse(true, null, '카테고리가 수정되었습니다.');
}

// DELETE - 카테고리 삭제
if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    // 해당 카테고리에 연결된 지출의 category_id를 NULL로 변경
    $stmt = $db->prepare('UPDATE expenses SET category_id = NULL WHERE category_id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    $stmt = $db->prepare('DELETE FROM budget_categories WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    jsonResponse(true, null, '카테고리가 삭제되었습니다.');
}

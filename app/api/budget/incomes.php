<?php
/**
 * 수입 API
 * GET    /api/budget/incomes?trip_code=xxx    — 수입 목록
 * POST   /api/budget/incomes                  — 수입 추가
 * PUT    /api/budget/incomes                  — 수입 수정
 * DELETE /api/budget/incomes?id=xxx&trip_code=xxx — 수입 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT i.*, u.display_name AS user_name
         FROM incomes i
         LEFT JOIN users u ON u.trip_code = i.trip_code AND u.user_id = i.user_id
         WHERE i.trip_code = ?
         ORDER BY i.income_date DESC, IFNULL(i.income_time, "23:59:59") DESC, i.created_at DESC'
    );
    $stmt->execute([$tripCode]);
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, ['incomes' => $incomes]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode    = trim($input['trip_code'] ?? '');
    $userId      = trim($input['user_id'] ?? '');
    $amount      = (int) ($input['amount'] ?? 0);
    $currency    = trim($input['currency'] ?? 'KRW');
    $type        = trim($input['type'] ?? 'other');
    $description = trim($input['description'] ?? '');
    $incomeDate  = $input['income_date'] ?? null;
    $incomeTime  = $input['income_time'] ?? null;

    if (empty($tripCode) || empty($userId) || $amount <= 0) {
        jsonResponse(false, null, '필수 항목을 입력해주세요.', 400);
    }

    if (!in_array($type, ['budget', 'refund', 'other'])) {
        $type = 'other';
    }

    if (!in_array($currency, ['KRW','USD','EUR','JPY','CNH','GBP','AUD','CAD','HKD','SGD','THB'], true)) {
        $currency = 'KRW';
    }

    $stmt = $db->prepare(
        'INSERT INTO incomes (trip_code, user_id, amount, currency, type, description, income_date, income_time)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$tripCode, $userId, $amount, $currency, $type, $description ?: null, $incomeDate ?: null, $incomeTime ?: null]);

    jsonResponse(true, ['id' => $db->lastInsertId()], '수입이 추가되었습니다.');
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id          = (int) ($input['id'] ?? 0);
    $tripCode    = trim($input['trip_code'] ?? '');
    $userId      = trim($input['user_id'] ?? '');
    $amount      = (int) ($input['amount'] ?? 0);
    $currency    = trim($input['currency'] ?? 'KRW');
    $type        = trim($input['type'] ?? 'other');
    $description = trim($input['description'] ?? '');
    $incomeDate  = $input['income_date'] ?? null;
    $incomeTime  = $input['income_time'] ?? null;

    if ($id <= 0 || empty($tripCode) || $amount <= 0) {
        jsonResponse(false, null, '필수 항목을 입력해주세요.', 400);
    }

    if (!in_array($type, ['budget', 'refund', 'other'])) {
        $type = 'other';
    }

    if (!in_array($currency, ['KRW','USD','EUR','JPY','CNH','GBP','AUD','CAD','HKD','SGD','THB'], true)) {
        $currency = 'KRW';
    }

    $stmt = $db->prepare(
        'UPDATE incomes SET user_id = ?, amount = ?, currency = ?, type = ?, description = ?, income_date = ?, income_time = ?
         WHERE id = ? AND trip_code = ?'
    );
    $stmt->execute([$userId, $amount, $currency, $type, $description ?: null, $incomeDate ?: null, $incomeTime ?: null, $id, $tripCode]);

    jsonResponse(true, null, '수입이 수정되었습니다.');
}

if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    if ($id <= 0 || empty($tripCode)) {
        jsonResponse(false, null, '필수 파라미터가 누락되었습니다.', 400);
    }

    $stmt = $db->prepare('DELETE FROM incomes WHERE id = ? AND trip_code = ?');
    $stmt->execute([$id, $tripCode]);

    jsonResponse(true, null, '수입이 삭제되었습니다.');
}

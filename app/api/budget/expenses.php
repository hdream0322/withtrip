<?php
/**
 * 지출 API
 * GET    /api/budget/expenses?trip_code=xxx - 지출 목록
 * POST   /api/budget/expenses - 지출 추가 (더치페이 시 dutch_splits 함께 저장)
 * PUT    /api/budget/expenses - 지출 수정
 * DELETE /api/budget/expenses - 지출 삭제
 */

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// GET - 지출 목록 조회
if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT e.*,
                u.display_name AS paid_by_name
         FROM expenses e
         LEFT JOIN users u ON u.trip_code = e.trip_code AND u.user_id = e.paid_by
         WHERE e.trip_code = ?
         ORDER BY e.expense_date DESC, IFNULL(e.expense_time, "23:59:59") DESC, e.created_at DESC'
    );
    $stmt->execute([$tripCode]);
    $expenses = $stmt->fetchAll();

    // 각 지출의 더치페이 분담 내역 조회
    foreach ($expenses as &$expense) {
        $expense['amount'] = (int) $expense['amount'];
        $expense['is_dutch'] = (int) $expense['is_dutch'];

        if ($expense['is_dutch']) {
            $stmt = $db->prepare(
                'SELECT ds.*, u.display_name
                 FROM dutch_splits ds
                 LEFT JOIN users u ON u.trip_code = ds.trip_code AND u.user_id = ds.user_id
                 WHERE ds.expense_id = ?'
            );
            $stmt->execute([$expense['id']]);
            $splits = $stmt->fetchAll();

            foreach ($splits as &$split) {
                $split['amount'] = (int) $split['amount'];
            }
            unset($split);

            $expense['splits'] = $splits;
        } else {
            $expense['splits'] = [];
        }
    }
    unset($expense);

    jsonResponse(true, ['expenses' => $expenses]);
}

// POST - 지출 추가
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode      = $input['trip_code'] ?? '';
    $categoryId    = !empty($input['category_id']) ? (int) $input['category_id'] : null;
    $paidBy        = trim($input['paid_by'] ?? '');
    $amount        = (int) ($input['amount'] ?? 0);
    $currency      = $input['currency'] ?? 'KRW';
    $description   = trim($input['description'] ?? '');
    $expenseDate   = $input['expense_date'] ?? null;
    $expenseTime   = $input['expense_time'] ?? null;
    $isDutch       = (int) ($input['is_dutch'] ?? 0);
    $paymentMethod = in_array($input['payment_method'] ?? '', ['cash', 'card'], true) ? $input['payment_method'] : 'card';
    $splits        = $input['splits'] ?? [];

    // 유효성 검증
    if (empty($paidBy)) {
        jsonResponse(false, null, '결제자를 선택해주세요.', 400);
    }

    if ($amount <= 0) {
        jsonResponse(false, null, '금액을 입력해주세요.', 400);
    }

    if (!in_array($currency, ['KRW','USD','EUR','JPY','CNH','GBP','AUD','CAD','HKD','SGD','THB'], true)) {
        jsonResponse(false, null, '지원하지 않는 통화입니다.', 400);
    }

    $expenseDate = !empty($expenseDate) ? $expenseDate : null;
    $expenseTime = !empty($expenseTime) ? $expenseTime : null;

    // 트랜잭션 시작
    $db->beginTransaction();

    try {
        // 지출 저장
        $stmt = $db->prepare(
            'INSERT INTO expenses (trip_code, category_id, paid_by, amount, currency, description, expense_date, expense_time, is_dutch, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tripCode, $categoryId, $paidBy, $amount, $currency, $description ?: null, $expenseDate, $expenseTime, $isDutch, $paymentMethod]);
        $expenseId = (int) $db->lastInsertId();

        // 더치페이 분담 내역 저장
        if ($isDutch && !empty($splits)) {
            $stmtSplit = $db->prepare(
                'INSERT INTO dutch_splits (expense_id, trip_code, user_id, amount)
                 VALUES (?, ?, ?, ?)'
            );

            foreach ($splits as $split) {
                $splitUserId = trim($split['user_id'] ?? '');
                $splitAmount = (int) ($split['amount'] ?? 0);

                if (!empty($splitUserId) && $splitAmount > 0) {
                    $stmtSplit->execute([$expenseId, $tripCode, $splitUserId, $splitAmount]);
                }
            }
        }

        $db->commit();
        jsonResponse(true, ['id' => $expenseId], '지출이 추가되었습니다.');

    } catch (Throwable $e) {
        $db->rollBack();

        if ($_ENV['APP_ENV'] === 'development') {
            jsonResponse(false, null, '지출 추가 실패: ' . $e->getMessage(), 500);
        }

        jsonResponse(false, null, '지출 추가 중 오류가 발생했습니다.', 500);
    }
}

// PUT - 지출 수정
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id            = (int) ($input['id'] ?? 0);
    $tripCode      = $input['trip_code'] ?? '';
    $categoryId    = !empty($input['category_id']) ? (int) $input['category_id'] : null;
    $paidBy        = trim($input['paid_by'] ?? '');
    $amount        = (int) ($input['amount'] ?? 0);
    $currency      = $input['currency'] ?? 'KRW';
    $description   = trim($input['description'] ?? '');
    $expenseDate   = $input['expense_date'] ?? null;
    $expenseTime   = $input['expense_time'] ?? null;
    $isDutch       = (int) ($input['is_dutch'] ?? 0);
    $paymentMethod = in_array($input['payment_method'] ?? '', ['cash', 'card'], true) ? $input['payment_method'] : 'card';
    $splits        = $input['splits'] ?? [];

    if (empty($paidBy)) {
        jsonResponse(false, null, '결제자를 선택해주세요.', 400);
    }

    if ($amount <= 0) {
        jsonResponse(false, null, '금액을 입력해주세요.', 400);
    }

    if (!in_array($currency, ['KRW','USD','EUR','JPY','CNH','GBP','AUD','CAD','HKD','SGD','THB'], true)) {
        jsonResponse(false, null, '지원하지 않는 통화입니다.', 400);
    }

    $expenseDate = !empty($expenseDate) ? $expenseDate : null;
    $expenseTime = !empty($expenseTime) ? $expenseTime : null;

    $db->beginTransaction();

    try {
        // 지출 수정
        $stmt = $db->prepare(
            'UPDATE expenses
             SET category_id = ?, paid_by = ?, amount = ?, currency = ?,
                 description = ?, expense_date = ?, expense_time = ?, is_dutch = ?, payment_method = ?
             WHERE id = ? AND trip_code = ?'
        );
        $stmt->execute([$categoryId, $paidBy, $amount, $currency, $description ?: null, $expenseDate, $expenseTime, $isDutch, $paymentMethod, $id, $tripCode]);

        // 기존 분담 내역 삭제 후 재입력
        $stmt = $db->prepare('DELETE FROM dutch_splits WHERE expense_id = ? AND trip_code = ?');
        $stmt->execute([$id, $tripCode]);

        // 더치페이 분담 내역 재저장
        if ($isDutch && !empty($splits)) {
            $stmtSplit = $db->prepare(
                'INSERT INTO dutch_splits (expense_id, trip_code, user_id, amount)
                 VALUES (?, ?, ?, ?)'
            );

            foreach ($splits as $split) {
                $splitUserId = trim($split['user_id'] ?? '');
                $splitAmount = (int) ($split['amount'] ?? 0);

                if (!empty($splitUserId) && $splitAmount > 0) {
                    $stmtSplit->execute([$id, $tripCode, $splitUserId, $splitAmount]);
                }
            }
        }

        $db->commit();
        jsonResponse(true, null, '지출이 수정되었습니다.');

    } catch (Throwable $e) {
        $db->rollBack();

        if ($_ENV['APP_ENV'] === 'development') {
            jsonResponse(false, null, '지출 수정 실패: ' . $e->getMessage(), 500);
        }

        jsonResponse(false, null, '지출 수정 중 오류가 발생했습니다.', 500);
    }
}

// DELETE - 지출 삭제
if ($method === 'DELETE') {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $id       = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';

    $db->beginTransaction();

    try {
        // 분담 내역 삭제
        $stmt = $db->prepare('DELETE FROM dutch_splits WHERE expense_id = ? AND trip_code = ?');
        $stmt->execute([$id, $tripCode]);

        // 지출 삭제
        $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND trip_code = ?');
        $stmt->execute([$id, $tripCode]);

        $db->commit();
        jsonResponse(true, null, '지출이 삭제되었습니다.');

    } catch (Throwable $e) {
        $db->rollBack();

        if ($_ENV['APP_ENV'] === 'development') {
            jsonResponse(false, null, '지출 삭제 실패: ' . $e->getMessage(), 500);
        }

        jsonResponse(false, null, '지출 삭제 중 오류가 발생했습니다.', 500);
    }
}

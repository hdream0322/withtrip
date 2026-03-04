<?php
/**
 * 여행 환율 API (trip_exchange_rates 테이블)
 * GET  /api/trips/rate?trip_code=xxx  — 저장된 환율 조회 + needs_refresh 여부
 * POST /api/trips/rate                — 환율 일괄 저장
 */

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

const REFRESH_INTERVAL_SECONDS = 3600; // 1시간

// GET — 환율 조회
if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $stmt = $db->prepare(
        'SELECT currency, rate, rate_adjustment, updated_at
         FROM trip_exchange_rates
         WHERE trip_code = ?
         ORDER BY currency'
    );
    $stmt->execute([$tripCode]);
    $rows = $stmt->fetchAll();

    $rates       = [];  // 조정 적용된 실효 환율 (계산에 사용)
    $baseRates   = [];  // TTS 기준 원본 환율
    $adjustments = [];  // 사용자 조정값
    $updatedAt   = null;

    foreach ($rows as $row) {
        $base = (float) $row['rate'];
        $adj  = (float) ($row['rate_adjustment'] ?? 0);
        $baseRates[$row['currency']]   = $base;
        $adjustments[$row['currency']] = $adj;
        $rates[$row['currency']]       = round($base + $adj, 4);

        if ($updatedAt === null || $row['updated_at'] > $updatedAt) {
            $updatedAt = $row['updated_at'];
        }
    }

    // 1시간 이상 지났거나 데이터 없으면 갱신 권장
    $needsRefresh = true;
    if ($updatedAt !== null) {
        $diff         = time() - strtotime($updatedAt);
        $needsRefresh = $diff >= REFRESH_INTERVAL_SECONDS;
    }

    jsonResponse(true, [
        'rates'         => $rates,
        'base_rates'    => $baseRates,
        'adjustments'   => $adjustments,
        'updated_at'    => $updatedAt,
        'needs_refresh' => $needsRefresh,
    ]);
}

// POST — 환율 저장 (여러 통화 한번에)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode    = $input['trip_code'] ?? '';
    $rates       = $input['rates'] ?? [];        // ['USD' => 1450, ...] 기준 환율
    $adjustments = $input['adjustments'] ?? [];  // ['USD' => 10, ...]   조정값

    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $allowed = ['USD', 'EUR', 'JPY', 'CNH', 'GBP', 'AUD', 'CAD', 'HKD', 'SGD', 'THB'];

    // 기준 환율 저장 (실시간 불러오기 시)
    if (!empty($rates) && is_array($rates)) {
        $stmt = $db->prepare(
            'INSERT INTO trip_exchange_rates (trip_code, currency, rate, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_at = NOW()'
        );

        foreach ($rates as $currency => $rate) {
            if (!in_array($currency, $allowed, true)) continue;
            $rate = (float) $rate;
            if ($rate <= 0) continue;
            $stmt->execute([$tripCode, $currency, $rate]);
        }
    }

    // 조정값 저장 (카드 수수료 등 사용자 조정)
    if (!empty($adjustments) && is_array($adjustments)) {
        $stmt = $db->prepare(
            'UPDATE trip_exchange_rates
             SET rate_adjustment = ?
             WHERE trip_code = ? AND currency = ?'
        );

        foreach ($adjustments as $currency => $adj) {
            if (!in_array($currency, $allowed, true)) continue;
            $stmt->execute([(float) $adj, $tripCode, $currency]);
        }
    }

    if (empty($rates) && empty($adjustments)) {
        jsonResponse(false, null, '저장할 데이터가 없습니다.', 400);
    }

    jsonResponse(true, null, '저장되었습니다.');
}

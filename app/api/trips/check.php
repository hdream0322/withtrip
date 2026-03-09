<?php
/**
 * 여행 코드 존재 확인 API
 * GET /api/trips/check?trip_code=xxx
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(false, null, '허용되지 않는 요청입니다.', 405);
}

$tripCode = $_GET['trip_code'] ?? '';

if (empty($tripCode) || !preg_match('/^[a-f0-9]{8}$/', $tripCode)) {
    jsonResponse(false, null, '유효하지 않은 여행 코드입니다.', 400);
}

$db = getDB();
$trip = getTripByCode($db, $tripCode);

if ($trip) {
    jsonResponse(true, ['exists' => true]);
} else {
    jsonResponse(false, ['exists' => false], '존재하지 않는 여행 코드입니다.');
}

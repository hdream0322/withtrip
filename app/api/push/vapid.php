<?php
/**
 * VAPID 공개 키 API
 * GET /api/push/vapid
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, '지원하지 않는 요청입니다.', 405);
}

$publicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? '';

if (empty($publicKey)) {
    jsonResponse(false, null, '푸시 알림이 설정되지 않았습니다.', 500);
}

jsonResponse(true, ['publicKey' => $publicKey]);

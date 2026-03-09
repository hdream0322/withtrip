<?php
/**
 * 동적 PWA manifest 생성
 * 여행 페이지: start_url = /{trip_code}/{user_id}/
 * 기본: start_url = /
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$tripCode = isset($_GET['trip']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['trip']) : '';
$userId   = isset($_GET['user']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['user']) : '';
$name     = isset($_GET['name']) ? mb_substr(strip_tags($_GET['name']), 0, 50) : '';

if ($tripCode && $userId) {
    $startUrl  = '/' . $tripCode . '/' . $userId . '/';
    $scope     = '/' . $tripCode . '/' . $userId . '/';
    $appName   = $name ? $name . ' - WithPlan' : 'WithPlan';
    $appId     = '/manifest.php?trip=' . urlencode($tripCode) . '&user=' . urlencode($userId);
} else {
    $startUrl = '/';
    $scope    = '/';
    $appName  = 'WithPlan - 여행 플래너';
    $appId    = '/manifest.json';
}

$manifest = [
    'id'               => $appId,
    'name'             => $appName,
    'short_name'       => 'WithPlan',
    'description'      => '함께 만드는 여행 계획',
    'start_url'        => $startUrl,
    'scope'            => $scope,
    'display'          => 'standalone',
    'background_color' => '#ffffff',
    'theme_color'      => '#0891b2',
    'orientation'      => 'portrait',
    'icons'            => [
        ['src' => '/assets/icons/icon-192.png',          'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/assets/icons/icon-512.png',          'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/assets/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => '/assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

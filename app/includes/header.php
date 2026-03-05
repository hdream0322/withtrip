<?php
/**
 * 공통 헤더
 * CSS_VERSION: CSS/JS 캐시 버스팅 버전
 */
const CSS_VERSION = '4.3.2';

// 현재 페이지 정보 (네비게이션 활성 탭 판별용)
$currentPage = $currentPage ?? 'home';
$tripCode    = $tripCode ?? '';
$userId      = $userId ?? '';
$tripTitle   = $tripTitle ?? 'WithPlan';
$pageTitle   = $pageTitle ?? $tripTitle;
$bodyClass   = $bodyClass ?? '';
$headExtra   = $headExtra ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0891b2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="/favicon/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/manifest.json" id="manifest-link">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <title><?= e($pageTitle) ?> - WithPlan</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="/assets/css/common.css?v=<?= CSS_VERSION ?>">
    <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="/assets/css/pages/<?= $pageCss ?>.css?v=<?= CSS_VERSION ?>">
    <?php endif; ?>
    <?= $headExtra ?>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    <?php if ($tripCode && $userId): ?>
    // 현재 여행이 start_url이 되도록 동적 manifest 생성
    (function() {
        var m = {
            name: <?= json_encode($tripTitle . ' - WithPlan', JSON_UNESCAPED_UNICODE) ?>,
            short_name: 'WithPlan',
            description: '함께 만드는 여행 계획',
            start_url: '/<?= e($tripCode) ?>/<?= e($userId) ?>/',
            display: 'standalone',
            background_color: '#ffffff',
            theme_color: '#0891b2',
            orientation: 'portrait',
            icons: [
                { src: '/assets/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                { src: '/assets/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                { src: '/assets/icons/icon-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
                { src: '/assets/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' }
            ]
        };
        var blob = new Blob([JSON.stringify(m)], { type: 'application/json' });
        document.getElementById('manifest-link').href = URL.createObjectURL(blob);
    })();
    <?php endif; ?>
    </script>
</head>
<body<?= $bodyClass ? ' class="' . e($bodyClass) . '"' : '' ?>>
<div class="app-container">

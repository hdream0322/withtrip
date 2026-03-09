<?php
/**
 * 공통 헤더
 * CSS_VERSION: CSS/JS 캐시 버스팅 버전
 */
const CSS_VERSION = '4.5.0';

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
    <?php if ($tripCode && $userId): ?>
    <link rel="manifest" href="/manifest.php?trip=<?= urlencode($tripCode) ?>&user=<?= urlencode($userId) ?>&name=<?= urlencode($tripTitle) ?>" id="manifest-link">
    <?php else: ?>
    <link rel="manifest" href="/manifest.json" id="manifest-link">
    <?php endif; ?>
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <title><?= e($pageTitle) ?> - WithPlan</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="/assets/css/common.css?v=<?= CSS_VERSION ?>">
    <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="/assets/css/pages/<?= $pageCss ?>.css?v=<?= CSS_VERSION ?>">
    <?php endif; ?>
    <?= $headExtra ?>
    <script src="/assets/js/push.js?v=<?= CSS_VERSION ?>"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    </script>
</head>
<body<?= $bodyClass ? ' class="' . e($bodyClass) . '"' : '' ?>>
<div class="app-container">

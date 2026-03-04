<?php
/**
 * 공통 헤더
 * CSS_VERSION: CSS/JS 캐시 버스팅 버전
 */
const CSS_VERSION = '3.0.17';

// 현재 페이지 정보 (네비게이션 활성 탭 판별용)
$currentPage = $currentPage ?? 'home';
$tripCode    = $tripCode ?? '';
$userId      = $userId ?? '';
$tripTitle   = $tripTitle ?? 'WithPlan';
$pageTitle   = $pageTitle ?? $tripTitle;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0891b2">
    <title><?= e($pageTitle) ?> - WithPlan</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="/assets/css/common.css?v=<?= CSS_VERSION ?>">
    <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="/assets/css/pages/<?= $pageCss ?>.css?v=<?= CSS_VERSION ?>">
    <?php endif; ?>
</head>
<body>
<div class="app-container">

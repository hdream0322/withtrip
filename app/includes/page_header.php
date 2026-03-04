<?php
/**
 * 멤버 페이지 공통 헤더 partial
 *
 * 사용 전 설정할 변수:
 *   $pageHeaderTitle    (필수) h1 텍스트
 *   $pageHeaderSubtitle (선택) 부제목 (기본: $tripTitle, false면 미표시)
 *   $pageHeaderRight    (선택) 우측 추가 HTML (뱃지 등)
 *   $pageHeaderMenu     (선택) true/false 설정 드롭다운 표시 (기본: true)
 *
 * 부모에서 이미 존재해야 하는 변수: $tripCode, $userId, $tripTitle
 */
$pageHeaderSubtitle = $pageHeaderSubtitle ?? $tripTitle;
$pageHeaderMenu     = $pageHeaderMenu ?? true;
?>
<div class="page-header">
    <div class="page-header-row">
        <div class="page-header-left">
            <h1><?= e($pageHeaderTitle) ?></h1>
            <?php if ($pageHeaderSubtitle): ?>
                <p class="subtitle"><?= $pageHeaderSubtitle ?></p>
            <?php endif; ?>
        </div>
        <?php if ($pageHeaderMenu || !empty($pageHeaderRight)): ?>
        <div class="header-right">
            <?= $pageHeaderRight ?? '' ?>
            <?php if ($pageHeaderMenu): ?>
            <div class="header-more-wrap">
                <button class="header-more-btn" onclick="toggleHeaderMenu()">
                    <span class="material-icons">more_vert</span>
                </button>
                <div class="header-dropdown" id="headerDropdown">
                    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settings" class="header-dropdown-item">
                        <span class="material-icons">settings</span> 설정
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

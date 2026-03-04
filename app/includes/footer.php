<?php
/**
 * 공통 푸터
 * 하단 네비게이션 바 포함
 */
$showNav = $showNav ?? false;
?>

<?php if ($showNav && !empty($tripCode) && !empty($userId)): ?>
<nav class="bottom-nav">
    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/" class="nav-item <?= $currentPage === 'home' ? 'active' : '' ?>">
        <span class="nav-icon">&#127968;</span>
        <span class="nav-label">홈</span>
    </a>
    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule" class="nav-item <?= $currentPage === 'schedule' ? 'active' : '' ?>">
        <span class="nav-icon">&#128197;</span>
        <span class="nav-label">일정</span>
    </a>
    <?php if ($currentPage === 'budget'): ?>
    <a href="#" id="navBudget" class="nav-item active">
        <span class="nav-icon">&#128176;</span>
        <span class="nav-label">지출</span>
    </a>
    <?php else: ?>
    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="nav-item">
        <span class="nav-icon">&#128176;</span>
        <span class="nav-label">지출</span>
    </a>
    <?php endif; ?>
    <?php
        $checkActive = in_array($currentPage, ['checklist', 'todo']) ? 'active' : '';
        $checkHref   = $currentPage === 'checklist'
            ? '/' . e($tripCode) . '/' . e($userId) . '/todo'
            : '/' . e($tripCode) . '/' . e($userId) . '/checklist';
    ?>
    <a href="<?= $checkHref ?>" id="nav-checklist" class="nav-item <?= $checkActive ?>">
        <span class="nav-icon">&#9989;</span>
        <span class="nav-label">체크</span>
    </a>
    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/notes" class="nav-item <?= $currentPage === 'notes' ? 'active' : '' ?>">
        <span class="nav-icon">&#128221;</span>
        <span class="nav-label">메모</span>
    </a>
</nav>
<?php endif; ?>

</div><!-- /.app-container -->

<script src="/assets/js/common.js?v=<?= CSS_VERSION ?>"></script>
<?php if (!empty($pageJs)): ?>
<script src="/assets/js/pages/<?= $pageJs ?>.js?v=<?= CSS_VERSION ?>"></script>
<?php endif; ?>
</body>
</html>

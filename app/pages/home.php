<?php
/**
 * 홈 대시보드
 * /{trip_code}/{user_id}/
 */
$currentPage = 'home';
$showNav = true;
$pageCss = 'home';

$db = getDB();

// 여행 정보
$tripTitle = $trip['title'];
$pageTitle = $tripTitle;

// D-day 계산
$dDay = null;
if ($trip['start_date']) {
    $startDate = new DateTime($trip['start_date']);
    $today = new DateTime('today');
    $diff = $today->diff($startDate);
    $dDay = $startDate > $today ? $diff->days : -$diff->days;
}

// 총 예산 / 지출
$stmt = $db->prepare('SELECT COALESCE(SUM(planned_amount), 0) FROM budget_categories WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalBudget = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalExpense = (int) $stmt->fetchColumn();

$budgetPercent = $totalBudget > 0 ? min(round($totalExpense / $totalBudget * 100), 100) : 0;

// To-Do 완료율
$stmt = $db->prepare('SELECT COUNT(*) FROM todos WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalTodo = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM todos WHERE trip_code = ? AND is_done = 1');
$stmt->execute([$tripCode]);
$doneTodo = (int) $stmt->fetchColumn();

// 체크리스트 완료율
$stmt = $db->prepare('SELECT COUNT(*) FROM checklists WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalChecklist = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM checklists WHERE trip_code = ? AND is_done = 1');
$stmt->execute([$tripCode]);
$doneChecklist = (int) $stmt->fetchColumn();

$checkPercent = $totalChecklist > 0 ? round($doneChecklist / $totalChecklist * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e($tripTitle) ?></h1>
    <?php if ($trip['destination']): ?>
        <p class="subtitle"><?= e($trip['destination']) ?></p>
    <?php endif; ?>
    <?php if ($trip['start_date'] && $trip['end_date']): ?>
        <p class="subtitle"><?= e($trip['start_date']) ?> ~ <?= e($trip['end_date']) ?></p>
    <?php endif; ?>
</div>

<div class="page-content">
    <?php if ($trip['description']): ?>
        <div class="card">
            <p class="text-sm"><?= nl2br(e($trip['description'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($dDay !== null): ?>
        <div class="card text-center">
            <div class="dday-label">
                <?php if ($dDay > 0): ?>
                    D-<?= $dDay ?>
                <?php elseif ($dDay === 0): ?>
                    D-Day!
                <?php else: ?>
                    여행 <?= abs($dDay) ?>일째
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 요약 카드들 -->
    <div class="summary-grid">
        <div class="card summary-card">
            <div class="summary-label">예산 대비 지출</div>
            <div class="summary-value"><?= $budgetPercent ?>%</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $budgetPercent ?>%; background: <?= $budgetPercent > 80 ? 'var(--color-coral)' : 'var(--color-primary)' ?>;"></div>
            </div>
            <div class="text-sm text-muted mt-8">
                <?= number_format($totalExpense) ?> / <?= number_format($totalBudget) ?>원
            </div>
        </div>

        <div class="card summary-card">
            <div class="summary-label">To-Do</div>
            <div class="summary-value"><?= $doneTodo ?> / <?= $totalTodo ?></div>
        </div>

        <div class="card summary-card">
            <div class="summary-label">체크리스트</div>
            <div class="summary-value"><?= $checkPercent ?>%</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $checkPercent ?>%;"></div>
            </div>
            <div class="text-sm text-muted mt-8">
                <?= $doneChecklist ?> / <?= $totalChecklist ?>개 완료
            </div>
        </div>
    </div>

    <!-- 추가 메뉴 -->
    <div class="card">
        <h3 class="card-title">더보기</h3>
        <div class="menu-list">
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settlement" class="menu-item">
                <span>정산</span>
                <span>&rsaquo;</span>
            </a>
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/members" class="menu-item">
                <span>멤버 목록</span>
                <span>&rsaquo;</span>
            </a>
        </div>
    </div>

    <div class="text-center mt-16">
        <p class="text-sm text-muted"><?= e($user['display_name']) ?>님으로 접속 중</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

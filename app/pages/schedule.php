<?php
/**
 * 일정표 페이지
 * /{trip_code}/{user_id}/schedule
 */
$currentPage = 'schedule';
$showNav = true;
$pageCss = 'schedule';
$pageJs = 'schedule';
$pageTitle = '일정표';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// 일정 데이터 로드
$stmt = $db->prepare('SELECT * FROM schedule_days WHERE trip_code = ? ORDER BY day_number ASC');
$stmt->execute([$tripCode]);
$days = $stmt->fetchAll();

// 각 일자의 세부 항목
$dayItems = [];
foreach ($days as $day) {
    $stmt = $db->prepare('SELECT * FROM schedule_items WHERE day_id = ? ORDER BY sort_order ASC, time ASC');
    $stmt->execute([$day['id']]);
    $dayItems[$day['id']] = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-row">
        <div class="page-header-left">
            <h1>일정표</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
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
    </div>
</div>

<div class="page-content">
    <div id="scheduleContainer">
        <?php if (empty($days)): ?>
            <div class="card text-center">
                <p class="text-muted mb-16">아직 일정이 없습니다.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($days as $day): ?>
            <div class="card schedule-day" data-day-id="<?= $day['id'] ?>">
                <div class="flex-between mb-8">
                    <h3 class="card-title" style="margin-bottom: 0;">
                        Day <?= $day['day_number'] ?>
                        <?php if ($day['date']): ?>
                            <span class="text-sm text-muted">(<?= e($day['date']) ?>)</span>
                        <?php endif; ?>
                    </h3>
                    <button class="btn btn-sm btn-secondary" onclick="editDay(<?= $day['id'] ?>)">편집</button>
                </div>
                <?php if ($day['title']): ?>
                    <p class="text-sm" style="font-weight: 500;"><?= e($day['title']) ?></p>
                <?php endif; ?>
                <?php if ($day['note']): ?>
                    <p class="text-sm text-muted mt-8"><?= nl2br(e($day['note'])) ?></p>
                <?php endif; ?>

                <div class="schedule-items mt-16">
                    <?php foreach ($dayItems[$day['id']] ?? [] as $item): ?>
                        <div class="schedule-item" data-item-id="<?= $item['id'] ?>">
                            <div class="item-time"><?= e($item['time'] ?? '') ?></div>
                            <div class="item-content">
                                <div><?= e($item['content']) ?></div>
                                <?php if ($item['location']): ?>
                                    <div class="text-sm text-muted"><?= e($item['location']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="item-actions">
                                <button class="btn btn-sm btn-secondary" onclick="editItem(<?= $item['id'] ?>)">편집</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem(<?= $item['id'] ?>, <?= $day['id'] ?>)">삭제</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn btn-sm btn-secondary btn-full mt-8" onclick="addItem(<?= $day['id'] ?>)">+ 항목 추가</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button class="btn btn-primary btn-full mt-16" onclick="addDay()">+ Day 추가</button>
</div>

<script>
    window.SCHEDULE_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

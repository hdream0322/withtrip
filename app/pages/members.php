<?php
/**
 * 멤버 목록 페이지 (일반 멤버 읽기 전용 뷰)
 * /{trip_code}/{user_id}/members
 */
$currentPage = 'members';
$showNav = false;
$pageCss = 'members';
$pageTitle = '멤버 목록';
$tripTitle = $trip['title'];

$db = getDB();
$members = getTripMembers($db, $tripCode);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>멤버 목록</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/" class="back-link">홈으로</a>
    </div>
</div>

<div class="page-content no-nav">
    <div class="card">
        <h3 class="card-title">참여 멤버 (<?= count($members) ?>명)</h3>

        <div class="members-list">
            <?php foreach ($members as $member): ?>
                <div class="member-item <?= $member['user_id'] === $userId ? 'member-me' : '' ?>">
                    <div class="member-avatar">
                        <?= mb_substr($member['display_name'], 0, 1) ?>
                    </div>
                    <div class="member-info">
                        <div class="member-display-name">
                            <?= e($member['display_name']) ?>
                            <?php if ($member['is_owner']): ?>
                                <span class="owner-badge">오너</span>
                            <?php endif; ?>
                            <?php if ($member['user_id'] === $userId): ?>
                                <span class="me-badge">나</span>
                            <?php endif; ?>
                        </div>
                        <div class="member-user-id">@<?= e($member['user_id']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="text-center mt-16">
        <p class="text-sm text-muted">멤버 추가/삭제는 여행 오너만 가능합니다.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

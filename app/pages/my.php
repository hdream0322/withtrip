<?php
/**
 * 오너 관리 대시보드 (/my)
 * Google OAuth 로그인 필수
 */
$pageTitle = '내 여행 관리';
$showNav = false;
$pageCss = 'my';
$pageJs = 'my';

$db = getDB();
$googleId = getOwnerGoogleId();

// 내 여행 목록
$stmt = $db->prepare('SELECT * FROM trips WHERE owner_google_id = ? ORDER BY created_at DESC');
$stmt->execute([$googleId]);
$trips = $stmt->fetchAll();

// 각 여행의 멤버 수 및 PIN 미설정 멤버 수
$tripData = [];
foreach ($trips as $trip) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE trip_code = ?');
    $stmt->execute([$trip['trip_code']]);
    $memberCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE trip_code = ? AND pin_hash IS NULL');
    $stmt->execute([$trip['trip_code']]);
    $noPinCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT user_id FROM users WHERE trip_code = ? AND is_owner = 1 LIMIT 1');
    $stmt->execute([$trip['trip_code']]);
    $ownerUserId = $stmt->fetchColumn();

    $tripData[] = [
        'trip' => $trip,
        'member_count' => $memberCount,
        'no_pin_count' => $noPinCount,
        'owner_user_id' => $ownerUserId ?: null,
    ];
}

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>내 여행</h1>
            <p class="subtitle"><?= e($_SESSION['owner_name'] ?? '') ?>님</p>
        </div>
        <a href="/auth/logout" class="btn btn-sm btn-secondary">로그아웃</a>
    </div>
</div>

<div class="page-content no-nav">
    <a href="/new" class="btn btn-primary btn-full mb-16">+ 새 여행 만들기</a>

    <?php if (empty($tripData)): ?>
        <div class="card text-center">
            <p class="text-muted">아직 생성한 여행이 없습니다.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($tripData as $td): ?>
        <?php $t = $td['trip']; ?>
        <div class="card trip-card"
             data-trip-code="<?= e($t['trip_code']) ?>"
             data-title="<?= e($t['title']) ?>"
             data-description="<?= e($t['description'] ?? '') ?>"
             data-destination="<?= e($t['destination'] ?? '') ?>"
             data-start-date="<?= e($t['start_date'] ?? '') ?>"
             data-end-date="<?= e($t['end_date'] ?? '') ?>">

            <div class="trip-card-top">
                <div class="trip-card-info">
                    <h3 class="trip-card-title"><?= e($t['title']) ?></h3>
                    <div class="trip-card-meta">
                        <?php if ($t['destination']): ?>
                            <span><?= e($t['destination']) ?></span>
                        <?php endif; ?>
                        <?php if ($t['start_date'] && $t['end_date']): ?>
                            <?php if ($t['destination']): ?><span class="meta-sep">·</span><?php endif; ?>
                            <span><?= e($t['start_date']) ?> ~ <?= e($t['end_date']) ?></span>
                        <?php endif; ?>
                        <?php if (!$t['destination'] && !$t['start_date']): ?>
                            <span class="text-muted"><?= e($t['trip_code']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- more_vert 드롭다운 -->
                <div class="card-menu">
                    <button class="card-menu-btn" onclick="toggleCardMenu('<?= e($t['trip_code']) ?>')">
                        <span class="material-icons">more_vert</span>
                    </button>
                    <div class="card-menu-dropdown hidden" id="menu-<?= e($t['trip_code']) ?>">
                        <?php if ($td['owner_user_id']): ?>
                        <a class="menu-item" href="/<?= e($t['trip_code']) ?>/<?= e($td['owner_user_id']) ?>/">
                            <span class="material-icons">open_in_new</span>플랜 열기
                        </a>
                        <?php endif; ?>
                        <button class="menu-item" onclick="openMembersModal('<?= e($t['trip_code']) ?>')">
                            <span class="material-icons">group</span>멤버 관리
                        </button>
                        <button class="menu-item" onclick="openEditModal('<?= e($t['trip_code']) ?>')">
                            <span class="material-icons">edit</span>수정
                        </button>
                        <button class="menu-item menu-item-danger" onclick="deleteTrip('<?= e($t['trip_code']) ?>', '<?= e($t['title']) ?>')">
                            <span class="material-icons">delete</span>삭제
                        </button>
                    </div>
                </div>
            </div>

            <div class="trip-card-bottom">
                <span class="trip-member-badge">
                    <span class="material-icons">group</span>
                    <?= $td['member_count'] ?>명
                </span>
                <?php if ($td['no_pin_count'] > 0): ?>
                    <span class="trip-pin-badge">PIN 미설정 <?= $td['no_pin_count'] ?>명</span>
                <?php endif; ?>
                <span class="trip-code-badge"><?= e($t['trip_code']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 수정 모달 -->
<div id="editModal" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header">
            <h3>여행 수정</h3>
            <button class="modal-close" onclick="closeModal('editModal')">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editTripCode">
            <div class="form-group">
                <label class="form-label">여행 제목 *</label>
                <input type="text" class="form-input" id="editTitle" placeholder="제목을 입력해주세요" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">목적지</label>
                <input type="text" class="form-input" id="editDestination" placeholder="예: 오키나와" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">여행 설명</label>
                <textarea class="form-textarea" id="editDescription" placeholder="여행에 대한 간단한 설명" rows="3" maxlength="500"></textarea>
            </div>
            <div style="display: flex; gap: 12px;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">시작일</label>
                    <input type="date" class="form-input" id="editStartDate">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">종료일</label>
                    <input type="date" class="form-input" id="editEndDate">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editModal')">취소</button>
            <button class="btn btn-primary" id="editSaveBtn" onclick="saveTrip()">저장</button>
        </div>
    </div>
</div>

<!-- 멤버 관리 모달 -->
<div id="membersModal" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header">
            <h3 id="membersModalTitle">멤버 관리</h3>
            <button class="modal-close" onclick="closeModal('membersModal')">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div id="modalMemberList"></div>
            <div class="flex gap-8 mt-16">
                <input type="text" class="form-input" placeholder="ID (영문+숫자)" id="modalNewUserId" style="flex: 1;" maxlength="30">
                <input type="text" class="form-input" placeholder="이름" id="modalNewDisplayName" style="flex: 1;" maxlength="50">
                <button class="btn btn-primary btn-sm" onclick="addMemberFromModal()">추가</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.MY_CONFIG = { csrfToken: '<?= e($csrfToken) ?>' };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

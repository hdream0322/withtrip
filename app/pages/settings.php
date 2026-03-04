<?php
/**
 * 설정 페이지
 * /{trip_code}/{user_id}/settings
 */
$currentPage = 'settings';
$showNav = true;
$pageCss = 'settings';
$pageJs = 'settings';
$pageTitle = '설정';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// 멤버 목록
$members = getTripMembers($db, $tripCode);

// 현재 사용자가 오너인지
$isOwner = (bool) $user['is_owner'];

require_once __DIR__ . '/../includes/header.php';
?>

<?php $pageHeaderTitle = '설정'; $pageHeaderMenu = false; require __DIR__ . '/../includes/page_header.php'; ?>

<div class="page-content">
    <!-- 여행 정보 -->
    <div class="card settings-section">
        <h3 class="settings-section-title">여행 정보</h3>
        <div class="settings-item">
            <span class="settings-item-label">제목</span>
            <span class="settings-item-value"><?= e($trip['title']) ?></span>
        </div>
        <?php if ($trip['description']): ?>
        <div class="settings-item">
            <span class="settings-item-label">설명</span>
            <span class="settings-item-value"><?= e($trip['description']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($trip['destination']): ?>
        <div class="settings-item">
            <span class="settings-item-label">목적지</span>
            <span class="settings-item-value"><?= e($trip['destination']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($trip['start_date'] && $trip['end_date']): ?>
        <div class="settings-item">
            <span class="settings-item-label">기간</span>
            <span class="settings-item-value"><?= e($trip['start_date']) ?> ~ <?= e($trip['end_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($isOwner): ?>
        <button class="btn btn-secondary btn-sm btn-full mt-8" onclick="Settings.openTripEditModal()">여행 정보 수정</button>
        <?php endif; ?>
    </div>

    <!-- 멤버 -->
    <div class="card settings-section">
        <div class="flex-between mb-8">
            <h3 class="settings-section-title" style="margin-bottom:0;">멤버 (<?= count($members) ?>명)</h3>
            <?php if ($isOwner): ?>
            <button class="btn btn-sm btn-primary" onclick="Settings.openAddMemberModal()">+ 추가</button>
            <?php endif; ?>
        </div>
        <div class="members-list">
            <?php foreach ($members as $member): ?>
                <div class="member-item <?= $member['user_id'] === $userId ? 'member-me' : '' ?>" data-user-id="<?= e($member['user_id']) ?>">
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
                    <div class="member-actions">
                        <button class="btn-icon" onclick="Settings.copyMemberUrl('<?= e($member['user_id']) ?>')" title="URL 복사">
                            <span class="material-icons" style="font-size:18px;">link</span>
                        </button>
                        <?php if ($isOwner && !$member['is_owner']): ?>
                        <button class="btn-icon danger" onclick="Settings.deleteMember('<?= e($member['user_id']) ?>', '<?= e(addslashes($member['display_name'])) ?>')" title="삭제">
                            <span class="material-icons" style="font-size:18px;">delete</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 보안 -->
    <div class="card settings-section">
        <h3 class="settings-section-title">보안</h3>
        <button class="btn btn-secondary btn-sm btn-full" onclick="Settings.openPinChangeModal()">PIN 변경</button>
    </div>

    <!-- 내 정보 -->
    <div class="card settings-section">
        <h3 class="settings-section-title">내 정보</h3>
        <div class="settings-item">
            <span class="settings-item-label">표시 이름</span>
            <span class="settings-item-value"><?= e($user['display_name']) ?></span>
        </div>
        <div class="settings-item">
            <span class="settings-item-label">ID</span>
            <span class="settings-item-value">@<?= e($userId) ?></span>
        </div>
    </div>
</div>

<!-- 여행 정보 수정 모달 (오너 전용) -->
<?php if ($isOwner): ?>
<div id="tripEditOverlay" class="modal-overlay hidden" onclick="Settings.closeTripEditModal()"></div>
<div id="tripEditSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title">여행 정보 수정</h3>
    <div class="form-group">
        <label class="form-label">제목 *</label>
        <input type="text" id="editTripTitle" class="form-input" value="<?= e($trip['title']) ?>" maxlength="100">
    </div>
    <div class="form-group">
        <label class="form-label">설명</label>
        <textarea id="editTripDescription" class="form-textarea"><?= e($trip['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label">목적지</label>
        <input type="text" id="editTripDestination" class="form-input" value="<?= e($trip['destination'] ?? '') ?>" maxlength="100">
    </div>
    <div class="form-group">
        <label class="form-label">시작일</label>
        <input type="date" id="editTripStartDate" class="form-input" value="<?= e($trip['start_date'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label class="form-label">종료일</label>
        <input type="date" id="editTripEndDate" class="form-input" value="<?= e($trip['end_date'] ?? '') ?>">
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="Settings.closeTripEditModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="Settings.saveTripEdit()" style="flex:1;">저장</button>
    </div>
</div>

<!-- 멤버 추가 모달 -->
<div id="addMemberOverlay" class="modal-overlay hidden" onclick="Settings.closeAddMemberModal()"></div>
<div id="addMemberSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title">멤버 추가</h3>
    <div class="form-group">
        <label class="form-label">ID * (영문, 숫자, _, -)</label>
        <input type="text" id="newMemberUserId" class="form-input" placeholder="예: jimin" maxlength="30">
    </div>
    <div class="form-group">
        <label class="form-label">표시 이름 *</label>
        <input type="text" id="newMemberDisplayName" class="form-input" placeholder="예: 지민" maxlength="50">
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="Settings.closeAddMemberModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="Settings.addMember()" style="flex:1;">추가</button>
    </div>
</div>
<?php endif; ?>

<!-- PIN 변경 모달 -->
<div id="pinChangeOverlay" class="modal-overlay hidden" onclick="Settings.closePinChangeModal()"></div>
<div id="pinChangeSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title">PIN 변경</h3>
    <div class="form-group">
        <label class="form-label">현재 PIN (6자리)</label>
        <input type="password" id="currentPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*">
    </div>
    <div class="form-group">
        <label class="form-label">새 PIN (6자리)</label>
        <input type="password" id="newPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*">
    </div>
    <div class="form-group">
        <label class="form-label">새 PIN 확인</label>
        <input type="password" id="confirmPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*">
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="Settings.closePinChangeModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="Settings.changePIN()" style="flex:1;">변경</button>
    </div>
</div>

<script>
    window.SETTINGS_CONFIG = {
        tripCode:  '<?= e($tripCode) ?>',
        userId:    '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        isOwner:   <?= $isOwner ? 'true' : 'false' ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

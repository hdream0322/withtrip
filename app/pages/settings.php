<?php
/**
 * 설정 페이지
 * /{trip_code}/{user_id}/settings
 */
$currentPage = 'settings';
$showNav = true;
$pageCss = 'settings';
$pageJs = 'settings';
$pageJsExtra = ['settings-rates'];
$pageTitle = '설정';
$tripTitle = $trip['title'];

$headExtra = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>';

$db = getDB();
$csrfToken = generateCsrfToken();

// 멤버 목록
$members = getTripMembers($db, $tripCode);

// 현재 사용자가 오너인지
$isOwner = (bool) $user['is_owner'];

// 오너가 아닌 멤버 목록 (PIN 초기화용)
$nonOwnerMembers = array_filter($members, fn($m) => !$m['is_owner']);

// 날짜 포맷
$dateRangeFormatted = formatDateRangeKorean($trip['start_date'] ?? null, $trip['end_date'] ?? null);

require_once __DIR__ . '/../includes/header.php';
?>

<?php $pageHeaderTitle = '설정'; $pageHeaderMenu = false; require __DIR__ . '/../includes/page_header.php'; ?>

<div class="page-content">
    <!-- 1. 내 정보 -->
    <div class="card settings-section">
        <h3 class="settings-section-title">내 정보</h3>
        <div class="settings-item">
            <span class="settings-item-label">표시 이름</span>
            <span class="settings-item-value settings-item-editable" onclick="Settings.openDisplayNameModal()">
                <span id="myDisplayName"><?= e($user['display_name']) ?></span>
                <span class="material-icons settings-edit-icon">edit</span>
            </span>
        </div>
        <div class="settings-item">
            <span class="settings-item-label">ID</span>
            <span class="settings-item-value">@<?= e($userId) ?></span>
        </div>
    </div>

    <!-- 2. 보안 -->
    <div class="card settings-section">
        <h3 class="settings-section-title">보안</h3>
        <button class="btn btn-secondary btn-sm btn-full" onclick="Settings.openPinChangeModal()">PIN 변경</button>
        <?php if ($isOwner && count($nonOwnerMembers) > 0): ?>
        <div class="pin-reset-section">
            <p class="pin-reset-title">멤버 PIN 초기화</p>
            <?php foreach ($nonOwnerMembers as $member): ?>
            <div class="pin-reset-item">
                <span class="pin-reset-name"><?= e($member['display_name']) ?> <span class="pin-reset-id">@<?= e($member['user_id']) ?></span></span>
                <button class="btn btn-secondary btn-xs" onclick="Settings.resetMemberPin('<?= e($member['user_id']) ?>', '<?= e(addslashes($member['display_name'])) ?>')">초기화</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 3. 멤버 -->
    <div class="card settings-section">
        <div class="flex-between mb-8">
            <h3 class="settings-section-title" style="margin-bottom:0;">멤버 (<?= count($members) ?>명)</h3>
            <div class="settings-member-actions">
                <?php if ($isOwner): ?>
                <button class="btn btn-sm btn-secondary" onclick="Settings.shareAllMemberLinks()" title="일괄 링크 공유">
                    <span class="material-icons" style="font-size:15px;vertical-align:middle;">share</span>
                </button>
                <button class="btn btn-sm btn-primary" onclick="Settings.openAddMemberModal()">+ 추가</button>
                <?php endif; ?>
            </div>
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
                        <button class="btn-icon" onclick="Settings.showQr('<?= e($member['user_id']) ?>', '<?= e(addslashes($member['display_name'])) ?>')" title="QR 코드">
                            <span class="material-icons" style="font-size:18px;">qr_code_2</span>
                        </button>
                        <button class="btn-icon" onclick="Settings.shareMemberUrl('<?= e($member['user_id']) ?>', '<?= e(addslashes($member['display_name'])) ?>')" title="링크 공유">
                            <span class="material-icons" style="font-size:18px;">share</span>
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

    <!-- 4. 여행 정보 -->
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
        <div class="settings-item">
            <span class="settings-item-label">기간</span>
            <span class="settings-item-value"><?= e($dateRangeFormatted) ?></span>
        </div>
        <div class="settings-item">
            <span class="settings-item-label">여행 코드</span>
            <span class="settings-item-value">
                <span class="trip-code"><?= e($tripCode) ?></span>
                <button class="btn-icon-inline" onclick="Settings.showTripQr()" title="QR 코드">
                    <span class="material-icons" style="font-size:16px;">qr_code_2</span>
                </button>
                <button class="btn-icon-inline" onclick="Settings.copyTripCode()" title="코드 복사">
                    <span class="material-icons" style="font-size:16px;">content_copy</span>
                </button>
            </span>
        </div>
        <?php if ($isOwner): ?>
        <button class="btn btn-secondary btn-sm btn-full mt-8" onclick="Settings.openTripEditModal()">여행 정보 수정</button>
        <?php endif; ?>
    </div>

    <!-- 5. 환율 설정 -->
    <div class="card settings-section">
        <div class="flex-between mb-4">
            <h3 class="settings-section-title" style="margin-bottom:0;">환율 설정</h3>
            <button class="btn btn-secondary btn-sm" id="btnFetchLiveRate" onclick="Settings.fetchAndSaveRates()">
                <span class="material-icons" style="font-size:15px;vertical-align:middle;">sync</span>
                환율 갱신
            </button>
        </div>
        <p class="text-xs text-muted mb-12" id="rateSourceLabel">KRW 환산에 사용됩니다. 1시간마다 자동 갱신됩니다.</p>
        <div id="rateTableWrap">
            <div class="text-center text-muted text-sm"><div class="spinner"></div></div>
        </div>
    </div>

    <!-- 6. 웹앱 설치 -->
    <div class="card settings-section" id="installSection" style="display:none;">
        <h3 class="settings-section-title">웹앱으로 설치</h3>
        <p class="text-xs text-muted mb-12">홈 화면에 추가하면 앱처럼 빠르게 접근할 수 있습니다.</p>
        <button class="btn btn-primary btn-full settings-install-btn" id="btnInstallApp" onclick="Settings.installApp()">
            <span class="material-icons">install_mobile</span> 홈 화면에 추가
        </button>
    </div>

    <!-- iOS 설치 안내 (Safari) -->
    <div class="card settings-section" id="installGuideIos" style="display:none;">
        <h3 class="settings-section-title">웹앱으로 설치</h3>
        <p class="text-xs text-muted mb-8">홈 화면에 추가하면 앱처럼 빠르게 접근할 수 있습니다.</p>
        <div class="install-guide-steps">
            <div class="install-guide-step">
                <span class="install-step-num">1</span>
                <span>하단 공유 버튼 <span class="material-icons" style="font-size:16px;vertical-align:middle;color:var(--color-primary);">ios_share</span> 을 탭하세요</span>
            </div>
            <div class="install-guide-step">
                <span class="install-step-num">2</span>
                <span><strong>홈 화면에 추가</strong>를 선택하세요</span>
            </div>
        </div>
    </div>

    <!-- 이미 설치됨 -->
    <div class="card settings-section" id="installDone" style="display:none;">
        <h3 class="settings-section-title">웹앱으로 설치</h3>
        <div class="install-done-msg">
            <span class="material-icons install-done-icon">check_circle</span>
            <span>이미 앱으로 설치되어 있습니다</span>
        </div>
    </div>

    <!-- 7. 로그아웃 -->
    <div class="card settings-section">
        <button class="btn btn-secondary btn-full settings-logout-btn" onclick="Settings.logout()">
            <span class="material-icons">logout</span> 로그아웃
        </button>
    </div>

    <!-- 8. 앱 정보 -->
    <div class="settings-app-info">
        WithPlan v<?= CSS_VERSION ?>
    </div>
</div>

<!-- QR 코드 모달 -->
<div id="qrOverlay" class="modal-overlay hidden" onclick="Settings.closeQrModal()"></div>
<div id="qrSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="qrTitle"></h3>
    <div class="qr-container">
        <div id="qrCanvas"></div>
        <p class="qr-url" id="qrUrl"></p>
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="Settings.closeQrModal()" style="flex:1;">닫기</button>
        <button class="btn btn-primary" onclick="Settings.copyQrUrl()" style="flex:1;">URL 복사</button>
    </div>
</div>

<!-- 표시 이름 편집 모달 -->
<div id="displayNameOverlay" class="modal-overlay hidden" onclick="Settings.closeDisplayNameModal()"></div>
<div id="displayNameSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title">표시 이름 변경</h3>
    <div class="form-group">
        <label class="form-label">표시 이름</label>
        <input type="text" id="editDisplayName" class="form-input" maxlength="50">
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="Settings.closeDisplayNameModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="Settings.saveDisplayName()" style="flex:1;">저장</button>
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
    <form onsubmit="Settings.changePIN(); return false;">
        <input type="hidden" id="pinUsername" value="<?= e($userId) ?>" autocomplete="username">
        <div class="form-group">
            <label class="form-label">현재 PIN (6자리)</label>
            <input type="password" id="currentPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="current-password">
        </div>
        <div class="form-group">
            <label class="form-label">새 PIN (6자리)</label>
            <input type="password" id="newPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label class="form-label">새 PIN 확인</label>
            <input type="password" id="confirmPin" class="form-input" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password">
        </div>
        <div class="flex gap-8">
            <button type="button" class="btn btn-secondary" onclick="Settings.closePinChangeModal()" style="flex:1;">취소</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">변경</button>
        </div>
    </form>
</div>

<script>
    window.SETTINGS_CONFIG = {
        tripCode:    '<?= e($tripCode) ?>',
        userId:      '<?= e($userId) ?>',
        csrfToken:   '<?= e($csrfToken) ?>',
        isOwner:     <?= $isOwner ? 'true' : 'false' ?>,
        displayName: '<?= e(addslashes($user['display_name'])) ?>',
        tripTitle:   '<?= e(addslashes($trip['title'])) ?>',
        members:     <?= json_encode(array_map(function($m) { return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']]; }, $members), JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

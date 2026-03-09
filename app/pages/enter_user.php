<?php
/**
 * user_id 입력 페이지
 * /{trip_code}/ 접근 시 표시
 */
$pageTitle = $trip['title'] ?? 'WithPlan';
$showNav = false;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pin-container">
    <h1 class="pin-title"><?= e($trip['title']) ?></h1>
    <p class="pin-subtitle">참여자 ID를 입력해주세요</p>

    <form id="userIdForm" onsubmit="return false;" style="width: 100%; max-width: 320px;">
        <div class="form-group">
            <input type="text" class="form-input text-center" id="userIdInput"
                   placeholder="예: dad, mom, jimin" maxlength="30"
                   pattern="[a-zA-Z0-9_-]+" autocomplete="off" autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="enterBtn">입장하기</button>
    </form>
</div>

<script>
document.getElementById('userIdForm').addEventListener('submit', function() {
    const userId = document.getElementById('userIdInput').value.trim();
    if (/^[a-zA-Z0-9_-]+$/.test(userId)) {
        window.location.href = '/<?= e($tripCode) ?>/' + userId + '/';
    } else {
        WP.toast('영문, 숫자, _, -만 사용할 수 있습니다.', 'error');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

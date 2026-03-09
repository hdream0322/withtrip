<?php
/**
 * 여행 생성 페이지 (/new)
 * Google OAuth 로그인 필수
 */
$pageTitle = '새 여행 만들기';
$showNav = false;
$pageCss = 'new';
require_once __DIR__ . '/../includes/header.php';

$csrfToken = generateCsrfToken();
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>새 여행 만들기</h1>
            <p class="subtitle"><?= e($_SESSION['owner_name'] ?? '') ?>님의 새로운 여행</p>
        </div>
        <a href="/my" class="back-link">← 내 여행</a>
    </div>
</div>

<div class="page-content no-nav">
    <form id="newTripForm" onsubmit="return false;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-group">
            <label class="form-label">여행 제목 *</label>
            <input type="text" class="form-input" name="title" placeholder="예: 2026 괌 가족여행" required maxlength="100">
        </div>

        <div class="form-group">
            <label class="form-label">상세 설명</label>
            <textarea class="form-textarea" name="description" placeholder="여행 상세 정보를 입력해주세요" maxlength="1000"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">목적지</label>
            <input type="text" class="form-input" name="destination" placeholder="예: 괌" maxlength="100">
        </div>

        <div class="flex gap-8">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">출발일</label>
                <input type="date" class="form-input" name="start_date">
            </div>
            <div class="form-group" style="flex: 1;">
                <label class="form-label">도착일</label>
                <input type="date" class="form-input" name="end_date">
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid var(--color-border); margin: 24px 0;">

        <div class="form-group">
            <label class="form-label">내 참여자 ID *</label>
            <input type="text" class="form-input" name="owner_user_id" placeholder="예: dad" required maxlength="30" pattern="[a-zA-Z0-9_-]+">
            <p class="text-sm text-muted mt-8">영문, 숫자, _, -만 사용 가능</p>
        </div>

        <div class="form-group">
            <label class="form-label">내 표시 이름 *</label>
            <input type="text" class="form-input" name="owner_display_name" placeholder="예: 아빠" required maxlength="50">
        </div>

        <button type="submit" class="btn btn-primary btn-full mt-16" id="createBtn">여행 만들기</button>
    </form>

    <!-- 생성 완료 모달 -->
    <div id="successModal" class="hidden">
        <div class="card mt-24">
            <h2 class="card-title">여행이 생성되었습니다!</h2>
            <p class="text-sm text-muted mb-16">아래 링크를 멤버들에게 공유해주세요.</p>
            <div class="flex gap-8">
                <input type="text" class="form-input" id="tripUrl" readonly style="flex: 1;">
                <button class="btn btn-primary btn-sm" onclick="WP.copyToClipboard(document.getElementById('tripUrl').value)">복사</button>
            </div>
            <a href="#" id="goToTrip" class="btn btn-primary btn-full mt-16">여행으로 이동</a>
        </div>
    </div>
</div>

<script>
document.getElementById('newTripForm').addEventListener('submit', async function() {
    const form = this;
    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    btn.textContent = '생성 중...';

    const formData = new FormData(form);
    const body = Object.fromEntries(formData);

    try {
        const data = await WP.post('/api/trips', body);

        if (data.success) {
            form.classList.add('hidden');
            const modal = document.getElementById('successModal');
            modal.classList.remove('hidden');

            const tripUrl = location.origin + '/' + data.data.trip_code;
            document.getElementById('tripUrl').value = tripUrl;
            document.getElementById('goToTrip').href = '/' + data.data.trip_code + '/' + data.data.user_id + '/';
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '여행 만들기';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

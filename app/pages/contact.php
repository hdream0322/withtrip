<?php
/**
 * 문의 및 제안 페이지 (/contact)
 * 로그인 불필요
 */
$pageTitle = '문의 및 제안';
$showNav = false;
$pageCss = 'contact';
$pageJs = 'contact';

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>문의 및 제안</h1>
            <p class="subtitle">WithPlan에 대한 의견을 보내주세요</p>
        </div>
        <a href="/" class="back-link">← 홈</a>
    </div>
</div>

<div class="page-content no-nav">
    <form id="contactForm" onsubmit="return false;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-group">
            <label class="form-label">이름 *</label>
            <input type="text" class="form-input" name="name" required maxlength="100" placeholder="이름을 입력해주세요">
        </div>

        <div class="form-group">
            <label class="form-label">이메일 *</label>
            <input type="email" class="form-input" name="email" required maxlength="200" placeholder="답변 받으실 이메일">
        </div>

        <div class="form-group">
            <label class="form-label">문의 유형 *</label>
            <select class="form-select" name="category" required>
                <option value="">선택해주세요</option>
                <option value="general">일반 문의</option>
                <option value="bug">버그 신고</option>
                <option value="feature">기능 제안</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">내용 *</label>
            <textarea class="form-textarea" name="content" required maxlength="2000" placeholder="내용을 입력해주세요" rows="6"></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-full" id="submitBtn">보내기</button>
    </form>

    <div id="successMessage" class="hidden">
        <div class="card text-center mt-24">
            <h2 class="card-title">감사합니다!</h2>
            <p class="text-sm text-muted mb-16">문의가 정상적으로 접수되었습니다.<br>빠른 시일 내에 답변 드리겠습니다.</p>
            <a href="/" class="btn btn-primary">홈으로 돌아가기</a>
        </div>
    </div>
</div>

<script>
document.getElementById('contactForm').addEventListener('submit', async function() {
    const form = this;
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '전송 중...';

    const formData = new FormData(form);
    const body = Object.fromEntries(formData);

    try {
        const data = await WP.post('/api/contact', body);

        if (data.success) {
            form.classList.add('hidden');
            document.getElementById('successMessage').classList.remove('hidden');
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '보내기';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

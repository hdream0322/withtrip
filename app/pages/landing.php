<?php
/**
 * 랜딩 페이지 (/)
 */
$pageTitle = 'WithPlan - 함께 만드는 여행 계획';
$showNav = false;
$pageCss = 'landing';

$isLoggedIn = isOwnerLoggedIn();
$errorMsg = $_GET['error'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="hero-inner">
        <p class="hero-eyebrow">여행 플래너</p>
        <h1 class="hero-title">WithPlan</h1>
        <p class="hero-desc">일정부터 정산까지, 함께 준비하는 여행</p>

        <?php if ($errorMsg): ?>
            <div class="hero-error">
                <?php
                $msgs = [
                    'state_mismatch' => '로그인 세션이 만료됐습니다. 다시 시도해주세요.',
                    'auth_failed'    => '로그인에 실패했습니다. 다시 시도해주세요.',
                ];
                echo e($msgs[$errorMsg] ?? '오류가 발생했습니다.');
                ?>
            </div>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <a href="/my" class="btn-hero">내 여행 관리 →</a>
        <?php else: ?>
            <a href="/auth/google" class="btn-hero">
                <svg width="16" height="16" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                </svg>
                Google로 시작하기
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- 기능 소개 (스크롤 시 등장) -->
<section class="features">
    <div class="feature-item reveal">
        <div class="feature-num">01</div>
        <div class="feature-body">
            <h3>일정 관리</h3>
            <p>Day별 타임라인으로 여행 일정을 한눈에 정리하고 멤버 전원과 실시간 공유</p>
        </div>
    </div>
    <div class="feature-item reveal">
        <div class="feature-num">02</div>
        <div class="feature-body">
            <h3>예산 & 지출 관리</h3>
            <p>카테고리별 예산을 세우고 실제 지출을 기록해 초과 여부를 즉시 파악</p>
        </div>
    </div>
    <div class="feature-item reveal">
        <div class="feature-num">03</div>
        <div class="feature-body">
            <h3>더치페이 정산</h3>
            <p>누가 얼마를 냈는지 자동 계산, 최소 송금 횟수로 깔끔하게 정산</p>
        </div>
    </div>
    <div class="feature-item reveal">
        <div class="feature-num">04</div>
        <div class="feature-body">
            <h3>체크리스트 & 할일</h3>
            <p>준비물 체크리스트와 예약 할일 목록으로 빠뜨리는 것 없이 챙기기</p>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="landing-footer">
    <span class="footer-brand">WithPlan</span>
    <div class="footer-links">
        <a href="/contact" class="footer-link">문의</a>
        <a href="/terms" class="footer-link">이용약관</a>
        <a href="/privacy" class="footer-link">개인정보처리방침</a>
    </div>
</footer>

<!-- 하단 플로팅 바: 어느 화면에서나 노출 -->
<div class="landing-float">
    <?php if ($isLoggedIn): ?>
        <a href="/my" class="float-auth">내 여행 →</a>
    <?php else: ?>
        <a href="/auth/google" class="float-auth">
            <svg width="14" height="14" viewBox="0 0 48 48">
                <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
            </svg>
            Google 로그인
        </a>
    <?php endif; ?>

    <div class="float-sep"></div>

    <form class="float-code" id="floatCodeForm" onsubmit="return false;">
        <input type="text" class="float-code-input" id="floatCodeInput"
               placeholder="여행 코드 입력" maxlength="8"
               autocomplete="off" autocorrect="off" spellcheck="false">
        <button type="submit" class="float-code-btn">입장</button>
        <span id="floatCodeError" class="float-code-error hidden"></span>
    </form>
</div>

<script>
// 스크롤 reveal 애니메이션
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('revealed');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.15 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// 여행 코드 입장 공통 함수
async function submitTripCode(code, errorEl) {
    const trimmed = code.trim().toLowerCase();

    errorEl.classList.add('hidden');

    if (!/^[a-f0-9]{8}$/.test(trimmed)) {
        errorEl.textContent = '8자리 여행 코드를 입력해주세요.';
        errorEl.classList.remove('hidden');
        return;
    }

    try {
        const resp = await fetch('/api/trips/check?trip_code=' + trimmed);
        const data = await resp.json();
        if (data.success) {
            window.location.href = '/' + trimmed;
        } else {
            errorEl.textContent = '존재하지 않는 여행 코드입니다.';
            errorEl.classList.remove('hidden');
        }
    } catch {
        window.location.href = '/' + trimmed;
    }
}

// 플로팅 바 여행 코드 폼
document.getElementById('floatCodeForm').addEventListener('submit', function () {
    const input = document.getElementById('floatCodeInput');
    const errorEl = document.getElementById('floatCodeError');
    submitTripCode(input.value, errorEl);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

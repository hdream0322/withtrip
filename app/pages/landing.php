<?php
/**
 * 랜딩 페이지 (/) — 전면 재설계 v4.0
 */
$pageTitle = 'WithPlan - 함께 만드는 여행 계획';
$showNav = false;
$pageCss = 'landing';
$pageJs = 'landing';
$bodyClass = 'is-landing';
$headExtra = '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>'
           . '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>'
           . '<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>';

$isLoggedIn = isOwnerLoggedIn();
$errorMsg = $_GET['error'] ?? '';

// 생성된 일정 수 (DB)
try {
    $tripCount = (int) getDB()->query('SELECT COUNT(*) FROM schedule_items')->fetchColumn();
} catch (\Throwable $e) {
    $tripCount = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php
// Google OAuth SVG 아이콘 (재사용)
$googleSvg = '<svg width="16" height="16" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>';
$googleSvgSm = '<svg width="14" height="14" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>';
?>

<!-- ========== 1. Hero ========== -->
<section class="landing-hero" id="heroSection">
    <div class="hero-particles" aria-hidden="true">
        <span class="particle p1">✈️</span>
        <span class="particle p2">🌴</span>
        <span class="particle p3">🧳</span>
        <span class="particle p4">🗺️</span>
        <span class="particle p5">🌊</span>
        <span class="particle p6">🏝️</span>
        <span class="particle p7">🍹</span>
        <span class="particle p8">⛵</span>
        <span class="particle p9">🏔️</span>
        <span class="particle p10">🌸</span>
        <span class="particle p11">🎒</span>
        <span class="particle p12">🌅</span>
        <span class="particle p13">🌺</span>
    </div>

    <div class="section-inner hero-content">
        <p class="hero-eyebrow gsap-hero">Travel Planner</p>
        <h1 class="hero-title">
            <span class="hero-title-line gsap-hero">함께 만드는</span>
            <span class="hero-title-line hero-title-grad gsap-hero">WithPlan</span>
        </h1>
        <p class="hero-desc gsap-hero">설치 없이, 링크 하나로 시작하세요.<br>계획·기록·정산을 모두가 함께.</p>

        <?php if ($errorMsg): ?>
        <div class="hero-error gsap-hero">
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
        <a href="/my" class="btn-hero gsap-hero">내 여행 관리 →</a>
        <?php else: ?>
        <a href="/auth/google" class="btn-hero gsap-hero"><?= $googleSvg ?> Google로 시작하기</a>
        <?php endif; ?>
    </div>

    <div class="hero-scroll-hint gsap-hero" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 13l5 5 5-5M7 6l5 5 5-5"/></svg>
    </div>
</section>

<!-- ========== 2. 사용 흐름 ========== -->
<section class="landing-flow" id="flowSection">
    <div class="section-inner">
        <h2 class="section-title">3단계로 시작하기</h2>

        <div class="flow-step gsap-flow">
            <div class="flow-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
            </div>
            <div class="flow-text">
                <strong>여행 만들기</strong>
                <span>제목, 날짜, 목적지를 입력하고 새 여행 플랜을 생성하세요</span>
            </div>
        </div>
        <div class="flow-arrow" aria-hidden="true">↓</div>

        <div class="flow-step gsap-flow">
            <div class="flow-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            </div>
            <div class="flow-text">
                <strong>멤버 초대</strong>
                <span>고유 링크를 공유해 여행 멤버를 초대하세요</span>
            </div>
        </div>
        <div class="flow-arrow" aria-hidden="true">↓</div>

        <div class="flow-step gsap-flow">
            <div class="flow-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div class="flow-text">
                <strong>함께 준비</strong>
                <span>일정, 지출, 체크리스트를 실시간으로 함께 관리하세요</span>
            </div>
        </div>
    </div>
</section>

<!-- ========== 3. 기능 쇼케이스 ========== -->
<section class="landing-features" id="featuresSection">
    <div class="section-inner">
        <h2 class="section-title">주요 기능</h2>

        <!-- 기능 1: 일정 관리 -->
        <div class="feature-card gsap-feat">
            <div class="feature-phone">
                <svg viewBox="0 0 260 520" xmlns="http://www.w3.org/2000/svg">
                    <!-- 폰 프레임 -->
                    <rect x="0" y="0" width="260" height="520" rx="28" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2"/>
                    <rect x="80" y="8" width="100" height="24" rx="12" fill="#e2e8f0"/>
                    <!-- 날짜 칩바 -->
                    <rect x="16" y="48" width="52" height="28" rx="14" fill="#0891b2"/>
                    <text x="42" y="66" fill="#fff" font-size="11" text-anchor="middle" font-weight="600">Day 1</text>
                    <rect x="76" y="48" width="52" height="28" rx="14" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="102" y="66" fill="#64748b" font-size="11" text-anchor="middle">Day 2</text>
                    <rect x="136" y="48" width="52" height="28" rx="14" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="162" y="66" fill="#64748b" font-size="11" text-anchor="middle">Day 3</text>
                    <!-- 타임라인 -->
                    <line x1="36" y1="100" x2="36" y2="380" stroke="#e2e8f0" stroke-width="2"/>
                    <!-- 항목 1 - 식사 -->
                    <circle cx="36" cy="110" r="6" fill="#f97316"/>
                    <rect x="56" y="96" width="180" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="68" y="115" fill="#64748b" font-size="10">09:00</text>
                    <text x="68" y="132" fill="#1e293b" font-size="12" font-weight="600">호텔 조식</text>
                    <text x="68" y="146" fill="#94a3b8" font-size="10">🍳 식사</text>
                    <!-- 항목 2 - 관광 -->
                    <circle cx="36" cy="180" r="6" fill="#0891b2"/>
                    <rect x="56" y="166" width="180" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="68" y="185" fill="#64748b" font-size="10">10:30</text>
                    <text x="68" y="202" fill="#1e293b" font-size="12" font-weight="600">다이아몬드헤드 등반</text>
                    <text x="68" y="216" fill="#94a3b8" font-size="10">🏔️ 관광</text>
                    <!-- 항목 3 - 이동 -->
                    <circle cx="36" cy="250" r="6" fill="#8b5cf6"/>
                    <rect x="56" y="236" width="180" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="68" y="255" fill="#64748b" font-size="10">13:00</text>
                    <text x="68" y="272" fill="#1e293b" font-size="12" font-weight="600">와이키키 비치 이동</text>
                    <text x="68" y="286" fill="#94a3b8" font-size="10">🚗 이동</text>
                    <!-- 항목 4 - 쇼핑 -->
                    <circle cx="36" cy="320" r="6" fill="#ec4899"/>
                    <rect x="56" y="306" width="180" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="68" y="325" fill="#64748b" font-size="10">15:30</text>
                    <text x="68" y="342" fill="#1e293b" font-size="12" font-weight="600">알라모아나 센터</text>
                    <text x="68" y="356" fill="#94a3b8" font-size="10">🛍️ 쇼핑</text>
                    <!-- FAB -->
                    <circle cx="220" cy="440" r="24" fill="#0891b2"/>
                    <text x="220" y="446" fill="#fff" font-size="22" text-anchor="middle">+</text>
                </svg>
            </div>
            <div class="feature-info">
                <span class="feature-badge">일정 관리</span>
                <h3>Day별 타임라인으로<br>깔끔하게 정리</h3>
                <ul class="feature-checks">
                    <li>날짜별 일정 카드 구성</li>
                    <li>카테고리별 색상 구분</li>
                    <li>Google Maps 연동</li>
                </ul>
            </div>
        </div>

        <!-- 기능 2: 지출 추적 -->
        <div class="feature-card gsap-feat">
            <div class="feature-phone">
                <svg viewBox="0 0 260 520" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0" y="0" width="260" height="520" rx="28" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2"/>
                    <rect x="80" y="8" width="100" height="24" rx="12" fill="#e2e8f0"/>
                    <!-- 탭바 -->
                    <rect x="16" y="48" width="112" height="32" rx="8" fill="#0891b2"/>
                    <text x="72" y="69" fill="#fff" font-size="12" text-anchor="middle" font-weight="600">지출 내역</text>
                    <rect x="132" y="48" width="112" height="32" rx="8" fill="#f1f5f9"/>
                    <text x="188" y="69" fill="#64748b" font-size="12" text-anchor="middle">정산</text>
                    <!-- 지출 항목 1 -->
                    <rect x="16" y="100" width="228" height="68" rx="12" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="28" y="122" fill="#1e293b" font-size="13" font-weight="600">와이키키 씨푸드</text>
                    <text x="28" y="140" fill="#94a3b8" font-size="10">3/1 · 식비 · 아빠</text>
                    <text x="220" y="122" fill="#0891b2" font-size="14" font-weight="700" text-anchor="end">$85.50</text>
                    <rect x="178" y="130" width="40" height="18" rx="9" fill="#dbeafe"/>
                    <text x="198" y="143" fill="#3b82f6" font-size="9" text-anchor="middle">USD</text>
                    <!-- 지출 항목 2 -->
                    <rect x="16" y="180" width="228" height="68" rx="12" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="28" y="202" fill="#1e293b" font-size="13" font-weight="600">택시 (호텔→공항)</text>
                    <text x="28" y="220" fill="#94a3b8" font-size="10">3/1 · 교통 · 엄마</text>
                    <text x="220" y="202" fill="#0891b2" font-size="14" font-weight="700" text-anchor="end">$32.00</text>
                    <rect x="178" y="210" width="40" height="18" rx="9" fill="#dbeafe"/>
                    <text x="198" y="223" fill="#3b82f6" font-size="9" text-anchor="middle">USD</text>
                    <!-- 지출 항목 3 -->
                    <rect x="16" y="260" width="228" height="68" rx="12" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="28" y="282" fill="#1e293b" font-size="13" font-weight="600">편의점 간식</text>
                    <text x="28" y="300" fill="#94a3b8" font-size="10">3/2 · 식비 · 지민</text>
                    <text x="220" y="282" fill="#0891b2" font-size="14" font-weight="700" text-anchor="end">₩15,000</text>
                    <rect x="178" y="290" width="40" height="18" rx="9" fill="#fef3c7"/>
                    <text x="198" y="303" fill="#d97706" font-size="9" text-anchor="middle">KRW</text>
                    <!-- 2 FABs -->
                    <circle cx="188" cy="440" r="22" fill="#10b981"/>
                    <text x="188" y="446" fill="#fff" font-size="18" text-anchor="middle">+</text>
                    <circle cx="224" cy="410" r="22" fill="#f43f5e"/>
                    <text x="224" y="416" fill="#fff" font-size="18" text-anchor="middle">−</text>
                </svg>
            </div>
            <div class="feature-info">
                <span class="feature-badge badge-coral">지출 추적</span>
                <h3>다중 통화 지출을<br>한 눈에 관리</h3>
                <ul class="feature-checks">
                    <li>KRW / USD 등 10개 통화</li>
                    <li>수입 · 지출 분리 기록</li>
                    <li>카드 / 현금 구분</li>
                    <li>날짜 · 시간별 정렬</li>
                </ul>
            </div>
        </div>

        <!-- 기능 3: 통합 정산 -->
        <div class="feature-card gsap-feat">
            <div class="feature-phone">
                <svg viewBox="0 0 260 520" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0" y="0" width="260" height="520" rx="28" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2"/>
                    <rect x="80" y="8" width="100" height="24" rx="12" fill="#e2e8f0"/>
                    <!-- 탭바 (정산 활성) -->
                    <rect x="16" y="48" width="112" height="32" rx="8" fill="#f1f5f9"/>
                    <text x="72" y="69" fill="#64748b" font-size="12" text-anchor="middle">지출 내역</text>
                    <rect x="132" y="48" width="112" height="32" rx="8" fill="#0891b2"/>
                    <text x="188" y="69" fill="#fff" font-size="12" text-anchor="middle" font-weight="600">정산</text>
                    <!-- 멤버별 잔액 바 -->
                    <text x="16" y="110" fill="#64748b" font-size="11" font-weight="600">멤버별 잔액</text>
                    <!-- 아빠: +45,000 -->
                    <text x="16" y="138" fill="#1e293b" font-size="12">아빠</text>
                    <rect x="60" y="126" width="120" height="18" rx="4" fill="#dcfce7"/>
                    <text x="184" y="139" fill="#10b981" font-size="11" font-weight="600">+₩45,000</text>
                    <!-- 엄마: -15,000 -->
                    <text x="16" y="168" fill="#1e293b" font-size="12">엄마</text>
                    <rect x="60" y="156" width="40" height="18" rx="4" fill="#fee2e2"/>
                    <text x="184" y="169" fill="#ef4444" font-size="11" font-weight="600">−₩15,000</text>
                    <!-- 지민: -30,000 -->
                    <text x="16" y="198" fill="#1e293b" font-size="12">지민</text>
                    <rect x="60" y="186" width="80" height="18" rx="4" fill="#fee2e2"/>
                    <text x="184" y="199" fill="#ef4444" font-size="11" font-weight="600">−₩30,000</text>
                    <!-- 구분선 -->
                    <line x1="16" y1="224" x2="244" y2="224" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="16" y="248" fill="#64748b" font-size="11" font-weight="600">이체 내역</text>
                    <!-- 이체 카드 1 -->
                    <rect x="16" y="262" width="228" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="28" y="284" fill="#1e293b" font-size="12" font-weight="600">엄마 → 아빠</text>
                    <text x="220" y="284" fill="#0891b2" font-size="13" font-weight="700" text-anchor="end">₩15,000</text>
                    <rect x="28" y="296" width="14" height="14" rx="3" fill="#fff" stroke="#e2e8f0" stroke-width="1.5"/>
                    <text x="48" y="308" fill="#94a3b8" font-size="10">완료 체크</text>
                    <!-- 이체 카드 2 -->
                    <rect x="16" y="330" width="228" height="56" rx="10" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <text x="28" y="352" fill="#1e293b" font-size="12" font-weight="600">지민 → 아빠</text>
                    <text x="220" y="352" fill="#0891b2" font-size="13" font-weight="700" text-anchor="end">₩30,000</text>
                    <rect x="28" y="364" width="14" height="14" rx="3" fill="#10b981" stroke="#10b981" stroke-width="1.5"/>
                    <path d="M31 371 l3 3 l5 -5" fill="none" stroke="#fff" stroke-width="1.5"/>
                    <text x="48" y="376" fill="#10b981" font-size="10" text-decoration="line-through">완료</text>
                </svg>
            </div>
            <div class="feature-info">
                <span class="feature-badge badge-green">통합 정산</span>
                <h3>최소 이체로<br>깔끔하게 정산</h3>
                <ul class="feature-checks">
                    <li>자동 잔액 계산</li>
                    <li>최소 이체 횟수 최적화</li>
                    <li>다중 통화 통합 정산</li>
                    <li>완료 체크 기능</li>
                </ul>
            </div>
        </div>

        <!-- 기능 4: 준비물 체크 -->
        <div class="feature-card gsap-feat">
            <div class="feature-phone">
                <svg viewBox="0 0 260 520" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0" y="0" width="260" height="520" rx="28" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2"/>
                    <rect x="80" y="8" width="100" height="24" rx="12" fill="#e2e8f0"/>
                    <!-- 탭바 -->
                    <rect x="16" y="48" width="112" height="32" rx="8" fill="#0891b2"/>
                    <text x="72" y="69" fill="#fff" font-size="12" text-anchor="middle" font-weight="600">준비물</text>
                    <rect x="132" y="48" width="112" height="32" rx="8" fill="#f1f5f9"/>
                    <text x="188" y="69" fill="#64748b" font-size="12" text-anchor="middle">할 일</text>
                    <!-- 진행률 바 -->
                    <rect x="16" y="96" width="228" height="6" rx="3" fill="#e2e8f0"/>
                    <rect x="16" y="96" width="137" height="6" rx="3" fill="#0891b2"/>
                    <text x="228" y="92" fill="#64748b" font-size="10" text-anchor="end">3/5</text>
                    <!-- 카테고리 헤더 -->
                    <text x="16" y="128" fill="#0891b2" font-size="11" font-weight="700">📄 서류</text>
                    <!-- 체크 항목 1 - 완료 -->
                    <rect x="16" y="138" width="228" height="42" rx="8" fill="#f0fdf4"/>
                    <rect x="28" y="152" width="16" height="16" rx="4" fill="#10b981"/>
                    <path d="M32 160 l3 3 l6 -6" fill="none" stroke="#fff" stroke-width="2"/>
                    <text x="52" y="164" fill="#94a3b8" font-size="12" text-decoration="line-through">여권</text>
                    <rect x="180" y="152" width="48" height="18" rx="9" fill="#dbeafe"/>
                    <text x="204" y="165" fill="#3b82f6" font-size="9" text-anchor="middle">아빠</text>
                    <!-- 체크 항목 2 - 완료 -->
                    <rect x="16" y="186" width="228" height="42" rx="8" fill="#f0fdf4"/>
                    <rect x="28" y="200" width="16" height="16" rx="4" fill="#10b981"/>
                    <path d="M32 208 l3 3 l6 -6" fill="none" stroke="#fff" stroke-width="2"/>
                    <text x="52" y="212" fill="#94a3b8" font-size="12" text-decoration="line-through">항공권 출력</text>
                    <rect x="180" y="200" width="48" height="18" rx="9" fill="#fce7f3"/>
                    <text x="204" y="213" fill="#db2777" font-size="9" text-anchor="middle">엄마</text>
                    <!-- 카테고리 헤더 2 -->
                    <text x="16" y="256" fill="#0891b2" font-size="11" font-weight="700">👕 의류</text>
                    <!-- 체크 항목 3 - 완료 -->
                    <rect x="16" y="266" width="228" height="42" rx="8" fill="#f0fdf4"/>
                    <rect x="28" y="280" width="16" height="16" rx="4" fill="#10b981"/>
                    <path d="M32 288 l3 3 l6 -6" fill="none" stroke="#fff" stroke-width="2"/>
                    <text x="52" y="292" fill="#94a3b8" font-size="12" text-decoration="line-through">수영복</text>
                    <rect x="160" y="280" width="36" height="18" rx="9" fill="#dbeafe"/>
                    <text x="178" y="293" fill="#3b82f6" font-size="9" text-anchor="middle">전체</text>
                    <!-- 체크 항목 4 - 미완 -->
                    <rect x="16" y="314" width="228" height="42" rx="8" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <rect x="28" y="328" width="16" height="16" rx="4" fill="#fff" stroke="#d1d5db" stroke-width="1.5"/>
                    <text x="52" y="340" fill="#1e293b" font-size="12">래시가드</text>
                    <rect x="160" y="328" width="36" height="18" rx="9" fill="#fef3c7"/>
                    <text x="178" y="341" fill="#d97706" font-size="9" text-anchor="middle">지민</text>
                    <!-- 체크 항목 5 - 미완 -->
                    <rect x="16" y="362" width="228" height="42" rx="8" fill="#fff" stroke="#e2e8f0" stroke-width="1"/>
                    <rect x="28" y="376" width="16" height="16" rx="4" fill="#fff" stroke="#d1d5db" stroke-width="1.5"/>
                    <text x="52" y="388" fill="#1e293b" font-size="12">선크림 SPF50+</text>
                    <rect x="160" y="376" width="36" height="18" rx="9" fill="#dbeafe"/>
                    <text x="178" y="389" fill="#3b82f6" font-size="9" text-anchor="middle">전체</text>
                </svg>
            </div>
            <div class="feature-info">
                <span class="feature-badge badge-purple">준비물 체크</span>
                <h3>빠뜨리는 것 없이<br>꼼꼼하게 챙기기</h3>
                <ul class="feature-checks">
                    <li>카테고리별 그룹 관리</li>
                    <li>담당자 배정 · 배지</li>
                    <li>개인별 완료 체크</li>
                    <li>전체 진행률 표시</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ========== 4. 숫자 통계 ========== -->
<section class="landing-stats" id="statsSection">
    <div class="section-inner">
        <div class="stats-grid">
            <div class="stat-item gsap-stat">
                <span class="stat-num" data-target="10">0</span><span class="stat-suffix">개+</span>
                <span class="stat-label">지원 통화</span>
            </div>
            <div class="stat-item gsap-stat">
                <span class="stat-num" data-target="<?= $tripCount ?>">0</span><span class="stat-suffix">개</span>
                <span class="stat-label">등록된 일정</span>
            </div>
            <div class="stat-item gsap-stat">
                <span class="stat-num" data-target="100">0</span><span class="stat-suffix">%</span>
                <span class="stat-label">무료</span>
            </div>
            <div class="stat-item gsap-stat">
                <span class="stat-num" data-target="∞">∞</span><span class="stat-suffix"></span>
                <span class="stat-label">멤버 초대</span>
            </div>
        </div>
    </div>
</section>

<!-- ========== 5. FAQ ========== -->
<section class="landing-faq" id="faqSection">
    <div class="section-inner">
        <h2 class="section-title">자주 묻는 질문</h2>

        <div class="faq-item gsap-faq">
            <button class="faq-q" onclick="toggleFaq(this)">
                <span>앱을 설치해야 하나요?</span>
                <svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="faq-a"><p>아니요! WithPlan은 웹앱이라 브라우저에서 바로 사용할 수 있습니다. 별도 설치 없이 공유 받은 링크를 클릭하면 바로 시작됩니다.</p></div>
        </div>
        <div class="faq-item gsap-faq">
            <button class="faq-q" onclick="toggleFaq(this)">
                <span>무료인가요?</span>
                <svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="faq-a"><p>네, WithPlan의 모든 기능은 완전히 무료입니다. 숨겨진 비용이나 프리미엄 플랜 없이 모든 기능을 자유롭게 사용하실 수 있습니다.</p></div>
        </div>
        <div class="faq-item gsap-faq">
            <button class="faq-q" onclick="toggleFaq(this)">
                <span>멤버도 Google 로그인이 필요한가요?</span>
                <svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="faq-a"><p>아니요, Google 로그인은 여행을 만드는 오너(관리자)만 필요합니다. 멤버는 공유된 링크와 6자리 PIN만으로 간편하게 접속할 수 있습니다.</p></div>
        </div>
        <div class="faq-item gsap-faq">
            <button class="faq-q" onclick="toggleFaq(this)">
                <span>해외 통화도 지원하나요?</span>
                <svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="faq-a"><p>USD, EUR, JPY 등 10개 주요 외화를 지원합니다. 한국수출입은행 환율 API를 통해 실시간 환율을 반영하고, 카드·현금 결제 수단별로 다른 환율을 적용할 수 있습니다.</p></div>
        </div>
        <div class="faq-item gsap-faq">
            <button class="faq-q" onclick="toggleFaq(this)">
                <span>정산은 어떻게 계산되나요?</span>
                <svg class="faq-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="faq-a"><p>모든 지출을 분석하여 "누가 누구에게 얼마를 보내야 하는지" 최소 이체 횟수로 자동 계산합니다. 여러 통화가 섞여 있어도 환율을 적용해 원화로 통합 정산할 수 있습니다.</p></div>
        </div>
    </div>
</section>

<!-- ========== 6. CTA ========== -->
<section class="landing-cta" id="ctaSection">
    <div class="section-inner">
        <h2 class="cta-title gsap-cta">다음 여행,<br>함께 준비할까요?</h2>

        <?php if ($isLoggedIn): ?>
        <a href="/my" class="btn-cta gsap-cta">내 여행 관리 →</a>
        <?php else: ?>
        <a href="/auth/google" class="btn-cta gsap-cta"><?= $googleSvg ?> Google로 시작하기</a>
        <?php endif; ?>

        <div class="cta-divider gsap-cta">
            <span>또는</span>
        </div>

        <p class="cta-invite gsap-cta">초대받으셨나요?</p>
        <form class="cta-code-form gsap-cta" id="ctaCodeForm" onsubmit="return false;">
            <input type="text" class="cta-code-input" id="ctaCodeInput"
                   placeholder="여행 코드 8자리" maxlength="8"
                   autocomplete="off" autocorrect="off" spellcheck="false">
            <button type="button" class="cta-qr-btn" onclick="openQrScanner()" title="QR 코드 스캔">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            </button>
            <button type="submit" class="cta-code-btn">입장</button>
        </form>
        <span id="ctaCodeError" class="cta-code-error hidden"></span>
    </div>
</section>

<!-- ========== 7. Footer ========== -->
<footer class="landing-footer" id="footerSection">
    <div class="section-inner">
        <div class="footer-brand">WithPlan</div>
        <p class="footer-tagline">함께 만드는 여행 계획</p>
        <div class="footer-links">
            <a href="/contact">문의 및 제안</a>
            <a href="/terms">이용약관</a>
            <a href="/privacy">개인정보처리방침</a>
        </div>
        <p class="footer-copy">&copy; 2026 WithPlan. All rights reserved.</p>
    </div>
</footer>

<!-- ========== 플로팅 바 ========== -->
<div class="landing-float" id="floatingBar">
    <?php if ($isLoggedIn): ?>
    <a href="/my" class="float-auth">내 여행 →</a>
    <?php else: ?>
    <a href="/auth/google" class="float-auth"><?= $googleSvgSm ?> Google 로그인</a>
    <?php endif; ?>
    <div class="float-sep"></div>
    <form class="float-code" id="floatCodeForm" onsubmit="return false;">
        <input type="text" class="float-code-input" id="floatCodeInput"
               placeholder="여행 코드 입력" maxlength="8"
               autocomplete="off" autocorrect="off" spellcheck="false">
        <button type="button" class="float-qr-btn" onclick="openQrScanner()" title="QR 스캔">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        </button>
        <button type="submit" class="float-code-btn">입장</button>
        <span id="floatCodeError" class="float-code-error hidden"></span>
    </form>
</div>

<!-- QR 스캔 모달 -->
<div id="qrScanModal" class="qr-scan-modal hidden">
    <div class="qr-scan-header">
        <span class="qr-scan-title">QR 코드 스캔</span>
        <button class="qr-scan-close" onclick="closeQrScanner()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div id="qrScanReader"></div>
    <p class="qr-scan-hint">카메라를 QR 코드에 비춰주세요</p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

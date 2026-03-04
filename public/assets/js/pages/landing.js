/**
 * WithPlan Landing Page v4.0
 * GSAP ScrollTrigger 기반 애니메이션 + FAQ + 여행 코드 폼
 */
document.addEventListener('DOMContentLoaded', function () {

    // ── 여행 코드 입장 (플로팅바 + CTA 공유) ──
    async function submitTripCode(code, errorEl) {
        var trimmed = code.trim().toLowerCase();
        errorEl.classList.add('hidden');

        if (!/^[a-f0-9]{8}$/.test(trimmed)) {
            errorEl.textContent = '8자리 여행 코드를 입력해주세요.';
            errorEl.classList.remove('hidden');
            return;
        }

        try {
            var resp = await fetch('/api/trips/check?trip_code=' + trimmed);
            var data = await resp.json();
            if (data.success) {
                window.location.href = '/' + trimmed;
            } else {
                errorEl.textContent = '존재하지 않는 여행 코드입니다.';
                errorEl.classList.remove('hidden');
            }
        } catch (e) {
            window.location.href = '/' + trimmed;
        }
    }

    // 플로팅 바 폼
    var floatForm = document.getElementById('floatCodeForm');
    if (floatForm) {
        floatForm.addEventListener('submit', function () {
            submitTripCode(
                document.getElementById('floatCodeInput').value,
                document.getElementById('floatCodeError')
            );
        });
    }

    // CTA 섹션 폼
    var ctaForm = document.getElementById('ctaCodeForm');
    if (ctaForm) {
        ctaForm.addEventListener('submit', function () {
            submitTripCode(
                document.getElementById('ctaCodeInput').value,
                document.getElementById('ctaCodeError')
            );
        });
    }

    // ── FAQ 아코디언 ──
    window.toggleFaq = function (btn) {
        var item = btn.closest('.faq-item');
        var isOpen = item.classList.contains('open');

        // 다른 열린 항목 닫기
        document.querySelectorAll('.faq-item.open').forEach(function (el) {
            el.classList.remove('open');
        });

        if (!isOpen) {
            item.classList.add('open');
        }
    };

    // ── GSAP 애니메이션 ──
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        // GSAP 미로드 — 폴백: 플로팅 바만 스크롤로 표시
        var floatBar = document.getElementById('floatingBar');
        if (floatBar) {
            window.addEventListener('scroll', function () {
                var heroH = document.getElementById('heroSection').offsetHeight;
                if (window.scrollY > heroH * 0.7) {
                    floatBar.classList.add('visible');
                } else {
                    floatBar.classList.remove('visible');
                }
            }, { passive: true });
        }
        return;
    }

    // GSAP 사용 가능
    document.body.classList.add('gsap-loaded');
    gsap.registerPlugin(ScrollTrigger);

    // fromTo 헬퍼 — CSS opacity:0에서 명시적으로 opacity:1로 애니메이션
    var SHOW = { opacity: 1, y: 0, x: 0 };

    // ── 1. Hero 타임라인 (페이지 로드) ──
    var heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    heroTl
        .fromTo('.hero-eyebrow', { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6 })
        .fromTo('.hero-title-line', { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 0.7, stagger: 0.15 }, '-=0.3')
        .fromTo('.hero-desc', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 }, '-=0.3');
    if (document.querySelector('.hero-error')) {
        heroTl.fromTo('.hero-error', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4 }, '-=0.2');
    }
    heroTl
        .fromTo('.btn-hero', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 }, '-=0.2')
        .fromTo('.hero-scroll-hint', { opacity: 0 }, { opacity: 1, duration: 0.8 }, '-=0.2');

    // ── 플로팅 바: Hero 지나면 등장 ──
    ScrollTrigger.create({
        trigger: '#heroSection',
        start: 'bottom 80%',
        onLeave: function () {
            document.getElementById('floatingBar').classList.add('visible');
        },
        onEnterBack: function () {
            document.getElementById('floatingBar').classList.remove('visible');
        }
    });

    // ── 2. 사용 흐름 ──
    gsap.fromTo('.gsap-flow',
        { y: 40, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.6, stagger: 0.15,
          scrollTrigger: { trigger: '#flowSection', start: 'top 60%', once: true } }
    );

    // ── 3. 기능 쇼케이스 ──
    document.querySelectorAll('.gsap-feat').forEach(function (card, i) {
        var dir = i % 2 === 0 ? -30 : 30;
        var tl = gsap.timeline({
            scrollTrigger: { trigger: card, start: 'top 60%', once: true }
        });
        // 부모 카드 먼저 보이게 (자식은 아직 opacity:0이라 플래시 없음)
        tl.set(card, { opacity: 1 })
          .fromTo(card.querySelector('.feature-phone'),
            { x: dir, opacity: 0 },
            { x: 0, opacity: 1, duration: 0.7, ease: 'power2.out' })
          .fromTo(card.querySelector('.feature-info'),
            { y: 30, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.6, ease: 'power2.out' }, '-=0.3');
    });

    // ── 4. 숫자 카운트업 ──
    document.querySelectorAll('.gsap-stat').forEach(function (el) {
        var numEl = el.querySelector('.stat-num');
        var rawTarget = numEl.dataset.target;
        var target = parseInt(rawTarget, 10);
        var isStatic = isNaN(target); // ∞ 등 숫자가 아닌 경우

        ScrollTrigger.create({
            trigger: el,
            start: 'top 60%',
            once: true,
            onEnter: function () {
                // 카드 등장
                gsap.fromTo(el, { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 });

                // 정적 텍스트(∞ 등)는 카운트업 생략
                if (isStatic) {
                    numEl.textContent = rawTarget;
                    return;
                }
                if (target === 0) {
                    numEl.textContent = '0';
                    return;
                }
                var obj = { val: 0 };
                gsap.to(obj, {
                    val: target,
                    duration: 2,
                    ease: 'power1.out',
                    snap: { val: 1 },
                    onUpdate: function () {
                        numEl.textContent = Math.round(obj.val);
                    }
                });
            }
        });
    });

    // ── 5. FAQ ──
    gsap.fromTo('.gsap-faq',
        { y: 20, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.5, stagger: 0.1,
          scrollTrigger: { trigger: '#faqSection', start: 'top 60%', once: true } }
    );

    // ── 6. CTA ──
    gsap.fromTo('.gsap-cta',
        { y: 30, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.6, stagger: 0.12,
          scrollTrigger: { trigger: '#ctaSection', start: 'top 60%', once: true } }
    );

});

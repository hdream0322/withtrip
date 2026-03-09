/**
 * PIN 입력/설정 페이지 JS
 */
(function () {
    const config = window.PIN_CONFIG;
    const dots = document.querySelectorAll('#pinDots .pin-dot');
    const alertEl = document.getElementById('pinAlert');
    const stepEl = document.getElementById('pinStep');

    let pin = '';
    let firstPin = '';
    let step = 1; // set_pin: 1=입력, 2=확인
    let isSubmitting = false;

    function updateDots() {
        dots.forEach((dot, i) => {
            dot.classList.toggle('filled', i < pin.length);
        });
    }

    function showError(msg) {
        alertEl.textContent = msg;
        alertEl.classList.remove('hidden');
    }

    function hideError() {
        alertEl.classList.add('hidden');
    }

    function resetPin() {
        pin = '';
        updateDots();
    }

    async function submitPin() {
        if (isSubmitting) return;
        isSubmitting = true;
        hideError();

        const url = `/${config.tripCode}/${config.userId}/?action=${config.action}`;
        const body = {
            pin: config.action === 'set_pin' && step === 2 ? firstPin : pin,
            extend_session: document.getElementById('extendSession').checked,
        };

        if (config.action === 'set_pin') {
            body.pin = firstPin;
            body.pin_confirm = pin;
        }

        try {
            const data = await WP.post(url, body);

            if (data.success) {
                window.location.href = data.data.redirect;
            } else {
                showError(data.message);
                resetPin();
            }
        } catch (err) {
            showError(err.message || '오류가 발생했습니다.');
            resetPin();
        } finally {
            isSubmitting = false;
        }
    }

    function onKeyPress(key) {
        if (isSubmitting) return;
        hideError();

        if (key === 'back') {
            pin = pin.slice(0, -1);
            updateDots();
            return;
        }

        if (pin.length >= 6) return;

        pin += key;
        updateDots();

        if (pin.length === 6) {
            if (config.action === 'set_pin' && step === 1) {
                // 1단계 완료 → 2단계 (확인 입력)
                firstPin = pin;
                step = 2;
                pin = '';
                updateDots();
                if (stepEl) stepEl.textContent = 'PIN 확인 (2/2)';
                return;
            }

            // 서버 전송
            setTimeout(() => submitPin(), 200);
        }
    }

    // 키패드 이벤트
    document.querySelectorAll('.pin-key[data-key]').forEach(btn => {
        btn.addEventListener('click', () => onKeyPress(btn.dataset.key));
    });

    // 물리 키보드 지원
    document.addEventListener('keydown', (e) => {
        if (e.key >= '0' && e.key <= '9') {
            onKeyPress(e.key);
        } else if (e.key === 'Backspace') {
            onKeyPress('back');
        }
    });
})();

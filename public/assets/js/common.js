/**
 * WithPlan - 공통 JavaScript
 */

const WP = {
    /**
     * CSRF 토큰 가져오기
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /**
     * API 호출 래퍼
     */
    async api(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken(),
            },
        };

        const config = { ...defaults, ...options };
        if (options.headers) {
            config.headers = { ...defaults.headers, ...options.headers };
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || '요청 처리 중 오류가 발생했습니다.');
            }

            return data;
        } catch (error) {
            if (error instanceof TypeError && error.message === 'Failed to fetch') {
                throw new Error('네트워크 연결을 확인해주세요.');
            }
            throw error;
        }
    },

    /**
     * POST 요청
     */
    async post(url, body = {}) {
        return this.api(url, {
            method: 'POST',
            body: JSON.stringify(body),
        });
    },

    /**
     * PUT 요청
     */
    async put(url, body = {}) {
        return this.api(url, {
            method: 'PUT',
            body: JSON.stringify(body),
        });
    },

    /**
     * DELETE 요청
     */
    async delete(url) {
        return this.api(url, { method: 'DELETE' });
    },

    /**
     * 토스트 메시지 표시
     */
    toast(message, type = 'success', duration = 3000) {
        // 기존 토스트 제거
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // 표시 애니메이션
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // 자동 제거
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    /**
     * 클립보드 복사
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.toast('클립보드에 복사되었습니다.');
            return true;
        } catch {
            this.toast('복사에 실패했습니다.', 'error');
            return false;
        }
    },

    /**
     * 금액 포맷
     */
    formatMoney(amount, currency = 'KRW') {
        const num = Number(amount);
        if (currency === 'KRW') {
            return num.toLocaleString('ko-KR') + '원';
        }
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2 });
    },

    /**
     * 삭제 확인 모달 (Promise 기반)
     */
    async confirm(message) {
        const overlay = document.getElementById('confirmOverlay');
        const sheet = document.getElementById('confirmSheet');
        const messageEl = document.getElementById('confirmMessage');

        if (!overlay || !sheet || !messageEl) {
            // 폴백: 모달이 없으면 브라우저 대화상자 사용
            return window.confirm(message);
        }

        messageEl.textContent = message;

        return new Promise(resolve => {
            // 전역 resolve 함수 저장 (버튼 클릭 시 호출용)
            window._confirmResolve = (result) => {
                _hideModal('confirmOverlay', 'confirmSheet');
                resolve(result);
            };

            _showModal('confirmOverlay', 'confirmSheet');
        });
    },

    /**
     * 내부 헬퍼: 삭제 확인 취소
     */
    _cancelConfirm() {
        if (window._confirmResolve) {
            window._confirmResolve(false);
        }
    },

    /**
     * 내부 헬퍼: 삭제 확인
     */
    _confirmDelete() {
        if (window._confirmResolve) {
            window._confirmResolve(true);
        }
    },
};

/* ============================================================
   헤더 드롭다운 메뉴
   ============================================================ */

function toggleHeaderMenu() {
    const dd = document.getElementById('headerDropdown');
    if (!dd) return;
    dd.classList.toggle('open');
}

// 외부 클릭 시 드롭다운 닫기
document.addEventListener('click', function (e) {
    const dd = document.getElementById('headerDropdown');
    if (!dd) return;
    const wrap = dd.closest('.header-more-wrap');
    if (wrap && !wrap.contains(e.target)) {
        dd.classList.remove('open');
    }
});

// ESC 키로 드롭다운 또는 모달 닫기
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const dd = document.getElementById('headerDropdown');
        if (dd) dd.classList.remove('open');

        // 삭제 확인 모달 닫기
        const confirmSheet = document.getElementById('confirmSheet');
        if (confirmSheet && !confirmSheet.classList.contains('hidden')) {
            WP._cancelConfirm();
        }
    }
});

/* ============================================================
   공용 Sheet 모달 유틸
   ============================================================ */

function _showModal(overlayId, sheetId) {
    var overlay = document.getElementById(overlayId);
    var sheet   = document.getElementById(sheetId);
    if (!overlay || !sheet) return;
    overlay.classList.remove('hidden');
    sheet.classList.remove('hidden');
    requestAnimationFrame(function () {
        overlay.classList.add('visible');
        sheet.classList.add('visible');
    });

    // 드래그로 닫기 기능 (한 번만 추가)
    if (!sheet.dataset.dragHandlerAttached) {
        _attachModalDragHandler(sheet, overlayId, sheetId);
        sheet.dataset.dragHandlerAttached = 'true';
    }
}

function _hideModal(overlayId, sheetId) {
    var overlay = document.getElementById(overlayId);
    var sheet   = document.getElementById(sheetId);
    if (!overlay || !sheet) return;
    overlay.classList.remove('visible');
    sheet.classList.remove('visible');
    sheet.style.transform = '';
    sheet.style.transition = '';
    setTimeout(function () {
        overlay.classList.add('hidden');
        sheet.classList.add('hidden');
    }, 250);
}

function _attachModalDragHandler(sheet, overlayId, sheetId) {
    let startY = 0;
    let currentY = 0;
    let isDragging = false;
    const CLOSE_THRESHOLD = 80; // 80px 이상 드래그하면 닫기

    function handleStart(e) {
        if (e.type === 'mousedown' && e.button !== 0) return; // 좌클릭만
        const clientY = e.type.indexOf('touch') === 0 ? e.touches[0].clientY : e.clientY;
        startY = clientY;
        currentY = 0;
        isDragging = true;
        sheet.style.transition = 'none';
    }

    function handleMove(e) {
        if (!isDragging || startY === 0) return;
        const clientY = e.type.indexOf('touch') === 0 ? e.touches[0].clientY : e.clientY;
        currentY = clientY - startY;

        // 아래로 드래그할 때만 이동 (위로 스크롤할 때는 스크롤 허용)
        if (currentY > 15) {
            // 모달이 끝까지 스크롤되지 않았으면 드래그하지 않음
            const isScrolledToBottom = sheet.scrollHeight - sheet.scrollTop <= sheet.clientHeight + 5;
            if (sheet.scrollTop > 0 && !isScrolledToBottom) {
                return; // 아직 스크롤할 콘텐츠가 있으면 드래그 무시
            }

            e.preventDefault();
            var percent = Math.min(currentY / 300, 1);
            sheet.style.opacity = Math.max(1 - percent * 0.3, 0.7);
            sheet.style.transform = 'translateX(-50%) translateY(' + currentY + 'px)';
        }
    }

    function handleEnd(e) {
        if (!isDragging) return;
        isDragging = false;
        sheet.style.transition = '';
        sheet.style.opacity = '';

        if (currentY > CLOSE_THRESHOLD) {
            _hideModal(overlayId, sheetId);
        } else {
            sheet.style.transform = 'translateX(-50%) translateY(0)';
        }

        startY = 0;
        currentY = 0;
    }

    sheet.addEventListener('touchstart', handleStart, false);
    sheet.addEventListener('touchmove', handleMove, false);
    sheet.addEventListener('touchend', handleEnd, false);

    // PC 마우스 지원
    sheet.addEventListener('mousedown', handleStart, false);
    document.addEventListener('mousemove', handleMove, false);
    document.addEventListener('mouseup', handleEnd, false);
}

/**
 * 설정 페이지 JS
 */
const SC = window.SETTINGS_CONFIG;

const Settings = {
    /* ============================================================
       여행 정보 수정 (오너 전용)
       ============================================================ */

    openTripEditModal() {
        _showModal('tripEditOverlay', 'tripEditSheet');
    },

    closeTripEditModal() {
        _hideModal('tripEditOverlay', 'tripEditSheet');
    },

    async saveTripEdit() {
        const title = document.getElementById('editTripTitle').value.trim();
        if (!title) {
            WP.toast('제목을 입력해주세요.', 'error');
            return;
        }

        try {
            const data = await WP.put('/api/trips/update', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: SC.userId,
                title: title,
                description: document.getElementById('editTripDescription').value.trim(),
                destination: document.getElementById('editTripDestination').value.trim(),
                start_date: document.getElementById('editTripStartDate').value,
                end_date: document.getElementById('editTripEndDate').value,
            });

            if (data.success) {
                WP.toast('여행 정보가 수정되었습니다.');
                location.reload();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       멤버 관리 (오너 전용)
       ============================================================ */

    openAddMemberModal() {
        document.getElementById('newMemberUserId').value = '';
        document.getElementById('newMemberDisplayName').value = '';
        _showModal('addMemberOverlay', 'addMemberSheet');
        document.getElementById('newMemberUserId').focus();
    },

    closeAddMemberModal() {
        _hideModal('addMemberOverlay', 'addMemberSheet');
    },

    async addMember() {
        const userId = document.getElementById('newMemberUserId').value.trim();
        const displayName = document.getElementById('newMemberDisplayName').value.trim();

        if (!userId || !displayName) {
            WP.toast('모든 필드를 입력해주세요.', 'error');
            return;
        }

        if (!/^[a-zA-Z0-9_-]+$/.test(userId)) {
            WP.toast('ID는 영문, 숫자, _, -만 사용할 수 있습니다.', 'error');
            return;
        }

        try {
            const data = await WP.post('/api/members/manage', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: userId,
                display_name: displayName,
                requester_user_id: SC.userId,
            });

            if (data.success) {
                WP.toast('멤버가 추가되었습니다.');
                location.reload();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    async deleteMember(userId, displayName) {
        if (!await WP.confirm(displayName + ' 멤버를 삭제하시겠습니까?')) return;

        try {
            const data = await WP.delete(
                '/api/members/manage?csrf_token=' + SC.csrfToken +
                '&trip_code=' + SC.tripCode +
                '&user_id=' + encodeURIComponent(userId) +
                '&requester_user_id=' + SC.userId
            );

            if (data.success) {
                WP.toast('멤버가 삭제되었습니다.');
                const el = document.querySelector('.member-item[data-user-id="' + userId + '"]');
                if (el) el.remove();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    copyMemberUrl(userId) {
        const url = location.origin + '/' + SC.tripCode + '/' + userId + '/';
        WP.copyToClipboard(url);
    },

    /* ============================================================
       PIN 변경
       ============================================================ */

    openPinChangeModal() {
        document.getElementById('currentPin').value = '';
        document.getElementById('newPin').value = '';
        document.getElementById('confirmPin').value = '';
        _showModal('pinChangeOverlay', 'pinChangeSheet');
        document.getElementById('currentPin').focus();
    },

    closePinChangeModal() {
        _hideModal('pinChangeOverlay', 'pinChangeSheet');
    },

    async changePIN() {
        const currentPin = document.getElementById('currentPin').value;
        const newPin = document.getElementById('newPin').value;
        const confirmPin = document.getElementById('confirmPin').value;

        if (!currentPin || !newPin || !confirmPin) {
            WP.toast('모든 필드를 입력해주세요.', 'error');
            return;
        }

        if (newPin.length !== 6 || !/^\d{6}$/.test(newPin)) {
            WP.toast('새 PIN은 6자리 숫자여야 합니다.', 'error');
            return;
        }

        if (newPin !== confirmPin) {
            WP.toast('새 PIN이 일치하지 않습니다.', 'error');
            return;
        }

        try {
            const data = await WP.put('/api/pin_change', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: SC.userId,
                current_pin: currentPin,
                new_pin: newPin,
            });

            if (data.success) {
                WP.toast('PIN이 변경되었습니다.');
                Settings.closePinChangeModal();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       환율 설정
       ============================================================ */

    // 통화 표시명
    CURRENCY_LABELS: {
        USD: '미국 달러',
        EUR: '유로',
        JPY: '일본 엔',
        CNH: '중국 위안',
        GBP: '영국 파운드',
        AUD: '호주 달러',
        CAD: '캐나다 달러',
        HKD: '홍콩 달러',
        SGD: '싱가포르 달러',
        THB: '태국 바트',
    },

    async loadRate() {
        try {
            const result = await WP.api('/api/trips/rate?trip_code=' + SC.tripCode);
            if (!result.success) return;

            this.renderRateTable(
                result.data.base_rates || result.data.rates,
                result.data.adjustments || {},
                result.data.updated_at,
                result.data.cash_rates || {},
                result.data.cash_exchangers || {}
            );

            // 1시간 지났으면 자동 갱신
            if (result.data.needs_refresh) {
                await this.fetchAndSaveRates(true);
            }
        } catch (_) {
            document.getElementById('rateTableWrap').innerHTML =
                '<p class="text-sm text-muted">환율 정보를 불러올 수 없습니다.</p>';
        }
    },

    renderRateTable(baseRates, adjustments, updatedAt, cashRates, cashExchangers) {
        const wrap  = document.getElementById('rateTableWrap');
        const label = document.getElementById('rateSourceLabel');
        const members = SC.members || [];

        if (!baseRates || Object.keys(baseRates).length === 0) {
            wrap.innerHTML = '<p class="text-sm text-muted">저장된 환율이 없습니다. 환율 갱신을 눌러주세요.</p>';
            return;
        }

        // USD, EUR 우선 정렬
        const PRIORITY = ['USD', 'EUR'];
        const allCurs = Object.keys(baseRates);
        const sorted = [
            ...PRIORITY.filter(c => allCurs.includes(c)),
            ...allCurs.filter(c => !PRIORITY.includes(c)),
        ];

        let html = '<div class="rate-list">';

        for (const cur of sorted) {
            const base = baseRates[cur];
            const name = this.CURRENCY_LABELS[cur] || cur;
            const adj  = adjustments[cur] || 0;
            const eff  = base + adj;
            const cashRate      = cashRates[cur] ?? '';
            const cashExchanger = cashExchangers[cur] || '';
            const step    = cur === 'JPY' ? '0.01' : '1';
            const baseStr = cur === 'JPY' ? base.toFixed(2) : Math.round(base).toLocaleString('ko-KR');
            const effStr  = cur === 'JPY' ? eff.toFixed(2)  : Math.round(eff).toLocaleString('ko-KR');
            const isPriority = PRIORITY.includes(cur);

            let memberOptions = '<option value="">환전자</option>';
            for (const m of members) {
                memberOptions += '<option value="' + m.user_id + '"' + (m.user_id === cashExchanger ? ' selected' : '') + '>' + m.display_name + '</option>';
            }

            html += '<div class="rate-item' + (isPriority ? ' rate-item-primary' : '') + '">'
                // 상단: 통화 정보 + 기준 환율
                + '<div class="rate-item-top">'
                +   '<div class="rate-item-currency">'
                +     '<span class="rate-code">' + cur + '</span>'
                +     '<span class="rate-name">' + name + '</span>'
                +   '</div>'
                +   '<div class="rate-item-base" id="rateEff_' + cur + '">'
                +     effStr + '<span class="rate-unit">원</span>'
                +     (adj !== 0 ? '<span class="rate-adj-badge">' + (adj > 0 ? '+' : '') + adj + '</span>' : '')
                +   '</div>'
                + '</div>'
                // 하단: 카드 조정 + 현금 환전
                + '<div class="rate-item-fields">'
                +   '<div class="rate-field">'
                +     '<span class="rate-field-label">카드 조정</span>'
                +     '<div class="rate-field-row">'
                +       '<input type="number" class="rate-adj-input" data-currency="' + cur + '" data-base="' + base + '" value="' + adj + '" step="' + step + '" placeholder="0">'
                +       '<span class="rate-field-unit">원</span>'
                +       '<span class="rate-field-hint">기준 ' + baseStr + '원</span>'
                +     '</div>'
                +   '</div>'
                +   '<div class="rate-field rate-field-cash">'
                +     '<span class="rate-field-label cash">현금 환전</span>'
                +     '<div class="rate-field-row">'
                +       '<input type="number" class="rate-cash-input" data-currency="' + cur + '" value="' + cashRate + '" step="' + step + '" placeholder="미설정">'
                +       '<span class="rate-field-unit">원</span>'
                +       '<select class="rate-exchanger-select" data-currency="' + cur + '">' + memberOptions + '</select>'
                +     '</div>'
                +   '</div>'
                + '</div>'
                + '</div>';
        }

        html += '</div>';
        html += '<button class="btn btn-primary btn-sm btn-full mt-12" onclick="Settings.saveAdjustments()">환율 설정 저장</button>';
        wrap.innerHTML = html;

        wrap.querySelectorAll('.rate-adj-input').forEach(input => {
            input.addEventListener('input', () => this.previewAdjustment(input));
        });

        if (updatedAt) {
            const d = new Date(updatedAt.replace(' ', 'T'));
            const fmt = d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
            label.textContent = '마지막 갱신: ' + fmt + ' · 1시간마다 자동 갱신';
        }
    },

    previewAdjustment(input) {
        const cur  = input.dataset.currency;
        const base = parseFloat(input.dataset.base) || 0;
        const adj  = parseFloat(input.value) || 0;
        const eff  = base + adj;
        const effStr = cur === 'JPY' ? eff.toFixed(2) : Math.round(eff).toLocaleString('ko-KR');
        const effEl = document.getElementById('rateEff_' + cur);
        if (!effEl) return;
        effEl.innerHTML = effStr + '<span class="rate-unit">원</span>'
            + (adj !== 0 ? '<span class="rate-adj-badge">' + (adj > 0 ? '+' : '') + adj + '</span>' : '');
    },

    async saveAdjustments() {
        const adjustments = {};
        const cashRates = {};
        const cashExchangers = {};

        document.querySelectorAll('.rate-adj-input').forEach(input => {
            adjustments[input.dataset.currency] = parseFloat(input.value) || 0;
        });
        document.querySelectorAll('.rate-cash-input').forEach(input => {
            const val = input.value.trim();
            cashRates[input.dataset.currency] = val !== '' ? parseFloat(val) : '';
        });
        document.querySelectorAll('.rate-exchanger-select').forEach(select => {
            cashExchangers[select.dataset.currency] = select.value;
        });

        try {
            const result = await WP.post('/api/trips/rate', {
                csrf_token:      SC.csrfToken,
                trip_code:       SC.tripCode,
                adjustments:     adjustments,
                cash_rates:      cashRates,
                cash_exchangers: cashExchangers,
            });

            if (result.success) {
                WP.toast('환율 설정이 저장되었습니다.');
            } else {
                WP.toast(result.message, 'error');
            }
        } catch (err) {
            WP.toast('저장 중 오류가 발생했습니다.', 'error');
        }
    },

    async fetchAndSaveRates(silent = false) {
        const btn = document.getElementById('btnFetchLiveRate');
        if (btn) btn.disabled = true;
        try {
            const liveResult = await WP.api('/api/budget/exchange_rate');
            if (!liveResult.success) {
                const debugMsg = liveResult.data?.debug || '';
                if (debugMsg) console.error('[환율 오류]', debugMsg);
                if (!silent) WP.toast(liveResult.message || '환율 정보를 불러올 수 없습니다.', 'error');
                return;
            }

            const saveResult = await WP.post('/api/trips/rate', {
                csrf_token: SC.csrfToken,
                trip_code:  SC.tripCode,
                rates:      liveResult.data.rates,
            });

            if (saveResult.success) {
                // 기존 조정값 유지하며 재렌더링
                await this.loadRate();
                if (!silent) WP.toast('환율이 갱신되었습니다.');
            } else {
                if (!silent) WP.toast(saveResult.message, 'error');
            }
        } catch (err) {
            if (!silent) WP.toast('환율 정보를 불러올 수 없습니다.', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    },
};

document.addEventListener('DOMContentLoaded', () => {
    Settings.loadRate();
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        Settings.closeTripEditModal();
        Settings.closeAddMemberModal();
        Settings.closePinChangeModal();
    }
});

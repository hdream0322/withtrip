/**
 * 설정 페이지 - 환율 관리 JS
 * settings.js에서 분리
 */

// Settings 객체에 환율 관련 기능 병합
Object.assign(Settings, {
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

    // 지원 외화 순서
    ALL_FOREIGN: ['USD', 'EUR', 'JPY', 'CNH', 'GBP', 'AUD', 'CAD', 'HKD', 'SGD', 'THB'],

    // 현재 활성 통화 목록 (KRW 포함)
    activeCurrencies: ['KRW'],

    // 재렌더링용 캐시
    _rateCache: null,

    async loadRate() {
        try {
            const result = await WP.api('/api/trips/rate?trip_code=' + SC.tripCode);
            if (!result.success) return;

            // 활성 통화 파싱
            this.activeCurrencies = (result.data.active_currencies || 'KRW')
                .split(',').map(c => c.trim()).filter(Boolean);

            // 재렌더링용 캐시 저장
            this._rateCache = {
                baseRates:      result.data.base_rates || result.data.rates || {},
                adjustments:    result.data.adjustments || {},
                updatedAt:      result.data.updated_at,
                cashRates:      result.data.cash_rates || {},
                cashExchangers: result.data.cash_exchangers || {},
            };

            this.renderRateTable();

            // 1시간 지났고 외화가 활성화된 경우 자동 갱신
            const hasForeign = this.activeCurrencies.some(c => c !== 'KRW');
            if (result.data.needs_refresh && hasForeign) {
                await this.fetchAndSaveRates(true);
            }
        } catch (_) {
            document.getElementById('rateTableWrap').innerHTML =
                '<p class="text-sm text-muted">환율 정보를 불러올 수 없습니다.</p>';
        }
    },

    toggleCurrencyChip(cur) {
        const idx = this.activeCurrencies.indexOf(cur);
        if (idx === -1) {
            this.activeCurrencies.push(cur);
        } else {
            this.activeCurrencies.splice(idx, 1);
        }
        this.renderRateTable();
    },

    renderRateTable() {
        const wrap    = document.getElementById('rateTableWrap');
        const label   = document.getElementById('rateSourceLabel');
        const members = SC.members || [];
        const cache   = this._rateCache || { baseRates: {}, adjustments: {}, updatedAt: null, cashRates: {}, cashExchangers: {} };
        const { baseRates, adjustments, updatedAt, cashRates, cashExchangers } = cache;

        // ── 통화 선택 칩 ──
        let html = '<div class="currency-picker">'
            + '<p class="currency-picker-label">사용할 통화 선택</p>'
            + '<div class="currency-chip-row">'
            + '<span class="currency-chip currency-chip-krw">KRW</span>';

        for (const cur of this.ALL_FOREIGN) {
            const isActive = this.activeCurrencies.includes(cur);
            html += '<button class="currency-chip' + (isActive ? ' active' : '') + '" '
                + 'onclick="Settings.toggleCurrencyChip(\'' + cur + '\')">'
                + cur + '</button>';
        }
        html += '</div></div>';

        // ── 활성 외화 목록 ──
        const activeForeign = this.ALL_FOREIGN.filter(c => this.activeCurrencies.includes(c));

        if (activeForeign.length === 0) {
            html += '<p class="text-xs text-muted" style="margin:8px 0 12px;">외화를 선택하면 환율 설정이 표시됩니다.</p>';
        } else {
            const PRIORITY = ['USD', 'EUR'];
            const sorted = [
                ...PRIORITY.filter(c => activeForeign.includes(c)),
                ...activeForeign.filter(c => !PRIORITY.includes(c)),
            ];

            html += '<div class="rate-list">';

            for (const cur of sorted) {
                const base = baseRates[cur];

                if (!base) {
                    html += '<div class="rate-item rate-item-empty">'
                        + '<div class="rate-item-top">'
                        + '<div class="rate-item-currency">'
                        + '<span class="rate-code">' + cur + '</span>'
                        + '<span class="rate-name">' + (this.CURRENCY_LABELS[cur] || '') + '</span>'
                        + '</div>'
                        + '<span class="text-xs text-muted">환율 갱신 후 표시됩니다</span>'
                        + '</div></div>';
                    continue;
                }

                const name          = this.CURRENCY_LABELS[cur] || cur;
                const adj           = adjustments[cur] || 0;
                const eff           = base + adj;
                const cashRate      = cashRates[cur] ?? '';
                const cashExchanger = cashExchangers[cur] || '';
                const step          = cur === 'JPY' ? '0.01' : '1';
                const baseStr       = cur === 'JPY' ? base.toFixed(2) : Math.round(base).toLocaleString('ko-KR');
                const effStr        = cur === 'JPY' ? eff.toFixed(2)  : Math.round(eff).toLocaleString('ko-KR');
                const isPriority    = PRIORITY.includes(cur);

                let memberOptions = '<option value="">환전자</option>';
                for (const m of members) {
                    memberOptions += '<option value="' + m.user_id + '"'
                        + (m.user_id === cashExchanger ? ' selected' : '') + '>'
                        + WP.escapeHtml(m.display_name) + '</option>';
                }

                html += '<div class="rate-item' + (isPriority ? ' rate-item-primary' : '') + '">'
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
        }

        html += '<button class="btn btn-primary btn-sm btn-full mt-12" onclick="Settings.saveAdjustments()">환율 설정 저장</button>';
        wrap.innerHTML = html;

        wrap.querySelectorAll('.rate-adj-input').forEach(input => {
            input.addEventListener('input', () => this.previewAdjustment(input));
        });

        if (updatedAt) {
            const d   = new Date(updatedAt.replace(' ', 'T'));
            const fmt = d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' });
            label.textContent = '마지막 갱신: ' + fmt + ' · 1시간마다 자동 갱신';
        }
    },

    previewAdjustment(input) {
        const cur    = input.dataset.currency;
        const base   = parseFloat(input.dataset.base) || 0;
        const adj    = parseFloat(input.value) || 0;
        const eff    = base + adj;
        const effStr = cur === 'JPY' ? eff.toFixed(2) : Math.round(eff).toLocaleString('ko-KR');
        const effEl  = document.getElementById('rateEff_' + cur);
        if (!effEl) return;
        effEl.innerHTML = effStr + '<span class="rate-unit">원</span>'
            + (adj !== 0 ? '<span class="rate-adj-badge">' + (adj > 0 ? '+' : '') + adj + '</span>' : '');
    },

    async saveAdjustments() {
        const adjustments    = {};
        const cashRates      = {};
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
                csrf_token:        SC.csrfToken,
                trip_code:         SC.tripCode,
                adjustments:       adjustments,
                cash_rates:        cashRates,
                cash_exchangers:   cashExchangers,
                active_currencies: this.activeCurrencies.join(','),
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
            const activeForeign = this.activeCurrencies.filter(c => c !== 'KRW');
            if (activeForeign.length === 0) {
                if (!silent) WP.toast('사용할 외화 통화를 먼저 선택해주세요.', 'error');
                return;
            }

            const liveResult = await WP.api('/api/budget/exchange_rate');
            if (!liveResult.success) {
                const debugMsg = liveResult.data?.debug || '';
                if (debugMsg) console.error('[환율 오류]', debugMsg);
                if (!silent) WP.toast(liveResult.message || '환율 정보를 불러올 수 없습니다.', 'error');
                return;
            }

            const filteredRates = {};
            for (const cur of activeForeign) {
                if (liveResult.data.rates[cur] !== undefined) {
                    filteredRates[cur] = liveResult.data.rates[cur];
                }
            }

            const saveResult = await WP.post('/api/trips/rate', {
                csrf_token: SC.csrfToken,
                trip_code:  SC.tripCode,
                rates:      filteredRates,
            });

            if (saveResult.success) {
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
});

// 환율 자동 로드
document.addEventListener('DOMContentLoaded', () => {
    Settings.loadRate();
});

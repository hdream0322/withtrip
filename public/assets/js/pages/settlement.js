/**
 * 정산 페이지 JS
 */
const SCONF = window.SETTLEMENT_CONFIG;

const Settlement = {
    data: null,
    exchangeRate: null,
    completedTransfers: {},

    /**
     * 초기화
     */
    async init() {
        this.loadCompletedTransfers();
        await this.loadData();
    },

    /**
     * 정산 데이터 로드
     */
    async loadData() {
        try {
            const result = await WP.api(
                '/api/settlement?trip_code=' + encodeURIComponent(SCONF.tripCode)
            );

            if (!result.success) {
                WP.toast(result.message, 'error');
                return;
            }

            this.data = result.data;
            document.getElementById('settlementLoading').classList.add('hidden');

            if (!this.data.currencies || this.data.currencies.length === 0) {
                document.getElementById('settlementEmpty').classList.remove('hidden');
                return;
            }

            document.getElementById('settlementContent').classList.remove('hidden');

            // 통화 혼용 시 환율 섹션 표시
            if (this.data.has_multiple) {
                document.getElementById('exchangeRateSection').classList.remove('hidden');
            }

            this.render();
        } catch (err) {
            document.getElementById('settlementLoading').classList.add('hidden');
            WP.toast(err.message, 'error');
        }
    },

    /**
     * 화면 렌더링
     */
    render() {
        if (!this.data) return;

        if (this.exchangeRate && this.data.has_multiple) {
            this.renderUnified();
        } else {
            this.renderByCurrency();
        }
    },

    /**
     * 통화별 분리 렌더링
     */
    renderByCurrency() {
        const { members, settlements, currencies, member_names } = this.data;

        // 멤버별 요약
        let summaryHtml = '';
        for (const member of members) {
            summaryHtml += this.renderMemberSummary(member, currencies);
        }
        document.getElementById('memberSummaryList').innerHTML = summaryHtml;

        // 이체 내역
        let transferHtml = '';
        for (const currency of currencies) {
            const settlement = settlements[currency];
            if (!settlement) continue;

            if (currencies.length > 1) {
                transferHtml += '<div class="currency-header">' + this.currencyLabel(currency) + '</div>';
            }

            if (settlement.transfers.length === 0) {
                transferHtml += '<div class="no-transfers">정산할 내역이 없습니다.</div>';
            } else {
                for (const t of settlement.transfers) {
                    transferHtml += this.renderTransferItem(t, currency, member_names);
                }
            }
        }

        if (!transferHtml) {
            transferHtml = '<div class="no-transfers">정산할 내역이 없습니다.</div>';
        }

        document.getElementById('transferList').innerHTML = transferHtml;
    },

    /**
     * 통합 정산 렌더링 (환율 적용)
     */
    renderUnified() {
        const { members, settlements, currencies, member_names } = this.data;
        const rate = this.exchangeRate;

        // 통합 balance 계산
        const unifiedBalances = {};
        for (const member of members) {
            const uid = member.user_id;
            let totalPaid = 0;
            let totalOwed = 0;

            for (const currency of currencies) {
                const bal = member.by_currency[currency];
                if (!bal) continue;

                const multiplier = currency === 'USD' ? rate : 1;
                totalPaid += bal.paid * multiplier;
                totalOwed += bal.owed * multiplier;
            }

            unifiedBalances[uid] = {
                paid: Math.round(totalPaid),
                owed: Math.round(totalOwed),
                net: Math.round(totalPaid - totalOwed),
                display_name: member.display_name,
                is_owner: member.is_owner,
            };
        }

        // 멤버별 요약 (통합)
        let summaryHtml = '<div class="unified-notice">환율 1 USD = ' + WP.formatMoney(rate, 'KRW').replace('원', '') + '원 기준 통합 정산</div>';
        for (const member of members) {
            const uid = member.user_id;
            const bal = unifiedBalances[uid];
            summaryHtml += this.renderUnifiedMemberSummary(member, bal);
        }
        document.getElementById('memberSummaryList').innerHTML = summaryHtml;

        // 통합 이체 계산
        const netBalances = {};
        for (const uid in unifiedBalances) {
            if (unifiedBalances[uid].net !== 0) {
                netBalances[uid] = unifiedBalances[uid].net;
            }
        }

        const transfers = this.calculateMinTransfers(netBalances);

        let transferHtml = '<div class="unified-notice">KRW 통합 정산 결과</div>';
        if (transfers.length === 0) {
            transferHtml += '<div class="no-transfers">정산할 내역이 없습니다.</div>';
        } else {
            for (const t of transfers) {
                transferHtml += this.renderTransferItem(t, 'KRW', member_names);
            }
        }

        document.getElementById('transferList').innerHTML = transferHtml;
    },

    /**
     * 멤버 요약 렌더 (통화별)
     */
    renderMemberSummary(member, currencies) {
        let html = '<div class="member-summary-item">';
        html += '<div class="member-name">' + this.esc(member.display_name);
        if (member.is_owner) {
            html += ' <span class="owner-badge">오너</span>';
        }
        html += '</div>';

        for (const currency of currencies) {
            const bal = member.by_currency[currency] || { paid: 0, owed: 0, net: 0 };
            if (currencies.length > 1) {
                html += '<div style="font-size:0.75rem; color:var(--color-primary); margin-top:4px; font-weight:600;">' + this.currencyLabel(currency) + '</div>';
            }
            html += '<div class="member-balance-row">';
            html += '<span>결제한 금액</span>';
            html += '<span class="balance-amount">' + this.formatAmount(bal.paid, currency) + '</span>';
            html += '</div>';
            html += '<div class="member-balance-row">';
            html += '<span>부담해야 할 금액</span>';
            html += '<span class="balance-amount">' + this.formatAmount(bal.owed, currency) + '</span>';
            html += '</div>';

            const netClass = bal.net > 0 ? 'balance-positive' : (bal.net < 0 ? 'balance-negative' : 'balance-zero');
            const netLabel = bal.net > 0 ? '받을 금액' : (bal.net < 0 ? '줄 금액' : '정산 완료');
            html += '<div class="net-balance">';
            html += '<span>' + netLabel + '</span>';
            html += '<span class="' + netClass + '">' + this.formatAmount(Math.abs(bal.net), currency) + '</span>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    },

    /**
     * 멤버 요약 렌더 (통합)
     */
    renderUnifiedMemberSummary(member, bal) {
        let html = '<div class="member-summary-item">';
        html += '<div class="member-name">' + this.esc(member.display_name);
        if (member.is_owner) {
            html += ' <span class="owner-badge">오너</span>';
        }
        html += '</div>';

        html += '<div class="member-balance-row">';
        html += '<span>결제한 금액</span>';
        html += '<span class="balance-amount">' + this.formatAmount(bal.paid, 'KRW') + '</span>';
        html += '</div>';
        html += '<div class="member-balance-row">';
        html += '<span>부담해야 할 금액</span>';
        html += '<span class="balance-amount">' + this.formatAmount(bal.owed, 'KRW') + '</span>';
        html += '</div>';

        const netClass = bal.net > 0 ? 'balance-positive' : (bal.net < 0 ? 'balance-negative' : 'balance-zero');
        const netLabel = bal.net > 0 ? '받을 금액' : (bal.net < 0 ? '줄 금액' : '정산 완료');
        html += '<div class="net-balance">';
        html += '<span>' + netLabel + '</span>';
        html += '<span class="' + netClass + '">' + this.formatAmount(Math.abs(bal.net), 'KRW') + '</span>';
        html += '</div>';

        html += '</div>';
        return html;
    },

    /**
     * 이체 항목 렌더
     */
    renderTransferItem(transfer, currency, memberNames) {
        const key = transfer.from + '-' + transfer.to + '-' + currency;
        const isCompleted = !!this.completedTransfers[key];
        const completedClass = isCompleted ? ' completed' : '';
        const checkedAttr = isCompleted ? ' checked' : '';

        const fromName = memberNames[transfer.from] || transfer.from;
        const toName = memberNames[transfer.to] || transfer.to;

        let html = '<div class="transfer-item' + completedClass + '" data-key="' + key + '">';
        html += '<div class="transfer-info">';
        html += '<div class="transfer-from-to">';
        html += '<strong>' + this.esc(fromName) + '</strong>';
        html += '<span class="transfer-arrow">&rarr;</span>';
        html += '<strong>' + this.esc(toName) + '</strong>';
        html += '</div>';
        html += '</div>';
        html += '<div class="transfer-amount">' + this.formatAmount(transfer.amount, currency) + '</div>';
        html += '<div class="transfer-check">';
        html += '<input type="checkbox" title="정산 완료" onchange="Settlement.toggleComplete(\'' + key + '\')"' + checkedAttr + '>';
        html += '</div>';
        html += '</div>';

        return html;
    },

    /**
     * 환율 적용
     */
    applyExchangeRate() {
        const input = document.getElementById('exchangeRate');
        const rate = parseInt(input.value, 10);

        if (!rate || rate <= 0) {
            WP.toast('올바른 환율을 입력해주세요.', 'error');
            return;
        }

        this.exchangeRate = rate;
        this.render();
        WP.toast('환율이 적용되었습니다.');
    },

    /**
     * 환율 초기화 (통화별 분리)
     */
    resetExchangeRate() {
        this.exchangeRate = null;
        document.getElementById('exchangeRate').value = '';
        this.render();
        WP.toast('통화별 분리 정산으로 변경되었습니다.');
    },

    /**
     * 정산 완료 토글
     */
    toggleComplete(key) {
        if (this.completedTransfers[key]) {
            delete this.completedTransfers[key];
        } else {
            this.completedTransfers[key] = true;
        }

        this.saveCompletedTransfers();

        // DOM 업데이트
        const item = document.querySelector('.transfer-item[data-key="' + key + '"]');
        if (item) {
            item.classList.toggle('completed');
        }
    },

    /**
     * 완료 상태 localStorage 저장
     */
    saveCompletedTransfers() {
        const storageKey = 'settlement_' + SCONF.tripCode;
        localStorage.setItem(storageKey, JSON.stringify(this.completedTransfers));
    },

    /**
     * 완료 상태 localStorage 로드
     */
    loadCompletedTransfers() {
        const storageKey = 'settlement_' + SCONF.tripCode;
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                this.completedTransfers = JSON.parse(saved);
            }
        } catch {
            this.completedTransfers = {};
        }
    },

    /**
     * 클라이언트 측 최소 이체 알고리즘 (통합 정산용)
     */
    calculateMinTransfers(netBalances) {
        const creditors = [];
        const debtors = [];

        for (const uid in netBalances) {
            const amount = netBalances[uid];
            if (amount > 0) {
                creditors.push({ user_id: uid, amount: amount });
            } else if (amount < 0) {
                debtors.push({ user_id: uid, amount: Math.abs(amount) });
            }
        }

        creditors.sort((a, b) => b.amount - a.amount);
        debtors.sort((a, b) => b.amount - a.amount);

        const transfers = [];
        let ci = 0;
        let di = 0;

        while (ci < creditors.length && di < debtors.length) {
            const transferAmount = Math.min(creditors[ci].amount, debtors[di].amount);

            if (transferAmount > 0) {
                transfers.push({
                    from: debtors[di].user_id,
                    to: creditors[ci].user_id,
                    amount: transferAmount,
                });
            }

            creditors[ci].amount -= transferAmount;
            debtors[di].amount -= transferAmount;

            if (creditors[ci].amount === 0) ci++;
            if (debtors[di].amount === 0) di++;
        }

        return transfers;
    },

    /**
     * 금액 포맷
     */
    formatAmount(amount, currency) {
        return WP.formatMoney(amount, currency);
    },

    /**
     * 통화 라벨
     */
    currencyLabel(currency) {
        const labels = { 'KRW': 'KRW (원)', 'USD': 'USD ($)' };
        return labels[currency] || currency;
    },

    /**
     * XSS 방지
     */
    esc(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
};

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', () => Settlement.init());

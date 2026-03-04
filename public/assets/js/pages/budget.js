/**
 * 지출 관리 페이지 JS
 * 2탭: 지출 내역 | 정산
 *
 * BUDGET_CONFIG = { tripCode, userId, csrfToken, members: [{user_id, display_name}] }
 */
const BC = window.BUDGET_CONFIG;

// --- 상태 ---
let expenses = [];
let incomes = [];
let settlementLoaded = false;

// ========================
// 초기화
// ========================
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initExpenseModal();
    loadExpenses();
    loadIncomes();
    handleHashTab();
});

// ========================
// 탭 전환 (2탭)
// ========================
function initTabs() {
    const tabMap = {
        'expenses': 'tabExpenses',
        'settlement': 'tabSettlement',
    };

    document.querySelectorAll('.page-tab-btn[data-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            switchTab(tab.dataset.tab);
        });
    });

    updateFabVisibility('expenses');

    // 하단 네비 "지출" 클릭 시 탭 토글
    const navBudget = document.getElementById('navBudget');
    if (navBudget) {
        navBudget.addEventListener('click', e => {
            e.preventDefault();
            const active = document.querySelector('.tab-pane.active');
            const next = active?.id === 'tabExpenses' ? 'settlement' : 'expenses';
            switchTab(next);
        });
    }
}

function switchTab(tabName) {
    const tabMap = { 'expenses': 'tabExpenses', 'settlement': 'tabSettlement' };

    document.querySelectorAll('.page-tab-btn[data-tab]').forEach(t => t.classList.remove('active'));
    const activeBtn = document.querySelector('.page-tab-btn[data-tab="' + tabName + '"]');
    if (activeBtn) activeBtn.classList.add('active');

    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    const targetId = tabMap[tabName] || 'tabExpenses';
    document.getElementById(targetId).classList.add('active');

    updateFabVisibility(tabName);

    if (tabName === 'settlement' && !settlementLoaded) {
        settlementLoaded = true;
        Settlement.init();
    }
}

function handleHashTab() {
    const hash = location.hash.replace('#', '');
    if (hash === 'settlement') {
        switchTab('settlement');
    }
}

function updateFabVisibility(activeTab) {
    const fabIncome = document.getElementById('fabIncome');
    const fabExpense = document.getElementById('fabExpense');
    if (activeTab === 'expenses') {
        fabIncome.style.display = 'flex';
        fabExpense.style.display = 'flex';
    } else {
        fabIncome.style.display = 'none';
        fabExpense.style.display = 'none';
    }
}

function formatAmount(amount, currency) {
    if (currency === 'USD') {
        return '$' + Number(amount).toLocaleString('en-US');
    }
    return Number(amount).toLocaleString('ko-KR') + '원';
}

// ========================
// 지출 CRUD
// ========================

async function loadExpenses() {
    try {
        const data = await WP.api('/api/budget/expenses?trip_code=' + BC.tripCode);
        if (data.success) {
            expenses = data.data.expenses;
            renderExpenses();
        }
    } catch (err) {
        document.getElementById('expenseList').innerHTML =
            '<div class="card text-center text-muted text-sm">지출 목록을 불러올 수 없습니다.</div>';
    }
}

function renderExpenses() {
    const container = document.getElementById('expenseList');

    // 수입 + 지출 합쳐서 날짜순 표시
    const allItems = [];

    expenses.forEach(exp => {
        allItems.push({ type: 'expense', data: exp, date: exp.expense_date || '9999-99-99' });
    });

    incomes.forEach(inc => {
        allItems.push({ type: 'income', data: inc, date: inc.income_date || '9999-99-99' });
    });

    allItems.sort((a, b) => b.date.localeCompare(a.date));

    if (allItems.length === 0) {
        container.innerHTML =
            '<div class="empty-state">' +
            '<div class="empty-state-icon">&#128179;</div>' +
            '<div class="empty-state-text">아직 내역이 없습니다.<br>아래 버튼으로 추가해주세요.</div>' +
            '</div>';
        return;
    }

    let html = '';
    allItems.forEach(item => {
        if (item.type === 'expense') {
            html += renderExpenseCard(item.data);
        } else {
            html += renderIncomeCard(item.data);
        }
    });

    container.innerHTML = html;
}

function renderExpenseCard(exp) {
    const desc = exp.description || '(설명 없음)';
    const paidByName = exp.paid_by_name || exp.paid_by;
    const dateStr = exp.expense_date || '';
    const isDutch = exp.is_dutch === 1;

    let html =
        '<div class="card expense-card" data-expense-id="' + exp.id + '">' +
        '<div class="expense-header">' +
        '<span class="expense-desc">' + escHtml(desc) + '</span>' +
        '<span class="expense-amount">' + formatAmount(exp.amount, exp.currency) + '</span>' +
        '</div>' +
        '<div class="expense-meta">';

    html += '<span class="expense-meta-item">' + escHtml(paidByName) + ' 결제</span>';

    if (dateStr) {
        html += '<span class="expense-meta-item">' + escHtml(dateStr) + '</span>';
    }

    html +=
        '<span class="expense-badge ' + (isDutch ? 'badge-dutch' : 'badge-solo') + '">' +
        (isDutch ? '분담' : '개인') +
        '</span>' +
        '</div>';

    if (isDutch && exp.splits && exp.splits.length > 0) {
        html += '<div class="expense-splits">';
        html += '<div class="expense-splits-title">분담 내역</div>';
        exp.splits.forEach(s => {
            html +=
                '<div class="split-item">' +
                '<span>' + escHtml(s.display_name || s.user_id) + '</span>' +
                '<span>' + formatAmount(s.amount, exp.currency) + '</span>' +
                '</div>';
        });
        html += '</div>';
    }

    html +=
        '<div class="expense-actions">' +
        '<button class="btn btn-sm btn-secondary" onclick="editExpense(' + exp.id + ')">수정</button>' +
        '<button class="btn btn-sm btn-danger" onclick="deleteExpense(' + exp.id + ')">삭제</button>' +
        '</div>' +
        '</div>';

    return html;
}

function renderIncomeCard(inc) {
    const desc = inc.description || '(설명 없음)';
    const userName = inc.user_name || inc.user_id;
    const dateStr = inc.income_date || '';
    const typeLabel = { budget: '예산 충당', refund: '환불', other: '기타' };

    let html =
        '<div class="card expense-card income-card" data-income-id="' + inc.id + '">' +
        '<div class="expense-header">' +
        '<span class="expense-desc">' + escHtml(desc) + '</span>' +
        '<span class="expense-amount">+' + formatAmount(inc.amount, inc.currency) + '</span>' +
        '</div>' +
        '<div class="expense-meta">' +
        '<span class="expense-meta-item">' + escHtml(userName) + '</span>';

    if (dateStr) {
        html += '<span class="expense-meta-item">' + escHtml(dateStr) + '</span>';
    }

    html +=
        '<span class="income-badge">' + (typeLabel[inc.type] || '기타') + '</span>' +
        '</div>' +
        '<div class="expense-actions">' +
        '<button class="btn btn-sm btn-secondary" onclick="editIncome(' + inc.id + ')">수정</button>' +
        '<button class="btn btn-sm btn-danger" onclick="deleteIncome(' + inc.id + ')">삭제</button>' +
        '</div>' +
        '</div>';

    return html;
}

// --- 지출 모달 (sheet) ---
function initExpenseModal() {
    document.getElementById('expenseDutch').addEventListener('change', (e) => {
        document.getElementById('dutchSection').style.display = e.target.checked ? 'block' : 'none';
    });

    document.getElementById('expenseAmount').addEventListener('input', () => {
        if (document.getElementById('expenseDutch').checked) {
            equalSplit();
        }
    });

    document.getElementById('btnSelectAll').addEventListener('click', () => {
        document.querySelectorAll('.dutch-check').forEach(cb => { cb.checked = true; });
        equalSplit();
    });

    document.getElementById('btnEqualSplit').addEventListener('click', equalSplit);

    document.querySelectorAll('.dutch-check').forEach(cb => {
        cb.addEventListener('change', () => { equalSplit(); });
    });

    document.querySelectorAll('.dutch-amount').forEach(input => {
        input.addEventListener('input', updateDutchTotal);
    });
}

function openExpenseModal(exp) {
    const title = document.getElementById('expenseModalTitle');
    const today = new Date().toISOString().slice(0, 10);

    if (exp) {
        title.textContent = '지출 수정';
        document.getElementById('expenseEditId').value = exp.id;
        document.getElementById('expensePaidBy').value = exp.paid_by || '';
        document.getElementById('expenseAmount').value = exp.amount || '';
        document.getElementById('expenseCurrency').value = exp.currency || 'KRW';
        document.getElementById('expenseDescription').value = exp.description || '';
        document.getElementById('expenseDate').value = exp.expense_date || '';
        document.getElementById('expenseDutch').checked = exp.is_dutch === 1;
        document.getElementById('dutchSection').style.display = exp.is_dutch === 1 ? 'block' : 'none';

        if (exp.is_dutch === 1 && exp.splits && exp.splits.length > 0) {
            document.querySelectorAll('.dutch-check').forEach(cb => { cb.checked = false; });
            document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });

            exp.splits.forEach(s => {
                const memberDiv = document.querySelector('.dutch-member[data-user-id="' + s.user_id + '"]');
                if (memberDiv) {
                    memberDiv.querySelector('.dutch-check').checked = true;
                    memberDiv.querySelector('.dutch-amount').value = s.amount || '';
                }
            });
            updateDutchTotal();
        } else {
            resetDutchSection();
        }
    } else {
        title.textContent = '지출 추가';
        document.getElementById('expenseEditId').value = '';
        document.getElementById('expensePaidBy').value = BC.userId;
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseCurrency').value = 'KRW';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('expenseDate').value = today;
        document.getElementById('expenseDutch').checked = true;
        document.getElementById('dutchSection').style.display = 'block';
        resetDutchSection();
    }

    _showModal('expenseOverlay', 'expenseSheet');
}

function closeExpenseModal() {
    _hideModal('expenseOverlay', 'expenseSheet');
}

function resetDutchSection() {
    document.querySelectorAll('.dutch-check').forEach(cb => { cb.checked = true; });
    document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });
    updateDutchTotal();
}

function equalSplit() {
    const totalAmount = parseInt(document.getElementById('expenseAmount').value, 10) || 0;
    const checkedBoxes = document.querySelectorAll('.dutch-check:checked');
    const count = checkedBoxes.length;

    if (count === 0 || totalAmount === 0) {
        document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });
        updateDutchTotal();
        return;
    }

    const perPerson = Math.floor(totalAmount / count);
    const remainder = totalAmount - (perPerson * count);

    document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });

    let idx = 0;
    checkedBoxes.forEach(cb => {
        const memberDiv = cb.closest('.dutch-member');
        const amountInput = memberDiv.querySelector('.dutch-amount');
        amountInput.value = perPerson + (idx < remainder ? 1 : 0);
        idx++;
    });

    updateDutchTotal();
}

function updateDutchTotal() {
    let total = 0;
    document.querySelectorAll('.dutch-member').forEach(div => {
        const cb = div.querySelector('.dutch-check');
        const input = div.querySelector('.dutch-amount');
        if (cb.checked) {
            total += parseInt(input.value, 10) || 0;
        }
    });

    const currency = document.getElementById('expenseCurrency').value;
    document.getElementById('dutchTotalAmount').textContent = formatAmount(total, currency);

    const expenseAmount = parseInt(document.getElementById('expenseAmount').value, 10) || 0;
    const badge = document.getElementById('dutchDiffBadge');

    if (expenseAmount > 0 && total !== expenseAmount) {
        const diff = total - expenseAmount;
        badge.classList.remove('hidden');
        if (diff > 0) {
            badge.textContent = '+' + formatAmount(diff, currency);
            badge.className = 'dutch-diff over';
        } else {
            badge.textContent = formatAmount(diff, currency);
            badge.className = 'dutch-diff under';
        }
    } else {
        badge.classList.add('hidden');
    }
}

async function saveExpense() {
    const editId = document.getElementById('expenseEditId').value;
    const paidBy = document.getElementById('expensePaidBy').value;
    const amount = parseInt(document.getElementById('expenseAmount').value, 10) || 0;
    const currency = document.getElementById('expenseCurrency').value;
    const description = document.getElementById('expenseDescription').value.trim();
    const expenseDate = document.getElementById('expenseDate').value;
    const isDutch = document.getElementById('expenseDutch').checked ? 1 : 0;

    if (!paidBy) { WP.toast('결제자를 선택해주세요.', 'error'); return; }
    if (amount <= 0) { WP.toast('금액을 입력해주세요.', 'error'); return; }

    const splits = [];
    if (isDutch) {
        document.querySelectorAll('.dutch-member').forEach(div => {
            const cb = div.querySelector('.dutch-check');
            const amountInput = div.querySelector('.dutch-amount');
            if (cb.checked) {
                const splitAmount = parseInt(amountInput.value, 10) || 0;
                if (splitAmount > 0) {
                    splits.push({ user_id: cb.value, amount: splitAmount });
                }
            }
        });

        if (splits.length === 0) {
            WP.toast('분담 인원을 선택하고 금액을 입력해주세요.', 'error');
            return;
        }
    }

    const body = {
        csrf_token: BC.csrfToken,
        trip_code: BC.tripCode,
        category_id: null,
        paid_by: paidBy,
        amount: amount,
        currency: currency,
        description: description,
        expense_date: expenseDate || null,
        is_dutch: isDutch,
        splits: splits,
    };

    try {
        let data;
        if (editId) {
            body.id = parseInt(editId, 10);
            data = await WP.put('/api/budget/expenses', body);
        } else {
            data = await WP.post('/api/budget/expenses', body);
        }

        if (data.success) {
            WP.toast(data.message || '저장되었습니다.');
            closeExpenseModal();
            loadExpenses();
            if (settlementLoaded) Settlement.loadData();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function editExpense(id) {
    const exp = expenses.find(e => e.id === id || e.id === String(id));
    if (!exp) return;
    openExpenseModal(exp);
}

async function deleteExpense(id) {
    if (!WP.confirm('이 지출 내역을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/budget/expenses?csrf_token=' + BC.csrfToken +
            '&id=' + id + '&trip_code=' + BC.tripCode
        );

        if (data.success) {
            WP.toast(data.message || '삭제되었습니다.');
            loadExpenses();
            if (settlementLoaded) Settlement.loadData();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// ========================
// 수입 CRUD
// ========================

async function loadIncomes() {
    try {
        const data = await WP.api('/api/budget/incomes?trip_code=' + BC.tripCode);
        if (data.success) {
            incomes = data.data.incomes || [];
            renderExpenses(); // 지출 목록에 수입도 포함
        }
    } catch {
        incomes = [];
    }
}

function openIncomeModal(inc) {
    const title = document.getElementById('incomeModalTitle');
    const today = new Date().toISOString().slice(0, 10);

    if (inc) {
        title.textContent = '수입 수정';
        document.getElementById('incomeEditId').value = inc.id;
        document.getElementById('incomeType').value = inc.type || 'other';
        document.getElementById('incomeUserId').value = inc.user_id || BC.userId;
        document.getElementById('incomeAmount').value = inc.amount || '';
        document.getElementById('incomeCurrency').value = inc.currency || 'KRW';
        document.getElementById('incomeDescription').value = inc.description || '';
        document.getElementById('incomeDate').value = inc.income_date || '';
    } else {
        title.textContent = '수입 추가';
        document.getElementById('incomeEditId').value = '';
        document.getElementById('incomeType').value = 'other';
        document.getElementById('incomeUserId').value = BC.userId;
        document.getElementById('incomeAmount').value = '';
        document.getElementById('incomeCurrency').value = 'KRW';
        document.getElementById('incomeDescription').value = '';
        document.getElementById('incomeDate').value = today;
    }

    _showModal('incomeOverlay', 'incomeSheet');
}

function closeIncomeModal() {
    _hideModal('incomeOverlay', 'incomeSheet');
}

async function saveIncome() {
    const editId = document.getElementById('incomeEditId').value;
    const amount = parseInt(document.getElementById('incomeAmount').value, 10) || 0;

    if (amount <= 0) { WP.toast('금액을 입력해주세요.', 'error'); return; }

    const body = {
        csrf_token: BC.csrfToken,
        trip_code: BC.tripCode,
        user_id: document.getElementById('incomeUserId').value,
        amount: amount,
        currency: document.getElementById('incomeCurrency').value,
        type: document.getElementById('incomeType').value,
        description: document.getElementById('incomeDescription').value.trim(),
        income_date: document.getElementById('incomeDate').value || null,
    };

    try {
        let data;
        if (editId) {
            body.id = parseInt(editId, 10);
            data = await WP.put('/api/budget/incomes', body);
        } else {
            data = await WP.post('/api/budget/incomes', body);
        }

        if (data.success) {
            WP.toast(data.message || '저장되었습니다.');
            closeIncomeModal();
            loadIncomes();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function editIncome(id) {
    const inc = incomes.find(i => i.id === id || i.id === String(id));
    if (!inc) return;
    openIncomeModal(inc);
}

async function deleteIncome(id) {
    if (!WP.confirm('이 수입 내역을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/budget/incomes?csrf_token=' + BC.csrfToken +
            '&id=' + id + '&trip_code=' + BC.tripCode
        );

        if (data.success) {
            WP.toast(data.message || '삭제되었습니다.');
            loadIncomes();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// ========================
// Settlement (정산) 통합
// ========================

const Settlement = {
    data: null,
    exchangeRate: null,
    completedTransfers: {},

    async init() {
        this.loadCompletedTransfers();
        await this.loadData();
    },

    async loadData() {
        try {
            const result = await WP.api(
                '/api/settlement?trip_code=' + encodeURIComponent(BC.tripCode)
            );

            if (!result.success) {
                WP.toast(result.message, 'error');
                return;
            }

            this.data = result.data;
            document.getElementById('settlementLoading').classList.add('hidden');

            if (!this.data.currencies || this.data.currencies.length === 0) {
                document.getElementById('settlementEmpty').classList.remove('hidden');
                document.getElementById('settlementContent').classList.add('hidden');
                return;
            }

            document.getElementById('settlementEmpty').classList.add('hidden');
            document.getElementById('settlementContent').classList.remove('hidden');

            if (this.data.has_multiple) {
                document.getElementById('exchangeRateSection').classList.remove('hidden');
            }

            this.render();
        } catch (err) {
            document.getElementById('settlementLoading').classList.add('hidden');
            WP.toast(err.message, 'error');
        }
    },

    render() {
        if (!this.data) return;

        if (this.exchangeRate && this.data.has_multiple) {
            this.renderUnified();
        } else {
            this.renderByCurrency();
        }
    },

    renderByCurrency() {
        const { members, settlements, currencies, member_names } = this.data;

        let summaryHtml = '';
        for (const member of members) {
            summaryHtml += this.renderMemberSummary(member, currencies);
        }
        document.getElementById('memberSummaryList').innerHTML = summaryHtml;

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

    renderUnified() {
        const { members, settlements, currencies, member_names } = this.data;
        const rate = this.exchangeRate;

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

        let summaryHtml = '<div class="unified-notice">환율 1 USD = ' + WP.formatMoney(rate, 'KRW').replace('원', '') + '원 기준 통합 정산</div>';
        for (const member of members) {
            const uid = member.user_id;
            const bal = unifiedBalances[uid];
            summaryHtml += this.renderUnifiedMemberSummary(member, bal);
        }
        document.getElementById('memberSummaryList').innerHTML = summaryHtml;

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

    renderMemberSummary(member, currencies) {
        let html = '<div class="member-summary-item">';
        html += '<div class="member-name">' + escHtml(member.display_name);
        if (member.is_owner) html += ' <span class="owner-badge">오너</span>';
        html += '</div>';

        for (const currency of currencies) {
            const bal = member.by_currency[currency] || { paid: 0, owed: 0, net: 0 };
            if (currencies.length > 1) {
                html += '<div style="font-size:0.75rem; color:var(--color-primary); margin-top:4px; font-weight:600;">' + this.currencyLabel(currency) + '</div>';
            }
            html += '<div class="member-balance-row"><span>결제한 금액</span><span class="balance-amount">' + WP.formatMoney(bal.paid, currency) + '</span></div>';
            html += '<div class="member-balance-row"><span>부담해야 할 금액</span><span class="balance-amount">' + WP.formatMoney(bal.owed, currency) + '</span></div>';

            const netClass = bal.net > 0 ? 'balance-positive' : (bal.net < 0 ? 'balance-negative' : 'balance-zero');
            const netLabel = bal.net > 0 ? '받을 금액' : (bal.net < 0 ? '줄 금액' : '정산 완료');
            html += '<div class="net-balance"><span>' + netLabel + '</span><span class="' + netClass + '">' + WP.formatMoney(Math.abs(bal.net), currency) + '</span></div>';
        }

        html += '</div>';
        return html;
    },

    renderUnifiedMemberSummary(member, bal) {
        let html = '<div class="member-summary-item">';
        html += '<div class="member-name">' + escHtml(member.display_name);
        if (member.is_owner) html += ' <span class="owner-badge">오너</span>';
        html += '</div>';

        html += '<div class="member-balance-row"><span>결제한 금액</span><span class="balance-amount">' + WP.formatMoney(bal.paid, 'KRW') + '</span></div>';
        html += '<div class="member-balance-row"><span>부담해야 할 금액</span><span class="balance-amount">' + WP.formatMoney(bal.owed, 'KRW') + '</span></div>';

        const netClass = bal.net > 0 ? 'balance-positive' : (bal.net < 0 ? 'balance-negative' : 'balance-zero');
        const netLabel = bal.net > 0 ? '받을 금액' : (bal.net < 0 ? '줄 금액' : '정산 완료');
        html += '<div class="net-balance"><span>' + netLabel + '</span><span class="' + netClass + '">' + WP.formatMoney(Math.abs(bal.net), 'KRW') + '</span></div>';

        html += '</div>';
        return html;
    },

    renderTransferItem(transfer, currency, memberNames) {
        const key = transfer.from + '-' + transfer.to + '-' + currency;
        const isCompleted = !!this.completedTransfers[key];
        const completedClass = isCompleted ? ' completed' : '';
        const checkedAttr = isCompleted ? ' checked' : '';

        const fromName = memberNames[transfer.from] || transfer.from;
        const toName = memberNames[transfer.to] || transfer.to;

        let html = '<div class="transfer-item' + completedClass + '" data-key="' + key + '">';
        html += '<div class="transfer-info"><div class="transfer-from-to">';
        html += '<strong>' + escHtml(fromName) + '</strong>';
        html += '<span class="transfer-arrow">&rarr;</span>';
        html += '<strong>' + escHtml(toName) + '</strong>';
        html += '</div></div>';
        html += '<div class="transfer-amount">' + WP.formatMoney(transfer.amount, currency) + '</div>';
        html += '<div class="transfer-check">';
        html += '<input type="checkbox" title="정산 완료" onchange="Settlement.toggleComplete(\'' + key + '\')"' + checkedAttr + '>';
        html += '</div></div>';

        return html;
    },

    applyExchangeRate() {
        const input = document.getElementById('settlementExchangeRate');
        const rate = parseInt(input.value, 10);

        if (!rate || rate <= 0) {
            WP.toast('올바른 환율을 입력해주세요.', 'error');
            return;
        }

        this.exchangeRate = rate;
        this.render();
        WP.toast('환율이 적용되었습니다.');
    },

    resetExchangeRate() {
        this.exchangeRate = null;
        document.getElementById('settlementExchangeRate').value = '';
        this.render();
        WP.toast('통화별 분리 정산으로 변경되었습니다.');
    },

    toggleComplete(key) {
        if (this.completedTransfers[key]) {
            delete this.completedTransfers[key];
        } else {
            this.completedTransfers[key] = true;
        }

        this.saveCompletedTransfers();

        const item = document.querySelector('.transfer-item[data-key="' + key + '"]');
        if (item) item.classList.toggle('completed');
    },

    saveCompletedTransfers() {
        localStorage.setItem('settlement_' + BC.tripCode, JSON.stringify(this.completedTransfers));
    },

    loadCompletedTransfers() {
        try {
            const saved = localStorage.getItem('settlement_' + BC.tripCode);
            if (saved) this.completedTransfers = JSON.parse(saved);
        } catch {
            this.completedTransfers = {};
        }
    },

    calculateMinTransfers(netBalances) {
        const creditors = [];
        const debtors = [];

        for (const uid in netBalances) {
            const amount = netBalances[uid];
            if (amount > 0) creditors.push({ user_id: uid, amount: amount });
            else if (amount < 0) debtors.push({ user_id: uid, amount: Math.abs(amount) });
        }

        creditors.sort((a, b) => b.amount - a.amount);
        debtors.sort((a, b) => b.amount - a.amount);

        const transfers = [];
        let ci = 0, di = 0;

        while (ci < creditors.length && di < debtors.length) {
            const transferAmount = Math.min(creditors[ci].amount, debtors[di].amount);
            if (transferAmount > 0) {
                transfers.push({ from: debtors[di].user_id, to: creditors[ci].user_id, amount: transferAmount });
            }
            creditors[ci].amount -= transferAmount;
            debtors[di].amount -= transferAmount;
            if (creditors[ci].amount === 0) ci++;
            if (debtors[di].amount === 0) di++;
        }

        return transfers;
    },

    currencyLabel(currency) {
        const labels = { 'KRW': 'KRW (원)', 'USD': 'USD ($)' };
        return labels[currency] || currency;
    },
};

// ========================
// ESC 키로 모달 닫기
// ========================
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeExpenseModal();
        closeIncomeModal();
    }
});

// ========================
// 유틸
// ========================
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

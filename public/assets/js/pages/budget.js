/**
 * 예산 관리 페이지 JS
 *
 * BUDGET_CONFIG = { tripCode, userId, csrfToken, members: [{user_id, display_name}] }
 */
const BC = window.BUDGET_CONFIG;

// --- 상태 ---
let categories = [];
let expenses = [];
let exchangeRate = 0; // 0이면 미입력 상태

// ========================
// 초기화
// ========================
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initCategoryModal();
    initExpenseModal();
    initExchangeRate();
    loadCategories();
    loadExpenses();
});

// ========================
// 탭 전환
// ========================
function initTabs() {
    document.querySelectorAll('.budget-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            // 탭 버튼 활성화
            document.querySelectorAll('.budget-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // 패널 전환
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            const targetId = tab.dataset.tab === 'plan' ? 'tabPlan' : 'tabExpenses';
            document.getElementById(targetId).classList.add('active');
        });
    });
}

// ========================
// 환율 설정
// ========================
function initExchangeRate() {
    const input = document.getElementById('exchangeRate');
    const saved = localStorage.getItem('wp_exchange_rate_' + BC.tripCode);
    if (saved) {
        exchangeRate = parseInt(saved, 10);
        input.value = exchangeRate || '';
    }

    input.addEventListener('change', () => {
        exchangeRate = parseInt(input.value, 10) || 0;
        localStorage.setItem('wp_exchange_rate_' + BC.tripCode, exchangeRate);
        renderCategories();
    });
}

/**
 * 금액을 KRW로 변환 (환율 적용)
 */
function toKRW(amount, currency) {
    if (currency === 'KRW') return amount;
    if (currency === 'USD' && exchangeRate > 0) return amount * exchangeRate;
    return amount; // 환율 미설정 시 원본 반환
}

/**
 * 금액 포맷 (통화 표시)
 */
function formatAmount(amount, currency) {
    if (currency === 'USD') {
        return '$' + Number(amount).toLocaleString('en-US');
    }
    return Number(amount).toLocaleString('ko-KR') + '원';
}

// ========================
// 카테고리 CRUD
// ========================

async function loadCategories() {
    try {
        const data = await WP.api('/api/budget/categories?trip_code=' + BC.tripCode);
        if (data.success) {
            categories = data.data.categories;
            renderCategories();
            updateCategorySelect();
        }
    } catch (err) {
        document.getElementById('categoryList').innerHTML =
            '<div class="card text-center text-muted text-sm">카테고리를 불러올 수 없습니다.</div>';
    }
}

function renderCategories() {
    const container = document.getElementById('categoryList');

    if (categories.length === 0) {
        container.innerHTML =
            '<div class="empty-state">' +
                '<div class="empty-state-icon">&#128202;</div>' +
                '<div class="empty-state-text">카테고리를 추가해주세요.<br>항공, 숙박, 식비 등 예산을 관리할 수 있습니다.</div>' +
            '</div>';
        updateTotalSummary(0, 0);
        return;
    }

    let totalPlanned = 0;
    let totalSpent = 0;
    let html = '';

    categories.forEach(cat => {
        const planned = cat.planned_amount;
        const spent = cat.spent_amount;
        const percent = planned > 0 ? Math.min(Math.round(spent / planned * 100), 999) : (spent > 0 ? 100 : 0);
        const isOver = planned > 0 && spent > planned;

        // 환율 적용해서 총합 계산
        totalPlanned += toKRW(planned, cat.currency);
        totalSpent += toKRW(spent, cat.currency);

        html +=
            '<div class="card category-card">' +
                '<div class="category-header">' +
                    '<span class="category-name">' + escHtml(cat.name) + '</span>' +
                    '<div class="category-actions">' +
                        '<button class="btn-icon" onclick="editCategory(' + cat.id + ')" title="수정">&#9998;</button>' +
                        '<button class="btn-icon danger" onclick="deleteCategory(' + cat.id + ')" title="삭제">&#128465;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="category-amounts">' +
                    '<span>계획: ' + formatAmount(planned, cat.currency) + '</span>' +
                    '<span>지출: ' + formatAmount(spent, cat.currency) + '</span>' +
                '</div>' +
                '<div class="category-bar-wrap">' +
                    '<div class="category-bar-fill' + (isOver ? ' over-budget' : '') + '" style="width: ' + Math.min(percent, 100) + '%;"></div>' +
                '</div>' +
                '<div class="text-sm text-muted" style="text-align: right; margin-top: 4px;">' + percent + '%</div>' +
            '</div>';
    });

    container.innerHTML = html;
    updateTotalSummary(totalPlanned, totalSpent);
}

function updateTotalSummary(totalPlanned, totalSpent) {
    document.getElementById('totalPlanned').textContent = Number(totalPlanned).toLocaleString('ko-KR') + '원';
    document.getElementById('totalSpent').textContent = Number(totalSpent).toLocaleString('ko-KR') + '원';

    const percent = totalPlanned > 0 ? Math.min(Math.round(totalSpent / totalPlanned * 100), 100) : 0;
    const fill = document.getElementById('totalProgressFill');
    fill.style.width = percent + '%';

    if (totalPlanned > 0 && totalSpent > totalPlanned) {
        fill.classList.add('over-budget');
    } else {
        fill.classList.remove('over-budget');
    }

    document.getElementById('totalProgressText').textContent = percent + '%';
}

function updateCategorySelect() {
    const select = document.getElementById('expenseCategory');
    // 기존 옵션 제거 (첫 번째 "선택 안 함" 유지)
    while (select.options.length > 1) {
        select.remove(1);
    }
    categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        select.appendChild(opt);
    });
}

// --- 카테고리 모달 ---
function initCategoryModal() {
    document.getElementById('btnAddCategory').addEventListener('click', () => openCategoryModal());
    document.getElementById('categoryModalClose').addEventListener('click', closeCategoryModal);
    document.getElementById('categoryModalCancel').addEventListener('click', closeCategoryModal);
    document.getElementById('categoryModalSave').addEventListener('click', saveCategory);

    // 오버레이 클릭으로 닫기
    document.getElementById('categoryModal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCategoryModal();
    });
}

function openCategoryModal(cat) {
    const modal = document.getElementById('categoryModal');
    const title = document.getElementById('categoryModalTitle');

    if (cat) {
        title.textContent = '카테고리 수정';
        document.getElementById('categoryEditId').value = cat.id;
        document.getElementById('categoryName').value = cat.name;
        document.getElementById('categoryAmount').value = cat.planned_amount || '';
        document.getElementById('categoryCurrency').value = cat.currency || 'KRW';
    } else {
        title.textContent = '카테고리 추가';
        document.getElementById('categoryEditId').value = '';
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryAmount').value = '';
        document.getElementById('categoryCurrency').value = 'KRW';
    }

    modal.classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

async function saveCategory() {
    const editId = document.getElementById('categoryEditId').value;
    const name = document.getElementById('categoryName').value.trim();
    const amount = parseInt(document.getElementById('categoryAmount').value, 10) || 0;
    const currency = document.getElementById('categoryCurrency').value;

    if (!name) {
        WP.toast('카테고리 이름을 입력해주세요.', 'error');
        return;
    }

    const body = {
        csrf_token: BC.csrfToken,
        trip_code: BC.tripCode,
        name: name,
        planned_amount: amount,
        currency: currency,
    };

    try {
        let data;
        if (editId) {
            body.id = parseInt(editId, 10);
            data = await WP.put('/api/budget/categories', body);
        } else {
            data = await WP.post('/api/budget/categories', body);
        }

        if (data.success) {
            WP.toast(data.message || '저장되었습니다.');
            closeCategoryModal();
            loadCategories();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function editCategory(id) {
    const cat = categories.find(c => c.id === id || c.id === String(id));
    if (!cat) return;
    openCategoryModal(cat);
}

async function deleteCategory(id) {
    if (!WP.confirm('이 카테고리를 삭제하시겠습니까?\n연결된 지출은 유지되지만 카테고리 분류가 해제됩니다.')) return;

    try {
        const data = await WP.delete(
            '/api/budget/categories?csrf_token=' + BC.csrfToken +
            '&id=' + id + '&trip_code=' + BC.tripCode
        );

        if (data.success) {
            WP.toast(data.message || '삭제되었습니다.');
            loadCategories();
            loadExpenses();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
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

    if (expenses.length === 0) {
        container.innerHTML =
            '<div class="empty-state">' +
                '<div class="empty-state-icon">&#128179;</div>' +
                '<div class="empty-state-text">아직 지출 내역이 없습니다.<br>위의 버튼으로 지출을 추가해주세요.</div>' +
            '</div>';
        return;
    }

    let html = '';
    expenses.forEach(exp => {
        const desc = exp.description || '(설명 없음)';
        const paidByName = exp.paid_by_name || exp.paid_by;
        const catName = exp.category_name || '';
        const dateStr = exp.expense_date || '';
        const isDutch = exp.is_dutch === 1;

        html +=
            '<div class="card expense-card" data-expense-id="' + exp.id + '">' +
                '<div class="expense-header">' +
                    '<span class="expense-desc">' + escHtml(desc) + '</span>' +
                    '<span class="expense-amount">' + formatAmount(exp.amount, exp.currency) + '</span>' +
                '</div>' +
                '<div class="expense-meta">';

        if (catName) {
            html += '<span class="expense-meta-item">' + escHtml(catName) + '</span>';
        }

        html +=
                    '<span class="expense-meta-item">' + escHtml(paidByName) + ' 결제</span>';

        if (dateStr) {
            html += '<span class="expense-meta-item">' + escHtml(dateStr) + '</span>';
        }

        html +=
                    '<span class="expense-badge ' + (isDutch ? 'badge-dutch' : 'badge-solo') + '">' +
                        (isDutch ? '분담' : '개인') +
                    '</span>' +
                '</div>';

        // 더치페이 분담 내역
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

        // 수정/삭제 버튼
        html +=
                '<div class="expense-actions">' +
                    '<button class="btn btn-sm btn-secondary" onclick="editExpense(' + exp.id + ')">수정</button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deleteExpense(' + exp.id + ')">삭제</button>' +
                '</div>' +
            '</div>';
    });

    container.innerHTML = html;
}

// --- 지출 모달 ---
function initExpenseModal() {
    document.getElementById('btnAddExpense').addEventListener('click', () => openExpenseModal());
    document.getElementById('expenseModalClose').addEventListener('click', closeExpenseModal);
    document.getElementById('expenseModalCancel').addEventListener('click', closeExpenseModal);
    document.getElementById('expenseModalSave').addEventListener('click', saveExpense);

    // 오버레이 클릭
    document.getElementById('expenseModal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeExpenseModal();
    });

    // 더치페이 체크박스
    document.getElementById('expenseDutch').addEventListener('change', (e) => {
        document.getElementById('dutchSection').style.display = e.target.checked ? 'block' : 'none';
    });

    // 금액 변경 시 균등 분배 자동 갱신
    document.getElementById('expenseAmount').addEventListener('input', () => {
        if (document.getElementById('expenseDutch').checked) {
            equalSplit();
        }
    });

    // 전체 선택
    document.getElementById('btnSelectAll').addEventListener('click', () => {
        document.querySelectorAll('.dutch-check').forEach(cb => {
            cb.checked = true;
        });
        equalSplit();
    });

    // 균등 분배
    document.getElementById('btnEqualSplit').addEventListener('click', equalSplit);

    // 개별 체크 변경 시 재분배
    document.querySelectorAll('.dutch-check').forEach(cb => {
        cb.addEventListener('change', () => {
            equalSplit();
        });
    });

    // 분담 금액 수동 입력 시 합계 업데이트
    document.querySelectorAll('.dutch-amount').forEach(input => {
        input.addEventListener('input', updateDutchTotal);
    });
}

function openExpenseModal(exp) {
    const modal = document.getElementById('expenseModal');
    const title = document.getElementById('expenseModalTitle');

    // 카테고리 select 갱신
    updateCategorySelect();

    // 오늘 날짜 기본값
    const today = new Date().toISOString().slice(0, 10);

    if (exp) {
        // 수정 모드
        title.textContent = '지출 수정';
        document.getElementById('expenseEditId').value = exp.id;
        document.getElementById('expenseCategory').value = exp.category_id || '';
        document.getElementById('expensePaidBy').value = exp.paid_by || '';
        document.getElementById('expenseAmount').value = exp.amount || '';
        document.getElementById('expenseCurrency').value = exp.currency || 'KRW';
        document.getElementById('expenseDescription').value = exp.description || '';
        document.getElementById('expenseDate').value = exp.expense_date || '';
        document.getElementById('expenseDutch').checked = exp.is_dutch === 1;
        document.getElementById('dutchSection').style.display = exp.is_dutch === 1 ? 'block' : 'none';

        // 분담 내역 복원
        if (exp.is_dutch === 1 && exp.splits && exp.splits.length > 0) {
            // 모든 체크 해제
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
        // 추가 모드
        title.textContent = '지출 추가';
        document.getElementById('expenseEditId').value = '';
        document.getElementById('expenseCategory').value = '';
        document.getElementById('expensePaidBy').value = BC.userId;
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseCurrency').value = 'KRW';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('expenseDate').value = today;
        document.getElementById('expenseDutch').checked = true;
        document.getElementById('dutchSection').style.display = 'block';
        resetDutchSection();
    }

    modal.classList.remove('hidden');
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
}

function resetDutchSection() {
    document.querySelectorAll('.dutch-check').forEach(cb => { cb.checked = true; });
    document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });
    updateDutchTotal();
}

/**
 * 균등 분배
 */
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

    // 모든 금액 초기화
    document.querySelectorAll('.dutch-amount').forEach(input => { input.value = ''; });

    // 체크된 인원에게 분배
    let idx = 0;
    checkedBoxes.forEach(cb => {
        const memberDiv = cb.closest('.dutch-member');
        const amountInput = memberDiv.querySelector('.dutch-amount');
        // 첫 번째 사람에게 나머지 추가
        amountInput.value = perPerson + (idx < remainder ? 1 : 0);
        idx++;
    });

    updateDutchTotal();
}

/**
 * 분담 합계 업데이트
 */
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

    // 지출 금액과 차이 표시
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
    const categoryId = document.getElementById('expenseCategory').value;
    const paidBy = document.getElementById('expensePaidBy').value;
    const amount = parseInt(document.getElementById('expenseAmount').value, 10) || 0;
    const currency = document.getElementById('expenseCurrency').value;
    const description = document.getElementById('expenseDescription').value.trim();
    const expenseDate = document.getElementById('expenseDate').value;
    const isDutch = document.getElementById('expenseDutch').checked ? 1 : 0;

    if (!paidBy) {
        WP.toast('결제자를 선택해주세요.', 'error');
        return;
    }

    if (amount <= 0) {
        WP.toast('금액을 입력해주세요.', 'error');
        return;
    }

    // 분담 내역 수집
    const splits = [];
    if (isDutch) {
        document.querySelectorAll('.dutch-member').forEach(div => {
            const cb = div.querySelector('.dutch-check');
            const amountInput = div.querySelector('.dutch-amount');
            if (cb.checked) {
                const splitAmount = parseInt(amountInput.value, 10) || 0;
                if (splitAmount > 0) {
                    splits.push({
                        user_id: cb.value,
                        amount: splitAmount,
                    });
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
        category_id: categoryId || null,
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
            loadCategories(); // 카테고리별 지출 합계도 갱신
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
            loadCategories();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// ========================
// 유틸
// ========================
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

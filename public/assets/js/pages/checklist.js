/**
 * 체크리스트 페이지 JS (준비물 + 할일 통합)
 */

let currentTab = location.hash === '#todo' ? 'todo' : (sessionStorage.getItem('checklistTab') || 'checklist');

// ===== 탭 전환 =====
function switchTab(tabName) {
    currentTab = tabName;
    sessionStorage.setItem('checklistTab', tabName);

    // 탭 버튼 활성화
    document.querySelectorAll('.page-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    // 탭 콘텐츠 표시/숨김
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.toggle('active', pane.id === `tab${tabName.charAt(0).toUpperCase() + tabName.slice(1)}`);
    });

    // 헤더 배지 업데이트
    if (tabName === 'checklist') {
        document.getElementById('headerBadge').textContent = (document.querySelectorAll('.checklist-item.done').length) + '/' + document.querySelectorAll('.checklist-item').length + '%';
    } else {
        const todoDone = document.querySelectorAll('.todo-item.done').length;
        const todoTotal = document.querySelectorAll('.todo-item').length;
        document.getElementById('headerBadge').textContent = todoDone + '/' + todoTotal;
    }
}

// 탭 버튼 클릭 리스너
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.page-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            switchTab(btn.dataset.tab);
        });
    });

    // 저장된 탭 복원
    if (currentTab !== 'checklist') {
        switchTab(currentTab);
    }

    // 네비게이션 체크 버튼 클릭 (탭 전환)
    const navChecklist = document.getElementById('navChecklist');
    if (navChecklist) {
        navChecklist.addEventListener('click', e => {
            e.preventDefault();
            const tabs = ['checklist', 'todo'];
            const currentIndex = tabs.indexOf(currentTab);
            const nextTab = tabs[(currentIndex + 1) % tabs.length];
            switchTab(nextTab);
        });
    }
});

// ===== 준비물 - 모달 열기/닫기 =====
function openAddModal() {
    const isChecklist = currentTab === 'checklist';
    document.getElementById('addChecklistForm').style.display = isChecklist ? 'block' : 'none';
    document.getElementById('addTodoForm').style.display = isChecklist ? 'none' : 'block';
    document.getElementById('addModalTitle').textContent = isChecklist ? '준비물 추가' : '할 일 추가';

    const overlay = document.getElementById('addOverlay');
    const modal = document.getElementById('addModal');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.add('visible');
        modal.classList.add('visible');
    });

    const firstInput = isChecklist ? document.getElementById('addItem') : document.getElementById('addTitle');
    firstInput.focus();
}

function closeAddModal() {
    const overlay = document.getElementById('addOverlay');
    const modal = document.getElementById('addModal');
    overlay.classList.remove('visible');
    modal.classList.remove('visible');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
        // 준비물 폼 초기화
        document.getElementById('addCategory').value = '';
        document.getElementById('addItem').value = '';
        clearAssignees('addAssigneeGroup');
        // 할일 폼 초기화
        document.getElementById('addTitle').value = '';
        document.getElementById('addDetail').value = '';
        document.getElementById('addDueDate').value = '';
        clearAssignees('addTodoAssigneeGroup');
    }, 250);
}

function openEditModal() {
    const isChecklist = currentTab === 'checklist';
    document.getElementById('editChecklistForm').style.display = isChecklist ? 'block' : 'none';
    document.getElementById('editTodoForm').style.display = isChecklist ? 'none' : 'block';
    document.getElementById('editModalTitle').textContent = isChecklist ? '준비물 수정' : '할 일 수정';

    const overlay = document.getElementById('editOverlay');
    const modal = document.getElementById('editModal');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.add('visible');
        modal.classList.add('visible');
    });

    const firstInput = isChecklist ? document.getElementById('editItem') : document.getElementById('editTitle');
    firstInput.focus();
}

function closeEditModal() {
    const overlay = document.getElementById('editOverlay');
    const modal = document.getElementById('editModal');
    overlay.classList.remove('visible');
    modal.classList.remove('visible');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
    }, 250);
}

// ===== 준비물 로직 =====
function toggleAssigneeBtn(btn, groupId) {
    btn.classList.toggle('selected');
}

function clearAssignees(groupId) {
    document.querySelectorAll(`#${groupId} .assignee-toggle-btn`).forEach(b => {
        b.classList.remove('selected');
    });
}

function getSelectedAssignees(groupId) {
    const selected = document.querySelectorAll(`#${groupId} .assignee-toggle-btn.selected`);
    return Array.from(selected).map(b => b.getAttribute('data-user-id')).join(',');
}

function setAssignees(groupId, assignedTo) {
    clearAssignees(groupId);
    if (!assignedTo) return;
    const ids = assignedTo.split(',').map(s => s.trim());
    document.querySelectorAll(`#${groupId} .assignee-toggle-btn`).forEach(b => {
        if (ids.includes(b.getAttribute('data-user-id'))) {
            b.classList.add('selected');
        }
    });
}

async function addItem() {
    const category = document.getElementById('addCategory').value.trim();
    const item = document.getElementById('addItem').value.trim();
    const assignedTo = getSelectedAssignees('addAssigneeGroup');

    if (!item) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        return;
    }

    try {
        const data = await WP.post('/api/checklist', {
            csrf_token: CONFIG.csrfToken,
            trip_code: CONFIG.tripCode,
            category: category,
            item: item,
            assigned_to: assignedTo
        });

        if (data.success) {
            closeAddModal();
            appendChecklistItem(data.data);
            WP.toast('추가되었습니다.');
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function toggleItem(id, checked) {
    try {
        const data = await WP.put('/api/checklist', {
            csrf_token: CONFIG.csrfToken,
            id: id,
            trip_code: CONFIG.tripCode,
            user_id: CONFIG.userId,
            is_done: checked ? 1 : 0
        });

        if (data.success) {
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                el.classList.toggle('done', checked);
                el.setAttribute('data-done', checked ? '1' : '0');
            }

            if (data.data && data.data.completedUsers) {
                updateAssigneeStatus(id, data.data.completedUsers);
            }

            updateProgress();
            applyFilters();
        } else {
            WP.toast(data.message, 'error');
            const cb = document.querySelector(`.checklist-item[data-id="${id}"] input[type="checkbox"]`);
            if (cb) cb.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        const cb = document.querySelector(`.checklist-item[data-id="${id}"] input[type="checkbox"]`);
        if (cb) cb.checked = !checked;
    }
}

function updateAssigneeStatus(itemId, completedUsers) {
    const container = document.querySelector(`.assignee-status[data-item-id="${itemId}"]`);
    if (!container) return;

    container.querySelectorAll('.badge-assignee').forEach(badge => {
        const uid = badge.getAttribute('data-uid');
        const name = badge.getAttribute('data-name') || badge.textContent.replace(' ✓', '').trim();
        badge.setAttribute('data-name', name);

        if (completedUsers.includes(uid)) {
            badge.classList.add('done');
            badge.textContent = name + ' ✓';
        } else {
            badge.classList.remove('done');
            badge.textContent = name;
        }
    });
}

function editItem(id, item, category, assignedTo) {
    document.getElementById('editId').value = id;
    document.getElementById('editItem').value = item;
    document.getElementById('editCategory').value = category;
    setAssignees('editAssigneeGroup', assignedTo);
    openEditModal();
}

async function updateItem() {
    const id = document.getElementById('editId').value;
    const item = document.getElementById('editItem').value.trim();
    const category = document.getElementById('editCategory').value.trim();
    const assignedTo = getSelectedAssignees('editAssigneeGroup');

    if (!item) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        return;
    }

    try {
        const data = await WP.put('/api/checklist', {
            csrf_token: CONFIG.csrfToken,
            id: parseInt(id),
            trip_code: CONFIG.tripCode,
            category: category,
            item: item,
            assigned_to: assignedTo
        });

        if (data.success) {
            closeEditModal();
            updateChecklistItemDOM(parseInt(id), item, category || '기타', assignedTo);
            WP.toast('수정되었습니다.');
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteItem(id) {
    if (!await WP.confirm('이 항목을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(`/api/checklist?csrf_token=${CONFIG.csrfToken}&id=${id}&trip_code=${CONFIG.tripCode}&user_id=${CONFIG.userId}`);

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateProgress();

                const catEl = el.closest('.checklist-category');
                if (catEl && catEl.querySelectorAll('.checklist-item').length === 0) {
                    catEl.remove();
                }

                if (document.querySelectorAll('.checklist-item').length === 0) {
                    document.getElementById('checklistContainer').innerHTML = '<div class="card text-center"><p class="text-muted">아직 준비물이 없습니다.</p><p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
                }
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function updateProgress() {
    const allItems = document.querySelectorAll('.checklist-item');
    const doneItems = document.querySelectorAll('.checklist-item.done');
    const total = allItems.length;
    const done = doneItems.length;
    const percent = total > 0 ? Math.round(done / total * 100) : 0;

    const fill = document.getElementById('clProgressFill');
    if (fill) fill.style.width = percent + '%';

    const countEl = document.getElementById('clCountText');
    if (countEl) countEl.textContent = done + ' / ' + total + '개';

    document.querySelectorAll('.checklist-category').forEach(catEl => {
        const catDone = catEl.querySelectorAll('.checklist-item.done').length;
        const catTotal = catEl.querySelectorAll('.checklist-item').length;
        const countSpan = catEl.querySelector('.category-count');
        if (countSpan) countSpan.textContent = catDone + '/' + catTotal;
    });
}

function applyFilters() {
    const keyword = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const status = document.getElementById('statusFilter')?.value || 'all';
    const category = document.getElementById('categoryFilter')?.value || '';
    const assignee = document.getElementById('assigneeFilter')?.value || '';

    let totalVisible = 0;

    document.querySelectorAll('.checklist-category').forEach(catEl => {
        const catName = catEl.getAttribute('data-category') || '';
        let catVisible = 0;

        if (category && catName !== category) {
            catEl.classList.add('hidden');
            return;
        }
        catEl.classList.remove('hidden');

        catEl.querySelectorAll('.checklist-item').forEach(itemEl => {
            const itemText = itemEl.getAttribute('data-item-text') || '';
            const assigned = itemEl.getAttribute('data-assigned') || '';
            const isDone = itemEl.getAttribute('data-done') === '1';

            if (status === 'done' && !isDone) { itemEl.classList.add('hidden'); return; }
            if (status === 'undone' && isDone) { itemEl.classList.add('hidden'); return; }

            if (assignee === '__none__' && assigned !== '') { itemEl.classList.add('hidden'); return; }
            if (assignee && assignee !== '__none__') {
                const assignedList = assigned.split(' ').map(s => s.trim());
                if (!assignedList.includes(assignee)) { itemEl.classList.add('hidden'); return; }
            }
            if (keyword && !itemText.includes(keyword)) { itemEl.classList.add('hidden'); return; }

            itemEl.classList.remove('hidden');
            catVisible++;
            totalVisible++;
        });

        if (catVisible === 0) catEl.classList.add('hidden');
    });

    const noResult = document.getElementById('noFilterResult');
    if (noResult) {
        const hasItems = document.querySelectorAll('.checklist-item').length > 0;
        noResult.classList.toggle('hidden', !(hasItems && totalVisible === 0));
    }
}

// ===== 할일 로직 =====
async function addTodo() {
    const title = document.getElementById('addTitle').value.trim();
    const detail = document.getElementById('addDetail').value.trim();
    const assignedTo = getSelectedAssignees('addTodoAssigneeGroup');
    const dueDate = document.getElementById('addDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        return;
    }

    try {
        const data = await WP.post('/api/todo', {
            csrf_token: CONFIG.csrfToken,
            trip_code: CONFIG.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate
        });

        if (data.success) {
            closeAddModal();
            appendTodoItem(data.data);
            WP.toast('추가되었습니다.');
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function toggleTodo(id, checked) {
    try {
        const data = await WP.put('/api/todo', {
            csrf_token: CONFIG.csrfToken,
            id: id,
            trip_code: CONFIG.tripCode,
            user_id: CONFIG.userId,
            is_done: checked ? 1 : 0
        });

        if (data.success) {
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.classList.toggle('done', checked);
                el.setAttribute('data-done', checked ? '1' : '0');
            }

            if (data.data && data.data.completedUsers) {
                updateTodoAssigneeStatus(id, data.data.completedUsers);
            }

            updateTodoCount();
        } else {
            WP.toast(data.message, 'error');
            const cb = document.querySelector(`.todo-item[data-id="${id}"] input[type="checkbox"]`);
            if (cb) cb.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        const cb = document.querySelector(`.todo-item[data-id="${id}"] input[type="checkbox"]`);
        if (cb) cb.checked = !checked;
    }
}

function updateTodoAssigneeStatus(itemId, completedUsers) {
    const container = document.querySelector(`.assignee-status[data-item-id="${itemId}"]`);
    if (!container) return;

    container.querySelectorAll('.badge-assignee').forEach(badge => {
        const uid = badge.getAttribute('data-uid');
        const name = badge.getAttribute('data-name') || badge.textContent.replace(' ✓', '').trim();
        badge.setAttribute('data-name', name);

        if (completedUsers.includes(uid)) {
            badge.classList.add('done');
            badge.textContent = name + ' ✓';
        } else {
            badge.classList.remove('done');
            badge.textContent = name;
        }
    });
}

function editTodo(id, title, detail, assignedTo, dueDate) {
    document.getElementById('editId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDetail').value = detail;
    document.getElementById('editDueDate').value = dueDate;
    setAssignees('editTodoAssigneeGroup', assignedTo);
    openEditModal();
}

async function updateTodo() {
    const id = document.getElementById('editId').value;
    const title = document.getElementById('editTitle').value.trim();
    const detail = document.getElementById('editDetail').value.trim();
    const assignedTo = getSelectedAssignees('editTodoAssigneeGroup');
    const dueDate = document.getElementById('editDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        return;
    }

    try {
        const data = await WP.put('/api/todo', {
            csrf_token: CONFIG.csrfToken,
            id: parseInt(id),
            trip_code: CONFIG.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate
        });

        if (data.success) {
            closeEditModal();
            updateTodoItemDOM(parseInt(id), title, detail, assignedTo, dueDate);
            WP.toast('수정되었습니다.');
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteTodo(id) {
    if (!await WP.confirm('이 항목을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(`/api/todo?csrf_token=${CONFIG.csrfToken}&id=${id}&trip_code=${CONFIG.tripCode}&user_id=${CONFIG.userId}`);

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateTodoCount();

                if (document.querySelectorAll('.todo-item').length === 0) {
                    document.getElementById('todoContainer').innerHTML = '<div class="card text-center"><p class="text-muted">아직 할 일이 없습니다.</p><p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
                }
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function updateTodoCount() {
    // 현재 탭이 할일일 때만 업데이트
    // 준비물 탭의 완료율 표시는 updateProgress() 사용
}

// ===== DOM 빌더 =====
function escAttr(str) {
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function buildAssigneeBadges(assignedTo, itemId) {
    if (!assignedTo) return '';
    const uids = assignedTo.split(',').map(s => s.trim()).filter(Boolean);
    if (!uids.length) return '';
    const badges = uids.map(uid => {
        const name = CONFIG.memberMap[uid] || uid;
        return `<span class="badge badge-assignee" data-uid="${escAttr(uid)}">${WP.escapeHtml(name)}</span>`;
    }).join('');
    return `<div class="assignee-status" data-item-id="${itemId}">${badges}</div>`;
}

function buildDDayText(dueDateStr) {
    if (!dueDateStr) return { text: '', overdue: false };
    const today = new Date(); today.setHours(0,0,0,0);
    const due = new Date(dueDateStr + 'T00:00:00');
    const diff = Math.round((due - today) / 86400000);
    if (diff > 0) return { text: 'D-' + diff, overdue: false };
    if (diff === 0) return { text: 'D-Day', overdue: true };
    return { text: 'D+' + Math.abs(diff), overdue: true };
}

function appendTodoItem(item) {
    const container = document.getElementById('todoContainer');
    // 빈 상태 메시지 제거
    const emptyCard = container.querySelector('.text-center');
    if (emptyCard && emptyCard.querySelector('.text-muted')) {
        const isEmpty = !container.querySelector('.todo-item');
        if (isEmpty) emptyCard.remove();
    }

    const dday = buildDDayText(item.due_date);
    const assigneesHtml = buildAssigneeBadges(item.assigned_to, item.id);
    const dueDateHtml = item.due_date
        ? `<span class="todo-due">${WP.escapeHtml(item.due_date)}${dday.text ? ` <span class="dday-tag">${WP.escapeHtml(dday.text)}</span>` : ''}</span>`
        : '';

    const detailHtml = item.detail
        ? `<p class="todo-detail">${WP.escapeHtml(item.detail).replace(/\n/g, '<br>')}</p>`
        : '';

    const html = `
        <div class="card todo-item" data-id="${item.id}">
            <div class="todo-item-header">
                <label class="todo-check">
                    <input type="checkbox" onchange="toggleTodo(${item.id}, this.checked)">
                    <span class="todo-title">${WP.escapeHtml(item.title)}</span>
                </label>
                <div class="todo-actions">
                    <button class="btn-icon" onclick="editTodo(${item.id}, '${escAttr(item.title)}', '${escAttr(item.detail || '')}', '${escAttr(item.assigned_to || '')}', '${escAttr(item.due_date || '')}')" title="수정"><span class="material-icons">edit</span></button>
                    <button class="btn-icon danger" onclick="deleteTodo(${item.id})" title="삭제"><span class="material-icons">delete_outline</span></button>
                </div>
            </div>
            ${detailHtml}
            <div class="todo-meta">
                ${assigneesHtml}
                ${dueDateHtml}
            </div>
        </div>`;

    // "완료된 항목" 구분선 앞 또는 맨 끝에 삽입
    const divider = container.querySelector('.todo-divider');
    if (divider) {
        divider.insertAdjacentHTML('beforebegin', html);
    } else {
        container.insertAdjacentHTML('beforeend', html);
    }
    updateTodoCount();
}

function updateTodoItemDOM(id, title, detail, assignedTo, dueDate) {
    const el = document.querySelector(`.todo-item[data-id="${id}"]`);
    if (!el) return;

    el.querySelector('.todo-title').textContent = title;

    let detailEl = el.querySelector('.todo-detail');
    if (detail) {
        const detailHtml = WP.escapeHtml(detail).replace(/\n/g, '<br>');
        if (detailEl) {
            detailEl.innerHTML = detailHtml;
        } else {
            const header = el.querySelector('.todo-item-header');
            header.insertAdjacentHTML('afterend', `<p class="todo-detail">${detailHtml}</p>`);
        }
    } else if (detailEl) {
        detailEl.remove();
    }

    // 담당자 업데이트
    const meta = el.querySelector('.todo-meta');
    const oldAssignee = meta.querySelector('.assignee-status');
    if (oldAssignee) oldAssignee.remove();
    if (assignedTo) {
        meta.insertAdjacentHTML('afterbegin', buildAssigneeBadges(assignedTo, id));
    }

    // 마감일 업데이트
    const oldDue = meta.querySelector('.todo-due');
    if (oldDue) oldDue.remove();
    if (dueDate) {
        const dday = buildDDayText(dueDate);
        const isDone = el.classList.contains('done');
        const overdueClass = (dday.overdue && !isDone) ? ' overdue' : '';
        meta.insertAdjacentHTML('beforeend',
            `<span class="todo-due${overdueClass}">${WP.escapeHtml(dueDate)}${dday.text ? ` <span class="dday-tag">${WP.escapeHtml(dday.text)}</span>` : ''}</span>`);
    }

    // 수정 버튼의 onclick 업데이트
    const editBtn = el.querySelector('.btn-icon[title="수정"]');
    if (editBtn) {
        editBtn.setAttribute('onclick', `editTodo(${id}, '${escAttr(title)}', '${escAttr(detail)}', '${escAttr(assignedTo)}', '${escAttr(dueDate)}')`);
    }
}

function appendChecklistItem(item) {
    const container = document.getElementById('checklistContainer');
    const cat = item.category || '기타';

    // 빈 상태 메시지 제거
    const emptyCard = container.querySelector('.text-center');
    if (emptyCard && !container.querySelector('.checklist-category')) {
        emptyCard.remove();
    }

    // 해당 카테고리 찾기 또는 생성
    let catEl = container.querySelector(`.checklist-category[data-category="${CSS.escape(cat)}"]`);
    if (!catEl) {
        const catHtml = `
            <div class="card checklist-category" data-category="${escAttr(cat)}">
                <div class="flex-between mb-8">
                    <h3 class="card-title" style="margin-bottom:0;">${WP.escapeHtml(cat)}</h3>
                    <span class="text-sm text-muted category-count" data-category="${escAttr(cat)}">0/0</span>
                </div>
                <div class="checklist-items"></div>
            </div>`;
        container.insertAdjacentHTML('beforeend', catHtml);
        catEl = container.querySelector(`.checklist-category[data-category="${CSS.escape(cat)}"]`);
    }

    const assignedUsers = item.assigned_to ? item.assigned_to.split(',').map(s => s.trim()).filter(Boolean) : [];
    const assignedStr = assignedUsers.join(' ');
    const assigneesHtml = buildAssigneeBadges(item.assigned_to, item.id);

    const itemHtml = `
        <div class="checklist-item"
             data-id="${item.id}"
             data-item-text="${escAttr(item.item.toLowerCase())}"
             data-assigned="${escAttr(assignedStr)}"
             data-done="0">
            <label class="checklist-check">
                <input type="checkbox" onchange="toggleItem(${item.id}, this.checked)">
                <span class="checklist-item-text">${WP.escapeHtml(item.item)}</span>
            </label>
            <div class="checklist-item-meta">
                ${assigneesHtml ? `${assigneesHtml}` : ''}
                <button class="btn-icon" onclick="editItem(${item.id}, '${escAttr(item.item)}', '${escAttr(cat)}', '${escAttr(item.assigned_to || '')}')" title="수정"><span class="material-icons">edit</span></button>
                <button class="btn-icon danger" onclick="deleteItem(${item.id})" title="삭제"><span class="material-icons">delete_outline</span></button>
            </div>
        </div>`;

    catEl.querySelector('.checklist-items').insertAdjacentHTML('beforeend', itemHtml);
    updateProgress();
}

function updateChecklistItemDOM(id, itemName, category, assignedTo) {
    const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
    if (!el) return;

    const currentCatEl = el.closest('.checklist-category');
    const currentCat = currentCatEl?.getAttribute('data-category') || '';

    // 텍스트 업데이트
    el.querySelector('.checklist-item-text').textContent = itemName;
    el.setAttribute('data-item-text', itemName.toLowerCase());

    // 담당자 업데이트
    const assignedUsers = assignedTo ? assignedTo.split(',').map(s => s.trim()).filter(Boolean) : [];
    el.setAttribute('data-assigned', assignedUsers.join(' '));
    const meta = el.querySelector('.checklist-item-meta');
    const oldAssignee = meta.querySelector('.assignee-status');
    if (oldAssignee) oldAssignee.remove();
    if (assignedTo) {
        meta.insertAdjacentHTML('afterbegin', buildAssigneeBadges(assignedTo, id));
    }

    // 수정 버튼의 onclick 업데이트
    const editBtn = meta.querySelector('.btn-icon[title="수정"]');
    if (editBtn) {
        editBtn.setAttribute('onclick', `editItem(${id}, '${escAttr(itemName)}', '${escAttr(category)}', '${escAttr(assignedTo)}')`);
    }

    // 카테고리 변경 시 이동
    if (currentCat !== category) {
        el.remove();
        // 원래 카테고리가 비었으면 제거
        if (currentCatEl && currentCatEl.querySelectorAll('.checklist-item').length === 0) {
            currentCatEl.remove();
        }
        // 새 카테고리에 추가 (appendChecklistItem 로직 재활용)
        const fakeItem = { id, item: itemName, category: category === '기타' ? null : category, assigned_to: assignedTo };
        // 카테고리 찾기/생성 후 삽입
        const container = document.getElementById('checklistContainer');
        let newCatEl = container.querySelector(`.checklist-category[data-category="${CSS.escape(category)}"]`);
        if (!newCatEl) {
            const catHtml = `
                <div class="card checklist-category" data-category="${escAttr(category)}">
                    <div class="flex-between mb-8">
                        <h3 class="card-title" style="margin-bottom:0;">${WP.escapeHtml(category)}</h3>
                        <span class="text-sm text-muted category-count" data-category="${escAttr(category)}">0/0</span>
                    </div>
                    <div class="checklist-items"></div>
                </div>`;
            container.insertAdjacentHTML('beforeend', catHtml);
            newCatEl = container.querySelector(`.checklist-category[data-category="${CSS.escape(category)}"]`);
        }
        newCatEl.querySelector('.checklist-items').appendChild(el);
    }

    updateProgress();
}

// 모달 닫기 - 오버레이 클릭
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('addOverlay').addEventListener('click', closeAddModal);
    document.getElementById('editOverlay').addEventListener('click', closeEditModal);

    // 첫 번째 입력 필드에서 Enter 키
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const addModal = document.getElementById('addModal');
            if (!addModal.classList.contains('hidden')) {
                if (currentTab === 'checklist') {
                    e.preventDefault();
                    addItem();
                } else {
                    // 할일의 경우 textarea가 있으므로 Enter는 기본 동작 허용
                }
            }
        }
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
});

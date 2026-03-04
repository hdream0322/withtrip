/**
 * 체크리스트 페이지 JS
 */

// 모달 열기/닫기
function openAddModal() {
    const overlay = document.getElementById('addOverlay');
    const modal = document.getElementById('addModal');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.add('visible');
        modal.classList.add('visible');
    });
    document.getElementById('addItem').focus();
}

function closeAddModal() {
    const overlay = document.getElementById('addOverlay');
    const modal = document.getElementById('addModal');
    overlay.classList.remove('visible');
    modal.classList.remove('visible');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
        document.getElementById('addCategory').value = '';
        document.getElementById('addItem').value = '';
        clearAssignees('addAssigneeGroup');
    }, 250);
}

function openEditModal() {
    const overlay = document.getElementById('editOverlay');
    const modal = document.getElementById('editModal');
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.add('visible');
        modal.classList.add('visible');
    });
    document.getElementById('editItem').focus();
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

// 담당자 토글
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

// 항목 추가
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
            WP.toast('추가되었습니다.');
            closeAddModal();
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// 항목 토글
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

// 담당자 완료 상태 업데이트
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

// 항목 수정
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
            WP.toast('수정되었습니다.');
            closeEditModal();
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// 항목 삭제
async function deleteItem(id) {
    if (!await WP.confirm('이 항목을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(`/api/checklist?csrf_token=${CONFIG.csrfToken}&id=${id}&trip_code=${CONFIG.tripCode}`);

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
                    document.getElementById('listContainer').innerHTML = '<div class="card text-center"><p class="text-muted">아직 준비물이 없습니다.</p><p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
                }
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// 진행률 업데이트
function updateProgress() {
    const allItems = document.querySelectorAll('.checklist-item');
    const doneItems = document.querySelectorAll('.checklist-item.done');
    const total = allItems.length;
    const done = doneItems.length;
    const percent = total > 0 ? Math.round(done / total * 100) : 0;

    const badge = document.querySelector('.checklist-progress-badge');
    if (badge) badge.textContent = percent + '%';

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

// 검색 및 필터
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

// 모달 클로즈 (오버레이 클릭)
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('addOverlay').addEventListener('click', closeAddModal);
    document.getElementById('editOverlay').addEventListener('click', closeEditModal);

    document.getElementById('addItem').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); addItem(); }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
});

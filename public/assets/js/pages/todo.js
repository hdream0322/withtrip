/**
 * 할 일 페이지 JS
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
    document.getElementById('addTitle').focus();
}

function closeAddModal() {
    const overlay = document.getElementById('addOverlay');
    const modal = document.getElementById('addModal');
    overlay.classList.remove('visible');
    modal.classList.remove('visible');
    setTimeout(() => {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
        document.getElementById('addTitle').value = '';
        document.getElementById('addDetail').value = '';
        document.getElementById('addDueDate').value = '';
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
    document.getElementById('editTitle').focus();
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

// 할 일 추가
async function addTodo() {
    const title = document.getElementById('addTitle').value.trim();
    const detail = document.getElementById('addDetail').value.trim();
    const assignedTo = getSelectedAssignees('addAssigneeGroup');
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

// 할 일 토글
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
            }

            if (data.data && data.data.completedUsers) {
                updateAssigneeStatus(id, data.data.completedUsers);
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

// 할 일 수정
function editTodo(id, title, detail, assignedTo, dueDate) {
    document.getElementById('editId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDetail').value = detail;
    document.getElementById('editDueDate').value = dueDate;
    setAssignees('editAssigneeGroup', assignedTo);
    openEditModal();
}

async function updateTodo() {
    const id = document.getElementById('editId').value;
    const title = document.getElementById('editTitle').value.trim();
    const detail = document.getElementById('editDetail').value.trim();
    const assignedTo = getSelectedAssignees('editAssigneeGroup');
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

// 할 일 삭제
async function deleteTodo(id) {
    if (!await WP.confirm('이 할 일을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(`/api/todo?csrf_token=${CONFIG.csrfToken}&id=${id}&trip_code=${CONFIG.tripCode}`);

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateTodoCount();

                if (document.querySelectorAll('.todo-item').length === 0) {
                    document.getElementById('listContainer').innerHTML = '<div class="card text-center"><p class="text-muted">아직 할 일이 없습니다.</p><p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
                }
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// 카운트 업데이트
function updateTodoCount() {
    const allItems = document.querySelectorAll('.todo-item');
    const doneItems = document.querySelectorAll('.todo-item.done');
    const badge = document.getElementById('todoCountBadge');
    if (badge) badge.textContent = doneItems.length + '/' + allItems.length;
}

// 모달 클로즈 (오버레이 클릭)
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('addOverlay').addEventListener('click', closeAddModal);
    document.getElementById('editOverlay').addEventListener('click', closeEditModal);

    document.getElementById('addTitle').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); addTodo(); }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
});

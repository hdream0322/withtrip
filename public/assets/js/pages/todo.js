/**
 * To-Do 페이지 JS
 */
const TD = window.TODO_CONFIG;

/* ============================================================
   담당자 토글 버튼 (공통)
   ============================================================ */

function toggleAssigneeBtn(btn, groupId) {
    btn.classList.toggle('selected');
}

function getSelectedAssignees(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return '';
    const selected = group.querySelectorAll('.assignee-toggle-btn.selected');
    return Array.from(selected).map(function (b) { return b.getAttribute('data-user-id'); }).join(',');
}

function clearAssigneeGroup(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.assignee-toggle-btn').forEach(function (b) {
        b.classList.remove('selected');
    });
}

function initAssigneeGroup(groupId, assignedTo) {
    clearAssigneeGroup(groupId);
    if (!assignedTo) return;
    const ids = assignedTo.split(',').map(function (s) { return s.trim(); });
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.assignee-toggle-btn').forEach(function (b) {
        if (ids.includes(b.getAttribute('data-user-id'))) {
            b.classList.add('selected');
        }
    });
}

/* ============================================================
   추가 폼
   ============================================================ */

function showAddForm() {
    clearAssigneeGroup('addTodoAssigneeGroup');
    document.getElementById('addTitle').value   = '';
    document.getElementById('addDetail').value  = '';
    document.getElementById('addDueDate').value = '';
    _showModal('addFormOverlay', 'addForm');
    document.getElementById('addTitle').focus();
}

function hideAddForm() {
    _hideModal('addFormOverlay', 'addForm');
}

/* ============================================================
   할일 CRUD
   ============================================================ */

async function addTodoItem() {
    const title      = document.getElementById('addTitle').value.trim();
    const detail     = document.getElementById('addDetail').value.trim();
    const assignedTo = getSelectedAssignees('addTodoAssigneeGroup');
    const dueDate    = document.getElementById('addDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('addTitle').focus();
        return;
    }

    try {
        const data = await WP.post('/api/todo', {
            csrf_token:  TD.csrfToken,
            trip_code:   TD.tripCode,
            title:       title,
            detail:      detail,
            assigned_to: assignedTo,
            due_date:    dueDate,
        });

        if (data.success) {
            WP.toast('할 일이 추가되었습니다.');
            location.reload();
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
            csrf_token: TD.csrfToken,
            id:         id,
            trip_code:  TD.tripCode,
            user_id:    TD.userId,
            is_done:    checked ? 1 : 0,
        });

        if (data.success) {
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.classList.toggle('done', checked);
            }

            // 담당자 완료 현황 뱃지 업데이트
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

/**
 * 담당자 완료 현황 뱃지 업데이트
 */
function updateAssigneeStatus(itemId, completedUsers) {
    const container = document.querySelector(`.assignee-status[data-item-id="${itemId}"]`);
    if (!container) return;

    container.querySelectorAll('.badge-assignee').forEach(function (badge) {
        const uid  = badge.getAttribute('data-uid');
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

async function editTodoItem(id) {
    try {
        const data = await WP.api('/api/todo?trip_code=' + TD.tripCode + '&user_id=' + TD.userId);

        if (!data.success) {
            WP.toast('데이터를 불러올 수 없습니다.', 'error');
            return;
        }

        const item = data.data.items.find(function (i) { return parseInt(i.id) === id; });
        if (!item) {
            WP.toast('항목을 찾을 수 없습니다.', 'error');
            return;
        }

        document.getElementById('editId').value    = id;
        document.getElementById('editTitle').value  = item.title || '';
        document.getElementById('editDetail').value = item.detail || '';
        document.getElementById('editDueDate').value = item.due_date || '';
        initAssigneeGroup('editTodoAssigneeGroup', item.assigned_to || '');

        document.getElementById('editModal').classList.remove('hidden');
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

async function saveTodoEdit() {
    const id         = parseInt(document.getElementById('editId').value);
    const title      = document.getElementById('editTitle').value.trim();
    const detail     = document.getElementById('editDetail').value.trim();
    const assignedTo = getSelectedAssignees('editTodoAssigneeGroup');
    const dueDate    = document.getElementById('editDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('editTitle').focus();
        return;
    }

    try {
        const data = await WP.put('/api/todo', {
            csrf_token:  TD.csrfToken,
            id:          id,
            trip_code:   TD.tripCode,
            title:       title,
            detail:      detail,
            assigned_to: assignedTo,
            due_date:    dueDate,
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

async function deleteTodoItem(id) {
    if (!WP.confirm('이 할 일을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/todo?csrf_token=' + TD.csrfToken +
            '&id=' + id + '&trip_code=' + TD.tripCode
        );

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateTodoCount();
                if (document.querySelectorAll('.todo-item').length === 0) {
                    const container = document.getElementById('todoContainer');
                    container.innerHTML = '<div class="card text-center" id="emptyMessage">' +
                        '<p class="text-muted">아직 할 일이 없습니다.</p>' +
                        '<p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
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
    const allItems  = document.querySelectorAll('.todo-item');
    const doneItems = document.querySelectorAll('.todo-item.done');
    const badge = document.getElementById('todoCountBadge');
    if (badge) badge.textContent = doneItems.length + '/' + allItems.length;
}

/* ============================================================
   초기화
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    const addTitleInput = document.getElementById('addTitle');
    if (addTitleInput) {
        addTitleInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTodoItem();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeEditModal();
    });
});

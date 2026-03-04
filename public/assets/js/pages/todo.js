var TD = window.TODO_CONFIG;

function testOpen() {
    alert('testOpen 호출됨');
    openTodoModal();
}

function toggleAssigneeBtn(btn, groupId) {
    btn.classList.toggle('selected');
}

function getSelectedAssignees(groupId) {
    var group = document.getElementById(groupId);
    if (!group) return '';
    var selected = group.querySelectorAll('.assignee-toggle-btn.selected');
    return Array.from(selected).map(function (b) {
        return b.getAttribute('data-user-id');
    }).join(',');
}

function clearAssigneeGroup(groupId) {
    var group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.assignee-toggle-btn').forEach(function (b) {
        b.classList.remove('selected');
    });
}

function initAssigneeGroup(groupId, assignedTo) {
    clearAssigneeGroup(groupId);
    if (!assignedTo) return;
    var ids = assignedTo.split(',').map(function (s) { return s.trim(); });
    var group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.assignee-toggle-btn').forEach(function (b) {
        if (ids.includes(b.getAttribute('data-user-id'))) {
            b.classList.add('selected');
        }
    });
}

function openTodoModal(item) {
    alert('1. openTodoModal 시작');
    var titleEl = document.getElementById('todoTitle');
    var detailEl = document.getElementById('todoDetail');
    var dueEl = document.getElementById('todoDueDate');
    var titleHeaderEl = document.getElementById('todoModalTitle');
    var idEl = document.getElementById('todoEditId');
    alert('2. 요소 가져오기 완료. titleEl=' + (titleEl ? 'found' : 'null'));

    if (item) {
        if (titleHeaderEl) titleHeaderEl.textContent = '할 일 수정';
        if (idEl) idEl.value = item.id;
        if (titleEl) titleEl.value = item.title || '';
        if (detailEl) detailEl.value = item.detail || '';
        if (dueEl) dueEl.value = item.due_date || '';
        initAssigneeGroup('todoAssigneeGroup', item.assigned_to || '');
    } else {
        if (titleHeaderEl) titleHeaderEl.textContent = '할 일 추가';
        if (idEl) idEl.value = '';
        if (titleEl) titleEl.value = '';
        if (detailEl) detailEl.value = '';
        if (dueEl) dueEl.value = '';
        clearAssigneeGroup('todoAssigneeGroup');
    }
    alert('3. 데이터 채우기 완료');

    alert('4. _showModal 호출 전');

    // 테스트: 직접 모달 보이기
    var overlay = document.getElementById('todoOverlay');
    var sheet = document.getElementById('todoSheet');
    alert('5. overlay=' + (overlay ? 'found' : 'null') + ', sheet=' + (sheet ? 'found' : 'null'));

    if (overlay) overlay.classList.remove('hidden');
    if (sheet) sheet.classList.remove('hidden');
    alert('6. 모달 직접 표시 완료');
    if (titleEl) titleEl.focus();
}

function closeTodoModal() {
    _hideModal('todoOverlay', 'todoSheet');
}

function editTodoItem(id) {
    var item = window.TODO_DATA.find(function (i) { return parseInt(i.id) === id; });
    if (item) openTodoModal(item);
}

async function saveTodoItem() {
    var idEl = document.getElementById('todoEditId');
    var titleEl = document.getElementById('todoTitle');
    var detailEl = document.getElementById('todoDetail');
    var dueEl = document.getElementById('todoDueDate');

    var id = idEl ? idEl.value : '';
    var title = titleEl ? titleEl.value.trim() : '';
    var detail = detailEl ? detailEl.value.trim() : '';
    var assignedTo = getSelectedAssignees('todoAssigneeGroup');
    var dueDate = dueEl ? dueEl.value : '';

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        if (titleEl) titleEl.focus();
        return;
    }

    try {
        var payload = {
            csrf_token: TD.csrfToken,
            trip_code: TD.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate,
        };
        if (id) payload.id = id;

        var data = id
            ? await WP.put('/api/todo', payload)
            : await WP.post('/api/todo', payload);

        if (data.success) {
            WP.toast(id ? '수정되었습니다.' : '할 일이 추가되었습니다.');
            closeTodoModal();
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
        var data = await WP.put('/api/todo', {
            csrf_token: TD.csrfToken,
            id: id,
            trip_code: TD.tripCode,
            user_id: TD.userId,
            is_done: checked ? 1 : 0,
        });

        if (data.success) {
            var el = document.querySelector('.todo-item[data-id="' + id + '"]');
            if (el) el.classList.toggle('done', checked);
            if (data.data && data.data.completedUsers) {
                updateAssigneeStatus(id, data.data.completedUsers);
            }
            updateTodoCount();
        } else {
            WP.toast(data.message, 'error');
            var cb = document.querySelector('.todo-item[data-id="' + id + '"] input[type="checkbox"]');
            if (cb) cb.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        var cb = document.querySelector('.todo-item[data-id="' + id + '"] input[type="checkbox"]');
        if (cb) cb.checked = !checked;
    }
}

function updateAssigneeStatus(itemId, completedUsers) {
    var container = document.querySelector('.assignee-status[data-item-id="' + itemId + '"]');
    if (!container) return;
    container.querySelectorAll('.badge-assignee').forEach(function (badge) {
        var uid = badge.getAttribute('data-uid');
        var name = badge.getAttribute('data-name') || badge.textContent.replace(' ✓', '').trim();
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

async function deleteTodoItem(id) {
    if (!await WP.confirm('이 할 일을 삭제하시겠습니까?')) return;

    try {
        var data = await WP.delete('/api/todo?csrf_token=' + TD.csrfToken + '&id=' + id + '&trip_code=' + TD.tripCode);
        if (data.success) {
            WP.toast('삭제되었습니다.');
            var el = document.querySelector('.todo-item[data-id="' + id + '"]');
            if (el) {
                el.remove();
                updateTodoCount();
                if (document.querySelectorAll('.todo-item').length === 0) {
                    var container = document.getElementById('todoContainer');
                    container.innerHTML = '<div class="card text-center" id="emptyMessage"><p class="text-muted">아직 할 일이 없습니다.</p><p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p></div>';
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
    var allItems = document.querySelectorAll('.todo-item');
    var doneItems = document.querySelectorAll('.todo-item.done');
    var badge = document.getElementById('todoCountBadge');
    if (badge) badge.textContent = doneItems.length + '/' + allItems.length;
}

document.addEventListener('DOMContentLoaded', function () {
    var todoTitleInput = document.getElementById('todoTitle');
    if (todoTitleInput) {
        todoTitleInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveTodoItem();
            }
        });
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTodoModal();
});

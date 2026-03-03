/**
 * To-Do 페이지 JS
 */
const TD = window.TODO_CONFIG;

/**
 * 추가 폼 표시
 */
function showAddForm() {
    document.getElementById('addForm').classList.remove('hidden');
    document.getElementById('addTitle').focus();
}

/**
 * 추가 폼 숨기기
 */
function hideAddForm() {
    document.getElementById('addForm').classList.add('hidden');
    document.getElementById('addTitle').value = '';
    document.getElementById('addDetail').value = '';
    document.getElementById('addAssignedTo').value = '';
    document.getElementById('addDueDate').value = '';
}

/**
 * 할 일 추가
 */
async function addTodoItem() {
    const title = document.getElementById('addTitle').value.trim();
    const detail = document.getElementById('addDetail').value.trim();
    const assignedTo = document.getElementById('addAssignedTo').value;
    const dueDate = document.getElementById('addDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('addTitle').focus();
        return;
    }

    try {
        const data = await WP.post('/api/todo', {
            csrf_token: TD.csrfToken,
            trip_code: TD.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate,
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

/**
 * 완료 토글
 */
async function toggleTodo(id, checked) {
    try {
        const data = await WP.put('/api/todo', {
            csrf_token: TD.csrfToken,
            id: id,
            trip_code: TD.tripCode,
            is_done: checked ? 1 : 0,
        });

        if (data.success) {
            // 토글 후 페이지 리로드 (정렬 순서가 변경되므로)
            location.reload();
        } else {
            WP.toast(data.message, 'error');
            const checkbox = document.querySelector(`.todo-item[data-id="${id}"] input[type="checkbox"]`);
            if (checkbox) checkbox.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        const checkbox = document.querySelector(`.todo-item[data-id="${id}"] input[type="checkbox"]`);
        if (checkbox) checkbox.checked = !checked;
    }
}

/**
 * 수정 모달 열기
 */
async function editTodoItem(id) {
    // 서버에서 최신 데이터 가져오기
    try {
        const data = await WP.api('/api/todo?trip_code=' + TD.tripCode);

        if (!data.success) {
            WP.toast('데이터를 불러올 수 없습니다.', 'error');
            return;
        }

        const item = data.data.items.find(function (i) { return parseInt(i.id) === id; });
        if (!item) {
            WP.toast('항목을 찾을 수 없습니다.', 'error');
            return;
        }

        document.getElementById('editId').value = id;
        document.getElementById('editTitle').value = item.title || '';
        document.getElementById('editDetail').value = item.detail || '';
        document.getElementById('editAssignedTo').value = item.assigned_to || '';
        document.getElementById('editDueDate').value = item.due_date || '';

        document.getElementById('editModal').classList.remove('hidden');
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

/**
 * 수정 모달 닫기
 */
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

/**
 * 수정 저장
 */
async function saveTodoEdit() {
    const id = parseInt(document.getElementById('editId').value);
    const title = document.getElementById('editTitle').value.trim();
    const detail = document.getElementById('editDetail').value.trim();
    const assignedTo = document.getElementById('editAssignedTo').value;
    const dueDate = document.getElementById('editDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('editTitle').focus();
        return;
    }

    try {
        const data = await WP.put('/api/todo', {
            csrf_token: TD.csrfToken,
            id: id,
            trip_code: TD.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate,
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

/**
 * 할 일 삭제
 */
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

                // 전체 항목이 없으면 빈 메시지 표시
                if (document.querySelectorAll('.todo-item').length === 0) {
                    const container = document.getElementById('todoContainer');
                    container.innerHTML = '<div class="card text-center" id="emptyMessage">' +
                        '<p class="text-muted">아직 할 일이 없습니다.</p>' +
                        '<p class="text-sm text-muted mt-8">위의 버튼으로 할 일을 추가해보세요.</p></div>';
                }

                // 헤더 카운트 업데이트
                updateTodoCount();
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

/**
 * 헤더 카운트 업데이트
 */
function updateTodoCount() {
    const allItems = document.querySelectorAll('.todo-item');
    const doneItems = document.querySelectorAll('.todo-item.done');
    const badge = document.querySelector('.todo-count-badge');
    if (badge) {
        badge.textContent = doneItems.length + '/' + allItems.length;
    }
}

// 엔터키로 추가
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

    // 모달 외부 클릭으로 닫기 (ESC)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });
});

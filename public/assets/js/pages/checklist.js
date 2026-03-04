/**
 * 통합 체크 페이지 JS (준비물 + 할 일)
 */
const CL = window.CHECKLIST_CONFIG;
const TD = window.TODO_CONFIG;

let currentCheckTab = 'checklist'; // 'checklist' | 'todo'

/* ============================================================
   탭 전환
   ============================================================ */

/**
 * 탭 전환
 * @param {string} tab - 'checklist' | 'todo'
 */
function switchCheckTab(tab) {
    currentCheckTab = tab;

    const checklistTab = document.getElementById('checklistTab');
    const todoTab = document.getElementById('todoTab');
    const tabBtns = document.querySelectorAll('.page-tabs .page-tab-btn');

    if (tab === 'checklist') {
        checklistTab.classList.remove('hidden');
        todoTab.classList.add('hidden');
        tabBtns[0].classList.add('active');
        tabBtns[1].classList.remove('active');
        updateHeaderBadge();
    } else {
        checklistTab.classList.add('hidden');
        todoTab.classList.remove('hidden');
        tabBtns[0].classList.remove('active');
        tabBtns[1].classList.add('active');
        updateTodoHeaderBadge();
    }
}

/**
 * FAB 클릭 핸들러
 */
function fabClickHandler() {
    if (currentCheckTab === 'checklist') {
        showAddClForm();
    } else {
        openTodoModal();
    }
}

/**
 * 준비물 탭 헤더 뱃지 업데이트
 */
function updateHeaderBadge() {
    const allItems = document.querySelectorAll('#checklistTab .checklist-item');
    const doneItems = document.querySelectorAll('#checklistTab .checklist-item.done');
    const total = allItems.length;
    const done = doneItems.length;
    const percent = total > 0 ? Math.round(done / total * 100) : 0;

    const badge = document.getElementById('checklist-header-badge');
    if (badge) badge.textContent = percent + '%';
}

/**
 * 할 일 탭 헤더 뱃지 업데이트
 */
function updateTodoHeaderBadge() {
    const allItems = document.querySelectorAll('#todoTab .todo-item');
    const doneItems = document.querySelectorAll('#todoTab .todo-item.done');
    const badge = document.getElementById('checklist-header-badge');
    if (badge) badge.textContent = doneItems.length + '/' + allItems.length;
}

/* ============================================================
   담당자 토글 버튼 (공통)
   ============================================================ */

/**
 * 담당자 토글 버튼 클릭 처리
 * @param {HTMLElement} btn
 * @param {string} groupId
 */
function toggleAssigneeBtn(btn, groupId) {
    btn.classList.toggle('selected');
}

/**
 * 특정 그룹의 선택된 담당자 user_id를 comma-separated 문자열로 반환
 * @param {string} groupId
 * @returns {string}
 */
function getSelectedAssignees(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return '';
    const selected = group.querySelectorAll('.assignee-toggle-btn.selected');
    return Array.from(selected).map(function (b) { return b.getAttribute('data-user-id'); }).join(',');
}

/**
 * 특정 그룹의 모든 버튼 선택 해제
 * @param {string} groupId
 */
function clearAssigneeGroup(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('.assignee-toggle-btn').forEach(function (b) {
        b.classList.remove('selected');
    });
}

/**
 * comma-separated 담당자 문자열로 그룹 버튼 초기화
 * @param {string} groupId
 * @param {string} assignedTo
 */
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
   추가 모달
   ============================================================ */

function showAddClForm() {
    clearAssigneeGroup('addAssigneeGroup');
    document.getElementById('addCategory').value = '';
    document.getElementById('addItem').value = '';
    _showModal('addClOverlay', 'addClForm');
    document.getElementById('addItem').focus();
}

function hideAddClForm() {
    _hideModal('addClOverlay', 'addClForm');
}

/* ============================================================
   체크리스트 CRUD
   ============================================================ */

async function addChecklistItem() {
    const category   = document.getElementById('addCategory').value.trim();
    const item       = document.getElementById('addItem').value.trim();
    const assignedTo = getSelectedAssignees('addAssigneeGroup');

    if (!item) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        document.getElementById('addItem').focus();
        return;
    }

    try {
        const data = await WP.post('/api/checklist', {
            csrf_token:  CL.csrfToken,
            trip_code:   CL.tripCode,
            category:    category,
            item:        item,
            assigned_to: assignedTo,
        });

        if (data.success) {
            WP.toast('항목이 추가되었습니다.');
            location.reload();
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
            csrf_token: CL.csrfToken,
            id:         id,
            trip_code:  CL.tripCode,
            user_id:    CL.userId,
            is_done:    checked ? 1 : 0,
        });

        if (data.success) {
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                el.classList.toggle('done', checked);
                el.setAttribute('data-done', checked ? '1' : '0');
            }

            // 담당자 완료 현황 뱃지 업데이트
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

/**
 * 담당자 완료 현황 뱃지 업데이트
 * @param {number} itemId
 * @param {string[]} completedUsers - 완료한 user_id 배열
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

function showEditClForm(id, currentItem, currentCategory, currentAssignedTo) {
    document.getElementById('editClId').value       = id;
    document.getElementById('editClItem').value     = currentItem;
    document.getElementById('editClCategory').value = currentCategory;
    initAssigneeGroup('editAssigneeGroup', currentAssignedTo);
    _showModal('editClOverlay', 'editClForm');
    document.getElementById('editClItem').focus();
}

function hideEditClForm() {
    _hideModal('editClOverlay', 'editClForm');
}

async function saveChecklistEdit() {
    const id         = parseInt(document.getElementById('editClId').value);
    const item       = document.getElementById('editClItem').value.trim();
    const category   = document.getElementById('editClCategory').value.trim();
    const assignedTo = getSelectedAssignees('editAssigneeGroup');

    if (!item) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        document.getElementById('editClItem').focus();
        return;
    }

    try {
        const data = await WP.put('/api/checklist', {
            csrf_token:  CL.csrfToken,
            id:          id,
            trip_code:   CL.tripCode,
            category:    category,
            item:        item,
            assigned_to: assignedTo,
        });

        if (data.success) {
            WP.toast('항목이 수정되었습니다.');
            hideEditClForm();
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteChecklistItem(id) {
    if (!await WP.confirm('이 항목을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/checklist?csrf_token=' + CL.csrfToken +
            '&id=' + id + '&trip_code=' + CL.tripCode
        );

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateProgress();
                document.querySelectorAll('.checklist-category').forEach(function (catEl) {
                    if (catEl.querySelectorAll('.checklist-item').length === 0) catEl.remove();
                });
                if (document.querySelectorAll('.checklist-item').length === 0) {
                    document.getElementById('checklistContainer').innerHTML =
                        '<div class="card text-center"><p class="text-muted">아직 준비물이 없습니다.</p>' +
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

/* ============================================================
   진행률 업데이트
   ============================================================ */

function updateProgress() {
    const allItems  = document.querySelectorAll('.checklist-item');
    const doneItems = document.querySelectorAll('.checklist-item.done');
    const total     = allItems.length;
    const done      = doneItems.length;
    const percent   = total > 0 ? Math.round(done / total * 100) : 0;

    const badge = document.querySelector('.checklist-progress-badge');
    if (badge) badge.textContent = percent + '%';

    const fill = document.getElementById('clProgressFill');
    if (fill) fill.style.width = percent + '%';

    const countEl = document.getElementById('clCountText');
    if (countEl) countEl.textContent = done + ' / ' + total + '개';

    document.querySelectorAll('.checklist-category').forEach(function (catEl) {
        const catDone  = catEl.querySelectorAll('.checklist-item.done').length;
        const catTotal = catEl.querySelectorAll('.checklist-item').length;
        const countSpan = catEl.querySelector('.category-count');
        if (countSpan) countSpan.textContent = catDone + '/' + catTotal;
    });
}

/* ============================================================
   검색 / 필터
   ============================================================ */

function applyFilters() {
    const keyword     = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const statusVal   = document.getElementById('statusFilter')?.value || 'all';
    const categoryVal = document.getElementById('categoryFilter')?.value || '';
    const assigneeVal = document.getElementById('assigneeFilter')?.value || '';

    let totalVisible = 0;

    document.querySelectorAll('.checklist-category').forEach(function (catEl) {
        const catName = catEl.getAttribute('data-category') || '';
        let catVisible = 0;

        if (categoryVal && catName !== categoryVal) {
            catEl.classList.add('hidden');
            return;
        }
        catEl.classList.remove('hidden');

        catEl.querySelectorAll('.checklist-item').forEach(function (itemEl) {
            const itemText = itemEl.getAttribute('data-item-text') || '';
            const assigned = itemEl.getAttribute('data-assigned') || '';
            const isDone   = itemEl.getAttribute('data-done') === '1';

            if (statusVal === 'done' && !isDone)   { itemEl.classList.add('hidden'); return; }
            if (statusVal === 'undone' && isDone)   { itemEl.classList.add('hidden'); return; }

            if (assigneeVal === '__none__' && assigned !== '') { itemEl.classList.add('hidden'); return; }
            if (assigneeVal && assigneeVal !== '__none__') {
                // 공백 구분된 담당자 목록에 해당 user_id가 포함되는지 확인
                const assignedList = assigned.split(' ').map(function (s) { return s.trim(); });
                if (!assignedList.includes(assigneeVal)) { itemEl.classList.add('hidden'); return; }
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

/* ============================================================
   초기화
   ============================================================ */

/* ============================================================
   할 일 (TODO) 함수들
   ============================================================ */

function openTodoModal(item) {
    const titleEl = document.getElementById('todoTitle');
    const detailEl = document.getElementById('todoDetail');
    const dueEl = document.getElementById('todoDueDate');
    const titleHeaderEl = document.getElementById('todoModalTitle');
    const idEl = document.getElementById('todoEditId');

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

    _showModal('todoOverlay', 'todoSheet');
    if (titleEl) titleEl.focus();
}

function closeTodoModal() {
    _hideModal('todoOverlay', 'todoSheet');
}

function editTodoItem(id) {
    const item = window.TODO_DATA.find(function (i) { return parseInt(i.id) === id; });
    if (item) openTodoModal(item);
}

async function saveTodoItem() {
    const idEl = document.getElementById('todoEditId');
    const titleEl = document.getElementById('todoTitle');
    const detailEl = document.getElementById('todoDetail');
    const dueEl = document.getElementById('todoDueDate');

    const id = idEl ? idEl.value : '';
    const title = titleEl ? titleEl.value.trim() : '';
    const detail = detailEl ? detailEl.value.trim() : '';
    const assignedTo = getSelectedAssignees('todoAssigneeGroup');
    const dueDate = dueEl ? dueEl.value : '';

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        if (titleEl) titleEl.focus();
        return;
    }

    try {
        const payload = {
            csrf_token: TD.csrfToken,
            trip_code: TD.tripCode,
            title: title,
            detail: detail,
            assigned_to: assignedTo,
            due_date: dueDate,
        };
        if (id) payload.id = id;

        const data = id
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
        const data = await WP.put('/api/todo', {
            csrf_token: TD.csrfToken,
            id: id,
            trip_code: TD.tripCode,
            user_id: TD.userId,
            is_done: checked ? 1 : 0,
        });

        if (data.success) {
            const el = document.querySelector('.todo-item[data-id="' + id + '"]');
            if (el) el.classList.toggle('done', checked);
            if (data.data && data.data.completedUsers) {
                updateAssigneeStatus(id, data.data.completedUsers);
            }
            updateTodoHeaderBadge();
        } else {
            WP.toast(data.message, 'error');
            const cb = document.querySelector('.todo-item[data-id="' + id + '"] input[type="checkbox"]');
            if (cb) cb.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        const cb = document.querySelector('.todo-item[data-id="' + id + '"] input[type="checkbox"]');
        if (cb) cb.checked = !checked;
    }
}

async function deleteTodoItem(id) {
    if (!await WP.confirm('이 할 일을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete('/api/todo?csrf_token=' + TD.csrfToken + '&id=' + id + '&trip_code=' + TD.tripCode);
        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector('.todo-item[data-id="' + id + '"]');
            if (el) {
                el.remove();
                updateTodoHeaderBadge();
                if (document.querySelectorAll('.todo-item').length === 0) {
                    const container = document.getElementById('todoContainer');
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

/* ============================================================
   초기화
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    // 엔터키: 준비물 추가
    var addItemInput = document.getElementById('addItem');
    if (addItemInput) {
        addItemInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addChecklistItem(); }
        });
    }

    // 엔터키: 할 일 제목 입력
    var todoTitleInput = document.getElementById('todoTitle');
    if (todoTitleInput) {
        todoTitleInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); saveTodoItem(); }
        });
    }

    // ESC: 모달 닫기
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hideEditClForm();
            closeTodoModal();
        }
    });
});

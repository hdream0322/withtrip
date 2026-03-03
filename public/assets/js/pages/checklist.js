/**
 * 체크리스트 + 할일 통합 페이지 JS
 */
const CL = window.CHECKLIST_CONFIG;

// 현재 활성 탭
var activeTab = 'checklist';

/* ============================================================
   탭 전환
   ============================================================ */

function switchTab(tab) {
    if (tab === 'todo') {
        location.href = '/' + CL.tripCode + '/' + CL.userId + '/todo';
    }
    // 'checklist' 탭은 현재 페이지이므로 아무것도 하지 않음
}

/* ============================================================
   FAB / 모달 공통
   ============================================================ */

function showAddForm() {
    if (activeTab === 'todo') {
        _showModal('addTodoOverlay', 'addTodoForm');
        document.getElementById('addTodoTitle').focus();
    } else {
        _showModal('addClOverlay', 'addClForm');
        document.getElementById('addItem').focus();
    }
}

function hideAddForm() {
    _hideModal('addClOverlay', 'addClForm');
    _hideModal('addTodoOverlay', 'addTodoForm');
    document.getElementById('addCategory').value    = '';
    document.getElementById('addItem').value        = '';
    document.getElementById('addAssignedTo').value  = '';
    document.getElementById('addTodoTitle').value   = '';
    document.getElementById('addTodoDetail').value  = '';
    document.getElementById('addTodoAssignedTo').value = '';
    document.getElementById('addTodoDueDate').value = '';
}

function _showModal(overlayId, sheetId) {
    var overlay = document.getElementById(overlayId);
    var sheet   = document.getElementById(sheetId);
    overlay.classList.remove('hidden');
    sheet.classList.remove('hidden');
    requestAnimationFrame(function () {
        overlay.classList.add('visible');
        sheet.classList.add('visible');
    });
}

function _hideModal(overlayId, sheetId) {
    var overlay = document.getElementById(overlayId);
    var sheet   = document.getElementById(sheetId);
    if (!overlay || !sheet) return;
    overlay.classList.remove('visible');
    sheet.classList.remove('visible');
    setTimeout(function () {
        overlay.classList.add('hidden');
        sheet.classList.add('hidden');
    }, 250);
}

/* ============================================================
   체크리스트 기능
   ============================================================ */

async function addChecklistItem() {
    const category   = document.getElementById('addCategory').value.trim();
    const item       = document.getElementById('addItem').value.trim();
    const assignedTo = document.getElementById('addAssignedTo').value;

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
            is_done:    checked ? 1 : 0,
        });

        if (data.success) {
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                el.classList.toggle('done', checked);
                el.setAttribute('data-done', checked ? '1' : '0');
                el.querySelector('.checklist-item-text').style.textDecoration = checked ? 'line-through' : 'none';
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

async function editChecklistItem(id, currentItem, currentCategory, currentAssignedTo) {
    const item = prompt('항목 이름:', currentItem);
    if (item === null) return;
    if (!item.trim()) { WP.toast('항목 이름을 입력해주세요.', 'error'); return; }

    const category = prompt('카테고리:', currentCategory);
    if (category === null) return;

    let memberList = '없음';
    CL.members.forEach(function (m) { memberList += ', ' + m.user_id + '(' + m.display_name + ')'; });
    const assignedTo = prompt('담당자 ID (' + memberList + '):', currentAssignedTo);
    if (assignedTo === null) return;

    try {
        const data = await WP.put('/api/checklist', {
            csrf_token:  CL.csrfToken,
            id:          id,
            trip_code:   CL.tripCode,
            category:    category.trim(),
            item:        item.trim(),
            assigned_to: assignedTo.trim(),
        });

        if (data.success) {
            WP.toast('항목이 수정되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteChecklistItem(id) {
    if (!WP.confirm('이 항목을 삭제하시겠습니까?')) return;

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

function updateProgress() {
    const allItems  = document.querySelectorAll('.checklist-item');
    const doneItems = document.querySelectorAll('.checklist-item.done');
    const total     = allItems.length;
    const done      = doneItems.length;
    const percent   = total > 0 ? Math.round(done / total * 100) : 0;

    const badge = document.getElementById('headerBadgeCl');
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

            if (statusVal === 'done' && !isDone)    { itemEl.classList.add('hidden'); return; }
            if (statusVal === 'undone' && isDone)    { itemEl.classList.add('hidden'); return; }
            if (assigneeVal === '__none__' && assigned !== '') { itemEl.classList.add('hidden'); return; }
            if (assigneeVal && assigneeVal !== '__none__' && assigned !== assigneeVal) { itemEl.classList.add('hidden'); return; }
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
   할일 기능
   ============================================================ */

async function addTodoItem() {
    const title      = document.getElementById('addTodoTitle').value.trim();
    const detail     = document.getElementById('addTodoDetail').value.trim();
    const assignedTo = document.getElementById('addTodoAssignedTo').value;
    const dueDate    = document.getElementById('addTodoDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('addTodoTitle').focus();
        return;
    }

    try {
        const data = await WP.post('/api/todo', {
            csrf_token:  CL.csrfToken,
            trip_code:   CL.tripCode,
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
            csrf_token: CL.csrfToken,
            id:         id,
            trip_code:  CL.tripCode,
            is_done:    checked ? 1 : 0,
        });

        if (data.success) {
            location.reload();
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

async function editTodoItem(id) {
    try {
        const data = await WP.api('/api/todo?trip_code=' + CL.tripCode);
        if (!data.success) { WP.toast('데이터를 불러올 수 없습니다.', 'error'); return; }

        const item = data.data.items.find(function (i) { return parseInt(i.id) === id; });
        if (!item) { WP.toast('항목을 찾을 수 없습니다.', 'error'); return; }

        document.getElementById('editTodoId').value           = id;
        document.getElementById('editTodoTitle').value        = item.title || '';
        document.getElementById('editTodoDetail').value       = item.detail || '';
        document.getElementById('editTodoAssignedTo').value   = item.assigned_to || '';
        document.getElementById('editTodoDueDate').value      = item.due_date || '';

        document.getElementById('editTodoModal').classList.remove('hidden');
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

function closeEditTodoModal() {
    document.getElementById('editTodoModal').classList.add('hidden');
}

async function saveTodoEdit() {
    const id         = parseInt(document.getElementById('editTodoId').value);
    const title      = document.getElementById('editTodoTitle').value.trim();
    const detail     = document.getElementById('editTodoDetail').value.trim();
    const assignedTo = document.getElementById('editTodoAssignedTo').value;
    const dueDate    = document.getElementById('editTodoDueDate').value;

    if (!title) {
        WP.toast('제목을 입력해주세요.', 'error');
        document.getElementById('editTodoTitle').focus();
        return;
    }

    try {
        const data = await WP.put('/api/todo', {
            csrf_token:  CL.csrfToken,
            id:          id,
            trip_code:   CL.tripCode,
            title:       title,
            detail:      detail,
            assigned_to: assignedTo,
            due_date:    dueDate,
        });

        if (data.success) {
            WP.toast('수정되었습니다.');
            closeEditTodoModal();
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
            '/api/todo?csrf_token=' + CL.csrfToken +
            '&id=' + id + '&trip_code=' + CL.tripCode
        );

        if (data.success) {
            WP.toast('삭제되었습니다.');
            const el = document.querySelector(`.todo-item[data-id="${id}"]`);
            if (el) {
                el.remove();
                updateTodoCount();
                if (document.querySelectorAll('.todo-item').length === 0) {
                    document.getElementById('todoContainer').innerHTML =
                        '<div class="card text-center"><p class="text-muted">아직 할 일이 없습니다.</p>' +
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
    const badge = document.getElementById('headerBadgeTodo');
    if (badge) badge.textContent = doneItems.length + '/' + allItems.length;
}

/* ============================================================
   초기화
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    // 탭 초기화 (항상 준비물 탭으로 시작)
    switchTab('checklist');

    // 네비게이션 체크 버튼: 클릭 시 반대 탭으로 토글
    var navCl = document.getElementById('nav-checklist');
    if (navCl) {
        navCl.addEventListener('click', function (e) {
            e.preventDefault();
            switchTab(activeTab === 'checklist' ? 'todo' : 'checklist');
        });
    }

    // 엔터키: 준비물 추가
    var addItemInput = document.getElementById('addItem');
    if (addItemInput) {
        addItemInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addChecklistItem(); }
        });
    }

    // 엔터키: 할일 추가
    var addTodoTitleInput = document.getElementById('addTodoTitle');
    if (addTodoTitleInput) {
        addTodoTitleInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addTodoItem(); }
        });
    }

    // ESC: 수정 모달 닫기
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeEditTodoModal();
    });
});

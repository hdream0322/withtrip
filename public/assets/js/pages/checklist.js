/**
 * 체크리스트 페이지 JS
 */
const CL = window.CHECKLIST_CONFIG;

/**
 * 검색 + 필터 적용
 */
function applyFilters() {
    const keyword = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const statusVal = document.getElementById('statusFilter')?.value || 'all';
    const categoryVal = document.getElementById('categoryFilter')?.value || '';
    const assigneeVal = document.getElementById('assigneeFilter')?.value || '';

    const filters = {
        status: statusVal,
        category: categoryVal,
        assignee: assigneeVal,
    };

    let totalVisible = 0;

    document.querySelectorAll('.checklist-category').forEach(function (catEl) {
        const catName = catEl.getAttribute('data-category') || '';
        let catVisible = 0;

        // 카테고리 필터
        if (filters.category && catName !== filters.category) {
            catEl.classList.add('hidden');
            return;
        }

        catEl.classList.remove('hidden');

        catEl.querySelectorAll('.checklist-item').forEach(function (itemEl) {
            const itemText = itemEl.getAttribute('data-item-text') || '';
            const assigned = itemEl.getAttribute('data-assigned') || '';
            const isDone = itemEl.getAttribute('data-done') === '1';

            // 상태 필터
            if (filters.status === 'done' && !isDone) {
                itemEl.classList.add('hidden');
                return;
            }
            if (filters.status === 'undone' && isDone) {
                itemEl.classList.add('hidden');
                return;
            }

            // 담당자 필터
            if (filters.assignee === '__none__' && assigned !== '') {
                itemEl.classList.add('hidden');
                return;
            }
            if (filters.assignee && filters.assignee !== '__none__' && assigned !== filters.assignee) {
                itemEl.classList.add('hidden');
                return;
            }

            // 키워드 검색
            if (keyword && !itemText.includes(keyword)) {
                itemEl.classList.add('hidden');
                return;
            }

            itemEl.classList.remove('hidden');
            catVisible++;
            totalVisible++;
        });

        // 카테고리 내 보이는 항목이 없으면 카드 숨김
        if (catVisible === 0) {
            catEl.classList.add('hidden');
        }
    });

    // 결과 없음 메시지
    const noResult = document.getElementById('noFilterResult');
    const emptyMsg = document.getElementById('emptyMessage');
    if (noResult) {
        const hasItems = document.querySelectorAll('.checklist-item').length > 0;
        if (hasItems && totalVisible === 0) {
            noResult.classList.remove('hidden');
        } else {
            noResult.classList.add('hidden');
        }
    }
    if (emptyMsg) emptyMsg.style.display = 'none';
}

/**
 * 추가 폼 표시
 */
function showAddForm() {
    document.getElementById('addForm').classList.remove('hidden');
    document.getElementById('addItem').focus();
}

/**
 * 추가 폼 숨기기
 */
function hideAddForm() {
    document.getElementById('addForm').classList.add('hidden');
    document.getElementById('addCategory').value = '';
    document.getElementById('addItem').value = '';
    document.getElementById('addAssignedTo').value = '';
}

/**
 * 항목 추가
 */
async function addChecklistItem() {
    const category = document.getElementById('addCategory').value.trim();
    const item = document.getElementById('addItem').value.trim();
    const assignedTo = document.getElementById('addAssignedTo').value;

    if (!item) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        document.getElementById('addItem').focus();
        return;
    }

    try {
        const data = await WP.post('/api/checklist', {
            csrf_token: CL.csrfToken,
            trip_code: CL.tripCode,
            category: category,
            item: item,
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

/**
 * 항목 체크/해제 토글
 */
async function toggleItem(id, checked) {
    try {
        const data = await WP.put('/api/checklist', {
            csrf_token: CL.csrfToken,
            id: id,
            trip_code: CL.tripCode,
            is_done: checked ? 1 : 0,
        });

        if (data.success) {
            const el = document.querySelector(`.checklist-item[data-id="${id}"]`);
            if (el) {
                if (checked) {
                    el.classList.add('done');
                    el.setAttribute('data-done', '1');
                    el.querySelector('.checklist-item-text').style.textDecoration = 'line-through';
                } else {
                    el.classList.remove('done');
                    el.setAttribute('data-done', '0');
                    el.querySelector('.checklist-item-text').style.textDecoration = 'none';
                }
            }
            updateProgress();
            applyFilters();
        } else {
            WP.toast(data.message, 'error');
            // 체크박스 원복
            const checkbox = document.querySelector(`.checklist-item[data-id="${id}"] input[type="checkbox"]`);
            if (checkbox) checkbox.checked = !checked;
        }
    } catch (err) {
        WP.toast(err.message, 'error');
        const checkbox = document.querySelector(`.checklist-item[data-id="${id}"] input[type="checkbox"]`);
        if (checkbox) checkbox.checked = !checked;
    }
}

/**
 * 완료율 업데이트 (DOM 기반)
 */
function updateProgress() {
    const allItems = document.querySelectorAll('.checklist-item');
    const doneItems = document.querySelectorAll('.checklist-item.done');
    const total = allItems.length;
    const done = doneItems.length;
    const percent = total > 0 ? Math.round(done / total * 100) : 0;

    // 헤더 뱃지
    const badge = document.querySelector('.checklist-progress-badge');
    if (badge) badge.textContent = percent + '%';

    // 프로그레스 바
    const fill = document.querySelector('.progress-fill');
    if (fill) fill.style.width = percent + '%';

    // 완료 수
    const countEl = document.querySelector('.flex-between .text-sm:last-child');
    if (countEl) countEl.textContent = done + ' / ' + total + '개';

    // 카테고리별 카운트
    document.querySelectorAll('.checklist-category').forEach(function (catEl) {
        const catName = catEl.getAttribute('data-category');
        const catItems = catEl.querySelectorAll('.checklist-item');
        const catDone = catEl.querySelectorAll('.checklist-item.done');
        const countSpan = catEl.querySelector('.category-count');
        if (countSpan) {
            countSpan.textContent = catDone.length + '/' + catItems.length;
        }
    });
}

/**
 * 항목 수정
 */
async function editChecklistItem(id, currentItem, currentCategory, currentAssignedTo) {
    const item = prompt('항목 이름:', currentItem);
    if (item === null) return;
    if (!item.trim()) {
        WP.toast('항목 이름을 입력해주세요.', 'error');
        return;
    }

    const category = prompt('카테고리:', currentCategory);
    if (category === null) return;

    // 담당자 선택 (멤버 목록 기반)
    let memberList = '없음';
    CL.members.forEach(function (m, i) {
        memberList += ', ' + m.user_id + '(' + m.display_name + ')';
    });
    const assignedTo = prompt('담당자 ID (' + memberList + '):', currentAssignedTo);
    if (assignedTo === null) return;

    try {
        const data = await WP.put('/api/checklist', {
            csrf_token: CL.csrfToken,
            id: id,
            trip_code: CL.tripCode,
            category: category.trim(),
            item: item.trim(),
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

/**
 * 항목 삭제
 */
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

                // 카테고리에 남은 항목이 없으면 카테고리 카드도 제거
                document.querySelectorAll('.checklist-category').forEach(function (catEl) {
                    if (catEl.querySelectorAll('.checklist-item').length === 0) {
                        catEl.remove();
                    }
                });

                // 전체 항목이 없으면 빈 메시지 표시
                if (document.querySelectorAll('.checklist-item').length === 0) {
                    const container = document.getElementById('checklistContainer');
                    container.innerHTML = '<div class="card text-center" id="emptyMessage">' +
                        '<p class="text-muted">아직 체크리스트가 없습니다.</p>' +
                        '<p class="text-sm text-muted mt-8">위의 버튼으로 항목을 추가해보세요.</p></div>';
                }
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// 엔터키로 추가
document.addEventListener('DOMContentLoaded', function () {
    const addItemInput = document.getElementById('addItem');
    if (addItemInput) {
        addItemInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addChecklistItem();
            }
        });
    }
});

/**
 * /my 오너 대시보드 JS
 */

const csrfToken = window.MY_CONFIG.csrfToken;

// 현재 멤버 모달에서 열린 여행 코드
let currentMembersTripCode = null;

// ── 드롭다운 메뉴 ──────────────────────────────────────────

function toggleCardMenu(tripCode) {
    const dropdown = document.getElementById('menu-' + tripCode);
    const isHidden = dropdown.classList.contains('hidden');

    // 다른 열린 드롭다운 닫기
    closeAllMenus();

    if (isHidden) {
        dropdown.classList.remove('hidden');
    }
}

function closeAllMenus() {
    document.querySelectorAll('.card-menu-dropdown').forEach(el => {
        el.classList.add('hidden');
    });
}

// 드롭다운 외부 클릭 시 닫기
document.addEventListener('click', function (e) {
    if (!e.target.closest('.card-menu')) {
        closeAllMenus();
    }
});

// ── 모달 공통 ──────────────────────────────────────────────

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// 오버레이 클릭 시 모달 닫기
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});

// ── 수정 모달 ──────────────────────────────────────────────

function openEditModal(tripCode) {
    closeAllMenus();

    const card = document.querySelector(`[data-trip-code="${tripCode}"]`);

    document.getElementById('editTripCode').value = tripCode;
    document.getElementById('editTitle').value = card.dataset.title || '';
    document.getElementById('editDestination').value = card.dataset.destination || '';
    document.getElementById('editDescription').value = card.dataset.description || '';
    document.getElementById('editStartDate').value = card.dataset.startDate || '';
    document.getElementById('editEndDate').value = card.dataset.endDate || '';

    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editTitle').focus();
}

async function saveTrip() {
    const tripCode = document.getElementById('editTripCode').value;
    const title    = document.getElementById('editTitle').value.trim();

    if (!title) {
        WP.toast('여행 제목을 입력해주세요.', 'error');
        return;
    }

    const btn = document.getElementById('editSaveBtn');
    btn.disabled = true;
    btn.textContent = '저장 중...';

    try {
        const data = await WP.put('/api/trips', {
            csrf_token:  csrfToken,
            trip_code:   tripCode,
            title:       title,
            destination: document.getElementById('editDestination').value.trim(),
            description: document.getElementById('editDescription').value.trim(),
            start_date:  document.getElementById('editStartDate').value || null,
            end_date:    document.getElementById('editEndDate').value || null,
        });

        if (data.success) {
            WP.toast('수정되었습니다.');
            closeModal('editModal');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '저장';
    }
}

// ── 멤버 관리 모달 ─────────────────────────────────────────

async function openMembersModal(tripCode) {
    closeAllMenus();
    currentMembersTripCode = tripCode;

    // 모달 제목에 여행 이름 표시
    const card = document.querySelector(`[data-trip-code="${tripCode}"]`);
    document.getElementById('membersModalTitle').textContent =
        '멤버 관리 — ' + (card.dataset.title || tripCode);

    // 입력 필드 초기화
    document.getElementById('modalNewUserId').value = '';
    document.getElementById('modalNewDisplayName').value = '';

    document.getElementById('membersModal').classList.remove('hidden');
    await loadMembers(tripCode);
}

async function loadMembers(tripCode) {
    const listEl = document.getElementById('modalMemberList');
    listEl.innerHTML = '<div class="spinner"></div>';

    try {
        const data = await WP.api('/api/members?trip_code=' + tripCode);
        if (data.success) {
            renderMembers(tripCode, data.data);
        } else {
            listEl.innerHTML = '<p class="text-sm text-muted">멤버를 불러올 수 없습니다.</p>';
        }
    } catch {
        listEl.innerHTML = '<p class="text-sm text-muted">멤버를 불러올 수 없습니다.</p>';
    }
}

function renderMembers(tripCode, members) {
    const listEl = document.getElementById('modalMemberList');
    listEl.innerHTML = '';

    if (members.length === 0) {
        listEl.innerHTML = '<p class="text-sm text-muted">멤버가 없습니다.</p>';
        return;
    }

    members.forEach(m => {
        const pinBadge = m.pin_hash
            ? '<span class="pin-badge set">PIN 설정됨</span>'
            : '<span class="pin-badge unset">PIN 미설정</span>';

        const actions = [];
        actions.push(`<button class="btn btn-sm btn-secondary" onclick="WP.copyToClipboard(location.origin + '/${tripCode}/${m.user_id}/')">URL 복사</button>`);
        if (!m.is_owner) {
            actions.push(`<button class="btn btn-sm btn-danger" onclick="deleteMember('${tripCode}', '${m.user_id}')">삭제</button>`);
        }

        const item = document.createElement('div');
        item.className = 'member-item';
        item.innerHTML = `
            <div class="member-info">
                <span class="member-name">${m.display_name} ${pinBadge}</span>
                <span class="member-id">${m.user_id}${m.is_owner ? ' · 오너' : ''}</span>
            </div>
            <div class="member-actions">${actions.join('')}</div>
        `;
        listEl.appendChild(item);
    });
}

async function addMemberFromModal() {
    const tripCode = currentMembersTripCode;
    const userIdInput   = document.getElementById('modalNewUserId');
    const nameInput     = document.getElementById('modalNewDisplayName');
    const userId        = userIdInput.value.trim();
    const displayName   = nameInput.value.trim();

    if (!userId || !displayName) {
        WP.toast('ID와 이름을 모두 입력해주세요.', 'error');
        return;
    }

    try {
        const data = await WP.post('/api/members', {
            csrf_token:   csrfToken,
            trip_code:    tripCode,
            user_id:      userId,
            display_name: displayName,
        });

        if (data.success) {
            WP.toast('멤버가 추가되었습니다.');
            userIdInput.value = '';
            nameInput.value   = '';
            await loadMembers(tripCode);
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteMember(tripCode, userId) {
    if (!WP.confirm(`${userId} 멤버를 삭제하시겠습니까?`)) return;

    try {
        const data = await WP.delete(
            '/api/members?csrf_token=' + csrfToken +
            '&trip_code=' + tripCode +
            '&user_id=' + userId
        );

        if (data.success) {
            WP.toast('멤버가 삭제되었습니다.');
            await loadMembers(tripCode);
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// ── 여행 삭제 ──────────────────────────────────────────────

async function deleteTrip(tripCode, title) {
    closeAllMenus();
    if (!WP.confirm(`"${title}" 여행을 삭제하시겠습니까?\n모든 데이터가 삭제됩니다.`)) return;

    try {
        const data = await WP.api('/api/trips', {
            method: 'DELETE',
            body: JSON.stringify({
                csrf_token: csrfToken,
                trip_code:  tripCode,
            }),
        });

        if (data.success) {
            WP.toast('여행이 삭제되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

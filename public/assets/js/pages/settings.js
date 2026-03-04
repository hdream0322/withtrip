/**
 * 설정 페이지 JS
 */
const SC = window.SETTINGS_CONFIG;

const Settings = {
    /* ============================================================
       여행 정보 수정 (오너 전용)
       ============================================================ */

    openTripEditModal() {
        _showModal('tripEditOverlay', 'tripEditSheet');
    },

    closeTripEditModal() {
        _hideModal('tripEditOverlay', 'tripEditSheet');
    },

    async saveTripEdit() {
        const title = document.getElementById('editTripTitle').value.trim();
        if (!title) {
            WP.toast('제목을 입력해주세요.', 'error');
            return;
        }

        try {
            const data = await WP.put('/api/trips/update', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: SC.userId,
                title: title,
                description: document.getElementById('editTripDescription').value.trim(),
                destination: document.getElementById('editTripDestination').value.trim(),
                start_date: document.getElementById('editTripStartDate').value,
                end_date: document.getElementById('editTripEndDate').value,
            });

            if (data.success) {
                WP.toast('여행 정보가 수정되었습니다.');
                location.reload();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       멤버 관리 (오너 전용)
       ============================================================ */

    openAddMemberModal() {
        document.getElementById('newMemberUserId').value = '';
        document.getElementById('newMemberDisplayName').value = '';
        _showModal('addMemberOverlay', 'addMemberSheet');
        document.getElementById('newMemberUserId').focus();
    },

    closeAddMemberModal() {
        _hideModal('addMemberOverlay', 'addMemberSheet');
    },

    async addMember() {
        const userId = document.getElementById('newMemberUserId').value.trim();
        const displayName = document.getElementById('newMemberDisplayName').value.trim();

        if (!userId || !displayName) {
            WP.toast('모든 필드를 입력해주세요.', 'error');
            return;
        }

        if (!/^[a-zA-Z0-9_-]+$/.test(userId)) {
            WP.toast('ID는 영문, 숫자, _, -만 사용할 수 있습니다.', 'error');
            return;
        }

        try {
            const data = await WP.post('/api/members/manage', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: userId,
                display_name: displayName,
                requester_user_id: SC.userId,
            });

            if (data.success) {
                WP.toast('멤버가 추가되었습니다.');
                location.reload();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    async deleteMember(userId, displayName) {
        if (!WP.confirm(displayName + ' 멤버를 삭제하시겠습니까?')) return;

        try {
            const data = await WP.delete(
                '/api/members/manage?csrf_token=' + SC.csrfToken +
                '&trip_code=' + SC.tripCode +
                '&user_id=' + encodeURIComponent(userId) +
                '&requester_user_id=' + SC.userId
            );

            if (data.success) {
                WP.toast('멤버가 삭제되었습니다.');
                const el = document.querySelector('.member-item[data-user-id="' + userId + '"]');
                if (el) el.remove();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    copyMemberUrl(userId) {
        const url = location.origin + '/' + SC.tripCode + '/' + userId + '/';
        WP.copyToClipboard(url);
    },

    /* ============================================================
       PIN 변경
       ============================================================ */

    openPinChangeModal() {
        document.getElementById('currentPin').value = '';
        document.getElementById('newPin').value = '';
        document.getElementById('confirmPin').value = '';
        _showModal('pinChangeOverlay', 'pinChangeSheet');
        document.getElementById('currentPin').focus();
    },

    closePinChangeModal() {
        _hideModal('pinChangeOverlay', 'pinChangeSheet');
    },

    async changePIN() {
        const currentPin = document.getElementById('currentPin').value;
        const newPin = document.getElementById('newPin').value;
        const confirmPin = document.getElementById('confirmPin').value;

        if (!currentPin || !newPin || !confirmPin) {
            WP.toast('모든 필드를 입력해주세요.', 'error');
            return;
        }

        if (newPin.length !== 6 || !/^\d{6}$/.test(newPin)) {
            WP.toast('새 PIN은 6자리 숫자여야 합니다.', 'error');
            return;
        }

        if (newPin !== confirmPin) {
            WP.toast('새 PIN이 일치하지 않습니다.', 'error');
            return;
        }

        try {
            const data = await WP.put('/api/pin_change', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: SC.userId,
                current_pin: currentPin,
                new_pin: newPin,
            });

            if (data.success) {
                WP.toast('PIN이 변경되었습니다.');
                Settings.closePinChangeModal();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },
};

// ESC 키로 모달 닫기
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        Settings.closeTripEditModal();
        Settings.closeAddMemberModal();
        Settings.closePinChangeModal();
    }
});

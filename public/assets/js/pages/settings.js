/**
 * 설정 페이지 JS
 * 환율 관련 코드는 settings-rates.js로 분리
 */
const SC = window.SETTINGS_CONFIG;

const Settings = {
    /* ============================================================
       표시 이름 편집
       ============================================================ */

    openDisplayNameModal() {
        document.getElementById('editDisplayName').value = SC.displayName;
        _showModal('displayNameOverlay', 'displayNameSheet');
        document.getElementById('editDisplayName').focus();
    },

    closeDisplayNameModal() {
        _hideModal('displayNameOverlay', 'displayNameSheet');
    },

    async saveDisplayName() {
        const name = document.getElementById('editDisplayName').value.trim();
        if (!name) {
            WP.toast('표시 이름을 입력해주세요.', 'error');
            return;
        }
        if (name.length > 50) {
            WP.toast('표시 이름은 50자 이내로 입력해주세요.', 'error');
            return;
        }

        try {
            const data = await WP.put('/api/members/update_profile', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                user_id: SC.userId,
                display_name: name,
            });

            if (data.success) {
                WP.toast('표시 이름이 변경되었습니다.');
                SC.displayName = name;
                // DOM 업데이트
                document.getElementById('myDisplayName').textContent = name;
                // 멤버 목록의 내 이름도 업데이트
                const myItem = document.querySelector('.member-item.member-me .member-display-name');
                if (myItem) {
                    const badges = myItem.querySelectorAll('.owner-badge, .me-badge');
                    myItem.textContent = name;
                    badges.forEach(b => myItem.appendChild(b));
                }
                // 아바타 업데이트
                const myAvatar = document.querySelector('.member-item.member-me .member-avatar');
                if (myAvatar) myAvatar.textContent = name.charAt(0);
                Settings.closeDisplayNameModal();
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       멤버 URL 공유 (Web Share API)
       ============================================================ */

    shareMemberUrl(userId, displayName) {
        const url = location.origin + '/' + SC.tripCode + '/' + userId + '/';
        const title = SC.tripTitle + ' - ' + displayName + '의 참여 링크';

        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(err => {
                if (err.name !== 'AbortError') {
                    WP.copyToClipboard(url);
                }
            });
        } else {
            WP.copyToClipboard(url);
        }
    },

    shareAllMemberLinks() {
        const members = SC.members || [];
        const lines = members.map(m =>
            m.display_name + ': ' + location.origin + '/' + SC.tripCode + '/' + m.user_id + '/'
        );
        const text = lines.join('\n');

        if (navigator.share) {
            navigator.share({
                title: SC.tripTitle + ' - 참여 링크',
                text: text,
            }).catch(err => {
                if (err.name !== 'AbortError') {
                    WP.copyToClipboard(text);
                }
            });
        } else {
            WP.copyToClipboard(text);
        }
    },

    /* ============================================================
       QR 코드
       ============================================================ */

    _currentQrUrl: '',

    showQr(userId, displayName) {
        const url = location.origin + '/' + SC.tripCode + '/' + userId + '/';
        this._openQrModal(displayName + '의 참여 링크', url);
    },

    showTripQr() {
        const url = location.origin + '/' + SC.tripCode + '/';
        this._openQrModal('여행 입장 링크', url);
    },

    _openQrModal(title, url) {
        this._currentQrUrl = url;
        document.getElementById('qrTitle').textContent = title;
        document.getElementById('qrUrl').textContent = url;

        const canvas = document.getElementById('qrCanvas');
        canvas.innerHTML = '';
        new QRCode(canvas, {
            text: url,
            width: 180,
            height: 180,
            correctLevel: QRCode.CorrectLevel.M,
        });

        _showModal('qrOverlay', 'qrSheet');
    },

    closeQrModal() {
        _hideModal('qrOverlay', 'qrSheet');
    },

    copyQrUrl() {
        WP.copyToClipboard(this._currentQrUrl);
    },

    /* ============================================================
       여행 코드 복사
       ============================================================ */

    copyTripCode() {
        WP.copyToClipboard(SC.tripCode);
    },

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
        if (!await WP.confirm(displayName + ' 멤버를 삭제하시겠습니까?')) return;

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

    /* ============================================================
       PIN 초기화 (오너 전용)
       ============================================================ */

    async resetMemberPin(userId, displayName) {
        if (!await WP.confirm(displayName + '의 PIN을 초기화하시겠습니까?\n다음 접속 시 새 PIN을 설정하게 됩니다.')) return;

        try {
            const data = await WP.put('/api/pin_reset', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                requester_user_id: SC.userId,
                target_user_id: userId,
            });

            if (data.success) {
                WP.toast(displayName + '의 PIN이 초기화되었습니다.');
            } else {
                WP.toast(data.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       로그아웃
       ============================================================ */

    logout() {
        location.href = '/auth/logout';
    },
};

// ESC 키로 모달 닫기
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        Settings.closeQrModal();
        Settings.closeDisplayNameModal();
        Settings.closeTripEditModal();
        Settings.closeAddMemberModal();
        Settings.closePinChangeModal();
    }
});

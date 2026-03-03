/**
 * 일정표 페이지 JS
 */
const SC = window.SCHEDULE_CONFIG;

async function addDay() {
    const title = prompt('Day 제목 (선택):');
    const date = prompt('날짜 (YYYY-MM-DD, 선택):');

    try {
        const data = await WP.post('/api/schedule/days', {
            csrf_token: SC.csrfToken,
            trip_code: SC.tripCode,
            title: title || '',
            date: date || null,
        });

        if (data.success) {
            WP.toast('일정이 추가되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function editDay(dayId) {
    const title = prompt('Day 제목:');
    const note = prompt('메모:');

    try {
        const data = await WP.put('/api/schedule/days', {
            csrf_token: SC.csrfToken,
            id: dayId,
            trip_code: SC.tripCode,
            title: title || '',
            note: note || '',
        });

        if (data.success) {
            WP.toast('수정되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function addItem(dayId) {
    const time = prompt('시간 (예: 09:00):');
    const content = prompt('내용:');
    if (!content) return;
    const location_str = prompt('장소 (선택):');

    try {
        const data = await WP.post('/api/schedule/items', {
            csrf_token: SC.csrfToken,
            day_id: dayId,
            trip_code: SC.tripCode,
            time: time || '',
            content: content,
            location: location_str || '',
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

async function editItem(itemId) {
    const time = prompt('시간:');
    const content = prompt('내용:');
    if (!content) return;
    const location_str = prompt('장소:');

    try {
        const data = await WP.put('/api/schedule/items', {
            csrf_token: SC.csrfToken,
            id: itemId,
            trip_code: SC.tripCode,
            time: time || '',
            content: content,
            location: location_str || '',
        });

        if (data.success) {
            WP.toast('수정되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteItem(itemId, dayId) {
    if (!WP.confirm('이 항목을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/schedule/items?csrf_token=' + SC.csrfToken +
            '&id=' + itemId + '&trip_code=' + SC.tripCode
        );

        if (data.success) {
            WP.toast('삭제되었습니다.');
            location.reload();
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

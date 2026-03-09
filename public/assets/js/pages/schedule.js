/**
 * 일정표 페이지 JS
 * 날짜 스크롤 바 + 타임라인 + Google Maps + Sheet 모달
 *
 * SCHEDULE_CONFIG = { tripCode, userId, csrfToken, startDate, endDate }
 */
const SC = window.SCHEDULE_CONFIG;

let days = [];
let currentItems = [];
let selectedDayId = null;   // null = 전체보기
let selectedCategory = null;

// 카테고리 설정
const CATEGORIES = {
    meal:          { label: '식사',    color: '#F97316', emoji: '🍽' },
    cafe:          { label: '카페',    color: '#A16207', emoji: '☕' },
    sightseeing:   { label: '관광',    color: '#2563EB', emoji: '📸' },
    transport:     { label: '이동',    color: '#6B7280', emoji: '🚗' },
    accommodation: { label: '숙소',    color: '#7C3AED', emoji: '🏨' },
    shopping:      { label: '쇼핑',    color: '#16A34A', emoji: '🛍' },
    activity:      { label: '액티비티', color: '#DC2626', emoji: '🏄' },
    other:         { label: '기타',    color: '#9CA3AF', emoji: '📌' },
};

// ========================
// 초기화
// ========================
document.addEventListener('DOMContentLoaded', () => {
    initCategoryChips();
    initAllDayToggle();
    loadDays();
    initLocationClickHandler();
});

// ========================
// 위치 클릭 핸들러
// ========================
function initLocationClickHandler() {
    document.addEventListener('click', (e) => {
        const placeSpan = e.target.closest('.timeline-place');
        if (!placeSpan) return;

        e.stopPropagation();
        const location = placeSpan.dataset.location;
        const mapsUrl = placeSpan.dataset.mapsUrl || '';

        if (location) {
            openMaps(location, mapsUrl);
        }
    });
}

// ========================
// 데이터 로드
// ========================
async function loadDays() {
    try {
        const data = await WP.api('/api/schedule/days?trip_code=' + SC.tripCode);
        if (data.success) {
            days = data.data.days;
            buildDateBar();

            if (days.length > 0) {
                const todayDay = findTodayDay();
                if (todayDay) {
                    selectDay(todayDay);
                } else {
                    selectDay(days[0]);
                }
            } else {
                renderEmptyState();
            }
        }
    } catch (err) {
        document.getElementById('timeline').innerHTML =
            '<div class="schedule-empty">일정을 불러올 수 없습니다.</div>';
    }
}

async function loadItems(dayId) {
    try {
        const data = await WP.api('/api/schedule/items?trip_code=' + SC.tripCode + '&day_id=' + dayId);
        if (data.success) {
            currentItems = data.data.items;
            renderTimeline();
        }
    } catch (err) {
        document.getElementById('timeline').innerHTML =
            '<div class="schedule-empty">항목을 불러올 수 없습니다.</div>';
    }
}

async function loadAllItems() {
    document.getElementById('timeline').innerHTML =
        '<div class="text-center text-muted text-sm"><div class="spinner"></div></div>';
    try {
        const data = await WP.api('/api/schedule/items?trip_code=' + SC.tripCode);
        if (data.success) {
            currentItems = data.data.items;
            renderAllTimeline();
        }
    } catch (err) {
        document.getElementById('timeline').innerHTML =
            '<div class="schedule-empty">일정을 불러올 수 없습니다.</div>';
    }
}

// ========================
// 날짜 스크롤 바
// ========================
function buildDateBar() {
    const scroll = document.getElementById('dateScroll');
    scroll.innerHTML = '';

    const today = new Date().toISOString().slice(0, 10);

    // 전체보기 칩
    const allChip = document.createElement('button');
    allChip.className = 'date-chip date-chip-special';
    allChip.dataset.mode = 'all';
    allChip.innerHTML = '<span class="date-chip-day">전체</span>';
    allChip.addEventListener('click', () => selectAll());
    scroll.appendChild(allChip);

    // 오늘 칩 (여행 기간 내에 오늘이 있을 때만 표시)
    const todayDay = days.find(d => d.date === today);
    if (todayDay) {
        const todayChip = document.createElement('button');
        todayChip.className = 'date-chip date-chip-special date-chip-today-btn';
        todayChip.dataset.mode = 'today';
        todayChip.innerHTML = '<span class="date-chip-day">오늘</span>';
        todayChip.addEventListener('click', () => selectDay(todayDay));
        scroll.appendChild(todayChip);
    }

    days.forEach(day => {
        const chip = document.createElement('button');
        chip.className = 'date-chip';
        chip.dataset.dayId = day.id;

        const topLabel = day.day_number + '일차';
        let subLabel = '';
        if (day.date) {
            const d = new Date(day.date + 'T00:00:00');
            subLabel = (d.getMonth() + 1) + '/' + d.getDate();
        }

        if (subLabel) {
            chip.innerHTML =
                '<span class="date-chip-day">' + topLabel + '</span>' +
                '<span class="date-chip-weekday">' + subLabel + '</span>';
        } else {
            chip.innerHTML = '<span class="date-chip-day">' + topLabel + '</span>';
        }

        if (day.date === today) chip.classList.add('today');

        chip.addEventListener('click', () => selectDay(day));
        scroll.appendChild(chip);
    });
}

function selectAll() {
    selectedDayId = null;

    document.querySelectorAll('.date-chip').forEach(c => c.classList.remove('active'));
    const allChip = document.querySelector('.date-chip[data-mode="all"]');
    if (allChip) {
        allChip.classList.add('active');
        allChip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }

    document.getElementById('dayHeader').innerHTML = '';
    loadAllItems();
}

function selectDay(day) {
    selectedDayId = day.id;

    document.querySelectorAll('.date-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.querySelector('.date-chip[data-day-id="' + day.id + '"]');
    if (activeChip) {
        activeChip.classList.add('active');
        activeChip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }

    renderDayHeader(day);
    loadItems(day.id);
}

function findTodayDay() {
    const today = new Date().toISOString().slice(0, 10);
    return days.find(d => d.date === today);
}

// ========================
// 렌더링
// ========================
function renderDayHeader(day) {
    const header = document.getElementById('dayHeader');
    let html = '<div class="day-title">' + day.day_number + '일차';
    if (day.title) html += ' · ' + escHtml(day.title);
    html += '</div>';

    if (day.date) {
        const d = new Date(day.date + 'T00:00:00');
        const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        html += '<div class="day-date">' +
            d.getFullYear() + '.' + (d.getMonth() + 1) + '.' + d.getDate() +
            ' (' + weekdays[d.getDay()] + ')' +
            '</div>';
    }

    if (day.note) {
        html += '<div class="day-note">' + escHtml(day.note) + '</div>';
    }

    header.innerHTML = html;
}

function renderTimelineItem(item) {
    const cat = CATEGORIES[item.category] || null;
    const dotColor = cat ? cat.color : '#cbd5e1';

    let html = '<div class="timeline-item" onclick="openDetailModal(' + item.id + ')">';
    html += '<div class="timeline-dot" style="background:' + dotColor + ';"></div>';
    html += '<div class="timeline-body">';

    let timeStr = '';
    if (item.is_all_day == 1) {
        timeStr = '종일';
    } else if (item.time) {
        timeStr = item.time;
        if (item.end_time) timeStr += ' - ' + item.end_time;
    }
    if (timeStr) {
        html += '<div class="timeline-time">' + escHtml(timeStr) + '</div>';
    }

    html += '<div class="timeline-title">' + escHtml(item.content) + '</div>';
    html += '<div class="timeline-meta">';
    if (item.location) {
        html += '<span class="timeline-place" data-location="' + escHtml(item.location) + '" data-maps-url="' + escHtml(item.google_maps_url || '') + '">' +
                '<span class="material-icons" style="font-size:14px;vertical-align:middle;">place</span> ' + escHtml(item.location) + '</span>';
    }
    html += '<div class="timeline-meta-right">';
    if (item.memo) {
        html += '<span class="timeline-memo-icon" title="메모 있음"><span class="material-icons">description</span></span>';
    }
    if (cat) {
        html += '<span class="timeline-cat-badge" style="background:' + cat.color + ';">' + cat.emoji + ' ' + cat.label + '</span>';
    }
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    return html;
}

function renderTimeline() {
    const container = document.getElementById('timeline');

    if (currentItems.length === 0) {
        container.innerHTML =
            '<div class="schedule-empty">' +
                '<div class="schedule-empty-icon">📋</div>' +
                '<div>아직 일정이 없습니다.<br>아래 + 버튼으로 추가해보세요.</div>' +
            '</div>';
        return;
    }

    container.innerHTML = currentItems.map(item => renderTimelineItem(item)).join('');
}

function renderAllTimeline() {
    const container = document.getElementById('timeline');

    if (currentItems.length === 0) {
        container.innerHTML =
            '<div class="schedule-empty">' +
                '<div class="schedule-empty-icon">📅</div>' +
                '<div>등록된 일정이 없습니다.</div>' +
            '</div>';
        return;
    }

    // day_id 기준 그룹핑
    const grouped = {};
    currentItems.forEach(item => {
        if (!grouped[item.day_id]) grouped[item.day_id] = [];
        grouped[item.day_id].push(item);
    });

    let html = '';
    days.forEach(day => {
        const items = grouped[day.id];
        if (!items || items.length === 0) return;

        html += '<div class="all-day-section">';
        html += '<div class="all-day-title">' + day.day_number + '일차';
        if (day.date) {
            const d = new Date(day.date + 'T00:00:00');
            const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
            html += ' <span class="all-day-date">' + (d.getMonth() + 1) + '/' + d.getDate() + ' (' + weekdays[d.getDay()] + ')</span>';
        }
        if (day.title) html += ' · ' + escHtml(day.title);
        html += '</div>';
        items.forEach(item => { html += renderTimelineItem(item); });
        html += '</div>';
    });

    container.innerHTML = html || '<div class="schedule-empty"><div class="schedule-empty-icon">📅</div><div>등록된 일정이 없습니다.</div></div>';
}

function renderEmptyState() {
    document.getElementById('dayHeader').innerHTML = '';
    document.getElementById('timeline').innerHTML =
        '<div class="schedule-empty">' +
            '<div class="schedule-empty-icon">📅</div>' +
            '<div>여행 일정이 없습니다.<br>여행 날짜를 설정하면 일차가 자동으로 생성됩니다.</div>' +
        '</div>';
}

// ========================
// Google Maps
// ========================
function getGoogleMapsLink(location, mapsUrl) {
    if (mapsUrl) return mapsUrl;
    if (location) return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(location);
    return null;
}

function openMaps(location, mapsUrl) {
    const link = getGoogleMapsLink(location, mapsUrl);
    if (link) window.open(link, '_blank');
}

// ========================
// 상세 모달
// ========================
function openDetailModal(itemId) {
    const item = currentItems.find(i => i.id == itemId);
    if (!item) return;

    const cat = CATEGORIES[item.category] || null;
    const mapsLink = getGoogleMapsLink(item.location, item.google_maps_url);

    let html = '';
    html += '<div class="detail-title">' + escHtml(item.content) + '</div>';

    if (cat) {
        html += '<span class="timeline-cat-badge" style="background:' + cat.color + ';margin-bottom:12px;display:inline-block;">' + cat.emoji + ' ' + cat.label + '</span>';
    }

    let timeStr = '';
    if (item.is_all_day == 1) {
        timeStr = '종일';
    } else if (item.time) {
        timeStr = item.time;
        if (item.end_time) timeStr += ' - ' + item.end_time;
    }
    if (timeStr) {
        html += '<div class="detail-row"><span class="material-icons">schedule</span><span>' + escHtml(timeStr) + '</span></div>';
    }

    if (item.location) {
        html += '<div class="detail-row"><span class="material-icons">place</span><span>' + escHtml(item.location) + '</span></div>';
    }

    if (item.memo) {
        html += '<div class="detail-memo">' + nl2br(escHtml(item.memo)) + '</div>';
    }

    if (mapsLink) {
        html += '<button class="detail-maps-btn" data-maps-link="' + escHtml(mapsLink) + '">' +
                '<span class="material-icons">map</span> 구글맵에서 열기</button>';
    }

    html += '<div class="detail-actions">';
    html += '<button class="btn btn-secondary" onclick="closeDetailModal();editScheduleItem(' + item.id + ')">수정</button>';
    html += '<button class="btn btn-danger" onclick="deleteScheduleItem(' + item.id + ')">삭제</button>';
    html += '</div>';

    document.getElementById('detailContent').innerHTML = html;
    _showModal('detailOverlay', 'detailSheet');

    const mapsBtn = document.querySelector('.detail-maps-btn[data-maps-link]');
    if (mapsBtn) {
        mapsBtn.addEventListener('click', () => {
            const link = mapsBtn.dataset.mapsLink;
            if (link) window.open(link, '_blank');
        });
    }
}

function closeDetailModal() {
    _hideModal('detailOverlay', 'detailSheet');
}

// ========================
// 추가/수정 모달
// ========================
function openScheduleModal(item) {
    const daySelect = document.getElementById('scheduleDay');
    daySelect.innerHTML = '';

    days.forEach(day => {
        const opt = document.createElement('option');
        opt.value = day.id;
        let label = day.day_number + '일차';
        if (day.date) {
            const d = new Date(day.date + 'T00:00:00');
            const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
            label += ' (' + (d.getMonth() + 1) + '/' + d.getDate() + ' ' + weekdays[d.getDay()] + ')';
        }
        if (day.title) label += ' - ' + day.title;
        opt.textContent = label;
        daySelect.appendChild(opt);
    });

    if (item) {
        document.getElementById('scheduleModalTitle').textContent = '일정 수정';
        document.getElementById('scheduleEditId').value = item.id;
        document.getElementById('scheduleTitle').value = item.content || '';
        daySelect.value = item.day_id || (selectedDayId || (days.length > 0 ? days[0].id : ''));
        document.getElementById('scheduleAllDay').checked = item.is_all_day == 1;
        document.getElementById('scheduleStartTime').value = item.time || '';
        document.getElementById('scheduleEndTime').value = item.end_time || '';
        document.getElementById('schedulePlace').value = item.location || '';
        document.getElementById('scheduleMapsUrl').value = item.google_maps_url || '';
        document.getElementById('scheduleMemo').value = item.memo || '';
        setSelectedCategory(item.category || null);
        toggleTimeRow(item.is_all_day == 1);
    } else {
        document.getElementById('scheduleModalTitle').textContent = '일정 추가';
        document.getElementById('scheduleEditId').value = '';
        document.getElementById('scheduleTitle').value = '';
        daySelect.value = selectedDayId || (days.length > 0 ? days[0].id : '');
        document.getElementById('scheduleAllDay').checked = false;
        document.getElementById('scheduleStartTime').value = '';
        document.getElementById('scheduleEndTime').value = '';
        document.getElementById('schedulePlace').value = '';
        document.getElementById('scheduleMapsUrl').value = '';
        document.getElementById('scheduleMemo').value = '';
        setSelectedCategory(null);
        toggleTimeRow(false);
    }

    _showModal('scheduleOverlay', 'scheduleSheet');
}

function closeScheduleModal() {
    _hideModal('scheduleOverlay', 'scheduleSheet');
}

function editScheduleItem(itemId) {
    const item = currentItems.find(i => i.id == itemId);
    if (!item) return;
    openScheduleModal(item);
}

async function saveScheduleItem() {
    const editId = document.getElementById('scheduleEditId').value;
    const content = document.getElementById('scheduleTitle').value.trim();
    const dayId = document.getElementById('scheduleDay').value;
    const isAllDay = document.getElementById('scheduleAllDay').checked ? 1 : 0;
    const time = document.getElementById('scheduleStartTime').value;
    const endTime = document.getElementById('scheduleEndTime').value;
    const location = document.getElementById('schedulePlace').value.trim();
    const mapsUrl = document.getElementById('scheduleMapsUrl').value.trim();
    const memo = document.getElementById('scheduleMemo').value.trim();

    if (!content) {
        WP.toast('제목을 입력해주세요.', 'error');
        return;
    }
    if (!dayId) {
        WP.toast('일차를 선택해주세요.', 'error');
        return;
    }

    const body = {
        csrf_token: SC.csrfToken,
        trip_code: SC.tripCode,
        day_id: parseInt(dayId, 10),
        content: content,
        time: isAllDay ? '' : time,
        end_time: isAllDay ? '' : endTime,
        is_all_day: isAllDay,
        location: location,
        google_maps_url: mapsUrl,
        memo: memo,
        category: selectedCategory,
    };

    try {
        let data;
        if (editId) {
            body.id = parseInt(editId, 10);
            data = await WP.put('/api/schedule/items', body);
        } else {
            data = await WP.post('/api/schedule/items', body);
        }

        if (data.success) {
            WP.toast(editId ? '수정되었습니다.' : '추가되었습니다.');
            closeScheduleModal();

            if (selectedDayId === null) {
                // 전체보기 모드
                loadAllItems();
            } else {
                const targetDay = days.find(d => d.id == dayId);
                if (targetDay) selectDay(targetDay);
                else loadItems(selectedDayId);
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

async function deleteScheduleItem(id) {
    if (!await WP.confirm('이 일정을 삭제하시겠습니까?')) return;

    try {
        const data = await WP.delete(
            '/api/schedule/items?csrf_token=' + SC.csrfToken +
            '&id=' + id + '&trip_code=' + SC.tripCode
        );

        if (data.success) {
            WP.toast('삭제되었습니다.');
            closeDetailModal();
            if (selectedDayId === null) {
                loadAllItems();
            } else {
                loadItems(selectedDayId);
            }
        } else {
            WP.toast(data.message, 'error');
        }
    } catch (err) {
        WP.toast(err.message, 'error');
    }
}

// ========================
// 카테고리 칩
// ========================
function initCategoryChips() {
    document.querySelectorAll('.cat-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const cat = chip.dataset.cat;
            setSelectedCategory(selectedCategory === cat ? null : cat);
        });
    });
}

function setSelectedCategory(cat) {
    selectedCategory = cat;
    document.querySelectorAll('.cat-chip').forEach(c => {
        c.classList.toggle('selected', c.dataset.cat === cat);
    });
}

// ========================
// 종일 토글
// ========================
function initAllDayToggle() {
    document.getElementById('scheduleAllDay').addEventListener('change', (e) => {
        toggleTimeRow(e.target.checked);
    });
}

function toggleTimeRow(isAllDay) {
    document.getElementById('timeRow').style.display = isAllDay ? 'none' : 'flex';
}

// ========================
// 스와이프 지원 (일차 단위)
// ========================
(function() {
    let touchStartX = 0;

    document.getElementById('timeline').addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.getElementById('timeline').addEventListener('touchend', (e) => {
        if (selectedDayId === null) return; // 전체보기에서는 스와이프 비활성
        const diff = touchStartX - e.changedTouches[0].screenX;
        if (Math.abs(diff) < 80) return;

        const currentIdx = days.findIndex(d => d.id == selectedDayId);
        if (currentIdx === -1) return;

        if (diff > 0 && currentIdx < days.length - 1) {
            selectDay(days[currentIdx + 1]);
        } else if (diff < 0 && currentIdx > 0) {
            selectDay(days[currentIdx - 1]);
        }
    }, { passive: true });
})();

// ========================
// ESC 키
// ========================
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeDetailModal();
        closeScheduleModal();
    }
});

// ========================
// 유틸
// ========================
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function nl2br(str) {
    return str.replace(/\n/g, '<br>');
}

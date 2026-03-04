/**
 * 일정표 페이지 JS
 * 날짜 스크롤 바 + 타임라인 + Google Maps + Sheet 모달
 *
 * SCHEDULE_CONFIG = { tripCode, userId, csrfToken, startDate, endDate }
 */
const SC = window.SCHEDULE_CONFIG;

let days = [];
let currentItems = [];
let selectedDayId = null;
let selectedCategory = null;

// 카테고리 설정
const CATEGORIES = {
    meal:          { label: '식사',  color: '#f97316', emoji: '🍽' },
    transport:     { label: '이동',  color: '#3b82f6', emoji: '🚗' },
    accommodation: { label: '숙소',  color: '#8b5cf6', emoji: '🏨' },
    sightseeing:   { label: '관광',  color: '#10b981', emoji: '📸' },
    shopping:      { label: '쇼핑',  color: '#ec4899', emoji: '🛍' },
    other:         { label: '기타',  color: '#6b7280', emoji: '📌' },
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
                selectDay(todayDay || days[0]);
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

// ========================
// 날짜 스크롤 바
// ========================
function buildDateBar() {
    const scroll = document.getElementById('dateScroll');
    scroll.innerHTML = '';

    if (days.length === 0) return;

    const today = new Date().toISOString().slice(0, 10);

    days.forEach(day => {
        const chip = document.createElement('button');
        chip.className = 'date-chip';
        chip.dataset.dayId = day.id;

        let label = 'D' + day.day_number;
        if (day.date) {
            const d = new Date(day.date + 'T00:00:00');
            const month = d.getMonth() + 1;
            const date = d.getDate();
            const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
            const weekday = weekdays[d.getDay()];
            label = month + '/' + date;
            chip.innerHTML = '<span class="date-chip-day">' + label + '</span><span class="date-chip-weekday">' + weekday + '</span>';

            if (day.date === today) {
                chip.classList.add('today');
            }
        } else {
            chip.innerHTML = '<span class="date-chip-day">' + label + '</span>';
        }

        chip.addEventListener('click', () => selectDay(day));
        scroll.appendChild(chip);
    });
}

function selectDay(day) {
    selectedDayId = day.id;

    // 칩 활성화
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

function scrollToToday() {
    const todayDay = findTodayDay();
    if (todayDay) {
        selectDay(todayDay);
    } else if (days.length > 0) {
        selectDay(days[0]);
    }
}

// ========================
// 렌더링
// ========================
function renderDayHeader(day) {
    const header = document.getElementById('dayHeader');
    let html = '<div class="day-title">Day ' + day.day_number;
    if (day.title) html += ' · ' + escHtml(day.title);
    html += '</div>';

    if (day.date) {
        const d = new Date(day.date + 'T00:00:00');
        html += '<div class="day-date">' + d.getFullYear() + '.' + (d.getMonth() + 1) + '.' + d.getDate() + '</div>';
    }

    if (day.note) {
        html += '<div class="day-note">' + escHtml(day.note) + '</div>';
    }

    header.innerHTML = html;
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

    let html = '';
    currentItems.forEach(item => {
        const cat = CATEGORIES[item.category] || null;
        const dotColor = cat ? cat.color : '#cbd5e1';

        html += '<div class="timeline-item" onclick="openDetailModal(' + item.id + ')">';

        // 카테고리 컬러 닷
        html += '<div class="timeline-dot" style="background:' + dotColor + ';"></div>';

        html += '<div class="timeline-body">';

        // 시간
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

        // 제목
        html += '<div class="timeline-title">' + escHtml(item.content) + '</div>';

        // 메타 (장소, 메모 아이콘, 카테고리)
        html += '<div class="timeline-meta">';
        if (item.location) {
            html += '<span class="timeline-place" data-location="' + escHtml(item.location) + '" data-maps-url="' + escHtml(item.google_maps_url || '') + '" style="cursor:pointer;">' +
                    '<span class="material-icons" style="font-size:14px;vertical-align:middle;">place</span> ' + escHtml(item.location) + '</span>';
        }
        // 우측 영역 (메모 아이콘 + 카테고리)
        html += '<div class="timeline-meta-right">';
        if (item.memo) {
            html += '<span class="timeline-memo-icon" title="메모 있음"><span class="material-icons">description</span></span>';
        }
        if (cat) {
            html += '<span class="timeline-cat-badge" style="background:' + cat.color + ';">' + cat.emoji + ' ' + cat.label + '</span>';
        }
        html += '</div>';
        html += '</div>';

        html += '</div>'; // .timeline-body
        html += '</div>'; // .timeline-item
    });

    container.innerHTML = html;
}

function renderEmptyState() {
    document.getElementById('dayHeader').innerHTML = '';
    document.getElementById('timeline').innerHTML =
        '<div class="schedule-empty">' +
            '<div class="schedule-empty-icon">📅</div>' +
            '<div>아직 일정이 없습니다.<br>아래 + 버튼으로 일차를 추가해보세요.</div>' +
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

    // 제목
    html += '<div class="detail-title">' + escHtml(item.content) + '</div>';

    // 카테고리 뱃지
    if (cat) {
        html += '<span class="timeline-cat-badge" style="background:' + cat.color + ';margin-bottom:12px;display:inline-block;">' + cat.emoji + ' ' + cat.label + '</span>';
    }

    // 시간
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

    // 장소
    if (item.location) {
        html += '<div class="detail-row"><span class="material-icons">place</span><span>' + escHtml(item.location) + '</span></div>';
    }

    // 메모
    if (item.memo) {
        html += '<div class="detail-memo">' + nl2br(escHtml(item.memo)) + '</div>';
    }

    // Google Maps 버튼
    if (mapsLink) {
        html += '<button class="detail-maps-btn" data-maps-link="' + escHtml(mapsLink) + '" style="cursor:pointer;">' +
                '<span class="material-icons">map</span> 구글맵에서 열기</button>';
    }

    // 수정/삭제 버튼
    html += '<div class="detail-actions">';
    html += '<button class="btn btn-secondary" onclick="closeDetailModal();editScheduleItem(' + item.id + ')">수정</button>';
    html += '<button class="btn btn-danger" onclick="deleteScheduleItem(' + item.id + ')">삭제</button>';
    html += '</div>';

    document.getElementById('detailContent').innerHTML = html;
    _showModal('detailOverlay', 'detailSheet');

    // 구글맵 버튼 이벤트
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

    // "새 일차 추가" 옵션
    const newOpt = document.createElement('option');
    newOpt.value = 'new';
    newOpt.textContent = '+ 새 일차 추가';
    daySelect.appendChild(newOpt);

    days.forEach(day => {
        const opt = document.createElement('option');
        opt.value = day.id;
        let label = 'Day ' + day.day_number;
        if (day.date) label += ' (' + day.date + ')';
        if (day.title) label += ' - ' + day.title;
        opt.textContent = label;
        daySelect.appendChild(opt);
    });

    if (item) {
        document.getElementById('scheduleModalTitle').textContent = '일정 수정';
        document.getElementById('scheduleEditId').value = item.id;
        document.getElementById('scheduleTitle').value = item.content || '';
        daySelect.value = item.day_id || (selectedDayId || '');
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
        daySelect.value = selectedDayId || (days.length > 0 ? days[0].id : 'new');
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
    let dayId = document.getElementById('scheduleDay').value;
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

    // 새 일차 추가
    if (dayId === 'new') {
        try {
            const result = await WP.post('/api/schedule/days', {
                csrf_token: SC.csrfToken,
                trip_code: SC.tripCode,
                title: '',
            });
            if (result.success) {
                dayId = result.data.id;
                days.push({ id: dayId, day_number: result.data.day_number, date: null, title: null, note: null });
                buildDateBar();
            } else {
                WP.toast('일차 추가 실패', 'error');
                return;
            }
        } catch (err) {
            WP.toast(err.message, 'error');
            return;
        }
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

            const targetDay = days.find(d => d.id == dayId);
            if (targetDay) {
                selectDay(targetDay);
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
            loadItems(selectedDayId);
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
            if (selectedCategory === cat) {
                setSelectedCategory(null);
            } else {
                setSelectedCategory(cat);
            }
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
// 스와이프 지원
// ========================
(function() {
    let touchStartX = 0;

    document.getElementById('timeline').addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.getElementById('timeline').addEventListener('touchend', (e) => {
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

function escAttr(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function nl2br(str) {
    return str.replace(/\n/g, '<br>');
}

/**
 * 홈 대시보드 - 오늘 일정 타임라인 현재 시간 강조
 */
document.addEventListener('DOMContentLoaded', () => {
    const config = window.HOME_CONFIG;
    if (!config || config.tripPhase !== 'during') return;

    updateCurrentSchedule();
    setInterval(updateCurrentSchedule, 60_000);
});

function getNowMinutes() {
    const now = new Date();
    return now.getHours() * 60 + now.getMinutes();
}

function timeToMinutes(timeStr) {
    if (!timeStr) return null;
    const [h, m] = timeStr.split(':').map(Number);
    return h * 60 + m;
}

function getItemStatus(time, endTime, isAllDay) {
    if (isAllDay === '1') return 'allday';

    const startMin = timeToMinutes(time);
    if (startMin === null) return 'future';

    const nowMin = getNowMinutes();
    const endMin = timeToMinutes(endTime);

    if (endMin !== null) {
        if (nowMin >= startMin && nowMin < endMin) return 'current';
    } else {
        if (Math.abs(nowMin - startMin) <= 30) return 'current';
    }

    return nowMin > startMin ? 'past' : 'future';
}

function updateCurrentSchedule() {
    const items = document.querySelectorAll('.home-timeline-item');
    if (!items.length) return;

    items.forEach(el => {
        const status = getItemStatus(
            el.dataset.time || '',
            el.dataset.endTime || '',
            el.dataset.allDay || '0'
        );

        el.classList.remove('home-timeline-item--current', 'home-timeline-item--past');
        const badge = el.querySelector('.home-timeline-badge');

        if (status === 'current') {
            el.classList.add('home-timeline-item--current');
            if (badge) badge.style.display = '';
        } else if (status === 'past') {
            el.classList.add('home-timeline-item--past');
            if (badge) badge.style.display = 'none';
        } else {
            if (badge) badge.style.display = 'none';
        }
    });
}

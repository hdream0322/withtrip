/**
 * 홈 대시보드
 */

// 푸시 알림 배너
const HomePush = {
    async init() {
        if (!WPPush.isSupported()) return;
        if (WPPush.getPermission() !== 'default') return;
        if (localStorage.getItem('wp_push_dismissed')) return;

        const subscribed = await WPPush.isSubscribed();
        if (subscribed) return;

        const banner = document.getElementById('pushBanner');
        if (banner) banner.style.display = '';
    },

    async enable() {
        const config = window.HOME_CONFIG;
        const ok = await WPPush.subscribe(config.tripCode, config.userId, config.csrfToken);
        if (ok) {
            WP.toast('알림이 활성화되었습니다.');
        }
        const banner = document.getElementById('pushBanner');
        if (banner) banner.style.display = 'none';
    },

    dismiss() {
        localStorage.setItem('wp_push_dismissed', '1');
        const banner = document.getElementById('pushBanner');
        if (banner) banner.style.display = 'none';
    },
};

document.addEventListener('DOMContentLoaded', () => {
    const config = window.HOME_CONFIG;
    if (!config) return;

    // 타임라인 업데이트
    if (config.tripPhase === 'during') {
        updateCurrentSchedule();
        setInterval(updateCurrentSchedule, 60_000);
    }

    // 푸시 배너
    HomePush.init();
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

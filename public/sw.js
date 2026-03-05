/**
 * WithPlan Service Worker
 * PWA 설치를 위한 최소 서비스 워커
 */
const CACHE_NAME = 'withplan-v1';
const PRECACHE_URLS = [
    '/',
    '/assets/css/common.css',
    'https://fonts.googleapis.com/icon?family=Material+Icons'
];

// 설치: 기본 리소스 캐시
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// 활성화: 이전 캐시 정리
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// 네트워크 우선, 실패 시 캐시 폴백
self.addEventListener('fetch', event => {
    // API 요청은 캐시하지 않음
    if (event.request.url.includes('/api/')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // GET 요청만 캐시
                if (event.request.method === 'GET' && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});

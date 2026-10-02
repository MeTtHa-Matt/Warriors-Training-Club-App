const CACHE_NAME = 'jcm-studio-shell-v14';
const SHELL_ASSETS = [
    './offline.html',
    './assets/dashboard-premium.css?v=12',
    './assets/dashboard.js?v=14',
    './assets/dashboard-admin.css?v=5',
    './assets/dashboard-admin.js?v=4',
    './assets/anton.ttf',
    './assets/oswald-500.ttf',
    './assets/inter-400.ttf',
    './assets/inter-600.ttf',
    './assets/icons/icon-192.png',
    './assets/icons/icon-512.png',
    './assets/icons/apple-touch-icon.png',
    './assets/icons/icon.svg',
    './assets/icons/favicon-32.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin || !url.href.startsWith(self.registration.scope)) return;

    const isAppEndpoint = /\/(?:api|auth)\.php(?:$|\?)/.test(url.pathname);
    if (isAppEndpoint || request.mode === 'navigate' || url.pathname.endsWith('.php')) {
        if (request.mode === 'navigate') {
            event.respondWith(fetch(request).catch(() => caches.match('./offline.html')));
        }
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }
                return response;
            });
        }),
    );
});
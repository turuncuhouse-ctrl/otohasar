const CACHE_NAME = 'otohasar-shell-v25';
const SHELL_ASSETS = [
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/manifest.json'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(SHELL_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) { return k !== CACHE_NAME; })
                    .map(function(k) { return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

function isNetworkOnly(url) {
    if (url.pathname.startsWith('/api/')) return true;
    if (url.pathname.startsWith('/uploads/')) return true;
    if (url.pathname.startsWith('/musteri/')) return true;
    if (url.pathname.startsWith('/assets/js/')) return true;
    if (url.pathname.startsWith('/assets/css/')) return true;
    if (url.pathname.endsWith('.php')) return true;
    return false;
}

self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    if (isNetworkOnly(url) || event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(function(cached) {
            return cached || fetch(event.request);
        })
    );
});

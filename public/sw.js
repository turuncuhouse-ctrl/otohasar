const CACHE_NAME = 'otohasar-shell-v10';
const SHELL_ASSETS = [
    '/assets/css/style.css',
    '/assets/js/app.js',
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
    if (url.pathname.endsWith('.php')) return true;
    return false;
}

function isMutableAsset(url) {
    return url.pathname.indexOf('/assets/js/') === 0 || url.pathname.indexOf('/assets/css/') === 0;
}

self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    if (isNetworkOnly(url) || event.request.method !== 'GET') {
        return;
    }

    if (isMutableAsset(url)) {
        event.respondWith(
            fetch(event.request).then(function(response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            }).catch(function() {
                return caches.match(event.request);
            })
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then(function(cached) {
            var fetched = fetch(event.request).then(function(response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            }).catch(function() {
                return cached;
            });
            return cached || fetched;
        })
    );
});

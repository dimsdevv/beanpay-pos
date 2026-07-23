const CACHE = 'checkpoint-pos-v1';
const base = self.location.pathname.replace('service-worker.js', '');

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([
            base,
            base + 'assets/vendor/tailwind.min.js',
            base + 'assets/vendor/alpine.min.js',
            base + 'assets/vendor/sweetalert2.min.css',
            base + 'assets/vendor/sweetalert2.min.js',
        ]))
    );
});

self.addEventListener('fetch', (e) => {
    e.respondWith(
        caches.match(e.request).then((r) => r || fetch(e.request))
    );
});

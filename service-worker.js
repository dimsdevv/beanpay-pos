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
    // Hanya tangani request GET dan URL http/https
    if (e.request.method !== 'GET') return;
    if (!e.request.url.startsWith('http')) return;

    e.respondWith(
        fetch(e.request)
            .then((response) => {
                return response;
            })
            .catch(() => {
                return caches.match(e.request);
            })
    );
});

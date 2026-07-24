self.addEventListener('install', (e) => {
    self.skipWaiting(); // Paksa service worker baru langsung aktif
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => caches.delete(cacheName)) // Hapus semua cache lama yang rusak
            );
        }).then(() => self.clients.claim()) // Ambil alih kontrol halaman
    );
});

self.addEventListener('fetch', (e) => {
    // Kosong: Biarkan browser menangani request secara native
    // Ini 100% aman dan menjamin website selalu live tanpa error ERR_FAILED
});

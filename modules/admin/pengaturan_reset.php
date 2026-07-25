<?php
$page_title = 'Reset Data Transaksi';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);
requireCsrfToken();

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== 'RESET') {
        $error = 'Ketik "RESET" untuk mengonfirmasi.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM pembayaran");
            $pdo->exec("DELETE FROM detail_pesanan");
            $pdo->exec("DELETE FROM pesanan");
            $pdo->exec("DELETE FROM sesi_kasir");
            $pdo->exec("DELETE FROM audit_trail");

            $pdo->exec("ALTER TABLE pembayaran AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE detail_pesanan AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE pesanan AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE sesi_kasir AUTO_INCREMENT = 1");
            $pdo->exec("ALTER TABLE audit_trail AUTO_INCREMENT = 1");
            $pdo->commit();

            // Hapus file bukti transfer dari disk
            $buktiDir = __DIR__ . '/../../assets/uploads/bukti/';
            if (is_dir($buktiDir)) {
                $files = glob($buktiDir . 'tf_*');
                foreach ($files as $f) {
                    if (is_file($f)) unlink($f);
                }
            }

            $done = true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="max-w-2xl">

    <div class="flex items-center gap-3 mb-6">
        <a href="pengaturan.php" class="p-1.5 rounded-lg text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Zona Berbahaya</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Reset data transaksi — tindakan permanen.</p>
        </div>
    </div>

    <?php if ($done): ?>
    <div class="bg-vibe-secondary-container border border-vibe-secondary/20 rounded-xl p-6 text-center">
        <div class="w-14 h-14 rounded-xl bg-white flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-lg font-display font-bold text-vibe-on-surface mb-1">Data Transaksi Direset</h2>
        <p class="text-sm text-vibe-on-surface-variant mb-6">Semua data pesanan, pembayaran, shift, dan bukti transfer berhasil dihapus.</p>
        <a href="pengaturan.php" class="inline-block px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-colors">Kembali ke Pengaturan</a>
    </div>
    <?php else: ?>

    <div class="bg-white border border-red-200 rounded-xl overflow-hidden">
        <div class="bg-red-50 px-6 py-4 border-b border-red-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h2 class="font-bold text-vibe-on-surface">Reset Data Transaksi</h2>
                <p class="text-sm text-vibe-on-surface-variant">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
        </div>

        <div class="p-6 space-y-5">
            <div>
                <div class="text-sm font-bold text-vibe-on-surface mb-2">Data yang akan dihapus:</div>
                <ul class="space-y-1.5 text-sm">
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Semua pesanan & detail pesanan
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Semua pembayaran & riwayat transaksi
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Semua sesi shift kasir
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Log aktivitas & audit trail
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        File bukti transfer pembayaran
                    </li>
                </ul>
            </div>

            <div class="bg-vibe-surface-dim rounded-lg p-4">
                <div class="text-sm font-bold text-vibe-on-surface mb-2">Data yang tetap aman:</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Menu & kategori</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Bahan baku & resep</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Pengguna & peran</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Meja & promo</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-vibe-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Pengaturan toko</span>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="flex items-center gap-2 px-4 py-3 bg-vibe-error-container text-vibe-error rounded-lg text-sm font-medium">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" onsubmit="return confirm('Yakin ingin menghapus SEMUA data transaksi? Tindakan ini tidak bisa dibatalkan.')">
                <?= csrfField() ?>
                <div>
                    <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Ketik <span class="text-red-600 font-black">RESET</span> untuk konfirmasi</label>
                    <input type="text" name="confirm" required placeholder="Ketik RESET" autocomplete="off"
                           class="w-full px-4 py-3 bg-white border border-red-300 rounded-lg text-sm font-bold text-center tracking-widest uppercase focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-colors">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 transition-colors active:scale-[0.98]">
                    Hapus Semua Data Transaksi
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

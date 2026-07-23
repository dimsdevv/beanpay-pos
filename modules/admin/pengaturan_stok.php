<?php
$page_title = 'Stok & Nomor Pesanan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole(['admin']);
requireCsrfToken();

$stmt = $pdo->query("SELECT * FROM pengaturan");
$settings = [];
foreach ($stmt->fetchAll() as $s) { $settings[$s['kunci']] = $s['nilai']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmtU = $pdo->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = ?");
    $stmtU->execute([max(1, (int)($_POST['batas_stok_rendah'] ?? 10)), 'batas_stok_rendah']);
    $stmtU->execute([strtoupper(substr(trim($_POST['prefix_pesanan'] ?? 'ORD'), 0, 5)), 'prefix_pesanan']);
    $_SESSION['success'] = "Pengaturan stok & nomor pesanan berhasil disimpan.";
    header("Location: pengaturan.php");
    exit;
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
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Stok &amp; Nomor Pesanan</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Ambang batas peringatan stok dan format nomor transaksi.</p>
        </div>
    </div>

    <form method="POST" class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <?= csrfField() ?>
        <div class="p-6 space-y-6">
            <div>
                <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Batas Stok Rendah</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="batas_stok_rendah" min="1" max="9999"
                           value="<?= htmlspecialchars($settings['batas_stok_rendah'] ?? '10') ?>"
                           class="w-24 px-3 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary font-bold text-vibe-on-surface text-center text-sm transition-all">
                    <span class="text-sm text-vibe-on-surface-variant font-medium">satuan</span>
                </div>
                <p class="text-xs text-vibe-on-surface-variant mt-1.5">Peringatan stok akan muncul di dashboard dan halaman kasir jika stok bahan ≤ angka ini.</p>
            </div>

            <div class="border-t border-vibe-outline-variant/10 pt-6">
                <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Awalan Nomor Pesanan</label>
                <div class="flex items-center gap-2">
                    <input type="text" name="prefix_pesanan" maxlength="5" placeholder="ORD"
                           value="<?= htmlspecialchars($settings['prefix_pesanan'] ?? 'ORD') ?>"
                           style="text-transform:uppercase"
                           class="w-24 px-3 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary font-bold text-vibe-on-surface text-center text-sm tracking-wider transition-all">
                    <span class="text-sm text-vibe-on-surface-variant font-medium">-20260721-001</span>
                </div>
                <p class="text-xs text-vibe-on-surface-variant mt-1.5">Maksimal 5 huruf atau angka. Contoh: <strong>ORD</strong>-20260721-001, <strong>INV</strong>-20260721-001.<br>Diubah hanya untuk transaksi baru, tidak mempengaruhi riwayat.</p>
            </div>
        </div>
        <div class="px-6 py-4 bg-vibe-bg/50 border-t border-vibe-outline-variant/10 flex justify-end gap-3">
            <a href="pengaturan.php" class="px-5 py-2.5 border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-all active:scale-[0.97] text-sm">Simpan</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
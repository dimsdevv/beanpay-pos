<?php
$page_title = 'Informasi Toko';
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
    $stmtU->execute([trim($_POST['nama_toko'] ?? 'Checkpoint Cafe'), 'nama_toko']);
    $stmtU->execute([trim($_POST['alamat_toko'] ?? ''), 'alamat_toko']);
    $stmtU->execute([trim($_POST['telepon_toko'] ?? ''), 'telepon_toko']);
    $_SESSION['success'] = "Informasi toko berhasil disimpan.";
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
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Informasi Toko</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Identitas toko yang tampil di struk pembayaran.</p>
        </div>
    </div>

    <form method="POST" class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <?= csrfField() ?>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Nama Toko</label>
                <input type="text" name="nama_toko" value="<?= htmlspecialchars($settings['nama_toko'] ?? 'Checkpoint Cafe') ?>" placeholder="Checkpoint Cafe" maxlength="100" class="w-full px-3.5 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-semibold text-vibe-on-surface transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Alamat</label>
                <input type="text" name="alamat_toko" value="<?= htmlspecialchars($settings['alamat_toko'] ?? '') ?>" placeholder="Jl. Coffee Avenue No. 123" maxlength="255" class="w-full px-3.5 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-medium text-vibe-on-surface transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Nomor Telepon</label>
                <input type="text" name="telepon_toko" value="<?= htmlspecialchars($settings['telepon_toko'] ?? '') ?>" placeholder="0812-3456-7890" maxlength="30" class="w-full px-3.5 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-medium text-vibe-on-surface transition-all">
            </div>
        </div>
        <div class="px-6 py-4 bg-vibe-bg/50 border-t border-vibe-outline-variant/10 flex justify-end gap-3">
            <a href="pengaturan.php" class="px-5 py-2.5 border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-all active:scale-[0.97] text-sm">Simpan</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
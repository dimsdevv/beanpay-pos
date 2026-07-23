<?php
$page_title = 'Cetak Struk';
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
    $stmtU->execute([isset($_POST['cetak_otomatis']) ? '1' : '0', 'cetak_otomatis']);
    $_SESSION['success'] = "Pengaturan cetak struk berhasil disimpan.";
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
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Cetak Struk</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Atur kapan struk pembayaran dicetak.</p>
        </div>
    </div>

    <form method="POST" class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <?= csrfField() ?>
        <div class="p-6">
            <div class="flex items-center justify-between" x-data="{ enabled: <?= ($settings['cetak_otomatis'] ?? '1') === '1' ? 'true' : 'false' ?> }">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-vibe-on-surface">Cetak Otomatis</span>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="cetak_otomatis" value="1" class="sr-only peer"
                                   <?= ($settings['cetak_otomatis'] ?? '1') === '1' ? 'checked' : '' ?>
                                   @change="enabled = $event.target.checked">
                            <div class="w-10 h-6 bg-vibe-outline-variant rounded-full peer peer-checked:bg-vibe-primary
                                        after:content-[''] after:absolute after:top-[3px] after:left-[3px]
                                        after:bg-white after:rounded-full after:h-[18px] after:w-[18px]
                                        after:transition-all peer-checked:after:translate-x-4 relative"></div>
                        </label>
                    </div>
                    <p class="text-sm text-vibe-on-surface-variant mt-1.5">Saat <strong>on</strong>, struk langsung muncul cetak setelah pembayaran berhasil. Saat <strong>off</strong>, kasir cetak manual dari tombol "Nota" di riwayat transaksi.</p>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-vibe-bg/50 border-t border-vibe-outline-variant/10 flex justify-end gap-3">
            <a href="pengaturan.php" class="px-5 py-2.5 border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-all active:scale-[0.97] text-sm">Simpan</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
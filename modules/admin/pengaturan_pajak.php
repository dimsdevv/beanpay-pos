<?php
$page_title = 'Pajak & Biaya Layanan';
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
    $stmtU->execute([floatval($_POST['pajak_persen'] ?? 10), 'pajak_persen']);
    $stmtU->execute([floatval($_POST['service_charge_persen'] ?? 5), 'service_charge_persen']);
    $stmtU->execute([isset($_POST['aktifkan_pajak']) ? '1' : '0', 'aktifkan_pajak']);
    $stmtU->execute([isset($_POST['aktifkan_service']) ? '1' : '0', 'aktifkan_service']);
    $_SESSION['success'] = "Pengaturan pajak & biaya layanan berhasil disimpan.";
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
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Pajak &amp; Biaya Layanan</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Pengaturan ini berlaku untuk semua transaksi baru.</p>
        </div>
    </div>

    <form method="POST" class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <?= csrfField() ?>
        <div class="divide-y divide-vibe-outline-variant/10">
            <div class="p-6 flex flex-col sm:flex-row sm:items-center gap-5" x-data="{ enabled: <?= ($settings['aktifkan_pajak'] ?? '1') === '1' ? 'true' : 'false' ?> }">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-vibe-on-surface">Pajak Pembangunan 1 (PB1)</span>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="aktifkan_pajak" value="1" class="sr-only peer" <?= ($settings['aktifkan_pajak'] ?? '1') === '1' ? 'checked' : '' ?> @change="enabled = $event.target.checked">
                            <div class="w-10 h-6 bg-vibe-outline-variant rounded-full peer peer-checked:bg-vibe-primary after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[18px] after:w-[18px] after:transition-all peer-checked:after:translate-x-4 relative"></div>
                        </label>
                    </div>
                    <p class="text-sm text-vibe-on-surface-variant">Pajak daerah yang dibebankan kepada pelanggan. Standarnya 10%.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <input type="number" step="0.1" min="0" max="100" name="pajak_persen" value="<?= htmlspecialchars($settings['pajak_persen'] ?? '10') ?>" :disabled="!enabled"
                           class="w-20 px-3 py-2 bg-vibe-bg border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary font-bold text-vibe-on-surface text-center text-sm disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                    <span class="font-bold text-vibe-on-surface-variant text-sm">%</span>
                </div>
            </div>

            <div class="p-6 flex flex-col sm:flex-row sm:items-center gap-5" x-data="{ enabled: <?= ($settings['aktifkan_service'] ?? '1') === '1' ? 'true' : 'false' ?> }">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-vibe-on-surface">Service Charge</span>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="aktifkan_service" value="1" class="sr-only peer" <?= ($settings['aktifkan_service'] ?? '1') === '1' ? 'checked' : '' ?> @change="enabled = $event.target.checked">
                            <div class="w-10 h-6 bg-vibe-outline-variant rounded-full peer peer-checked:bg-vibe-secondary after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[18px] after:w-[18px] after:transition-all peer-checked:after:translate-x-4 relative"></div>
                        </label>
                    </div>
                    <p class="text-sm text-vibe-on-surface-variant">Biaya pelayanan tambahan untuk operasional restoran. Biasanya 5%.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <input type="number" step="0.1" min="0" max="100" name="service_charge_persen" value="<?= htmlspecialchars($settings['service_charge_persen'] ?? '5') ?>" :disabled="!enabled"
                           class="w-20 px-3 py-2 bg-vibe-bg border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-secondary/30 focus:border-vibe-secondary font-bold text-vibe-on-surface text-center text-sm disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                    <span class="font-bold text-vibe-on-surface-variant text-sm">%</span>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-vibe-bg/50 border-t border-vibe-outline-variant/10 flex justify-end gap-3">
            <a href="pengaturan.php" class="px-5 py-2.5 border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-all active:scale-[0.97] text-sm">Simpan</button>
        </div>
    </form>

    <div class="mt-5 bg-vibe-primary/5 border border-vibe-primary/15 rounded-xl p-4 flex gap-3">
        <svg class="w-5 h-5 text-vibe-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-vibe-on-surface-variant">Pajak dan service charge dihitung otomatis di halaman kasir. Perubahan hanya berlaku untuk transaksi <strong>baru</strong>.</p>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
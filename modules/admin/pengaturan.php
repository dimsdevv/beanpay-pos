<?php
$page_title = 'Pengaturan Sistem';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole(['admin']);

// Fetch all settings
$stmt = $pdo->query("SELECT * FROM pengaturan");
$settings_db = $stmt->fetchAll();
$settings = [];
foreach($settings_db as $s) {
    $settings[$s['kunci']] = $s['nilai'];
}

// Handle POST save — MUST be before header.php (HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pajak_persen     = floatval($_POST['pajak_persen'] ?? 10);
    $service_persen   = floatval($_POST['service_charge_persen'] ?? 5);
    $aktifkan_pajak   = isset($_POST['aktifkan_pajak']) ? '1' : '0';
    $aktifkan_service = isset($_POST['aktifkan_service']) ? '1' : '0';

    $stmtUpdate = $pdo->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = ?");
    $stmtUpdate->execute([$pajak_persen,   'pajak_persen']);
    $stmtUpdate->execute([$service_persen, 'service_charge_persen']);
    $stmtUpdate->execute([$aktifkan_pajak,   'aktifkan_pajak']);
    $stmtUpdate->execute([$aktifkan_service, 'aktifkan_service']);

    $_SESSION['success'] = "Pengaturan sistem berhasil diperbarui.";
    header("Location: pengaturan.php");
    exit;
}

// Load header AFTER redirect logic
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="space-y-8 max-w-3xl">

    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-extrabold text-vibe-on-surface tracking-tight">Pengaturan Sistem</h1>
        <p class="text-vibe-on-surface-variant mt-1 text-sm font-medium">Konfigurasi parameter global seperti pajak dan biaya layanan.</p>
    </div>

    <!-- Settings Form -->
    <form method="POST" class="bg-white rounded-2xl border border-vibe-outline-variant/20 shadow-card overflow-hidden">

        <!-- Section Header -->
        <div class="px-6 py-5 border-b border-vibe-outline-variant/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-vibe-primary/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-bold text-vibe-on-surface">Pajak &amp; Biaya Layanan</h2>
                <p class="text-xs text-vibe-on-surface-variant">Pengaturan ini berlaku untuk semua transaksi baru.</p>
            </div>
        </div>

        <div class="divide-y divide-vibe-outline-variant/10">

            <!-- ── Pajak PB1 ── -->
            <div class="p-6 flex flex-col sm:flex-row sm:items-center gap-5" x-data="{ enabled: <?= ($settings['aktifkan_pajak'] ?? '1') === '1' ? 'true' : 'false' ?> }">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-vibe-on-surface">Pajak Pembangunan 1 (PB1)</span>
                        <!-- Toggle -->
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="aktifkan_pajak" value="1" class="sr-only peer"
                                   <?= ($settings['aktifkan_pajak'] ?? '1') === '1' ? 'checked' : '' ?>
                                   @change="enabled = $event.target.checked">
                            <div class="w-10 h-6 bg-vibe-outline-variant rounded-full peer peer-checked:bg-vibe-primary
                                        after:content-[''] after:absolute after:top-[3px] after:left-[3px]
                                        after:bg-white after:rounded-full after:h-[18px] after:w-[18px]
                                        after:transition-all peer-checked:after:translate-x-4 relative">
                            </div>
                        </label>
                    </div>
                    <p class="text-sm text-vibe-on-surface-variant">Pajak daerah yang dibebankan kepada pelanggan. Standarnya 10%.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <input type="number" step="0.1" min="0" max="100" name="pajak_persen"
                           value="<?= htmlspecialchars($settings['pajak_persen'] ?? '10') ?>"
                           :disabled="!enabled"
                           class="w-20 px-3 py-2 bg-vibe-bg border border-vibe-outline-variant/40 rounded-xl
                                  focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary outline-none
                                  font-bold text-vibe-on-surface text-center text-sm
                                  disabled:opacity-40 disabled:cursor-not-allowed transition-opacity">
                    <span class="font-bold text-vibe-on-surface-variant text-sm">%</span>
                </div>
            </div>

            <!-- ── Service Charge ── -->
            <div class="p-6 flex flex-col sm:flex-row sm:items-center gap-5" x-data="{ enabled: <?= ($settings['aktifkan_service'] ?? '1') === '1' ? 'true' : 'false' ?> }">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-vibe-on-surface">Service Charge</span>
                        <!-- Toggle -->
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="aktifkan_service" value="1" class="sr-only peer"
                                   <?= ($settings['aktifkan_service'] ?? '1') === '1' ? 'checked' : '' ?>
                                   @change="enabled = $event.target.checked">
                            <div class="w-10 h-6 bg-vibe-outline-variant rounded-full peer peer-checked:bg-vibe-secondary
                                        after:content-[''] after:absolute after:top-[3px] after:left-[3px]
                                        after:bg-white after:rounded-full after:h-[18px] after:w-[18px]
                                        after:transition-all peer-checked:after:translate-x-4 relative">
                            </div>
                        </label>
                    </div>
                    <p class="text-sm text-vibe-on-surface-variant">Biaya pelayanan tambahan untuk operasional restoran. Biasanya 5%.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <input type="number" step="0.1" min="0" max="100" name="service_charge_persen"
                           value="<?= htmlspecialchars($settings['service_charge_persen'] ?? '5') ?>"
                           :disabled="!enabled"
                           class="w-20 px-3 py-2 bg-vibe-bg border border-vibe-outline-variant/40 rounded-xl
                                  focus:ring-2 focus:ring-vibe-secondary/30 focus:border-vibe-secondary outline-none
                                  font-bold text-vibe-on-surface text-center text-sm
                                  disabled:opacity-40 disabled:cursor-not-allowed transition-opacity">
                    <span class="font-bold text-vibe-on-surface-variant text-sm">%</span>
                </div>
            </div>

        </div>

        <!-- Save Button -->
        <div class="px-6 py-4 bg-vibe-bg/50 border-t border-vibe-outline-variant/10 flex justify-end">
            <button type="submit"
                    class="flex items-center gap-2 px-6 py-2.5 bg-vibe-primary text-white font-bold rounded-xl
                           hover:bg-vibe-primary-container shadow-md shadow-vibe-primary/25
                           transition-all hover-lift text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Pengaturan
            </button>
        </div>
    </form>

    <!-- Info Card -->
    <div class="bg-vibe-primary/5 border border-vibe-primary/15 rounded-2xl p-5 flex gap-4">
        <div class="w-9 h-9 rounded-xl bg-vibe-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h4 class="font-bold text-vibe-primary text-sm mb-1">Cara Kerja Pajak &amp; Service Charge</h4>
            <p class="text-sm text-vibe-on-surface-variant leading-relaxed">
                Pajak dan service charge dihitung otomatis di halaman kasir saat transaksi dibuat.
                Perubahan di sini hanya berlaku untuk transaksi <strong>baru</strong> dan tidak mempengaruhi riwayat transaksi yang sudah ada.
            </p>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

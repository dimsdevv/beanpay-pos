<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

$page_title = 'Riwayat Transaksi';
require_once __DIR__ . '/../../includes/header.php';

// Sesi aktif
$stmtSesi = $pdo->prepare("SELECT s.*, u.nama_lengkap FROM sesi_kasir s JOIN users u ON s.kasir_id = u.id WHERE s.kasir_id = ? AND s.status = 'buka' LIMIT 1");
$stmtSesi->execute([$_SESSION['user_id']]);
$activeSesi = $stmtSesi->fetch();

$totalSesiIni = 0;
$totalTransaksiSesiIni = 0;
if ($activeSesi) {
    $stmtT = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah_bayar),0) as total FROM pembayaran WHERE sesi_kasir_id = ?");
    $stmtT->execute([$activeSesi['id']]);
    $sesiStats = $stmtT->fetch();
    $totalSesiIni = $sesiStats['total'];
    $totalTransaksiSesiIni = $sesiStats['cnt'];
    // Total tunai untuk perhitungan laci fisik
    $stmtCash = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar),0) FROM pembayaran WHERE sesi_kasir_id = ? AND metode_pembayaran = 'cash'");
    $stmtCash->execute([$activeSesi['id']]);
    $totalTunaiSesi = (float)$stmtCash->fetchColumn();

    // Smart End-of-Day: Best-seller
    $bestSeller = [];
    $stmtB = $pdo->prepare("
        SELECT m.nama_menu, SUM(dp.qty) as qty
        FROM detail_pesanan dp
        JOIN menu m ON dp.menu_id = m.id
        JOIN pesanan p ON dp.pesanan_id = p.id
        WHERE p.id IN (SELECT pesanan_id FROM pembayaran WHERE sesi_kasir_id = ?)
        GROUP BY dp.menu_id ORDER BY qty DESC LIMIT 3
    ");
    $stmtB->execute([$activeSesi['id']]);
    $bestSeller = $stmtB->fetchAll();

    // Smart End-of-Day: Critical ingredients
    $batasStok = 5;
    $stmtR = $pdo->query("SELECT nilai FROM pengaturan WHERE kunci = 'batas_stok_rendah'");
    if ($rowR = $stmtR->fetch()) $batasStok = max(1, (int)$rowR['nilai']);
    $stmtK = $pdo->prepare("SELECT nama_bahan, stok_sekarang, satuan FROM bahan_baku WHERE stok_sekarang <= ? ORDER BY stok_sekarang ASC LIMIT 3");
    $stmtK->execute([$batasStok]);
    $kritis = $stmtK->fetchAll();

    // Smart End-of-Day: Previous shift comparison
    $prevRevenue = 0;
    $stmtP = $pdo->prepare("SELECT total_pemasukan FROM sesi_kasir WHERE kasir_id = ? AND status = 'tutup' ORDER BY waktu_tutup DESC LIMIT 1");
    $stmtP->execute([$_SESSION['user_id']]);
    if ($rowP = $stmtP->fetch()) $prevRevenue = (float)$rowP['total_pemasukan'];
}

$transaksis = [];
if ($activeSesi) {
    $stmtTrx = $pdo->prepare("
        SELECT b.*, p.nomor_pesanan, p.tipe_pesanan, p.nama_pelanggan, m.nomor_meja
        FROM pembayaran b
        JOIN pesanan p ON b.pesanan_id = p.id
        LEFT JOIN meja m ON p.meja_id = m.id
        WHERE b.sesi_kasir_id = ?
        ORDER BY b.waktu_bayar DESC
    ");
    $stmtTrx->execute([$activeSesi['id']]);
    $transaksis = $stmtTrx->fetchAll();
}

// Last closed shift (for empty state summary)
$lastShift = null;
$lastShiftTransaksi = 0;
$lastShiftRevenue = 0;
if (!$activeSesi) {
    $stmtLast = $pdo->prepare("SELECT * FROM sesi_kasir WHERE kasir_id = ? AND status = 'tutup' ORDER BY waktu_tutup DESC LIMIT 1");
    $stmtLast->execute([$_SESSION['user_id']]);
    $lastShift = $stmtLast->fetch();
    if ($lastShift) {
        $stmtLastT = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah_bayar),0) as total FROM pembayaran WHERE sesi_kasir_id = ?");
        $stmtLastT->execute([$lastShift['id']]);
        $lastShiftStats = $stmtLastT->fetch();
        $lastShiftTransaksi = $lastShiftStats['cnt'];
        $lastShiftRevenue = $lastShiftStats['total'];
    }
}

$metodeWarna = [
    'cash'     => ['badge' => 'bg-vibe-secondary-container text-vibe-secondary', 'label' => 'Tunai'],
    'qris'     => ['badge' => 'bg-purple-50 text-purple-700', 'label' => 'QRIS'],
    'transfer' => ['badge' => 'bg-blue-50 text-blue-700', 'label' => 'Transfer'],
    'debit'    => ['badge' => 'bg-blue-50 text-blue-700', 'label' => 'Transfer'],
];

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="riwayatApp()" class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Riwayat Transaksi</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Transaksi shift yang sedang berjalan.</p>
        </div>
        <?php if($activeSesi): ?>
        <button @click="showTutupModal = true"
                class="flex items-center justify-center gap-2 px-5 py-2.5 bg-vibe-error text-white font-bold rounded-lg hover:bg-red-700 transition-colors active:scale-[0.97] w-full sm:w-auto text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
            Tutup Shift
        </button>
        <?php endif; ?>
    </div>

    <!-- Active Shift Card -->
    <?php if($activeSesi): ?>
    <div class="bg-vibe-primary text-white rounded-xl p-5 md:p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -translate-y-1/4 translate-x-1/4 blur-xl"></div>
        <div class="relative z-10 space-y-4">
            <div class="flex items-center gap-2 text-white/70 text-xs font-bold uppercase tracking-widest">
                <span class="w-1.5 h-1.5 rounded-full bg-vibe-secondary"></span>
                Shift Aktif
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-white/50 text-[10px] font-semibold uppercase tracking-wider">Dibuka</div>
                    <div class="font-bold text-sm mt-0.5"><?= date('H:i', strtotime($activeSesi['waktu_buka'])) ?></div>
                    <div class="text-white/60 text-[11px]"><?= date('d M Y', strtotime($activeSesi['waktu_buka'])) ?></div>
                </div>
                <div>
                    <div class="text-white/50 text-[10px] font-semibold uppercase tracking-wider">Modal Awal</div>
                    <div class="font-bold text-sm mt-0.5"><?= formatRupiah($activeSesi['modal_awal']) ?></div>
                </div>
                <div>
                    <div class="text-white/50 text-[10px] font-semibold uppercase tracking-wider">Total Pendapatan</div>
                    <div class="font-extrabold text-lg md:text-xl mt-0.5 text-vibe-secondary"><?= formatRupiah($totalSesiIni) ?></div>
                    <div class="text-white/60 text-[11px]"><?= $totalTransaksiSesiIni ?> transaksi</div>
                </div>
                <div>
                    <div class="text-white/50 text-[10px] font-semibold uppercase tracking-wider">Uang Tunai Masuk</div>
                    <div class="font-extrabold text-lg md:text-xl mt-0.5"><?= formatRupiah($totalTunaiSesi) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="relative">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </span>
        <input type="text" x-model="searchQuery" placeholder="Cari nomor pesanan atau pelanggan..."
               class="w-full pl-10 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
    </div>

    <!-- Transactions -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-vibe-outline-variant flex items-center justify-between">
            <h2 class="font-bold text-vibe-on-surface text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Transaksi
            </h2>
            <span class="text-xs text-vibe-on-surface-variant font-medium" x-text="'\( \' + filteredItems.length + ' \)'"></span>
        </div>

        <!-- Empty state -->
        <template x-if="filteredItems.length === 0">
            <div class="py-12 text-center">
                <svg class="w-10 h-10 mx-auto mb-3 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="font-medium text-sm text-vibe-on-surface-variant">Tidak ada transaksi ditemukan</p>
            </div>
        </template>

        <!-- Desktop: Table -->
        <template x-if="filteredItems.length > 0">
            <div>
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline">
                                <th class="px-5 py-3.5 text-left">Pesanan</th>
                                <th class="px-5 py-3.5 text-left">Waktu</th>
                                <th class="px-5 py-3.5 text-left">Metode</th>
                                <th class="px-5 py-3.5 text-right">Total</th>
                                <th class="px-5 py-3.5 text-right">Kembali</th>
                                <th class="px-5 py-3.5 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-vibe-outline/50">
                            <template x-for="t in pageItems" :key="t.id">
                                <tr class="hover:bg-vibe-surface-dim transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-sm text-vibe-on-surface" x-text="t.nomor_pesanan"></div>
                                        <div class="text-[11px] text-vibe-on-surface-variant mt-0.5">
                                            <span x-text="t.tipe_pesanan === 'dine_in' ? 'Meja ' + t.nomor_meja : 'Bungkus'"></span>
                                            <span x-show="t.nama_pelanggan" x-text="' • ' + t.nama_pelanggan"></span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-vibe-on-surface-variant font-medium" x-text="formatTime(t.waktu_bayar)"></td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider"
                                              :class="getMetodeBadge(t.metode_pembayaran)" x-text="getMetodeLabel(t.metode_pembayaran)"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-bold text-vibe-on-surface text-sm" x-text="formatRp(t.jumlah_bayar)"></td>
                                    <td class="px-5 py-3.5 text-right text-sm text-vibe-on-surface-variant font-medium" x-text="formatRp(t.kembalian)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <a :href="'struk.php?pesanan_id=' + t.pesanan_id" target="_blank"
                                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-vibe-on-surface-variant border border-vibe-outline-variant rounded-md hover:bg-vibe-surface-dim hover:text-vibe-on-surface transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            Nota
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="bg-vibe-surface-dim">
                                <td colspan="3" class="px-5 py-4 font-bold text-vibe-on-surface text-right text-sm">Total Pendapatan Shift</td>
                                <td class="px-5 py-4 text-right font-extrabold text-vibe-primary text-base" x-text="formatRp(totalRevenue)"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Mobile: Cards -->
                <div class="md:hidden divide-y divide-vibe-outline/50">
                    <template x-for="t in pageItems" :key="t.id">
                        <div class="p-4 hover:bg-vibe-surface-dim transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <div class="font-bold text-sm text-vibe-on-surface" x-text="t.nomor_pesanan"></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant mt-0.5">
                                        <span x-text="formatTime(t.waktu_bayar)"></span>
                                        <span x-text="' — ' + (t.tipe_pesanan === 'dine_in' ? 'Meja ' + t.nomor_meja : 'Bungkus')"></span>
                                        <span x-show="t.nama_pelanggan" x-text="' • ' + t.nama_pelanggan"></span>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider"
                                      :class="getMetodeBadge(t.metode_pembayaran)" x-text="getMetodeLabel(t.metode_pembayaran)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs text-vibe-on-surface-variant">Total:</span>
                                    <span class="font-bold text-sm text-vibe-on-surface ml-1" x-text="formatRp(t.jumlah_bayar)"></span>
                                    <span x-show="parseFloat(t.kembalian) > 0" class="text-[11px] text-vibe-on-surface-variant ml-2">
                                        (kembali <span x-text="formatRp(t.kembalian)"></span>)
                                    </span>
                                </div>
                                <a :href="'struk.php?pesanan_id=' + t.pesanan_id" target="_blank"
                                   class="flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold text-vibe-on-surface-variant border border-vibe-outline-variant rounded-md hover:bg-vibe-surface-dim hover:text-vibe-on-surface transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    Nota
                                </a>
                            </div>
                        </div>
                    </template>

                    <!-- Mobile total -->
                    <div class="p-4 bg-vibe-surface-dim flex items-center justify-between">
                        <span class="font-bold text-sm text-vibe-on-surface">Total Pendapatan</span>
                        <span class="font-extrabold text-base text-vibe-primary" x-text="formatRp(totalRevenue)"></span>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="px-5 py-3 border-t border-vibe-outline flex items-center justify-between" x-show="totalPages > 1">
                    <span class="text-[11px] text-vibe-on-surface-variant font-medium" x-text="'Halaman ' + currentPage + ' dari ' + totalPages"></span>
                    <div class="flex gap-1">
                        <button @click="prevPage" :disabled="currentPage === 1"
                                class="px-3 py-1.5 text-xs font-bold border border-vibe-outline-variant rounded-md text-vibe-on-surface-variant hover:bg-vibe-surface-dim disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Sebelumnya
                        </button>
                        <button @click="nextPage" :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 text-xs font-bold border border-vibe-outline-variant rounded-md text-vibe-on-surface-variant hover:bg-vibe-surface-dim disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <?php else: ?>
    <!-- No active shift -->
    <div class="space-y-4">
        <?php if ($lastShift): ?>
        <div class="bg-white border border-vibe-outline-variant rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4 text-vibe-on-surface-variant text-xs font-bold uppercase tracking-widest">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Shift Terakhir
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <div class="text-[10px] font-semibold text-vibe-on-surface-variant uppercase tracking-wider">Dibuka</div>
                    <div class="font-bold text-sm text-vibe-on-surface mt-0.5"><?= date('H:i', strtotime($lastShift['waktu_buka'])) ?></div>
                    <div class="text-[11px] text-vibe-on-surface-variant"><?= date('d M Y', strtotime($lastShift['waktu_buka'])) ?></div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold text-vibe-on-surface-variant uppercase tracking-wider">Ditutup</div>
                    <div class="font-bold text-sm text-vibe-on-surface mt-0.5"><?= $lastShift['waktu_tutup'] ? date('H:i', strtotime($lastShift['waktu_tutup'])) : '-' ?></div>
                    <div class="text-[11px] text-vibe-on-surface-variant"><?= $lastShift['waktu_tutup'] ? date('d M Y', strtotime($lastShift['waktu_tutup'])) : '' ?></div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold text-vibe-on-surface-variant uppercase tracking-wider">Pendapatan</div>
                    <div class="font-extrabold text-base text-vibe-primary mt-0.5"><?= formatRupiah($lastShiftRevenue) ?></div>
                    <div class="text-[11px] text-vibe-on-surface-variant"><?= $lastShiftTransaksi ?> transaksi</div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold text-vibe-on-surface-variant uppercase tracking-wider">Selisih</div>
                    <div class="font-extrabold text-base mt-0.5 <?= ($lastShift['selisih_kas'] ?? 0) < 0 ? 'text-vibe-error' : (($lastShift['selisih_kas'] ?? 0) > 0 ? 'text-yellow-600' : 'text-vibe-secondary') ?>">
                        <?= $lastShift['selisih_kas'] !== null ? formatRupiah($lastShift['selisih_kas']) : '-' ?>
                    </div>
                    <div class="text-[11px] <?= ($lastShift['selisih_kas'] ?? 0) == 0 ? 'text-vibe-secondary' : 'text-vibe-on-surface-variant' ?>">
                        <?= $lastShift['selisih_kas'] !== null ? ($lastShift['selisih_kas'] == 0 ? 'Rapi' : ($lastShift['selisih_kas'] < 0 ? 'Minus' : 'Lebih')) : '' ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-vibe-surface-dim border-2 border-dashed border-vibe-outline-variant rounded-xl p-10 text-center">
            <div class="w-14 h-14 rounded-xl bg-white border border-vibe-outline-variant flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="font-bold text-vibe-on-surface text-sm mb-1">Tidak Ada Shift Aktif</h3>
            <p class="text-sm text-vibe-on-surface-variant mb-4">Buka shift terlebih dahulu di halaman Kasir untuk mulai mencatat transaksi.</p>
            <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-lg hover:bg-vibe-primary-container transition-colors text-sm active:scale-[0.97]">
                Buka Shift
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Tutup Shift -->
    <?php if($activeSesi): ?>
    <div x-show="showTutupModal" x-data="{ expected: <?= $activeSesi['modal_awal'] + $totalTunaiSesi ?>, actual: '' }"
         @keydown.escape.window="showTutupModal=false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
         x-transition style="display:none">
        <div @click.stop class="bg-white rounded-xl p-6 max-w-sm w-full border border-vibe-outline-variant">
            <div class="text-center mb-5">
                <div class="w-12 h-12 rounded-xl bg-vibe-error-container text-vibe-error flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
                </div>
                <h3 class="text-lg font-display font-bold text-vibe-on-surface">Tutup Shift?</h3>
                <p class="text-sm text-vibe-on-surface-variant mt-1">Shift akan diakhiri dan kasir terkunci.</p>
            </div>

            <div class="bg-vibe-surface-dim rounded-lg p-4 mb-5 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-vibe-on-surface-variant">Modal Awal</span>
                    <span class="font-bold text-vibe-on-surface"><?= formatRupiah($activeSesi['modal_awal']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-vibe-on-surface-variant">Pendapatan (semua)</span>
                    <span class="font-bold text-vibe-secondary"><?= formatRupiah($totalSesiIni) ?></span>
                </div>
                <div class="flex justify-between text-sm pt-1 border-t border-dashed border-vibe-outline-variant">
                    <span class="text-vibe-on-surface-variant">Uang Tunai Masuk</span>
                    <span class="font-bold text-vibe-on-surface"><?= formatRupiah($totalTunaiSesi) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-vibe-on-surface text-sm">Uang di Laci (expected)</span>
                    <span class="font-extrabold text-vibe-on-surface"><?= formatRupiah($activeSesi['modal_awal'] + $totalTunaiSesi) ?></span>
                </div>
            </div>

            <!-- Ringkasan Shift -->
            <div class="bg-vibe-surface-dim rounded-lg px-4 py-3 mb-4 space-y-2 text-[11px]">
                <?php if (!empty($bestSeller)): ?>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-vibe-on-surface-variant uppercase tracking-wider shrink-0">Terlaris</span>
                    <div class="flex gap-1.5 flex-wrap">
                    <?php foreach ($bestSeller as $i => $b): ?>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-white rounded border border-vibe-outline-variant font-medium text-vibe-on-surface whitespace-nowrap">
                            <?= htmlspecialchars($b['nama_menu']) ?>
                            <span class="text-vibe-primary font-bold"><?= (int)$b['qty'] ?></span>
                        </span>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($kritis)): ?>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-vibe-on-surface-variant uppercase tracking-wider shrink-0">Kritis</span>
                    <div class="flex gap-1.5 flex-wrap">
                    <?php foreach ($kritis as $k): ?>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-white rounded border border-orange-200 font-medium text-orange-700 whitespace-nowrap">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                            <?= htmlspecialchars($k['nama_bahan']) ?>
                            <span class="text-orange-800 font-bold"><?= (float)$k['stok_sekarang'] ?></span>
                        </span>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($prevRevenue > 0): ?>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-vibe-on-surface-variant uppercase tracking-wider shrink-0">Shift lalu</span>
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-white rounded border border-vibe-outline-variant font-medium text-vibe-on-surface">
                        <?= formatRupiah($prevRevenue) ?>
                        <span class="<?= $totalSesiIni >= $prevRevenue ? 'text-vibe-secondary' : 'text-vibe-error' ?> font-bold">
                            <?= $prevRevenue > 0 ? ($totalSesiIni >= $prevRevenue ? '↑' : '↓') : '' ?>
                            <?= $prevRevenue > 0 ? round(($totalSesiIni - $prevRevenue) / $prevRevenue * 100) : 0 ?>%
                        </span>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <form action="tutup_shift.php" method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="sesi_id" value="<?= $activeSesi['id'] ?>">
                
                <div>
                    <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Uang Fisik di Laci</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant font-bold text-sm">Rp</span>
                        <input type="number" name="uang_fisik" x-model.number="actual" required min="0"
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface font-bold text-lg text-vibe-on-surface transition-colors" placeholder="0">
                    </div>
                    <div x-show="actual !== '' && actual !== null" class="mt-2 p-2.5 rounded-lg text-xs font-bold flex justify-between"
                         :class="actual == expected ? 'bg-vibe-secondary-container text-vibe-secondary' : (actual < expected ? 'bg-vibe-error-container text-vibe-error' : 'bg-yellow-50 text-yellow-700')">
                        <span>Selisih</span>
                        <span x-text="(actual - expected) >= 0 ? '+' + formatRp(actual - expected) : formatRp(actual - expected)"></span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="showTutupModal=false"
                            class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-lg bg-vibe-error text-white font-bold text-sm hover:bg-red-700 transition-colors active:scale-[0.97]">Tutup Shift</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('riwayatApp', () => ({
        items: <?= json_encode($transaksis) ?>,
        searchQuery: '',
        currentPage: 1,
        perPage: 10,
        showTutupModal: false,

        get filteredItems() {
            if (!this.searchQuery.trim()) return this.items;
            const q = this.searchQuery.toLowerCase();
            return this.items.filter(t =>
                (t.nomor_pesanan && t.nomor_pesanan.toLowerCase().includes(q)) ||
                (t.nama_pelanggan && t.nama_pelanggan.toLowerCase().includes(q))
            );
        },
        get totalPages() {
            return Math.ceil(this.filteredItems.length / this.perPage) || 1;
        },
        get pageItems() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredItems.slice(start, start + this.perPage);
        },
        get totalRevenue() {
            return this.items.reduce((s, t) => s + parseFloat(t.jumlah_bayar || 0), 0);
        },
        prevPage() {
            if (this.currentPage > 1) { this.currentPage--; }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) { this.currentPage++; }
        },

        getMetodeBadge(m) {
            const map = { 'cash': 'bg-vibe-secondary-container text-vibe-secondary', 'qris': 'bg-purple-50 text-purple-700', 'transfer': 'bg-blue-50 text-blue-700', 'debit': 'bg-blue-50 text-blue-700' };
            return map[m] || 'bg-vibe-surface-dim text-vibe-on-surface-variant';
        },
        getMetodeLabel(m) {
            const map = { 'cash': 'Tunai', 'qris': 'QRIS', 'transfer': 'Transfer', 'debit': 'Transfer' };
            return map[m] || m;
        },
        formatRp(a) {
            return 'Rp ' + parseInt(a || 0).toLocaleString('id-ID');
        },
        formatTime(dt) {
            if (!dt) return '-';
            const d = new Date(dt);
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
$page_title = 'Administrasi Keuangan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);
requireCsrfToken();

// ---------------------------------------------------------------
// Blok Try-Catch Utama untuk mencegah HTTP 500
// ---------------------------------------------------------------
$db_error = null;
$budget = 0;
$expenses = [];
$totalBelanja = 0;
$rataHari = 0;
$sisaBudget = 0;
$pctBudget = 0;
$hariAcuan = 1;
$cogsBulan = 0;
$cat = ['pembukaan' => 0, 'operasional' => 0, 'lainnya' => 0];
$bahanList = [];
$menuHpp = [];
$bulanOptions = [];
$periodeLabel = '';

try {
    // ---------------------------------------------------------------
    // Pastikan tabel keuangan ada
    // ---------------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengeluaran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            supplier VARCHAR(120) DEFAULT NULL,
            kategori ENUM('pembukaan','operasional','lainnya') NOT NULL DEFAULT 'operasional',
            keterangan TEXT,
            metode_bayar ENUM('cash','qris','transfer') NOT NULL DEFAULT 'cash',
            bukti VARCHAR(255) DEFAULT NULL,
            total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            stok_updated TINYINT(1) NOT NULL DEFAULT 0,
            input_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tanggal (tanggal),
            INDEX idx_kategori (kategori)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengeluaran_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pengeluaran_id INT NOT NULL,
            bahan_id INT DEFAULT NULL,
            nama_bahan VARCHAR(120) NOT NULL,
            qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            satuan VARCHAR(50) DEFAULT '',
            harga_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran(id) ON DELETE CASCADE,
            INDEX idx_bahan (bahan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS anggaran_bulan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            periode CHAR(7) NOT NULL UNIQUE,
            nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------------------------------------------------------------
    // Periode terpilih
    // ---------------------------------------------------------------
    $periode = $_GET['periode'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $periode)) $periode = date('Y-m');
    $periodeLabel = date('F Y', strtotime($periode . '-01'));

    for ($i = 0; $i < 12; $i++) {
        $ts = strtotime(date('Y-m-01') . " -$i months");
        $bulanOptions[] = [
            'value' => date('Y-m', $ts),
            'label' => date('M Y', $ts),
        ];
    }

    // ---------------------------------------------------------------
    // Anggaran periode
    // ---------------------------------------------------------------
    $stmtBudget = $pdo->prepare("SELECT nominal FROM anggaran_bulan WHERE periode = ?");
    $stmtBudget->execute([$periode]);
    $budget = (float)($stmtBudget->fetchColumn() ?: 0);

    // ---------------------------------------------------------------
    // Daftar pengeluaran periode + nested items
    // ---------------------------------------------------------------
    $stmtExpenses = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS input_nama, u.role AS input_role
        FROM pengeluaran p
        LEFT JOIN users u ON u.id = p.input_by
        WHERE p.tanggal >= ? AND p.tanggal <= LAST_DAY(?)
        ORDER BY p.tanggal DESC, p.id DESC
    ");
    $start_date = $periode . '-01';
    $stmtExpenses->execute([$start_date, $start_date]);
    $expenses = $stmtExpenses->fetchAll();

    $expenseIds = array_column($expenses, 'id');
    $itemsByExpense = [];
    if (!empty($expenseIds)) {
        $placeholders = rtrim(str_repeat('?,', count($expenseIds)), ',');
        $stmtItems = $pdo->prepare("SELECT * FROM pengeluaran_item WHERE pengeluaran_id IN ($placeholders) ORDER BY id ASC");
        $stmtItems->execute($expenseIds);
        foreach ($stmtItems->fetchAll() as $it) {
            $itemsByExpense[$it['pengeluaran_id']][] = $it;
        }
    }
    foreach ($expenses as &$e) {
        $e['items'] = $itemsByExpense[$e['id']] ?? [];
    }
    unset($e);

    // Ringkasan kategori & total
    foreach ($expenses as $e) {
        $totalBelanja += (float)$e['total'];
        $cat[$e['kategori']] += (float)$e['total'];
    }
    $sisaBudget = $budget - $totalBelanja;
    $pctBudget = $budget > 0 ? round($totalBelanja / $budget * 100, 1) : 0;

    $hariIni = (int)date('j');
    $hariBulan = (int)date('t', strtotime($periode . '-01'));
    $hariAcuan = ($periode === date('Y-m')) ? max(1, $hariIni) : $hariBulan;
    $rataHari = $hariAcuan > 0 ? $totalBelanja / $hariAcuan : 0;

    // ---------------------------------------------------------------
    // Daftar bahan + harga terakhir
    // ---------------------------------------------------------------
    $bahanList = $pdo->query("
        SELECT b.id, b.nama_bahan AS nama, b.satuan, b.harga_beli,
            (SELECT pi.harga_satuan FROM pengeluaran_item pi
             JOIN pengeluaran p2 ON pi.pengeluaran_id = p2.id
             WHERE pi.bahan_id = b.id ORDER BY p2.tanggal DESC, p2.id DESC LIMIT 1) AS last_price
        FROM bahan_baku b
        ORDER BY b.nama_bahan ASC
    ")->fetchAll();

    // ---------------------------------------------------------------
    // COGS per menu
    // ---------------------------------------------------------------
    $menuHpp = $pdo->query("
        SELECT m.id, m.nama_menu, m.harga,
            COALESCE(SUM(rm.jumlah_dibutuhkan * b.harga_beli), 0) AS hpp
        FROM menu m
        LEFT JOIN resep_menu rm ON rm.menu_id = m.id
        LEFT JOIN bahan_baku b ON b.id = rm.bahan_id
        GROUP BY m.id, m.nama_menu, m.harga
        ORDER BY m.nama_menu ASC
    ")->fetchAll();
    
    foreach ($menuHpp as &$m) {
        $m['hpp'] = (float)$m['hpp'];
        $m['harga'] = (float)$m['harga'];
        $m['laba'] = $m['harga'] - $m['hpp'];
        $m['margin'] = $m['harga'] > 0 ? round($m['laba'] / $m['harga'] * 100, 1) : 0;
    }
    unset($m);

    $hppMap = [];
    foreach ($menuHpp as $m) $hppMap[$m['id']] = $m['hpp'];

    $omzet = (float)$pdo->query("
        SELECT COALESCE(SUM(pb.jumlah_bayar), 0)
        FROM pembayaran pb
        WHERE DATE(pb.waktu_bayar) BETWEEN '{$periode}-01' AND LAST_DAY('{$periode}-01')
    ")->fetchColumn();

    $cogsBulanRows = $pdo->query("
        SELECT dp.menu_id, SUM(dp.qty) AS qty
        FROM detail_pesanan dp
        JOIN pesanan p ON dp.pesanan_id = p.id
        JOIN pembayaran pb ON pb.pesanan_id = p.id
        WHERE p.status_pesanan IN ('dibayar','selesai','diproses')
          AND DATE(pb.waktu_bayar) BETWEEN '{$periode}-01' AND LAST_DAY('{$periode}-01')
        GROUP BY dp.menu_id
    ")->fetchAll();
    
    foreach ($cogsBulanRows as $r) {
        $cogsBulan += (float)($hppMap[$r['menu_id']] ?? 0) * (float)$r['qty'];
    }
    $labaKotor = $omzet - $cogsBulan - $totalBelanja;

} catch (\Throwable $e) {
    $db_error = $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="space-y-6 w-full">
    <?php if ($db_error): ?>
        <div class="bg-vibe-error-container text-vibe-error p-6 rounded-xl border border-vibe-error/30 animate-fade-in">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-vibe-error/10 rounded-lg shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold font-display mb-1">Gagal Memuat Data Keuangan (HTTP 500 Dicegah)</h2>
                    <p class="text-sm opacity-90 mb-3">Terdapat masalah pada database. Kemungkinan tabel belum dibuat atau user database tidak memiliki hak akses (CREATE).</p>
                    <div class="p-3 bg-white/50 border border-vibe-error/20 rounded-lg font-mono text-xs overflow-x-auto">
                        <?= htmlspecialchars($db_error) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div x-data="keuanganApp()" class="w-full">

<style>
/* Micro-interactions & Emil Kowalski Animations */
.btn-press {
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease-out, border-color 160ms ease-out;
}
.btn-press:active {
    transform: scale(0.97);
}

/* Modal Spring Entrance */
.modal-enter {
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 200ms ease-out, transform 200ms cubic-bezier(0.23, 1, 0.32, 1);
}
.modal-enter-active {
    opacity: 1;
    transform: scale(1);
}

/* Horizontal Snap Scrollbar hiding */
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

</style>

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-vibe-primary/10 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h18M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6m-6 4h2a2 2 0 002-2V9M3 13v4a2 2 0 002 2h14a2 2 0 002-2v-4"/></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Administrasi Keuangan</h1>
                    <p class="text-sm text-vibe-on-surface-variant mt-1">Catat pengeluaran bahan baku, atur anggaran bulanan, dan pantau harga modal menu.</p>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-nowrap items-center gap-3 w-full lg:w-auto mt-6 lg:mt-0">
            <label class="relative col-span-1 sm:col-span-2 lg:col-span-1 w-full lg:w-auto">
                <select @change="gantiPeriode($event)" class="appearance-none bg-white border border-vibe-outline-variant/60 rounded-xl pl-4 pr-10 py-3 text-sm font-semibold text-vibe-on-surface focus:outline-none focus:border-vibe-primary transition-colors cursor-pointer w-full hover:bg-vibe-surface-dim">
                    <?php foreach ($bulanOptions as $bo): ?>
                        <option value="<?= $bo['value'] ?>" <?= $bo['value'] === $periode ? 'selected' : '' ?>><?= $bo['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="w-4 h-4 text-vibe-outline-variant absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <button @click="openBudget()" class="btn-press w-full lg:w-auto flex items-center justify-center gap-2 px-5 py-3 bg-white border border-vibe-outline-variant/60 rounded-xl text-sm font-bold text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim hover:border-vibe-outline-variant transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Anggaran
            </button>
            <a href="<?= BASE_URL ?>/modules/admin/catat-pengeluaran.php" class="btn-press w-full lg:w-auto flex items-center justify-center gap-2 px-5 py-3 bg-vibe-primary text-white rounded-xl text-sm font-bold hover:bg-vibe-primary-container transition-colors shadow-sm shadow-vibe-primary/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Catat Pengeluaran
            </a>
        </div>
    </div>

    <!-- KPI strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-8">
        <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-4 sm:p-6 flex flex-col justify-between transition-colors hover:border-vibe-on-surface/30 group">
            <div>
                <div class="text-[9px] sm:text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest opacity-80 leading-tight">Total Pengeluaran</div>
                <div class="text-xl sm:text-2xl font-black text-vibe-on-surface mt-1.5 group-hover:text-vibe-primary transition-colors"><?= formatRupiah($totalBelanja) ?></div>
            </div>
            <div class="text-[10px] sm:text-xs font-semibold text-vibe-on-surface-variant mt-3 sm:mt-4 opacity-70"><?= count($expenses) ?> transaksi</div>
        </div>
        <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-4 sm:p-6 flex flex-col justify-between transition-colors hover:border-vibe-on-surface/30 group">
            <div>
                <div class="text-[9px] sm:text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest opacity-80 leading-tight">Pemakaian Anggaran</div>
                <div class="text-xl sm:text-2xl font-black mt-1.5 <?= $sisaBudget < 0 ? 'text-vibe-error' : 'text-vibe-on-surface group-hover:text-vibe-primary transition-colors' ?>">
                    <?= $budget > 0 ? $pctBudget . '%' : '—' ?>
                </div>
            </div>
            <div class="text-[10px] sm:text-xs font-semibold mt-3 sm:mt-4 <?= $sisaBudget < 0 ? 'text-vibe-error bg-vibe-error/10 px-2 py-1 rounded-md inline-block w-fit' : 'text-vibe-on-surface-variant opacity-70' ?>">
                Sisa <?= formatRupiah($sisaBudget) ?>
            </div>
        </div>
        <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-4 sm:p-6 flex flex-col justify-between transition-colors hover:border-vibe-on-surface/30 group">
            <div>
                <div class="text-[9px] sm:text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest opacity-80 leading-tight">Rata Harian</div>
                <div class="text-xl sm:text-2xl font-black text-vibe-on-surface mt-1.5 group-hover:text-vibe-primary transition-colors"><?= formatRupiah($rataHari) ?></div>
            </div>
            <div class="text-[10px] sm:text-xs font-semibold text-vibe-on-surface-variant mt-3 sm:mt-4 opacity-70"><?= $hariAcuan ?> hari operasional</div>
        </div>
        <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-4 sm:p-6 flex flex-col justify-between transition-colors hover:border-vibe-on-surface/30 group">
            <div>
                <div class="text-[9px] sm:text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest opacity-80 leading-tight">Biaya Bahan Terpakai</div>
                <div class="text-xl sm:text-2xl font-black text-vibe-accent mt-1.5 group-hover:text-vibe-primary transition-colors"><?= formatRupiah($cogsBulan) ?></div>
            </div>
            <div class="text-[10px] sm:text-xs font-semibold text-vibe-on-surface-variant mt-3 sm:mt-4 opacity-70">Estimasi HPP</div>
        </div>
    </div>

    <!-- Budget Pulse (signature) + Posisi Laba -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
        <!-- Budget Pulse -->
        <div class="lg:col-span-2 bg-white border border-vibe-outline-variant/60 rounded-2xl p-6 animate-fade-up">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="font-display text-lg font-bold text-vibe-on-surface">Pantauan Anggaran</h3>
                    <p class="text-xs text-vibe-on-surface-variant mt-1">Perbandingan total pengeluaran dengan batas anggaran bulan ini</p>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Batas Anggaran</div>
                    <div class="font-bold text-vibe-on-surface"><?= $budget > 0 ? formatRupiah($budget) : 'Belum diatur' ?></div>
                </div>
            </div>

            <?php if ($budget <= 0): ?>
                <div class="flex items-center gap-3 bg-vibe-surface-dim rounded-lg px-4 py-3.5">
                    <svg class="w-5 h-5 text-vibe-on-surface-variant shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-vibe-on-surface-variant">Atur pagu anggaran bulan ini untuk memantau realisasi belanja. Tekan tombol <span class="font-semibold text-vibe-on-surface">Anggaran</span>.</p>
                </div>
            <?php else: ?>
                <?php
                    $wPem = $budget > 0 ? min(100, $cat['pembukaan'] / $budget * 100) : 0;
                    $wOpe = $budget > 0 ? min(100, $cat['operasional'] / $budget * 100) : 0;
                    $wLain = $budget > 0 ? min(100, $cat['lainnya'] / $budget * 100) : 0;
                    $over = $totalBelanja > $budget;
                ?>
                <div class="relative h-4 rounded-full bg-vibe-surface-container overflow-hidden flex">
                    <div class="h-full bg-vibe-secondary transition-all duration-700 ease-out" style="width:<?= $wPem ?>%" title="Pembukaan"></div>
                    <div class="h-full bg-vibe-accent transition-all duration-700 ease-out" style="width:<?= $wOpe ?>%" title="Operasional"></div>
                    <div class="h-full bg-vibe-tertiary transition-all duration-700 ease-out" style="width:<?= $wLain ?>%" title="Lainnya"></div>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <div class="text-xs text-vibe-on-surface-variant">
                        Terpakai <span class="font-bold text-vibe-on-surface"><?= formatRupiah($totalBelanja) ?></span>
                        <?php if ($over): ?>
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-vibe-error-container text-vibe-error text-[10px] font-bold">Lewat pagu</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs font-bold <?= $over ? 'text-vibe-error' : 'text-vibe-on-surface' ?>"><?= $pctBudget ?>%</div>
                </div>
                <!-- Legend -->
                <div class="grid grid-cols-3 gap-2 mt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-vibe-secondary shrink-0"></span>
                        <div class="min-w-0">
                            <div class="text-[11px] text-vibe-on-surface-variant truncate">Pembukaan</div>
                            <div class="text-sm font-bold text-vibe-on-surface"><?= formatRupiah($cat['pembukaan']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-vibe-accent shrink-0"></span>
                        <div class="min-w-0">
                            <div class="text-[11px] text-vibe-on-surface-variant truncate">Operasional</div>
                            <div class="text-sm font-bold text-vibe-on-surface"><?= formatRupiah($cat['operasional']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-vibe-tertiary shrink-0"></span>
                        <div class="min-w-0">
                            <div class="text-[11px] text-vibe-on-surface-variant truncate">Lainnya</div>
                            <div class="text-sm font-bold text-vibe-on-surface"><?= formatRupiah($cat['lainnya']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Posisi Laba -->
        <div class="bg-white border border-vibe-outline-variant/60 rounded-2xl p-6 animate-fade-up" style="animation-delay:.1s">
            <h3 class="font-display text-lg font-bold text-vibe-on-surface mb-1">Keuntungan Sementara</h3>
            <p class="text-xs text-vibe-on-surface-variant mb-6"><?= $periodeLabel ?></p>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-vibe-on-surface-variant">Omzet</span>
                    <span class="font-bold text-vibe-secondary"><?= formatRupiah($omzet) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-vibe-on-surface-variant">HPP terpakai</span>
                    <span class="font-semibold text-vibe-accent">− <?= formatRupiah($cogsBulan) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-vibe-on-surface-variant">Belanja bahan</span>
                    <span class="font-semibold text-vibe-tertiary">− <?= formatRupiah($totalBelanja) ?></span>
                </div>
                <div class="border-t border-vibe-outline-variant pt-2.5 flex items-center justify-between">
                    <span class="text-sm font-bold text-vibe-on-surface">Laba Kotor</span>
                    <span class="font-black text-lg <?= $labaKotor >= 0 ? 'text-vibe-on-surface' : 'text-vibe-error' ?>"><?= formatRupiah($labaKotor) ?></span>
                </div>
            </div>
            <p class="text-[11px] text-vibe-on-surface-variant mt-4 leading-relaxed">
                <?= $labaKotor >= 0
                    ? 'Pendapatan saat ini sudah berhasil menutupi modal bahan baku dan pengeluaran lainnya.'
                    : 'Pengeluaran masih lebih besar daripada pemasukan (wajar jika sedang masa awal pembukaan).' ?>
            </p>
        </div>
    </div>

    <!-- Transaksi -->
    <div class="bg-white border border-vibe-outline-variant/60 rounded-2xl overflow-hidden animate-fade-up mt-8 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 border-b border-vibe-outline-variant/50">
            <div>
                <h3 class="font-display text-lg font-bold text-vibe-on-surface">Riwayat Pengeluaran</h3>
                <p class="text-xs text-vibe-on-surface-variant mt-1">Daftar semua transaksi pada <?= $periodeLabel ?></p>
            </div>
            <div class="relative w-full sm:w-72">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" x-model="search" placeholder="Cari nama supplier atau catatan..." class="w-full pl-10 pr-4 py-3 bg-vibe-surface-dim border border-transparent rounded-xl focus:outline-none focus:border-vibe-primary focus:bg-white text-sm transition-colors">
            </div>
        </div>

        <div class="overflow-x-auto hidden sm:block">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-4 text-left">Tanggal</th>
                        <th class="px-5 py-4 text-left">Supplier</th>
                        <th class="px-5 py-4 text-left">Kategori</th>
                        <th class="px-5 py-4 text-center">Metode</th>
                        <th class="px-5 py-4 text-right">Total</th>
                        <th class="px-5 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <template x-for="e in paginatedExpenses" :key="e.id">
                        <tr class="hover:bg-vibe-surface-dim transition-colors group">
                            <td class="px-5 py-4 text-sm text-vibe-on-surface-variant group-hover:text-vibe-on-surface transition-colors" x-text="e.tanggal"></td>
                            <td class="px-5 py-4">
                                <div class="font-bold text-sm text-vibe-on-surface" x-text="e.supplier || '—'"></div>
                                <div class="text-[11px] font-medium text-vibe-on-surface-variant opacity-80 flex items-center gap-1.5">
                                    <span x-text="(e.items ? e.items.length : 0) + ' item'"></span>
                                    <span>·</span>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold"
                                        :class="e.input_role === 'kasir' ? 'bg-vibe-accent/10 text-vibe-accent' : 'bg-vibe-primary/10 text-vibe-primary'"
                                        x-text="e.input_role === 'kasir' ? 'KASIR: ' + (e.input_nama || '-') : (e.input_nama || 'ADMIN')"></span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold border"
                                    :class="{
                                        'bg-vibe-secondary/10 text-vibe-secondary border-vibe-secondary/20': e.kategori==='pembukaan',
                                        'bg-vibe-accent/10 text-vibe-accent border-vibe-accent/20': e.kategori==='operasional',
                                        'bg-vibe-tertiary/10 text-vibe-tertiary border-vibe-tertiary/20': e.kategori==='lainnya'
                                    }"
                                    x-text="e.kategori === 'pembukaan' ? 'Pembukaan' : (e.kategori === 'operasional' ? 'Operasional' : 'Lainnya')"></span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-vibe-on-surface-variant capitalize"
                                    :class="{'text-vibe-secondary': e.metode_bayar==='qris', 'text-vibe-accent': e.metode_bayar==='transfer'}"
                                    x-text="e.metode_bayar"></span>
                            </td>
                            <td class="px-5 py-4 text-right font-black text-vibe-on-surface" x-text="fmt(e.total)"></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-center gap-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button @click="showDetail(e)" title="Detail" class="p-2 rounded-lg text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-container transition-colors btn-press">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <a :href="'<?= BASE_URL ?>/modules/admin/catat-pengeluaran.php?id=' + e.id" title="Ubah" class="p-2 rounded-lg text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-container transition-colors btn-press inline-block">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button @click="confirmDelete(e)" title="Hapus" class="p-2 rounded-lg text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error-container transition-colors btn-press">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List for Riwayat Pengeluaran -->
        <div class="sm:hidden flex flex-col divide-y divide-vibe-outline-variant/50">
            <template x-for="e in paginatedExpenses" :key="e.id">
                <div class="p-4 flex flex-col gap-3 hover:bg-vibe-surface-dim transition-colors">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-bold text-sm text-vibe-on-surface" x-text="e.supplier || 'Tanpa Supplier'"></div>
                            <div class="text-[11px] text-vibe-on-surface-variant mt-0.5" x-text="e.tanggal"></div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-sm text-vibe-on-surface" x-text="fmt(e.total)"></div>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-bold border mt-1"
                                :class="{
                                    'bg-vibe-secondary/10 text-vibe-secondary border-vibe-secondary/20': e.kategori==='pembukaan',
                                    'bg-vibe-accent/10 text-vibe-accent border-vibe-accent/20': e.kategori==='operasional',
                                    'bg-vibe-tertiary/10 text-vibe-tertiary border-vibe-tertiary/20': e.kategori==='lainnya'
                                }"
                                x-text="e.kategori === 'pembukaan' ? 'Pembukaan' : (e.kategori === 'operasional' ? 'Operasional' : 'Lainnya')"></span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <div class="text-[10px] font-medium text-vibe-on-surface-variant flex items-center gap-1.5">
                            <span class="capitalize" x-text="e.metode_bayar"></span>
                            <span>·</span>
                            <span x-text="(e.items ? e.items.length : 0) + ' item'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="showDetail(e)" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface bg-white border border-vibe-outline-variant/60 rounded-md shadow-sm btn-press">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                            <a :href="'<?= BASE_URL ?>/modules/admin/catat-pengeluaran.php?id=' + e.id" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface bg-white border border-vibe-outline-variant/60 rounded-md shadow-sm btn-press">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <button @click="confirmDelete(e)" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-error bg-white border border-vibe-outline-variant/60 rounded-md shadow-sm btn-press">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State & Pagination -->
        <div x-show="filteredExpenses().length === 0" class="p-10 sm:p-12 text-center flex flex-col items-center justify-center">
            <div class="w-16 h-16 bg-vibe-surface-dim rounded-full flex items-center justify-center text-vibe-on-surface-variant mb-4">
                <svg class="w-8 h-8 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h4 class="text-base font-bold text-vibe-on-surface mb-1">Belum ada catatan</h4>
            <p class="text-xs text-vibe-on-surface-variant max-w-[250px] mx-auto mb-5">Catat semua pengeluaran operasional Anda agar mudah dilacak.</p>
            <a href="<?= BASE_URL ?>/modules/admin/catat-pengeluaran.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-vibe-primary text-white text-sm font-bold rounded-xl hover:bg-vibe-primary-container transition-colors shadow-sm btn-press">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Catat Sekarang
            </a>
        </div>
        
        <div x-show="filteredExpenses().length > 10 && !showAllExpenses" class="p-4 border-t border-vibe-outline-variant/50 text-center bg-vibe-surface-dim/30">
            <button @click="showAllExpenses = true" class="text-sm font-bold text-vibe-primary hover:text-vibe-primary-container transition-colors btn-press inline-block py-1">
                Tampilkan semua (<span x-text="filteredExpenses().length"></span>)
            </button>
        </div>
    </div>

    <!-- COGS per Menu -->
    <div class="bg-white border border-vibe-outline-variant/60 rounded-2xl overflow-hidden animate-fade-up mt-8 shadow-sm">
        <div class="p-6 border-b border-vibe-outline-variant/50">
            <h3 class="font-display text-lg font-bold text-vibe-on-surface">Keuntungan per Menu</h3>
            <p class="text-xs text-vibe-on-surface-variant mt-1">Perbandingan antara harga modal bahan dengan harga jual menu</p>
        </div>
        <div class="overflow-x-auto hidden sm:block">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-4 text-left">Menu</th>
                        <th class="px-5 py-4 text-right">Harga Jual</th>
                        <th class="px-5 py-4 text-right" title="Harga Pokok Penjualan (Modal Bahan)">Modal Bahan</th>
                        <th class="px-5 py-4 text-right">Laba</th>
                        <th class="px-5 py-4 text-left">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php foreach ($menuHpp as $m): ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors group">
                        <td class="px-5 py-4 font-bold text-sm text-vibe-on-surface"><?= htmlspecialchars($m['nama_menu']) ?></td>
                        <td class="px-5 py-4 text-right text-sm font-semibold text-vibe-on-surface-variant group-hover:text-vibe-on-surface transition-colors"><?= formatRupiah($m['harga']) ?></td>
                        <td class="px-5 py-4 text-right text-sm text-vibe-accent font-bold"><?= formatRupiah($m['hpp']) ?></td>
                        <td class="px-5 py-4 text-right text-sm font-black text-vibe-on-surface"><?= formatRupiah($m['laba']) ?></td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2.5">
                                <div class="flex-1 h-2 rounded-full bg-vibe-surface-container overflow-hidden max-w-[140px]">
                                    <div class="h-full rounded-full <?= $m['margin'] >= 60 ? 'bg-vibe-secondary' : ($m['margin'] >= 30 ? 'bg-vibe-accent' : 'bg-vibe-error') ?>"
                                         style="width:<?= max(4, min(100, $m['margin'])) ?>%"></div>
                                </div>
                                <span class="text-xs font-bold <?= $m['margin'] >= 60 ? 'text-vibe-secondary' : ($m['margin'] >= 30 ? 'text-vibe-accent' : 'text-vibe-error') ?> w-12 text-right"><?= $m['margin'] ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card Grid for Menu Keuntungan -->
        <div class="sm:hidden grid grid-cols-1 gap-3 p-4 bg-vibe-surface-dim/30">
            <?php foreach ($menuHpp as $m): ?>
                <div class="bg-white border border-vibe-outline-variant/60 rounded-xl p-4 shadow-sm hover:border-vibe-on-surface/30 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <div class="font-bold text-sm text-vibe-on-surface truncate pr-2"><?= htmlspecialchars($m['nama_menu']) ?></div>
                        <div class="font-black text-sm text-vibe-on-surface shrink-0"><?= formatRupiah($m['harga']) ?></div>
                    </div>
                    <div class="flex justify-between items-center mb-3">
                        <div class="text-[11px] text-vibe-on-surface-variant">Modal: <span class="font-bold text-vibe-accent"><?= formatRupiah($m['hpp']) ?></span></div>
                        <div class="text-[11px] text-vibe-on-surface-variant">Laba: <span class="font-bold text-vibe-on-surface"><?= formatRupiah($m['laba']) ?></span></div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex-1 h-1.5 rounded-full bg-vibe-surface-container overflow-hidden">
                            <div class="h-full rounded-full <?= $m['margin'] >= 60 ? 'bg-vibe-secondary' : ($m['margin'] >= 30 ? 'bg-vibe-accent' : 'bg-vibe-error') ?>"
                                 style="width:<?= max(4, min(100, $m['margin'])) ?>%"></div>
                        </div>
                        <span class="text-[10px] font-bold <?= $m['margin'] >= 60 ? 'text-vibe-secondary' : ($m['margin'] >= 30 ? 'text-vibe-accent' : 'text-vibe-error') ?> w-9 text-right"><?= $m['margin'] ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($menuHpp)): ?>
                <div class="p-8 text-center text-sm text-vibe-on-surface-variant">Belum ada menu.</div>
            <?php endif; ?>
        </div>
    </div>


<!-- ============ MODAL: DETAIL ============ -->
<div x-show="showDetailModal" @keydown.escape.window="showDetailModal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-vibe-surface-dim/80 backdrop-blur-sm" x-transition.opacity.duration.200ms style="display:none">
    <div @click.stop class="bg-white rounded-2xl w-full max-w-lg border border-vibe-outline-variant/50 max-h-[85vh] flex flex-col shadow-2xl"
         x-show="showDetailModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
        <div class="flex items-center justify-between px-6 py-5 border-b border-vibe-outline-variant/50 shrink-0">
            <div>
                <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="detail?.supplier || 'Detail Pengeluaran'"></h3>
                <p class="text-xs text-vibe-on-surface-variant"><span x-text="detail?.tanggal"></span> · <span x-text="detail?.kategori === 'pembukaan' ? 'Pembukaan' : (detail?.kategori === 'operasional' ? 'Operasional' : 'Lainnya')"></span></p>
            </div>
            <button @click="showDetailModal=false" class="p-2 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-lg transition-colors btn-press">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto min-h-0 px-6 py-5 space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                    <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Metode</div>
                    <div class="text-sm font-bold text-vibe-on-surface mt-1 capitalize" x-text="detail?.metode_bayar"></div>
                </div>
                <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                    <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Input</div>
                    <div class="text-sm font-bold text-vibe-on-surface mt-1 truncate" x-text="detail?.input_nama || 'admin'"></div>
                </div>
                <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                    <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total</div>
                    <div class="text-sm font-bold text-vibe-primary mt-1" x-text="fmt(detail?.total)"></div>
                </div>
            </div>

            <div>
                <div class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-2">Rincian Item</div>
                <div class="space-y-1.5">
                    <template x-for="(it, i) in (detail?.items || [])" :key="i">
                        <div class="flex items-center justify-between bg-white border border-vibe-outline-variant rounded-lg px-3.5 py-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-vibe-on-surface truncate" x-text="it.nama_bahan"></div>
                                <div class="text-[11px] text-vibe-on-surface-variant">
                                    <span x-text="it.qty_beli"></span> <span x-text="it.satuan_beli"></span>
                                    <template x-if="it.konversi && it.konversi != 1">
                                        <span> (Masuk gudang: <span x-text="it.qty"></span> <span x-text="it.satuan"></span>)</span>
                                    </template>
                                    × Rp <span x-text="Number(it.harga_satuan).toLocaleString('id-ID')"></span>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-3 font-bold text-sm text-vibe-on-surface" x-text="fmt(it.subtotal)"></div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="detail?.keterangan">
                <div class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1">Keterangan</div>
                <p class="text-sm text-vibe-on-surface" x-text="detail?.keterangan"></p>
            </div>

            <div x-show="detail?.bukti">
                <div class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1">Bukti</div>
                <img :src="'<?= BASE_URL ?>/assets/uploads/bukti/' + detail?.bukti" alt="Bukti" class="max-h-48 rounded-lg border border-vibe-outline-variant object-contain">
            </div>
        </div>
        <div class="px-6 py-5 border-t border-vibe-outline-variant/50 flex gap-3 shrink-0">
            <button type="button" @click="showDetailModal=false" class="btn-press flex-1 py-3 rounded-xl border border-vibe-outline-variant/60 text-vibe-on-surface-variant font-bold text-sm hover:text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors">Tutup</button>
            <button type="button" @click="openEdit(detail); showDetailModal=false" class="btn-press flex-1 py-3 rounded-xl bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors shadow-sm shadow-vibe-primary/20">Ubah</button>
        </div>
    </div>
</div>

<!-- ============ MODAL: ANGGARAN ============ -->
<div x-show="showBudgetModal" @keydown.escape.window="showBudgetModal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-vibe-surface-dim/80 backdrop-blur-sm" x-transition.opacity.duration.200ms style="display:none">
    <div @click.stop class="bg-white rounded-2xl w-full max-w-sm border border-vibe-outline-variant/50 p-7 shadow-2xl"
         x-show="showBudgetModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
        <h3 class="text-lg font-display font-bold text-vibe-on-surface mb-1">Atur Anggaran Bulanan</h3>
        <p class="text-xs text-vibe-on-surface-variant mb-5" x-text="'Batas pengeluaran untuk ' + periodeLabel"></p>
        <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Batas Anggaran (Rp)</label>
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant">Rp</span>
            <input type="number" step="1" min="0" x-model="budgetNominal" class="w-full pl-9 pr-4 py-3 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
        </div>
        <div class="flex gap-3 mt-8">
            <button type="button" @click="showBudgetModal=false" class="btn-press flex-1 py-3 rounded-xl border border-vibe-outline-variant/60 text-vibe-on-surface-variant font-bold text-sm hover:text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors">Batal</button>
            <button type="button" @click="submitBudget()" :disabled="savingBudget" class="btn-press flex-1 py-3 rounded-xl bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors disabled:opacity-60 shadow-sm shadow-vibe-primary/20" x-text="savingBudget ? 'Menyimpan…' : 'Simpan'"></button>
        </div>
    </div>
</div>

    </div> <?php /* close x-data="keuanganApp()" */ ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('keuanganApp', () => ({
        periode: '<?= $periode ?>',
        periodeLabel: '<?= $periodeLabel ?>',
        search: '',
        savingBudget: false,

        expenses: <?= json_encode($expenses, JSON_UNESCAPED_SLASHES) ?>,
        budgetNominal: <?= (int)$budget ?>,

        showDetailModal: false,
        showBudgetModal: false,
        detail: null,

        gantiPeriode(e) {
            const v = e.target.value;
            window.location.href = '<?= BASE_URL ?>/modules/admin/keuangan.php?periode=' + v;
        },

        fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },

        filteredExpenses() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.expenses;
            return this.expenses.filter(e =>
                (e.supplier || '').toLowerCase().includes(q) ||
                (e.keterangan || '').toLowerCase().includes(q)
            );
        },

        showAllExpenses: false,
        get paginatedExpenses() {
            const arr = this.filteredExpenses();
            return this.showAllExpenses ? arr : arr.slice(0, 10);
        },



        // ---- Detail ----
        showDetail(e) { this.detail = e; this.showDetailModal = true; },

        // ---- Delete ----
        async confirmDelete(e) {
            const r = await Swal.fire({
                icon: 'warning', title: 'Hapus pengeluaran?',
                text: (e.supplier || 'Tanpa supplier') + ' · ' + this.fmt(e.total),
                showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                confirmButtonColor: '#DC2626'
            });
            if (!r.isConfirmed) return;
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', 'delete');
                fd.append('id', e.id);
                const res = await fetch('proses_pengeluaran.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                Swal.fire({ icon: 'success', title: 'Dihapus', timer: 1300, showConfirmButton: false });
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            }
        },

        // ---- Budget ----
        openBudget() { this.showBudgetModal = true; },
        async submitBudget() {
            this.savingBudget = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', 'set_budget');
                fd.append('periode', this.periode);
                fd.append('nominal', this.budgetNominal || 0);
                const res = await fetch('proses_pengeluaran.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                Swal.fire({ icon: 'success', title: 'Tersimpan', timer: 1300, showConfirmButton: false });
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            } finally {
                this.savingBudget = false;
            }
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

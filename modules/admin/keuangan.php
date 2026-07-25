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

    // Patch for Hostinger existing tables with wrong collation
    $pdo->exec("ALTER TABLE pengeluaran CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE pengeluaran_item CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE anggaran_bulan CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

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
        SELECT p.*, u.nama_lengkap AS input_nama
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
        ORDER BY (m.harga - COALESCE(SUM(rm.jumlah_dibutuhkan * b.harga_beli), 0)) DESC
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

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="space-y-6">
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
        <div x-data="keuanganApp()">

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-vibe-primary/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h18M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6m-6 4h2a2 2 0 002-2V9M3 13v4a2 2 0 002 2h14a2 2 0 002-2v-4"/></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Administrasi Keuangan</h1>
                    <p class="text-sm text-vibe-on-surface-variant mt-0.5">Catat belanja bahan, pantau anggaran, dan hitung HPP menu.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2.5">
            <label class="relative">
                <select @change="gantiPeriode($event)" class="appearance-none bg-white border border-vibe-outline-variant rounded-lg pl-3.5 pr-9 py-2.5 text-sm font-semibold text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface transition-colors cursor-pointer">
                    <?php foreach ($bulanOptions as $bo): ?>
                        <option value="<?= $bo['value'] ?>" <?= $bo['value'] === $periode ? 'selected' : '' ?>><?= $bo['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="w-4 h-4 text-vibe-outline-variant absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <button @click="openBudget()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant hover:bg-vibe-surface-dim transition-colors active:scale-[0.99]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Anggaran
            </button>
            <button @click="openAdd()" class="flex items-center gap-2 px-4 py-2.5 bg-vibe-primary text-white rounded-lg text-sm font-bold hover:bg-vibe-primary-container transition-colors active:scale-[0.99]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Catat Pengeluaran
            </button>
        </div>
    </div>

    <!-- KPI strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5 animate-fade-up">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Belanja</div>
            <div class="text-xl font-black text-vibe-on-surface mt-1"><?= formatRupiah($totalBelanja) ?></div>
            <div class="text-[11px] text-vibe-on-surface-variant mt-0.5"><?= count($expenses) ?> transaksi · <?= $periodeLabel ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5 animate-fade-up" style="animation-delay:.05s">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Realisasi Anggaran</div>
            <div class="text-xl font-black mt-1 <?= $sisaBudget < 0 ? 'text-vibe-error' : 'text-vibe-on-surface' ?>">
                <?= $budget > 0 ? $pctBudget . '%' : '—' ?>
            </div>
            <div class="text-[11px] mt-0.5 <?= $sisaBudget < 0 ? 'text-vibe-error font-semibold' : 'text-vibe-on-surface-variant' ?>">
                Sisa <?= formatRupiah($sisaBudget) ?>
            </div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5 animate-fade-up" style="animation-delay:.1s">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Rata-rata / Hari</div>
            <div class="text-xl font-black text-vibe-on-surface mt-1"><?= formatRupiah($rataHari) ?></div>
            <div class="text-[11px] text-vibe-on-surface-variant mt-0.5">Acuan <?= $hariAcuan ?> hari</div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5 animate-fade-up" style="animation-delay:.15s">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total HPP (Bulan)</div>
            <div class="text-xl font-black text-vibe-accent mt-1"><?= formatRupiah($cogsBulan) ?></div>
            <div class="text-[11px] text-vibe-on-surface-variant mt-0.5">Biaya bahan terpakai</div>
        </div>
    </div>

    <!-- Budget Pulse (signature) + Posisi Laba -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Budget Pulse -->
        <div class="lg:col-span-2 bg-white border border-vibe-outline-variant rounded-xl p-5 animate-fade-up">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-display font-bold text-vibe-on-surface">Detak Anggaran</h3>
                    <p class="text-[11px] text-vibe-on-surface-variant">Realisasi belanja terhadap pagu <?= $periodeLabel ?></p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-vibe-on-surface-variant">Pagu</div>
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
        <div class="bg-white border border-vibe-outline-variant rounded-xl p-5 animate-fade-up" style="animation-delay:.1s">
            <h3 class="font-display font-bold text-vibe-on-surface mb-1">Posisi Laba</h3>
            <p class="text-[11px] text-vibe-on-surface-variant mb-4"><?= $periodeLabel ?></p>
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
            <p class="text-[11px] text-vibe-on-surface-variant mt-3 leading-relaxed">
                <?= $labaKotor >= 0
                    ? 'Pendapatan sudah menutupi HPP dan belanja bahan periode ini.'
                    : 'Belanja bahan masih lebih besar dari omzet — wajar di masa pembukaan.' ?>
            </p>
        </div>
    </div>

    <!-- Transaksi -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden animate-fade-up">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-vibe-outline-variant">
            <div>
                <h3 class="font-display font-bold text-vibe-on-surface">Riwayat Pengeluaran</h3>
                <p class="text-[11px] text-vibe-on-surface-variant"><?= $periodeLabel ?></p>
            </div>
            <div class="relative w-full sm:w-64">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" x-model="search" placeholder="Cari supplier / catatan..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left">Tanggal</th>
                        <th class="px-5 py-3.5 text-left">Supplier</th>
                        <th class="px-5 py-3.5 text-left">Kategori</th>
                        <th class="px-5 py-3.5 text-center">Metode</th>
                        <th class="px-5 py-3.5 text-right">Total</th>
                        <th class="px-5 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <template x-for="e in filteredExpenses()" :key="e.id">
                        <tr class="hover:bg-vibe-surface-dim transition-colors">
                            <td class="px-5 py-3.5 text-sm text-vibe-on-surface-variant" x-text="e.tanggal"></td>
                            <td class="px-5 py-3.5">
                                <div class="font-semibold text-sm text-vibe-on-surface" x-text="e.supplier || '—'"></div>
                                <div class="text-[11px] text-vibe-on-surface-variant" x-text="(e.items ? e.items.length : 0) + ' item · ' + (e.input_nama || 'admin')"></div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold border"
                                    :class="{
                                        'bg-vibe-secondary/10 text-vibe-secondary border-vibe-secondary/20': e.kategori==='pembukaan',
                                        'bg-vibe-accent/10 text-vibe-accent border-vibe-accent/20': e.kategori==='operasional',
                                        'bg-vibe-tertiary/10 text-vibe-tertiary border-vibe-tertiary/20': e.kategori==='lainnya'
                                    }"
                                    x-text="e.kategori === 'pembukaan' ? 'Pembukaan' : (e.kategori === 'operasional' ? 'Operasional' : 'Lainnya')"></span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-vibe-on-surface-variant capitalize"
                                    :class="{'text-vibe-secondary': e.metode_bayar==='qris', 'text-vibe-accent': e.metode_bayar==='transfer'}"
                                    x-text="e.metode_bayar"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-vibe-on-surface" x-text="fmt(e.total)"></td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="showDetail(e)" title="Detail" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-container transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button @click="openEdit(e)" title="Ubah" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-container transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="confirmDelete(e)" title="Hapus" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error-container transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredExpenses().length === 0">
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-vibe-on-surface-variant">Belum ada pengeluaran tercatat untuk periode ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- COGS per Menu -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden animate-fade-up">
        <div class="p-5 border-b border-vibe-outline-variant">
            <h3 class="font-display font-bold text-vibe-on-surface">HPP &amp; Margin per Menu</h3>
            <p class="text-[11px] text-vibe-on-surface-variant">Biaya bahan (resep × harga beli) vs harga jual</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left">Menu</th>
                        <th class="px-5 py-3.5 text-right">Harga Jual</th>
                        <th class="px-5 py-3.5 text-right">HPP</th>
                        <th class="px-5 py-3.5 text-right">Laba</th>
                        <th class="px-5 py-3.5 text-left">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php foreach ($menuHpp as $m): ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors">
                        <td class="px-5 py-3.5 font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($m['nama_menu']) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm text-vibe-on-surface"><?= formatRupiah($m['harga']) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm text-vibe-accent font-medium"><?= formatRupiah($m['hpp']) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm font-bold text-vibe-on-surface"><?= formatRupiah($m['laba']) ?></td>
                        <td class="px-5 py-3.5">
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
                    <?php if (empty($menuHpp)): ?>
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-vibe-on-surface-variant">Belum ada menu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ============ MODAL: FORM PENGELUARAN ============ -->
<div x-show="showForm" @keydown.escape.window="showForm=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" x-transition style="display:none">
    <div @click.stop class="bg-white rounded-xl w-full max-w-2xl border border-vibe-outline-variant max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-vibe-outline-variant shrink-0">
            <div>
                <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="form.id ? 'Ubah Pengeluaran' : 'Catat Pengeluaran'"></h3>
                <p class="text-xs text-vibe-on-surface-variant">Isi belanja bahan — harga otomatis dari riwayat bila ada.</p>
            </div>
            <button @click="showForm=false" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-md transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto min-h-0 px-6 py-5 space-y-4">
            <!-- Baris atas -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Tanggal</label>
                    <input type="date" x-model="form.tanggal" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Kategori</label>
                    <select x-model="form.kategori" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
                        <option value="operasional">Operasional</option>
                        <option value="pembukaan">Belanja Pembukaan</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Supplier / Toko</label>
                    <input type="text" x-model="form.supplier" placeholder="Mis. Toko Sembako Makmur" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Metode Bayar</label>
                    <select x-model="form.metode_bayar" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
                        <option value="cash">Tunai</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </div>
            </div>

            <!-- Item belanja -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Item Bahan</label>
                    <button type="button" @click="addItem()" class="inline-flex items-center gap-1 text-[11px] font-bold text-vibe-primary hover:underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Tambah item
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(it, idx) in form.items" :key="idx">
                        <div class="grid grid-cols-12 gap-2 items-end bg-vibe-surface-dim rounded-lg p-2.5">
                            <div class="col-span-12 sm:col-span-4">
                                <select x-model="it.bahan_id" @change="pickBahan(idx)" class="w-full px-2.5 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-xs transition-colors">
                                    <option value="">— Bahan dari stok —</option>
                                    <template x-for="b in bahanList" :key="b.id">
                                        <option :value="b.id" x-text="b.nama"></option>
                                    </template>
                                    <option value="__custom">⇥ Tulis manual…</option>
                                </select>
                                <input x-show="it.bahan_id === '__custom'" type="text" x-model="it.nama_bahan" placeholder="Nama bahan" class="w-full mt-1.5 px-2.5 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-xs transition-colors">
                            </div>
                            <div class="col-span-3 sm:col-span-2">
                                <input type="number" step="0.01" min="0" x-model="it.qty" @input="recalc(idx)" placeholder="Qty" class="w-full px-2.5 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-xs transition-colors">
                            </div>
                            <div class="col-span-3 sm:col-span-2">
                                <input type="text" x-model="it.satuan_view" @input="syncSatuan(idx)" placeholder="Satuan" class="w-full px-2.5 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-xs transition-colors">
                            </div>
                            <div class="col-span-3 sm:col-span-2">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[11px] text-vibe-on-surface-variant">Rp</span>
                                    <input type="number" step="1" min="0" x-model="it.harga_satuan" @input="recalc(idx)" placeholder="0" class="w-full pl-6 pr-2 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-xs transition-colors">
                                </div>
                            </div>
                            <div class="col-span-2 sm:col-span-1 flex items-center justify-between">
                                <span class="text-xs font-bold text-vibe-on-surface" x-text="fmt(it.subtotal)"></span>
                                <button type="button" @click="removeItem(idx)" class="p-1 text-vibe-on-surface-variant hover:text-vibe-error transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                    <div x-show="form.items.length === 0" class="text-center py-4 text-xs text-vibe-on-surface-variant bg-vibe-surface-dim rounded-lg">
                        Belum ada item. Tekan “Tambah item”.
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-2">
                    <span class="text-xs text-vibe-on-surface-variant">Total</span>
                    <span class="text-lg font-black text-vibe-primary" x-text="fmt(formTotal())"></span>
                </div>
            </div>

            <!-- Bukti & opsi -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Bukti (foto, opsional)</label>
                    <input type="file" accept="image/*" @change="onBukti($event)" class="w-full text-xs text-vibe-on-surface-variant file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-vibe-surface-container file:text-vibe-on-surface file:font-semibold file:cursor-pointer hover:file:bg-vibe-outline-variant transition-colors">
                    <p class="text-[11px] text-vibe-on-surface-variant mt-1" x-show="form.buktiName" x-text="'Tersimpan: ' + form.buktiName"></p>
                </div>
                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none bg-vibe-surface-dim rounded-lg px-3 py-2.5">
                        <input type="checkbox" x-model="form.stok_updated" class="w-4 h-4 accent-vibe-primary">
                        <span class="text-sm text-vibe-on-surface font-medium">Perbarui stok &amp; harga beli bahan</span>
                    </label>
                    <p class="text-[11px] text-vibe-on-surface-variant mt-1.5">Centang agar stok bertambah & harga beli mengikuti nota ini.</p>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Keterangan</label>
                <textarea x-model="form.keterangan" rows="2" placeholder="Catatan tambahan…" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors resize-none"></textarea>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-vibe-outline-variant flex gap-3 shrink-0">
            <button type="button" @click="showForm=false" class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
            <button type="button" @click="submitForm()" :disabled="saving" class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99] disabled:opacity-60" x-text="saving ? 'Menyimpan…' : (form.id ? 'Simpan Perubahan' : 'Catat Pengeluaran')"></button>
        </div>
    </div>
</div>

<!-- ============ MODAL: DETAIL ============ -->
<div x-show="showDetailModal" @keydown.escape.window="showDetailModal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" x-transition style="display:none">
    <div @click.stop class="bg-white rounded-xl w-full max-w-lg border border-vibe-outline-variant max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-vibe-outline-variant shrink-0">
            <div>
                <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="detail?.supplier || 'Detail Pengeluaran'"></h3>
                <p class="text-xs text-vibe-on-surface-variant"><span x-text="detail?.tanggal"></span> · <span x-text="detail?.kategori === 'pembukaan' ? 'Pembukaan' : (detail?.kategori === 'operasional' ? 'Operasional' : 'Lainnya')"></span></p>
            </div>
            <button @click="showDetailModal=false" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-md transition-colors">
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
                                <div class="text-[11px] text-vibe-on-surface-variant"><span x-text="it.qty"></span> <span x-text="it.satuan"></span> × Rp <span x-text="Number(it.harga_satuan).toLocaleString('id-ID')"></span></div>
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
        <div class="px-6 py-4 border-t border-vibe-outline-variant flex gap-3 shrink-0">
            <button type="button" @click="showDetailModal=false" class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Tutup</button>
            <button type="button" @click="openEdit(detail); showDetailModal=false" class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99]">Ubah</button>
        </div>
    </div>
</div>

<!-- ============ MODAL: ANGGARAN ============ -->
<div x-show="showBudgetModal" @keydown.escape.window="showBudgetModal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" x-transition style="display:none">
    <div @click.stop class="bg-white rounded-xl w-full max-w-sm border border-vibe-outline-variant p-6">
        <h3 class="text-lg font-display font-bold text-vibe-on-surface mb-1">Atur Anggaran Bulanan</h3>
        <p class="text-xs text-vibe-on-surface-variant mb-5" x-text="'Pagu untuk ' + periodeLabel"></p>
        <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Nominal Pagu</label>
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant">Rp</span>
            <input type="number" step="1" min="0" x-model="budgetNominal" class="w-full pl-9 pr-4 py-3 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
        </div>
        <div class="flex gap-3 mt-6">
            <button type="button" @click="showBudgetModal=false" class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
            <button type="button" @click="submitBudget()" :disabled="savingBudget" class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99] disabled:opacity-60" x-text="savingBudget ? 'Menyimpan…' : 'Simpan'"></button>
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
        saving: false,
        savingBudget: false,

        bahanList: <?= json_encode($bahanList, JSON_UNESCAPED_SLASHES) ?>,
        expenses: <?= json_encode($expenses, JSON_UNESCAPED_SLASHES) ?>,
        budgetNominal: <?= (int)$budget ?>,

        showForm: false,
        showDetailModal: false,
        showBudgetModal: false,
        detail: null,

        form: {
            id: null, tanggal: '<?= date('Y-m-d') ?>', supplier: '', kategori: 'operasional',
            metode_bayar: 'cash', keterangan: '', stok_updated: true, buktiName: '',
            items: []
        },
        buktiFile: null,

        init() {
            if (this.form.items.length === 0) this.addItem();
        },

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

        // ---- Form item helpers ----
        addItem() {
            this.form.items.push({ bahan_id: '', nama_bahan: '', qty: '', satuan_view: '', satuan: '', harga_satuan: '', subtotal: 0 });
        },
        removeItem(i) { this.form.items.splice(i, 1); if (this.form.items.length === 0) this.addItem(); },
        pickBahan(i) {
            const it = this.form.items[i];
            if (it.bahan_id === '__custom') { it.nama_bahan = ''; it.satuan = ''; it.satuan_view = ''; it.harga_satuan = ''; this.recalc(i); return; }
            const b = this.bahanList.find(x => String(x.id) === String(it.bahan_id));
            if (b) {
                it.nama_bahan = b.nama;
                it.satuan = b.satuan; it.satuan_view = b.satuan;
                const last = (b.last_price !== null && b.last_price !== '') ? b.last_price : b.harga_beli;
                it.harga_satuan = last || '';
            }
            this.recalc(i);
        },
        syncSatuan(i) {
            const it = this.form.items[i];
            it.satuan = it.satuan_view;
        },
        recalc(i) {
            const it = this.form.items[i];
            const q = parseFloat(it.qty) || 0;
            const h = parseFloat(it.harga_satuan) || 0;
            it.subtotal = Math.round(q * h);
        },
        formTotal() {
            return this.form.items.reduce((s, it) => s + (parseFloat(it.subtotal) || 0), 0);
        },

        // ---- Open ----
        openAdd() {
            this.form = { id: null, tanggal: '<?= date('Y-m-d') ?>', supplier: '', kategori: 'operasional', metode_bayar: 'cash', keterangan: '', stok_updated: true, buktiName: '', items: [] };
            this.buktiFile = null;
            this.addItem();
            this.showForm = true;
        },
        openEdit(e) {
            this.form = {
                id: e.id, tanggal: e.tanggal, supplier: e.supplier || '', kategori: e.kategori,
                metode_bayar: e.metode_bayar, keterangan: e.keterangan || '', stok_updated: !!e.stok_updated,
                buktiName: e.bukti || '', items: (e.items || []).map(it => ({
                    bahan_id: it.bahan_id ? String(it.bahan_id) : '', nama_bahan: it.nama_bahan,
                    qty: it.qty, satuan_view: it.satuan, satuan: it.satuan,
                    harga_satuan: it.harga_satuan, subtotal: it.subtotal
                }))
            };
            if (this.form.items.length === 0) this.addItem();
            this.buktiFile = null;
            this.showForm = true;
        },
        onBukti(e) { this.buktiFile = e.target.files[0] || null; },

        // ---- Submit ----
        async submitForm() {
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', 'save');
                fd.append('id', this.form.id || '');
                fd.append('tanggal', this.form.tanggal);
                fd.append('supplier', this.form.supplier);
                fd.append('kategori', this.form.kategori);
                fd.append('metode_bayar', this.form.metode_bayar);
                fd.append('keterangan', this.form.keterangan);
                fd.append('stok_updated', this.form.stok_updated ? '1' : '0');
                const clean = this.form.items.map(it => ({
                    bahan_id: it.bahan_id && it.bahan_id !== '__custom' ? it.bahan_id : null,
                    nama_bahan: it.nama_bahan, qty: it.qty, satuan: it.satuan,
                    harga_satuan: it.harga_satuan
                }));
                fd.append('items', JSON.stringify(clean));
                if (this.buktiFile) fd.append('bukti', this.buktiFile);

                const res = await fetch('proses_pengeluaran.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal menyimpan.');
                Swal.fire({ icon: 'success', title: 'Tersimpan', text: data.message, timer: 1500, showConfirmButton: false });
                setTimeout(() => window.location.reload(), 700);
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            } finally {
                this.saving = false;
            }
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

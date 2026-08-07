<?php
$page_title = 'Laporan Penjualan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

require_once __DIR__ . '/../../includes/header.php';
// Filter tanggal
$dateFrom = $_GET['from'] ?? date('Y-m-01');   // awal bulan ini
$dateTo   = $_GET['to']   ?? date('Y-m-d');    // hari ini

// Quick Filter active state determination
$quickFilter = 'custom';
if ($dateFrom == date('Y-m-d') && $dateTo == date('Y-m-d')) $quickFilter = 'today';
elseif ($dateFrom == date('Y-m-d', strtotime('-7 days')) && $dateTo == date('Y-m-d')) $quickFilter = 'week';
elseif ($dateFrom == date('Y-m-01') && $dateTo == date('Y-m-d')) $quickFilter = 'month';

// ─── KPI ───────────────────────────────────────
$stmtKPI = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_orders,
        COALESCE(SUM(b.jumlah_bayar), 0) as total_revenue,
        COALESCE(AVG(p.total_harga), 0) as avg_check,
        COUNT(DISTINCT DATE(b.waktu_bayar)) as days_active
    FROM pesanan p
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?
");
$stmtKPI->execute([$dateFrom, $dateTo]);
$kpi = $stmtKPI->fetch();

// ─── Revenue per Day ───────────────────────────
$stmtDaily = $pdo->prepare("
    SELECT DATE(b.waktu_bayar) as day, 
           SUM(b.jumlah_bayar) as revenue,
           COUNT(*) as orders
    FROM pembayaran b
    WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?
    GROUP BY DATE(b.waktu_bayar)
    ORDER BY day ASC
");
$stmtDaily->execute([$dateFrom, $dateTo]);
$dailyData = $stmtDaily->fetchAll();

// ─── Best Seller ───────────────────────────────
$stmtBest = $pdo->prepare("
    SELECT m.nama_menu, k.nama_kategori,
           SUM(d.qty) as total_qty,
           SUM(d.qty * d.harga_satuan) as total_revenue
    FROM detail_pesanan d
    JOIN menu m ON d.menu_id = m.id
    JOIN kategori k ON m.kategori_id = k.id
    JOIN pesanan p ON d.pesanan_id = p.id
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?
    GROUP BY m.id
    ORDER BY total_qty DESC
    LIMIT 10
");
$stmtBest->execute([$dateFrom, $dateTo]);
$bestSellers = $stmtBest->fetchAll();

// ─── Payment Method Breakdown ──────────────────
$stmtMetode = $pdo->prepare("
    SELECT metode_pembayaran, COUNT(*) as cnt, SUM(jumlah_bayar) as total
    FROM pembayaran b
    WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?
    GROUP BY metode_pembayaran
");
$stmtMetode->execute([$dateFrom, $dateTo]);
$metodeData = $stmtMetode->fetchAll();

// ─── All Transactions ──────────────────────────
$stmtTrx = $pdo->prepare("
    SELECT b.*, p.nomor_pesanan, p.tipe_pesanan, p.nama_pelanggan, m.nomor_meja, u.nama_lengkap as kasir
    FROM pembayaran b
    JOIN pesanan p ON b.pesanan_id = p.id
    LEFT JOIN meja m ON p.meja_id = m.id
    JOIN sesi_kasir s ON b.sesi_kasir_id = s.id
    JOIN users u ON s.kasir_id = u.id
    WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?
    ORDER BY b.waktu_bayar DESC
");
$stmtTrx->execute([$dateFrom, $dateTo]);
$transactions = $stmtTrx->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<script src="<?= BASE_URL ?>/assets/vendor/chart.min.js"></script>

<div class="space-y-8">

    <!-- Header & Date Filter Panel -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] p-6 no-print">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div>
                <h1 class="text-2xl font-extrabold text-theme-evergreen tracking-tight">Analitik & Laporan</h1>
                <p class="text-gray-500 mt-1 text-sm font-medium">Pantau performa bisnismu di sini.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <!-- Quick Filter Toggle -->
                <div class="flex bg-gray-50 p-1 rounded-xl border border-gray-100">
                    <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" 
                       class="px-4 py-2 text-xs font-bold rounded-lg transition-colors <?= $quickFilter === 'today' ? 'bg-white text-theme-ocean shadow-sm hover-lift' : 'text-gray-500 hover:text-gray-700 hover-lift' ?>">
                        Hari ini
                    </a>
                    <a href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" 
                       class="px-4 py-2 text-xs font-bold rounded-lg transition-colors <?= $quickFilter === 'week' ? 'bg-white text-theme-ocean shadow-sm hover-lift' : 'text-gray-500 hover:text-gray-700 hover-lift' ?>">
                        7 hari
                    </a>
                    <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" 
                       class="px-4 py-2 text-xs font-bold rounded-lg transition-colors <?= $quickFilter === 'month' ? 'bg-white text-theme-ocean shadow-sm hover-lift' : 'text-gray-500 hover:text-gray-700 hover-lift' ?>">
                        Bulan ini
                    </a>
                </div>

                <!-- Custom Range Form -->
                <form method="GET" class="flex items-center gap-2 relative">
                    <div class="flex items-center bg-gray-50 border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-theme-ocean/30 focus-within:border-theme-ocean transition-all">
                        <input type="date" name="from" value="<?= $dateFrom ?>" class="px-3 py-2 bg-transparent text-xs font-bold text-gray-700 outline-none w-[110px]">
                        <span class="text-gray-300">-</span>
                        <input type="date" name="to" value="<?= $dateTo ?>" class="px-3 py-2 bg-transparent text-xs font-bold text-gray-700 outline-none w-[110px]">
                    </div>
                    <button type="submit" class="p-2 bg-theme-ocean text-white rounded-xl hover:bg-theme-ocean-light transition-colors shadow-sm hover-lift">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button type="button" onclick="window.print()" class="p-2 bg-gray-100 text-gray-500 rounded-xl hover:bg-gray-200 transition-colors shadow-sm ml-1 hover-lift" title="Cetak Laporan">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Print Header -->
    <div class="hidden print:block mb-8 text-center">
        <h1 class="text-2xl font-bold">Laporan Penjualan</h1>
        <p class="text-gray-500">Periode: <?= date('d M Y', strtotime($dateFrom)) ?> - <?= date('d M Y', strtotime($dateTo)) ?></p>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] relative overflow-hidden group hover-lift">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br from-theme-ocean/20 to-transparent rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-theme-ocean/10 text-theme-ocean flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Pemasukan</h3>
            </div>
            <div class="text-3xl font-black text-theme-evergreen"><?= formatRupiah($kpi['total_revenue']) ?></div>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] relative overflow-hidden group hover-lift">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br from-theme-twilight/20 to-transparent rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-theme-twilight/10 text-theme-twilight flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                </div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Transaksi</h3>
            </div>
            <div class="text-3xl font-black text-theme-evergreen"><?= $kpi['total_orders'] ?></div>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] relative overflow-hidden group hover-lift">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br from-theme-sun/20 to-transparent rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-theme-sun/10 text-theme-sun flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Rata-rata Transaksi</h3>
            </div>
            <div class="text-3xl font-black text-theme-evergreen"><?= formatRupiah($kpi['avg_check']) ?></div>
        </div>
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] relative overflow-hidden group hover-lift">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-gradient-to-br from-theme-coral/20 to-transparent rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-theme-coral/10 text-theme-coral flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Hari Aktif</h3>
            </div>
            <div class="text-3xl font-black text-theme-evergreen"><?= $kpi['days_active'] ?></div>
        </div>
    </div>

    <!-- Charts & Graphics -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 print:block">
        
        <!-- Smooth Line Revenue Chart -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-7 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] print:mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-bold text-theme-evergreen text-lg">Grafik Pemasukan</h2>
                <div class="px-3 py-1 rounded-lg bg-theme-bg text-theme-leaf text-xs font-bold border border-theme-sage/20">Harian</div>
            </div>
            <div class="relative h-[300px] w-full">
                <canvas id="revenueLineChart"></canvas>
            </div>
        </div>

        <!-- Payment Methods Doughnut -->
        <div class="bg-white rounded-3xl p-7 border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] print:mb-8">
            <h2 class="font-bold text-theme-evergreen text-lg mb-6">Metode Pembayaran</h2>
            <?php if(count($metodeData) > 0): ?>
            <div class="relative h-[180px] mb-8">
                <canvas id="metodePieChart"></canvas>
            </div>
            <div class="space-y-4">
                <?php 
                $metodeColors = ['cash'=>['bg-theme-ocean','text-theme-ocean'],'qris'=>['bg-theme-twilight','text-theme-twilight'],'transfer'=>['bg-theme-sun','text-theme-sun'],'debit'=>['bg-theme-sun','text-theme-sun'],'hutang'=>['bg-red-100','text-red-600']];
                $metodeLabelMap = ['cash'=>'Tunai','qris'=>'QRIS','transfer'=>'Transfer','debit'=>'Transfer','hutang'=>'Hutang'];
                foreach($metodeData as $m): 
                $colors = $metodeColors[$m['metode_pembayaran']] ?? ['bg-gray-500','text-gray-600'];
                $labelMetode = $metodeLabelMap[$m['metode_pembayaran']] ?? strtoupper($m['metode_pembayaran']);
                ?>
                <div class="flex justify-between items-center group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center group-hover:bg-gray-100 transition-colors">
                            <div class="w-3.5 h-3.5 rounded-full <?= $colors[0] ?>"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 uppercase tracking-wide"><?= $labelMetode ?></span>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-sm text-theme-evergreen"><?= formatRupiah($m['total']) ?></div>
                        <div class="text-xs text-gray-400 font-medium"><?= $m['cnt'] ?> transaksi</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="h-40 flex items-center justify-center text-gray-400 text-sm">Tidak ada data pembayaran.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Best Sellers -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden print:mb-8">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h2 class="font-bold text-theme-evergreen text-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                Produk Terlaris
            </h2>
        </div>
        <?php if(count($bestSellers) > 0): ?>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <?php 
                $maxQty = $bestSellers[0]['total_qty'] ?? 1;
                foreach($bestSellers as $i => $item): 
                $pct = $maxQty > 0 ? ($item['total_qty'] / $maxQty) * 100 : 0;
                $isTop3 = $i < 3;
                ?>
                <div class="flex items-center gap-4 group">
                    <div class="w-8 h-8 rounded-xl <?= $isTop3 ? 'bg-theme-sun/10 text-theme-sun border border-theme-sun/20 shadow-sm' : 'bg-gray-50 text-gray-400 border border-gray-100' ?> flex items-center justify-center font-black text-sm flex-shrink-0 transition-colors">
                        #<?= $i+1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-end mb-1.5">
                            <span class="font-bold text-theme-evergreen text-sm truncate pr-2 group-hover:text-theme-ocean transition-colors"><?= htmlspecialchars($item['nama_menu']) ?></span>
                            <span class="text-xs font-black text-gray-500 whitespace-nowrap"><?= $item['total_qty'] ?> <span class="text-gray-400 font-medium">terjual</span></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r <?= $isTop3 ? 'from-theme-sun to-theme-coral' : 'from-theme-ocean-light to-theme-ocean' ?> rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 w-24">
                        <div class="font-black text-sm text-theme-evergreen"><?= formatRupiah($item['total_revenue']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="p-12 text-center text-gray-400 text-sm">Tidak ada data penjualan.</div>
        <?php endif; ?>
    </div>

    <!-- Paginated Transactions Table (Alpine.js) -->
    <div x-data="transactionTable()" class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden no-print">
        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <div>
                <h2 class="font-bold text-theme-evergreen text-lg">Riwayat Transaksi</h2>
                <p class="text-xs text-gray-500 mt-1"><span x-text="filteredTransactions.length"></span> data ditemukan</p>
            </div>
            
            <!-- Live Search -->
            <div class="relative w-full md:w-72">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Cari ID pesanan atau nama kasir..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage text-sm font-medium transition-shadow placeholder-gray-400 shadow-sm">
            </div>
        </div>
        
        <div class="overflow-x-auto min-h-[400px]">
            <table class="w-full">
                <thead>
                    <tr class="bg-white border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-widest">
                        <th class="px-6 py-4 text-left">ID Pesanan</th>
                        <th class="px-6 py-4 text-left">Tanggal & Waktu</th>
                        <th class="px-6 py-4 text-left">Kasir</th>
                        <th class="px-6 py-4 text-left">Metode</th>
                        <th class="px-6 py-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50/80">
                    <tr x-show="paginatedData.length === 0" style="display:none">
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <h3 class="text-base font-bold text-gray-600">Belum ada transaksi</h3>
                        </td>
                    </tr>
                    
                    <template x-for="t in paginatedData" :key="t.id">
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-extrabold text-theme-evergreen text-sm" x-text="t.nomor_pesanan"></div>
                                <div class="text-[11px] font-bold mt-1 tracking-wide" 
                                     :class="t.tipe_pesanan === 'dine_in' ? 'text-theme-sage' : 'text-orange-500'" 
                                     x-text="t.tipe_pesanan === 'dine_in' ? 'Meja ' + t.nomor_meja : 'Bungkus / Take Away'"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-700" x-text="formatDate(t.waktu_bayar)"></div>
                                <div class="text-[11px] text-gray-400 font-medium" x-text="formatTime(t.waktu_bayar)"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-theme-bg text-theme-leaf flex items-center justify-center font-bold text-[10px]" x-text="t.kasir.charAt(0).toUpperCase()"></div>
                                    <span class="text-sm font-medium text-gray-600" x-text="t.kasir"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider"
                                      :class="getMethodColor(t.metode_pembayaran)" x-text="t.metode_pembayaran"></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-black text-theme-evergreen text-base" x-text="formatRupiah(t.jumlah_bayar)"></div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls -->
        <div class="p-4 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between" x-show="totalPages > 1">
            <div class="text-xs font-medium text-gray-500">
                Menampilkan <span class="font-bold text-gray-700" x-text="startIndex + 1"></span> sampai <span class="font-bold text-gray-700" x-text="Math.min(endIndex, filteredTransactions.length)"></span> dari <span class="font-bold text-gray-700" x-text="filteredTransactions.length"></span> data
            </div>
            <div class="flex items-center gap-1">
                <button @click="prevPage()" :disabled="currentPage === 1" class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-white hover:border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors hover-lift">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <div class="px-4 text-sm font-bold text-theme-evergreen">
                    <span x-text="currentPage"></span> / <span x-text="totalPages"></span>
                </div>
                <button @click="nextPage()" :disabled="currentPage === totalPages" class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-white hover:border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors hover-lift">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Format Rupiah
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
}

// Chart.js Implementations
<?php
$labels  = array_map(fn($d) => date('d M', strtotime($d['day'])), $dailyData);
$revenues = array_map(fn($d) => (float)$d['revenue'], $dailyData);
$metodeLabels = array_map(fn($m) => strtoupper($m['metode_pembayaran']), $metodeData);
$metodeTotals = array_map(fn($m) => (float)$m['total'], $metodeData);
?>

document.addEventListener("DOMContentLoaded", () => {
    // 1. Smooth Line Revenue Chart
    const rCtx = document.getElementById('revenueLineChart');
    if (rCtx) {
        let grad = rCtx.getContext('2d').createLinearGradient(0,0,0,300);
        grad.addColorStop(0, 'rgba(37, 99, 235, 0.4)');
        grad.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
        
        new Chart(rCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($revenues) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: grad,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4, // Smooth curve
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { 
                    legend: { display: false }, 
                    tooltip: {
                        backgroundColor: '#213145',
                        padding: 12,
                        titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                        bodyFont: { family: 'Plus Jakarta Sans', weight: 'bold', size: 14 },
                        displayColors: false,
                        callbacks: { label: ctx => 'Rp ' + parseInt(ctx.parsed.y).toLocaleString('id-ID') }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 }, color: '#9ca3af' } },
                    y: { 
                        beginAtZero: true,
                        grid: { color: '#f3f4f6', borderDash: [5,5] }, 
                        ticks: { font: { family: 'Plus Jakarta Sans', weight: '500' }, color: '#9ca3af', callback: v => 'Rp ' + parseInt(v/1000).toLocaleString('id-ID') + 'K', maxTicksLimit: 6 } 
                    }
                }
            }
        });
    }

    // 2. Payment Method Doughnut
    const mCtx = document.getElementById('metodePieChart');
    if (mCtx && <?= count($metodeData) ?> > 0) {
        new Chart(mCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($metodeLabels) ?>,
                datasets: [{ 
                    data: <?= json_encode($metodeTotals) ?>, 
                    backgroundColor: ['#2563eb','#006c49','#632ecd'], 
                    borderWidth: 0, 
                    hoverOffset: 6 
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '75%',
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        backgroundColor: '#213145', padding: 10,
                        callbacks: { label: ctx => ' Rp ' + parseInt(ctx.raw).toLocaleString('id-ID') } 
                    } 
                }
            }
        });
    }
});

// Alpine.js for Table Pagination & Search
document.addEventListener('alpine:init', () => {
    Alpine.data('transactionTable', () => ({
        transactions: <?= json_encode($transactions) ?>,
        searchQuery: '',
        currentPage: 1,
        itemsPerPage: 10,

        get filteredTransactions() {
            if (this.searchQuery === '') return this.transactions;
            const sq = this.searchQuery.toLowerCase();
            return this.transactions.filter(t => 
                t.nomor_pesanan.toLowerCase().includes(sq) || 
                t.kasir.toLowerCase().includes(sq)
            );
        },

        get totalPages() {
            return Math.ceil(this.filteredTransactions.length / this.itemsPerPage) || 1;
        },

        get startIndex() {
            return (this.currentPage - 1) * this.itemsPerPage;
        },

        get endIndex() {
            return this.startIndex + this.itemsPerPage;
        },

        get paginatedData() {
            return this.filteredTransactions.slice(this.startIndex, this.endIndex);
        },

        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },

        formatDate(datetime) {
            const date = new Date(datetime);
            return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute:'2-digit' });
        },

        getMethodColor(method) {
            const colors = {
                'cash': 'bg-theme-ocean/10 text-theme-ocean border border-theme-ocean/20',
                'qris': 'bg-theme-twilight/10 text-theme-twilight border border-theme-twilight/20',
                'transfer': 'bg-theme-sun/10 text-theme-sun border border-theme-sun/20',
                'debit': 'bg-theme-sun/10 text-theme-sun border border-theme-sun/20',
                'hutang': 'bg-red-100 text-red-600 border border-red-200'
            };
            return colors[method] || 'bg-gray-100 text-gray-500';
        }
    }));
});
</script>

<style>
@media print {
    aside, header, form, .no-print { display: none !important; }
    main { padding: 0 !important; overflow: visible !important; }
    body { overflow: visible !important; height: auto !important; background-color: white !important; }
    .shadow-sm, .shadow-\[0_4px_20px_rgba\(0\,0\,0\,0\.03\)\] { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    .bg-white { background-color: white !important; }
    .print\:block { display: block !important; }
    .print\:mb-8 { margin-bottom: 2rem !important; }
    .lg\:col-span-2 { width: 100% !important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

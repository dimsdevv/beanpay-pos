<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

// --- DATA FETCHING ---

// 1. Sales & Orders Data
$stmtToday = $pdo->query("
    SELECT 
        COUNT(DISTINCT p.id) as total_orders,
        COALESCE(SUM(p.total_harga), 0) as total_sales
    FROM pesanan p
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) = CURDATE()
");
$todayData = $stmtToday->fetch();
$totalOrdersToday = (int)$todayData['total_orders'];
$totalSalesToday = (float)$todayData['total_sales'];
$averageCheck = $totalOrdersToday > 0 ? $totalSalesToday / $totalOrdersToday : 0;

$stmtYest = $pdo->query("
    SELECT 
        COUNT(DISTINCT p.id) as total_orders,
        COALESCE(SUM(p.total_harga), 0) as total_sales
    FROM pesanan p
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
");
$yestData = $stmtYest->fetch();
$totalOrdersYest = (int)$yestData['total_orders'];
$totalSalesYest = (float)$yestData['total_sales'];
$avgCheckYest = $totalOrdersYest > 0 ? $totalSalesYest / $totalOrdersYest : 0;

function calcPct($today, $yesterday) {
    if ($yesterday == 0) return $today > 0 ? 100 : 0;
    return round((($today - $yesterday) / $yesterday) * 100, 1);
}

$salesPct = calcPct($totalSalesToday, $totalSalesYest);
$ordersPct = calcPct($totalOrdersToday, $totalOrdersYest);
$avgPct = calcPct($averageCheck, $avgCheckYest);

// 2. Chart Data (Last 7 Days)
$chartData = array_fill(0, 7, 0);
$chartLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $chartLabels[] = date('D', strtotime("-$i days"));
}

$stmtChart = $pdo->query("
    SELECT DATE(b.waktu_bayar) as tgl, COALESCE(SUM(p.total_harga), 0) as rev
    FROM pesanan p
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(b.waktu_bayar)
");
while ($row = $stmtChart->fetch()) {
    $diffDays = (strtotime(date('Y-m-d')) - strtotime($row['tgl'])) / 86400;
    $idx = 6 - (int)$diffDays;
    if ($idx >= 0 && $idx <= 6) {
        $chartData[$idx] = (float)$row['rev'];
    }
}

// 3. Top Selling Items
$stmtTopSelling = $pdo->query("
    SELECT m.nama_menu, m.harga, m.gambar, k.nama_kategori,
           COALESCE(SUM(d.qty), 0) as total_sold
    FROM detail_pesanan d
    JOIN menu m ON d.menu_id = m.id
    JOIN kategori k ON m.kategori_id = k.id
    JOIN pesanan p ON d.pesanan_id = p.id
    JOIN pembayaran b ON p.id = b.pesanan_id
    WHERE DATE(b.waktu_bayar) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY m.id
    ORDER BY total_sold DESC
    LIMIT 3
");
$topSelling = $stmtTopSelling->fetchAll();

// 4. Low Stock Alert
$stmtLowStock = $pdo->query("
    SELECT nama_bahan, stok_sekarang, satuan 
    FROM bahan_baku 
    WHERE stok_sekarang <= 500
    ORDER BY stok_sekarang ASC
    LIMIT 4
");
$lowStocks = $stmtLowStock->fetchAll();

// Date formatting
$todayDate = date('l, F jS, Y');

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between mb-8 animate-fade-in">
    <div>
        <h1 class="text-2xl md:text-[28px] font-extrabold text-vibe-on-surface tracking-tight leading-tight">Today's Overview</h1>
        <p class="text-sm text-vibe-on-surface-variant mt-1"><?= $todayDate ?></p>
    </div>
    <div class="mt-3 sm:mt-0 flex items-center gap-3">
        <!-- LIVE Indicator -->
        <div id="live-indicator" class="flex items-center gap-1.5 px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-xs font-bold text-green-700 uppercase tracking-wider">Live</span>
        </div>
        <div class="flex items-center gap-2 text-sm font-semibold text-vibe-primary cursor-pointer hover:text-vibe-primary-container transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Last 7 Days
        </div>
    </div>
</div>

<!-- KPI Cards Row (3 cards like reference) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    
    <!-- Card 1: Total Revenue -->
    <div class="bg-white rounded-xl p-6 shadow-card hover:shadow-card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.05s">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary flex items-center justify-center text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $salesPct >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-vibe-error' ?>">
                <?php if($salesPct >= 0): ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <?php else: ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                <?php endif; ?>
                <?= $salesPct >= 0 ? '+' : '' ?><?= $salesPct ?>%
            </div>
        </div>
        <p class="text-xs font-semibold text-vibe-on-surface-variant uppercase tracking-wider mb-1">Total Revenue</p>
        <p id="kpi-revenue" class="text-2xl font-extrabold text-vibe-on-surface transition-all duration-500"><?= formatRupiah($totalSalesToday) ?></p>
    </div>

    <!-- Card 2: Transactions -->
    <div class="bg-white rounded-xl p-6 shadow-card hover:shadow-card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.1s">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-tertiary flex items-center justify-center text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $ordersPct >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-vibe-error' ?>">
                <?php if($ordersPct >= 0): ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <?php else: ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                <?php endif; ?>
                <?= $ordersPct >= 0 ? '+' : '' ?><?= $ordersPct ?>%
            </div>
        </div>
        <p class="text-xs font-semibold text-vibe-on-surface-variant uppercase tracking-wider mb-1">Transactions</p>
        <p id="kpi-orders" class="text-2xl font-extrabold text-vibe-on-surface transition-all duration-500"><?= $totalOrdersToday ?></p>
    </div>

    <!-- Card 3: Average Ticket -->
    <div class="bg-white rounded-xl p-6 shadow-card hover:shadow-card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.15s">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary flex items-center justify-center text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
            </div>
            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold <?= $avgPct >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-vibe-error' ?>">
                <?php if($avgPct >= 0): ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <?php else: ?>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                <?php endif; ?>
                <?= $avgPct >= 0 ? '+' : '' ?><?= $avgPct ?>%
            </div>
        </div>
        <p class="text-xs font-semibold text-vibe-on-surface-variant uppercase tracking-wider mb-1">Average Ticket</p>
        <p id="kpi-avg" class="text-2xl font-extrabold text-vibe-on-surface transition-all duration-500"><?= formatRupiah($averageCheck) ?></p>
    </div>
</div>

<!-- Main Content Grid: Chart + Right Widgets -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-8">
    
    <!-- Chart Section (dark container like reference) -->
    <div class="lg:col-span-2 bg-vibe-inverse-surface rounded-xl p-6 shadow-card animate-fade-in" style="animation-delay: 0.2s">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-base font-bold text-white">Weekly Sales Performance</h3>
                <p class="text-xs text-vibe-inverse-on-surface/60 mt-0.5">Performa penjualan 7 hari terakhir</p>
            </div>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center text-vibe-inverse-on-surface/40 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg>
            </button>
        </div>
        <div class="relative h-72 w-full">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Right Column: Top Selling + Low Stock -->
    <div class="flex flex-col gap-6">
        
        <!-- Top Selling Widget -->
        <div class="bg-white rounded-xl p-6 shadow-card animate-fade-in flex-1" style="animation-delay: 0.25s">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-base font-bold text-vibe-on-surface">Top Selling</h3>
                <a href="<?= BASE_URL ?>/modules/admin/menu.php" class="text-xs font-semibold text-vibe-primary hover:text-vibe-primary-container transition-colors">View All</a>
            </div>
            
            <div class="space-y-4">
                <?php if(empty($topSelling)): ?>
                    <div class="text-center py-6 text-vibe-on-surface-variant text-sm">
                        Belum ada data penjualan.
                    </div>
                <?php else: ?>
                    <?php foreach($topSelling as $item): 
                        // Category color chips
                        $catColors = [
                            'Beverage' => 'bg-blue-50 text-blue-600',
                            'Minuman' => 'bg-blue-50 text-blue-600',
                            'Pastry' => 'bg-purple-50 text-purple-600',
                            'Makanan' => 'bg-orange-50 text-orange-600',
                            'Food' => 'bg-orange-50 text-orange-600',
                            'Snack' => 'bg-yellow-50 text-yellow-600',
                        ];
                        $chipClass = $catColors[$item['nama_kategori']] ?? 'bg-gray-100 text-gray-600';
                    ?>
                    <div class="flex items-center gap-3">
                        <!-- Product Image -->
                        <div class="w-11 h-11 rounded-xl bg-vibe-surface-container overflow-hidden shrink-0">
                            <?php if($item['gambar'] && file_exists(__DIR__ . '/../../assets/images/' . $item['gambar'])): ?>
                            <img src="<?= BASE_URL ?>/assets/images/<?= $item['gambar'] ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-2xl bg-vibe-surface-container">🍽️</div>
                            <?php endif; ?>
                        </div>
                        <!-- Product Info -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-vibe-on-surface truncate"><?= htmlspecialchars($item['nama_menu']) ?></p>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold mt-0.5 <?= $chipClass ?>"><?= htmlspecialchars($item['nama_kategori']) ?></span>
                        </div>
                        <!-- Price & Sold -->
                        <div class="text-right shrink-0">
                            <p class="text-sm font-bold text-vibe-on-surface"><?= formatRupiah($item['harga']) ?></p>
                            <p class="text-[11px] text-vibe-on-surface-variant"><?= $item['total_sold'] ?> sold</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Alerts Widget -->
        <div class="bg-white rounded-xl p-6 shadow-card animate-fade-in" style="animation-delay: 0.3s">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-vibe-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                <h3 class="text-base font-bold text-vibe-on-surface">Low Stock Alerts</h3>
            </div>
            
            <div class="space-y-3">
                <?php if(empty($lowStocks)): ?>
                    <div class="flex items-center gap-3 py-3">
                        <div class="w-8 h-8 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-vibe-on-surface">Semua stok aman!</p>
                            <p class="text-xs text-vibe-on-surface-variant">Level inventaris optimal.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($lowStocks as $stock): ?>
                    <div class="flex items-center justify-between py-2 border-b border-vibe-outline-variant/15 last:border-0">
                        <p class="text-sm text-vibe-on-surface font-medium"><?= htmlspecialchars($stock['nama_bahan']) ?></p>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-vibe-error"><?= floatval($stock['stok_sekarang']) ?> left</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Live Activity Feed -->
<div id="live-activity-section" class="bg-white rounded-xl shadow-card p-6 mb-8 animate-fade-in" style="animation-delay: 0.3s">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <h3 class="text-base font-bold text-vibe-on-surface">Live Activity</h3>
        </div>
        <span id="activity-timer" class="text-xs font-medium text-vibe-on-surface-variant">Updated just now</span>
    </div>
    <div id="activity-feed" class="space-y-3">
        <?php if(count($activity ?? []) === 0 && $totalOrdersToday === 0): ?>
        <div class="text-center py-6 text-vibe-on-surface-variant">
            <div class="text-3xl mb-2">☕</div>
            <p class="text-sm font-medium">Belum ada transaksi hari ini</p>
            <p class="text-xs">Transaksi baru akan muncul otomatis di sini.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Formatting currency for Chart Tooltip
    const formatRp = (number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    };

    // Initialize Chart.js with dark theme
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Neon blue gradient for the fill area
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#60a5fa',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#60a5fa',
                pointBorderColor: '#60a5fa',
                pointBorderWidth: 0,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#60a5fa',
                pointHoverBorderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#0b1c30',
                    bodyColor: '#0b1c30',
                    borderColor: '#e5eeff',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '500' },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 14, weight: '700' },
                    displayColors: false,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return formatRp(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255,255,255,0.06)',
                        drawBorder: false,
                    },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' },
                        color: 'rgba(234,241,255,0.5)'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255,255,255,0.06)',
                        drawBorder: false,
                    },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11 },
                        color: 'rgba(234,241,255,0.5)',
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value / 1000).toFixed(0) + 'k';
                            return value;
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });

    // ═══════════════════════════════════════════════════════════
    // LIVE DASHBOARD POLLING SYSTEM
    // ═══════════════════════════════════════════════════════════
    
    const POLL_INTERVAL = 15000; // 15 seconds
    let lastPaymentId = <?= $pdo->query("SELECT COALESCE(MAX(id),0) FROM pembayaran")->fetchColumn() ?>;
    let isFirstLoad = true;

    // Format Rupiah
    function formatRupiahLive(num) {
        return 'Rp ' + parseInt(num || 0).toLocaleString('id-ID');
    }

    // Smooth count-up animation
    function animateValue(el, newText) {
        if (el.textContent === newText) return;
        el.style.transform = 'scale(1.08)';
        el.style.color = '#004ac6';
        setTimeout(() => {
            el.textContent = newText;
        }, 150);
        setTimeout(() => {
            el.style.transform = 'scale(1)';
            el.style.color = '';
        }, 400);
    }

    // Render activity feed
    function renderActivity(items) {
        const feed = document.getElementById('activity-feed');
        if (!feed || items.length === 0) return;
        
        const methodIcons = {
            'CASH': { bg: 'bg-green-50', text: 'text-green-600', icon: '💵' },
            'QRIS': { bg: 'bg-purple-50', text: 'text-purple-600', icon: '📱' },
            'DEBIT': { bg: 'bg-blue-50', text: 'text-blue-600', icon: '💳' }
        };

        feed.innerHTML = items.map(a => {
            const m = methodIcons[a.metode] || methodIcons['CASH'];
            return `
            <div class="flex items-center justify-between py-3 px-4 bg-vibe-bg rounded-lg hover:bg-vibe-surface-container transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg ${m.bg} flex items-center justify-center text-lg">${m.icon}</div>
                    <div>
                        <p class="text-sm font-bold text-vibe-on-surface">${a.nomor_pesanan}</p>
                        <p class="text-xs text-vibe-on-surface-variant">${a.kasir} • ${a.metode}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-extrabold text-vibe-on-surface">${formatRupiahLive(a.total)}</p>
                    <p class="text-xs text-vibe-on-surface-variant">${a.waktu}</p>
                </div>
            </div>`;
        }).join('');
    }

    // Show toast notification
    function showPaymentToast(notif) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            showClass: { popup: 'animate__animated animate__slideInRight' },
            hideClass: { popup: 'animate__animated animate__slideOutRight' },
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        Toast.fire({
            icon: 'success',
            title: `<span style="font-weight:700">💰 Pembayaran Baru!</span>`,
            html: `<span style="font-size:13px">${notif.nomor_pesanan} • <b>${formatRupiahLive(notif.total)}</b> (${notif.metode})</span>`
        });
    }

    // Update notification badge in topbar
    function updateBellBadge(count) {
        const badge = document.getElementById('notif-badge');
        const badgeCount = document.getElementById('notif-count');
        if (badge && badgeCount) {
            if (count > 0) {
                badge.classList.remove('hidden');
                badgeCount.textContent = count > 9 ? '9+' : count;
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    // Main polling function
    async function pollDashboard() {
        try {
            const res = await fetch(`<?= BASE_URL ?>/api/realtime.php?last_id=${lastPaymentId}`);
            if (!res.ok) return;
            const data = await res.json();

            // Update KPI cards with animation
            const elRevenue = document.getElementById('kpi-revenue');
            const elOrders = document.getElementById('kpi-orders');
            const elAvg = document.getElementById('kpi-avg');

            if (elRevenue) animateValue(elRevenue, formatRupiahLive(data.kpi.revenue));
            if (elOrders) animateValue(elOrders, String(data.kpi.orders));
            if (elAvg) animateValue(elAvg, formatRupiahLive(data.kpi.avg_ticket));

            // Render activity feed
            if (data.activity && data.activity.length > 0) {
                renderActivity(data.activity);
            }

            // Show toast for NEW notifications (skip first load)
            if (!isFirstLoad && data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(n => showPaymentToast(n));
                updateBellBadge(data.notifications.length);
            }

            // Update timer text
            const timer = document.getElementById('activity-timer');
            if (timer) timer.textContent = 'Updated at ' + data.server_time;

            // Update last_id
            lastPaymentId = data.last_id;
            isFirstLoad = false;

        } catch (err) {
            console.warn('[BeanPay Live] Polling error:', err);
            const indicator = document.getElementById('live-indicator');
            if (indicator) {
                indicator.querySelector('span:first-child').className = 'w-2 h-2 bg-yellow-400 rounded-full';
                indicator.querySelector('span:last-child').textContent = 'Reconnecting...';
            }
        }
    }

    // Start polling
    pollDashboard(); // Initial fetch
    setInterval(pollDashboard, POLL_INTERVAL);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

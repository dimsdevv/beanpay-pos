<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'dapur']);

// --- AJAX: FETCH ORDERS & STATS ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    session_write_close();
    header('Content-Type: application/json');

    // 1. Fetch Orders
    $stmt = $pdo->query("
        SELECT
            p.id as pesanan_id,
            p.nomor_pesanan,
            p.tipe_pesanan,
            p.waktu_pesan,
            m.nomor_meja,
            dp.id as detail_id,
            dp.qty,
            dp.catatan,
            dp.status_item,
            menu.nama_menu,
            kat.nama_kategori,
            kat.stasiun
        FROM pesanan p
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        JOIN menu ON dp.menu_id = menu.id
        LEFT JOIN kategori kat ON menu.kategori_id = kat.id
        LEFT JOIN meja m ON p.meja_id = m.id
        WHERE dp.status_item IN ('pending', 'cooking') AND p.status_pesanan IN ('pending', 'diproses')
        ORDER BY p.waktu_pesan ASC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orders = [];
    foreach ($items as $i) {
        $pid = $i['pesanan_id'];
        if (!isset($orders[$pid])) {
            $orders[$pid] = [
                'id'            => $pid,
                'nomor_pesanan' => $i['nomor_pesanan'],
                'tipe_pesanan'  => $i['tipe_pesanan'],
                'nomor_meja'    => $i['nomor_meja'],
                'waktu_pesan'   => $i['waktu_pesan'],
                'items'         => [],
                'stations'      => [] // Track stations involved in this order
            ];
        }
        $stasiun = $i['stasiun'] ?? 'any';
        if (!in_array($stasiun, $orders[$pid]['stations'])) {
            $orders[$pid]['stations'][] = $stasiun;
        }

        $orders[$pid]['items'][] = [
            'id'       => $i['detail_id'],
            'nama_menu'=> $i['nama_menu'],
            'kategori' => $i['nama_kategori'],
            'stasiun'  => $stasiun,
            'qty'      => $i['qty'],
            'catatan'  => $i['catatan'],
            'status'   => $i['status_item']
        ];
    }

    // 2. Fetch Stats
    $stats = $pdo->query("
        SELECT 
            COUNT(DISTINCT CASE WHEN p.status_pesanan = 'selesai' AND DATE(p.waktu_pesan) = CURDATE() THEN p.id END) AS selesai_hari_ini,
            COUNT(DISTINCT CASE WHEN p.status_pesanan IN ('pending','diproses') THEN p.id END) AS dalam_antrian,
            ROUND(AVG(CASE WHEN dp.waktu_selesai_masak IS NOT NULL 
                THEN TIMESTAMPDIFF(SECOND, dp.waktu_mulai_masak, dp.waktu_selesai_masak) END) / 60, 1) AS avg_menit,
            COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(MINUTE, p.waktu_pesan, NOW()) >= 12 
                AND p.status_pesanan IN ('pending','diproses') THEN p.id END) AS overdue_count
        FROM pesanan p
        LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        WHERE DATE(p.waktu_pesan) = CURDATE() OR p.status_pesanan IN ('pending','diproses')
    ")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'orders' => array_values($orders),
        'stats' => $stats
    ]);
    exit;
}

// --- AJAX: UPDATE STATUS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_write_close();
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'toggle_item') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT status_item FROM detail_pesanan WHERE id = ?");
        $stmt->execute([$id]);
        $cur = $stmt->fetchColumn();
        if ($cur === 'pending') {
            $pdo->prepare("UPDATE detail_pesanan SET status_item = 'cooking', waktu_mulai_masak = NOW() WHERE id = ?")->execute([$id]);
            $pdo->query("UPDATE pesanan p JOIN detail_pesanan dp ON p.id = dp.pesanan_id SET p.status_pesanan = 'diproses' WHERE dp.id = $id AND p.status_pesanan = 'pending'");
        } elseif ($cur === 'cooking') {
            $pdo->prepare("UPDATE detail_pesanan SET status_item = 'ready', waktu_selesai_masak = NOW() WHERE id = ?")->execute([$id]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'complete_order') {
        $pesanan_id = (int)$_POST['pesanan_id'];
        $pdo->prepare("UPDATE detail_pesanan SET status_item = 'ready', waktu_selesai_masak = COALESCE(waktu_selesai_masak, NOW()) WHERE pesanan_id = ? AND status_item IN ('pending','cooking')")->execute([$pesanan_id]);
        
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id = ? AND status_item != 'ready'");
        $checkStmt->execute([$pesanan_id]);
        if ($checkStmt->fetchColumn() == 0) {
            $pdo->prepare("UPDATE pesanan SET status_pesanan = 'selesai' WHERE id = ?")->execute([$pesanan_id]);
            
            // Insert notification for waiter
            $stmtWaiters = $pdo->prepare("SELECT waiter_id, nomor_pesanan FROM pesanan WHERE id = ?");
            $stmtWaiters->execute([$pesanan_id]);
            if($w = $stmtWaiters->fetch()) {
                 $pdo->prepare("INSERT INTO notif_waiter (pesanan_id, waiter_id, pesan) VALUES (?, ?, ?)")
                     ->execute([$pesanan_id, $w['waiter_id'], "Pesanan " . $w['nomor_pesanan'] . " siap dihidangkan!"]);
            }
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'recall_order') {
        $pesanan_id = (int)$_POST['pesanan_id'];
        // Recall only within last 5 mins
        $pdo->prepare("
            UPDATE pesanan SET status_pesanan = 'diproses'
            WHERE id = ? AND status_pesanan = 'selesai'
        ")->execute([$pesanan_id]);
        $pdo->prepare("
            UPDATE detail_pesanan SET status_item = 'cooking', waktu_selesai_masak = NULL
            WHERE pesanan_id = ? AND status_item = 'ready'
        ")->execute([$pesanan_id]);
        
        // Remove related notif_waiter if unread
        $pdo->prepare("DELETE FROM notif_waiter WHERE pesanan_id = ? AND is_read = 0")->execute([$pesanan_id]);

        echo json_encode(['success' => true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display — BeanPay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ["Plus Jakarta Sans", "sans-serif"] },
                    colors: {
                        vibe: {
                            primary:           "#004ac6",
                            "primary-light":   "#e8effe",
                            secondary:         "#006c49",
                            "secondary-light": "#dcfce7",
                            bg:                "#f0f4ff",
                            surface:           "#ffffff",
                            "on-surface":      "#0b1c30",
                            "on-surface-v":    "#434655",
                            outline:           "#737686",
                            "outline-v":       "#c3c6d7",
                            error:             "#ba1a1a",
                            "error-light":     "#ffdad6",
                            warning:           "#d97706",
                            "warning-light":   "#fef3c7",
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: "Plus Jakarta Sans", sans-serif; }

        .kds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
            align-items: start;
        }

        .order-card { transition: box-shadow 0.3s ease, transform 0.3s ease; }
        .order-card:hover { transform: translateY(-2px); }

        .item-check {
            appearance: none;
            width: 20px; height: 20px;
            border: 2px solid #c3c6d7;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            position: relative;
        }
        .item-check:checked {
            background: #006c49;
            border-color: #006c49;
        }
        .item-check:checked::after {
            content: '';
            position: absolute;
            top: 2px; left: 5px;
            width: 6px; height: 10px;
            border: 2px solid white;
            border-top: none;
            border-left: none;
            transform: rotate(45deg);
        }
        .item-check:hover:not(:checked) { border-color: #004ac6; }

        @keyframes timerFlash {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.5; }
        }
        .timer-overdue { animation: timerFlash 1s ease-in-out infinite; }
        
        @keyframes pulseWarning {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4); }
            70% { transform: scale(1.02); box-shadow: 0 0 0 10px rgba(217, 119, 6, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
        }
        .card-pulse { animation: pulseWarning 2s infinite; border-color: #d97706; }

        @keyframes shakeCritical {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
            20%, 40%, 60%, 80% { transform: translateX(4px); }
        }
        .card-shake { animation: shakeCritical 0.8s ease-in-out infinite; border-color: #ba1a1a; box-shadow: 0 4px 12px rgba(186,26,26,0.2); }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .card-enter { animation: slideIn 0.35s ease-out forwards; }
        
        @keyframes toastSlideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .toast-enter { animation: toastSlideUp 0.3s ease-out forwards; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c3c6d7; border-radius: 8px; }
    </style>
</head>
<body class="bg-vibe-bg text-vibe-on-surface h-screen flex flex-col overflow-hidden" x-data="kdsApp()">

    <!-- ══════════════════ TOPBAR ══════════════════ -->
    <header class="bg-white border-b border-vibe-outline-v/30 px-6 py-2.5 flex flex-col md:flex-row items-center justify-between shrink-0 shadow-sm z-10 gap-4 md:gap-0">
        <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
            <!-- Brand -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-vibe-primary flex items-center justify-center shadow-md shadow-vibe-primary/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"/>
                        </svg>
                    </div>
                    <span class="font-extrabold text-vibe-on-surface text-base hidden sm:inline">BeanPay KDS</span>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="flex items-center gap-4 bg-vibe-surface-container/50 px-4 py-2 rounded-xl border border-vibe-outline-v/20 text-sm overflow-x-auto w-full md:w-auto whitespace-nowrap">
                <div class="flex items-center gap-1.5 font-semibold text-vibe-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="stats.selesai_hari_ini || 0"></span> Selesai
                </div>
                <div class="h-4 w-px bg-vibe-outline-v/40"></div>
                <div class="flex items-center gap-1.5 font-semibold text-vibe-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="(stats.avg_menit || 0) + ' mnt'"></span> Avg
                </div>
                <div class="h-4 w-px bg-vibe-outline-v/40"></div>
                <div class="flex items-center gap-1.5 font-semibold text-vibe-error">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span x-text="stats.overdue_count || 0"></span> Overdue
                </div>
                 <div class="h-4 w-px bg-vibe-outline-v/40"></div>
                <div class="flex items-center gap-1.5 font-semibold text-vibe-on-surface">
                    <svg class="w-4 h-4 text-vibe-outline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span x-text="stats.dalam_antrian || 0"></span> Antrian
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
             <div class="flex items-center gap-2 px-3 py-1.5 bg-vibe-bg rounded-xl border border-vibe-outline-v/30 text-vibe-on-surface font-mono font-bold text-sm">
                 <span x-text="currentTime"></span>
             </div>
             <div class="flex items-center gap-2">
                 <button @click="testAudio()" title="Test Audio Alert" class="p-2 text-vibe-outline hover:text-vibe-primary transition-colors bg-vibe-bg rounded-lg border border-vibe-outline-v/30 hidden sm:block">
                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                 </button>
                 <a href="<?= BASE_URL ?>/modules/auth/logout.php"
                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-vibe-on-surface-v hover:text-vibe-error hover:bg-vibe-error-light/50 transition-colors text-sm font-semibold border border-transparent hover:border-vibe-error/20">
                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                     </svg>
                     <span class="hidden lg:inline">Keluar</span>
                 </a>
             </div>
        </div>
    </header>

    <!-- ══════════════════ PAGE HEADER & FILTERS ══════════════════ -->
    <div class="bg-white border-b border-vibe-outline-v/20 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between shrink-0 gap-4">
        
        <!-- Station Filters -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
            <template x-for="station in ['all', 'bar', 'kitchen', 'cold']">
                <button @click="activeStation = station"
                        class="px-4 py-2 rounded-full text-sm font-bold transition-all border whitespace-nowrap flex items-center gap-2"
                        :class="activeStation === station 
                            ? 'bg-vibe-primary text-white border-vibe-primary shadow-md shadow-vibe-primary/20' 
                            : 'bg-white text-vibe-on-surface-v border-vibe-outline-v/40 hover:bg-vibe-bg'">
                    
                    <template x-if="station === 'all'"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg></template>
                    <template x-if="station === 'bar'"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></template>
                    <template x-if="station === 'kitchen'"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg></template>
                    <template x-if="station === 'cold'"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></template>
                    
                    <span x-text="station.charAt(0).toUpperCase() + station.slice(1)"></span>
                    <span x-show="station === 'all'" class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-md text-[10px]" x-text="filteredOrders.length"></span>
                </button>
            </template>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-3">
            <button @click="toggleSort()" class="flex items-center gap-2 px-3 py-2 bg-vibe-bg border border-vibe-outline-v/30 rounded-xl text-xs font-bold transition-colors hover:bg-vibe-surface-container" :class="sortByPriority ? 'text-vibe-primary border-vibe-primary/30 bg-vibe-primary-light/50' : 'text-vibe-on-surface-v'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                <span x-text="sortByPriority ? 'Sort: Priority' : 'Sort: Oldest'"></span>
            </button>
        </div>
    </div>

    <!-- ══════════════════ MAIN CONTENT ══════════════════ -->
    <main class="flex-1 overflow-y-auto p-6 relative">

        <!-- Empty State -->
        <div x-show="filteredOrders.length === 0" style="display:none" class="h-full flex flex-col items-center justify-center py-24 text-vibe-outline">
            <div class="w-20 h-20 rounded-2xl bg-vibe-bg border-2 border-dashed border-vibe-outline-v/50 flex items-center justify-center mb-5">
                <svg class="w-9 h-9 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <h2 class="text-xl font-extrabold text-vibe-on-surface mb-1">Stasiun Kosong</h2>
            <p class="text-sm font-medium" x-text="activeStation === 'all' ? 'Belum ada pesanan yang masuk saat ini.' : 'Tidak ada pesanan untuk stasiun ini.'"></p>
        </div>

        <!-- Orders Grid -->
        <div class="kds-grid">
            <template x-for="(order, idx) in sortedOrders" :key="order.id">

                <!-- ── Order Card ── -->
                <div class="order-card card-enter bg-white rounded-2xl shadow-sm overflow-hidden border-2 flex flex-col relative"
                     :class="getCardAnimationClass(order)"
                     :style="`animation-delay: ${idx * 0.05}s`">

                    <!-- Priority Badge -->
                    <template x-if="sortByPriority && idx < 2 && getPriorityScore(order) > 20">
                        <div class="absolute -top-1 -right-1 z-20">
                             <span class="flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                        </div>
                    </template>

                    <!-- Card Header -->
                    <div class="px-4 py-3.5 border-b flex items-start justify-between"
                         :class="getHeaderBg(order)">
                        <div>
                            <div class="flex items-center gap-2">
                                <div class="font-extrabold text-lg text-vibe-on-surface leading-none" x-text="order.nomor_pesanan"></div>
                                <template x-if="sortByPriority">
                                    <span class="px-1.5 py-0.5 rounded bg-black/5 text-[9px] font-bold text-vibe-on-surface-v" x-text="'Pts: ' + getPriorityScore(order)"></span>
                                </template>
                            </div>
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs text-vibe-on-surface-v font-medium">
                                <template x-if="order.tipe_pesanan === 'dine_in'">
                                    <span class="px-2 py-0.5 rounded-md bg-vibe-primary-light text-vibe-primary font-bold" x-text="'Table ' + order.nomor_meja"></span>
                                </template>
                                <template x-if="order.tipe_pesanan !== 'dine_in'">
                                    <span class="flex items-center gap-1 px-2 py-0.5 rounded-md bg-vibe-warning-light text-vibe-warning font-bold">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                        Takeaway
                                    </span>
                                </template>
                            </div>
                        </div>
                        <!-- Timer -->
                        <div class="text-right">
                            <div class="text-2xl font-black font-mono leading-none tracking-tighter"
                                 :class="getTimerClass(order.waktu_pesan)"
                                 x-text="getElapsedTime(order.waktu_pesan)"></div>
                            <div class="text-[10px] font-semibold text-vibe-outline mt-1 uppercase tracking-wider">Elapsed</div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="flex-1 px-4 py-3 space-y-2.5 bg-gray-50/30">
                        <template x-for="item in getFilteredItems(order)" :key="item.id">
                            <div class="flex items-start gap-3 py-1 group">
                                <input type="checkbox"
                                       class="item-check mt-0.5 shadow-sm"
                                       :checked="item.status === 'cooking' || item.status === 'ready'"
                                       @change="toggleItem(item, order)">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-extrabold text-sm text-vibe-on-surface"
                                              :class="(item.status === 'cooking' || item.status === 'ready') ? 'line-through text-vibe-outline opacity-60' : ''"
                                              x-text="item.qty + 'x ' + item.nama_menu"></span>
                                    </div>
                                    <template x-if="item.catatan">
                                        <div class="mt-1 text-xs space-y-0.5 text-vibe-error font-semibold bg-vibe-error-light/30 px-2 py-1 rounded border border-vibe-error/10">
                                            <template x-for="note in item.catatan.split(',')" :key="note">
                                                <div class="flex items-start gap-1">
                                                    <span class="text-vibe-error font-bold mt-px">!</span>
                                                    <span x-text="note.trim()"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Complete Order Button -->
                    <div class="px-4 pb-4 pt-3 bg-white border-t border-vibe-outline-v/10">
                        <button @click="completeOrder(order)"
                                :disabled="!canComplete(order)"
                                class="w-full py-3.5 rounded-xl font-extrabold text-sm flex items-center justify-center gap-2 transition-all shadow-sm"
                                :class="canComplete(order)
                                    ? 'bg-vibe-secondary text-white hover:bg-emerald-700 shadow-vibe-secondary/25 hover:-translate-y-0.5 active:translate-y-0 border-b-4 border-emerald-800'
                                    : 'bg-vibe-bg text-vibe-outline cursor-not-allowed border border-vibe-outline-v/40'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            Complete Order
                        </button>
                    </div>
                </div>

            </template>
        </div>
        
        <!-- Toast Notification Area -->
        <div class="fixed bottom-6 left-6 z-50 flex flex-col gap-2">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="toast-enter bg-vibe-on-surface text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 w-80 border border-white/10">
                    <div class="w-8 h-8 rounded-full bg-vibe-secondary/20 flex items-center justify-center shrink-0">
                         <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold truncate" x-text="toast.message"></div>
                        <div class="text-[10px] text-gray-400" x-text="toast.sub"></div>
                    </div>
                    <!-- Undo Button -->
                    <button @click="undoRecall(toast.orderId, toast.id)" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs font-bold transition-colors shrink-0">
                        Undo
                    </button>
                </div>
            </template>
        </div>

    </main>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kdsApp', () => ({
            orders:      [],
            stats:       {},
            currentTime: '',
            now:         new Date(),
            activeStation: localStorage.getItem('kds_station') || 'all',
            sortByPriority: localStorage.getItem('kds_sort') === 'true',
            toasts:      [],
            alertedOrders: new Set(), // Track which orders have played sound

            init() {
                this.tick();
                setInterval(() => this.tick(), 1000);
                this.fetchData();
                setInterval(() => this.fetchData(), 5000);

                this.$watch('activeStation', val => localStorage.setItem('kds_station', val));
                this.$watch('sortByPriority', val => localStorage.setItem('kds_sort', val));
            },

            tick() {
                this.now = new Date();
                this.currentTime = this.now.toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
                this.checkSLAAlerts();
            },
            
            toggleSort() {
                this.sortByPriority = !this.sortByPriority;
            },

            async fetchData() {
                try {
                    const res   = await fetch('index.php?ajax=1');
                    const data  = await res.json();
                    this.orders = data.orders;
                    this.stats  = data.stats;
                } catch (e) { console.error('Fetch error', e); }
            },

            get filteredOrders() {
                if (this.activeStation === 'all') return this.orders;
                return this.orders.filter(o => o.stations.includes(this.activeStation) || o.stations.includes('any'));
            },
            
            get sortedOrders() {
                let items = [...this.filteredOrders];
                if (this.sortByPriority) {
                    items.sort((a, b) => this.getPriorityScore(b) - this.getPriorityScore(a));
                }
                return items;
            },
            
            getFilteredItems(order) {
                if (this.activeStation === 'all') return order.items;
                return order.items.filter(i => i.stasiun === this.activeStation || i.stasiun === 'any');
            },

            // --- Priority Scoring Logic ---
            getPriorityScore(order) {
                const ageMins = this.getElapsedMinutes(order.waktu_pesan);
                const isTA    = order.tipe_pesanan !== 'dine_in' ? 15 : 0;
                const items   = order.items.length * 2;
                const urgency = ageMins >= 12 ? 40 : (ageMins >= 7 ? 15 : 0);
                return ageMins + isTA + items + urgency;
            },

            // --- Order Actions ---
            async toggleItem(item, order) {
                const fd = new FormData();
                fd.append('action', 'toggle_item');
                fd.append('id', item.id);
                try {
                    await fetch('index.php', { method: 'POST', body: fd });
                    await this.fetchData();
                } catch (e) { console.error('Toggle error', e); }
            },

            async completeOrder(order) {
                if (!this.canComplete(order)) return;
                
                // Optimistic UI update
                const orderId = order.id;
                const orderNum = order.nomor_pesanan;
                this.orders = this.orders.filter(o => o.id !== orderId);
                
                const fd = new FormData();
                fd.append('action', 'complete_order');
                fd.append('pesanan_id', orderId);
                try {
                    await fetch('index.php', { method: 'POST', body: fd });
                    this.showToast(`Pesanan ${orderNum} selesai`, 'Klik Undo jika tidak sengaja', orderId);
                    this.fetchData(); // Sync stats
                } catch (e) { console.error('Complete error', e); this.fetchData(); }
            },
            
            async undoRecall(orderId, toastId) {
                // Remove toast
                this.toasts = this.toasts.filter(t => t.id !== toastId);
                
                const fd = new FormData();
                fd.append('action', 'recall_order');
                fd.append('pesanan_id', orderId);
                try {
                    await fetch('index.php', { method: 'POST', body: fd });
                    this.fetchData();
                } catch (e) { console.error('Recall error', e); }
            },

            canComplete(order) {
                const items = this.getFilteredItems(order);
                return items.length > 0 && items.every(i => i.status === 'cooking' || i.status === 'ready');
            },

            // --- SLA & Alerts ---
            checkSLAAlerts() {
                this.orders.forEach(o => {
                    const m = this.getElapsedMinutes(o.waktu_pesan);
                    if (m >= 15 && !this.alertedOrders.has(o.id)) {
                        this.playAudioAlert();
                        this.alertedOrders.add(o.id);
                    }
                });
            },
            
            playAudioAlert() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
                    osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.1);
                    
                    gain.gain.setValueAtTime(0, ctx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.5, ctx.currentTime + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                    
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.5);
                } catch(e) { console.log('Audio alert blocked/failed', e); }
            },
            
            testAudio() {
                this.playAudioAlert();
            },

            // --- UI Helpers ---
            showToast(msg, sub, orderId) {
                const id = Date.now();
                this.toasts.push({ id, message: msg, sub, orderId });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 5000);
            },

            getElapsedMinutes(ts) {
                return Math.floor((this.now - new Date(ts)) / 60000);
            },

            getElapsedTime(ts) {
                const diff = this.now - new Date(ts);
                if (diff < 0) return '00:00';
                const m = Math.floor(diff / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            },

            getCardAnimationClass(order) {
                const m = this.getElapsedMinutes(order.waktu_pesan);
                const allDone = this.canComplete(order);
                if (allDone) return 'border-vibe-secondary shadow-vibe-secondary/10';
                if (m >= 15)  return 'card-shake';
                if (m >= 7)   return 'card-pulse';
                return 'border-vibe-outline-v/30';
            },

            getHeaderBg(order) {
                const m = this.getElapsedMinutes(order.waktu_pesan);
                const allDone = this.canComplete(order);
                if (allDone) return 'bg-vibe-secondary-light/50 border-vibe-secondary/20';
                if (m >= 15)  return 'bg-vibe-error-light/50 border-vibe-error/20';
                if (m >= 7)   return 'bg-vibe-warning-light/50 border-vibe-warning/20';
                return 'bg-white border-vibe-outline-v/20';
            },

            getTimerClass(ts) {
                const m = this.getElapsedMinutes(ts);
                if (m >= 15) return 'text-vibe-error timer-overdue';
                if (m >= 7)  return 'text-vibe-warning';
                return 'text-vibe-secondary';
            }
        }));
    });
    </script>

</body>
</html>

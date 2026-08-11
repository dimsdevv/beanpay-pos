<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

$page_title = 'Cashier POS';
require_once __DIR__ . '/../../includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$activeSession = $stmt->fetch();

$shiftStats = ['time' => '', 'modal' => 0, 'net_sales' => 0, 'cash_net' => 0, 'trx_count' => 0];
if ($activeSession) {
    $sId = $activeSession['id'];
    $shiftStats['time'] = $activeSession['waktu_buka'];
    $shiftStats['modal'] = (float)($activeSession['modal_awal'] ?? 0);

    $stmtS = $pdo->prepare("SELECT
        COALESCE(SUM(p.total_harga), 0) as net_sales,
        COALESCE(SUM(CASE WHEN b.metode_pembayaran = 'cash' THEN b.jumlah_bayar - b.kembalian ELSE 0 END), 0) as cash_net,
        COUNT(b.id) as trx_count
        FROM pembayaran b
        JOIN pesanan p ON b.pesanan_id = p.id
        WHERE b.sesi_kasir_id = ?");
    $stmtS->execute([$sId]);
    $stats = $stmtS->fetch();
    $shiftStats['net_sales'] = (float)$stats['net_sales'];
    $shiftStats['cash_net'] = (float)$stats['cash_net'];
    $shiftStats['trx_count'] = (int)$stats['trx_count'];
}

// Fetch settings from DB
$settings = [];
$stmtSet = $pdo->query("SELECT * FROM pengaturan");
while ($s = $stmtSet->fetch()) {
    $settings[$s['kunci']] = $s['nilai'];
}
$service_persen_db = (float)($settings['service_charge_persen'] ?? 5);
$pajak_persen_db = (float)($settings['pajak_persen'] ?? 10);
$service_aktif = ($settings['aktifkan_service'] ?? '1') === '1';
$pajak_aktif = ($settings['aktifkan_pajak'] ?? '1') === '1';

// Fetch Data for POS
$categories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
$menus = $pdo->query("
    SELECT m.*, k.nama_kategori,
        CASE WHEN (SELECT COUNT(*) FROM resep_menu WHERE menu_id = m.id) = 0
             THEN 1
             ELSE (SELECT COUNT(*) FROM resep_menu rm
                   JOIN bahan_baku b ON rm.bahan_id = b.id
                   WHERE rm.menu_id = m.id AND b.stok_sekarang < rm.jumlah_dibutuhkan)
        END as missing_ingredients,
        (SELECT COUNT(*) FROM resep_menu WHERE menu_id = m.id) as total_resep
    FROM menu m
    JOIN kategori k ON m.kategori_id = k.id
    WHERE m.is_active = 1
    ORDER BY k.nama_kategori, m.nama_menu
")->fetchAll();
$tables = $pdo->query("SELECT * FROM meja ORDER BY nomor_meja")->fetchAll();

// Fetch pelanggan list for hutang method
$pelangganList = $pdo->query("SELECT id, nama_lengkap, telepon FROM pelanggan ORDER BY nama_lengkap")->fetchAll();

// Fetch low stock details per menu
$lowStockDetails = [];
$stmtLow = $pdo->query("
    SELECT rm.menu_id, b.id, b.nama_bahan, b.stok_sekarang, rm.jumlah_dibutuhkan, b.satuan
    FROM resep_menu rm
    JOIN bahan_baku b ON rm.bahan_id = b.id
    WHERE b.stok_sekarang < rm.jumlah_dibutuhkan
");
while ($row = $stmtLow->fetch()) {
    $mid = $row['menu_id'];
    if (!isset($lowStockDetails[$mid])) $lowStockDetails[$mid] = [];
    $lowStockDetails[$mid][] = [
        'id' => (int)$row['id'],
        'nama' => $row['nama_bahan'],
        'stok' => (float)$row['stok_sekarang'],
        'butuh' => (float)$row['jumlah_dibutuhkan'],
        'satuan' => $row['satuan']
    ];
}

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.panel-slide {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.cart-dot {
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cart-dot:active {
    transform: scale(0.9);
}
@media (max-width: 767px) {
    .cart-panel {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 40;
        max-height: 92vh;
        border-radius: 1rem 1rem 0 0;
        transform: translateY(100%);
        box-shadow: 0 -8px 30px rgba(0,0,0,0.12);
    }
    .cart-panel.open {
        transform: translateY(0);
    }
    .cart-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        z-index: 39;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }
    .cart-backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }
}
</style>

<div x-data="posApp()" class="h-full flex flex-col md:flex-row gap-6 relative">

    <!-- OVERLAY BUKA SHIFT -->
    <?php if (!$activeSession): ?>
    <div class="fixed inset-0 z-50 bg-white/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-vibe-outline-variant rounded-xl p-8 max-w-md w-full animate-fade-in">
            <div class="w-12 h-12 bg-vibe-surface-container text-vibe-primary rounded-lg flex items-center justify-center mb-6 mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-2xl font-display font-bold text-center text-vibe-on-surface mb-2">Buka Shift</h2>
            <p class="text-center text-vibe-on-surface-variant text-sm mb-8">Masukkan saldo awal laci kasir.</p>
            <form action="proses_buka_sesi.php" method="POST">
                <?= csrfField() ?>
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-vibe-on-surface mb-2">Modal Awal</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant font-medium">Rp</span>
                        <input type="number" name="modal_awal" required min="0" class="w-full pl-12 pr-4 py-3 bg-vibe-surface border border-vibe-outline-variant rounded-lg focus:ring-1 focus:ring-vibe-primary focus:border-vibe-primary transition-colors font-medium text-lg text-vibe-on-surface outline-none" placeholder="0">
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-vibe-primary text-white font-semibold rounded-lg hover:bg-vibe-primary-container transition-colors">
                    Mulai Shift
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== MENU CATALOG ==================== -->
    <div class="flex-1 flex flex-col overflow-hidden bg-vibe-bg relative pb-20 md:pb-0">

        <?php if ($activeSession): ?>
        <div x-data="{ shiftOpen: true }" x-show="shiftOpen" x-transition.duration.200ms
             class="shrink-0">
            <div class="flex items-center gap-2 md:gap-3 px-4 md:px-0 py-2 mb-2 bg-vibe-surface-container rounded-lg text-xs select-none">
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-3.5 h-3.5 shrink-0 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-vibe-on-surface-variant font-medium whitespace-nowrap"><?= date('H:i', strtotime($shiftStats['time'])) ?></span>
                </div>

                <span class="w-px h-4 bg-vibe-outline shrink-0"></span>

                <div class="flex items-center gap-1 min-w-0">
                    <span class="text-vibe-on-surface-variant hidden sm:inline">Modal</span>
                    <span class="font-semibold text-vibe-on-surface whitespace-nowrap"><?= number_format($shiftStats['modal'], 0, ',', '.') ?></span>
                </div>

                <span class="w-px h-4 bg-vibe-outline shrink-0"></span>

                <div class="flex items-center gap-1 min-w-0">
                    <svg class="w-3.5 h-3.5 shrink-0 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-vibe-on-surface-variant hidden sm:inline">Penjualan</span>
                    <span class="font-bold text-vibe-primary whitespace-nowrap"><?= number_format($shiftStats['net_sales'], 0, ',', '.') ?></span>
                </div>

                <span class="w-px h-4 bg-vibe-outline shrink-0 hidden sm:block"></span>

                <div class="items-center gap-1 min-w-0 hidden sm:flex">
                    <span class="text-vibe-on-surface-variant font-medium whitespace-nowrap">Laci</span>
                    <span class="font-semibold text-vibe-on-surface whitespace-nowrap"><?= number_format($shiftStats['modal'] + $shiftStats['cash_net'], 0, ',', '.') ?></span>
                </div>

                <span class="w-px h-4 bg-vibe-outline shrink-0 hidden sm:block"></span>

                <div class="items-center gap-1 min-w-0 hidden sm:flex">
                    <span class="text-vibe-on-surface-variant">Transaksi</span>
                    <span class="font-bold text-vibe-on-surface"><?= $shiftStats['trx_count'] ?></span>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <a href="riwayat.php" class="text-xs text-vibe-on-surface-variant hover:text-vibe-primary font-medium transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        Tutup Shift
                    </a>
                    <button @click="shiftOpen = false" class="p-1 text-vibe-outline-variant hover:text-vibe-on-surface transition-colors" title="Sembunyikan" aria-label="Sembunyikan ringkasan shift">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="p-4 mb-4 rounded-md bg-vibe-error-container text-vibe-error font-medium flex items-center gap-2 border border-vibe-error/20 text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Search & Filters -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl md:text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Menu</h2>
                <!-- Desktop cart toggle -->
                <button @click="showCart = true" class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-vibe-primary text-white text-xs font-bold rounded-md hover:bg-vibe-primary-container transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Order <span class="bg-white/20 px-1.5 py-0.5 rounded text-white" x-text="cart.reduce((s,i)=>s+i.qty,0) || '0'"></span>
                </button>
            </div>
            <div class="relative mb-3">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Cari menu..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar">
                <button @click="selectedCategory = 'all'" 
                        class="whitespace-nowrap px-3.5 py-2 rounded-lg border text-xs font-bold transition-colors"
                        :class="selectedCategory === 'all' ? 'bg-vibe-primary border-vibe-primary text-white' : 'bg-white border-vibe-outline-variant text-vibe-on-surface-variant hover:text-vibe-on-surface'">
                    Semua
                </button>
                <?php foreach($categories as $cat): ?>
                <button @click="selectedCategory = <?= $cat['id'] ?>" 
                        class="whitespace-nowrap px-3.5 py-2 rounded-lg border text-xs font-bold transition-colors"
                        :class="selectedCategory === <?= $cat['id'] ?> ? 'bg-vibe-primary border-vibe-primary text-white' : 'bg-white border-vibe-outline-variant text-vibe-on-surface-variant hover:text-vibe-on-surface'">
                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Stock Info Bar -->
            <template x-if="menuStockIssues > 0">
                <div class="flex items-center gap-2 px-3 py-2 mb-3 bg-orange-50 border border-orange-200 rounded-lg text-xs">
                    <svg class="w-4 h-4 shrink-0 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="flex-1 text-orange-800 font-medium"><span x-text="menuStockIssues"></span> menu tidak bisa dipesan — stok bahan habis</span>
                    <button @click="showStockDetail()" class="text-orange-700 font-bold hover:underline shrink-0">Lihat</button>
                </div>
            </template>
        </div>
        
        <!-- Menu Grid -->
        <div class="flex-1 overflow-y-auto rounded-lg pb-2">
            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <template x-for="menu in filteredMenus" :key="menu.id">
                    <button @click="addToCart(menu)" 
                            :disabled="menu.status === 'habis' || menu.missing_ingredients > 0"
                            class="group flex flex-col text-left bg-white border rounded-xl overflow-hidden active:scale-[0.97] transition-all duration-150"
                            :class="(menu.status === 'habis' || menu.missing_ingredients > 0) ? 'border-vibe-outline-variant/50 opacity-60 cursor-not-allowed' : 'border-vibe-outline-variant hover:border-vibe-on-surface'">
                        <div class="w-full h-24 md:h-32 bg-vibe-surface-dim relative overflow-hidden">
                            <template x-if="menu.gambar">
                                <img :src="'../../assets/images/' + menu.gambar" class="w-full h-full object-cover" alt="">
                            </template>
                            <template x-if="!menu.gambar">
                                <div class="w-full h-full flex items-center justify-center text-vibe-outline-variant">
                                    <svg class="w-6 h-6 md:w-8 md:h-8 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            </template>
                            <template x-if="menu.status === 'habis'">
                                <div class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
                                    <span class="px-2 py-1 bg-vibe-error text-white text-[9px] font-bold rounded uppercase tracking-wider">Habis</span>
                                </div>
                            </template>
                            <template x-if="menu.missing_ingredients > 0 && menu.status !== 'habis'">
                                <div class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
                                    <span class="px-2 py-1 bg-orange-500 text-white text-[9px] font-bold rounded uppercase tracking-wider" x-text="menu.total_resep === 0 ? 'Blm Ada Resep' : 'Bahan Habis'"></span>
                                </div>
                            </template>
                        </div>
                        <div class="p-2.5 md:p-3">
                            <div class="text-[9px] md:text-[10px] font-semibold text-vibe-on-surface-variant uppercase tracking-wider mb-0.5" x-text="menu.nama_kategori || 'Uncategorized'"></div>
                            <h3 class="font-semibold text-xs md:text-sm text-vibe-on-surface mb-1 leading-tight line-clamp-2" x-text="menu.nama_menu"></h3>
                            <div class="font-bold font-display text-xs md:text-sm text-vibe-primary mt-auto" x-text="formatRupiah(menu.harga)"></div>
                        </div>
                    </button>
                </template>
            </div>
            <template x-if="filteredMenus.length === 0">
                <div class="flex flex-col items-center justify-center h-48 text-vibe-on-surface-variant">
                    <svg class="w-10 h-10 mb-3 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <p class="font-medium text-sm">Menu tidak ditemukan</p>
                </div>
            </template>
        </div>
    </div>

    <!-- ==================== MOBILE CART FAB ==================== -->
    <template x-if="cart.length > 0">
        <div class="md:hidden fixed bottom-4 left-4 right-4 z-30">
            <button @click="showCart = true" 
                    class="w-full flex items-center gap-3 px-4 py-3 bg-vibe-on-surface text-white rounded-xl shadow-lg active:scale-[0.98] transition-transform cart-dot">
                <div class="relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-vibe-error text-white text-[9px] font-bold rounded-full flex items-center justify-center" x-text="cart.reduce((s,i)=>s+i.qty,0)"></span>
                </div>
                <div class="flex-1 text-left text-sm font-semibold truncate">
                    <span x-text="cart.length + ' item' + (cart.length > 1 ? 's' : '')"></span>
                </div>
                <div class="text-right font-bold font-display text-base" x-text="formatRupiah(grandTotal)"></div>
            </button>
        </div>
    </template>

    <!-- ==================== CART BACKDROP (mobile) ==================== -->
    <div class="cart-backdrop md:hidden" :class="showCart ? 'open' : ''" @click="showCart = false"></div>

    <!-- ==================== CHECKOUT CART ==================== -->
    <div class="cart-panel md:!transform-none md:!relative md:w-[420px] flex flex-col bg-white md:border md:border-vibe-outline-variant md:rounded-xl overflow-hidden md:flex-shrink-0 md:max-h-[calc(100vh-120px)] md:shadow-sm"
         :class="showCart ? 'open' : ''">

        <!-- Handle drag indicator (mobile) -->
        <div class="md:hidden flex justify-center pt-2 pb-1">
            <div class="w-10 h-1 bg-vibe-outline-variant rounded-full"></div>
        </div>

        <!-- Header -->
        <div class="px-4 py-3 border-b border-vibe-outline-variant bg-vibe-surface-dim flex justify-between items-center shrink-0">
            <h3 class="font-display font-bold text-vibe-on-surface flex items-center gap-2">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pesanan
            </h3>
            <div class="flex items-center gap-2">
                <button @click="clearCart()" x-show="cart.length > 0" class="text-[11px] font-semibold text-vibe-error hover:underline uppercase tracking-wider">Hapus</button>
                <button @click="showCart = false" class="md:hidden p-1 text-vibe-on-surface-variant hover:text-vibe-on-surface">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <form action="proses_bayar_langsung.php" method="POST" id="formPOS" class="flex flex-col flex-1 overflow-hidden" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="cart_data" :value="JSON.stringify(cart)">
            <input type="hidden" name="tipe_pesanan" :value="orderType">
            <input type="hidden" name="meja_id" :value="tableId">
            <input type="hidden" name="nama_pelanggan" :value="customerName">
            <input type="hidden" name="metode_pembayaran" :value="paymentMethod">
            <input type="hidden" name="jumlah_bayar" :value="paymentMethod === 'cash' ? amountReceived : grandTotal">
            <input type="hidden" name="pelanggan_id" :value="hutangPelangganId">
            <input type="hidden" name="promo_id" :value="appliedPromo ? appliedPromo.id : ''">

            <!-- Customer Details -->
            <div class="px-4 py-2.5 bg-white border-b border-vibe-outline-variant shrink-0">
                <div class="flex gap-1 p-0.5 bg-vibe-surface-dim rounded-md mb-2">
                    <button type="button" @click="orderType = 'dine_in'" class="flex-1 py-1.5 text-xs font-bold rounded transition-all" :class="orderType === 'dine_in' ? 'bg-white text-vibe-on-surface shadow-sm border border-vibe-outline-variant' : 'text-vibe-on-surface-variant'">Makan di Sini</button>
                    <button type="button" @click="orderType = 'take_away'" class="flex-1 py-1.5 text-xs font-bold rounded transition-all" :class="orderType === 'take_away' ? 'bg-white text-vibe-on-surface shadow-sm border border-vibe-outline-variant' : 'text-vibe-on-surface-variant'">Bungkus</button>
                </div>
                <div class="flex gap-2">
                    <select x-show="orderType === 'dine_in'" x-model="tableId" class="w-1/3 px-2 py-1.5 bg-white border border-vibe-outline-variant rounded-md text-xs font-medium focus:border-vibe-on-surface outline-none">
                        <option value="">Meja...</option>
                        <?php foreach($tables as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nomor_meja']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" x-model="customerName" placeholder="Nama Pelanggan (opsional)" class="flex-1 px-3 py-1.5 bg-white border border-vibe-outline-variant rounded-md text-xs font-medium focus:border-vibe-on-surface outline-none placeholder-vibe-outline-variant">
                </div>
            </div>

            <!-- Items List -->
            <div class="flex-1 overflow-y-auto bg-vibe-surface-dim p-3 md:p-4">
                <template x-if="cart.length === 0">
                    <div class="h-full flex flex-col items-center justify-center text-vibe-on-surface-variant">
                        <svg class="w-10 h-10 mb-3 text-vibe-outline-variant opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <p class="text-sm font-medium">Keranjang kosong</p>
                        <p class="text-[11px] mt-1">Pilih menu di atas</p>
                    </div>
                </template>
                <div class="space-y-2">
                    <template x-for="(item, index) in cart" :key="item.cartId">
                        <div class="bg-white border border-vibe-outline-variant rounded-lg p-2.5 flex flex-col gap-2 relative">
                            <button type="button" @click="removeItem(index)" class="absolute top-1.5 right-1.5 text-vibe-outline-variant hover:text-vibe-error p-1 rounded-md transition-colors z-10">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <div class="flex justify-between items-start pr-6">
                                <div class="font-semibold text-sm text-vibe-on-surface leading-tight" x-text="item.nama_menu"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-sm text-vibe-primary" x-text="formatRupiah(item.harga * item.qty)"></div>
                                <div class="flex items-center border border-vibe-outline-variant rounded-md overflow-hidden bg-vibe-surface-dim h-8">
                                    <button type="button" @click="decreaseQty(index)" class="w-8 h-full flex items-center justify-center text-vibe-on-surface-variant hover:bg-white hover:text-vibe-on-surface transition-colors font-bold text-base">-</button>
                                    <div class="w-8 h-full flex items-center justify-center text-sm font-bold bg-white border-x border-vibe-outline-variant" x-text="item.qty"></div>
                                    <button type="button" @click="increaseQty(index)" class="w-8 h-full flex items-center justify-center text-vibe-on-surface-variant hover:bg-white hover:text-vibe-on-surface transition-colors font-bold text-base">+</button>
                                </div>
                            </div>
                            <input type="text" x-model="item.catatan" placeholder="Catatan (opsional)..." class="w-full text-[10px] px-2 py-1.5 bg-vibe-surface-dim border border-transparent rounded focus:outline-none focus:border-vibe-outline-variant placeholder-vibe-outline-variant transition-colors">
                        </div>
                    </template>
                </div>
            </div>

            <!-- Payment Footer -->
            <div class="p-3 md:p-4 bg-white border-t border-vibe-outline-variant shrink-0">
                <div class="flex flex-col gap-2.5">
                    
                    <!-- Breakdown -->
                    <div class="space-y-1">
                        <div class="flex justify-between text-[11px] text-vibe-on-surface-variant font-medium">
                            <span>Subtotal</span>
                            <span x-text="formatRupiah(subtotal)"></span>
                        </div>
                        <div class="flex justify-between text-[11px] text-vibe-secondary font-medium" x-show="appliedPromo">
                            <span>Diskon</span>
                            <span x-text="'- ' + formatRupiah(discountAmount)"></span>
                        </div>
                        <div class="flex justify-between text-[11px] text-vibe-on-surface-variant font-medium">
                            <span>Pajak & Service</span>
                            <span x-text="formatRupiah(serviceAmount + taxAmount)"></span>
                        </div>
                        <div class="flex justify-between items-end pt-1 mt-1 border-t border-vibe-outline-variant">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-vibe-on-surface">Total</span>
                            <span class="text-lg md:text-xl font-display font-bold text-vibe-on-surface tracking-tight leading-none" x-text="formatRupiah(grandTotal)"></span>
                        </div>
                    </div>

                    <!-- Promo -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input type="text" x-model="promoCode" placeholder="Kode promo" :disabled="appliedPromo"
                                   style="text-transform: uppercase;"
                                   class="w-full px-2.5 py-1.5 bg-vibe-surface-dim border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface font-semibold text-[11px] pr-10">
                            <button type="button" @click="applyPromo()" x-show="!appliedPromo"
                                    class="absolute right-1 top-1/2 -translate-y-1/2 px-2 py-0.5 bg-vibe-on-surface text-white text-[10px] font-bold rounded">Pakai</button>
                            <button type="button" @click="removePromo()" x-show="appliedPromo"
                                    class="absolute right-1 top-1/2 -translate-y-1/2 px-2 py-0.5 bg-vibe-error-container text-vibe-error text-[10px] font-bold rounded">Batal</button>
                        </div>
                    </div>
                    <p class="text-[10px] font-semibold text-right -mt-1.5" :class="promoMessageClass" x-text="promoMessage"></p>

                    <!-- Payment Methods -->
                    <div class="flex gap-2">
                        <template x-for="m in methods">
                            <button type="button" @click="setPaymentMethod(m.id)" 
                                    class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-[11px] font-bold transition-colors"
                                    :class="paymentMethod === m.id ? 'border-vibe-on-surface bg-vibe-on-surface text-white' : 'border-vibe-outline-variant bg-white text-vibe-on-surface-variant hover:border-vibe-on-surface'">
                                <span x-html="m.icon"></span>
                                <span x-text="m.label"></span>
                            </button>
                        </template>
                    </div>

                    <!-- Cash Input -->
                    <div x-show="paymentMethod === 'cash'">
                        <div class="bg-vibe-surface-dim p-2 rounded-lg border border-vibe-outline-variant space-y-2">
                            <div class="flex gap-1.5 items-center">
                                <div class="relative flex-1">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant font-semibold text-xs">Rp</span>
                                    <input type="number" x-model.number="amountReceived" class="w-full pl-7 pr-2 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface font-semibold text-vibe-on-surface text-sm">
                                </div>
                                <button type="button" @click="amountReceived = grandTotal" class="px-2.5 py-2 bg-vibe-on-surface text-white rounded-md text-[10px] font-bold transition-colors shrink-0 active:scale-[0.98]">Pas</button>
                            </div>
                            <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar">
                                <span class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider shrink-0">Cepat</span>
                                <template x-for="amount in getQuickCashOptions()" :key="amount">
                                    <button type="button" @click="amountReceived = amount" 
                                            class="px-2.5 py-1.5 bg-white border border-vibe-outline-variant hover:border-vibe-on-surface rounded-md text-[10px] font-bold text-vibe-on-surface transition-colors shrink-0 active:scale-[0.98]"
                                            :class="amountReceived === amount ? 'border-vibe-on-surface bg-vibe-primary-light' : ''"
                                            x-text="quickCashLabel(amount)">
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="flex justify-between items-center px-1 pt-1.5">
                            <span class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest" :class="changeAmount < 0 ? 'text-vibe-error' : ''">Kembali</span>
                            <span class="text-sm font-bold" :class="changeAmount < 0 ? 'text-vibe-error' : 'text-vibe-secondary'" x-text="formatRupiah(Math.max(0, changeAmount))"></span>
                        </div>
                    </div>

                    <div x-show="paymentMethod === 'transfer'" x-transition.opacity.duration.150ms class="flex items-center gap-3 bg-vibe-surface-dim border border-vibe-outline-variant rounded-lg p-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[11px] text-vibe-on-surface-variant">Transfer <span class="font-bold" x-text="formatRupiah(grandTotal)"></span></div>
                            <div class="text-[10px] text-vibe-on-surface-variant truncate">BCA 5142777011 a.n. Budi Mulyana</div>
                        </div>
                        <button type="button" @click="openTransferModal()" class="px-3 py-2 bg-vibe-on-surface text-white rounded-md text-xs font-bold transition-colors active:scale-[0.98] shrink-0">Lanjut</button>
                    </div>

                    <!-- Hutang Customer Selector -->
                    <div x-show="paymentMethod === 'hutang'" x-transition.opacity.duration.150ms class="space-y-2">
                        <div class="flex items-center gap-3 bg-vibe-surface-dim border border-vibe-outline-variant rounded-lg p-3">
                            <div class="w-9 h-9 rounded-lg bg-vibe-accent/10 border border-vibe-accent/20 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-vibe-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[11px] text-vibe-on-surface-variant">Hutang <span class="font-bold" x-text="formatRupiah(grandTotal)"></span></div>
                                <div class="text-[10px] text-vibe-on-surface-variant">Catat ke piutang pelanggan</div>
                            </div>
                        </div>
                        <div class="relative">
                            <select x-model="hutangPelangganId" :class="paymentMethod === 'hutang' && !hutangPelangganId ? 'border-vibe-error' : 'border-vibe-outline-variant'"
                                    class="w-full px-3 py-2.5 bg-white border rounded-md text-sm font-semibold text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface transition-colors appearance-none pr-9">
                                <option value="">— Pilih pelanggan —</option>
                                <template x-for="pl in pelangganList" :key="pl.id">
                                    <option :value="pl.id" x-text="pl.nama_lengkap + (pl.telepon ? ' (' + pl.telepon + ')' : '')"></option>
                                </template>
                            </select>
                            <svg class="w-4 h-4 text-vibe-outline-variant absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                        <p class="text-[10px] text-vibe-on-surface-variant px-1" x-show="paymentMethod === 'hutang' && !hutangPelangganId">Pilih pelanggan untuk mencatat hutang ini.</p>
                        <p class="text-[10px] text-vibe-on-surface-variant px-1" x-show="hutangPelangganId">Hutang akan dicatat atas nama pelanggan terpilih. Hubungi admin jika pelanggan belum terdaftar.</p>
                    </div>

                    <div x-show="paymentIssue" x-transition.opacity.duration.150ms class="flex items-start gap-2 rounded-lg border border-vibe-outline-variant bg-vibe-surface-dim px-3 py-2">
                        <div class="w-5 h-5 rounded-full bg-white border border-vibe-outline-variant flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-vibe-on-surface" x-text="paymentIssue ? paymentIssue.title : ''"></div>
                            <div class="text-[11px] text-vibe-on-surface-variant leading-snug" x-text="paymentIssue ? paymentIssue.detail : ''"></div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="button" @click="submitForm()" 
                            :disabled="isSubmitting"
                            class="w-full py-3.5 rounded-xl font-bold text-sm transition-all flex justify-center items-center gap-2 bg-vibe-primary text-white hover:bg-vibe-primary-container shadow-md active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="!isSubmitting">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                BAYAR <span x-text="formatRupiah(grandTotal)"></span>
                            </span>
                        </template>
                        <template x-if="isSubmitting">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Memproses...
                            </span>
                        </template>
                    </button>
                </div>
            </div>

            <!-- ═══ TRANSFER MODAL (fullscreen, inside form) ═══ -->
            <div x-show="showTransferModal" x-cloak
                 x-transition:enter="transition duration-200 ease-out"
                 x-transition:enter-start="opacity-0 scale-[0.97]"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition duration-150 ease-in"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-[0.97]"
                 @keydown.escape.window="closeTransferModal()"
                 class="fixed inset-0 z-50 flex flex-col bg-white">
                <div class="flex items-center justify-between px-5 py-4 border-b border-vibe-outline shrink-0">
                    <h2 class="text-lg font-display font-bold text-vibe-on-surface tracking-tight">Konfirmasi Transfer</h2>
                    <button type="button" @click="closeTransferModal()" class="p-2 -mr-2 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-6 space-y-6">
                    <!-- Bank Info Card -->
                    <div class="bg-vibe-primary text-white rounded-xl p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <div class="text-white/60 text-[11px] font-medium">Bank Tujuan</div>
                                    <div class="font-bold text-sm tracking-tight">BCA</div>
                                </div>
                            </div>
                            <button type="button" @click="copyRekening()" class="px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-[11px] font-bold transition-colors active:scale-[0.97]" x-text="rekeningCopied ? 'Disalin' : 'Salin'"></button>
                        </div>
                        <div class="border-t border-white/15 pt-3">
                            <div class="text-white/60 text-[11px] font-medium">Nomor Rekening</div>
                            <div class="font-display font-bold text-xl md:text-2xl tracking-tight tabular-nums">5142777011</div>
                        </div>
                        <div>
                            <div class="text-white/60 text-[11px] font-medium">Atas Nama</div>
                            <div class="font-bold text-sm">Budi Mulyana</div>
                        </div>
                        <div class="bg-white/10 rounded-lg px-3.5 py-2.5 -mx-0.5">
                            <div class="text-white/80 text-xs font-medium">Total yang harus ditransfer</div>
                            <div class="font-display font-black text-xl md:text-2xl tracking-tight mt-0.5" x-text="formatRupiah(grandTotal)"></div>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Nama Pengirim</label>
                            <input type="text" name="nama_pengirim" x-model="transferNama" required
                                   placeholder="Cth: Budi Mulyana"
                                   class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-lg text-sm font-semibold text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface transition-colors placeholder-vibe-outline-variant">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Nomor Referensi / Berita</label>
                            <input type="text" name="referensi" x-model="transferRef" required
                                   placeholder="Cth: 271890 / Pembayaran"
                                   class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-lg text-sm font-semibold text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface transition-colors placeholder-vibe-outline-variant">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1.5">Upload Bukti Transfer</label>
                            <div @click="$refs.buktiInput.click()"
                                 class="relative flex items-center justify-center gap-3 p-6 bg-vibe-surface-dim border-2 border-dashed border-vibe-outline-variant rounded-xl cursor-pointer hover:border-vibe-on-surface hover:bg-white transition-colors"
                                 :class="transferBuktiPreview ? 'border-vibe-secondary bg-white' : ''">
                                <template x-if="!transferBuktiPreview">
                                    <div class="flex flex-col items-center gap-2 text-center">
                                        <div class="w-12 h-12 rounded-xl bg-white border border-vibe-outline-variant flex items-center justify-center">
                                            <svg class="w-6 h-6 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-vibe-on-surface">Ketuk untuk unggah</div>
                                            <div class="text-[11px] text-vibe-on-surface-variant">JPG, PNG, atau WebP · Maks 2MB</div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="transferBuktiPreview">
                                    <div class="flex items-center gap-4 w-full">
                                        <img :src="transferBuktiPreview" class="w-16 h-16 rounded-lg object-cover border border-vibe-outline-variant shrink-0">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-vibe-on-surface truncate" x-text="transferBuktiNama || 'Bukti terunggah'"></div>
                                            <div class="text-[11px] text-vibe-secondary font-medium">Terunggah</div>
                                        </div>
                                        <button type="button" @click.stop="resetBukti()" class="p-2 text-vibe-on-surface-variant hover:text-vibe-error hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <input type="file" name="bukti_transfer" x-ref="buktiInput" class="hidden" accept="image/*" @change="previewBukti($event)">
                        </div>
                    </div>
                </div>

                <div class="shrink-0 border-t border-vibe-outline-variant bg-white px-5 py-4 space-y-2">
                    <div x-show="paymentIssue" x-transition.opacity.duration.150ms class="flex items-center gap-2 px-3 py-2 bg-vibe-error-container text-vibe-error rounded-lg text-xs font-bold">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="paymentIssue ? paymentIssue.title + ' — ' + paymentIssue.detail : ''"></span>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="closeTransferModal()" class="flex-1 py-3 rounded-xl border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors active:scale-[0.98]">Batal</button>
                        <button type="button" @click="submitForm()" 
                                :disabled="isSubmitting"
                                class="flex-1 py-3 rounded-xl bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.98] shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!isSubmitting">Konfirmasi & Bayar</span>
                            <span x-show="isSubmitting" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('posApp', () => ({
        // ── Menu ──
        menus: <?= json_encode($menus) ?>,
        searchQuery: '',
        selectedCategory: 'all',
        
        // ── Cart ──
        cart: [],
        showCart: false,
        
        // ── Order ──
        orderType: 'dine_in',
        tableId: '',
        customerName: '',
        
        // ── Payment ──
        paymentMethod: 'cash',
        amountReceived: 0,
        rekeningCopied: false,
        showTransferModal: false,
        transferNama: '',
        transferRef: '',
        transferBuktiPreview: null,
        transferBuktiNama: '',
        isSubmitting: false,
        
        // ── Promo ──
        promoCode: '',
        appliedPromo: null,
        promoMessage: '',
        promoMessageClass: '',

        // Payment methods config
        methods: [
            { id: 'cash', label: 'Tunai', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>' },
            { id: 'hutang', label: 'Hutang', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>' },
            { id: 'transfer', label: 'Transfer', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>' },
        ],

        // ── Hutang (debt) state ──
        hutangPelangganId: '',
        pelangganList: <?= json_encode($pelangganList, JSON_UNESCAPED_SLASHES) ?>,

        // ── Low stock details (menu_id -> [{nama, stok, butuh, satuan}]) ──
        lowStockDetails: <?= json_encode($lowStockDetails) ?>,

        init() {
            window.restockBahan = (id, nama) => this.restockBahan(id, nama, 5);
            window.restockBahanDialog = (id, nama, butuh) => {
                const min = Math.ceil(butuh || 1);
                Swal.fire({
                    title: 'Tambah Stok',
                    html: `<div style="font-family:Inter,sans-serif;text-align:left">
                        <p class="text-sm text-gray-600 mb-2" style="font-family:Inter,sans-serif">Stok <strong>${nama}</strong>:</p>
                        <input id="qty-input" type="number" value="${min}" min="1" step="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-lg font-bold text-center" style="font-family:Inter,sans-serif">
                    </div>`,
                    confirmButtonText: 'Tambah',
                    showCancelButton: true,
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#0F172A',
                    cancelButtonColor: '#E2E8F0',
                    customClass: { popup: 'rounded-xl' },
                    preConfirm: () => parseInt(document.getElementById('qty-input').value) || 0,
                }).then(r => {
                    if (r.isConfirmed && r.value > 0) this.restockBahan(id, nama, r.value);
                });
            };
        },

        // ── Constants from DB ──
        SERVICE_PERCENT: <?= $service_persen_db ?>,
        SERVICE_ACTIVE: <?= $service_aktif ? 'true' : 'false' ?>,
        TAX_PERCENT: <?= $pajak_persen_db ?>,
        TAX_ACTIVE: <?= $pajak_aktif ? 'true' : 'false' ?>,

        // ── Computed ──
        get filteredMenus() {
            let f = this.menus;
            if (this.selectedCategory !== 'all') f = f.filter(m => m.kategori_id == this.selectedCategory);
            const q = this.searchQuery.trim().toLowerCase();
            if (q) f = f.filter(m => m.nama_menu.toLowerCase().includes(q));
            return f;
        },
        get menuStockIssues() {
            return this.menus.filter(m => m.missing_ingredients > 0).length;
        },

        // ── Cart actions ──
        addToCart(menu) {
            if (menu.missing_ingredients > 0) {
                const bahan = this.lowStockDetails[menu.id] || [];
                let rows = bahan.map(b => `
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3 text-sm font-semibold text-gray-800">${b.nama}</td>
                        <td class="py-2 pr-3 text-sm text-right font-bold" style="color:#dc2626">${b.stok}</td>
                        <td class="py-2 pr-3 text-sm text-right text-gray-500">${b.butuh}</td>
                        <td class="py-2 text-sm text-gray-400">${b.satuan}</td>
                    </tr>
                `).join('');
                Swal.fire({
                    icon: 'warning',
                    title: '<span class="text-base font-bold" style="font-family:Outfit,sans-serif">Stok Bahan Tidak Cukup</span>',
                    html: `
                        <div class="text-left" style="font-family:Inter,sans-serif">
                            <p class="text-sm text-gray-600 mb-3 font-medium">Menu <span class="font-bold text-gray-900">${menu.nama_menu}</span> tidak bisa dipesan karena bahan berikut habis:</p>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-200 text-gray-400 uppercase tracking-wider text-[10px]">
                                        <th class="pb-1.5 pr-3 text-left font-semibold">Bahan</th>
                                        <th class="pb-1.5 pr-3 text-right font-semibold">Stok</th>
                                        <th class="pb-1.5 pr-3 text-right font-semibold">Butuh</th>
                                        <th class="pb-1.5 text-left font-semibold">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                            <p class="text-[11px] text-gray-400 mt-3">Isi ulang bahan di inventaris agar menu tersedia kembali.</p>
                        </div>
                    `,
                    confirmButtonColor: '#0F172A',
                    confirmButtonText: 'Mengerti',
                    customClass: { popup: 'rounded-xl' }
                });
                return;
            }
            const i = this.cart.findIndex(item => item.id === menu.id && !item.catatan);
            if (i > -1) { this.cart[i].qty++; }
            else {
                this.cart.push({
                    cartId: Date.now() + Math.random(),
                    id: menu.id,
                    nama_menu: menu.nama_menu,
                    harga: parseFloat(menu.harga),
                    qty: 1,
                    catatan: ''
                });
            }
            this.syncAmount();
        },
        increaseQty(i) { this.cart[i].qty++; this.syncAmount(); },
        decreaseQty(i) {
            if (this.cart[i].qty > 1) this.cart[i].qty--;
            else this.cart.splice(i, 1);
            this.syncAmount();
        },
        removeItem(i) { this.cart.splice(i, 1); this.syncAmount(); },
        clearCart() {
            if (!confirm('Hapus semua item?')) return;
            this.cart = [];
            this.removePromo();
            this.syncAmount();
        },
        syncAmount() {
            if (this.paymentMethod !== 'cash' || this.amountReceived < this.grandTotal) {
                this.amountReceived = this.grandTotal;
            }
        },

        // ── Kalkulasi ──
        get subtotal() { return this.cart.reduce((s, i) => s + (i.harga * i.qty), 0); },
        get discountAmount() { return this.appliedPromo ? parseFloat(this.appliedPromo.diskon_nominal) || 0 : 0; },
        get subtotalBersih() { return Math.max(0, this.subtotal - this.discountAmount); },
        get serviceAmount() { return this.SERVICE_ACTIVE ? Math.round((this.subtotalBersih * this.SERVICE_PERCENT) / 100 / 100) * 100 : 0; },
        get taxAmount() { return this.TAX_ACTIVE ? Math.round(((this.subtotalBersih + this.serviceAmount) * this.TAX_PERCENT) / 100 / 100) * 100 : 0; },
        get grandTotal() { return this.subtotalBersih + this.serviceAmount + this.taxAmount; },

        // ── Promo ──
        async applyPromo() {
            if (!this.promoCode.trim() || this.subtotal === 0) return;
            this.promoMessage = 'Cek...';
            this.promoMessageClass = 'text-vibe-on-surface-variant';
            try {
                const fd = new FormData();
                fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fd.append('kode_promo', this.promoCode);
                fd.append('subtotal', this.subtotal);
                const res = await fetch('../../api/cek_promo.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.appliedPromo = { id: data.promo_id, diskon_nominal: data.diskon_nominal };
                    this.promoMessage = data.message;
                    this.promoMessageClass = 'text-vibe-primary';
                    this.syncAmount();
                } else {
                    this.promoMessage = data.message;
                    this.promoMessageClass = 'text-vibe-error';
                }
            } catch (e) { this.promoMessage = 'Gagal hubungi server.'; this.promoMessageClass = 'text-vibe-error'; }
        },
        removePromo() {
            this.appliedPromo = null;
            this.promoCode = '';
            this.promoMessage = '';
            this.syncAmount();
        },

        // ── Stock detail modal ──
        showStockDetail() {
            const menus = this.menus.filter(m => m.missing_ingredients > 0);
            let items = menus.map(m => {
                const bahan = this.lowStockDetails[m.id] || [];
                const rows = bahan.map(b => `
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                        <div>
                            <span class="text-sm font-semibold text-gray-800">${b.nama}</span>
                            <span class="text-[11px] text-gray-400 ml-1">${b.satuan}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right">
                                <span class="text-sm font-bold" style="color:#dc2626">${b.stok}</span>
                                <span class="text-[11px] text-gray-400 ml-1">/ ${b.butuh}</span>
                            </div>
                            <button onclick="window.restockBahanDialog(${b.id}, '${b.nama.replace(/'/g, "\\'")}', ${b.butuh})"
                                    class="px-2 py-0.5 bg-white border border-gray-200 hover:border-vibe-primary rounded text-[10px] font-bold text-vibe-primary transition-colors">
                                Restock
                            </button>
                        </div>
                    </div>
                `).join('');
                return `
                    <div class="mb-4 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-bold text-gray-900">${m.nama_menu}</span>
                            <span class="px-1.5 py-0.5 bg-orange-100 text-orange-700 text-[10px] font-bold rounded">${bahan.length} bahan</span>
                        </div>
                        ${rows}
                    </div>
                `;
            }).join('');
            Swal.fire({
                icon: 'warning',
                title: '<span class="text-base font-bold" style="font-family:Outfit,sans-serif">Stok Bahan Habis</span>',
                html: `
                    <div class="text-left max-h-80 overflow-y-auto" style="font-family:Inter,sans-serif">
                        <p class="text-sm text-gray-500 mb-4 font-medium">Menu berikut tidak bisa dipesan — stok bahan tidak mencukupi:</p>
                        ${items}
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 text-[10px] text-gray-400">Klik Restock untuk tambah stok bahan</div>
                `,
                confirmButtonColor: '#0F172A',
                confirmButtonText: 'Tutup',
                customClass: { popup: 'rounded-xl' }
            });
        },

        // ── Restock Cepat ──
        async restockBahan(bahanId, bahanNama, qty) {
            try {
                const fd = new FormData();
                const token = document.querySelector('input[name="csrf_token"]');
                if (token)                 fd.append('csrf_token', token.value);
                fd.append('bahan_id', bahanId);
                fd.append('jumlah', qty);

                const res = await fetch('proses_restock_cepat.php', { method: 'POST', body: fd });
                const data = await res.json();

                    if (data.success) {
                    for (const menuId in this.lowStockDetails) {
                        const arr = this.lowStockDetails[menuId];
                        for (let i = 0; i < arr.length; i++) {
                            if (arr[i].id === bahanId) {
                                arr[i].stok += data.jumlah || qty;
                                break;
                            }
                        }
                    }
                    // Re-hitung missing_ingredients per menu
                    this.menus.forEach(m => {
                        const bahanList = this.lowStockDetails[m.id] || [];
                        m.missing_ingredients = bahanList.filter(b => b.stok < b.butuh).length;
                    });
                    // Tutup modal lama, buka ulang
                    Swal.close();
                    setTimeout(() => { if (this.menuStockIssues > 0) this.showStockDetail(); }, 100);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#0F172A',
                        customClass: { popup: 'rounded-xl' }
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal hubungi server.',
                    confirmButtonColor: '#0F172A',
                    customClass: { popup: 'rounded-xl' }
                });
            }
        },

        // ── Payment ──
        setPaymentMethod(m) {
            this.paymentMethod = m;
            this.rekeningCopied = false;
            if (m === 'transfer') { this.openTransferModal(); }
            this.syncAmount();
        },
        openTransferModal() { this.showTransferModal = true; },
        closeTransferModal() {
            this.showTransferModal = false;
            this.paymentMethod = 'cash';
            this.rekeningCopied = false;
            this.transferNama = '';
            this.transferRef = '';
            this.resetBukti();
        },
        async copyRekening() {
            try {
                await navigator.clipboard.writeText('5142777011');
                this.rekeningCopied = true;
                setTimeout(() => { this.rekeningCopied = false; }, 2000);
            } catch (e) { this.rekeningCopied = false; }
        },
        previewBukti(e) {
            const f = e.target.files?.[0];
            if (!f) return;
            this.transferBuktiNama = f.name;
            const r = new FileReader();
            r.onload = (ev) => { this.transferBuktiPreview = ev.target.result; };
            r.readAsDataURL(f);
        },
        resetBukti() {
            this.transferBuktiPreview = null;
            this.transferBuktiNama = '';
            const inp = this.$refs?.buktiInput;
            if (inp) inp.value = '';
        },
        get changeAmount() { return this.paymentMethod === 'cash' ? this.amountReceived - this.grandTotal : 0; },
        getQuickCashOptions() {
            const t = this.grandTotal;
            if (t <= 0) return [];
            const ceil5k = Math.ceil(t / 5000) * 5000;
            const ceil10k = Math.ceil(t / 10000) * 10000;
            const ceil20k = Math.ceil(t / 20000) * 20000;
            const denoms = [50000, 100000, 200000];
            let opts = [ceil5k];
            if (ceil10k > ceil5k) opts.push(ceil10k);
            if (ceil20k > ceil10k) opts.push(ceil20k);
            denoms.forEach(d => { if (d > t && !opts.includes(d)) opts.push(d); });
            return opts.filter((v, i, a) => a.indexOf(v) === i && v > t).slice(0, 4);
        },
        quickCashLabel(amount) {
            return amount >= 1000 ? (amount / 1000).toLocaleString('id-ID') + 'rb' : this.formatRupiah(amount);
        },
        get paymentIssue() {
            if (!this.cart.length) {
                return { title: 'Keranjang masih kosong', detail: 'Pilih menu dulu, lalu pembayaran bisa diproses.' };
            }
            if (this.orderType === 'dine_in' && !this.tableId) {
                return { title: 'Meja belum dipilih', detail: 'Pesanan makan di sini butuh nomor meja.' };
            }
            if (this.paymentMethod === 'cash' && this.amountReceived < this.grandTotal) {
                return { title: 'Uang tunai kurang', detail: 'Kurang ' + this.formatRupiah(this.grandTotal - this.amountReceived) + ' dari total tagihan.' };
            }
            if (this.paymentMethod === 'transfer') {
                if (!this.transferNama.trim()) return { title: 'Nama pengirim belum diisi', detail: 'Isi nama pengirim untuk verifikasi transfer.' };
                if (!this.transferRef.trim()) return { title: 'Nomor referensi belum diisi', detail: 'Isi nomor referensi dari bukti transfer.' };
                if (!this.transferBuktiPreview) return { title: 'Bukti transfer belum diupload', detail: 'Upload foto bukti transfer untuk arsip.' };
            }
            return null;
        },
        isValidOrder() {
            return !this.paymentIssue;
        },
        formatRupiah(a) { return a ? 'Rp ' + Math.round(a).toLocaleString('id-ID') : 'Rp 0'; },

        // Handle form submit with validation
        submitForm() {
            if (this.isSubmitting) return;

            if (!this.isValidOrder()) {
                if (this.paymentIssue) {
                    Swal.fire({
                        icon: 'warning',
                        title: this.paymentIssue.title,
                        text: this.paymentIssue.detail,
                        confirmButtonColor: '#0F172A',
                        customClass: { popup: 'rounded-xl' }
                    });
                }
                return;
            }

            // Khusus transfer, submit dilakukan melalui modal
            if (this.paymentMethod === 'transfer') {
                if (!this.transferNama.trim() || !this.transferRef.trim() || !this.transferBuktiPreview) {
                    this.openTransferModal();
                    return;
                }
                this.isSubmitting = true;
                document.getElementById('formPOS').submit();
                return;
            }

            // Khusus hutang, wajib pilih pelanggan
            if (this.paymentMethod === 'hutang') {
                if (!this.hutangPelangganId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih Pelanggan',
                        text: 'Pilih pelanggan terlebih dahulu untuk mencatat hutang.',
                        confirmButtonColor: '#0F172A',
                        customClass: { popup: 'rounded-xl' }
                    });
                    return;
                }
                this.isSubmitting = true;
                document.getElementById('formPOS').submit();
                return;
            }

            // Untuk cash, submit langsung
            this.isSubmitting = true;
            document.getElementById('formPOS').submit();
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

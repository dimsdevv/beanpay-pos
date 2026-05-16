<?php
$page_title = 'Input Pesanan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Generate nomor pesanan otomatis
function generateNomor(PDO $pdo): string {
    $today = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE DATE(waktu_pesan) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return 'ORD-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Fetch pengaturan (dibutuhkan oleh POST handler)
$stmtSet = $pdo->query("SELECT kunci, nilai FROM pengaturan");
$settings = [];
while ($row = $stmtSet->fetch()) $settings[$row['kunci']] = $row['nilai'];

$pajakPersen = ($settings['aktifkan_pajak'] ?? '0') === '1' ? (float)($settings['pajak_persen'] ?? 0) : 0;
$servicePersen = ($settings['aktifkan_service'] ?? '0') === '1' ? (float)($settings['service_charge_persen'] ?? 0) : 0;

// Handle submit order — HARUS sebelum header.php (sebelum HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_order') {
    $tipe        = $_POST['tipe_pesanan'];
    $meja_id     = !empty($_POST['meja_id']) ? (int)$_POST['meja_id'] : null;
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $items       = json_decode($_POST['items_json'] ?? '[]', true);

    if (empty($items)) { $_SESSION['error'] = "Keranjang pesanan masih kosong!"; header('Location: order.php'); exit; }
    if ($tipe === 'dine_in' && !$meja_id) { $_SESSION['error'] = "Pilih meja terlebih dahulu!"; header('Location: order.php'); exit; }

    try {
        $pdo->beginTransaction();

        // Hitung total dengan smart logic di server
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['harga'] * $item['qty'];
        }
        
        $serviceNominal = ($subtotal * $servicePersen) / 100;
        $pajakNominal = (($subtotal + $serviceNominal) * $pajakPersen) / 100;
        $total = $subtotal + $serviceNominal + $pajakNominal;

        $nomor = generateNomor($pdo);

        // Insert pesanan
        $stmtP = $pdo->prepare("INSERT INTO pesanan (nomor_pesanan, tipe_pesanan, meja_id, nama_pelanggan, waiter_id, subtotal, service_persen, service_nominal, pajak_persen, pajak_nominal, total_harga, status_pesanan, waktu_pesan) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
        $stmtP->execute([$nomor, $tipe, $meja_id, $nama_pelanggan, $_SESSION['user_id'], $subtotal, $servicePersen, $serviceNominal, $pajakPersen, $pajakNominal, $total]);
        $pesanan_id = $pdo->lastInsertId();

        // Insert detail
        $stmtD = $pdo->prepare("INSERT INTO detail_pesanan (pesanan_id, menu_id, qty, harga_satuan, catatan, status_item) VALUES (?,?,?,?,?,'pending')");
        foreach ($items as $item) {
            $stmtD->execute([$pesanan_id, $item['menu_id'], $item['qty'], $item['harga'], $item['catatan'] ?? '']);
        }

        // Update meja
        if ($tipe === 'dine_in' && $meja_id) {
            $pdo->prepare("UPDATE meja SET status='terisi' WHERE id=?")->execute([$meja_id]);
        }

        // --- SMART LOGIC: Deduct Bahan Baku (Best-effort, tidak block order) ---
        foreach ($items as $item) {
            $qty     = (int)$item['qty'];
            $menu_id = (int)$item['menu_id'];

            // Ambil resep (jika ada)
            $stmtResep = $pdo->prepare("SELECT bahan_id, jumlah_dibutuhkan FROM resep_menu WHERE menu_id = ?");
            $stmtResep->execute([$menu_id]);
            $resepList = $stmtResep->fetchAll();

            foreach ($resepList as $res) {
                $total_dibutuhkan = $res['jumlah_dibutuhkan'] * $qty;
                // Kurangi stok jika mencukupi; skip jika tidak (order tetap jalan)
                $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang - ? WHERE id = ? AND stok_sekarang >= ?")
                    ->execute([$total_dibutuhkan, $res['bahan_id'], $total_dibutuhkan]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Pesanan $nomor berhasil dikirim ke dapur!";
        header('Location: order.php'); exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Gagal membuat pesanan: " . $e->getMessage();
        header('Location: order.php'); exit;
    }
}

// Sekarang baru aman load header (output HTML)
requireRole(['waiter', 'admin']);

require_once __DIR__ . '/../../includes/header.php';
// Fetch data
$kategoris = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
$menusRaw  = $pdo->query("SELECT m.*, k.nama_kategori FROM menu m JOIN kategori k ON m.kategori_id=k.id WHERE m.status='tersedia' ORDER BY k.nama_kategori, m.nama_menu")->fetchAll();
$mejas     = $pdo->query("SELECT * FROM meja ORDER BY nomor_meja")->fetchAll();

// --- SMART LOGIC: Filter menu berdasarkan ketersediaan bahan baku ---
$menus = [];
foreach ($menusRaw as $m) {
    $stmtCheck = $pdo->prepare("
        SELECT r.jumlah_dibutuhkan, b.stok_sekarang 
        FROM resep_menu r 
        JOIN bahan_baku b ON r.bahan_id = b.id 
        WHERE r.menu_id = ?
    ");
    $stmtCheck->execute([$m['id']]);
    $reseps = $stmtCheck->fetchAll();
    
    $is_available = true;
    foreach ($reseps as $r) {
        if ($r['stok_sekarang'] < $r['jumlah_dibutuhkan']) {
            $is_available = false;
            break;
        }
    }
    
    if ($is_available) {
        $menus[] = $m;
    }
}

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="posApp()" class="h-full flex flex-col lg:flex-row gap-6">

    <!-- LEFT: Menu Browser -->
    <div class="flex-1 flex flex-col overflow-hidden min-h-0">
        <!-- Search & Filter -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" x-model="search" placeholder="Search menu..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none text-sm font-medium">
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button @click="activeKat='all'" class="px-3 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-colors" :class="activeKat==='all' ? 'bg-theme-evergreen text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">All</button>
                <?php foreach($kategoris as $k): ?>
                <button @click="activeKat='<?= $k['id'] ?>'" class="px-3 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-colors" :class="activeKat==='<?= $k['id'] ?>' ? 'bg-theme-evergreen text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'"><?= htmlspecialchars($k['nama_kategori']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Menu Grid (scrollable) -->
        <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach($menus as $m): ?>
                <div x-show="isVisible('<?= $m['id'] ?>', '<?= $m['kategori_id'] ?>', '<?= addslashes($m['nama_menu']) ?>')"
                     @click="addToCart(<?= htmlspecialchars(json_encode($m)) ?>)"
                     class="group bg-white rounded-2xl border border-gray-100 overflow-hidden cursor-pointer hover:border-theme-sage hover:shadow-lg hover:-translate-y-1 transition-all duration-300 select-none active:scale-95">
                    <div class="h-28 bg-theme-bg relative overflow-hidden">
                        <?php if($m['gambar'] && file_exists(__DIR__ . '/../../assets/images/' . $m['gambar'])): ?>
                            <img src="<?= BASE_URL ?>/assets/images/<?= $m['gambar'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-4xl">🍽️</div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-theme-leaf/0 group-hover:bg-theme-leaf/10 transition-colors flex items-center justify-center">
                            <div class="w-8 h-8 rounded-full bg-white/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow">
                                <svg class="w-4 h-4 text-theme-leaf" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="font-bold text-theme-evergreen text-xs truncate mb-1"><?= htmlspecialchars($m['nama_menu']) ?></div>
                        <div class="font-extrabold text-theme-sage text-sm"><?= formatRupiah($m['harga']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart & Checkout -->
    <div class="w-full lg:w-[360px] flex flex-col bg-white rounded-3xl border border-gray-100 shadow-[0_4px_24px_rgba(0,0,0,0.04)] flex-shrink-0 overflow-hidden">

        <!-- Order Type Selector -->
        <div class="p-5 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Order Type</h3>
            <div class="grid grid-cols-2 gap-2">
                <button @click="orderType='dine_in'" class="py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all" :class="orderType==='dine_in' ? 'bg-theme-evergreen text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-500 hover:bg-gray-50'">
                    🪑 Dine In
                </button>
                <button @click="orderType='take_away'; selectedMeja=null" class="py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all" :class="orderType==='take_away' ? 'bg-theme-evergreen text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-500 hover:bg-gray-50'">
                    🛍️ Take Away
                </button>
            </div>

            <!-- Pilih Meja (jika dine in) -->
            <div x-show="orderType==='dine_in'" class="mt-3">
                <label class="text-xs font-bold text-gray-500 mb-2 block">Select Table</label>
                <div class="grid grid-cols-4 gap-1.5">
                    <?php foreach($mejas as $meja): ?>
                    <button @click="selectedMeja=<?= $meja['id'] ?>" 
                            :disabled="<?= $meja['status']==='terisi' ? 'true' : 'false' ?>"
                            class="py-2 rounded-lg text-xs font-bold transition-colors"
                            :class="selectedMeja===<?= $meja['id'] ?> ? 'bg-theme-sage text-white' : '<?= $meja['status']==='terisi' ? 'bg-red-50 text-red-300 cursor-not-allowed' : 'bg-gray-100 text-gray-600 hover:bg-theme-bg hover:text-theme-leaf' ?>'">
                        <?= $meja['nomor_meja'] ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Nama Pelanggan (Take Away) -->
            <div x-show="orderType==='take_away'" class="mt-3">
                <label class="text-xs font-bold text-gray-500 mb-2 block">Customer Name</label>
                <input type="text" x-model="customerName" placeholder="Enter name..." class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-theme-sage font-medium">
            </div>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-5">
            <div x-show="cart.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 py-8">
                <div class="text-5xl mb-3">🛒</div>
                <p class="font-medium text-sm">Cart is empty</p>
                <p class="text-xs">Tap menu items to add them</p>
            </div>

            <div class="space-y-3">
                <template x-for="(item, idx) in cart" :key="idx">
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                        <div class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-theme-evergreen text-sm truncate" x-text="item.nama_menu"></div>
                                <div class="text-theme-sage text-xs font-semibold" x-text="formatRupiah(item.harga)"></div>
                            </div>
                            <button @click="removeFromCart(idx)" class="text-red-400 hover:text-red-600 p-1 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <div class="flex items-center gap-2">
                                <button @click="changeQty(idx, -1)" class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-theme-bg hover:border-theme-sage transition-colors font-bold text-sm">-</button>
                                <span class="font-bold text-theme-evergreen w-6 text-center text-sm" x-text="item.qty"></span>
                                <button @click="changeQty(idx, 1)" class="w-6 h-6 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-theme-bg hover:border-theme-sage transition-colors font-bold text-sm">+</button>
                            </div>
                            <div class="font-extrabold text-theme-leaf text-sm" x-text="formatRupiah(item.harga * item.qty)"></div>
                        </div>
                        <!-- Note -->
                        <input type="text" x-model="item.catatan" placeholder="Special notes (optional)..." class="w-full mt-2 px-2.5 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-theme-sage transition-colors">
                    </div>
                </template>
            </div>
        </div>

        <!-- Total & Submit -->
        <div class="p-5 border-t border-gray-100 bg-white">
            <div class="space-y-1.5 mb-4 text-sm">
                <div class="flex justify-between items-center text-gray-500">
                    <span>Subtotal</span>
                    <span class="font-bold" x-text="formatRupiah(subtotal)"></span>
                </div>
                <template x-if="serviceRate > 0">
                    <div class="flex justify-between items-center text-gray-500">
                        <span x-text="'Service Charge (' + serviceRate + '%)'"></span>
                        <span class="font-bold" x-text="formatRupiah(serviceAmount)"></span>
                    </div>
                </template>
                <template x-if="taxRate > 0">
                    <div class="flex justify-between items-center text-gray-500">
                        <span x-text="'PB1 Tax (' + taxRate + '%)'"></span>
                        <span class="font-bold" x-text="formatRupiah(taxAmount)"></span>
                    </div>
                </template>
                <div class="flex justify-between items-center pt-3 border-t border-dashed border-gray-200 mt-2">
                    <span class="font-medium text-gray-400 uppercase tracking-wider text-xs">Grand Total</span>
                    <span class="text-2xl font-extrabold text-theme-evergreen" x-text="formatRupiah(grandTotal)"></span>
                </div>
            </div>

            <form method="POST" @submit.prevent="submitOrder($el)">
                <input type="hidden" name="action" value="submit_order">
                <input type="hidden" name="tipe_pesanan" :value="orderType">
                <input type="hidden" name="meja_id" :value="selectedMeja">
                <input type="hidden" name="nama_pelanggan" :value="customerName">
                <input type="hidden" name="items_json" :value="JSON.stringify(cart)">

                <button type="submit" :disabled="!canSubmit()"
                        class="w-full py-4 rounded-2xl font-bold text-base transition-all flex items-center justify-center gap-2"
                        :class="canSubmit() ? 'bg-theme-sage text-white hover:bg-theme-leaf shadow-lg shadow-theme-sage/30' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    Send to Kitchen
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('posApp', () => ({
        cart: JSON.parse(localStorage.getItem('beanpay_cart') || '[]'),
        orderType: 'dine_in',
        selectedMeja: null,
        customerName: '',
        search: '',
        activeKat: 'all',
        
        taxRate: <?= $pajakPersen ?>,
        serviceRate: <?= $servicePersen ?>,

        get subtotal() {
            return this.cart.reduce((s, i) => s + i.harga * i.qty, 0);
        },
        get serviceAmount() {
            return (this.subtotal * this.serviceRate) / 100;
        },
        get taxAmount() {
            return ((this.subtotal + this.serviceAmount) * this.taxRate) / 100;
        },
        get grandTotal() {
            return this.subtotal + this.serviceAmount + this.taxAmount;
        },

        addToCart(menu) {
            const existing = this.cart.find(i => i.menu_id == menu.id);
            if (existing) {
                existing.qty++;
            } else {
                this.cart.push({ menu_id: menu.id, nama_menu: menu.nama_menu, harga: parseFloat(menu.harga), qty: 1, catatan: '' });
            }
            this.saveCart();
        },

        removeFromCart(idx) { this.cart.splice(idx, 1); this.saveCart(); },

        changeQty(idx, delta) {
            this.cart[idx].qty += delta;
            if (this.cart[idx].qty <= 0) this.cart.splice(idx, 1);
            this.saveCart();
        },

        saveCart() { localStorage.setItem('beanpay_cart', JSON.stringify(this.cart)); },

        isVisible(id, katId, nama) {
            const matchKat = this.activeKat === 'all' || this.activeKat === katId;
            const matchSearch = nama.toLowerCase().includes(this.search.toLowerCase());
            return matchKat && matchSearch;
        },

        canSubmit() {
            if (this.cart.length === 0) return false;
            if (this.orderType === 'dine_in' && !this.selectedMeja) return false;
            return true;
        },

        submitOrder(form) {
            if (!this.canSubmit()) return;
            Swal.fire({
                title: 'Kirim Pesanan?',
                text: `${this.cart.length} item • ${this.formatRupiah(this.grandTotal)}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#709255',
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'Ya, Kirim!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    localStorage.removeItem('beanpay_cart');
                    form.submit();
                }
            });
        },

        formatRupiah(angka) {
            return 'Rp ' + parseInt(angka || 0).toLocaleString('id-ID');
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

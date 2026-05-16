<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

// Jika ada AJAX request untuk mengambil detail pesanan (HARUS SEBELUM HEADER.PHP KARENA OUTPUT JSON)
if (isset($_GET['get_order_details']) && isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    
    // Header order
    $stmtO = $pdo->prepare("SELECT p.*, m.nomor_meja FROM pesanan p LEFT JOIN meja m ON p.meja_id = m.id WHERE p.id = ?");
    $stmtO->execute([$orderId]);
    $order = $stmtO->fetch();
    
    // Items
    $stmtI = $pdo->prepare("SELECT d.*, m.nama_menu FROM detail_pesanan d JOIN menu m ON d.menu_id = m.id WHERE d.pesanan_id = ?");
    $stmtI->execute([$orderId]);
    $items = $stmtI->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['order' => $order, 'items' => $items]);
    exit;
}

// AJAX: Ambil semua pesanan aktif (Real-time polling)
if (isset($_GET['get_orders']) && $_GET['get_orders'] == 1) {
    session_write_close();
    $stmtOrders = $pdo->query("SELECT p.*, m.nomor_meja FROM pesanan p LEFT JOIN meja m ON p.meja_id = m.id WHERE p.status_pesanan IN ('pending', 'diproses', 'selesai') ORDER BY p.waktu_pesan DESC");
    $orders = $stmtOrders->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['orders' => $orders]);
    exit;
}

$page_title = 'Cashier Station';
require_once __DIR__ . '/../../includes/header.php';

// Cek sesi kasir yang sedang aktif untuk user ini
$stmt = $pdo->prepare("SELECT * FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$activeSession = $stmt->fetch();

// Ambil semua pesanan yang belum dibayar
$stmtOrders = $pdo->query("SELECT p.*, m.nomor_meja FROM pesanan p LEFT JOIN meja m ON p.meja_id = m.id WHERE p.status_pesanan IN ('pending', 'diproses', 'selesai') ORDER BY p.waktu_pesan DESC");
$pendingOrders = $stmtOrders->fetchAll();



require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="cashierApp()" class="h-full flex flex-col md:flex-row gap-6">

    <!-- OVERLAY BUKA SHIFT (Jika belum buka shift) -->
    <?php if (!$activeSession): ?>
    <div class="fixed inset-0 z-50 bg-theme-evergreen/40 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl animate-[fadeIn_0.3s_ease-out_forwards]">
            <div class="w-16 h-16 bg-theme-sage/20 text-theme-sage rounded-2xl flex items-center justify-center mb-6 mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-center text-theme-evergreen mb-2">Open Register</h2>
            <p class="text-center text-gray-500 text-sm mb-8">Enter your opening cash balance to start the shift.</p>
            
            <form action="proses_buka_sesi.php" method="POST">
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Opening Balance (Modal Awal)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">Rp</span>
                        <input type="number" name="modal_awal" required min="0" class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/50 focus:border-theme-sage transition-all font-bold text-lg text-theme-evergreen" placeholder="0">
                    </div>
                </div>
                <button type="submit" class="w-full py-3.5 bg-theme-evergreen text-white font-bold rounded-xl hover:bg-theme-leaf transition-colors shadow-lg shadow-theme-evergreen/30">
                    Start Shift
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- LEFT PANEL: Daftar Pesanan -->
    <div class="flex-1 flex flex-col overflow-hidden bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] relative">
        <!-- Header Panel Kiri -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white z-10 rounded-t-3xl">
            <div>
                <h3 class="text-lg font-bold text-theme-evergreen">Active Orders</h3>
                <p class="text-sm text-gray-400">Select an order to process payment</p>
            </div>
            <div class="px-3 py-1 bg-theme-sage/10 text-theme-leaf rounded-lg font-bold text-sm">
                <span x-text="pendingOrders.length"></span> Orders
            </div>
        </div>
        
        <!-- List Pesanan Scrollable -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50/30">
            <template x-if="pendingOrders.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-gray-400">
                    <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <p class="font-medium">No active orders</p>
                </div>
            </template>
            
            <template x-if="pendingOrders.length > 0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-for="ord in pendingOrders" :key="ord.id">
                        <div @click="loadOrder(ord.id)" 
                             class="group bg-white border border-gray-100 rounded-2xl p-5 cursor-pointer hover:border-theme-sage hover:shadow-lg transition-all duration-300 relative overflow-hidden"
                             :class="{'ring-2 ring-theme-sage border-theme-sage shadow-md': selectedOrderId === ord.id}">
                            
                            <!-- Decorative background blob -->
                            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-theme-bg rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
                            
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1" x-text="ord.nomor_pesanan"></div>
                                    <div class="text-xl font-extrabold text-theme-evergreen" x-text="ord.tipe_pesanan === 'dine_in' ? 'Meja ' + ord.nomor_meja : 'Take Away'"></div>
                                </div>
                                <span class="w-3 h-3 rounded-full" :class="ord.status_pesanan === 'selesai' ? 'bg-theme-sage animate-pulse' : 'bg-amber-400'"></span>
                            </div>
                            
                            <div class="text-sm font-medium text-gray-500 mb-4 relative z-10 truncate" x-text="ord.nama_pelanggan || 'Guest'"></div>
                            
                            <div class="flex justify-between items-center pt-3 border-t border-gray-50 border-dashed relative z-10">
                                <div class="font-bold text-theme-leaf text-lg" x-text="formatRupiah(ord.total_harga)"></div>
                                <div class="text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-500 capitalize" x-text="ord.status_pesanan"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- RIGHT PANEL: Checkout -->
    <div class="w-full md:w-[400px] flex flex-col bg-white rounded-3xl border border-gray-100 shadow-[0_4px_24px_rgba(0,0,0,0.04)] overflow-hidden flex-shrink-0 max-h-[calc(100vh-120px)]">
        
        <!-- Empty State (Jika belum pilih pesanan) -->
        <div x-show="!selectedOrder" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-gray-400">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-600 mb-1">Select an Order</h3>
            <p class="text-sm">Choose an active order from the list to process payment.</p>
        </div>

        <!-- Checkout Content -->
        <template x-if="selectedOrder">
            <div class="flex flex-col h-full overflow-y-auto animate-[fadeIn_0.3s_ease-out_forwards]">
                <!-- Header Order Info -->
                <div class="p-6 bg-theme-evergreen text-white">
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-sm text-theme-sage font-medium tracking-wider" x-text="selectedOrder.nomor_pesanan"></div>
                        <div class="text-xs px-2 py-1 rounded bg-white/10" x-text="formatDate(selectedOrder.waktu_pesan)"></div>
                    </div>
                    <div class="text-2xl font-bold" x-text="selectedOrder.tipe_pesanan === 'dine_in' ? 'Table ' + selectedOrder.nomor_meja : 'Take Away'"></div>
                    <div class="text-sm text-white/70 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span x-text="selectedOrder.nama_pelanggan || 'Guest'"></span>
                    </div>
                </div>

                <!-- Items List -->
                <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Order Items</h4>
                    <div class="space-y-4">
                        <template x-for="item in orderItems" :key="item.id">
                            <div class="flex justify-between">
                                <div class="flex gap-3">
                                    <div class="font-bold text-theme-sage" x-text="item.qty + 'x'"></div>
                                    <div>
                                        <div class="font-bold text-theme-evergreen text-sm" x-text="item.nama_menu"></div>
                                        <div x-show="item.catatan" class="text-xs text-gray-400 mt-0.5 italic" x-text="item.catatan"></div>
                                    </div>
                                </div>
                                <div class="font-bold text-gray-600 text-sm" x-text="formatRupiah(item.harga_satuan * item.qty)"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="p-6 bg-white border-t border-gray-100 shadow-[0_-10px_30px_rgba(0,0,0,0.02)]">
                    
                    <!-- Promo Code Input -->
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">Promo Voucher</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="promoCode" placeholder="Masukkan kode..." :disabled="appliedPromo"
                                   style="text-transform: uppercase;"
                                   class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-theme-sage font-medium text-sm">
                            <button type="button" @click="applyPromo()" x-show="!appliedPromo"
                                    class="px-4 py-2 bg-theme-evergreen text-white font-bold text-sm rounded-lg hover:bg-theme-leaf transition-colors">Terapkan</button>
                            <button type="button" @click="removePromo()" x-show="appliedPromo"
                                    class="px-4 py-2 bg-red-50 text-red-600 font-bold text-sm rounded-lg hover:bg-red-100 transition-colors">Hapus</button>
                        </div>
                        <p class="text-xs mt-1" :class="promoMessageClass" x-text="promoMessage"></p>
                    </div>

                    <!-- Breakdown -->
                    <div class="space-y-1.5 mb-4 text-sm border-t border-dashed border-gray-200 pt-4">
                        <div class="flex justify-between text-gray-500">
                            <span>Subtotal</span>
                            <span class="font-bold" x-text="formatRupiah(selectedOrder.subtotal)"></span>
                        </div>
                        <div class="flex justify-between text-theme-leaf font-bold" x-show="appliedPromo">
                            <span>Diskon Promo</span>
                            <span x-text="'- ' + formatRupiah(calculatedDiscount)"></span>
                        </div>
                        <div class="flex justify-between text-gray-500" x-show="selectedOrder.service_persen > 0">
                            <span x-text="'Service Charge (' + selectedOrder.service_persen + '%)'"></span>
                            <span class="font-bold" x-text="formatRupiah(calculatedService)"></span>
                        </div>
                        <div class="flex justify-between text-gray-500" x-show="selectedOrder.pajak_persen > 0">
                            <span x-text="'PB1 Tax (' + selectedOrder.pajak_persen + '%)'"></span>
                            <span class="font-bold" x-text="formatRupiah(calculatedTax)"></span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-6 border-t border-gray-100 pt-4">
                        <span class="text-gray-500 font-bold uppercase tracking-wider text-xs">Grand Total</span>
                        <span class="text-3xl font-extrabold text-theme-evergreen" x-text="formatRupiah(calculatedGrandTotal)"></span>
                    </div>

                    <!-- Payment Methods -->
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <button @click="setPaymentMethod('cash')" 
                                class="py-3 px-2 rounded-xl border-2 flex flex-col items-center justify-center gap-2 transition-all"
                                :class="paymentMethod === 'cash' ? 'border-theme-sage bg-theme-bg text-theme-leaf' : 'border-gray-100 text-gray-500 hover:border-gray-200 hover:bg-gray-50'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span class="text-xs font-bold">Cash</span>
                        </button>
                        <button @click="setPaymentMethod('qris')" 
                                class="py-3 px-2 rounded-xl border-2 flex flex-col items-center justify-center gap-2 transition-all"
                                :class="paymentMethod === 'qris' ? 'border-theme-sage bg-theme-bg text-theme-leaf' : 'border-gray-100 text-gray-500 hover:border-gray-200 hover:bg-gray-50'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                            <span class="text-xs font-bold">QRIS</span>
                        </button>
                        <button @click="setPaymentMethod('debit')" 
                                class="py-3 px-2 rounded-xl border-2 flex flex-col items-center justify-center gap-2 transition-all"
                                :class="paymentMethod === 'debit' ? 'border-theme-sage bg-theme-bg text-theme-leaf' : 'border-gray-100 text-gray-500 hover:border-gray-200 hover:bg-gray-50'">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            <span class="text-xs font-bold">Debit</span>
                        </button>
                    </div>

                    <!-- Cash Input -->
                    <div x-show="paymentMethod === 'cash'" x-collapse>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-400 mb-1">Amount Received</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">Rp</span>
                                <input type="number" x-model.number="amountReceived" class="w-full pl-10 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-theme-sage font-bold text-theme-evergreen">
                            </div>
                            
                            <!-- Quick Cash Buttons -->
                            <div class="grid grid-cols-3 gap-2 mt-2">
                                <button type="button" @click="amountReceived = calculatedGrandTotal" class="py-1.5 px-2 bg-gray-100 hover:bg-theme-bg hover:text-theme-leaf border border-transparent hover:border-theme-sage/30 rounded-lg text-xs font-bold text-gray-600 transition-colors">
                                    Uang Pas
                                </button>
                                <template x-for="amount in getQuickCashOptions()" :key="amount">
                                    <button type="button" @click="amountReceived = amount" 
                                            class="py-1.5 px-2 bg-gray-100 hover:bg-theme-bg hover:text-theme-leaf border border-transparent hover:border-theme-sage/30 rounded-lg text-xs font-bold text-gray-600 transition-colors"
                                            x-text="formatRupiah(amount).replace('Rp ', '')">
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mb-6 p-3 rounded-lg" :class="changeAmount >= 0 ? 'bg-theme-bg text-theme-leaf' : 'bg-red-50 text-red-500'">
                            <span class="font-bold text-sm">Change</span>
                            <span class="font-extrabold" x-text="formatRupiah(Math.max(0, changeAmount))"></span>
                        </div>
                    </div>

                    <form action="proses_bayar.php" method="POST" id="formPayment">
                        <input type="hidden" name="pesanan_id" :value="selectedOrder.id">
                        <input type="hidden" name="metode_pembayaran" :value="paymentMethod">
                        <input type="hidden" name="jumlah_bayar" :value="paymentMethod === 'cash' ? amountReceived : calculatedGrandTotal">
                        <input type="hidden" name="promo_id" :value="appliedPromo ? appliedPromo.id : ''">
                        
                        <button type="button" @click="processPayment()" 
                                :disabled="!isPaymentValid()"
                                class="w-full py-4 rounded-2xl font-bold text-lg transition-all flex justify-center items-center gap-2"
                                :class="isPaymentValid() ? 'bg-theme-sage text-white hover:bg-theme-leaf shadow-lg shadow-theme-sage/30' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                            <span>Pay</span>
                            <span x-text="formatRupiah(calculatedGrandTotal)"></span>
                            <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cashierApp', () => ({
        pendingOrders: <?= json_encode($pendingOrders) ?>,
        selectedOrderId: null,
        selectedOrder: null,
        orderItems: [],
        paymentMethod: null,
        amountReceived: 0,
        
        promoCode: '',
        appliedPromo: null,
        promoMessage: '',
        promoMessageClass: '',
        pollTimer: null,

        init() {
            this.startPolling();
        },

        async fetchOrders() {
            try {
                const res = await fetch('index.php?get_orders=1');
                if (!res.ok) return;
                const data = await res.json();
                this.pendingOrders = data.orders;
            } catch (err) {
                console.error('Fetch orders error:', err);
            }
        },

        startPolling() {
            this.pollTimer = setInterval(() => this.fetchOrders(), 5000);
        },

        async loadOrder(id) {
            this.selectedOrderId = id;
            this.paymentMethod = null;
            this.removePromo();
            
            try {
                const response = await fetch(`index.php?get_order_details=1&order_id=${id}`);
                const data = await response.json();
                this.selectedOrder = data.order;
                this.orderItems = data.items;
                this.amountReceived = parseFloat(data.order.total_harga);
            } catch (err) { console.error(err); }
        },
        
        get calculatedDiscount() {
            if (!this.appliedPromo || !this.selectedOrder) return 0;
            return parseFloat(this.appliedPromo.diskon_nominal);
        },
        
        get calculatedService() {
            if (!this.selectedOrder) return 0;
            const subtotalBersih = parseFloat(this.selectedOrder.subtotal) - this.calculatedDiscount;
            return (subtotalBersih * parseFloat(this.selectedOrder.service_persen)) / 100;
        },
        
        get calculatedTax() {
            if (!this.selectedOrder) return 0;
            const subtotalBersih = parseFloat(this.selectedOrder.subtotal) - this.calculatedDiscount;
            return ((subtotalBersih + this.calculatedService) * parseFloat(this.selectedOrder.pajak_persen)) / 100;
        },
        
        get calculatedGrandTotal() {
            if (!this.selectedOrder) return 0;
            const subtotalBersih = parseFloat(this.selectedOrder.subtotal) - this.calculatedDiscount;
            return subtotalBersih + this.calculatedService + this.calculatedTax;
        },

        async applyPromo() {
            if (!this.promoCode.trim()) return;
            
            this.promoMessage = 'Memvalidasi...';
            this.promoMessageClass = 'text-gray-500';
            
            try {
                const fd = new FormData();
                fd.append('kode_promo', this.promoCode);
                fd.append('subtotal', this.selectedOrder.subtotal);
                
                const res = await fetch('../../api/cek_promo.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    this.appliedPromo = {
                        id: data.promo_id,
                        diskon_nominal: data.diskon_nominal
                    };
                    this.promoMessage = data.message;
                    this.promoMessageClass = 'text-theme-leaf';
                    this.amountReceived = this.calculatedGrandTotal;
                } else {
                    this.promoMessage = data.message;
                    this.promoMessageClass = 'text-red-500';
                }
            } catch (err) {
                this.promoMessage = 'Terjadi kesalahan jaringan.';
                this.promoMessageClass = 'text-red-500';
            }
        },

        removePromo() {
            this.appliedPromo = null;
            this.promoCode = '';
            this.promoMessage = '';
            if(this.selectedOrder) this.amountReceived = this.calculatedGrandTotal;
        },
        
        setPaymentMethod(method) {
            this.paymentMethod = method;
            if (method !== 'cash') {
                this.amountReceived = this.calculatedGrandTotal;
            }
        },
        
        get changeAmount() {
            if (!this.selectedOrder || this.paymentMethod !== 'cash') return 0;
            return this.amountReceived - this.calculatedGrandTotal;
        },
        
        isPaymentValid() {
            if (!this.paymentMethod) return false;
            if (this.paymentMethod === 'cash') {
                return this.amountReceived >= this.calculatedGrandTotal;
            }
            return true;
        },
        
        processPayment() {
            if(this.isPaymentValid()) {
                document.getElementById('formPayment').submit();
            }
        },
        
        formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        },
        
        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },
        
        getQuickCashOptions() {
            const total = this.calculatedGrandTotal;
            if (total <= 0) return [];
            
            let options = [];
            
            // Base denominations in IDR
            const denoms = [20000, 50000, 100000, 150000, 200000, 300000, 500000];
            
            // Find the next logical denominations greater than total
            for (let d of denoms) {
                if (d > total) {
                    options.push(d);
                    if (options.length >= 2) break;
                }
            }
            
            // If total is very large (e.g. 1.2M), add rounded up to next 50k/100k
            if (options.length === 0) {
                options.push(Math.ceil(total / 50000) * 50000);
                options.push(Math.ceil(total / 100000) * 100000);
            }
            
            // Remove duplicates
            return [...new Set(options)].slice(0, 2);
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

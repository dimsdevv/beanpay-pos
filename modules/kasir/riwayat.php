<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

$page_title = 'Transaction History';
require_once __DIR__ . '/../../includes/header.php';

// Sesi aktif (jika ada)
$stmtSesi = $pdo->prepare("SELECT s.*, u.nama_lengkap FROM sesi_kasir s JOIN users u ON s.kasir_id = u.id WHERE s.kasir_id = ? AND s.status = 'buka' LIMIT 1");
$stmtSesi->execute([$_SESSION['user_id']]);
$activeSesi = $stmtSesi->fetch();

// Total pemasukan sesi aktif
$totalSesiIni = 0;
$totalTransaksiSesiIni = 0;
if ($activeSesi) {
    $stmtT = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah_bayar),0) as total FROM pembayaran WHERE sesi_kasir_id = ?");
    $stmtT->execute([$activeSesi['id']]);
    $sesiStats = $stmtT->fetch();
    $totalSesiIni = $sesiStats['total'];
    $totalTransaksiSesiIni = $sesiStats['cnt'];
}

// Transaksi hari ini
$stmtTrx = $pdo->prepare("
    SELECT b.*, p.nomor_pesanan, p.tipe_pesanan, p.nama_pelanggan, m.nomor_meja
    FROM pembayaran b
    JOIN pesanan p ON b.pesanan_id = p.id
    LEFT JOIN meja m ON p.meja_id = m.id
    WHERE b.sesi_kasir_id = ?
    ORDER BY b.waktu_bayar DESC
");
$stmtTrx->execute([$activeSesi['id'] ?? 0]);
$transaksis = $stmtTrx->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="{ showTutupModal: false }" class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen">Transaction History</h1>
            <p class="text-gray-500 mt-0.5">Current shift transactions and summary.</p>
        </div>
        <?php if($activeSesi): ?>
        <button @click="showTutupModal = true" class="flex items-center gap-2 px-5 py-2.5 bg-red-500 text-white rounded-xl font-bold hover:bg-red-600 transition-colors shadow-md shadow-red-500/30 w-full sm:w-auto justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"></path></svg>
            Close Shift
        </button>
        <?php endif; ?>
    </div>

    <!-- Active Shift Info -->
    <?php if($activeSesi): ?>
    <div class="bg-gradient-to-br from-theme-evergreen to-theme-leaf text-white rounded-3xl p-7 shadow-lg relative overflow-hidden">
        <div class="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -translate-y-1/4 translate-x-1/4 blur-2xl"></div>
        <div class="relative z-10">
            <div class="text-theme-sage text-sm font-bold uppercase tracking-widest mb-4">Active Shift</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <div class="text-white/60 text-xs mb-1">Opened At</div>
                    <div class="font-bold text-lg"><?= date('H:i', strtotime($activeSesi['waktu_buka'])) ?></div>
                    <div class="text-white/70 text-sm"><?= date('d M Y', strtotime($activeSesi['waktu_buka'])) ?></div>
                </div>
                <div>
                    <div class="text-white/60 text-xs mb-1">Opening Balance</div>
                    <div class="font-extrabold text-2xl"><?= formatRupiah($activeSesi['modal_awal']) ?></div>
                </div>
                <div>
                    <div class="text-white/60 text-xs mb-1">Revenue This Shift</div>
                    <div class="font-extrabold text-2xl text-theme-muted-olive"><?= formatRupiah($totalSesiIni) ?></div>
                    <div class="text-white/70 text-xs mt-0.5"><?= $totalTransaksiSesiIni ?> transactions</div>
                </div>
            </div>
            <div class="mt-6 pt-5 border-t border-white/10 flex justify-between items-center">
                <div class="text-white/60 text-sm">Expected cash in drawer</div>
                <div class="font-extrabold text-xl"><?= formatRupiah($activeSesi['modal_awal'] + $totalSesiIni) ?></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl p-8 text-center text-gray-400">
        <div class="text-4xl mb-3">🔒</div>
        <p class="font-semibold">No active shift.</p>
        <p class="text-sm mt-1">Open a shift from the <a href="index.php" class="text-theme-sage font-bold hover:underline">Cashier page</a> to start tracking transactions.</p>
    </div>
    <?php endif; ?>

    <!-- Transactions Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h2 class="font-bold text-theme-evergreen text-lg">Today's Transactions</h2>
            <span class="text-sm text-gray-400"><?= count($transaksis) ?> records</span>
        </div>

        <?php if(count($transaksis) === 0): ?>
        <div class="p-12 text-center text-gray-400">
            <div class="text-4xl mb-3">📋</div>
            <p class="font-medium">No transactions yet in this shift</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-xs font-bold text-gray-400 uppercase tracking-widest">
                        <th class="px-6 py-4 text-left">Order</th>
                        <th class="px-6 py-4 text-left">Time</th>
                        <th class="px-6 py-4 text-left">Method</th>
                        <th class="px-6 py-4 text-right">Amount</th>
                        <th class="px-6 py-4 text-right">Change</th>
                        <th class="px-6 py-4 text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach($transaksis as $t): ?>
                    <?php $metodeBadge = ['cash'=>'bg-theme-bg text-theme-leaf','qris'=>'bg-purple-50 text-purple-600','debit'=>'bg-blue-50 text-blue-600'][$t['metode_pembayaran']] ?? 'bg-gray-100 text-gray-600'; ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-theme-evergreen text-sm"><?= $t['nomor_pesanan'] ?></div>
                            <div class="text-xs text-gray-400"><?= $t['tipe_pesanan'] === 'dine_in' ? 'Table '.$t['nomor_meja'] : 'Take Away' ?> • <?= htmlspecialchars($t['nama_pelanggan'] ?: 'Guest') ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 font-medium"><?= date('H:i', strtotime($t['waktu_bayar'])) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase <?= $metodeBadge ?>"><?= $t['metode_pembayaran'] ?></span>
                        </td>
                        <td class="px-6 py-4 text-right font-extrabold text-theme-evergreen"><?= formatRupiah($t['jumlah_bayar']) ?></td>
                        <td class="px-6 py-4 text-right font-bold text-gray-500"><?= formatRupiah($t['kembalian']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="struk.php?pesanan_id=<?= $t['pesanan_id'] ?>" target="_blank" class="px-3 py-1.5 text-xs font-bold text-theme-sage border border-theme-sage/30 rounded-lg hover:bg-theme-bg transition-colors">Print</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-theme-bg">
                        <td colspan="3" class="px-6 py-4 font-bold text-theme-evergreen text-right text-sm uppercase tracking-wide">Total Shift Revenue</td>
                        <td class="px-6 py-4 text-right font-extrabold text-theme-leaf text-lg"><?= formatRupiah($totalSesiIni) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

<!-- Modal Tutup Shift -->
<?php if($activeSesi): ?>
<div x-show="showTutupModal" @keydown.escape.window="showTutupModal=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
     x-transition style="display:none">
    <div @click.stop class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-red-100 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-theme-evergreen">Close Shift?</h3>
            <p class="text-gray-500 text-sm mt-2">This will end your current session and lock the cashier.</p>
        </div>

        <div class="bg-gray-50 rounded-2xl p-4 mb-6 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Opening Balance</span>
                <span class="font-bold"><?= formatRupiah($activeSesi['modal_awal']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Revenue</span>
                <span class="font-bold text-theme-leaf"><?= formatRupiah($totalSesiIni) ?></span>
            </div>
            <div class="border-t border-dashed border-gray-300 pt-2 flex justify-between">
                <span class="font-bold text-gray-700">Expected in Drawer</span>
                <span class="font-extrabold text-theme-evergreen"><?= formatRupiah($activeSesi['modal_awal'] + $totalSesiIni) ?></span>
            </div>
        </div>

        <form action="tutup_shift.php" method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="sesi_id" value="<?= $activeSesi['id'] ?>">
            
            <div x-data="{ expected: <?= $activeSesi['modal_awal'] + $totalSesiIni ?>, actual: '' }">
                <label class="block text-sm font-bold text-gray-700 mb-2">Uang Fisik di Laci</label>
                <div class="relative mb-3">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">Rp</span>
                    <input type="number" name="uang_fisik" x-model.number="actual" required min="0" class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/50 focus:border-theme-sage transition-all font-bold text-lg text-theme-evergreen" placeholder="0">
                </div>
                
                <div x-show="actual !== '' && actual !== null" class="p-3 rounded-lg text-sm font-bold flex justify-between"
                     :class="actual == expected ? 'bg-green-50 text-green-600' : (actual < expected ? 'bg-red-50 text-red-600' : 'bg-yellow-50 text-yellow-600')">
                    <span>Selisih (Discrepancy)</span>
                    <span x-text="(actual - expected) >= 0 ? '+' + formatRupiah(actual - expected) : formatRupiah(actual - expected)"></span>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" @click="showTutupModal=false" class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 shadow-md shadow-red-500/30 transition-colors">Tutup Shift</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

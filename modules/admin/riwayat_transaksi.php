<?php
$page_title = 'Riwayat Transaksi';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);

ensureTransferColumns();

$filterStart = $_GET['start'] ?? date('Y-m-01');
$filterEnd = $_GET['end'] ?? date('Y-m-d');
$filterKasir = (int)($_GET['kasir'] ?? 0);
$filterMetode = $_GET['metode'] ?? '';
$searchQ = trim($_GET['q'] ?? '');

// Fetch all kasir for filter
$semuaKasir = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role IN ('kasir','admin') AND status='aktif' ORDER BY nama_lengkap")->fetchAll();

// Build query
$where = "WHERE DATE(b.waktu_bayar) BETWEEN ? AND ?";
$params = [$filterStart, $filterEnd];

if ($filterKasir > 0) {
    $where .= " AND s.kasir_id = ?";
    $params[] = $filterKasir;
}
if ($filterMetode !== '') {
    $where .= " AND b.metode_pembayaran = ?";
    $params[] = $filterMetode;
}
if ($searchQ !== '') {
    $where .= " AND (p.nomor_pesanan LIKE ? OR p.nama_pelanggan LIKE ?)";
    $q = '%' . $searchQ . '%';
    $params[] = $q;
    $params[] = $q;
}

$transaksis = $pdo->prepare("
    SELECT b.*, p.nomor_pesanan, p.tipe_pesanan, p.nama_pelanggan, p.subtotal, p.total_harga, p.diskon_nominal, p.service_nominal, p.pajak_nominal,
           m.nomor_meja, u.nama_lengkap as nama_kasir, s.kasir_id
    FROM pembayaran b
    JOIN pesanan p ON b.pesanan_id = p.id
    JOIN sesi_kasir s ON b.sesi_kasir_id = s.id
    JOIN users u ON s.kasir_id = u.id
    LEFT JOIN meja m ON p.meja_id = m.id
    $where
    ORDER BY b.waktu_bayar DESC
");
$transaksis->execute($params);
$allTrx = $transaksis->fetchAll();

// Totals
$totalRevenue = array_sum(array_map(fn($t) => (float)$t['jumlah_bayar'], $allTrx));
$totalNet = array_sum(array_map(fn($t) => (float)$t['jumlah_bayar'] - (float)$t['kembalian'], $allTrx));
$totalDiscount = array_sum(array_map(fn($t) => (float)$t['diskon_nominal'], $allTrx));
$totalService = array_sum(array_map(fn($t) => (float)$t['service_nominal'], $allTrx));
$totalTax = array_sum(array_map(fn($t) => (float)$t['pajak_nominal'], $allTrx));

// Pre-fetch items per transaction
$stmtItems = $pdo->query("
    SELECT dp.pesanan_id, m.nama_menu, dp.qty, dp.harga_satuan
    FROM detail_pesanan dp
    JOIN menu m ON dp.menu_id = m.id
    ORDER BY dp.pesanan_id, m.nama_menu
");
$itemsByOrder = [];
while ($row = $stmtItems->fetch()) {
    $itemsByOrder[$row['pesanan_id']][] = $row;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div x-data="riwayatAdmin()" class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Riwayat Transaksi</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Semua transaksi dari seluruh kasir.</p>
        </div>
    </div>

    <!-- Filter -->
    <form class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Dari</label>
            <input type="date" name="start" value="<?= htmlspecialchars($filterStart) ?>" class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Sampai</label>
            <input type="date" name="end" value="<?= htmlspecialchars($filterEnd) ?>" class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Kasir</label>
            <select name="kasir" class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
                <option value="0">Semua</option>
                <?php foreach ($semuaKasir as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $filterKasir === (int)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Metode</label>
            <select name="metode" class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
                <option value="">Semua</option>
                <option value="cash" <?= $filterMetode === 'cash' ? 'selected' : '' ?>>Tunai</option>
                <option value="qris" <?= $filterMetode === 'qris' ? 'selected' : '' ?>>QRIS</option>
                <option value="transfer" <?= $filterMetode === 'transfer' ? 'selected' : '' ?>>Transfer</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Cari</label>
            <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="No. pesanan / pelanggan" class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface w-48">
        </div>
        <button type="submit" class="px-4 py-2 bg-vibe-primary text-white font-bold text-xs rounded-lg hover:bg-vibe-primary-container transition-colors active:scale-[0.97]">Filter</button>
        <a href="riwayat_transaksi.php" class="px-4 py-2 border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-xs rounded-lg hover:bg-vibe-surface-dim transition-colors">Reset</a>
    </form>

    <?php if (empty($allTrx)): ?>
    <div class="bg-vibe-surface-dim border-2 border-dashed border-vibe-outline-variant rounded-xl p-12 text-center">
        <div class="w-14 h-14 rounded-xl bg-white border border-vibe-outline-variant flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <h3 class="font-bold text-vibe-on-surface text-sm mb-1">Tidak Ada Transaksi</h3>
        <p class="text-sm text-vibe-on-surface-variant">Tidak ditemukan transaksi pada filter ini.</p>
    </div>
    <?php else: ?>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Transaksi</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= count($allTrx) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Pendapatan</div>
            <div class="text-2xl font-black text-vibe-primary mt-1"><?= formatRupiah($totalNet) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Diskon</div>
            <div class="text-lg font-black text-vibe-error mt-1"><?= formatRupiah($totalDiscount) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Service</div>
            <div class="text-lg font-black text-vibe-secondary mt-1"><?= formatRupiah($totalService) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Pajak</div>
            <div class="text-lg font-black text-vibe-on-surface mt-1"><?= formatRupiah($totalTax) ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-3 py-3.5 w-8"></th>
                        <th class="px-3 py-3.5 text-left">Pesanan</th>
                        <th class="px-3 py-3.5 text-left">Kasir</th>
                        <th class="px-3 py-3.5 text-left hidden md:table-cell">Tgl</th>
                        <th class="px-3 py-3.5 text-right hidden lg:table-cell">Subtotal</th>
                        <th class="px-3 py-3.5 text-right hidden lg:table-cell">Diskon</th>
                        <th class="px-3 py-3.5 text-right">Total</th>
                        <th class="px-3 py-3.5 text-center">Metode</th>
                        <th class="px-3 py-3.5 text-right">Nota</th>
                    </tr>
                </thead>
                <tbody x-data="{ openRows: {} }" class="divide-y divide-vibe-outline/50">
                    <?php foreach ($allTrx as $trx):
                        $items = $itemsByOrder[$trx['pesanan_id']] ?? [];
                        $bukti = $trx['bukti_transfer'] ?? '';
                        $trxId = $trx['pesanan_id'];
                    ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors">
                        <td class="px-3 py-3.5">
                            <button @click="openRows[<?= $trxId ?>] = !openRows[<?= $trxId ?>]" class="p-1 -ml-1 text-vibe-outline-variant hover:text-vibe-on-surface transition-colors">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="openRows[<?= $trxId ?>] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </td>
                        <td class="px-3 py-3.5">
                            <div class="font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($trx['nomor_pesanan']) ?></div>
                            <div class="text-[11px] text-vibe-on-surface-variant whitespace-nowrap">
                                <?= $trx['tipe_pesanan'] === 'dine_in' && $trx['nomor_meja'] ? 'M' . htmlspecialchars($trx['nomor_meja']) : 'Bks' ?>
                                <?php if ($trx['nama_pelanggan']): ?> · <?= htmlspecialchars($trx['nama_pelanggan']) ?><?php endif; ?>
                                · <?= count($items) ?> item
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-sm text-vibe-on-surface whitespace-nowrap"><?= htmlspecialchars($trx['nama_kasir']) ?></td>
                        <td class="px-3 py-3.5 text-sm text-vibe-on-surface-variant whitespace-nowrap hidden md:table-cell"><?= date('d/m', strtotime($trx['waktu_bayar'])) ?></td>
                        <td class="px-3 py-3.5 text-right text-sm text-vibe-on-surface-variant hidden lg:table-cell"><?= formatRupiah((float)$trx['subtotal']) ?></td>
                        <td class="px-3 py-3.5 text-right text-sm <?= (float)$trx['diskon_nominal'] > 0 ? 'text-vibe-error' : 'text-vibe-on-surface-variant' ?> hidden lg:table-cell">
                            <?= (float)$trx['diskon_nominal'] > 0 ? '-' . formatRupiah((float)$trx['diskon_nominal']) : '-' ?>
                        </td>
                        <td class="px-3 py-3.5 text-right font-bold text-sm text-vibe-primary whitespace-nowrap"><?= formatRupiah((float)$trx['total_harga']) ?></td>
                        <td class="px-3 py-3.5 text-center">
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold <?= $trx['metode_pembayaran'] === 'cash' ? 'bg-vibe-secondary-container text-vibe-secondary' : ($trx['metode_pembayaran'] === 'qris' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700') ?>">
                                <?= $trx['metode_pembayaran'] === 'cash' ? 'Cash' : ($trx['metode_pembayaran'] === 'qris' ? 'QR' : 'TF') ?>
                            </span>
                        </td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap">
                            <a href="<?= BASE_URL ?>/modules/kasir/struk.php?pesanan_id=<?= $trxId ?>" target="_blank" class="text-[10px] font-bold text-vibe-on-surface-variant hover:underline">Nota</a>
                        </td>
                    </tr>
                    <tr x-show="openRows[<?= $trxId ?>]" x-cloak>
                        <td colspan="9" class="px-4 pb-4 pt-0">
                            <div class="bg-vibe-surface-dim rounded-lg p-4 space-y-2">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs pb-3 border-b border-vibe-outline/50">
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Kasir</span>
                                        <div class="font-bold text-vibe-on-surface"><?= htmlspecialchars($trx['nama_kasir']) ?></div>
                                    </div>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Waktu</span>
                                        <div class="font-bold text-vibe-on-surface"><?= date('d M Y H:i', strtotime($trx['waktu_bayar'])) ?></div>
                                    </div>
                                    <?php if ((float)$trx['diskon_nominal'] > 0): ?>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Diskon</span>
                                        <div class="font-bold text-vibe-error">-<?= formatRupiah((float)$trx['diskon_nominal']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ((float)$trx['service_nominal'] > 0): ?>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Service</span>
                                        <div class="font-bold text-vibe-secondary"><?= formatRupiah((float)$trx['service_nominal']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ((float)$trx['pajak_nominal'] > 0): ?>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Pajak</span>
                                        <div class="font-bold text-vibe-on-surface"><?= formatRupiah((float)$trx['pajak_nominal']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Dibayar</span>
                                        <div class="font-bold text-vibe-on-surface"><?= formatRupiah((float)$trx['jumlah_bayar']) ?></div>
                                    </div>
                                    <?php if ((float)$trx['kembalian'] > 0): ?>
                                    <div>
                                        <span class="text-vibe-on-surface-variant">Kembali</span>
                                        <div class="font-bold text-vibe-error"><?= formatRupiah((float)$trx['kembalian']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($trx['nama_pengirim'] || $trx['referensi']): ?>
                                    <div class="col-span-2">
                                        <span class="text-vibe-on-surface-variant">Transfer</span>
                                        <div class="font-bold text-vibe-on-surface"><?= htmlspecialchars($trx['nama_pengirim']) ?> · Ref: <?= htmlspecialchars($trx['referensi']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs space-y-1">
                                    <div class="text-vibe-on-surface-variant font-bold uppercase tracking-wider text-[10px]">Item</div>
                                    <?php foreach ($items as $item): ?>
                                    <div class="flex items-center justify-between py-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded bg-white border border-vibe-outline-variant flex items-center justify-center text-[10px] font-bold text-vibe-on-surface"><?= (int)$item['qty'] ?></span>
                                            <span class="font-medium text-vibe-on-surface"><?= htmlspecialchars($item['nama_menu']) ?></span>
                                        </div>
                                        <span class="text-vibe-on-surface-variant"><?= formatRupiah((float)$item['harga_satuan'] * (int)$item['qty']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($bukti): ?>
                                <div class="pt-2 border-t border-vibe-outline/50">
                                    <div class="text-vibe-on-surface-variant font-bold uppercase tracking-wider text-[10px] mb-2">Bukti Transfer</div>
                                    <a href="<?= BASE_URL ?>/assets/uploads/bukti/<?= htmlspecialchars($bukti) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg hover:border-vibe-on-surface transition-colors text-xs font-medium text-vibe-on-surface"
                                       onclick="var img=new Image();img.onerror=function(){this.onerror=null;alert('File bukti tidak ditemukan atau sudah dihapus.');return false;};img.src=this.href;">
                                        <svg class="w-4 h-4 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Lihat Bukti Transfer
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('riwayatAdmin', () => ({ }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

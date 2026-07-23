<?php
$page_title = 'Leaderboard Kasir';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);

$filterStart = $_GET['start'] ?? date('Y-m-01');
$filterEnd = $_GET['end'] ?? date('Y-m-d');

$kasirData = $pdo->prepare("
    SELECT
        u.id, u.nama_lengkap, u.username,
        COUNT(DISTINCT s.id) as total_shifts,
        COALESCE(SUM(s.total_pemasukan), 0) as total_revenue,
        COUNT(DISTINCT b.id) as total_trx,
        COALESCE(SUM(b.jumlah_bayar - b.kembalian), 0) as net_cash,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.waktu_buka, COALESCE(s.waktu_tutup, NOW()))), 0) as total_minutes,
        COALESCE(AVG(s.total_pemasukan), 0) as avg_revenue_per_shift,
        COALESCE(MAX(s.total_pemasukan), 0) as best_shift_revenue
    FROM users u
    INNER JOIN sesi_kasir s ON s.kasir_id = u.id AND s.status = 'tutup'
    LEFT JOIN pembayaran b ON b.sesi_kasir_id = s.id
    WHERE DATE(s.waktu_buka) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_revenue DESC
");
$kasirData->execute([$filterStart, $filterEnd]);
$kasirs = $kasirData->fetchAll();

// Totals for summary
$grandTotal = array_sum(array_column($kasirs, 'total_revenue'));
$grandTrx = array_sum(array_column($kasirs, 'total_trx'));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div x-data class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Leaderboard Kasir</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Performa dan perbandingan produktivitas kasir.</p>
        </div>
    </div>

    <!-- Filter -->
    <form class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Dari</label>
            <input type="date" name="start" value="<?= htmlspecialchars($filterStart) ?>"
                   class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-1">Sampai</label>
            <input type="date" name="end" value="<?= htmlspecialchars($filterEnd) ?>"
                   class="px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg text-sm font-medium text-vibe-on-surface focus:outline-none focus:border-vibe-on-surface">
        </div>
        <button type="submit" class="px-4 py-2 bg-vibe-primary text-white font-bold text-xs rounded-lg hover:bg-vibe-primary-container transition-colors active:scale-[0.97]">Filter</button>
    </form>

    <?php if (empty($kasirs)): ?>
    <div class="bg-vibe-surface-dim border-2 border-dashed border-vibe-outline-variant rounded-xl p-12 text-center">
        <div class="w-14 h-14 rounded-xl bg-white border border-vibe-outline-variant flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-vibe-outline-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        </div>
        <h3 class="font-bold text-vibe-on-surface text-sm mb-1">Belum Ada Data Shift</h3>
        <p class="text-sm text-vibe-on-surface-variant">Tidak ada shift yang ditutup pada rentang tanggal ini.</p>
    </div>
    <?php else: ?>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Kasir Aktif</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= count($kasirs) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Pendapatan</div>
            <div class="text-2xl font-black text-vibe-primary mt-1"><?= formatRupiah($grandTotal) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Transaksi</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= number_format($grandTrx, 0, ',', '.') ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Rata-rata Shift</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= formatRupiah($grandTrx > 0 ? round($grandTotal / count($kasirs)) : 0) ?></div>
            <div class="text-[11px] text-vibe-on-surface-variant">per kasir</div>
        </div>
    </div>

    <!-- Leaderboard Table -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left w-10">#</th>
                        <th class="px-5 py-3.5 text-left">Kasir</th>
                        <th class="px-5 py-3.5 text-right">Shift</th>
                        <th class="px-5 py-3.5 text-right">Pendapatan</th>
                        <th class="px-5 py-3.5 text-right">Transaksi</th>
                        <th class="px-5 py-3.5 text-right">Rata/Shift</th>
                        <th class="px-5 py-3.5 text-right">Efisiensi</th>
                        <th class="px-5 py-3.5 text-right">Best Shift</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php foreach ($kasirs as $i => $k): 
                        $hours = max(1, (int)$k['total_minutes'] / 60);
                        $revenuePerHour = (float)$k['total_revenue'] / $hours;
                        $totalShifts = (int)$k['total_shifts'];
                    ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-black
                                <?= $i === 0 ? 'bg-vibe-on-surface text-white' : ($i === 1 ? 'bg-vibe-surface-high text-vibe-on-surface' : ($i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-vibe-surface-dim text-vibe-on-surface-variant')) ?>">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-vibe-surface-dim border border-vibe-outline-variant flex items-center justify-center font-bold text-xs text-vibe-on-surface shrink-0">
                                    <?= strtoupper(substr($k['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($k['nama_lengkap']) ?></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant">@<?= htmlspecialchars($k['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm text-vibe-on-surface"><?= $totalShifts ?></td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm text-vibe-primary"><?= formatRupiah((float)$k['total_revenue']) ?></td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm text-vibe-on-surface"><?= number_format((int)$k['total_trx'], 0, ',', '.') ?></td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm text-vibe-on-surface"><?= formatRupiah((float)$k['avg_revenue_per_shift']) ?></td>
                        <td class="px-5 py-3.5 text-right">
                            <span class="font-bold text-sm <?= $revenuePerHour >= 50000 ? 'text-vibe-secondary' : ($revenuePerHour >= 25000 ? 'text-vibe-on-surface' : 'text-vibe-on-surface-variant') ?>">
                                <?= formatRupiah(round($revenuePerHour)) ?>/jam
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold text-sm text-vibe-on-surface"><?= formatRupiah((float)$k['best_shift_revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
$page_title = 'Riwayat Stok';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);
requireCsrfToken();

ensureAuditTable();

// Fetch all ingredients with usage stats
$bahan = $pdo->query("
    SELECT
        b.id, b.nama_bahan, b.satuan, b.harga_beli, b.stok_sekarang,
        COALESCE(SUM(dp.qty * rm.jumlah_dibutuhkan), 0) as total_terpakai,
        COUNT(DISTINCT dp.id) as total_kali_dipakai
    FROM bahan_baku b
    LEFT JOIN resep_menu rm ON rm.bahan_id = b.id
    LEFT JOIN detail_pesanan dp ON dp.menu_id = rm.menu_id
    LEFT JOIN pesanan p ON dp.pesanan_id = p.id AND p.status_pesanan IN ('dibayar', 'selesai', 'diproses')
    GROUP BY b.id
    ORDER BY b.nama_bahan ASC
")->fetchAll();

// Calculate smart metrics per ingredient
$bahanData = [];
foreach ($bahan as $b) {
    $totalTerpakai = (float)$b['total_terpakai'];
    $stok = (float)$b['stok_sekarang'];
    $usageRate = 0;
    $estimatedDays = null;

    // Estimate daily usage from pattern (assume 30-day window)
    if ($totalTerpakai > 0) {
        $usageRate = round($totalTerpakai / max(1, $b['total_kali_dipakai']), 1);
        $dailyRate = round($totalTerpakai / 30, 2);
        if ($dailyRate > 0 && $stok > 0) {
            $estimatedDays = ceil($stok / $dailyRate);
        }
    }

    $bahanData[] = [
        'id' => $b['id'],
        'nama' => $b['nama_bahan'],
        'satuan' => $b['satuan'],
        'harga_beli' => (float)$b['harga_beli'],
        'stok' => $stok,
        'terpakai' => $totalTerpakai,
        'rata_per_transaksi' => $usageRate,
        'estimasi_hari' => $estimatedDays,
    ];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div x-data="stokMovement()" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Riwayat Stok</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Pergerakan stok bahan baku dan prediksi pemakaian.</p>
        </div>
        <div class="relative w-full sm:w-72">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" x-model="search" placeholder="Cari bahan..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
        </div>
    </div>

    <!-- Smart Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Bahan</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= count($bahanData) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Stok Kritis</div>
            <div class="text-2xl font-black text-vibe-error mt-1"><?= count(array_filter($bahanData, fn($b) => $b['stok'] <= 5)) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Paling Laku</div>
            <div class="text-lg font-black text-vibe-on-surface mt-1 truncate">
                <?php
                $sorted = $bahanData;
                usort($sorted, fn($a, $b) => $b['terpakai'] <=> $a['terpakai']);
                echo $sorted ? htmlspecialchars($sorted[0]['nama']) : '-';
                ?>
            </div>
            <div class="text-[11px] text-vibe-on-surface-variant"><?= $sorted ? (int)$sorted[0]['terpakai'] . ' ' . $sorted[0]['satuan'] . ' terpakai' : '' ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-lg px-4 py-3">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Tanpa Pemakaian</div>
            <div class="text-2xl font-black text-vibe-on-surface mt-1"><?= count(array_filter($bahanData, fn($b) => $b['terpakai'] == 0)) ?></div>
            <div class="text-[11px] text-vibe-on-surface-variant">bahan belum dipakai</div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left">Bahan</th>
                        <th class="px-5 py-3.5 text-right">Stok</th>
                        <th class="px-5 py-3.5 text-right">Terpakai</th>
                        <th class="px-5 py-3.5 text-right">Rata/Transaksi</th>
                        <th class="px-5 py-3.5 text-right">Estimasi Habis</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php if (empty($bahanData)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-sm text-vibe-on-surface-variant">Belum ada data bahan baku.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($bahanData as $b): ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors <?= $b['estimasi_hari'] !== null && $b['estimasi_hari'] <= 7 ? 'bg-orange-50/50' : '' ?>">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-vibe-outline-variant bg-vibe-surface-dim flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($b['nama']) ?></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant"><?= htmlspecialchars($b['satuan']) ?> · Rp <?= number_format($b['harga_beli'], 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <span class="font-bold text-base <?= $b['stok'] <= 5 ? 'text-vibe-error' : 'text-vibe-on-surface' ?>"><?= number_format($b['stok'], $b['satuan'] === 'Rp' ? 0 : 0, ',', '.') ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <span class="font-semibold text-sm text-vibe-on-surface"><?= number_format($b['terpakai'], 0, ',', '.') ?></span>
                            <span class="text-[11px] text-vibe-on-surface-variant"><?= htmlspecialchars($b['satuan']) ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <span class="text-sm text-vibe-on-surface font-medium"><?= number_format($b['rata_per_transaksi'], 1, ',', '.') ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <?php if ($b['estimasi_hari'] !== null): ?>
                                <span class="font-bold text-sm <?= $b['estimasi_hari'] <= 3 ? 'text-vibe-error' : ($b['estimasi_hari'] <= 7 ? 'text-yellow-600' : 'text-vibe-on-surface') ?>">
                                    <?= $b['estimasi_hari'] ?> hari
                                </span>
                            <?php else: ?>
                                <span class="text-sm text-vibe-on-surface-variant">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($b['stok'] <= 0): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">Habis</span>
                            <?php elseif ($b['stok'] <= 5): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-orange-100 text-orange-700 border border-orange-200">Kritis</span>
                            <?php elseif ($b['estimasi_hari'] !== null && $b['estimasi_hari'] <= 7): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-yellow-50 text-yellow-700 border border-yellow-200">Menipis</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">Aman</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <button @click="showDetail(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($b['satuan'], ENT_QUOTES) ?>')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-vibe-on-surface-variant border border-vibe-outline-variant rounded-md hover:bg-vibe-surface-dim hover:text-vibe-on-surface transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Riwayat
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Modal -->
    <div x-show="showModal" @keydown.escape.window="showModal=false; detailData=null"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
         x-transition style="display:none">
        <div @click.stop class="bg-white rounded-xl p-6 w-full max-w-lg border border-vibe-outline-variant max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between mb-5 shrink-0">
                <div>
                    <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="detailNama"></h3>
                    <p class="text-xs text-vibe-on-surface-variant" x-text="'Satuan: ' + detailSatuan"></p>
                </div>
                <button @click="showModal=false; detailData=null" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-md transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0 space-y-5">
                <!-- Info -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                        <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Stok Saat Ini</div>
                        <div class="text-xl font-black text-vibe-on-surface mt-1" x-text="detailData ? parseFloat(detailData.stok) : '-'"></div>
                    </div>
                    <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                        <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Terpakai</div>
                        <div class="text-xl font-black text-vibe-on-surface mt-1" x-text="detailData ? parseFloat(detailData.terpakai) : '-'"></div>
                    </div>
                    <div class="bg-vibe-surface-dim rounded-lg p-3 text-center">
                        <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Estimasi Habis</div>
                        <div class="text-xl font-black mt-1" :class="detailData && detailData.estimasi_hari !== null ? (detailData.estimasi_hari <= 3 ? 'text-vibe-error' : 'text-vibe-on-surface') : 'text-vibe-on-surface-variant'" x-text="detailData && detailData.estimasi_hari !== null ? detailData.estimasi_hari + ' hr' : '—'"></div>
                    </div>
                </div>

                <!-- Usage per transaksi -->
                <div>
                    <div class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-2">Pergerakan Pemakaian</div>
                    <div class="space-y-1.5">
                        <template x-for="m in detailMovements" :key="m.id">
                            <div class="flex items-center justify-between bg-white border border-vibe-outline-variant rounded-lg px-3.5 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-vibe-on-surface truncate" x-text="m.nomor_pesanan"></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant" x-text="m.waktu"></div>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <span class="font-bold text-sm text-vibe-error">-<span x-text="m.qty"></span></span>
                                    <span class="text-[11px] text-vibe-on-surface-variant ml-0.5" x-text="detailSatuan"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="!detailMovements || detailMovements.length === 0"
                             class="text-center py-6 text-sm text-vibe-on-surface-variant">
                            Belum ada pemakaian tercatat.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-vibe-outline-variant flex gap-3 shrink-0">
                <button type="button" @click="showModal=false; detailData=null"
                        class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Tutup</button>
                <a :href="'inventaris.php'"
                   class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors text-center">Ke Inventaris</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('stokMovement', () => ({
        search: '',

        showModal: false,
        detailData: null,
        detailNama: '',
        detailSatuan: '',
        detailMovements: [],

        <?php
        // Pre-fetch movement data for all bahan (serialized)
        $stmtM = $pdo->query("
            SELECT dp.menu_id, dp.pesanan_id, dp.qty, p.nomor_pesanan, p.waktu_pesan, rm.bahan_id, rm.jumlah_dibutuhkan
            FROM detail_pesanan dp
            JOIN pesanan p ON dp.pesanan_id = p.id
            JOIN resep_menu rm ON rm.menu_id = dp.menu_id
            WHERE p.status_pesanan IN ('dibayar', 'selesai', 'diproses')
            ORDER BY p.waktu_pesan DESC
        ");
        $movementsByBahan = [];
        while ($row = $stmtM->fetch()) {
            $bid = $row['bahan_id'];
            $used = (float)$row['qty'] * (float)$row['jumlah_dibutuhkan'];
            if (!isset($movementsByBahan[$bid])) $movementsByBahan[$bid] = [];
            $movementsByBahan[$bid][] = [
                'nomor_pesanan' => $row['nomor_pesanan'],
                'qty' => $used,
                'waktu' => date('d M H:i', strtotime($row['waktu_pesan'])),
            ];
        }
        ?>
        movementsByBahan: <?= json_encode($movementsByBahan) ?>,

        getAllData() {
            return <?= json_encode($bahanData) ?>;
        },

        showDetail(id, nama, satuan) {
            this.detailNama = nama;
            this.detailSatuan = satuan;
            this.detailData = this.getAllData().find(b => b.id === id) || null;
            this.detailMovements = this.movementsByBahan[id] || [];
            this.showModal = true;
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

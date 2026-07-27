<?php
$page_title = 'Analisis Menu & Bahan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole(['admin']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Data
$menus = $pdo->query("SELECT m.*, k.nama_kategori FROM menu m JOIN kategori k ON m.kategori_id=k.id ORDER BY k.nama_kategori, m.nama_menu")->fetchAll();
$bahan = $pdo->query("SELECT b.*, (SELECT COUNT(*) FROM resep_menu rm WHERE rm.bahan_id=b.id) AS dipakai FROM bahan_baku b ORDER BY b.nama_bahan")->fetchAll();
$resep = $pdo->query("SELECT rm.*, m.nama_menu, m.harga, b.nama_bahan, b.satuan, b.harga_beli FROM resep_menu rm JOIN menu m ON rm.menu_id=m.id JOIN bahan_baku b ON rm.bahan_id=b.id ORDER BY m.nama_menu, b.nama_bahan")->fetchAll();

$resepByMenu = [];
foreach ($resep as $r) { $resepByMenu[$r['menu_id']][] = $r; }

$menuWithResep = array_unique(array_column($resep, 'menu_id'));
$menuTanpaResep = array_filter($menus, fn($m) => !in_array($m['id'], $menuWithResep));
$bahanTakDipake = array_filter($bahan, fn($b) => $b['dipakai'] == 0);
?>
<div class="space-y-8">

    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl bg-vibe-primary/10 flex items-center justify-center">
            <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </div>
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Analisis Menu &amp; Bahan</h1>
            <p class="text-sm text-vibe-on-surface-variant">Ringkasan resep, HPP, margin, dan rekomendasi takaran.</p>
        </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5"><div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Menu</div><div class="text-2xl font-black text-vibe-on-surface mt-1"><?= count($menus) ?></div></div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5"><div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Dengan Resep</div><div class="text-2xl font-black text-vibe-secondary mt-1"><?= count($menuWithResep) ?></div></div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5"><div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Tanpa Resep</div><div class="text-2xl font-black text-vibe-error mt-1"><?= count($menuTanpaResep) ?></div></div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5"><div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Bahan Tak Terpakai</div><div class="text-2xl font-black text-vibe-accent mt-1"><?= count($bahanTakDipake) ?></div></div>
    </div>

    <?php foreach ($menus as $m):
        $items = $resepByMenu[$m['id']] ?? [];
        $hpp = array_sum(array_map(fn($i) => (float)$i['jumlah_dibutuhkan'] * (float)$i['harga_beli'], $items));
        $laba = (float)$m['harga'] - $hpp;
        $margin = (float)$m['harga'] > 0 ? round($laba / (float)$m['harga'] * 100) : 0;
    ?>
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-vibe-outline-variant flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold border <?= empty($items) ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200' ?>">
                    <?= empty($items) ? '❌' : '✅' ?>
                </span>
                <div>
                    <div class="font-bold text-vibe-on-surface"><?= htmlspecialchars($m['nama_menu']) ?></div>
                    <div class="text-[11px] text-vibe-on-surface-variant"><?= htmlspecialchars($m['nama_kategori']) ?></div>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-vibe-on-surface-variant">Jual <span class="font-bold text-vibe-on-surface"><?= formatRupiah($m['harga']) ?></span></span>
                <?php if (!empty($items)): ?>
                <span class="text-vibe-on-surface-variant">HPP <span class="font-bold text-vibe-accent"><?= formatRupiah($hpp) ?></span></span>
                <span class="text-vibe-on-surface-variant">Laba <span class="font-bold <?= $laba >= 0 ? 'text-vibe-on-surface' : 'text-vibe-error' ?>"><?= formatRupiah($laba) ?></span></span>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-bold <?= $margin >= 60 ? 'bg-green-50 text-green-700' : ($margin >= 30 ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') ?>"><?= $margin ?>%</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (empty($items)): ?>
        <div class="px-5 py-4 text-sm text-vibe-on-surface-variant"><em>Belum ada resep — isi di <a href="resep.php" class="text-vibe-primary font-semibold hover:underline">halaman Resep</a>.</em></div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                    <th class="px-5 py-3 text-left">Bahan</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Satuan</th><th class="px-5 py-3 text-right">Harga Satuan</th><th class="px-5 py-3 text-right">Subtotal</th><th class="px-5 py-3 text-left">Rekomendasi Takaran</th>
                </tr></thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php foreach ($items as $it):
                        $qty = (float)$it['jumlah_dibutuhkan'];
                        $price = (float)$it['harga_beli'];
                    ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors">
                        <td class="px-5 py-3.5 font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($it['nama_bahan']) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm"><?= number_format($qty, $it['satuan'] === 'biji' ? 0 : 2, ',', '.') ?></td>
                        <td class="px-5 py-3.5 text-right text-sm text-vibe-on-surface-variant"><?= htmlspecialchars($it['satuan']) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm"><?= formatRupiah($price) ?></td>
                        <td class="px-5 py-3.5 text-right text-sm font-bold"><?= formatRupiah($qty * $price) ?></td>
                        <td class="px-5 py-3.5 text-sm text-vibe-on-surface-variant">
                            <?php
                            $s = $it['satuan'];
                            $n = $it['nama_bahan'];
                            if (strpos($n, 'Nasi') !== false || strpos($n, 'nasi') !== false) {
                                echo "<span class='text-vibe-accent font-semibold'>Gunakan centong sebagai satuan (1 centong = 1 porsi)</span>";
                            } elseif ($s === 'biji' || $s === 'keping' || $s === 'pack') {
                                echo 'Cocok — gunakan jumlah satuan sesuai resep.';
                            } elseif ($s === 'kg' || $s === 'gram' || $s === 'ml' || $s === 'liter') {
                                echo 'Gunakan timbangan/ukur. Timbang dulu 1 porsi = berapa gram.';
                            } else {
                                echo 'Satuan sudah sesuai.';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Bahan tak dipakai -->
    <?php if (!empty($bahanTakDipake)): ?>
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-vibe-outline-variant flex items-center gap-3">
            <svg class="w-5 h-5 text-vibe-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div><div class="font-bold text-vibe-on-surface text-sm">Bahan Baku Tidak Terpakai</div><p class="text-xs text-vibe-on-surface-variant">Bahan ini belum dipakai di resep manapun. Mungkin perlu dikaitkan atau dihapus.</p></div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim"><th class="px-5 py-3 text-left">Bahan</th><th class="px-5 py-3 text-right">Stok</th><th class="px-5 py-3 text-right">Satuan</th><th class="px-5 py-3 text-right">Harga Beli</th></tr></thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php foreach ($bahanTakDipake as $b): ?>
                    <tr class="hover:bg-vibe-surface-dim"><td class="px-5 py-3.5 font-semibold"><?= htmlspecialchars($b['nama_bahan']) ?></td><td class="px-5 py-3.5 text-right"><?= $b['stok_sekarang'] ?></td><td class="px-5 py-3.5 text-right text-vibe-on-surface-variant"><?= htmlspecialchars($b['satuan']) ?></td><td class="px-5 py-3.5 text-right"><?= formatRupiah($b['harga_beli']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
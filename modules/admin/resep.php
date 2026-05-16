<?php
$page_title = 'Kelola Resep & Profit Margin';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole(['admin']);

$menu_id = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;

// Handle POST request first BEFORE loading header (HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_menu_id = (int)($_POST['menu_id'] ?? $menu_id);
    try {
        if ($action === 'add' && $post_menu_id > 0) {
            $bahan_id = (int)$_POST['bahan_id'];
            $jumlah = (float)$_POST['jumlah_dibutuhkan'];
            $stmtCek = $pdo->prepare("SELECT id FROM resep_menu WHERE menu_id = ? AND bahan_id = ?");
            $stmtCek->execute([$post_menu_id, $bahan_id]);
            if ($stmtCek->fetch()) throw new Exception("Bahan ini sudah ada di dalam resep.");
            $pdo->prepare("INSERT INTO resep_menu (menu_id, bahan_id, jumlah_dibutuhkan) VALUES (?,?,?)")
                ->execute([$post_menu_id, $bahan_id, $jumlah]);
            $_SESSION['success'] = "Bahan berhasil ditambahkan ke resep.";
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM resep_menu WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = "Bahan dihapus dari resep.";
        }
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: resep.php?menu_id=$post_menu_id");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

// ─── FETCH ALL MENUS WITH COGS ─────────────────────────────
$stmt = $pdo->query("
    SELECT 
        m.id, m.nama_menu, m.harga, m.gambar, m.status, m.updated_at,
        k.nama_kategori,
        COALESCE(SUM(rm.jumlah_dibutuhkan * b.harga_beli), 0) AS total_cogs
    FROM menu m
    LEFT JOIN kategori k ON m.kategori_id = k.id
    LEFT JOIN resep_menu rm ON m.id = rm.menu_id
    LEFT JOIN bahan_baku b ON rm.bahan_id = b.id
    WHERE m.is_active = 1
    GROUP BY m.id
    ORDER BY k.nama_kategori ASC, m.nama_menu ASC
");
$menus = $stmt->fetchAll();

// ─── FETCH SELECTED MENU DETAIL ────────────────────────────
$selectedMenu = null;
$reseps = [];
$bahanBaku = [];
$total_cogs = 0;
$profit = 0;
$margin_persen = 0;

if ($menu_id > 0) {
    $stmtM = $pdo->prepare("SELECT m.*, k.nama_kategori FROM menu m LEFT JOIN kategori k ON m.kategori_id = k.id WHERE m.id = ?");
    $stmtM->execute([$menu_id]);
    $selectedMenu = $stmtM->fetch();

    if ($selectedMenu) {
        $resep = $pdo->prepare("SELECT r.*, b.nama_bahan, b.satuan, b.harga_beli FROM resep_menu r JOIN bahan_baku b ON r.bahan_id = b.id WHERE r.menu_id = ?");
        $resep->execute([$menu_id]);
        $reseps = $resep->fetchAll();
        $bahanBaku = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC")->fetchAll();

        foreach($reseps as $r) {
            $total_cogs += ($r['harga_beli'] * $r['jumlah_dibutuhkan']);
        }
        $harga_jual = $selectedMenu['harga'];
        $profit = $harga_jual - $total_cogs;
        $margin_persen = $harga_jual > 0 ? ($profit / $harga_jual) * 100 : 0;
    }
}

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .recipe-master-list { max-height: calc(100vh - 180px); }
    .recipe-detail-panel { max-height: calc(100vh - 180px); }
    .menu-card-active { border-color: var(--tw-vibe-primary, #004ac6) !important; background: rgba(0, 74, 198, 0.04); }
    .menu-card { transition: all 0.2s ease; }
    .menu-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
    .ingredient-row { transition: background 0.15s ease; }
    .ingredient-row:hover { background: rgba(0, 74, 198, 0.03); }
    .margin-bar { transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
    @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    .animate-slide-in { animation: slideInRight 0.35s ease-out forwards; }
    .cover-placeholder {
        background: linear-gradient(135deg, #e5eeff 0%, #dce9ff 50%, #cbdbf5 100%);
    }
</style>

<div x-data="resepMasterDetail()" class="h-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-vibe-on-surface tracking-tight">Manajemen Resep</h1>
            <p class="text-vibe-on-surface-variant mt-0.5 text-sm font-medium">Kelola standar bahan dan HPP untuk menu restoran.</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- Search -->
            <div class="relative w-full sm:w-72">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-vibe-outline">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="search" placeholder="Cari resep..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-vibe-outline-variant/40 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-medium transition-all placeholder-vibe-outline shadow-sm">
            </div>
        </div>
    </div>

    <!-- Master-Detail Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- ═══ LEFT: Master List ═══ -->
        <div class="lg:col-span-5 xl:col-span-4">
            <div class="recipe-master-list overflow-y-auto pr-1 space-y-3 hide-scrollbar">
                <template x-for="m in filteredMenus" :key="m.id">
                    <a :href="'resep.php?menu_id=' + m.id"
                       class="menu-card block bg-white rounded-2xl border-2 p-4 cursor-pointer"
                       :class="m.id == selectedId ? 'menu-card-active border-vibe-primary shadow-lg shadow-vibe-primary/10' : 'border-vibe-outline-variant/20 hover:border-vibe-primary/40 shadow-card'">
                        <div class="flex items-start gap-4">
                            <!-- Thumbnail -->
                            <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0" :class="m.gambar ? '' : 'cover-placeholder flex items-center justify-center'">
                                <template x-if="m.gambar">
                                    <img :src="'<?= BASE_URL ?>/assets/images/' + m.gambar" class="w-full h-full object-cover" :alt="m.nama_menu">
                                </template>
                                <template x-if="!m.gambar">
                                    <svg class="w-7 h-7 text-vibe-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </template>
                            </div>
                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-bold text-vibe-on-surface text-sm leading-tight line-clamp-1" x-text="m.nama_menu"></h3>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-vibe-primary/10 text-vibe-primary" x-text="m.nama_kategori || 'Uncategorized'"></span>
                                            <span class="flex items-center gap-1 text-[10px] font-semibold"
                                                  :class="m.status === 'tersedia' ? 'text-vibe-secondary' : 'text-vibe-outline'">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="m.status === 'tersedia' ? 'bg-vibe-secondary' : 'bg-vibe-outline'"></span>
                                                <span x-text="m.status === 'tersedia' ? 'Active' : 'Draft'"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="text-[10px] font-semibold text-vibe-outline uppercase tracking-wider">HPP</div>
                                        <div class="font-extrabold text-sm" :class="m.total_cogs > 0 ? 'text-vibe-primary' : 'text-vibe-outline'" x-text="formatRp(m.total_cogs)"></div>
                                    </div>
                                </div>
                                <div class="text-[10px] text-vibe-outline mt-2 font-medium" x-text="'Last updated: ' + formatDate(m.updated_at)"></div>
                            </div>
                            <!-- Arrow for active -->
                            <div x-show="m.id == selectedId" class="flex items-center text-vibe-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </div>
                    </a>
                </template>
                <!-- Empty State -->
                <div x-show="filteredMenus.length === 0" class="text-center py-16 text-vibe-outline">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <p class="font-semibold text-sm">Menu tidak ditemukan.</p>
                </div>
            </div>
        </div>

        <!-- ═══ RIGHT: Detail Panel ═══ -->
        <div class="lg:col-span-7 xl:col-span-8">
            <div class="recipe-detail-panel overflow-y-auto hide-scrollbar">

                <?php if (!$selectedMenu): ?>
                <!-- Empty State: No menu selected -->
                <div class="flex flex-col items-center justify-center h-full min-h-[500px] text-center">
                    <div class="w-24 h-24 rounded-full bg-vibe-surface-container flex items-center justify-center mb-5">
                        <svg class="w-10 h-10 text-vibe-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-vibe-on-surface mb-1">Pilih menu dari daftar</h3>
                    <p class="text-sm text-vibe-outline max-w-xs">Klik salah satu menu di sebelah kiri untuk melihat dan mengelola resep beserta analisis HPP-nya.</p>
                </div>

                <?php else: ?>
                <!-- Detail Content -->
                <div class="space-y-5 animate-slide-in">

                    <!-- Cover Image / Hero -->
                    <div class="relative rounded-2xl overflow-hidden h-48 shadow-card">
                        <?php if ($selectedMenu['gambar']): ?>
                            <img src="<?= BASE_URL ?>/assets/images/<?= htmlspecialchars($selectedMenu['gambar']) ?>" 
                                 alt="<?= htmlspecialchars($selectedMenu['nama_menu']) ?>" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full cover-placeholder flex items-center justify-center">
                                <svg class="w-16 h-16 text-vibe-primary/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        <?php endif; ?>
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                        <!-- Badge + Title -->
                        <div class="absolute bottom-0 left-0 right-0 p-5">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-vibe-primary text-white mb-2 inline-block">
                                <?= htmlspecialchars($selectedMenu['nama_kategori'] ?? 'Uncategorized') ?>
                            </span>
                            <h2 class="text-2xl font-extrabold text-white leading-tight"><?= htmlspecialchars($selectedMenu['nama_menu']) ?></h2>
                        </div>
                        <!-- Edit button -->
                        <a href="menu.php" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/30 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </a>
                    </div>

                    <!-- KPI Cards Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white rounded-2xl border border-vibe-outline-variant/20 p-5 shadow-sm">
                            <div class="text-[11px] font-bold text-vibe-outline uppercase tracking-widest mb-1">Total Food Cost (HPP)</div>
                            <div class="text-2xl font-extrabold text-vibe-error"><?= formatRupiah($total_cogs) ?></div>
                        </div>
                        <div class="bg-white rounded-2xl border border-vibe-outline-variant/20 p-5 shadow-sm">
                            <div class="text-[11px] font-bold text-vibe-outline uppercase tracking-widest mb-1">Harga Jual</div>
                            <div class="text-2xl font-extrabold text-vibe-on-surface"><?= formatRupiah($selectedMenu['harga']) ?></div>
                        </div>
                    </div>

                    <!-- Gross Margin Bar -->
                    <div class="bg-white rounded-2xl border border-vibe-outline-variant/20 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-bold text-vibe-on-surface">Gross Margin</span>
                            <span class="text-xl font-extrabold <?= $margin_persen >= 50 ? 'text-vibe-secondary' : ($margin_persen > 20 ? 'text-vibe-primary' : 'text-vibe-error') ?>">
                                <?= number_format($margin_persen, 1) ?>%
                            </span>
                        </div>
                        <div class="w-full h-3 rounded-full bg-vibe-surface-container overflow-hidden">
                            <div class="margin-bar h-full rounded-full <?= $margin_persen >= 50 ? 'bg-vibe-secondary' : ($margin_persen > 20 ? 'bg-vibe-primary' : 'bg-vibe-error') ?>"
                                 style="width: <?= min(max($margin_persen, 0), 100) ?>%"></div>
                        </div>
                        <div class="flex justify-between mt-2 text-[10px] font-semibold text-vibe-outline">
                            <span>Untung: <?= formatRupiah($profit) ?> / porsi</span>
                            <span><?= $margin_persen >= 50 ? '✅ Sehat' : ($margin_persen > 20 ? '⚠️ Moderat' : '🔴 Rendah') ?></span>
                        </div>
                    </div>

                    <!-- Ingredients Section -->
                    <div class="bg-white rounded-2xl border border-vibe-outline-variant/20 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-vibe-outline-variant/10 flex items-center justify-between">
                            <h3 class="font-bold text-vibe-on-surface flex items-center gap-2">
                                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                Ingredients
                            </h3>
                            <span class="text-xs font-bold text-vibe-outline"><?= count($reseps) ?> bahan</span>
                        </div>

                        <!-- Ingredients Table -->
                        <table class="w-full">
                            <thead>
                                <tr class="text-[10px] font-bold text-vibe-outline uppercase tracking-widest border-b border-vibe-outline-variant/10">
                                    <th class="px-5 py-3 text-left">Item</th>
                                    <th class="px-5 py-3 text-center">Qty</th>
                                    <th class="px-5 py-3 text-right">Cost</th>
                                    <th class="px-5 py-3 text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reseps)): ?>
                                    <tr><td colspan="4" class="px-5 py-10 text-center text-vibe-outline text-sm font-medium">Belum ada bahan baku yang ditambahkan.</td></tr>
                                <?php else: ?>
                                    <?php foreach($reseps as $r): 
                                        $cost = $r['harga_beli'] * $r['jumlah_dibutuhkan'];
                                    ?>
                                    <tr class="ingredient-row border-b border-vibe-outline-variant/5">
                                        <td class="px-5 py-3.5">
                                            <div class="font-semibold text-vibe-on-surface text-sm"><?= htmlspecialchars($r['nama_bahan']) ?></div>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="font-bold text-vibe-on-surface"><?= floatval($r['jumlah_dibutuhkan']) ?></span>
                                            <span class="text-xs text-vibe-outline ml-1"><?= htmlspecialchars($r['satuan']) ?></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-bold text-vibe-on-surface text-sm"><?= formatRupiah($cost) ?></td>
                                        <td class="px-5 py-3.5 text-center">
                                            <form method="POST" class="m-0 inline" onsubmit="return confirm('Hapus bahan ini?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="menu_id" value="<?= $menu_id ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-vibe-error/60 hover:text-vibe-error hover:bg-vibe-error/10 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Add Ingredient Form -->
                        <div class="p-5 bg-vibe-bg/50 border-t border-vibe-outline-variant/10">
                            <form method="POST" class="flex items-end gap-3" x-data="{ selectedBahan: '' }">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="menu_id" value="<?= $menu_id ?>">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-vibe-outline uppercase tracking-widest mb-1.5">Bahan Baku</label>
                                    <select name="bahan_id" required class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-medium">
                                        <option value="">-- Pilih --</option>
                                        <?php foreach($bahanBaku as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_bahan']) ?> (<?= $b['satuan'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="w-28">
                                    <label class="block text-[10px] font-bold text-vibe-outline uppercase tracking-widest mb-1.5">Takaran</label>
                                    <input type="number" step="0.01" name="jumlah_dibutuhkan" required placeholder="0.00" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-vibe-primary/30 focus:border-vibe-primary text-sm font-bold text-center">
                                </div>
                                <button type="submit" class="px-5 py-2.5 bg-vibe-primary text-white font-bold rounded-xl hover:bg-vibe-primary-container transition-colors shadow-md shadow-vibe-primary/25 text-sm whitespace-nowrap">
                                    + Tambah
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('resepMasterDetail', () => ({
        menus: <?= json_encode($menus) ?>,
        selectedId: <?= $menu_id ?>,
        search: '',

        get filteredMenus() {
            if (this.search === '') return this.menus;
            const sq = this.search.toLowerCase();
            return this.menus.filter(m => 
                m.nama_menu.toLowerCase().includes(sq) || 
                (m.nama_kategori && m.nama_kategori.toLowerCase().includes(sq))
            );
        },

        formatRp(angka) {
            return 'Rp ' + parseInt(angka || 0).toLocaleString('id-ID');
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

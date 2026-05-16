<?php
$page_title = 'Manajemen Promo & Voucher';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

// Handle aksi CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $kode = strtoupper(trim($_POST['kode_promo']));
            $tipe = $_POST['tipe_diskon'];
            $nilai = (float)$_POST['nilai_diskon'];
            $min_belanja = (float)$_POST['min_belanja'];
            $kuota = (int)$_POST['kuota'];
            $mulai = $_POST['tanggal_mulai'];
            $selesai = $_POST['tanggal_selesai'];
            
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM promo WHERE kode_promo = ?");
            $stmtCek->execute([$kode]);
            if ($stmtCek->fetch()) throw new Exception("Kode promo sudah digunakan!");
            
            $pdo->prepare("INSERT INTO promo (kode_promo, tipe_diskon, nilai_diskon, min_belanja, kuota, tanggal_mulai, tanggal_selesai) VALUES (?,?,?,?,?,?,?)")
                ->execute([$kode, $tipe, $nilai, $min_belanja, $kuota, $mulai, $selesai]);
            $_SESSION['success'] = "Promo berhasil ditambahkan.";
            
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $kode = strtoupper(trim($_POST['kode_promo']));
            $tipe = $_POST['tipe_diskon'];
            $nilai = (float)$_POST['nilai_diskon'];
            $min_belanja = (float)$_POST['min_belanja'];
            $kuota = (int)$_POST['kuota'];
            $mulai = $_POST['tanggal_mulai'];
            $selesai = $_POST['tanggal_selesai'];
            $status = $_POST['status'];
            
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM promo WHERE kode_promo = ? AND id != ?");
            $stmtCek->execute([$kode, $id]);
            if ($stmtCek->fetch()) throw new Exception("Kode promo sudah digunakan!");
            
            $pdo->prepare("UPDATE promo SET kode_promo=?, tipe_diskon=?, nilai_diskon=?, min_belanja=?, kuota=?, tanggal_mulai=?, tanggal_selesai=?, status=? WHERE id=?")
                ->execute([$kode, $tipe, $nilai, $min_belanja, $kuota, $mulai, $selesai, $status, $id]);
            $_SESSION['success'] = "Promo berhasil diperbarui.";
            
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM promo WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = "Promo berhasil dihapus.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: promo.php");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$promos = $pdo->query("SELECT * FROM promo ORDER BY status ASC, tanggal_selesai DESC")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="{ showModal: false, modalMode: 'add', formData: {} }" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen">Promo & Voucher</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola diskon pintar untuk pelanggan Anda.</p>
        </div>
        <button @click="showModal = true; modalMode = 'add'; formData = {tipe_diskon:'persen', status:'aktif', tanggal_mulai:'<?= date('Y-m-d') ?>', tanggal_selesai:'<?= date('Y-m-d', strtotime('+7 days')) ?>'}" class="px-5 py-2.5 bg-theme-sage text-white rounded-xl font-bold hover:bg-theme-leaf transition-colors shadow-md shadow-theme-sage/30 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Buat Promo Baru
        </button>
    </div>

    <!-- Alert -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-xl bg-theme-bg text-theme-leaf font-bold flex items-center gap-2 border border-theme-sage/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-xl bg-red-50 text-red-600 font-bold flex items-center gap-2 border border-red-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Grid Promo -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <?php foreach($promos as $p): 
            $is_active = $p['status'] === 'aktif' && strtotime($p['tanggal_selesai']) >= strtotime(date('Y-m-d')) && $p['kuota'] > 0;
            $status_color = $is_active ? 'bg-theme-sage text-white' : 'bg-gray-200 text-gray-500';
            $status_text = $p['status'] !== 'aktif' ? 'Nonaktif' : ($p['kuota'] <= 0 ? 'Kuota Habis' : (strtotime($p['tanggal_selesai']) < strtotime(date('Y-m-d')) ? 'Kadaluarsa' : 'Aktif'));
        ?>
        <div class="bg-white rounded-2xl border <?= $is_active ? 'border-theme-sage/30 shadow-md' : 'border-gray-200 opacity-75' ?> overflow-hidden relative">
            <div class="absolute top-0 right-0 px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-bl-xl <?= $status_color ?>">
                <?= $status_text ?>
            </div>
            
            <div class="p-5 border-b border-gray-100 border-dashed">
                <div class="text-xs text-gray-400 font-medium mb-1">KODE VOUCHER</div>
                <div class="text-2xl font-extrabold text-theme-evergreen tracking-wider uppercase"><?= htmlspecialchars($p['kode_promo']) ?></div>
                
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-black <?= $is_active ? 'text-theme-leaf' : 'text-gray-400' ?>">
                        <?= $p['tipe_diskon'] === 'persen' ? floatval($p['nilai_diskon']) . '%' : 'Rp ' . number_format($p['nilai_diskon'], 0, ',', '.') ?>
                    </span>
                    <span class="text-xs font-bold text-gray-400 uppercase">Diskon</span>
                </div>
            </div>
            
            <div class="p-4 bg-gray-50/50 space-y-2 text-xs text-gray-500 font-medium">
                <div class="flex justify-between">
                    <span>Min. Belanja</span>
                    <span class="font-bold text-gray-700">Rp <?= number_format($p['min_belanja'], 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Sisa Kuota</span>
                    <span class="font-bold text-gray-700"><?= $p['kuota'] ?> <span class="font-normal text-gray-400">kali</span></span>
                </div>
                <div class="flex justify-between">
                    <span>Berlaku</span>
                    <span class="font-bold text-gray-700"><?= date('d M', strtotime($p['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($p['tanggal_selesai'])) ?></span>
                </div>
            </div>
            
            <div class="flex divide-x divide-gray-100 border-t border-gray-100 bg-white">
                <button @click="showModal = true; modalMode = 'edit'; formData = <?= htmlspecialchars(json_encode($p)) ?>" 
                        class="flex-1 py-3 text-xs font-bold text-gray-500 hover:text-theme-leaf hover:bg-theme-bg transition-colors">Edit</button>
                <form action="promo.php" method="POST" class="flex-1 flex m-0" onsubmit="return confirm('Yakin ingin menghapus promo ini secara permanen?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Form -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition style="display:none">
        <div @click.stop class="bg-white rounded-3xl p-6 md:p-8 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-extrabold text-theme-evergreen mb-6" x-text="modalMode === 'add' ? 'Buat Promo Baru' : 'Edit Promo'"></h3>
            
            <form action="promo.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" :value="modalMode">
                <input type="hidden" name="id" x-model="formData.id">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Kode Voucher</label>
                    <input type="text" name="kode_promo" x-model="formData.kode_promo" required style="text-transform: uppercase;"
                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-bold tracking-wider"
                           placeholder="Contoh: OPENING20">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tipe Diskon</label>
                        <select name="tipe_diskon" x-model="formData.tipe_diskon" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                            <option value="persen">Persentase (%)</option>
                            <option value="nominal">Nominal (Rp)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Nilai Diskon</label>
                        <input type="number" step="0.01" name="nilai_diskon" x-model="formData.nilai_diskon" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-bold">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Min. Belanja (Rp)</label>
                        <input type="number" name="min_belanja" x-model="formData.min_belanja" value="0" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Kuota Penggunaan</label>
                        <input type="number" name="kuota" x-model="formData.kuota" value="100" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" x-model="formData.tanggal_mulai" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" x-model="formData.tanggal_selesai" required
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                    </div>
                </div>

                <div x-show="modalMode === 'edit'">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Status Promo</label>
                    <select name="status" x-model="formData.status" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                
                <div class="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                    <button type="button" @click="showModal = false" class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors">Batal</button>
                    <button type="submit" class="flex-1 py-3 rounded-xl bg-theme-sage text-white font-bold hover:bg-theme-leaf transition-colors shadow-md shadow-theme-sage/30">Simpan Promo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
$page_title = 'Manajemen Inventaris & Bahan Baku';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Handle aksi CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $nama = trim($_POST['nama_bahan']);
            $satuan = trim($_POST['satuan']);
            $harga_beli = (float)$_POST['harga_beli'];
            $stok = (float)$_POST['stok_sekarang'];
            
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM bahan_baku WHERE nama_bahan = ?");
            $stmtCek->execute([$nama]);
            if ($stmtCek->fetch()) throw new Exception("Bahan baku sudah terdaftar!");
            
            $pdo->prepare("INSERT INTO bahan_baku (nama_bahan, satuan, harga_beli, stok_sekarang) VALUES (?,?,?,?)")
                ->execute([$nama, $satuan, $harga_beli, $stok]);
            $_SESSION['success'] = "Bahan baku berhasil ditambahkan.";
            
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $nama = trim($_POST['nama_bahan']);
            $satuan = trim($_POST['satuan']);
            $harga_beli = (float)$_POST['harga_beli'];
            
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM bahan_baku WHERE nama_bahan = ? AND id != ?");
            $stmtCek->execute([$nama, $id]);
            if ($stmtCek->fetch()) throw new Exception("Nama bahan sudah dipakai!");
            
            $pdo->prepare("UPDATE bahan_baku SET nama_bahan=?, satuan=?, harga_beli=? WHERE id=?")
                ->execute([$nama, $satuan, $harga_beli, $id]);
            $_SESSION['success'] = "Bahan baku berhasil diperbarui.";
            
        } elseif ($action === 'restock') {
            $id = (int)$_POST['id'];
            $tambah = (float)$_POST['tambah_stok'];
            
            $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang + ? WHERE id=?")
                ->execute([$tambah, $id]);
            $_SESSION['success'] = "Stok berhasil ditambahkan.";
            
        } elseif ($action === 'adjust') {
            $id = (int)$_POST['id'];
            $stok_fisik = (float)$_POST['stok_fisik'];
            
            // Ambil stok sistem untuk info
            $stmt = $pdo->prepare("SELECT stok_sekarang, satuan FROM bahan_baku WHERE id=?");
            $stmt->execute([$id]);
            $b = $stmt->fetch();
            $selisih = $stok_fisik - $b['stok_sekarang'];
            $kata_selisih = $selisih >= 0 ? "Surplus +" : "Defisit ";
            
            $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = ? WHERE id=?")
                ->execute([$stok_fisik, $id]);
            $_SESSION['success'] = "Opname berhasil! Stok disesuaikan menjadi " . floatval($stok_fisik) . " " . $b['satuan'] . " (" . $kata_selisih . floatval($selisih) . ").";
            
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            // SMART DELETE PROTECTION: Cek apakah digunakan di resep
            $stmtCek = $pdo->prepare("SELECT m.nama_menu FROM resep_menu rm JOIN menu m ON rm.menu_id = m.id WHERE rm.bahan_id = ? LIMIT 1");
            $stmtCek->execute([$id]);
            if ($menuDipakai = $stmtCek->fetchColumn()) {
                throw new Exception("Proteksi Aktif: Bahan ini tidak bisa dihapus karena sedang digunakan dalam resep '$menuDipakai'.");
            }
            
            $pdo->prepare("DELETE FROM bahan_baku WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = "Bahan baku berhasil dihapus.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: inventaris.php");
    exit;
}

// Sekarang baru aman load header (output HTML)
requireRole(['admin']);

require_once __DIR__ . '/../../includes/header.php';
$bahan = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="inventoryApp()" class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen tracking-tight">Inventaris</h1>
            <p class="text-gray-500 mt-0.5 text-sm font-medium">Kelola bahan baku dan otomatisasi fitur.</p>
        </div>
        <button @click="openAdd()" class="flex items-center gap-2 px-5 py-2.5 bg-theme-ocean text-white rounded-xl font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 w-full sm:w-auto justify-center hover-lift">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Tambah Bahan
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-xl bg-theme-bg text-theme-leaf font-bold flex items-center gap-2 border border-theme-sage/20 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-xl bg-red-50 text-red-600 font-bold flex items-center gap-2 border border-red-100 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Smart Widgets / Filters -->
    <?php
    $total = count($bahan);
    $kritis = count(array_filter($bahan, fn($b) => $b['stok_sekarang'] <= 5));
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        
        <!-- Total Items Filter -->
        <button @click="filterMode = 'all'" 
                class="relative flex flex-col p-6 rounded-3xl border transition-all duration-300 text-left overflow-hidden group"
                :class="filterMode === 'all' ? 'bg-theme-evergreen border-theme-evergreen shadow-lg shadow-theme-evergreen/20' : 'bg-white border-gray-100 hover:border-gray-300'">
            <div class="absolute right-[-10%] top-[-20%] w-32 h-32 bg-white/5 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
            <div class="flex justify-between items-start w-full">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest mb-1" :class="filterMode === 'all' ? 'text-white/80' : 'text-gray-400'">Total Bahan</div>
                    <div class="text-4xl font-black" :class="filterMode === 'all' ? 'text-white' : 'text-theme-evergreen'"><?= $total ?></div>
                </div>
                <div class="p-3 rounded-2xl" :class="filterMode === 'all' ? 'bg-white/10 text-white' : 'bg-gray-50 text-gray-400'">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
            </div>
            <div class="mt-4 text-xs font-medium" :class="filterMode === 'all' ? 'text-white/60' : 'text-gray-400'">Lihat semua bahan</div>
        </button>

        <!-- Low Stock Alert Filter -->
        <button @click="filterMode = 'critical'" 
                class="relative flex flex-col p-6 rounded-3xl border transition-all duration-300 text-left overflow-hidden group hover-lift"
                :class="filterMode === 'critical' ? 'bg-theme-coral border-theme-coral shadow-lg shadow-theme-coral/20' : 'bg-white border-gray-100 hover:border-theme-coral/50'">
            <div class="absolute right-[-10%] top-[-20%] w-32 h-32 bg-white/10 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
            <div class="flex justify-between items-start w-full z-10">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest mb-1 flex items-center gap-2" :class="filterMode === 'critical' ? 'text-white/80' : 'text-gray-400'">
                        <?php if($kritis > 0): ?><span class="w-2 h-2 bg-theme-coral rounded-full animate-pulse" :class="filterMode === 'critical' ? 'bg-white' : 'bg-theme-coral'"></span><?php endif; ?>
                        Stok Mau Habis!
                    </div>
                    <div class="text-4xl font-black" :class="filterMode === 'critical' ? 'text-white' : 'text-theme-coral'"><?= $kritis ?></div>
                </div>
                <div class="p-3 rounded-2xl" :class="filterMode === 'critical' ? 'bg-white/20 text-white' : 'bg-theme-coral/10 text-theme-coral'">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
            </div>
            <div class="mt-4 text-xs font-medium" :class="filterMode === 'critical' ? 'text-white/80' : 'text-gray-400'">Bahan dengan stok <= 5</div>
        </button>

    </div>

    <!-- Main Table Container -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden flex flex-col">
        
        <!-- Controls Bar -->
        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <h2 class="font-bold text-theme-evergreen text-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-theme-sage" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Daftar Bahan
            </h2>
            
            <!-- Live Search -->
            <div class="relative w-full md:w-80">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Cari nama bahan..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage text-sm font-medium transition-shadow placeholder-gray-400 shadow-sm">
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto min-h-[400px]">
            <table class="w-full text-left">
                <thead class="bg-white border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Nama Bahan</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Harga Beli</th>
                        <th class="px-6 py-4 text-right">Sisa Stok</th>
                        <th class="px-6 py-4 text-center">Satuan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50/80">
                    
                    <!-- Empty State -->
                    <tr x-show="filteredBahan.length === 0" style="display:none">
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-700 mb-1">Bahan tidak ditemukan</h3>
                            <p class="text-sm text-gray-400">Coba sesuaikan pencarian atau filter Anda.</p>
                            <button @click="searchQuery = ''; filterMode = 'all'" class="mt-4 px-4 py-2 bg-theme-bg text-theme-leaf font-bold text-sm rounded-lg hover:bg-theme-sage hover:text-white transition-colors">Hapus Filter</button>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <template x-for="b in filteredBahan" :key="b.id">
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <!-- Name -->
                            <td class="px-6 py-4">
                                <div class="font-bold text-theme-evergreen text-sm flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 border border-gray-100 flex-shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    </div>
                                    <span x-text="b.nama_bahan"></span>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4">
                                <template x-if="b.stok_sekarang <= 5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-extrabold uppercase tracking-wider bg-theme-coral/10 text-theme-coral border border-theme-coral/20 shadow-sm">
                                        <span class="w-1.5 h-1.5 bg-theme-coral rounded-full animate-pulse"></span>
                                        Kritis
                                    </span>
                                </template>
                                <template x-if="b.stok_sekarang > 5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-extrabold uppercase tracking-wider bg-theme-ocean/10 text-theme-ocean border border-theme-ocean/20 shadow-sm">
                                        <span class="w-1.5 h-1.5 bg-theme-ocean rounded-full"></span>
                                        Aman
                                    </span>
                                </template>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <span class="text-sm font-bold text-gray-700" x-text="formatRupiah(b.harga_beli)"></span>
                            </td>

                            <!-- Stock -->
                            <td class="px-6 py-4 text-right">
                                <span class="text-xl font-black" :class="b.stok_sekarang <= 5 ? 'text-red-500' : 'text-theme-evergreen'" x-text="parseFloat(b.stok_sekarang)"></span>
                            </td>

                            <!-- Unit -->
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest" x-text="b.satuan"></span>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <!-- Primary Fast Action: Restock -->
                                    <button @click="openRestock(b)" class="px-4 py-2 bg-theme-ocean/10 text-theme-ocean hover:bg-theme-ocean hover:text-white rounded-xl text-xs font-bold transition-all shadow-sm border border-theme-ocean/20 flex items-center gap-1.5 hover-lift">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                        Restock
                                    </button>

                                    <!-- Kebab Menu for Edit/Delete -->
                                    <div x-data="{ openKebab: false }" class="relative inline-block text-left">
                                        <button @click.stop="openKebab = !openKebab" @click.outside="openKebab = false" class="p-2 rounded-xl text-gray-400 hover:text-theme-ocean hover:bg-theme-ocean/10 transition-colors border border-transparent hover:border-theme-ocean/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                        </button>
                                        
                                        <!-- Dropdown -->
                                        <div x-show="openKebab" style="display:none" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                            <button @click="openEdit(b); openKebab = false" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2 border-b border-gray-50">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit Detail
                                            </button>
                                            
                                            <button @click="openAdjust(b); openKebab = false" class="w-full text-left px-4 py-3 text-xs font-bold text-theme-sun hover:bg-theme-sun/10 transition-colors flex items-center gap-2 border-b border-gray-50">
                                                <svg class="w-4 h-4 text-theme-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                                Opname (Sesuaikan Stok)
                                            </button>
                                            
                                            <form method="POST" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus bahan ini secara permanen?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" :value="b.id">
                                                <button type="submit" class="w-full text-left px-4 py-3 text-xs font-bold text-theme-coral hover:bg-theme-coral/10 transition-colors flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-theme-coral" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Hapus Bahan
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════ MODAL: Add/Edit/Restock ═══════════════════ -->
    <div x-show="modal" @keydown.escape.window="modal=false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-theme-evergreen/40 backdrop-blur-md"
         x-transition style="display:none">
        <div @click.stop class="bg-white/95 glass rounded-3xl p-6 md:p-8 w-full max-w-sm shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-extrabold tracking-tight" 
                    :class="modalMode === 'restock' ? 'text-theme-ocean' : (modalMode === 'adjust' ? 'text-theme-sun' : 'text-theme-evergreen')"
                    x-text="modalMode === 'add' ? 'Tambah Bahan Baru' : (modalMode === 'restock' ? 'Restock Bahan' : (modalMode === 'adjust' ? 'Opname Stok' : 'Edit Detail Bahan'))"></h3>
                <button @click="modal=false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form action="inventaris.php" method="POST" class="space-y-5">
                <input type="hidden" name="action" :value="modalMode">
                <input type="hidden" name="id" x-model="form.id">
                
                <!-- Fields for Add/Edit -->
                <div x-show="['add', 'edit'].includes(modalMode)">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nama Bahan</label>
                    <input type="text" name="nama_bahan" x-model="form.nama_bahan" :required="['add', 'edit'].includes(modalMode)"
                           placeholder="Cth: Biji Kopi Arabica"
                           class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-bold text-theme-evergreen transition-all placeholder-gray-300">
                </div>
                
                <div x-show="['add', 'edit'].includes(modalMode)">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Satuan</label>
                    <input type="text" name="satuan" x-model="form.satuan" :required="['add', 'edit'].includes(modalMode)"
                           placeholder="Cth: Gram, ml, Pcs"
                           class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium transition-all placeholder-gray-300">
                </div>
                
                <div x-show="['add', 'edit'].includes(modalMode)">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Harga Beli (Modal per Satuan)</label>
                    <input type="number" step="0.01" name="harga_beli" x-model="form.harga_beli" :required="['add', 'edit'].includes(modalMode)"
                           placeholder="Cth: 15000"
                           class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-medium transition-all placeholder-gray-300">
                </div>
                
                <div x-show="modalMode === 'add'">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Stok Awal</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="stok_sekarang" x-model="form.stok_sekarang" placeholder="0"
                               class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-extrabold text-lg text-theme-evergreen transition-all pr-16">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm uppercase" x-text="form.satuan"></span>
                    </div>
                </div>
                
                <!-- Fields for Restock -->
                <div x-show="modalMode === 'restock'" style="display:none">
                    <div class="p-5 bg-theme-ocean/10 rounded-2xl mb-5 border border-theme-ocean/20 flex flex-col items-center text-center">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-theme-ocean shadow-sm mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        </div>
                        <div class="text-[10px] uppercase tracking-widest font-extrabold text-theme-ocean mb-1">Stok Masuk Untuk:</div>
                        <div class="text-xl font-black text-theme-ocean" x-text="form.nama_bahan"></div>
                    </div>
                    
                    <label class="block text-sm font-bold text-gray-700 mb-2">Jumlah Tambahan</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="tambah_stok" value="" placeholder="0" :required="modalMode === 'restock'"
                               class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-ocean/30 focus:border-theme-ocean outline-none font-black text-theme-ocean text-2xl transition-all pr-16 text-center">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm uppercase" x-text="form.satuan"></span>
                    </div>
                </div>
                
                <!-- Fields for Adjust Stock (Opname) -->
                <div x-show="modalMode === 'adjust'" style="display:none">
                    <div class="p-5 bg-theme-sun/10 rounded-2xl mb-5 border border-theme-sun/20 flex flex-col items-center text-center">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-theme-sun shadow-sm mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        </div>
                        <div class="text-[10px] uppercase tracking-widest font-extrabold text-theme-sun mb-1">Stok Tercatat Sistem:</div>
                        <div class="text-xl font-black text-theme-sun"><span x-text="parseFloat(form.stok_sekarang)"></span> <span class="text-xs ml-1 uppercase" x-text="form.satuan"></span></div>
                    </div>
                    
                    <label class="block text-sm font-bold text-gray-700 mb-2">Hitungan Fisik Nyata (Real Stock)</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="stok_fisik" value="" placeholder="0" :required="modalMode === 'adjust'"
                               class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sun/30 focus:border-theme-sun outline-none font-black text-theme-evergreen text-2xl transition-all pr-16 text-center">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm uppercase" x-text="form.satuan"></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-3 font-medium text-center">Masukkan jumlah fisik barang yang Anda temukan di gudang. Sistem akan otomatis menghitung selisih surplus/defisit.</p>
                </div>
                
                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="modal=false" class="flex-1 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors hover-lift">Batal</button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl font-bold transition-all shadow-lg text-white hover-lift"
                            :class="modalMode === 'restock' ? 'bg-theme-ocean hover:bg-theme-ocean-light shadow-theme-ocean/30' : (modalMode === 'adjust' ? 'bg-theme-sun hover:bg-theme-sun-light text-theme-evergreen shadow-theme-sun/30' : 'bg-theme-sage hover:bg-theme-leaf shadow-theme-sage/30')">
                        <span x-text="modalMode === 'restock' ? 'Konfirmasi Restock' : (modalMode === 'adjust' ? 'Sinkronkan Opname' : 'Simpan Perubahan')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('inventoryApp', () => ({
        bahan: <?= json_encode($bahan) ?>,
        
        // State
        searchQuery: '',
        filterMode: 'all', // 'all' or 'critical'
        
        // Modal State
        modal: false,
        modalMode: 'add',
        form: { id: '', nama_bahan: '', satuan: 'Gram', harga_beli: 0, stok_sekarang: 0 },

        get filteredBahan() {
            return this.bahan.filter(b => {
                const matchMode = this.filterMode === 'all' || (this.filterMode === 'critical' && b.stok_sekarang <= 5);
                const matchSearch = b.nama_bahan.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchMode && matchSearch;
            });
        },

        openAdd() {
            this.modalMode = 'add';
            this.form = { id: '', nama_bahan: '', satuan: 'Gram', harga_beli: 0, stok_sekarang: 0 };
            this.modal = true;
        },

        openEdit(b) {
            this.modalMode = 'edit';
            this.form = { id: b.id, nama_bahan: b.nama_bahan, satuan: b.satuan, harga_beli: b.harga_beli, stok_sekarang: b.stok_sekarang };
            this.modal = true;
        },

        openRestock(b) {
            this.modalMode = 'restock';
            this.form = { id: b.id, nama_bahan: b.nama_bahan, satuan: b.satuan, harga_beli: b.harga_beli, stok_sekarang: b.stok_sekarang };
            this.modal = true;
        },

        openAdjust(b) {
            this.modalMode = 'adjust';
            this.form = { id: b.id, nama_bahan: b.nama_bahan, satuan: b.satuan, harga_beli: b.harga_beli, stok_sekarang: b.stok_sekarang };
            this.modal = true;
        },
        
        formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

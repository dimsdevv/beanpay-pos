<?php
$page_title = 'Menu & Kategori';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);
requireCsrfToken();

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─── KATEGORI ───────────────────────────────────────────────
    if ($action === 'add_kategori') {
        $nama = trim($_POST['nama_kategori']);
        if ($nama) {
            $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)")->execute([$nama]);
            $_SESSION['success'] = "Sip! Kategori \"$nama\" berhasil ditambah.";
        }
    }
    if ($action === 'edit_kategori') {
        $id = (int)$_POST['id'];
        $nama = trim($_POST['nama_kategori']);
        $pdo->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?")->execute([$nama, $id]);
        $_SESSION['success'] = "Kategori berhasil diperbarui ya.";
    }
    if ($action === 'delete_kategori') {
        $id = (int)$_POST['id'];
        // Category Shield: Cek apakah kategori masih digunakan menu
        $check = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE kategori_id = ? AND is_active = 1");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Wah, Kategori ini nggak bisa dihapus karena masih ada menu yang menggunakannya.";
        } else {
            $stmtNama = $pdo->prepare("SELECT nama_kategori FROM kategori WHERE id = ?");
            $stmtNama->execute([$id]);
            $namaKategori = $stmtNama->fetchColumn();
            $pdo->prepare("DELETE FROM kategori WHERE id = ?")->execute([$id]);
            logAuditAction('delete_kategori', 'kategori', $id, $namaKategori ? "Kategori: $namaKategori" : null);
            $_SESSION['success'] = "Kategori berhasil dihapus dengan aman.";
        }
    }

    // ─── MENU ────────────────────────────────────────────────────
    if ($action === 'add_menu') {
        $nama_menu     = trim($_POST['nama_menu']);
        $kategori_id   = (int)$_POST['kategori_id'];
        $harga         = (float)$_POST['harga'];
        $status        = $_POST['status'] ?? 'tersedia';
        $gambar_name   = null;

        try {
            $gambar_name = handleMenuImageUpload($_FILES['gambar'] ?? null);
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: menu.php'); exit;
        }

        $pdo->prepare("INSERT INTO menu (kategori_id, nama_menu, harga, gambar, status) VALUES (?,?,?,?,?)")
            ->execute([$kategori_id, $nama_menu, $harga, $gambar_name, $status]);
        $_SESSION['success'] = "Mantap! Menu \"$nama_menu\" berhasil dibuat.";
    }

    if ($action === 'edit_menu') {
        $id          = (int)$_POST['id'];
        $nama_menu   = trim($_POST['nama_menu']);
        $kategori_id = (int)$_POST['kategori_id'];
        $harga       = (float)$_POST['harga'];
        $status      = $_POST['status'] ?? 'tersedia';
        $stmtOld = $pdo->prepare("SELECT gambar FROM menu WHERE id = ?");
        $stmtOld->execute([$id]);
        $old_gambar = $stmtOld->fetchColumn();
        $gambar_name = $old_gambar;

        try {
            $new_filename = handleMenuImageUpload($_FILES['gambar'] ?? null);
            if ($new_filename) {
                deleteMenuImage($old_gambar);
                $gambar_name = $new_filename;
            }
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: menu.php'); exit;
        }

        $pdo->prepare("UPDATE menu SET kategori_id=?, nama_menu=?, harga=?, gambar=?, status=? WHERE id=?")
            ->execute([$kategori_id, $nama_menu, $harga, $gambar_name, $status, $id]);
        $_SESSION['success'] = "Menu \"$nama_menu\" berhasil diperbarui.";
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE menu SET status = IF(status='tersedia','habis','tersedia') WHERE id=?")->execute([$id]);
        $_SESSION['success'] = "Status ketersediaan menu berhasil diubah.";
    }

    if ($action === 'delete_menu') {
        $id = (int)$_POST['id'];
        $stmtMenu = $pdo->prepare("SELECT nama_menu FROM menu WHERE id = ?");
        $stmtMenu->execute([$id]);
        $namaMenu = $stmtMenu->fetchColumn();
        // SAFE ARCHIVING: Alih-alih DELETE, kita set is_active = 0
        $pdo->prepare("UPDATE menu SET is_active = 0 WHERE id = ?")->execute([$id]);
        logAuditAction('archive_menu', 'menu', $id, $namaMenu ? "Menu: $namaMenu" : null);
        $_SESSION['success'] = "Menu berhasil diarsipkan (Tenang, laporan penjualan lamanya tetap aman kok!).";
    }

    header('Location: menu.php');
    exit;
}

// Sekarang baru aman load header (output HTML)

require_once __DIR__ . '/../../includes/header.php';
// ─── FETCH DATA ───────────────────────────────────────────────
$kategoris = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Auto-Sold Out Check (missing_ingredients > 0 jika bahan kurang dari resep)
$menus = $pdo->query("
    SELECT m.*, k.nama_kategori,
    (SELECT COUNT(*) FROM resep_menu rm 
     JOIN bahan_baku b ON rm.bahan_id = b.id 
     WHERE rm.menu_id = m.id AND b.stok_sekarang < rm.jumlah_dibutuhkan) as missing_ingredients
    FROM menu m 
    JOIN kategori k ON m.kategori_id = k.id 
    WHERE m.is_active = 1
    ORDER BY k.nama_kategori, m.nama_menu
")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="menuApp()" class="space-y-6">

    <!-- Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen tracking-tight">Manajer Menu</h1>
            <p class="text-gray-500 mt-1 text-sm font-medium">Atur semua menu, kategori, dan ketersediaan stok di sini.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button @click="modalManageKategori = true" class="px-4 py-2.5 bg-white border border-gray-200 text-theme-twilight rounded-xl font-bold hover:bg-theme-twilight/5 transition-colors shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-theme-twilight" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                Kelola Kategori
            </button>
            <button @click="openAddMenu()" class="px-5 py-2.5 bg-theme-ocean text-white rounded-xl font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 hover-lift flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Tambah Menu
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-xl bg-theme-bg text-theme-leaf font-bold flex items-center gap-2 border border-theme-sage/20 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-xl bg-red-50 text-red-600 font-bold flex items-center gap-2 border border-red-100 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Box -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden flex flex-col">
        
        <!-- Controls Bar (Filter & Search) -->
        <div class="p-4 md:p-6 border-b border-gray-100 flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-gray-50/50">
            
            <!-- Category Tabs -->
            <div class="flex overflow-x-auto pb-2 lg:pb-0 hide-scrollbar w-full lg:w-auto gap-2">
                <button @click="activeKat = 'all'" 
                        class="px-4 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all duration-300"
                        :class="activeKat === 'all' ? 'bg-theme-evergreen text-white shadow-md' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'">
                    Semua Kategori
                </button>
                <template x-for="kat in categories" :key="kat.id">
                    <button @click="activeKat = kat.id" 
                            class="px-4 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all duration-300"
                            :class="activeKat == kat.id ? 'bg-theme-evergreen text-white shadow-md' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'"
                            x-text="kat.nama_kategori">
                    </button>
                </template>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full lg:w-72 flex-shrink-0">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Cari nama menu..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage text-sm font-medium transition-shadow placeholder-gray-400 shadow-sm">
            </div>
        </div>

        <!-- Menu Grid -->
        <div class="p-6 bg-white min-h-[400px]">
            
            <!-- Empty State -->
            <div x-show="filteredMenus.length === 0" style="display:none" class="flex flex-col items-center justify-center py-16 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-theme-bg rounded-full flex items-center justify-center text-theme-leaf mb-4">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-theme-evergreen mb-1">Wah, menu tidak ditemukan</h3>
                <p class="text-gray-400 text-sm max-w-sm mx-auto">Kami tidak bisa menemukan menu yang cocok dengan pencarian atau kategori yang Anda pilih.</p>
                <button x-show="searchQuery !== ''" @click="searchQuery = ''" class="mt-4 px-4 py-2 text-sm font-bold text-theme-sage hover:text-theme-leaf transition-colors">Hapus Pencarian</button>
            </div>

            <!-- Grid Content -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <template x-for="menu in filteredMenus" :key="menu.id">
                    <div class="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover-lift relative flex flex-col">
                        
                        <!-- Image -->
                        <div class="relative h-44 bg-gray-50 overflow-hidden flex-shrink-0">
                            <template x-if="menu.gambar">
                                <img :src="`<?= BASE_URL ?>/assets/images/${menu.gambar}`" :alt="menu.nama_menu" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            </template>
                            <template x-if="!menu.gambar">
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-theme-bg to-white group-hover:from-theme-sage/20 transition-colors duration-500">
                                    <svg class="w-16 h-16 text-theme-sage/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            </template>
                            
                            <!-- Badges Overlay -->
                            <div class="absolute inset-0 p-3 flex flex-col justify-between pointer-events-none">
                                <div class="flex justify-between items-start">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold tracking-wider bg-theme-sun/90 backdrop-blur-md text-white shadow-sm" x-text="menu.nama_kategori"></span>
                                    
                                    <!-- Kebab Menu Toggle (pointer events auto to allow click) -->
                                    <div x-data="{ openKebab: false }" class="relative pointer-events-auto">
                                        <button @click.stop="openKebab = !openKebab" @click.outside="openKebab = false" class="w-8 h-8 bg-white/90 backdrop-blur-md rounded-lg flex items-center justify-center text-gray-500 hover:text-theme-evergreen shadow-sm transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                        </button>
                                        
                                        <!-- Kebab Dropdown -->
                                        <div x-show="openKebab" x-transition.opacity.duration.200ms style="display:none" class="absolute right-0 mt-2 w-36 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                            <button @click="editMenu(menu); openKebab = false" class="w-full text-left px-4 py-2.5 text-xs font-bold text-gray-600 hover:bg-theme-bg hover:text-theme-leaf transition-colors flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Edit Detail
                                            </button>
                                            <form method="POST" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin mengarsipkan menu ini?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_menu">
                                                <input :value="menu.id" type="hidden" name="id">
                                                <button type="submit" class="w-full text-left px-4 py-2.5 text-xs font-bold text-red-500 hover:bg-red-50 transition-colors flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="p-5 flex-1 flex flex-col">
                            <h3 class="font-bold text-theme-evergreen text-base mb-1 line-clamp-1" x-text="menu.nama_menu"></h3>
                            <div class="text-theme-leaf font-extrabold text-lg mb-4" x-text="formatRupiah(menu.harga)"></div>
                            
                            <!-- Main Actions -->
                            <div class="mt-auto grid grid-cols-2 gap-2">
                                <a :href="`resep.php?menu_id=${menu.id}`" class="py-2.5 text-center text-xs font-bold rounded-xl bg-theme-ocean/10 text-theme-ocean hover:bg-theme-ocean hover:text-white transition-colors flex items-center justify-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    Atur Resep
                                </a>
                                <!-- Auto-Sold Out Indicator / Toggle -->
                                <template x-if="menu.missing_ingredients > 0">
                                    <div class="w-full py-2.5 text-xs font-bold rounded-xl flex items-center justify-center gap-1 bg-theme-coral text-white shadow-lg shadow-theme-coral/20 cursor-not-allowed opacity-90" title="Bahan baku di inventaris habis!">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Stok Habis!
                                    </div>
                                </template>
                                
                                <template x-if="menu.missing_ingredients == 0">
                                    <form method="POST" class="m-0">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" :value="menu.id">
                                        <button type="submit" class="w-full py-2.5 text-xs font-bold rounded-xl transition-colors flex items-center justify-center gap-1"
                                                :class="menu.status === 'tersedia' ? 'bg-theme-sage text-white hover:bg-theme-leaf shadow-md shadow-theme-sage/20' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                            <div class="w-1.5 h-1.5 rounded-full" :class="menu.status === 'tersedia' ? 'bg-white' : 'bg-gray-400'"></div>
                                            <span x-text="menu.status === 'tersedia' ? 'Tersedia' : 'Kosong'"></span>
                                        </button>
                                    </form>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ═══════════════════ MODAL: Manage Categories ═══════════════════ -->
    <div x-data="{ activeTab: 'list', newCatName: '', editCatId: null, editCatName: '' }" 
         x-show="modalManageKategori" 
         @keydown.escape.window="modalManageKategori = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-theme-evergreen/40 backdrop-blur-md"
         x-transition style="display:none">
        
        <div @click.stop class="bg-white/95 glass rounded-3xl w-full max-w-md shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-xl font-bold text-theme-evergreen">Kelola Kategori</h3>
                <button @click="modalManageKategori = false" class="p-2 text-gray-400 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                
                <!-- Add New Form -->
                <form method="POST" class="mb-6 pb-6 border-b border-dashed border-gray-200">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_kategori">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tambah Kategori Baru</label>
                    <div class="flex gap-2">
                        <input type="text" name="nama_kategori" required placeholder="Cth: Minuman Dingin" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage font-medium text-sm">
                        <button type="submit" class="px-4 py-2.5 bg-theme-evergreen text-white font-bold text-sm rounded-xl hover:bg-theme-leaf transition-colors">Simpan</button>
                    </div>
                </form>

                <!-- List Categories -->
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Daftar Kategori Saat Ini</label>
                <div class="space-y-2">
                    <template x-for="kat in categories" :key="kat.id">
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 hover:border-theme-sage/30 hover:bg-theme-bg/30 transition-colors group">
                            
                            <!-- View Mode -->
                            <div x-show="editCatId !== kat.id" class="font-bold text-sm text-theme-evergreen" x-text="kat.nama_kategori"></div>
                            <div x-show="editCatId !== kat.id" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" @click="editCatId = kat.id; editCatName = kat.nama_kategori" class="p-1.5 text-gray-400 hover:text-theme-sage hover:bg-theme-bg rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <form method="POST" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_kategori">
                                    <input type="hidden" name="id" :value="kat.id">
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>

                            <!-- Edit Mode -->
                            <form x-show="editCatId === kat.id" method="POST" class="flex-1 flex gap-2" style="display:none">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="edit_kategori">
                                <input type="hidden" name="id" :value="kat.id">
                                <input type="text" name="nama_kategori" x-model="editCatName" required class="flex-1 px-3 py-1.5 bg-white border border-theme-sage rounded-lg focus:outline-none text-sm font-bold text-theme-evergreen">
                                <button type="submit" class="p-1.5 bg-theme-sage text-white rounded-lg hover:bg-theme-leaf transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </button>
                                <button type="button" @click="editCatId = null" class="p-1.5 bg-gray-100 text-gray-500 rounded-lg hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </form>

                        </div>
                    </template>
                    <div x-show="categories.length === 0" class="text-center py-6 text-sm text-gray-400 font-medium">
                        Belum ada kategori yang dibuat.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════ MODAL: Add/Edit Menu ═══════════════════ -->
    <div x-show="modalMenu" @keydown.escape.window="modalMenu = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-theme-evergreen/40 backdrop-blur-md"
         x-transition style="display:none">
        <div @click.stop class="bg-white/95 glass rounded-3xl w-full max-w-xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 md:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-extrabold text-theme-evergreen tracking-tight" x-text="editingMenuId ? 'Edit Detail Menu' : 'Buat Menu Baru'"></h3>
                    <button @click="modalMenu = false" class="p-2 hover:bg-gray-100 text-gray-400 hover:text-gray-600 rounded-xl transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" :value="editingMenuId ? 'edit_menu' : 'add_menu'">
                    <input type="hidden" name="id" :value="editingMenuId">
                    <?= csrfField() ?>

                    <!-- Image Preview -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Foto Menu <span class="text-gray-400 font-medium font-normal">(Opsional, maks 2MB)</span></label>
                        <div class="relative h-40 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 overflow-hidden cursor-pointer hover:border-theme-sage/50 hover:bg-theme-bg/30 transition-all group" @click="$refs.fileInput.click()">
                            <img x-show="imagePreview" :src="imagePreview" class="w-full h-full object-cover">
                            <div x-show="!imagePreview" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 group-hover:text-theme-sage">
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="text-sm font-bold">Klik untuk unggah foto</span>
                            </div>
                        </div>
                        <input type="file" name="gambar" x-ref="fileInput" class="hidden" accept="image/*" @change="previewImage($event)">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Nama Menu</label>
                        <input type="text" name="nama_menu" x-model="menuForm.nama_menu" required placeholder="Cth: Kopi Gula Aren" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-theme-evergreen placeholder-gray-300">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Kategori</label>
                            <select name="kategori_id" x-model="menuForm.kategori_id" required class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-gray-700 appearance-none">
                                <option value="">Pilih Kategori...</option>
                                <template x-for="kat in categories" :key="kat.id">
                                    <option :value="kat.id" x-text="kat.nama_kategori"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Status Awal</label>
                            <select name="status" x-model="menuForm.status" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-gray-700 appearance-none">
                                <option value="tersedia">Tersedia</option>
                                <option value="habis">Kosong</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Harga Jual</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-extrabold text-lg">Rp</span>
                            <input type="number" name="harga" x-model="menuForm.harga" required min="0" placeholder="0" class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-extrabold text-xl text-theme-evergreen">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="modalMenu = false" class="flex-1 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors">Batal Deh</button>
                        <button type="submit" class="flex-1 py-3.5 rounded-xl bg-theme-ocean text-white font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 hover-lift">
                            <span x-text="editingMenuId ? 'Simpan Perubahan' : 'Buat Menu Sekarang'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
// Format Rupiah Helper
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(angka);
}

document.addEventListener('alpine:init', () => {
    Alpine.data('menuApp', () => ({
        // Data sources from PHP
        menus: <?= json_encode($menus) ?>,
        categories: <?= json_encode($kategoris) ?>,
        
        // State
        activeKat: 'all',
        searchQuery: '',
        
        modalManageKategori: false,
        
        modalMenu: false,
        editingMenuId: null,
        oldGambar: null,
        imagePreview: null,
        menuForm: { nama_menu: '', kategori_id: '', harga: '', status: 'tersedia' },

        // Computed property for filtering and searching
        get filteredMenus() {
            return this.menus.filter(m => {
                const matchKat = this.activeKat === 'all' || m.kategori_id == this.activeKat;
                const matchSearch = m.nama_menu.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchKat && matchSearch;
            });
        },

        openAddMenu() {
            this.editingMenuId = null;
            this.oldGambar = null;
            this.imagePreview = null;
            this.menuForm = { nama_menu: '', kategori_id: '', harga: '', status: 'tersedia' };
            this.modalMenu = true;
        },

        editMenu(m) {
            this.editingMenuId = m.id;
            this.oldGambar = m.gambar;
            this.imagePreview = m.gambar ? `<?= BASE_URL ?>/assets/images/${m.gambar}` : null;
            this.menuForm = {
                nama_menu: m.nama_menu,
                kategori_id: m.kategori_id,
                harga: m.harga,
                status: m.status
            };
            this.modalMenu = true;
        },

        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => { this.imagePreview = e.target.result; };
                reader.readAsDataURL(file);
            }
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

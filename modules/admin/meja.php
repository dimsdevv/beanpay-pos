<?php
$page_title = 'Manajemen Meja';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

// Handle aksi CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $nomor = trim($_POST['nomor_meja']);
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM meja WHERE nomor_meja = ?");
            $stmtCek->execute([$nomor]);
            if ($stmtCek->fetch()) throw new Exception("Nomor meja sudah ada!");
            
            $pdo->prepare("INSERT INTO meja (nomor_meja, status) VALUES (?, 'kosong')")->execute([$nomor]);
            $_SESSION['success'] = "Meja berhasil ditambahkan.";
            
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $nomor = trim($_POST['nomor_meja']);
            
            // Cek duplikat
            $stmtCek = $pdo->prepare("SELECT id FROM meja WHERE nomor_meja = ? AND id != ?");
            $stmtCek->execute([$nomor, $id]);
            if ($stmtCek->fetch()) throw new Exception("Nomor meja sudah dipakai!");
            
            $pdo->prepare("UPDATE meja SET nomor_meja = ? WHERE id = ?")->execute([$nomor, $id]);
            $_SESSION['success'] = "Nomor meja berhasil diperbarui.";
            
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            // Cek status meja
            $stmtCek = $pdo->prepare("SELECT status FROM meja WHERE id = ?");
            $stmtCek->execute([$id]);
            $meja = $stmtCek->fetch();
            
            if ($meja && $meja['status'] === 'terisi') {
                throw new Exception("Meja sedang digunakan, tidak bisa dihapus!");
            }
            
            $pdo->prepare("DELETE FROM meja WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = "Meja berhasil dihapus.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: meja.php");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$mejas = $pdo->query("SELECT * FROM meja ORDER BY CAST(nomor_meja AS UNSIGNED) ASC, nomor_meja ASC")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="tableApp()" class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen tracking-tight">Table Management</h1>
            <p class="text-gray-500 mt-0.5 text-sm font-medium">Map out your restaurant and track table availability in real-time.</p>
        </div>
        <button @click="openAdd()" class="flex items-center gap-2 px-5 py-2.5 bg-theme-ocean text-white rounded-xl font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 w-full sm:w-auto justify-center hover-lift">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Add New Table
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

    <!-- Stats & Filters -->
    <?php
    $total = count($mejas);
    $kosong = count(array_filter($mejas, fn($m) => $m['status'] === 'kosong'));
    $terisi = $total - $kosong;
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <button @click="filterStatus = 'all'" 
                class="relative flex flex-col p-5 rounded-3xl border transition-all duration-300 text-left overflow-hidden group hover-lift"
                :class="filterStatus === 'all' ? 'bg-theme-evergreen border-theme-evergreen shadow-lg shadow-theme-evergreen/20' : 'bg-white border-gray-100 hover:border-gray-300'">
            <div class="absolute right-[-10%] top-[-10%] w-24 h-24 bg-white/5 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
            <div class="text-3xl font-black mb-1" :class="filterStatus === 'all' ? 'text-white' : 'text-theme-evergreen'"><?= $total ?></div>
            <div class="text-sm font-bold uppercase tracking-wider" :class="filterStatus === 'all' ? 'text-white/80' : 'text-gray-400'">Total Tables</div>
        </button>

        <button @click="filterStatus = 'kosong'" 
                class="relative flex flex-col p-5 rounded-3xl border transition-all duration-300 text-left overflow-hidden group hover-lift"
                :class="filterStatus === 'kosong' ? 'bg-theme-ocean border-theme-ocean shadow-lg shadow-theme-ocean/20' : 'bg-white border-gray-100 hover:border-theme-ocean/50'">
            <div class="absolute right-[-10%] top-[-10%] w-24 h-24 bg-white/10 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
            <div class="text-3xl font-black mb-1" :class="filterStatus === 'kosong' ? 'text-white' : 'text-theme-ocean'"><?= $kosong ?></div>
            <div class="text-sm font-bold uppercase tracking-wider flex items-center gap-2" :class="filterStatus === 'kosong' ? 'text-white/80' : 'text-gray-400'">
                <span class="w-2 h-2 rounded-full" :class="filterStatus === 'kosong' ? 'bg-white' : 'bg-theme-ocean'"></span>
                Available
            </div>
        </button>

        <button @click="filterStatus = 'terisi'" 
                class="relative flex flex-col p-5 rounded-3xl border transition-all duration-300 text-left overflow-hidden group hover-lift"
                :class="filterStatus === 'terisi' ? 'bg-theme-sun border-theme-sun shadow-lg shadow-theme-sun/20' : 'bg-white border-gray-100 hover:border-theme-sun/50'">
            <div class="absolute right-[-10%] top-[-10%] w-24 h-24 bg-white/10 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
            <div class="text-3xl font-black mb-1" :class="filterStatus === 'terisi' ? 'text-theme-evergreen' : 'text-theme-sun'"><?= $terisi ?></div>
            <div class="text-sm font-bold uppercase tracking-wider flex items-center gap-2" :class="filterStatus === 'terisi' ? 'text-theme-evergreen/80' : 'text-gray-400'">
                <span class="w-2 h-2 rounded-full" :class="filterStatus === 'terisi' ? 'bg-theme-evergreen' : 'bg-theme-sun'"></span>
                In Use
            </div>
        </button>
    </div>

    <!-- Main Content Box -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden">
        
        <!-- Controls Bar -->
        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <h2 class="font-bold text-theme-evergreen text-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-theme-sage" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Floor Plan
            </h2>
            
            <!-- Search Bar -->
            <div class="relative w-full md:w-72">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Search table number..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage text-sm font-medium transition-shadow placeholder-gray-400 shadow-sm">
            </div>
        </div>

        <!-- Grid Container -->
        <div class="p-8 min-h-[400px]">
            
            <!-- Empty State -->
            <div x-show="filteredMejas.length === 0" style="display:none" class="flex flex-col items-center justify-center py-12 text-center animate-[fadeIn_0.5s_ease-out]">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-4">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-theme-evergreen mb-1">No tables found</h3>
                <p class="text-gray-400 text-sm max-w-sm mx-auto">Try adjusting your search query or status filter.</p>
                <button x-show="searchQuery !== '' || filterStatus !== 'all'" @click="searchQuery = ''; filterStatus = 'all'" class="mt-4 px-4 py-2 text-sm font-bold bg-theme-bg text-theme-leaf rounded-lg hover:bg-theme-sage hover:text-white transition-colors">Clear All Filters</button>
            </div>

            <!-- Table Grid Visual -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6">
                <template x-for="m in filteredMejas" :key="m.id">
                    <div class="relative group">
                        
                        <!-- Actions Dropdown (Kebab Menu) -->
                        <div x-data="{ openKebab: false }" class="absolute top-2 right-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="openKebab = !openKebab" @click.outside="openKebab = false" class="w-7 h-7 bg-white/90 backdrop-blur-md rounded-lg flex items-center justify-center text-gray-500 hover:text-theme-evergreen shadow-sm transition-colors border border-gray-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                            </button>
                            
                            <div x-show="openKebab" style="display:none" x-transition class="absolute right-0 mt-2 w-32 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
                                <button @click="openEdit(m); openKebab = false" class="w-full text-left px-4 py-2.5 text-xs font-bold text-gray-600 hover:bg-theme-bg hover:text-theme-leaf transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    Edit
                                </button>
                                <form method="POST" class="m-0" onsubmit="return confirm('Hapus meja ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" :value="m.id">
                                    <!-- Disable delete if table is in use -->
                                    <template x-if="m.status === 'terisi'">
                                        <button type="button" class="w-full text-left px-4 py-2.5 text-xs font-bold text-gray-300 flex items-center gap-2 cursor-not-allowed" title="Table is in use">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </template>
                                    <template x-if="m.status !== 'terisi'">
                                        <button type="submit" class="w-full text-left px-4 py-2.5 text-xs font-bold text-theme-coral hover:bg-theme-coral/10 transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </template>
                                </form>
                            </div>
                        </div>

                        <!-- Table Card Visual -->
                        <div class="aspect-square rounded-[2rem] border-4 flex flex-col items-center justify-center p-4 transition-all duration-300 relative shadow-sm hover-lift"
                             :class="m.status === 'terisi' ? 'bg-theme-sun/10 border-theme-sun/30 shadow-theme-sun/10' : 'bg-theme-bg/50 border-theme-ocean/20 hover:border-theme-ocean/50 shadow-theme-ocean/5'">
                            
                            <!-- Chairs (Visual effect) -->
                            <div class="absolute -top-1.5 w-8 h-2 rounded-full" :class="m.status === 'terisi' ? 'bg-theme-sun' : 'bg-theme-ocean/30'"></div>
                            <div class="absolute -bottom-1.5 w-8 h-2 rounded-full" :class="m.status === 'terisi' ? 'bg-theme-sun' : 'bg-theme-ocean/30'"></div>
                            <div class="absolute -left-1.5 w-2 h-8 rounded-full" :class="m.status === 'terisi' ? 'bg-theme-sun' : 'bg-theme-ocean/30'"></div>
                            <div class="absolute -right-1.5 w-2 h-8 rounded-full" :class="m.status === 'terisi' ? 'bg-theme-sun' : 'bg-theme-ocean/30'"></div>

                            <span class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Table</span>
                            <span class="text-3xl font-black truncate w-full text-center" :class="m.status === 'terisi' ? 'text-theme-sun' : 'text-theme-evergreen'" x-text="m.nomor_meja"></span>
                            
                            <!-- Pulse indicator for occupied -->
                            <template x-if="m.status === 'terisi'">
                                <div class="mt-2 px-2 py-1 bg-theme-sun text-theme-evergreen rounded-full text-[9px] font-extrabold tracking-wider uppercase flex items-center gap-1 shadow-sm">
                                    <span class="w-1.5 h-1.5 bg-theme-evergreen rounded-full animate-pulse"></span>
                                    In Use
                                </div>
                            </template>
                            <template x-if="m.status !== 'terisi'">
                                <div class="mt-2 px-2 py-1 bg-theme-ocean/10 text-theme-ocean rounded-full text-[9px] font-extrabold tracking-wider uppercase">
                                    Available
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ═══════════════════ MODAL: Add/Edit Meja ═══════════════════ -->
    <div x-show="modal" @keydown.escape.window="modal=false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-theme-evergreen/40 backdrop-blur-md"
         x-transition style="display:none">
        <div @click.stop class="bg-white/95 glass rounded-3xl p-6 md:p-8 w-full max-w-sm shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-extrabold text-theme-evergreen tracking-tight" x-text="isEdit ? 'Edit Table' : 'Add New Table'"></h3>
                <button @click="modal=false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form action="meja.php" method="POST" class="space-y-5">
                <input type="hidden" name="action" :value="isEdit ? 'edit' : 'add'">
                <input type="hidden" name="id" x-model="form.id">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Table Number/Name</label>
                    <input type="text" name="nomor_meja" x-model="form.nomor_meja" required
                           class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none font-bold text-lg text-theme-evergreen text-center transition-all placeholder-gray-300"
                           placeholder="e.g. 01, VIP-1, OUT-A">
                    <p class="text-xs text-gray-400 mt-2 text-center">Must be unique across all tables.</p>
                </div>
                
                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="modal=false" class="flex-1 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors hover-lift">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl bg-theme-ocean text-white font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 hover-lift">
                        <span x-text="isEdit ? 'Save Changes' : 'Create Table'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tableApp', () => ({
        mejas: <?= json_encode($mejas) ?>,
        
        // State
        searchQuery: '',
        filterStatus: 'all',
        
        // Modal State
        modal: false,
        isEdit: false,
        form: { id: '', nomor_meja: '' },

        get filteredMejas() {
            return this.mejas.filter(m => {
                const matchStatus = this.filterStatus === 'all' || m.status === this.filterStatus;
                const matchSearch = m.nomor_meja.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchStatus && matchSearch;
            });
        },

        openAdd() {
            this.isEdit = false;
            this.form = { id: '', nomor_meja: '' };
            this.modal = true;
        },

        openEdit(m) {
            this.isEdit = true;
            this.form = { id: m.id, nomor_meja: m.nomor_meja };
            this.modal = true;
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

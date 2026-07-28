<?php
$page_title = 'Data Pelanggan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin']);
requireCsrfToken();

// Ensure tables exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pelanggan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_lengkap VARCHAR(100) NOT NULL,
        telepon VARCHAR(20) DEFAULT NULL,
        catatan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nama (nama_lengkap)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS hutang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id INT NOT NULL,
        kasir_id INT NOT NULL,
        rincian TEXT NOT NULL,
        nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        status ENUM('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
        metode_bayar ENUM('cash','qris','transfer') DEFAULT NULL,
        bukti_transfer VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        lunas_at DATETIME DEFAULT NULL,
        INDEX idx_pelanggan (pelanggan_id),
        INDEX idx_status (status),
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
        FOREIGN KEY (kasir_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_pelanggan') {
            $nama = trim($_POST['nama_lengkap']);
            $telepon = trim($_POST['telepon']);
            $catatan = trim($_POST['catatan']);
            
            if ($nama === '') throw new Exception('Nama pelanggan wajib diisi.');
            
            $pdo->prepare("INSERT INTO pelanggan (nama_lengkap, telepon, catatan) VALUES (?,?,?)")
                ->execute([$nama, $telepon, $catatan]);
            $_SESSION['success'] = "Pelanggan \"$nama\" berhasil ditambahkan.";
        }
        
        elseif ($action === 'edit_pelanggan') {
            $id = (int)$_POST['id'];
            $nama = trim($_POST['nama_lengkap']);
            $telepon = trim($_POST['telepon']);
            $catatan = trim($_POST['catatan']);
            
            if ($nama === '') throw new Exception('Nama pelanggan wajib diisi.');
            
            $check = $pdo->prepare("SELECT id FROM pelanggan WHERE nama_lengkap = ? AND id != ?");
            $check->execute([$nama, $id]);
            if ($check->fetch()) throw new Exception("Nama pelanggan sudah digunakan!");
            
            $pdo->prepare("UPDATE pelanggan SET nama_lengkap=?, telepon=?, catatan=? WHERE id=?")
                ->execute([$nama, $telepon, $catatan, $id]);
            $_SESSION['success'] = "Pelanggan berhasil diperbarui.";
        }
        
        elseif ($action === 'delete_pelanggan') {
            $id = (int)$_POST['id'];
            
            // Check if pelanggan has hutang
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM hutang WHERE pelanggan_id = ? AND status = 'belum_lunas'");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Pelanggan tidak bisa dihapus karena masih memiliki hutang aktif.");
            }
            
            $stmt = $pdo->prepare("SELECT nama_lengkap FROM pelanggan WHERE id = ?");
            $stmt->execute([$id]);
            $nama = $stmt->fetchColumn();
            
            $pdo->prepare("DELETE FROM pelanggan WHERE id = ?")->execute([$id]);
            logAuditAction('delete_pelanggan', 'pelanggan', $id, $nama);
            $_SESSION['success'] = "Pelanggan berhasil dihapus.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: data_pelanggan.php");
    exit;
}

// Fetch pelanggan with hutang counts
$pelanggan = $pdo->query("
    SELECT p.*, 
        COUNT(h.id) as total_hutang,
        SUM(CASE WHEN h.status = 'belum_lunas' THEN 1 ELSE 0 END) as hutang_aktif,
        COALESCE(SUM(CASE WHEN h.status = 'belum_lunas' THEN h.nominal ELSE 0 END), 0) as total_belum_lunas
    FROM pelanggan p
    LEFT JOIN hutang h ON h.pelanggan_id = p.id
    GROUP BY p.id
    ORDER BY p.nama_lengkap ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="pelangganApp()"><div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Data Pelanggan</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Kelola data pelanggan untuk fitur hutang.</p>
        </div>
        <button @click="openAdd()" class="flex items-center gap-2 px-5 py-2.5 bg-vibe-primary text-white rounded-lg font-bold hover:bg-vibe-primary-container transition-colors active:scale-[0.99] w-full sm:w-auto justify-center hover-lift">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Tambah Pelanggan
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-xl bg-white border border-vibe-secondary/20 text-vibe-secondary font-bold flex items-center gap-2 animate-fade-in">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-xl bg-white border border-vibe-error/20 text-vibe-error font-bold flex items-center gap-2 animate-fade-in">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative w-full sm:w-72">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" x-model="search" placeholder="Cari nama pelanggan..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left">Nama</th>
                        <th class="px-5 py-3.5 text-left">Telepon</th>
                        <th class="px-5 py-3.5 text-center">Hutang Aktif</th>
                        <th class="px-5 py-3.5 text-right">Total Belum Lunas</th>
                        <th class="px-5 py-3.5 text-center">Total Riwayat</th>
                        <th class="px-5 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <template x-for="p in filteredPelanggan()" :key="p.id">
                        <tr class="hover:bg-vibe-surface-dim transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-sm text-vibe-on-surface" x-text="p.nama_lengkap"></td>
                            <td class="px-5 py-3.5 text-vibe-on-surface-variant" x-text="p.telepon || '—'"></td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-vibe-error/10 text-vibe-error border border-vibe-error/20" x-text="p.hutang_aktif"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-vibe-error" x-text="fmt(p.total_belum_lunas)"></td>
                            <td class="px-5 py-3.5 text-center text-sm text-vibe-on-surface-variant" x-text="p.total_hutang"></td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openEdit(p)" title="Ubah" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-container transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="confirmDelete(p)" title="Hapus" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error-container transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredPelanggan().length === 0">
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-vibe-on-surface-variant">Belum ada data pelanggan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form -->
    <div x-show="showForm" @keydown.escape.window="showForm=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" x-transition style="display:none">
    <div @click.stop class="bg-white rounded-xl w-full max-w-md border border-vibe-outline-variant max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-vibe-outline-variant shrink-0">
            <div>
                <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="form.id ? 'Ubah Pelanggan' : 'Tambah Pelanggan'"></h3>
                <p class="text-xs text-vibe-on-surface-variant">Isi data pelanggan.</p>
            </div>
            <button @click="showForm=false" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-md transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto min-h-0 px-6 py-5 space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Nama Lengkap <span class="text-vibe-error">*</span></label>
                <input type="text" x-model="form.nama_lengkap" placeholder="Nama pelanggan" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors" required>
            </div>
            
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Telepon</label>
                <input type="text" x-model="form.telepon" placeholder="08xx-xxxx-xxxx" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
            </div>
            
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Catatan</label>
                <textarea x-model="form.catatan" rows="3" placeholder="Catatan tambahan..." class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors resize-none"></textarea>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-vibe-outline-variant flex gap-3 shrink-0">
            <button type="button" @click="showForm=false" class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
            <button type="button" @click="submitForm()" :disabled="saving" class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99] disabled:opacity-60" x-text="saving ? 'Menyimpan…' : (form.id ? 'Simpan Perubahan' : 'Tambah Pelanggan')"></button>
        </div>
    </div>
</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pelangganApp', () => ({
        search: '',
        showForm: false,
        saving: false,
        pelangganData: <?= json_encode($pelanggan, JSON_UNESCAPED_SLASHES) ?>,
        
        form: {
            id: null,
            nama_lengkap: '',
            telepon: '',
            catatan: ''
        },

        fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },

        filteredPelanggan() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.pelangganData;
            return this.pelangganData.filter(p => p.nama_lengkap.toLowerCase().includes(q));
        },

        openAdd() {
            this.form = { id: null, nama_lengkap: '', telepon: '', catatan: '' };
            this.showForm = true;
        },
        
        openEdit(p) {
            this.form = {
                id: p.id,
                nama_lengkap: p.nama_lengkap,
                telepon: p.telepon || '',
                catatan: p.catatan || ''
            };
            this.showForm = true;
        },

        async submitForm() {
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', this.form.id ? 'edit_pelanggan' : 'add_pelanggan');
                fd.append('id', this.form.id || '');
                fd.append('nama_lengkap', this.form.nama_lengkap);
                fd.append('telepon', this.form.telepon);
                fd.append('catatan', this.form.catatan);

                const res = await fetch('data_pelanggan.php', { method: 'POST', body: fd });
                window.location.href = res.url;
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            } finally {
                this.saving = false;
            }
        },

        confirmDelete(p) {
            Swal.fire({
                icon: 'warning', title: 'Hapus pelanggan?',
                text: p.nama_lengkap + ' akan dihapus permanen.',
                showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                confirmButtonColor: '#DC2626'
            }).then(r => {
                if (r.isConfirmed) this.deletePelanggan(p.id);
            });
        },

        async deletePelanggan(id) {
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', 'delete_pelanggan');
                fd.append('id', id);
                const res = await fetch('data_pelanggan.php', { method: 'POST', body: fd });
                window.location.href = res.url;
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            }
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
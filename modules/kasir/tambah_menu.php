<?php
$page_title = 'Tambah Menu';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['kasir', 'admin']);
requireCsrfToken();

// Fetch categories & bahan baku
$kategoris = $pdo->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC")->fetchAll();
$bahanList = $pdo->query("SELECT id, nama_bahan AS nama, satuan FROM bahan_baku ORDER BY nama_bahan ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="tambahMenu()" class="max-w-3xl mx-auto">

    <!-- Back Navigation -->
    <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="btn-press inline-flex items-center gap-2 text-sm font-semibold text-vibe-on-surface-variant hover:text-vibe-on-surface transition-colors mb-6 group">
        <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke POS
    </a>

    <!-- Page Title -->
    <div class="mb-8">
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-vibe-primary/10 text-vibe-primary text-[11px] font-bold mb-3">Menu baru</div>
        <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight" x-text="form.id ? 'Ubah Menu' : 'Tambah Menu Baru'"></h1>
        <p class="text-sm text-vibe-on-surface-variant mt-1">Isi detail menu, harga jual bebas, lalu tambahkan resep bahan agar menu bisa dipesan.</p>
    </div>

    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6 mb-4">
        <h2 class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-5">Informasi Menu</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Nama Menu</label>
                <input type="text" x-model="form.nama_menu" placeholder="Contoh: Es Kopi Susu" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-medium transition-colors">
            </div>
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Kategori</label>
                <select x-model="form.kategori_id" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-medium transition-colors cursor-pointer">
                    <option value="">— Pilih kategori —</option>
                    <template x-for="kat in kategoris" :key="kat.id">
                        <option :value="kat.id" x-text="kat.nama_kategori"></option>
                    </template>
                </select>
                <div class="flex gap-2 mt-2">
                    <input type="text" x-model="newKategoriName" placeholder="Kategori baru"
                           class="flex-1 px-3 py-2 bg-vibe-surface-dim border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-primary text-xs transition-colors">
                    <button type="button" @click="addKategori()" :disabled="savingKategori || !newKategoriName.trim()"
                            class="px-3 py-2 rounded-lg bg-vibe-on-surface text-white text-xs font-bold disabled:opacity-50 transition-colors">Tambah</button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Harga Jual</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-vibe-on-surface-variant text-sm font-black">Rp</span>
                    <input type="number" step="1" min="0" x-model="form.harga" placeholder="0"
                           class="w-full pl-11 pr-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-black transition-colors">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6 mb-4">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest">Resep Bahan</h2>
                <p class="text-sm text-vibe-on-surface-variant mt-1">Minimal satu bahan wajib diisi agar menu bisa dipesan.</p>
            </div>
            <button type="button" @click="addItem()" class="px-3 py-2 rounded-lg bg-white border border-vibe-outline-variant text-vibe-primary text-xs font-bold hover:border-vibe-primary transition-colors">+ Bahan</button>
        </div>
        <div class="space-y-2">
            <template x-for="(item, index) in form.items" :key="index">
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_120px_60px_36px] gap-2 items-center bg-vibe-surface-dim/50 border border-vibe-outline-variant/70 rounded-xl p-3">
                    <div>
                        <label class="block sm:hidden text-[10px] font-bold text-vibe-on-surface-variant uppercase mb-1">Bahan</label>
                        <select x-model="item.bahan_id" @change="pickBahan(index)" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-primary text-sm font-semibold">
                            <option value="">Pilih bahan</option>
                            <template x-for="bahan in bahanList" :key="bahan.id">
                                <option :value="bahan.id" x-text="bahan.nama"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block sm:hidden text-[10px] font-bold text-vibe-on-surface-variant uppercase mb-1">Jumlah</label>
                        <input type="number" x-model="item.jumlah" min="0" step="0.01" placeholder="0"
                               class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-primary text-sm font-bold transition-colors">
                    </div>
                    <div class="px-2 py-2.5 bg-white border border-vibe-outline-variant rounded-lg text-sm font-bold text-vibe-on-surface-variant text-center" x-text="item.satuan || '—'"></div>
                    <button type="button" @click="removeItem(index)" class="h-10 w-9 rounded-lg text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error-container transition-colors">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
        <div x-show="formIssue" class="mt-3 text-xs font-bold text-vibe-error" x-text="formIssue"></div>
    </div>

    <div class="flex gap-3">
        <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="btn-press flex-1 py-3 rounded-xl border border-vibe-outline-variant/60 text-vibe-on-surface-variant font-bold text-sm hover:text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors text-center">Batal</a>
        <button type="button" @click="save()" :disabled="saving" class="btn-press flex-1 py-3 rounded-xl bg-vibe-primary text-white font-black text-sm hover:bg-vibe-primary-container transition-colors disabled:opacity-60 shadow-sm shadow-vibe-primary/20">
            <span x-show="!saving" x-text="form.id ? 'Simpan Perubahan' : 'Simpan Menu'"></span>
            <span x-show="saving" class="inline-flex items-center justify-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Menyimpan...
            </span>
        </button>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tambahMenu', () => ({
        kategoris: <?= json_encode($kategoris) ?>,
        bahanList: <?= json_encode($bahanList) ?>,
        saving: false,
        savingKategori: false,
        newKategoriName: '',
        form: { nama_menu: '', kategori_id: '', harga: '', status: 'tersedia', items: [] },

        init() {
            this.addItem();
        },

        addItem() {
            this.form.items.push({ bahan_id: '', jumlah: '', satuan: '' });
        },
        removeItem(index) {
            this.form.items.splice(index, 1);
            if (this.form.items.length === 0) this.addItem();
        },
        pickBahan(index) {
            const item = this.form.items[index];
            const bahan = this.bahanList.find(b => String(b.id) === String(item.bahan_id));
            item.satuan = bahan ? bahan.satuan : '';
        },
        get formIssue() {
            if (!this.form.nama_menu.trim()) return 'Nama menu wajib diisi.';
            if (!this.form.kategori_id) return 'Pilih kategori terlebih dahulu.';
            if ((parseFloat(this.form.harga) || 0) <= 0) return 'Harga jual harus lebih dari 0.';
            const validItems = this.form.items.filter(i => i.bahan_id && (parseFloat(i.jumlah) || 0) > 0);
            if (validItems.length === 0) return 'Tambahkan minimal satu bahan agar menu bisa dipesan.';
            return '';
        },
        async save() {
            if (this.saving) return;
            if (this.formIssue) {
                Swal.fire({ icon: 'warning', title: 'Data belum lengkap', text: this.formIssue, confirmButtonColor: '#0F172A' });
                return;
            }
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fd.append('action', 'save_menu');
                fd.append('nama_menu', this.form.nama_menu.trim());
                fd.append('kategori_id', this.form.kategori_id);
                fd.append('harga', this.form.harga);
                fd.append('status', this.form.status || 'tersedia');
                fd.append('items', JSON.stringify(this.form.items.map(i => ({ bahan_id: i.bahan_id, jumlah: i.jumlah }))));
                const res = await fetch('proses_tambah_menu.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal menyimpan menu.');
                Swal.fire({ icon: 'success', title: 'Menu tersimpan', text: data.message, timer: 1300, showConfirmButton: false });
                setTimeout(() => window.location.href = '<?= BASE_URL ?>/modules/kasir/index.php', 700);
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: e.message, confirmButtonColor: '#0F172A' });
            } finally {
                this.saving = false;
            }
        },
        async addKategori() {
            const nama = this.newKategoriName.trim();
            if (!nama || this.savingKategori) return;
            this.savingKategori = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fd.append('action', 'add_kategori');
                fd.append('nama_kategori', nama);
                const res = await fetch('proses_tambah_menu.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal membuat kategori.');
                this.kategoris.push({ id: data.id, nama_kategori: nama });
                this.newKategoriName = '';
                this.form.kategori_id = data.id;
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: e.message, confirmButtonColor: '#0F172A' });
            } finally {
                this.savingKategori = false;
            }
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

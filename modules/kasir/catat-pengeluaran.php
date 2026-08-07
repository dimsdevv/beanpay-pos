<?php
$page_title = 'Catat Pengeluaran Belanja';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['kasir']);
requireCsrfToken();

// ---------------------------------------------------------------
// Ensure tables exist (same as admin)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pengeluaran (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        supplier VARCHAR(120) DEFAULT NULL,
        kategori ENUM('pembukaan','operasional','lainnya') NOT NULL DEFAULT 'operasional',
        keterangan TEXT,
        metode_bayar ENUM('cash','qris','transfer') NOT NULL DEFAULT 'cash',
        bukti VARCHAR(255) DEFAULT NULL,
        total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        stok_updated TINYINT(1) NOT NULL DEFAULT 0,
        input_by INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tanggal (tanggal),
        INDEX idx_kategori (kategori),
        INDEX idx_input (input_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pengeluaran_item (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pengeluaran_id INT NOT NULL,
        bahan_id INT DEFAULT NULL,
        nama_bahan VARCHAR(120) NOT NULL,
        qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        satuan VARCHAR(50) DEFAULT '',
        satuan_beli VARCHAR(50) DEFAULT '',
        konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        harga_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran(id) ON DELETE CASCADE,
        INDEX idx_bahan (bahan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS anggaran_bulan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        periode CHAR(7) NOT NULL UNIQUE,
        nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Auto-patch existing tables
$cols = $pdo->query("SHOW COLUMNS FROM pengeluaran_item")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('satuan_beli', $cols)) {
    $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN satuan_beli VARCHAR(50) DEFAULT '' AFTER satuan");
    $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000 AFTER satuan_beli");
    $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER konversi");
    $pdo->exec("UPDATE pengeluaran_item SET qty_beli = qty, satuan_beli = satuan, konversi = 1 WHERE qty_beli = 0 AND qty > 0");
}

// ---------------------------------------------------------------
// Load bahan list for dropdown
$bahanList = $pdo->query("
    SELECT b.id, b.nama_bahan AS nama, b.satuan, b.harga_beli,
        (SELECT pi.harga_satuan FROM pengeluaran_item pi
         JOIN pengeluaran p2 ON pi.pengeluaran_id = p2.id
         WHERE pi.bahan_id = b.id ORDER BY p2.tanggal DESC, p2.id DESC LIMIT 1) AS last_price
    FROM bahan_baku b
    ORDER BY b.nama_bahan ASC
")->fetchAll();

// ---------------------------------------------------------------
// Edit mode: load existing data if ?id= is provided (only own records)
$editData = null;
$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE id = ? AND kasir_id = ?");
    $stmt->execute([$editId, $_SESSION['user_id']]);
    $editData = $stmt->fetch();
    if ($editData) {
        $stmtI = $pdo->prepare("SELECT bahan_id, nama_bahan, qty_beli, satuan_beli, konversi, qty, satuan, harga_satuan, subtotal FROM pengeluaran_item WHERE pengeluaran_id = ?");
        $stmtI->execute([$editId]);
        $editData['items'] = $stmtI->fetchAll();
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Emil Kowalski: Buttons must feel responsive */
.btn-press {
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease-out, border-color 160ms ease-out;
}
.btn-press:active {
    transform: scale(0.97);
}

/* Card item entrance stagger */
@keyframes itemSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.item-enter {
    animation: itemSlideIn 250ms cubic-bezier(0.23, 1, 0.32, 1) forwards;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .btn-press { transition: none; }
    .item-enter { animation: none; opacity: 1; }
}

/* Draft indicator */
.draft-badge {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<div x-data="catatPengeluaran()" class="max-w-3xl mx-auto space-y-0">

    <!-- Back Navigation -->
    <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="btn-press inline-flex items-center gap-2 text-sm font-semibold text-vibe-on-surface-variant hover:text-vibe-on-surface transition-colors mb-6 group">
        <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke POS
    </a>

    <!-- Page Title -->
    <div class="mb-8">
        <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight" x-text="editId ? 'Ubah Pengeluaran' : 'Catat Pengeluaran Belanja'"></h1>
        <p class="text-sm text-vibe-on-surface-variant mt-1">Catat belanja stok bahan. Stok & HPP otomatis terupdate.</p>
    </div>

    <!-- Draft Indicator -->
    <div x-show="hasDraft" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between animate-fade-in">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-medium text-amber-800">Ada draf tersimpan otomatis. <button type="button" @click="restoreDraft()" class="text-amber-600 hover:underline font-medium">Pulihkan</button></span>
        </div>
        <button type="button" @click="clearDraft()" class="text-xs text-amber-600 hover:underline">Hapus draf</button>
    </div>

    <!-- ═══════════ SECTION 1: Info Pengeluaran ═══════════ -->
    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6 mb-4">
        <h2 class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-5">Informasi Pengeluaran</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Tanggal</label>
                <input type="date" x-model="form.tanggal" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-on-surface text-sm font-medium transition-colors">
            </div>
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Kategori</label>
                <select x-model="form.kategori" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-on-surface text-sm font-medium transition-colors cursor-pointer">
                    <option value="operasional">Operasional</option>
                    <option value="pembukaan">Belanja Pembukaan</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Supplier / Toko / Penerima</label>
                <input type="text" x-model="form.supplier" placeholder="Mis. Toko Makmur / PLN" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-on-surface text-sm transition-colors placeholder-vibe-outline-variant">
            </div>
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Metode Bayar</label>
                <select x-model="form.metode_bayar" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-on-surface text-sm font-medium transition-colors cursor-pointer">
                    <option value="cash">Tunai</option>
                    <option value="qris">QRIS</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ═══════════ SECTION 2: Item Bahan ═══════════ -->
    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6 mb-4">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest">Daftar Item Bahan</h2>
            <button type="button" @click="addItem()" class="btn-press inline-flex items-center gap-1.5 text-sm font-bold text-vibe-primary hover:text-vibe-on-surface transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Item
            </button>
        </div>

        <div class="space-y-4">
            <template x-for="(it, idx) in form.items" :key="idx">
                <div class="item-enter bg-vibe-surface-dim/30 border border-vibe-outline-variant/40 rounded-xl p-5 relative group transition-colors hover:border-vibe-outline-variant/70">

                    <!-- Item number badge + delete -->
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-vibe-primary/10 text-vibe-primary text-xs font-black" x-text="idx + 1"></span>
                        <button type="button" @click="removeItem(idx)" class="btn-press p-2 rounded-lg text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error-container/50 transition-colors" title="Hapus item">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>

                    <!-- Row 1: Bahan selector -->
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Bahan Baku</label>
                        <select x-model="it.bahan_id" @change="pickBahan(idx)" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-medium transition-colors">
                            <option value="">— Pilih bahan dari inventaris —</option>
                            <template x-for="b in bahanList" :key="b.id">
                                <option :value="b.id" x-text="b.nama + ' (' + b.satuan + ')'"></option>
                            </template>
                            <option value="__custom">⇥ Ketik baru (belum ada di inventaris)...</option>
                        </select>
                        <input x-show="it.bahan_id === '__custom'" type="text" x-model="it.nama_bahan" placeholder="Nama bahan baru" class="w-full mt-2 px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm transition-colors">
                    </div>

                    <!-- Row 2: Quantity + Purchase Unit -->
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Jumlah Beli</label>
                            <input type="number" step="0.01" min="0" x-model="it.qty_beli" @input="recalc(idx)" placeholder="Mis. 5" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-bold text-center transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Satuan Beli</label>
                            <input type="text" x-model="it.satuan_beli" placeholder="Mis. Kg, Dus, Karung" class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm transition-colors">
                        </div>
                    </div>

                    <!-- Row 3: Conversion -->
                    <div class="flex items-center gap-2.5 bg-vibe-surface-dim rounded-xl px-4 py-3 mb-3 border border-vibe-outline-variant/30">
                        <span class="text-xs text-vibe-on-surface-variant font-medium whitespace-nowrap">1</span>
                        <span class="text-xs font-bold text-vibe-on-surface" x-text="it.satuan_beli || '?'"></span>
                        <span class="text-xs text-vibe-on-surface-variant">=</span>
                        <input type="number" step="0.01" min="0" x-model="it.konversi" placeholder="1000" class="w-20 px-3 py-2 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-primary text-xs font-bold text-center transition-colors">
                        <span class="text-xs font-bold text-vibe-on-surface uppercase" x-text="it.satuan || 'Satuan Resep'"></span>
                    </div>

                    <!-- Row 4: Total Price -->
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Total Harga Item Ini</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-vibe-on-surface-variant font-medium">Rp</span>
                            <input type="number" step="1" min="0" x-model="it.subtotal" @input="recalcRev(idx)" placeholder="0" class="w-full pl-10 pr-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-primary text-sm font-bold transition-colors">
                        </div>
                    </div>

                    <!-- Summary Line -->
                    <div class="flex items-center justify-between bg-vibe-primary/5 rounded-lg px-4 py-2.5 border border-vibe-primary/10">
                        <div class="text-xs text-vibe-on-surface-variant">
                            Masuk gudang: <strong class="text-vibe-on-surface" x-text="((parseFloat(it.qty_beli)||0) * (parseFloat(it.konversi)||1)).toLocaleString('id-ID')"></strong>
                            <span x-text="it.satuan" class="font-bold uppercase text-vibe-on-surface"></span>
                        </div>
                        <div class="text-xs text-vibe-on-surface-variant" x-show="(parseFloat(it.qty_beli)||0) > 0 && (parseFloat(it.subtotal)||0) > 0">
                            Harga/<span x-text="it.satuan || 'unit'" class="font-bold"></span>:
                            <strong class="text-vibe-primary" x-text="'Rp ' + Math.round((parseFloat(it.subtotal)||0) / ((parseFloat(it.qty_beli)||0) * (parseFloat(it.konversi)||1))).toLocaleString('id-ID')"></strong>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div x-show="form.items.length === 0" class="text-center py-10 text-sm text-vibe-on-surface-variant bg-vibe-surface-dim/30 rounded-xl border border-dashed border-vibe-outline-variant">
                Belum ada item. Klik "Tambah Item" untuk mulai mencatat belanja.
            </div>
        </div>
    </div>

    <!-- ═══════════ SECTION 3: Opsi & Bukti ═══════════ -->
    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6 mb-4">
        <h2 class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest mb-5">Opsi Tambahan</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Stok checkbox -->
            <div>
                <label class="flex items-start gap-3 cursor-pointer select-none bg-vibe-surface-dim/50 rounded-xl px-4 py-3.5 border border-vibe-outline-variant/30 hover:border-vibe-outline-variant/60 transition-colors">
                    <input type="checkbox" x-model="form.stok_updated" class="w-4.5 h-4.5 accent-vibe-primary mt-0.5 shrink-0">
                    <div>
                        <span class="text-sm text-vibe-on-surface font-semibold block">Perbarui stok & harga modal</span>
                        <span class="text-xs text-vibe-on-surface-variant mt-0.5 block">Jumlah stok dan HPP bahan akan otomatis dihitung ulang (Weighted Average Cost).</span>
                    </div>
                </label>
            </div>

            <!-- Upload bukti -->
            <div>
                <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Upload Bukti Nota <span class="font-normal text-vibe-on-surface-variant">(opsional)</span></label>
                <input type="file" accept="image/*" @change="onBukti($event)" class="w-full text-xs text-vibe-on-surface-variant file:mr-3 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-vibe-surface-dim file:text-vibe-on-surface file:font-semibold file:cursor-pointer hover:file:bg-vibe-outline-variant transition-colors">
                <p class="text-xs text-vibe-on-surface-variant mt-1" x-show="form.buktiName" x-text="'Tersimpan: ' + form.buktiName"></p>
            </div>
        </div>

        <!-- Catatan -->
        <div class="mt-4">
            <label class="block text-sm font-semibold text-vibe-on-surface mb-1.5">Catatan <span class="font-normal text-vibe-on-surface-variant">(opsional)</span></label>
            <textarea x-model="form.keterangan" rows="2" placeholder="Tuliskan catatan tambahan jika ada..." class="w-full px-4 py-3 bg-white border border-vibe-outline-variant rounded-xl focus:outline-none focus:border-vibe-on-surface text-sm transition-colors resize-none placeholder-vibe-outline-variant"></textarea>
        </div>
    </div>

    <!-- ═══════════ SECTION 4: Grand Total + Actions ═══════════ -->
    <div class="bg-white border border-vibe-outline-variant/50 rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="text-xs font-bold text-vibe-on-surface-variant uppercase tracking-widest">Grand Total</div>
                <div class="text-3xl font-black text-vibe-on-surface mt-1 font-display tracking-tight" x-text="fmt(formTotal())"></div>
                <div class="text-xs text-vibe-on-surface-variant mt-1" x-text="form.items.filter(i => i.nama_bahan || (i.bahan_id && i.bahan_id !== '')).length + ' item bahan'"></div>
            </div>
            <div class="flex gap-3 w-full sm:w-auto">
                <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="btn-press flex-1 sm:flex-initial flex items-center justify-center py-3.5 px-6 rounded-xl border border-vibe-outline-variant/60 text-vibe-on-surface-variant font-bold text-sm hover:text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors">
                    Batal
                </a>
                <button type="button" @click="submitForm()" :disabled="saving" class="btn-press flex-1 sm:flex-initial flex items-center justify-center gap-2 py-3.5 px-8 rounded-xl bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors disabled:opacity-60" x-text="saving ? 'Menyimpan…' : (editId ? 'Simpan Perubahan' : 'Simpan Pengeluaran')">
                </button>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('catatPengeluaran', () => ({
        editId: <?= $editId ?>,
        saving: false,
        buktiFile: null,

        bahanList: <?= json_encode($bahanList, JSON_UNESCAPED_SLASHES) ?>,

        form: <?php if ($editData): ?>{
            id: <?= $editId ?>,
            tanggal: '<?= $editData['tanggal'] ?>',
            supplier: <?= json_encode($editData['supplier'] ?? '') ?>,
            kategori: '<?= $editData['kategori'] ?>',
            metode_bayar: '<?= $editData['metode_bayar'] ?>',
            keterangan: <?= json_encode($editData['keterangan'] ?? '') ?>,
            stok_updated: <?= $editData['stok_updated'] ? 'true' : 'false' ?>,
            buktiName: <?= json_encode($editData['bukti'] ?? '') ?>,
            items: <?= json_encode(array_map(function($it) {
                return [
                    'bahan_id' => $it['bahan_id'] ? (string)$it['bahan_id'] : '',
                    'nama_bahan' => $it['nama_bahan'],
                    'qty_beli' => $it['qty_beli'],
                    'satuan_beli' => $it['satuan_beli'],
                    'konversi' => $it['konversi'],
                    'satuan' => $it['satuan'],
                    'harga_satuan' => $it['harga_satuan'],
                    'subtotal' => $it['subtotal'],
                ];
            }, $editData['items']), JSON_UNESCAPED_SLASHES) ?>
        }<?php else: ?>{
            id: null,
            tanggal: '<?= date('Y-m-d') ?>',
            supplier: '',
            kategori: 'operasional',
            metode_bayar: 'cash',
            keterangan: '',
            stok_updated: true,
            buktiName: '',
            items: []
        }<?php endif; ?>,

        init() {
            this.loadDraft();
            if (this.form.items.length === 0) this.addItem();
        },

        fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },

        // ---- Draft persistence ----
        loadDraft() {
            if (!this.editId) {
                const draft = localStorage.getItem('kasir_pengeluaran_draft');
                if (draft) {
                    try {
                        const parsed = JSON.parse(draft);
                        if (parsed.items && parsed.items.length) {
                            this.form = { ...this.form, ...parsed };
                        }
                    } catch (e) {}
                }
            }
        },
        saveDraft() {
            if (!this.editId) {
                localStorage.setItem('kasir_pengeluaran_draft', JSON.stringify(this.form));
            }
        },
        clearDraft() {
            localStorage.removeItem('kasir_pengeluaran_draft');
            this.hasDraft = false;
        },
        restoreDraft() {
            this.loadDraft();
        },
        get hasDraft() {
            return !!localStorage.getItem('kasir_pengeluaran_draft');
        },

        fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },

        // ---- Item helpers ----
        addItem() {
            this.form.items.push({
                bahan_id: '', nama_bahan: '',
                qty_beli: '', satuan_beli: '',
                konversi: 1, satuan: '',
                harga_satuan: '', subtotal: ''
            });
            this.saveDraft();
        },
        removeItem(i) {
            this.form.items.splice(i, 1);
            if (this.form.items.length === 0) this.addItem();
            this.saveDraft();
        },
        pickBahan(i) {
            const it = this.form.items[i];
            if (it.bahan_id === '__custom') {
                it.nama_bahan = ''; it.satuan = ''; it.satuan_beli = '';
                it.konversi = 1; it.harga_satuan = ''; it.subtotal = '';
                return;
            }
            const b = this.bahanList.find(x => String(x.id) === String(it.bahan_id));
            if (b) {
                it.nama_bahan = b.nama;
                it.satuan = b.satuan;
                it.satuan_beli = b.satuan;
                it.konversi = 1;
                const last = (b.last_price !== null && b.last_price !== '') ? b.last_price : b.harga_beli;
                it.harga_satuan = last || '';
            }
            this.recalc(i);
        },
        recalc(i) {
            const it = this.form.items[i];
            const q = parseFloat(it.qty_beli) || 0;
            const h = parseFloat(it.harga_satuan) || 0;
            it.subtotal = Math.round(q * h) || '';
            this.saveDraft();
        },
        recalcRev(i) {
            const it = this.form.items[i];
            const sub = parseFloat(it.subtotal) || 0;
            const q = parseFloat(it.qty_beli) || 0;
            it.harga_satuan = q > 0 ? (sub / q) : 0;
            this.saveDraft();
        },
        formTotal() {
            return this.form.items.reduce((s, it) => s + (parseFloat(it.subtotal) || 0), 0);
        },
        onBukti(e) { this.buktiFile = e.target.files[0] || null; },

        // ---- Submit ----
        async submitForm() {
            const validItems = this.form.items.filter(i =>
                i.nama_bahan && parseFloat(i.qty_beli) > 0 && parseFloat(i.harga_satuan) > 0
            );
            if (validItems.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Item Belum Lengkap', text: 'Isi setidaknya satu item bahan dengan jumlah dan harga yang valid.' });
                return;
            }
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');
                fd.append('action', 'save');
                fd.append('id', this.form.id || '');
                fd.append('tanggal', this.form.tanggal);
                fd.append('supplier', this.form.supplier);
                fd.append('kategori', this.form.kategori);
                fd.append('metode_bayar', this.form.metode_bayar);
                fd.append('keterangan', this.form.keterangan);
                fd.append('stok_updated', this.form.stok_updated ? '1' : '0');
                const clean = this.form.items.map(it => ({
                    bahan_id: it.bahan_id && it.bahan_id !== '__custom' ? it.bahan_id : null,
                    nama_bahan: it.nama_bahan,
                    qty_beli: it.qty_beli,
                    satuan_beli: it.satuan_beli,
                    konversi: it.konversi,
                    satuan: it.satuan,
                    harga_satuan: it.harga_satuan
                }));
                fd.append('items', JSON.stringify(clean));
                if (this.buktiFile) fd.append('bukti', this.buktiFile);

                const res = await fetch('<?= BASE_URL ?>/modules/admin/proses_pengeluaran.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Gagal menyimpan.');
                this.clearDraft();
                await Swal.fire({ icon: 'success', title: 'Tersimpan!', text: data.message, timer: 1800, showConfirmButton: false });
                window.location.href = '<?= BASE_URL ?>/modules/kasir/index.php';
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: err.message });
            } finally {
                this.saving = false;
            }
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
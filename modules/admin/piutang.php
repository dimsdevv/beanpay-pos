<?php
$page_title = 'Piutang';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['admin', 'kasir']);
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
        pesanan_id INT DEFAULT NULL,
        rincian TEXT NOT NULL,
        nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        status ENUM('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
        metode_bayar ENUM('cash','qris','transfer','hutang') DEFAULT NULL,
        bukti_transfer VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        lunas_at DATETIME DEFAULT NULL,
        INDEX idx_pelanggan (pelanggan_id),
        INDEX idx_status (status),
        INDEX idx_pesanan (pesanan_id),
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
        FOREIGN KEY (kasir_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Migrasi kolom pesanan_id untuk tabel yang sudah ada
try {
    $pdo->query("SELECT pesanan_id FROM hutang LIMIT 0");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE hutang ADD COLUMN pesanan_id INT DEFAULT NULL AFTER kasir_id");
    $pdo->exec("ALTER TABLE hutang ADD INDEX idx_pesanan (pesanan_id)");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'bayar_hutang') {
            $id = (int)$_POST['id'];
            $metode = $_POST['metode_bayar'] ?? 'cash';
            
            // Validate hutang exists and is unpaid
            $stmt = $pdo->prepare("SELECT h.*, p.nama_lengkap FROM hutang h JOIN pelanggan p ON h.pelanggan_id = p.id WHERE h.id = ? AND h.status = 'belum_lunas'");
            $stmt->execute([$id]);
            $hutang = $stmt->fetch();
            
            if (!$hutang) throw new Exception('Hutang tidak ditemukan atau sudah lunas.');
            
            $buktiName = null;
            if ($metode === 'transfer' && !empty($_FILES['bukti']) && ($_FILES['bukti']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $buktiName = handleTransferUpload($_FILES['bukti'], 'tf_hutang');
            }
            
            ensureMetodePembayaranEnum();
            
            $pdo->prepare("UPDATE hutang SET status = 'lunas', metode_bayar = ?, bukti_transfer = ?, lunas_at = NOW() WHERE id = ?")
                ->execute([$metode, $buktiName, $id]);
            
            // Jika hutang berasal dari pesanan, update pembayaran agar omzet tercatat
            if (!empty($hutang['pesanan_id'])) {
                $pdo->prepare("UPDATE pembayaran SET metode_pembayaran = ?, jumlah_bayar = ?, kembalian = 0, waktu_bayar = NOW() WHERE pesanan_id = ? AND metode_pembayaran = 'hutang'")
                    ->execute([$metode, $hutang['nominal'], $hutang['pesanan_id']]);
                // Update sesi kasir pemasukan (gunakan kasir yang melunasi)
                $stmtSesi2 = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1");
                $stmtSesi2->execute([$_SESSION['user_id']]);
                if ($sesi2 = $stmtSesi2->fetch()) {
                    $pdo->prepare("UPDATE sesi_kasir SET total_pemasukan = total_pemasukan + ? WHERE id = ?")
                        ->execute([$hutang['nominal'], $sesi2['id']]);
                }
            }
            
            logAuditAction('bayar_hutang', 'hutang', $id, "Hutang {$hutang['nama_lengkap']} Rp " . number_format($hutang['nominal'], 0, ',', '.') . " lunas via $metode");
            $_SESSION['success'] = "Hutang berhasil dibayar.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: piutang.php");
    exit;
}

// Fetch all hutang
$filter = $_GET['filter'] ?? 'belum_lunas';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($filter === 'belum_lunas') {
    $where[] = "h.status = 'belum_lunas'";
} elseif ($filter === 'lunas') {
    $where[] = "h.status = 'lunas'";
}

if ($search !== '') {
    $where[] = "(p.nama_lengkap LIKE ? OR h.rincian LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$hutangList = $pdo->prepare("
    SELECT h.*, p.nama_lengkap, p.telepon, u.nama_lengkap AS kasir_nama
    FROM hutang h
    JOIN pelanggan p ON h.pelanggan_id = p.id
    JOIN users u ON h.kasir_id = u.id
    $whereSql
    ORDER BY h.created_at DESC
");
$hutangList->execute($params);
$hutangList = $hutangList->fetchAll();

// Summary
$totalBelumLunas = $pdo->query("SELECT COALESCE(SUM(nominal),0) FROM hutang WHERE status = 'belum_lunas'")->fetchColumn();
$totalLunas = $pdo->query("SELECT COALESCE(SUM(nominal),0) FROM hutang WHERE status = 'lunas'")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="piutangApp()" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Piutang</h1>
            <p class="text-sm text-vibe-on-surface-variant mt-0.5">Kelola semua hutang pelanggan.</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Belum Lunas</div>
            <div class="text-xl font-black text-vibe-error mt-1"><?= formatRupiah($totalBelumLunas) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Lunas</div>
            <div class="text-xl font-black text-vibe-secondary mt-1"><?= formatRupiah($totalLunas) ?></div>
        </div>
        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3.5">
            <div class="text-[10px] font-bold text-vibe-on-surface-variant uppercase tracking-wider">Total Transaksi</div>
            <div class="text-xl font-black text-vibe-on-surface mt-1"><?= count($hutangList) ?></div>
        </div>
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

    <!-- Filter & Search -->
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex gap-1 bg-vibe-surface-dim rounded-lg p-1">
            <a href="?filter=belum_lunas&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-md text-[11px] font-bold transition-colors <?= $filter === 'belum_lunas' ? 'bg-vibe-primary text-white' : 'text-vibe-on-surface-variant hover:text-vibe-on-surface' ?>">Belum Lunas</a>
            <a href="?filter=lunas&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-md text-[11px] font-bold transition-colors <?= $filter === 'lunas' ? 'bg-vibe-primary text-white' : 'text-vibe-on-surface-variant hover:text-vibe-on-surface' ?>">Lunas</a>
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-md text-[11px] font-bold transition-colors <?= $filter === 'all' ? 'bg-vibe-primary text-white' : 'text-vibe-on-surface-variant hover:text-vibe-on-surface' ?>">Semua</a>
        </div>
        <div class="relative w-full sm:w-72">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <form method="GET" class="w-full">
                <input type="hidden" name="filter" value="<?= $filter ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari pelanggan..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-vibe-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-widest border-b border-vibe-outline-variant bg-vibe-surface-dim">
                        <th class="px-5 py-3.5 text-left">Pelanggan</th>
                        <th class="px-5 py-3.5 text-left">Rincian</th>
                        <th class="px-5 py-3.5 text-right">Nominal</th>
                        <th class="px-5 py-3.5 text-center">Kasir</th>
                        <th class="px-5 py-3.5 text-center">Tanggal</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline/50">
                    <?php if (empty($hutangList)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-sm text-vibe-on-surface-variant">Tidak ada data hutang.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($hutangList as $h): ?>
                    <tr class="hover:bg-vibe-surface-dim transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-vibe-outline-variant bg-vibe-surface-dim text-vibe-on-surface flex items-center justify-center font-semibold text-xs">
                                    <?= strtoupper(substr($h['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($h['nama_lengkap']) ?></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant"><?= htmlspecialchars($h['telepon'] ?? '-') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="text-sm text-vibe-on-surface max-w-xs truncate"><?= htmlspecialchars($h['rincian']) ?></div>
                        </td>
                        <td class="px-5 py-3.5 text-right font-bold <?= $h['status'] === 'belum_lunas' ? 'text-vibe-error' : 'text-vibe-secondary' ?>"><?= formatRupiah($h['nominal']) ?></td>
                        <td class="px-5 py-3.5 text-center text-sm text-vibe-on-surface-variant"><?= htmlspecialchars($h['kasir_nama']) ?></td>
                        <td class="px-5 py-3.5 text-center text-sm text-vibe-on-surface-variant"><?= date('d M Y', strtotime($h['created_at'])) ?></td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($h['status'] === 'belum_lunas'): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-vibe-error/10 text-vibe-error border border-vibe-error/20">Belum Lunas</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-vibe-secondary/10 text-vibe-secondary border border-vibe-secondary/20">Lunas</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($h['status'] === 'belum_lunas'): ?>
                                <button @click="openBayar(<?= $h['id'] ?>, '<?= addslashes(htmlspecialchars($h['nama_lengkap'])) ?>', <?= $h['nominal'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[11px] font-bold text-white bg-vibe-primary hover:bg-vibe-primary-container transition-colors">
                                    Bayar
                                </button>
                            <?php else: ?>
                                <span class="text-[11px] text-vibe-on-surface-variant">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
<div x-show="showBayar" @keydown.escape.window="showBayar=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" x-transition style="display:none">
    <div @click.stop class="bg-white rounded-xl w-full max-w-md border border-vibe-outline-variant p-6">
        <h3 class="text-lg font-display font-bold text-vibe-on-surface mb-1">Bayar Hutang</h3>
        <p class="text-sm text-vibe-on-surface-variant mb-1">Pelanggan: <span class="font-semibold text-vibe-on-surface" x-text="bayarNama"></span></p>
        <p class="text-sm text-vibe-on-surface-variant mb-5">Nominal: <span class="font-bold text-vibe-primary" x-text="fmt(bayarNominal)"></span></p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="bayar_hutang">
            <input type="hidden" name="id" :value="bayarId">
            
            <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Metode Pembayaran</label>
            <select name="metode_bayar" x-model="bayarMetode" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors mb-4">
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer</option>
            </select>
            
            <div x-show="bayarMetode === 'transfer'" class="mb-4">
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Bukti Transfer</label>
                <input type="file" name="bukti" accept="image/*" class="w-full text-xs text-vibe-on-surface-variant file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-vibe-surface-container file:text-vibe-on-surface file:font-semibold file:cursor-pointer hover:file:bg-vibe-outline-variant transition-colors">
            </div>
            
            <div class="flex gap-3">
                <button type="button" @click="showBayar=false" class="flex-1 py-2.5 rounded-lg border border-vibe-outline-variant text-vibe-on-surface-variant font-bold text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
                <button type="submit" class="flex-1 py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99]">Konfirmasi Bayar</button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('piutangApp', () => ({
        showBayar: false,
        bayarId: null,
        bayarNama: '',
        bayarNominal: 0,
        bayarMetode: 'cash',
        
        fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },
        
        openBayar(id, nama, nominal) {
            this.bayarId = id;
            this.bayarNama = nama;
            this.bayarNominal = nominal;
            this.bayarMetode = 'cash';
            this.showBayar = true;
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
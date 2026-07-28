<?php
$page_title = 'Catat Hutang';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['kasir']);
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

// Fetch pelanggan list (hanya dibuat oleh admin)
$pelangganList = $pdo->query("SELECT id, nama_lengkap, telepon FROM pelanggan ORDER BY nama_lengkap")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'catat_hutang') {
            $pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
            $rincian = trim($_POST['rincian']);
            $nominal = (float)($_POST['nominal'] ?? 0);
            
            if ($pelanggan_id <= 0) throw new Exception('Pilih pelanggan terlebih dahulu.');
            if ($rincian === '') throw new Exception('Rincian hutang wajib diisi.');
            if ($nominal <= 0) throw new Exception('Nominal hutang harus lebih dari 0.');
            
            $pdo->prepare("INSERT INTO hutang (pelanggan_id, kasir_id, rincian, nominal) VALUES (?,?,?,?)")
                ->execute([$pelanggan_id, $_SESSION['user_id'], $rincian, $nominal]);
            $_SESSION['success'] = "Hutang berhasil dicatat.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: catat_hutang.php");
    exit;
}

// Recent hutang for this kasir
$recentHutang = $pdo->prepare("
    SELECT h.*, p.nama_lengkap, p.telepon
    FROM hutang h
    JOIN pelanggan p ON h.pelanggan_id = p.id
    WHERE h.kasir_id = ? AND h.status = 'belum_lunas'
    ORDER BY h.created_at DESC LIMIT 5
");
$recentHutang->execute([$_SESSION['user_id']]);
$recentHutang = $recentHutang->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Catat Hutang</h1>
        <p class="text-sm text-vibe-on-surface-variant mt-0.5">Pilih pelanggan dan catat hutang baru.</p>
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

    <?php if (empty($pelangganList)): ?>
        <div class="bg-white border border-vibe-outline-variant rounded-xl p-8 text-center">
            <div class="w-12 h-12 rounded-xl bg-vibe-surface-dim mx-auto flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <p class="text-sm font-semibold text-vibe-on-surface">Belum ada data pelanggan</p>
            <p class="text-xs text-vibe-on-surface-variant mt-1">Minta admin menambahkan pelanggan terlebih dahulu.</p>
        </div>
    <?php else: ?>
        <form method="POST" class="bg-white border border-vibe-outline-variant rounded-xl p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="catat_hutang">
            
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Pelanggan <span class="text-vibe-error">*</span></label>
                <select name="pelanggan_id" required class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
                    <option value="">Pilih pelanggan...</option>
                    <?php foreach ($pelangganList as $pl): ?>
                        <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['nama_lengkap']) ?> (<?= htmlspecialchars($pl['telepon'] ?? '-') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Rincian Hutang <span class="text-vibe-error">*</span></label>
                <textarea name="rincian" rows="3" required placeholder="Contoh: 2 Nasi Tutug + 1 Es Teh" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors resize-none"></textarea>
            </div>
            
            <div>
                <label class="block text-[11px] font-bold text-vibe-on-surface-variant uppercase tracking-wider mb-1.5">Nominal (Rp) <span class="text-vibe-error">*</span></label>
                <input type="number" name="nominal" min="1" required placeholder="0" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-lg focus:outline-none focus:border-vibe-on-surface text-sm transition-colors">
            </div>
            
            <button type="submit" class="w-full py-2.5 rounded-lg bg-vibe-primary text-white font-bold text-sm hover:bg-vibe-primary-container transition-colors active:scale-[0.99]">
                Catat Hutang
            </button>
        </form>
        
        <!-- Riwayat Hutang Terakhir -->
        <?php if (!empty($recentHutang)): ?>
            <div>
                <h3 class="font-display font-bold text-vibe-on-surface mb-3">Hutang Terakhir Dicatat</h3>
                <div class="space-y-2">
                    <?php foreach ($recentHutang as $rh): ?>
                        <div class="bg-white border border-vibe-outline-variant rounded-xl px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-vibe-outline-variant bg-vibe-surface-dim text-vibe-on-surface flex items-center justify-center font-semibold text-xs">
                                    <?= strtoupper(substr($rh['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-sm text-vibe-on-surface"><?= htmlspecialchars($rh['nama_lengkap']) ?></div>
                                    <div class="text-[11px] text-vibe-on-surface-variant truncate max-w-[200px]"><?= htmlspecialchars($rh['rincian']) ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-vibe-error"><?= formatRupiah($rh['nominal']) ?></div>
                                <div class="text-[11px] text-vibe-on-surface-variant"><?= date('d M H:i', strtotime($rh['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
/**
 * BeanPay — Bersihkan Semua Data Waiter & Dapur
 * 
 * Hapus permanen semua transaksi dan akun user waiter/dapur.
 * Jalankan sekali: http://localhost/BeanPay/database/bersihkan_waiter_dapur.php
 */
require_once __DIR__ . '/../config/database.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$steps = [];
$errors = [];

try {
    // Cari user waiter & dapur (by username atau role yg masih tersisa)
    $stmt = $pdo->query("SELECT id, username, nama_lengkap, role FROM users WHERE username IN ('waiter1', 'dapur1') OR role IN ('waiter', 'dapur')");
    $targetUsers = $stmt->fetchAll();

    if (empty($targetUsers)) {
        $steps[] = "⏭  Tidak ada user waiter/dapur ditemukan.";
    } else {
        $targetIds = array_column($targetUsers, 'id');
        $idsPlaceholder = implode(',', $targetIds);
        
        $steps[] = "🔍 Ditemukan " . count($targetUsers) . " user:";
        foreach ($targetUsers as $u) {
            $steps[] = "   - {$u['nama_lengkap']} (@{$u['username']}, role: {$u['role']}, id: {$u['id']})";
        }

        // Hapus detail_pesanan untuk pesanan yg dibuat user tsb
        $count = $pdo->exec("DELETE dp FROM detail_pesanan dp JOIN pesanan p ON dp.pesanan_id = p.id WHERE p.waiter_id IN ($idsPlaceholder)");
        $steps[] = "🗑  Detail pesanan: $count baris dihapus";

        // Hapus pembayaran untuk pesanan tersebut
        $count = $pdo->exec("DELETE b FROM pembayaran b JOIN pesanan p ON b.pesanan_id = p.id WHERE p.waiter_id IN ($idsPlaceholder)");
        $steps[] = "🗑  Pembayaran: $count baris dihapus";

        // Hapus sesi_kasir milik user tersebut
        $count = $pdo->exec("DELETE FROM sesi_kasir WHERE kasir_id IN ($idsPlaceholder)");
        $steps[] = "🗑  Sesi kasir: $count baris dihapus";

        // Hapus notifikasi waiter (jika masih ada tabelnya)
        try {
            $count = $pdo->exec("DELETE FROM notif_waiter WHERE waiter_id IN ($idsPlaceholder)");
            $steps[] = "🗑  Notif waiter: $count baris dihapus";
        } catch (PDOException $e) {
            // Tabel notif_waiter mungkin sudah dihapus, skip
        }

        // Hapus pesanan
        $count = $pdo->exec("DELETE FROM pesanan WHERE waiter_id IN ($idsPlaceholder)");
        $steps[] = "🗑  Pesanan: $count baris dihapus";

        // Hapus user
        $count = $pdo->exec("DELETE FROM users WHERE id IN ($idsPlaceholder)");
        $steps[] = "🗑  User: $count baris dihapus";
        
        $steps[] = "✅ Bersih! Semua data waiter & dapur telah dihapus permanen.";
    }

} catch (Exception $e) {
    $errors[] = "❌ ERROR: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bersihkan Waiter & Dapur</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #f8fafc; padding: 40px; }
        .card { max-width: 640px; margin: 0 auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; }
        h1 { font-size: 20px; color: #0f172a; margin-bottom: 24px; }
        .step { padding: 8px 12px; margin-bottom: 6px; border-radius: 4px; font-size: 13px; }
        .ok { background: #f0fdf4; color: #166534; }
        .info { background: #eff6ff; color: #1e40af; }
        .skip { background: #fefce8; color: #a16207; }
        .err { background: #fef2f2; color: #991b1b; }
        .warning { margin-top: 20px; padding: 16px; background: #fef3c7; border-radius: 6px; font-size: 13px; color: #92400e; }
        .success { margin-top: 20px; padding: 16px; background: #f0fdf4; border-radius: 6px; font-size: 13px; color: #166534; }
    </style>
</head>
<body>
<div class="card">
    <h1>🧹 BeanPay — Bersihkan Waiter & Dapur</h1>
    <?php foreach ($steps as $s): ?>
    <div class="step <?= str_starts_with($s, '🗑') || str_starts_with($s, '✅') ? 'ok' : (str_starts_with($s, '⏭') ? 'skip' : (str_starts_with($s, '🔍') ? 'info' : '')) ?>">
        <?= htmlspecialchars($s) ?>
    </div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
    <div class="step err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors) && !empty($targetUsers)): ?>
    <div class="success">
        ✅ Selesai! Sekarang user waiter & dapur sudah bisa dihapus.<br>
        <a href="../modules/admin/users.php" style="color:#166534;font-weight:bold;">→ Kembali ke Kelola Pengguna</a>
    </div>
    <?php endif; ?>

    <div class="warning">
        ⚠️ Script ini hanya untuk dijalankan SATU KALI. Hapus file ini setelah selesai.
    </div>
</div>
</body>
</html>

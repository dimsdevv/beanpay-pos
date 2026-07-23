<?php
require_once __DIR__ . '/../config/database.php';

$steps = [];

try {
    // 1. Update existing waiter/dapur to kasir
    $pdo->exec("UPDATE users SET role = 'kasir' WHERE role IN ('waiter', 'dapur')");
    $steps[] = "✅ User waiter/dapur diubah jadi kasir";

    // 2. Alter ENUM
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir'");
    $steps[] = "✅ Kolom role users diubah (ENUM: admin, kasir)";

    // 3. Hapus notif_waiter
    $pdo->exec("DROP TABLE IF EXISTS notif_waiter");
    $steps[] = "✅ Tabel notif_waiter dihapus";

    // 4. Tambah stock_deduction_done
    try {
        $pdo->exec("ALTER TABLE pesanan ADD COLUMN stock_deduction_done TINYINT(1) NOT NULL DEFAULT 0 AFTER diskon_nominal");
        $steps[] = "✅ Kolom stock_deduction_done ditambahkan ke pesanan";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $steps[] = "⏭ Kolom stock_deduction_done sudah ada";
        } else {
            throw $e;
        }
    }

} catch (Exception $e) {
    $steps[] = "❌ ERROR: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Role & Fitur</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #f8fafc; padding: 40px; }
        .card { max-width: 600px; margin: 0 auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; }
        h1 { font-size: 20px; color: #0f172a; margin-bottom: 24px; }
        .step { padding: 8px 12px; margin-bottom: 8px; border-radius: 4px; font-size: 14px; }
        .ok { background: #f0fdf4; color: #166534; }
        .skip { background: #fefce8; color: #a16207; }
        .err { background: #fef2f2; color: #991b1b; }
        .info { margin-top: 24px; padding: 16px; background: #eff6ff; border-radius: 6px; font-size: 13px; color: #1e40af; }
    </style>
</head>
<body>
<div class="card">
    <h1>🔧 BeanPay — Migrasi Role & Fitur</h1>
    <?php foreach ($steps as $s): ?>
    <div class="step <?= str_starts_with($s, '✅') ? 'ok' : (str_starts_with($s, '⏭') ? 'skip' : 'err') ?>">
        <?= htmlspecialchars($s) ?>
    </div>
    <?php endforeach; ?>
    <div class="info">
        ✅ Migrasi selesai! <br>
        - Role waiter & dapur dihapus<br>
        - User waiter/dapur diubah jadi kasir<br>
        - File migration ini bisa dihapus setelah dijalankan
    </div>
</div>
</body>
</html>

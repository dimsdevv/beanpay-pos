<?php
/**
 * BeanPay — KDS Smart Features Migration
 * Jalankan sekali di browser: http://localhost/BeanPay/database/migrate_kds.php
 */
require_once __DIR__ . '/../config/database.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$steps = [];

// ── 1. Kolom waktu masak di detail_pesanan (mungkin sudah ada) ──────────────
foreach (['waktu_mulai_masak', 'waktu_selesai_masak'] as $col) {
    try {
        $pdo->exec("ALTER TABLE detail_pesanan ADD COLUMN $col DATETIME NULL");
        $steps[] = "✅ Kolom $col ditambahkan ke detail_pesanan";
    } catch (PDOException $e) {
        $steps[] = "⏭  Kolom $col sudah ada, dilewati";
    }
}

// ── 2. Kolom stasiun di kategori ────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE kategori ADD COLUMN stasiun ENUM('bar','kitchen','cold','any') NOT NULL DEFAULT 'any'");
    $steps[] = "✅ Kolom stasiun ditambahkan ke kategori";
} catch (PDOException $e) {
    $steps[] = "⏭  Kolom stasiun sudah ada, dilewati";
}

// ── 3. Seed nilai stasiun per kategori ──────────────────────────────────────
$pdo->exec("UPDATE kategori SET stasiun = 'bar'     WHERE nama_kategori IN ('Kopi', 'Non-Kopi')");
$pdo->exec("UPDATE kategori SET stasiun = 'kitchen' WHERE nama_kategori IN ('Makanan Berat')");
$pdo->exec("UPDATE kategori SET stasiun = 'cold'    WHERE nama_kategori IN ('Snack', 'Dessert')");
$steps[] = "✅ Nilai stasiun di-seed untuk semua kategori";

// ── 4. Tabel notif_waiter ────────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notif_waiter (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            pesanan_id  INT NOT NULL,
            waiter_id   INT NOT NULL,
            pesan       VARCHAR(255) NOT NULL,
            is_read     TINYINT(1) NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
            FOREIGN KEY (waiter_id)  REFERENCES users(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    $steps[] = "✅ Tabel notif_waiter dibuat";
} catch (PDOException $e) {
    $steps[] = "⏭  Tabel notif_waiter sudah ada, dilewati";
}

// ── 5. Verifikasi ────────────────────────────────────────────────────────────
$kategoriRows = $pdo->query("SELECT nama_kategori, stasiun FROM kategori ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>KDS Migration</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=1.0.1">
</head>
<body class="bg-gray-50 min-h-screen p-8 font-mono">
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-md p-8">
    <h1 class="text-xl font-bold text-gray-800 mb-6">🔧 BeanPay KDS Migration</h1>
    <div class="space-y-2 mb-8">
        <?php foreach ($steps as $s): ?>
        <div class="text-sm <?= str_starts_with($s, '✅') ? 'text-green-700' : 'text-gray-500' ?> bg-gray-50 rounded-lg px-4 py-2">
            <?= htmlspecialchars($s) ?>
        </div>
        <?php endforeach; ?>
    </div>

    <h2 class="text-base font-bold text-gray-700 mb-3">Stasiun Kategori:</h2>
    <table class="w-full text-sm border border-gray-200 rounded-xl overflow-hidden">
        <thead class="bg-gray-100 text-xs uppercase text-gray-500">
            <tr><th class="px-4 py-2 text-left">Kategori</th><th class="px-4 py-2 text-left">Stasiun</th></tr>
        </thead>
        <tbody>
            <?php foreach ($kategoriRows as $r): ?>
            <tr class="border-t border-gray-100">
                <td class="px-4 py-2"><?= htmlspecialchars($r['nama_kategori']) ?></td>
                <td class="px-4 py-2 font-bold <?= match($r['stasiun']) { 'bar'=>'text-blue-600','kitchen'=>'text-orange-600','cold'=>'text-purple-600',default=>'text-gray-400'} ?>">
                    <?= $r['stasiun'] ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-6 p-4 bg-green-50 text-green-700 rounded-xl font-semibold text-sm">
        ✅ Migration selesai! Hapus file ini setelah dijalankan.
    </div>
</div>
</body>
</html>

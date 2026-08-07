<?php
/**
 * Manual Migration Script untuk Production
 * Jalankan sekali di server production setelah deploy
 * Akses via browser: https://domain.com/modules/admin/migrate.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin']);

// Cek apakah sudah dijalankan
$migrationKey = 'migration_keuangan_v2_done';
$stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE kunci = ?");
$stmt->execute([$migrationKey]);
if ($stmt->fetchColumn()) {
    die('<div style="font-family:Inter,sans-serif;padding:20px;color:green">✅ Migrasi sudah dijalankan sebelumnya.</div>');
}

echo "<div style='font-family:Inter,sans-serif;padding:20px;max-width:800px;margin:0 auto'>";
echo "<h2 style='color:#0F172A'>🔧 Migrasi Database Keuangan v2</h2>";
echo "<hr>";

try {
    $pdo->beginTransaction();

    // 1. Tambah kolom ke pengeluaran_item
    $cols = $pdo->query("SHOW COLUMNS FROM pengeluaran_item")->fetchAll(PDO::FETCH_COLUMN);
    $alterations = [];

    if (!in_array('satuan_beli', $cols)) $alterations[] = "ALTER TABLE pengeluaran_item ADD COLUMN satuan_beli VARCHAR(50) DEFAULT '' AFTER satuan";
    if (!in_array('konversi', $cols)) $alterations[] = "ALTER TABLE pengeluaran_item ADD COLUMN konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000 AFTER satuan_beli";
    if (!in_array('qty_beli', $cols)) $alterations[] = "ALTER TABLE pengeluaran_item ADD COLUMN qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER konversi";

    foreach ($alterations as $sql) {
        $pdo->exec($sql);
        echo "<p style='color:green'>✅ $sql</p>";
    }

    // Migrasi data lama
    $pdo->exec("UPDATE pengeluaran_item SET qty_beli = qty, satuan_beli = satuan, konversi = 1 WHERE qty_beli = 0 AND qty > 0");
    echo "<p style='color:green'>✅ Migrasi data lama: qty_beli=qty, satuan_beli=satuan, konversi=1</p>";

    // 2. Tambah kolom pesanan_id ke hutang
    $colsHutang = $pdo->query("SHOW COLUMNS FROM hutang")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pesanan_id', $colsHutang)) {
        $pdo->exec("ALTER TABLE hutang ADD COLUMN pesanan_id INT DEFAULT NULL AFTER kasir_id");
        $pdo->exec("ALTER TABLE hutang ADD INDEX idx_pesanan (pesanan_id)");
        echo "<p style='color:green'>✅ Tambah kolom pesanan_id ke tabel hutang</p>";
    }

    // 3. Update ENUM metode_pembayaran di pembayaran
    try {
        // Test if 'hutang' accepted
        $pdo->query("INSERT INTO pembayaran (pesanan_id, sesi_kasir_id, metode_pembayaran, jumlah_bayar) VALUES (999999, 0, 'hutang', 0)");
        $pdo->exec("DELETE FROM pembayaran WHERE pesanan_id = 999999");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE pembayaran MODIFY COLUMN metode_pembayaran ENUM('cash','qris','transfer','hutang') DEFAULT 'cash'");
        echo "<p style='color:green'>✅ Update ENUM pembayaran.metode_pembayaran tambah 'hutang'</p>";
    }

    // 4. Tandai migrasi selesai
    $pdo->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, '1') ON DUPLICATE KEY UPDATE nilai='1'")->execute([$migrationKey]);

    $pdo->commit();

    echo "<div style='background:#dcfce7;border:1px solid #16a34a;padding:15px;border-radius:8px;margin-top:20px'>";
    echo "<h3 style='margin:0;color:#166534'>🎉 Migrasi Berhasil!</h3>";
    echo "<p style='margin:5px 0 0'>Semua perubahan database telah diterapkan. Halaman ini aman ditutup.</p>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<div style='background:#fee2e2;border:1px solid #dc2626;padding:15px;border-radius:8px;margin-top:20px;color:#dc2626'>";
    echo "<h3 style='margin:0'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "</div>";
}

echo "</div>";
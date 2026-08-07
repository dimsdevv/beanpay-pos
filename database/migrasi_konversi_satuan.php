<?php
/**
 * Migrasi Database: Konversi Satuan Fleksibel
 * 
 * Menambahkan kolom konversi ke tabel pengeluaran_item agar pembelian bisa
 * menggunakan satuan yang berbeda dari satuan dasar resep (misal: beli Kg, resep Gram).
 * 
 * Kolom baru:
 *   - satuan_beli  : satuan saat membeli (Kg, Dus, Karung, dll)
 *   - konversi     : faktor pengali (1 Kg = 1000 Gram → konversi = 1000)
 *   - qty_beli     : jumlah yang dibeli dalam satuan_beli
 *   
 * Kolom lama (qty, satuan) TETAP ADA dan menyimpan nilai dalam satuan dasar.
 * 
 * SAFE: Bisa dijalankan berulang kali tanpa error.
 */

require_once __DIR__ . '/../config/database.php';

echo "═══════════════════════════════════════════════════\n";
echo "  BeanPay — Migrasi: Konversi Satuan Fleksibel\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    // 1. Tambah kolom satuan_beli ke pengeluaran_item
    $cols = $pdo->query("SHOW COLUMNS FROM pengeluaran_item")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('satuan_beli', $cols)) {
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN satuan_beli VARCHAR(50) DEFAULT '' AFTER satuan");
        echo "[OK] Kolom 'satuan_beli' ditambahkan ke pengeluaran_item.\n";
    } else {
        echo "[SKIP] Kolom 'satuan_beli' sudah ada.\n";
    }

    if (!in_array('konversi', $cols)) {
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000 AFTER satuan_beli");
        echo "[OK] Kolom 'konversi' ditambahkan ke pengeluaran_item.\n";
    } else {
        echo "[SKIP] Kolom 'konversi' sudah ada.\n";
    }

    if (!in_array('qty_beli', $cols)) {
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER konversi");
        echo "[OK] Kolom 'qty_beli' ditambahkan ke pengeluaran_item.\n";
    } else {
        echo "[SKIP] Kolom 'qty_beli' sudah ada.\n";
    }

    // 2. Migrasi data lama: set qty_beli = qty, satuan_beli = satuan, konversi = 1
    //    Hanya untuk row yang belum pernah diisi (qty_beli masih 0)
    $affected = $pdo->exec("
        UPDATE pengeluaran_item 
        SET qty_beli = qty, 
            satuan_beli = satuan, 
            konversi = 1 
        WHERE qty_beli = 0 AND qty > 0
    ");
    echo "[OK] Migrasi data lama: $affected baris diperbarui (qty_beli = qty, konversi = 1).\n";

    echo "\n✅ Migrasi selesai dengan sukses!\n";
    echo "   Anda sekarang bisa membeli bahan dengan satuan yang berbeda.\n";
    
} catch (PDOException $e) {
    echo "\n❌ GAGAL: " . $e->getMessage() . "\n";
    exit(1);
}

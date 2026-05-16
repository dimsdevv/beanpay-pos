<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("
        ALTER TABLE sesi_kasir 
        ADD COLUMN uang_fisik DECIMAL(15,2) DEFAULT NULL AFTER total_pemasukan,
        ADD COLUMN selisih_kas DECIMAL(15,2) DEFAULT NULL AFTER uang_fisik
    ");
    echo "Berhasil menambahkan kolom uang_fisik dan selisih_kas ke tabel sesi_kasir.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Kolom sudah ada, abaikan.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

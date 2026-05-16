<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo->exec("ALTER TABLE menu ADD COLUMN is_active TINYINT(1) DEFAULT 1;");
    echo "Kolom is_active berhasil ditambahkan ke tabel menu.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Kolom is_active sudah ada di tabel menu.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

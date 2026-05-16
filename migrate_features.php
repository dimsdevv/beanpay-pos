<?php
require_once __DIR__ . '/config/database.php';

try {
    // 1. Tambahkan harga_beli ke bahan_baku
    try {
        $pdo->exec("ALTER TABLE bahan_baku ADD COLUMN harga_beli DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER satuan");
        echo "Kolom harga_beli ditambahkan ke bahan_baku.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Kolom harga_beli sudah ada.\n";
        } else {
            throw $e;
        }
    }

    // 2. Buat tabel resep
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resep (
            id INT AUTO_INCREMENT PRIMARY KEY,
            menu_id INT NOT NULL,
            bahan_id INT NOT NULL,
            jumlah DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE,
            FOREIGN KEY (bahan_id) REFERENCES bahan_baku(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel resep berhasil dibuat/sudah ada.\n";

    // 3. Buat tabel pengaturan
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengaturan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kunci VARCHAR(50) NOT NULL UNIQUE,
            nilai VARCHAR(255) NOT NULL,
            deskripsi TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel pengaturan berhasil dibuat/sudah ada.\n";

    // 4. Insert data pengaturan default
    $pengaturan_default = [
        ['pajak_persen', '10', 'Persentase Pajak Pembangunan 1 (PB1)'],
        ['service_charge_persen', '5', 'Persentase Biaya Pelayanan (Service Charge)'],
        ['aktifkan_pajak', '1', 'Status aktif pajak (1 = Aktif, 0 = Nonaktif)'],
        ['aktifkan_service', '1', 'Status aktif service charge (1 = Aktif, 0 = Nonaktif)']
    ];

    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO pengaturan (kunci, nilai, deskripsi) VALUES (?, ?, ?)");
    foreach ($pengaturan_default as $p) {
        $stmtInsert->execute($p);
    }
    echo "Data pengaturan default berhasil ditambahkan.\n";

    // 5. Tambahkan status_dapur ke detail_pesanan jika belum ada (meskipun sudah ada status_item, memastikan)
    // Berdasarkan check_schema, detail_pesanan sudah punya status_item enum('pending','cooking','ready').
    // Tidak perlu diubah.
    
    // 6. Tambahkan kolom waktu_mulai_masak dan waktu_selesai_masak ke detail_pesanan untuk melacak Service Time
    try {
        $pdo->exec("ALTER TABLE detail_pesanan ADD COLUMN waktu_mulai_masak DATETIME NULL AFTER status_item");
        $pdo->exec("ALTER TABLE detail_pesanan ADD COLUMN waktu_selesai_masak DATETIME NULL AFTER waktu_mulai_masak");
        echo "Kolom waktu masak ditambahkan ke detail_pesanan.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Kolom waktu masak sudah ada.\n";
        } else {
            throw $e;
        }
    }

    echo "\nMigrasi sukses!\n";
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage() . "\n";
}

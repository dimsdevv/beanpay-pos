<?php
require_once __DIR__ . '/config/database.php';

try {
    // 3. Buat tabel pengaturan
    try {
        $pdo->exec("ALTER TABLE pengaturan ADD COLUMN deskripsi TEXT AFTER nilai");
    } catch(Exception $e) {}

    // 4. Insert data pengaturan default
    $pengaturan_default = [
        ['pajak_persen', '10', 'Persentase Pajak Pembangunan 1 (PB1)'],
        ['service_charge_persen', '5', 'Persentase Biaya Pelayanan (Service Charge)'],
        ['aktifkan_pajak', '1', 'Status aktif pajak (1 = Aktif, 0 = Nonaktif)'],
        ['aktifkan_service', '1', 'Status aktif service charge (1 = Aktif, 0 = Nonaktif)']
    ];

    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO pengaturan (kunci, nilai, deskripsi) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE deskripsi=VALUES(deskripsi)");
    foreach ($pengaturan_default as $p) {
        $stmtInsert->execute($p);
    }
    echo "Data pengaturan default berhasil ditambahkan.\n";
    
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

<?php
require_once __DIR__ . '/config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Kolom waktu masak
foreach (['waktu_mulai_masak','waktu_selesai_masak'] as $col) {
    try { $pdo->exec("ALTER TABLE detail_pesanan ADD COLUMN $col DATETIME NULL"); echo "Added $col\n"; }
    catch (PDOException $e) { echo "Skip $col (already exists)\n"; }
}

// 2. Kolom stasiun di kategori
try { $pdo->exec("ALTER TABLE kategori ADD COLUMN stasiun ENUM('bar','kitchen','cold','any') NOT NULL DEFAULT 'any'"); echo "Added stasiun\n"; }
catch (PDOException $e) { echo "Skip stasiun (already exists)\n"; }

// 3. Seed stasiun
$pdo->exec("UPDATE kategori SET stasiun='bar' WHERE nama_kategori IN ('Kopi','Non-Kopi')");
$pdo->exec("UPDATE kategori SET stasiun='kitchen' WHERE nama_kategori='Makanan Berat'");
$pdo->exec("UPDATE kategori SET stasiun='cold' WHERE nama_kategori IN ('Snack','Dessert')");
echo "Seeded stasiun values\n";

// 4. Tabel notif_waiter
$pdo->exec("CREATE TABLE IF NOT EXISTS notif_waiter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id INT NOT NULL,
    waiter_id INT NOT NULL,
    pesan VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
    FOREIGN KEY (waiter_id)  REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB");
echo "notif_waiter table OK\n";

// 5. Verify
$rows = $pdo->query("SELECT nama_kategori, stasiun FROM kategori ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "\nKategori stasiun mapping:\n";
foreach ($rows as $r) echo "  {$r['nama_kategori']} => {$r['stasiun']}\n";
echo "\nMigration complete!\n";

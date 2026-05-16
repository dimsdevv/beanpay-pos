<?php
require_once __DIR__ . '/config/database.php';

echo "=== MEMPERBAIKI STATUS PESANAN YANG KOSONG ===\n";

// Pesanan yang status_pesanan = '' tapi semua detail_pesanan sudah 'ready' → set 'selesai'
$fixed_selesai = $pdo->exec("
    UPDATE pesanan p
    SET p.status_pesanan = 'selesai'
    WHERE p.status_pesanan = ''
    AND (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id AND dp.status_item != 'ready') = 0
    AND (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id) > 0
");
echo "Pesanan diperbaiki ke 'selesai': $fixed_selesai baris\n";

// Pesanan yang status_pesanan = '' tapi ada detail yang masih pending/cooking → set 'diproses'
$fixed_diproses = $pdo->exec("
    UPDATE pesanan p
    SET p.status_pesanan = 'diproses'
    WHERE p.status_pesanan = ''
    AND (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id AND dp.status_item IN ('pending','cooking')) > 0
");
echo "Pesanan diperbaiki ke 'diproses': $fixed_diproses baris\n";

// Sisa pesanan kosong (tidak ada detail sama sekali) → set 'pending'
$fixed_pending = $pdo->exec("UPDATE pesanan SET status_pesanan = 'pending' WHERE status_pesanan = ''");
echo "Pesanan diperbaiki ke 'pending': $fixed_pending baris\n";

echo "\n=== STATUS SETELAH PERBAIKAN ===\n";
$rows = $pdo->query("SELECT status_pesanan, COUNT(*) as count FROM pesanan GROUP BY status_pesanan")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo ($r['status_pesanan'] ?: '[kosong]') . ": " . $r['count'] . "\n";

echo "\nSelesai!\n";

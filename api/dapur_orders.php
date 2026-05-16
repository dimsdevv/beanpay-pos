<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['dapur', 'admin', 'kasir', 'waiter']);

// Ambil pesanan aktif (pending, diproses) beserta item-itemnya
$stmt = $pdo->query("
    SELECT p.id, p.nomor_pesanan, p.tipe_pesanan, p.nama_pelanggan, p.status_pesanan, p.waktu_pesan, m.nomor_meja
    FROM pesanan p
    LEFT JOIN meja m ON p.meja_id = m.id
    WHERE p.status_pesanan IN ('pending', 'diproses', 'selesai')
    ORDER BY p.waktu_pesan ASC
");
$pesanans = $stmt->fetchAll();

foreach ($pesanans as &$p) {
    $stmtI = $pdo->prepare("SELECT d.id, d.qty, d.catatan, d.status_item, mn.nama_menu FROM detail_pesanan d JOIN menu mn ON d.menu_id = mn.id WHERE d.pesanan_id = ?");
    $stmtI->execute([$p['id']]);
    $p['items'] = $stmtI->fetchAll();
}

header('Content-Type: application/json');
echo json_encode($pesanans);

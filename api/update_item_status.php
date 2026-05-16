<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['dapur', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id   = (int)$_POST['item_id'];
    $newStatus = $_POST['status'];

    // Validasi status
    $allowed = ['pending' => 'cooking', 'cooking' => 'ready'];

    // Ambil status saat ini
    $stmt = $pdo->prepare("SELECT status_item, pesanan_id FROM detail_pesanan WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if ($item && isset($allowed[$item['status_item']]) && $allowed[$item['status_item']] === $newStatus) {
        // Update item
        $pdo->prepare("UPDATE detail_pesanan SET status_item = ? WHERE id = ?")->execute([$newStatus, $item_id]);

        $pesanan_id = $item['pesanan_id'];

        // Cek apakah semua item sudah ready → update status pesanan
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id = ? AND status_item != 'ready'");
        $stmtCheck->execute([$pesanan_id]);
        $notReady = (int)$stmtCheck->fetchColumn();

        if ($notReady === 0) {
            // Semua ready → selesai
            $pdo->prepare("UPDATE pesanan SET status_pesanan = 'selesai' WHERE id = ?")->execute([$pesanan_id]);
        } else {
            // Ada yang cooking → diproses
            $stmtCheck2 = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id = ? AND status_item = 'cooking'");
            $stmtCheck2->execute([$pesanan_id]);
            if ((int)$stmtCheck2->fetchColumn() > 0) {
                $pdo->prepare("UPDATE pesanan SET status_pesanan = 'diproses' WHERE id = ?")->execute([$pesanan_id]);
            }
        }

        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    }
}

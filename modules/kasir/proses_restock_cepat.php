<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

requireRole(['kasir', 'admin']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$bahan_id = (int)($_POST['bahan_id'] ?? 0);
if ($bahan_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bahan tidak valid.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Cek sesi kasir aktif
    $stmtSesi = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1 FOR UPDATE");
    $stmtSesi->execute([$_SESSION['user_id']]);
    if (!$stmtSesi->fetch()) {
        throw new Exception('Shift kasir belum dibuka.');
    }

    // Cek bahan ada
    $stmtBahan = $pdo->prepare("SELECT id, nama_bahan, satuan FROM bahan_baku WHERE id = ? FOR UPDATE");
    $stmtBahan->execute([$bahan_id]);
    $bahan = $stmtBahan->fetch();
    if (!$bahan) {
        throw new Exception('Bahan tidak ditemukan.');
    }

    // Restock +5
    $jumlah = 5;
    $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang + ? WHERE id = ?")
        ->execute([$jumlah, $bahan_id]);

    $pdo->commit();

    logAuditAction('restock_cepat', 'bahan_baku', $bahan_id, "Bahan: {$bahan['nama_bahan']} (+5 {$bahan['satuan']})");

    echo json_encode([
        'success' => true,
        'message' => "{$bahan['nama_bahan']} +5 {$bahan['satuan']}. Stok siap!",
        'bahan_id' => $bahan_id,
        'jumlah' => $jumlah,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

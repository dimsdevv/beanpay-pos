<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modal_awal = $_POST['modal_awal'] ?? 0;
    
    // Cek apakah sudah ada sesi yang buka
    $stmt = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka'");
    $stmt->execute([$_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Anda sudah memiliki shift yang aktif.";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO sesi_kasir (kasir_id, modal_awal, waktu_buka) VALUES (?, ?, NOW())");
        if ($stmtInsert->execute([$_SESSION['user_id'], $modal_awal])) {
            $_SESSION['success'] = "Shift kasir berhasil dibuka dengan modal Rp " . number_format($modal_awal, 0, ',', '.');
        } else {
            $_SESSION['error'] = "Gagal membuka shift kasir.";
        }
    }
}

header('Location: ' . BASE_URL . '/modules/kasir/index.php');
exit;

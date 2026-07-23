<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modal_awal = (float)($_POST['modal_awal'] ?? 0);

    if ($modal_awal < 0) {
        $_SESSION['error'] = "Modal awal tidak boleh negatif.";
        header('Location: ' . BASE_URL . '/modules/kasir/index.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1 FOR UPDATE");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Anda sudah memiliki shift yang aktif.");
        }

        $stmtInsert = $pdo->prepare("INSERT INTO sesi_kasir (kasir_id, modal_awal, waktu_buka) VALUES (?, ?, NOW())");
        $stmtInsert->execute([$_SESSION['user_id'], $modal_awal]);

        $pdo->commit();
        $_SESSION['success'] = "Shift kasir berhasil dibuka dengan modal Rp " . number_format($modal_awal, 0, ',', '.');
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: ' . BASE_URL . '/modules/kasir/index.php');
exit;

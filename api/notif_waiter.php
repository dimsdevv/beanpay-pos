<?php
/**
 * BeanPay — Waiter Notification API
 * GET  ?waiter_id=X&last_id=Y  → ambil notif baru
 * POST action=mark_read&id=X   → tandai dibaca
 * POST action=mark_all_read&waiter_id=X → tandai semua dibaca
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_write_close();
header('Content-Type: application/json');

// Only waiter/admin allowed
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthenticated']); exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['waiter', 'admin'])) {
    echo json_encode(['notifs' => [], 'unread_count' => 0]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $waiter_id = (int)($_GET['waiter_id'] ?? $_SESSION['user_id']);
    $last_id   = (int)($_GET['last_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT n.id, n.pesan, n.is_read, n.created_at,
               p.nomor_pesanan, p.tipe_pesanan,
               m.nomor_meja
        FROM notif_waiter n
        JOIN pesanan p ON n.pesanan_id = p.id
        LEFT JOIN meja m ON p.meja_id = m.id
        WHERE n.waiter_id = ? AND n.id > ?
        ORDER BY n.id DESC
        LIMIT 20
    ");
    $stmt->execute([$waiter_id, $last_id]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notif_waiter WHERE waiter_id = ? AND is_read = 0");
    $countStmt->execute([$waiter_id]);
    $unread = (int)$countStmt->fetchColumn();

    echo json_encode([
        'notifs'       => $notifs,
        'unread_count' => $unread,
        'max_id'       => empty($notifs) ? $last_id : (int)$notifs[0]['id']
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE notif_waiter SET is_read = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'mark_all_read') {
        $waiter_id = (int)($_POST['waiter_id'] ?? $_SESSION['user_id']);
        $pdo->prepare("UPDATE notif_waiter SET is_read = 1 WHERE waiter_id = ?")->execute([$waiter_id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);

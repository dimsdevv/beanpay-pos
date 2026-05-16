<?php
/**
 * BeanPay Real-Time API Endpoint
 * 
 * GET /api/realtime.php?last_id=0
 * 
 * Returns JSON:
 * - kpi: today's revenue, orders, avg_ticket
 * - trend: percentage change vs yesterday
 * - notifications: new payments since last_id
 * - last_id: latest pembayaran.id (for next poll)
 * - activity: last 5 payments today
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only authenticated users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // ─── KPI TODAY ────────────────────────────────────────
    $stmtToday = $pdo->query("
        SELECT 
            COUNT(DISTINCT p.id) as total_orders,
            COALESCE(SUM(p.total_harga), 0) as total_revenue
        FROM pesanan p
        JOIN pembayaran b ON p.id = b.pesanan_id
        WHERE DATE(b.waktu_bayar) = CURDATE()
    ");
    $today = $stmtToday->fetch();
    $revenue = (float)$today['total_revenue'];
    $orders = (int)$today['total_orders'];
    $avgTicket = $orders > 0 ? round($revenue / $orders) : 0;

    // ─── KPI YESTERDAY (for trend) ────────────────────────
    $stmtYest = $pdo->query("
        SELECT 
            COUNT(DISTINCT p.id) as total_orders,
            COALESCE(SUM(p.total_harga), 0) as total_revenue
        FROM pesanan p
        JOIN pembayaran b ON p.id = b.pesanan_id
        WHERE DATE(b.waktu_bayar) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $yest = $stmtYest->fetch();
    $yestRevenue = (float)$yest['total_revenue'];
    $yestOrders = (int)$yest['total_orders'];
    $yestAvg = $yestOrders > 0 ? round($yestRevenue / $yestOrders) : 0;

    // Trend calculation
    function calcTrend($now, $prev) {
        if ($prev == 0) return $now > 0 ? 100 : 0;
        return round((($now - $prev) / $prev) * 100, 1);
    }

    // ─── NEW NOTIFICATIONS (since last_id) ────────────────
    $notifications = [];
    $stmtNew = $pdo->prepare("
        SELECT 
            b.id,
            p.nomor_pesanan,
            p.total_harga,
            p.nama_pelanggan,
            b.metode_pembayaran,
            b.jumlah_bayar,
            b.waktu_bayar,
            u.nama_lengkap as kasir
        FROM pembayaran b
        JOIN pesanan p ON b.pesanan_id = p.id
        JOIN sesi_kasir s ON b.sesi_kasir_id = s.id
        JOIN users u ON s.kasir_id = u.id
        WHERE b.id > ?
        ORDER BY b.id ASC
        LIMIT 10
    ");
    $stmtNew->execute([$lastId]);
    $newPayments = $stmtNew->fetchAll();

    foreach ($newPayments as $np) {
        $notifications[] = [
            'id'              => (int)$np['id'],
            'nomor_pesanan'   => $np['nomor_pesanan'],
            'total'           => (float)$np['total_harga'],
            'jumlah_bayar'    => (float)$np['jumlah_bayar'],
            'metode'          => strtoupper($np['metode_pembayaran']),
            'kasir'           => $np['kasir'],
            'pelanggan'       => $np['nama_pelanggan'] ?: '-',
            'waktu'           => date('H:i', strtotime($np['waktu_bayar'])),
            'waktu_full'      => $np['waktu_bayar'],
        ];
    }

    // ─── LATEST PAYMENT ID ────────────────────────────────
    $stmtLatest = $pdo->query("SELECT MAX(id) as max_id FROM pembayaran");
    $latestId = (int)$stmtLatest->fetch()['max_id'];

    // ─── RECENT ACTIVITY (last 5 payments today) ──────────
    $stmtActivity = $pdo->query("
        SELECT 
            b.id,
            p.nomor_pesanan,
            p.total_harga,
            p.tipe_pesanan,
            b.metode_pembayaran,
            b.waktu_bayar,
            u.nama_lengkap as kasir
        FROM pembayaran b
        JOIN pesanan p ON b.pesanan_id = p.id
        JOIN sesi_kasir s ON b.sesi_kasir_id = s.id
        JOIN users u ON s.kasir_id = u.id
        WHERE DATE(b.waktu_bayar) = CURDATE()
        ORDER BY b.waktu_bayar DESC
        LIMIT 5
    ");
    $activity = [];
    while ($row = $stmtActivity->fetch()) {
        $activity[] = [
            'id'             => (int)$row['id'],
            'nomor_pesanan'  => $row['nomor_pesanan'],
            'total'          => (float)$row['total_harga'],
            'tipe'           => $row['tipe_pesanan'],
            'metode'         => strtoupper($row['metode_pembayaran']),
            'kasir'          => $row['kasir'],
            'waktu'          => date('H:i', strtotime($row['waktu_bayar'])),
        ];
    }

    // ─── RESPONSE ─────────────────────────────────────────
    echo json_encode([
        'kpi' => [
            'revenue'    => $revenue,
            'orders'     => $orders,
            'avg_ticket' => $avgTicket,
        ],
        'trend' => [
            'revenue_pct' => calcTrend($revenue, $yestRevenue),
            'orders_pct'  => calcTrend($orders, $yestOrders),
            'avg_pct'     => calcTrend($avgTicket, $yestAvg),
        ],
        'notifications' => $notifications,
        'activity'      => $activity,
        'last_id'       => $latestId,
        'server_time'   => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

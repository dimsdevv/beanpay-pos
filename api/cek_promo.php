<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$kode = strtoupper(trim($_POST['kode_promo'] ?? ''));
$subtotal = (float)($_POST['subtotal'] ?? 0);

if (empty($kode)) {
    echo json_encode(['success' => false, 'message' => 'Kode promo kosong.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM promo WHERE kode_promo = ?");
    $stmt->execute([$kode]);
    $promo = $stmt->fetch();

    if (!$promo) {
        throw new Exception("Kode promo tidak ditemukan.");
    }

    if ($promo['status'] !== 'aktif') {
        throw new Exception("Promo ini sedang tidak aktif.");
    }

    $today = date('Y-m-d');
    if ($today < $promo['tanggal_mulai']) {
        throw new Exception("Promo belum dimulai (Berlaku mulai " . date('d M Y', strtotime($promo['tanggal_mulai'])) . ").");
    }
    if ($today > $promo['tanggal_selesai']) {
        throw new Exception("Promo sudah kadaluarsa (Berakhir " . date('d M Y', strtotime($promo['tanggal_selesai'])) . ").");
    }

    if ($promo['kuota'] <= 0) {
        throw new Exception("Kuota promo ini sudah habis.");
    }

    if ($subtotal < $promo['min_belanja']) {
        throw new Exception("Minimum belanja untuk promo ini adalah Rp " . number_format($promo['min_belanja'], 0, ',', '.') . ".");
    }

    // Hitung diskon
    $diskon = 0;
    if ($promo['tipe_diskon'] === 'persen') {
        $diskon = ($subtotal * $promo['nilai_diskon']) / 100;
    } else {
        $diskon = $promo['nilai_diskon'];
    }
    
    // Diskon maksimal sebesar subtotal
    if ($diskon > $subtotal) {
        $diskon = $subtotal;
    }

    echo json_encode([
        'success' => true,
        'promo_id' => $promo['id'],
        'kode_promo' => $promo['kode_promo'],
        'tipe_diskon' => $promo['tipe_diskon'],
        'nilai_diskon' => $promo['nilai_diskon'],
        'diskon_nominal' => $diskon,
        'message' => 'Promo berhasil diterapkan!'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['kasir', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sesi_id = (int)$_POST['sesi_id'];

    $uang_fisik = isset($_POST['uang_fisik']) ? (float)$_POST['uang_fisik'] : 0;
    
    // Validasi kepemilikan sesi
    $stmt = $pdo->prepare("SELECT * FROM sesi_kasir WHERE id = ? AND kasir_id = ? AND status = 'buka'");
    $stmt->execute([$sesi_id, $_SESSION['user_id']]);
    $sesi = $stmt->fetch();

    if (!$sesi) {
        $_SESSION['error'] = "Sesi tidak valid atau sudah ditutup.";
        header('Location: ' . BASE_URL . '/modules/kasir/index.php');
        exit;
    }

    // Hitung total pemasukan (hanya tunai/cash untuk laci, atau semuanya? Umumnya laci = modal + tunai. 
    // Tapi di BeanPay, asumsi total_pemasukan mencakup semua, lalu expected = modal_awal + total_pemasukan_tunai.
    // Let's filter by cash for actual drawer discrepancy calculation)
    $stmtTunai = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE sesi_kasir_id = ? AND metode_pembayaran = 'cash'");
    $stmtTunai->execute([$sesi_id]);
    $totalTunai = (float)$stmtTunai->fetchColumn();

    $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE sesi_kasir_id = ?");
    $stmtTotal->execute([$sesi_id]);
    $totalSemua = (float)$stmtTotal->fetchColumn();

    $expected_cash = $sesi['modal_awal'] + $totalTunai; // Yg harus ada di laci fisik
    // Tapi di riwayat.php UI lama menggunakan modal_awal + total semua. 
    // Let's stick to the UI's calculation for expected: modal_awal + totalSemua for consistency.
    $expected_drawer = $sesi['modal_awal'] + $totalSemua;
    $selisih = $uang_fisik - $expected_drawer;

    // Tutup sesi
    $pdo->prepare("UPDATE sesi_kasir SET status='tutup', waktu_tutup=NOW(), total_pemasukan=?, uang_fisik=?, selisih_kas=? WHERE id=?")
        ->execute([$totalSemua, $uang_fisik, $selisih, $sesi_id]);

    if ($selisih < 0) {
        $_SESSION['error'] = "Shift ditutup dengan SELISIH MINUS: " . formatRupiah($selisih);
    } else {
        $_SESSION['success'] = "Shift berhasil ditutup. Total pemasukan: " . formatRupiah($totalSemua);
    }
    header('Location: ' . BASE_URL . '/modules/kasir/riwayat.php');
    exit;
}

header('Location: ' . BASE_URL . '/modules/kasir/index.php');
exit;

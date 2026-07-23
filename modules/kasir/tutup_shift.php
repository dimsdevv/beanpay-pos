<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireRole(['kasir', 'admin']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sesi_id = (int)$_POST['sesi_id'];
    $uang_fisik = (float)($_POST['uang_fisik'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Validasi kepemilikan sesi
        $stmt = $pdo->prepare("SELECT * FROM sesi_kasir WHERE id = ? AND kasir_id = ? AND status = 'buka' LIMIT 1 FOR UPDATE");
        $stmt->execute([$sesi_id, $_SESSION['user_id']]);
        $sesi = $stmt->fetch();

        if (!$sesi) {
            throw new Exception("Sesi tidak valid atau sudah ditutup.");
        }

        // Total semua pemasukan (untuk laporan)
        $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE sesi_kasir_id = ?");
        $stmtTotal->execute([$sesi_id]);
        $totalSemua = (float)$stmtTotal->fetchColumn();

        // Hitung selisih laci fisik: uang fisik hanya terisi dari modal tunai + transaksi cash
        $stmtTunai = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE sesi_kasir_id = ? AND metode_pembayaran = 'cash'");
        $stmtTunai->execute([$sesi_id]);
        $totalTunai = (float)$stmtTunai->fetchColumn();

        $expectedLaci = $sesi['modal_awal'] + $totalTunai;
        $selisih = $uang_fisik - $expectedLaci;

        // Tutup sesi
        $pdo->prepare("UPDATE sesi_kasir SET status='tutup', waktu_tutup=NOW(), total_pemasukan=?, uang_fisik=?, selisih_kas=? WHERE id=?")
            ->execute([$totalSemua, $uang_fisik, $selisih, $sesi_id]);

        $pdo->commit();

        if ($selisih < 0) {
            $_SESSION['error'] = "Shift ditutup — selisih minus " . formatRupiah(abs($selisih)) . ". Total pemasukan: " . formatRupiah($totalSemua);
        } elseif ($selisih > 0) {
            $_SESSION['success'] = "Shift ditutup — selisih lebih " . formatRupiah($selisih) . ". Total pemasukan: " . formatRupiah($totalSemua);
        } else {
            $_SESSION['success'] = "Shift ditutup — laci rapi. Total pemasukan: " . formatRupiah($totalSemua);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: ' . BASE_URL . '/modules/kasir/riwayat.php');
    exit;
}

header('Location: ' . BASE_URL . '/modules/kasir/index.php');
exit;

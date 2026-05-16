<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pesanan_id = (int)$_POST['pesanan_id'] ?? 0;
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    $jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);
    $promo_id = !empty($_POST['promo_id']) ? (int)$_POST['promo_id'] : null;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Cek sesi kasir
        $stmtSesi = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1 FOR UPDATE");
        $stmtSesi->execute([$_SESSION['user_id']]);
        $sesi = $stmtSesi->fetch();
        
        if (!$sesi) {
            throw new Exception("Shift kasir belum dibuka!");
        }
        $sesi_id = $sesi['id'];
        
        // 2. Cek pesanan
        $stmtPesan = $pdo->prepare("SELECT subtotal, service_persen, pajak_persen, total_harga, meja_id, status_pesanan FROM pesanan WHERE id = ? FOR UPDATE");
        $stmtPesan->execute([$pesanan_id]);
        $pesanan = $stmtPesan->fetch();
        
        if (!$pesanan) {
            throw new Exception("Pesanan tidak ditemukan!");
        }
        if ($pesanan['status_pesanan'] === 'dibayar' || $pesanan['status_pesanan'] === 'dibatalkan') {
            throw new Exception("Pesanan sudah dibayar atau dibatalkan!");
        }
        
        $total_tagihan = (float)$pesanan['total_harga'];
        $diskon_nominal = 0;
        
        // 2.5 Cek dan Terapkan Promo jika ada
        if ($promo_id) {
            $stmtPromo = $pdo->prepare("SELECT * FROM promo WHERE id = ? AND status = 'aktif' FOR UPDATE");
            $stmtPromo->execute([$promo_id]);
            $promo = $stmtPromo->fetch();
            
            if (!$promo || $promo['kuota'] <= 0 || date('Y-m-d') < $promo['tanggal_mulai'] || date('Y-m-d') > $promo['tanggal_selesai']) {
                throw new Exception("Promo tidak valid, kadaluarsa, atau kuota habis.");
            }
            if ($pesanan['subtotal'] < $promo['min_belanja']) {
                throw new Exception("Minimum belanja promo tidak terpenuhi.");
            }
            
            // Hitung diskon
            if ($promo['tipe_diskon'] === 'persen') {
                $diskon_nominal = ($pesanan['subtotal'] * $promo['nilai_diskon']) / 100;
            } else {
                $diskon_nominal = $promo['nilai_diskon'];
            }
            if ($diskon_nominal > $pesanan['subtotal']) $diskon_nominal = $pesanan['subtotal'];
            
            // Hitung ulang Pajak & Service
            $subtotal_bersih = $pesanan['subtotal'] - $diskon_nominal;
            $new_service = ($subtotal_bersih * $pesanan['service_persen']) / 100;
            $new_pajak = (($subtotal_bersih + $new_service) * $pesanan['pajak_persen']) / 100;
            $total_tagihan = $subtotal_bersih + $new_service + $new_pajak;
            
            // Update pesanan dgn nilai baru
            $stmtUpdateP = $pdo->prepare("UPDATE pesanan SET promo_id = ?, diskon_nominal = ?, service_nominal = ?, pajak_nominal = ?, total_harga = ? WHERE id = ?");
            $stmtUpdateP->execute([$promo_id, $diskon_nominal, $new_service, $new_pajak, $total_tagihan, $pesanan_id]);
            
            // Kurangi kuota promo
            $pdo->prepare("UPDATE promo SET kuota = kuota - 1 WHERE id = ?")->execute([$promo_id]);
        }
        
        if ($jumlah_bayar < $total_tagihan) {
            throw new Exception("Jumlah bayar tidak mencukupi!");
        }
        
        $kembalian = $jumlah_bayar - $total_tagihan;
        
        // 3. Insert ke tabel pembayaran
        $stmtBayar = $pdo->prepare("INSERT INTO pembayaran (pesanan_id, sesi_kasir_id, metode_pembayaran, jumlah_bayar, kembalian, waktu_bayar) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtBayar->execute([$pesanan_id, $sesi_id, $metode_pembayaran, $jumlah_bayar, $kembalian]);
        
        // 4. Update pesanan menjadi dibayar
        $stmtUpdatePesan = $pdo->prepare("UPDATE pesanan SET status_pesanan = 'dibayar' WHERE id = ?");
        $stmtUpdatePesan->execute([$pesanan_id]);
        
        // 5. Kosongkan meja jika dine-in
        if ($pesanan['meja_id']) {
            $stmtMeja = $pdo->prepare("UPDATE meja SET status = 'kosong' WHERE id = ?");
            $stmtMeja->execute([$pesanan['meja_id']]);
        }
        
        // 6. Update total pemasukan di sesi kasir
        $stmtUpdateSesi = $pdo->prepare("UPDATE sesi_kasir SET total_pemasukan = total_pemasukan + ? WHERE id = ?");
        $stmtUpdateSesi->execute([$total_tagihan, $sesi_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Pembayaran berhasil! Kembalian: Rp " . number_format($kembalian, 0, ',', '.');
        // Redirect ke cetak struk atau kembali ke index
        header('Location: ' . BASE_URL . '/modules/kasir/struk.php?pesanan_id=' . $pesanan_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: ' . BASE_URL . '/modules/kasir/index.php');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/modules/kasir/index.php');
    exit;
}

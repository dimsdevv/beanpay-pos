<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Parse Cart Data
        $cart_json = $_POST['cart_data'] ?? '[]';
        $cart = json_decode($cart_json, true);
        
        if (empty($cart)) {
            throw new Exception("Keranjang kosong!");
        }

        $tipe_pesanan = $_POST['tipe_pesanan'] ?? 'dine_in';
        $meja_id = !empty($_POST['meja_id']) ? (int)$_POST['meja_id'] : null;
        $nama_pelanggan = $_POST['nama_pelanggan'] ?? '';
        $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
        if (!in_array($metode_pembayaran, ['cash', 'qris', 'transfer'], true)) {
            throw new Exception('Metode pembayaran tidak valid.');
        }
        $jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);
        $promo_id = !empty($_POST['promo_id']) ? (int)$_POST['promo_id'] : null;

        // Read tax/service settings from DB
        $stmtSet = $pdo->query("SELECT * FROM pengaturan");
        $settings = [];
        while ($s = $stmtSet->fetch()) {
            $settings[$s['kunci']] = $s['nilai'];
        }
        $service_persen = ($settings['aktifkan_service'] ?? '1') === '1' ? (float)($settings['service_charge_persen'] ?? 5) : 0;
        $pajak_persen = ($settings['aktifkan_pajak'] ?? '1') === '1' ? (float)($settings['pajak_persen'] ?? 10) : 0;
        $prefix_order = strtoupper(substr(trim($settings['prefix_pesanan'] ?? 'ORD'), 0, 5));

        $pdo->beginTransaction();
        
        // 1. Cek sesi kasir
        $stmtSesi = $pdo->prepare("SELECT id FROM sesi_kasir WHERE kasir_id = ? AND status = 'buka' LIMIT 1 FOR UPDATE");
        $stmtSesi->execute([$_SESSION['user_id']]);
        $sesi = $stmtSesi->fetch();
        
        if (!$sesi) {
            throw new Exception("Shift kasir belum dibuka!");
        }
        $sesi_id = $sesi['id'];

        // 2. Kalkulasi subtotal pakai harga resmi database
        $subtotal = 0;
        $cartItems = [];
        $stmtMenu = $pdo->prepare("SELECT id, nama_menu, harga, status FROM menu WHERE id = ? AND is_active = 1 FOR UPDATE");
        foreach ($cart as $item) {
            $menuId = (int)($item['id'] ?? 0);
            $qty = max(0, (int)($item['qty'] ?? 0));
            if ($menuId <= 0 || $qty <= 0) {
                throw new Exception("Item menu tidak valid.");
            }

            $stmtMenu->execute([$menuId]);
            $menuRow = $stmtMenu->fetch();
            if (!$menuRow || $menuRow['status'] !== 'tersedia') {
                throw new Exception("Menu tidak tersedia.");
            }

            $harga = (float)$menuRow['harga'];
            $subtotal += $harga * $qty;
            $cartItems[] = [
                'id' => $menuId,
                'qty' => $qty,
                'harga' => $harga,
                'catatan' => trim($item['catatan'] ?? ''),
            ];
        }

        // Generate Nomor Pesanan
        $dateCode = date('ymd');
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE DATE(waktu_pesan) = CURDATE()");
        $urut = $stmtCount->fetchColumn() + 1;
        $nomor_pesanan = $prefix_order . $dateCode . str_pad($urut, 3, '0', STR_PAD_LEFT);

        $diskon_nominal = 0;
        
        // 3. Cek Promo
        if ($promo_id) {
            $stmtPromo = $pdo->prepare("SELECT * FROM promo WHERE id = ? AND status = 'aktif' FOR UPDATE");
            $stmtPromo->execute([$promo_id]);
            $promo = $stmtPromo->fetch();
            
            if ($promo && $promo['kuota'] > 0 && date('Y-m-d') >= $promo['tanggal_mulai'] && date('Y-m-d') <= $promo['tanggal_selesai']) {
                if ($subtotal >= $promo['min_belanja']) {
                    if ($promo['tipe_diskon'] === 'persen') {
                        $diskon_nominal = ($subtotal * $promo['nilai_diskon']) / 100;
                    } else {
                        $diskon_nominal = $promo['nilai_diskon'];
                    }
                    if ($diskon_nominal > $subtotal) $diskon_nominal = $subtotal;
                    $pdo->prepare("UPDATE promo SET kuota = kuota - 1 WHERE id = ?")->execute([$promo_id]);
                } else {
                    $promo_id = null;
                }
            } else {
                $promo_id = null;
            }
        }

        $subtotal_bersih = $subtotal - $diskon_nominal;
        $service_nominal = round(($subtotal_bersih * $service_persen) / 100 / 100) * 100;
        $pajak_nominal = round((($subtotal_bersih + $service_nominal) * $pajak_persen) / 100 / 100) * 100;
        $total_harga = $subtotal_bersih + $service_nominal + $pajak_nominal;

        if ($jumlah_bayar < $total_harga) {
            throw new Exception("Jumlah bayar kurang dari total tagihan!");
        }
        $kembalian = $jumlah_bayar - $total_harga;

        // 4. Insert Pesanan (State: pending)
        $stmtPesan = $pdo->prepare("INSERT INTO pesanan (nomor_pesanan, meja_id, waiter_id, tipe_pesanan, nama_pelanggan, subtotal, service_persen, pajak_persen, promo_id, diskon_nominal, service_nominal, pajak_nominal, total_harga, status_pesanan, waktu_pesan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmtPesan->execute([
            $nomor_pesanan,
            $tipe_pesanan === 'dine_in' ? $meja_id : null,
            $_SESSION['user_id'],
            $tipe_pesanan,
            $nama_pelanggan,
            $subtotal,
            $service_persen,
            $pajak_persen,
            $promo_id,
            $diskon_nominal,
            $service_nominal,
            $pajak_nominal,
            $total_harga
        ]);
        
        $pesanan_id = $pdo->lastInsertId();

        // 5. Insert Detail Pesanan
        $stmtDetail = $pdo->prepare("INSERT INTO detail_pesanan (pesanan_id, menu_id, qty, harga_satuan, catatan) VALUES (?, ?, ?, ?, ?)");
        foreach ($cartItems as $item) {
            $stmtDetail->execute([
                $pesanan_id,
                $item['id'],
                $item['qty'],
                $item['harga'],
                $item['catatan']
            ]);
        }
 
        // 5b. Auto-transition: pending -> diproses -> selesai (no dapur)
        $pdo->prepare("UPDATE pesanan SET status_pesanan = 'diproses' WHERE id = ?")->execute([$pesanan_id]);
        $pdo->prepare("UPDATE detail_pesanan SET status_item = 'cooking', waktu_mulai_masak = NOW() WHERE pesanan_id = ?")->execute([$pesanan_id]);
        $pdo->prepare("UPDATE pesanan SET status_pesanan = 'selesai' WHERE id = ?")->execute([$pesanan_id]);
        $pdo->prepare("UPDATE detail_pesanan SET status_item = 'ready', waktu_selesai_masak = NOW() WHERE pesanan_id = ?")->execute([$pesanan_id]);

        // 6. Kurangi stok bahan berdasarkan resep
        $stmtDetailPesanan = $pdo->prepare("SELECT dp.menu_id, dp.qty, m.nama_menu FROM detail_pesanan dp JOIN menu m ON dp.menu_id = m.id WHERE dp.pesanan_id = ?");
        $stmtDetailPesanan->execute([$pesanan_id]);
        $detailPesanan = $stmtDetailPesanan->fetchAll();

        $stmtResep = $pdo->prepare("SELECT rm.bahan_id, rm.jumlah_dibutuhkan, b.nama_bahan, b.stok_sekarang FROM resep_menu rm JOIN bahan_baku b ON rm.bahan_id = b.id WHERE rm.menu_id = ? FOR UPDATE");
        $stmtUpdateBahan = $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang - ? WHERE id = ?");
        foreach ($detailPesanan as $detail) {
            $stmtResep->execute([$detail['menu_id']]);
            $recipes = $stmtResep->fetchAll();
            foreach ($recipes as $recipe) {
                $totalNeeded = $detail['qty'] * (float)$recipe['jumlah_dibutuhkan'];
                if ($totalNeeded > (float)$recipe['stok_sekarang']) {
                    throw new Exception("Stok tidak mencukupi untuk bahan \"{$recipe['nama_bahan']}\" pada menu \"{$detail['nama_menu']}\".");
                }
                $stmtUpdateBahan->execute([$totalNeeded, $recipe['bahan_id']]);
            }
        }
        $pdo->prepare("UPDATE pesanan SET stock_deduction_done = 1 WHERE id = ?")->execute([$pesanan_id]);

        // 7. Insert Pembayaran
        $stmtBayar = $pdo->prepare("INSERT INTO pembayaran (pesanan_id, sesi_kasir_id, metode_pembayaran, jumlah_bayar, kembalian, waktu_bayar) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtBayar->execute([$pesanan_id, $sesi_id, $metode_pembayaran, $jumlah_bayar, $kembalian]);

        // 7b. Update status pesanan jadi dibayar
        $pdo->prepare("UPDATE pesanan SET status_pesanan = 'dibayar' WHERE id = ?")->execute([$pesanan_id]);

        // 7c. Update Sesi Kasir
        $stmtUpdateSesi = $pdo->prepare("UPDATE sesi_kasir SET total_pemasukan = total_pemasukan + ? WHERE id = ?");
        $stmtUpdateSesi->execute([$total_harga, $sesi_id]);

        // 8. Update Meja ke kosong
        if ($meja_id && $tipe_pesanan === 'dine_in') {
            $pdo->prepare("UPDATE meja SET status = 'kosong' WHERE id = ?")->execute([$meja_id]);
        }

        $pdo->commit();

        $_SESSION['success'] = "Pembayaran berhasil! Kembalian: Rp " . number_format($kembalian, 0, ',', '.');
        header('Location: ' . BASE_URL . '/modules/kasir/struk.php?pesanan_id=' . $pesanan_id);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: ' . BASE_URL . '/modules/kasir/index.php');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/modules/kasir/index.php');
    exit;
}

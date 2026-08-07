<?php
/**
 * Checkpoint POS — Handler Administrasi Keuangan
 * Menangani simpan / ubah / hapus pengeluaran bahan & atur anggaran.
 * Merespons dengan JSON untuk dipanggil via fetch() dari halaman keuangan.php
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

requireRole(['admin', 'kasir']);
requireCsrfToken();

// ---------------------------------------------------------------
// Pastikan tabel ada (idempoten — aman dijalankan berulang kali)
// ---------------------------------------------------------------
function ensureKeuanganTables(): void {
    global $pdo;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengeluaran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            supplier VARCHAR(120) DEFAULT NULL,
            kategori ENUM('pembukaan','operasional','lainnya') NOT NULL DEFAULT 'operasional',
            keterangan TEXT,
            metode_bayar ENUM('cash','qris','transfer') NOT NULL DEFAULT 'cash',
            bukti VARCHAR(255) DEFAULT NULL,
            total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            stok_updated TINYINT(1) NOT NULL DEFAULT 0,
            input_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tanggal (tanggal),
            INDEX idx_kategori (kategori)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengeluaran_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pengeluaran_id INT NOT NULL,
            bahan_id INT DEFAULT NULL,
            nama_bahan VARCHAR(120) NOT NULL,
            qty DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            satuan VARCHAR(50) DEFAULT '',
            satuan_beli VARCHAR(50) DEFAULT '',
            konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
            qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            harga_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran(id) ON DELETE CASCADE,
            INDEX idx_bahan (bahan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    
    // Auto-patch existing tables for unit conversions
    $cols = $pdo->query("SHOW COLUMNS FROM pengeluaran_item")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('satuan_beli', $cols)) {
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN satuan_beli VARCHAR(50) DEFAULT '' AFTER satuan");
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN konversi DECIMAL(12,4) NOT NULL DEFAULT 1.0000 AFTER satuan_beli");
        $pdo->exec("ALTER TABLE pengeluaran_item ADD COLUMN qty_beli DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER konversi");
        // Migrate old data
        $pdo->exec("UPDATE pengeluaran_item SET qty_beli = qty, satuan_beli = satuan, konversi = 1 WHERE qty_beli = 0 AND qty > 0");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS anggaran_bulan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            periode CHAR(7) NOT NULL UNIQUE,
            nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
}

ensureKeuanganTables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Aksi tidak dikenali.'];

try {
    // ===========================================================
    // SIMPAN (baru / ubah)
    // ===========================================================
    if ($action === 'save') {
        $id            = (int)($_POST['id'] ?? 0);
        $tanggal       = trim($_POST['tanggal'] ?? '');
        $supplier      = trim($_POST['supplier'] ?? '');
        $kategori      = in_array($_POST['kategori'] ?? '', ['pembukaan','operasional','lainnya']) ? $_POST['kategori'] : 'operasional';
        $keterangan    = trim($_POST['keterangan'] ?? '');
        $metode        = in_array($_POST['metode_bayar'] ?? '', ['cash','qris','transfer']) ? $_POST['metode_bayar'] : 'cash';
        $stokUpdated   = !empty($_POST['stok_updated']) ? 1 : 0;
        $rawItems      = json_decode($_POST['items'] ?? '[]', true);
        $items         = is_array($rawItems) ? $rawItems : [];

        if (!$tanggal || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            throw new Exception('Tanggal belanja harus diisi dengan benar.');
        }
        if (empty($items)) {
            throw new Exception('Minimal satu item bahan harus dicatat.');
        }

        // Validasi & hitung total
        $cleanItems = [];
        $total = 0;
        foreach ($items as $it) {
            $nama   = trim((string)($it['nama_bahan'] ?? ''));
            $qtyBeli = (float)($it['qty_beli'] ?? $it['qty'] ?? 0);
            $harga   = (float)($it['harga_satuan'] ?? 0);
            $bid     = !empty($it['bahan_id']) ? (int)$it['bahan_id'] : null;
            
            if ($nama === '') continue;
            if ($qtyBeli <= 0) throw new Exception("Jumlah untuk \"$nama\" harus lebih dari 0.");
            if ($harga < 0) throw new Exception("Harga untuk \"$nama\" tidak valid.");
            
            $satuanBeli = trim((string)($it['satuan_beli'] ?? $it['satuan'] ?? ''));
            $konversi = (float)($it['konversi'] ?? 1);
            if ($konversi <= 0) $konversi = 1;
            
            // Calculate base qty
            $baseQty = $qtyBeli * $konversi;
            if ($baseQty <= 0) throw new Exception("Jumlah konversi untuk \"$nama\" harus lebih dari 0.");
            
            $sub = round($qtyBeli * $harga, 2);
            
            // Average costing logic for harga_beli per base unit
            // (Total Price / Total Base Qty)
            $hargaBaseUnit = $baseQty > 0 ? ($sub / $baseQty) : 0;

            $total += $sub;
            $cleanItems[] = [
                'bahan_id'      => $bid,
                'nama_bahan'    => $nama,
                'qty'           => $baseQty, // Qty saved to inventory is baseQty
                'satuan'        => trim((string)($it['satuan'] ?? '')), // Base unit name
                'satuan_beli'   => $satuanBeli,
                'konversi'      => $konversi,
                'qty_beli'      => $qtyBeli,
                'harga_satuan'  => $harga, // Price per purchase unit
                'harga_base'    => $hargaBaseUnit, // Calculated price per base unit
                'subtotal'      => $sub,
            ];
        }
        if (empty($cleanItems)) {
            throw new Exception('Data item tidak valid.');
        }

        $pdo->beginTransaction();

        // Jika ubah: revert stok lama bila sebelumnya di-update
        $oldStokUpdated = 0;
        $oldItems = [];
        if ($id > 0) {
            $stmtOld = $pdo->prepare("SELECT stok_updated, bukti, input_by FROM pengeluaran WHERE id = ?");
            $stmtOld->execute([$id]);
            $old = $stmtOld->fetch();
            if (!$old) throw new Exception('Pengeluaran tidak ditemukan.');
            // Kasir hanya bisa ubah pengeluaran miliknya sendiri
            if ($_SESSION['role'] === 'kasir' && (int)$old['input_by'] !== (int)$_SESSION['user_id']) {
                throw new Exception('Anda hanya bisa mengubah pengeluaran yang Anda input sendiri.');
            }
            $oldStokUpdated = (int)$old['stok_updated'];
            if ($oldStokUpdated) {
                $stmtOI = $pdo->prepare("SELECT bahan_id, qty FROM pengeluaran_item WHERE pengeluaran_id = ?");
                $stmtOI->execute([$id]);
                $oldItems = $stmtOI->fetchAll();
                foreach ($oldItems as $oi) {
                    if ($oi['bahan_id']) {
                        $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang - ? WHERE id = ?")
                            ->execute([$oi['qty'], $oi['bahan_id']]);
                    }
                }
            }
            // Hapus item lama (akan di-insert ulang)
            $pdo->prepare("DELETE FROM pengeluaran_item WHERE pengeluaran_id = ?")->execute([$id]);
        }

        // Upload bukti (opsional)
        $buktiName = null;
        if ($id > 0 && $old) $buktiName = $old['bukti'];
        if (!empty($_FILES['bukti']) && ($_FILES['bukti']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $buktiName = handleTransferUpload($_FILES['bukti'], 'bukti');
            // hapus bukti lama bila ada
            if ($id > 0 && !empty($old['bukti']) && $old['bukti'] !== $buktiName) {
                @unlink(__DIR__ . '/../../assets/uploads/bukti/' . $old['bukti']);
            }
        }

        if ($id > 0) {
            $pdo->prepare("
                UPDATE pengeluaran SET tanggal=?, supplier=?, kategori=?, keterangan=?, metode_bayar=?, bukti=?, total=?, stok_updated=?
                WHERE id=?
            ")->execute([$tanggal, $supplier ?: null, $kategori, $keterangan, $metode, $buktiName, $total, $stokUpdated, $id]);
            $expenseId = $id;
            $auditAction = 'update_pengeluaran';
        } else {
            $pdo->prepare("
                INSERT INTO pengeluaran (tanggal, supplier, kategori, keterangan, metode_bayar, bukti, total, stok_updated, input_by)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([$tanggal, $supplier ?: null, $kategori, $keterangan, $metode, $buktiName, $total, $stokUpdated, $_SESSION['user_id'] ?? null]);
            $expenseId = $pdo->lastInsertId();
            $auditAction = 'tambah_pengeluaran';
        }

        // Insert item + (opsional) update stok & harga beli (average cost)
        $stmtItem = $pdo->prepare("
            INSERT INTO pengeluaran_item (pengeluaran_id, bahan_id, nama_bahan, qty, satuan, satuan_beli, konversi, qty_beli, harga_satuan, subtotal)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        foreach ($cleanItems as $ci) {
            $stmtItem->execute([
                $expenseId, $ci['bahan_id'], $ci['nama_bahan'], $ci['qty'], $ci['satuan'], 
                $ci['satuan_beli'], $ci['konversi'], $ci['qty_beli'], $ci['harga_satuan'], $ci['subtotal']
            ]);
            
            if ($stokUpdated && $ci['bahan_id']) {
                // Average Cost Calculation
                $stmtStok = $pdo->prepare("SELECT stok_sekarang, harga_beli FROM bahan_baku WHERE id = ?");
                $stmtStok->execute([$ci['bahan_id']]);
                $b = $stmtStok->fetch();
                
                if ($b) {
                    $oldStok = (float)$b['stok_sekarang'];
                    $oldHarga = (float)$b['harga_beli'];
                    $newQty = $ci['qty']; // this is baseQty
                    $newPriceBase = $ci['harga_base'];
                    
                    // Prevent negative stock affecting average cost calculation weirdly
                    $validOldStok = max(0, $oldStok); 
                    
                    $totalValueOld = $validOldStok * $oldHarga;
                    $totalValueNew = $newQty * $newPriceBase;
                    
                    $stokAkhir = $oldStok + $newQty; // actual new stock
                    $stokForAvg = $validOldStok + $newQty;
                    
                    $avgHarga = $stokForAvg > 0 ? ($totalValueOld + $totalValueNew) / $stokForAvg : $newPriceBase;
                    
                    $pdo->prepare("
                        UPDATE bahan_baku SET stok_sekarang = ?, harga_beli = ?
                        WHERE id = ?
                    ")->execute([$stokAkhir, $avgHarga, $ci['bahan_id']]);
                }
            }
        }

        $pdo->commit();

        $label = $supplier ?: (count($cleanItems) . ' item bahan');
        logAuditAction($auditAction, 'pengeluaran', $expenseId, "Belanja: $label · Total Rp " . number_format($total, 0, ',', '.') . " · Kategori: $kategori");

        $response = [
            'success'  => true,
            'id'       => $expenseId,
            'message'  => $id > 0 ? 'Pengeluaran berhasil diperbarui.' : 'Pengeluaran berhasil dicatat.',
        ];
    }

    // ===========================================================
    // HAPUS
    // ===========================================================
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID tidak valid.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT stok_updated, bukti, supplier, total, input_by FROM pengeluaran WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Pengeluaran tidak ditemukan.');
        // Kasir hanya bisa hapus pengeluaran miliknya sendiri
        if ($_SESSION['role'] === 'kasir' && (int)$row['input_by'] !== (int)$_SESSION['user_id']) {
            throw new Exception('Anda hanya bisa menghapus pengeluaran yang Anda input sendiri.');
        }

        if ($row['stok_updated']) {
            $stmtI = $pdo->prepare("SELECT bahan_id, qty FROM pengeluaran_item WHERE pengeluaran_id = ?");
            $stmtI->execute([$id]);
            foreach ($stmtI->fetchAll() as $oi) {
                if ($oi['bahan_id']) {
                    $pdo->prepare("UPDATE bahan_baku SET stok_sekarang = stok_sekarang - ? WHERE id = ?")
                        ->execute([$oi['qty'], $oi['bahan_id']]);
                }
            }
        }
        if (!empty($row['bukti'])) {
            @unlink(__DIR__ . '/../../assets/uploads/bukti/' . $row['bukti']);
        }
        $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?")->execute([$id]);
        $pdo->commit();

        logAuditAction('hapus_pengeluaran', 'pengeluaran', $id, "Belanja dihapus: " . ($row['supplier'] ?: 'tanpa supplier') . " · Rp " . number_format($row['total'], 0, ',', '.'));
        $response = ['success' => true, 'message' => 'Pengeluaran berhasil dihapus.'];
    }

    // ===========================================================
    // AMBIL 1 DATA (untuk edit)
    // ===========================================================
    elseif ($action === 'get') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Data tidak ditemukan.');
        $stmtI = $pdo->prepare("SELECT bahan_id, nama_bahan, qty_beli, satuan_beli, konversi, qty, satuan, harga_satuan, subtotal FROM pengeluaran_item WHERE pengeluaran_id = ?");
        $stmtI->execute([$id]);
        $row['items'] = $stmtI->fetchAll();
        $response = ['success' => true, 'data' => $row];
    }

    // ===========================================================
    // ATUR ANGGARAN BULANAN
    // ===========================================================
    elseif ($action === 'set_budget') {
        $periode = trim($_POST['periode'] ?? '');
        $nominal = (float)($_POST['nominal'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) throw new Exception('Periode tidak valid.');
        if ($nominal < 0) throw new Exception('Anggaran tidak boleh negatif.');

        $stmtCek = $pdo->prepare("SELECT id FROM anggaran_bulan WHERE periode = ?");
        $stmtCek->execute([$periode]);
        if ($stmtCek->fetch()) {
            $pdo->prepare("UPDATE anggaran_bulan SET nominal = ? WHERE periode = ?")->execute([$nominal, $periode]);
        } else {
            $pdo->prepare("INSERT INTO anggaran_bulan (periode, nominal) VALUES (?,?)")->execute([$periode, $nominal]);
        }
        logAuditAction('set_budget', 'anggaran_bulan', null, "Periode $periode: Rp " . number_format($nominal, 0, ',', '.'));
        $response = ['success' => true, 'message' => 'Anggaran bulan ' . $periode . ' disimpan.'];
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;

<?php
/**
 * Checkpoint POS — Handler Tambah/Ubah Menu Kasir
 * Simpan menu + resep bahan + kategori baru. Merespons JSON.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

requireRole(['kasir', 'admin']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Aksi tidak dikenali.'];

try {
    // ===========================================================
    // TAMBAH KATEGORI BARU
    // ===========================================================
    if ($action === 'add_kategori') {
        $nama = trim($_POST['nama_kategori'] ?? '');
        if ($nama === '') throw new Exception('Nama kategori wajib diisi.');

        $stmtCek = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ?");
        $stmtCek->execute([$nama]);
        if ($stmtCek->fetch()) throw new Exception("Kategori \"$nama\" sudah ada.");

        $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)")->execute([$nama]);
        logAuditAction('tambah_kategori_kasir', 'kategori', $pdo->lastInsertId(), "Kategori: $nama (oleh kasir)");
        $response = ['success' => true, 'message' => "Kategori \"$nama\" berhasil dibuat.", 'id' => (int)$pdo->lastInsertId()];
    }

    // ===========================================================
    // SIMPAN MENU + RESEP
    // ===========================================================
    elseif ($action === 'save_menu') {
        $id          = (int)($_POST['id'] ?? 0);
        $nama_menu   = trim($_POST['nama_menu'] ?? '');
        $kategori_id = (int)($_POST['kategori_id'] ?? 0);
        $harga       = (float)($_POST['harga'] ?? 0);
        $status      = in_array($_POST['status'] ?? '', ['tersedia', 'habis']) ? $_POST['status'] : 'tersedia';
        $rawItems    = json_decode($_POST['items'] ?? '[]', true);
        $items       = is_array($rawItems) ? $rawItems : [];

        if ($nama_menu === '') throw new Exception('Nama menu wajib diisi.');
        if ($kategori_id <= 0) throw new Exception('Pilih kategori terlebih dahulu.');
        if ($harga <= 0) throw new Exception('Harga jual harus lebih dari 0.');
        if (empty($items)) throw new Exception('Tambahkan minimal satu bahan (resep) agar menu bisa dipesan.');

        // Validasi resep
        $cleanItems = [];
        foreach ($items as $it) {
            $bid = (int)($it['bahan_id'] ?? 0);
            $qty = (float)($it['jumlah'] ?? 0);
            if ($bid <= 0 || $qty <= 0) continue;
            $cleanItems[] = ['bahan_id' => $bid, 'jumlah' => $qty];
        }
        if (empty($cleanItems)) throw new Exception('Resep tidak valid — pilih bahan dan isi jumlah dengan benar.');

        $pdo->beginTransaction();

        // Upload gambar (opsional)
        $gambarName = null;
        if ($id > 0) {
            $stmtOld = $pdo->prepare("SELECT gambar FROM menu WHERE id = ?");
            $stmtOld->execute([$id]);
            $gambarName = $stmtOld->fetchColumn();
        }
        if (!empty($_FILES['gambar']) && ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $gambarName = handleMenuImageUpload($_FILES['gambar']);
            if ($id > 0 && $gambarName !== null && $stmtOld->fetchColumn()) {
                // tidak perlu hapus lama, handleMenuImageUpload menangani path baru
            }
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE menu SET kategori_id=?, nama_menu=?, harga=?, gambar=?, status=? WHERE id=?")
                ->execute([$kategori_id, $nama_menu, $harga, $gambarName, $status, $id]);
            $menuId = $id;
            // Hapus resep lama (akan di-insert ulang)
            $pdo->prepare("DELETE FROM resep_menu WHERE menu_id = ?")->execute([$menuId]);
            $auditAction = 'update_menu_kasir';
        } else {
            $pdo->prepare("INSERT INTO menu (kategori_id, nama_menu, harga, gambar, status) VALUES (?,?,?,?,?)")
                ->execute([$kategori_id, $nama_menu, $harga, $gambarName, $status]);
            $menuId = $pdo->lastInsertId();
            $auditAction = 'tambah_menu_kasir';
        }

        // Insert resep
        $stmtResep = $pdo->prepare("INSERT INTO resep_menu (menu_id, bahan_id, jumlah_dibutuhkan) VALUES (?,?,?)");
        foreach ($cleanItems as $ci) {
            $stmtResep->execute([$menuId, $ci['bahan_id'], $ci['jumlah']]);
        }

        $pdo->commit();

        logAuditAction($auditAction, 'menu', $menuId, "Menu: $nama_menu · Harga Rp " . number_format($harga, 0, ',', '.') . " · " . count($cleanItems) . " bahan · oleh " . $_SESSION['role']);
        $response = ['success' => true, 'id' => $menuId, 'message' => $id > 0 ? "Menu \"$nama_menu\" berhasil diperbarui." : "Menu \"$nama_menu\" berhasil dibuat & siap dipesan."];
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;

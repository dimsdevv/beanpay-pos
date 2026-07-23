<?php
require_once __DIR__ . '/../config/database.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$steps = [];

$defaults = [
    ['nama_toko',          'Checkpoint Cafe', 'Nama toko / restoran'],
    ['alamat_toko',        '',                'Alamat toko / restoran'],
    ['telepon_toko',       '',                'Nomor telepon toko'],
    ['cetak_otomatis',     '1',               'Cetak struk otomatis setelah bayar (1=ya, 0=tidak)'],
    ['batas_stok_rendah',  '10',              'Ambang batas stok rendah untuk peringatan'],
    ['prefix_pesanan',     'ORD',             'Awalan nomor pesanan (maks 5 karakter)'],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO pengaturan (kunci, nilai, deskripsi) VALUES (?, ?, ?)");
foreach ($defaults as $d) {
    try {
        $stmt->execute($d);
        $steps[] = "✅ {$d[0]} = {$d[1]}";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $steps[] = "⏭ {$d[0]} sudah ada";
        } else {
            $steps[] = "❌ {$d[0]}: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Migrasi Fitur Pengaturan</title>
<style>
body{font-family:'Courier New',monospace;background:#f8fafc;padding:40px}
.card{max-width:600px;margin:0 auto;background:white;border:1px solid #e2e8f0;border-radius:8px;padding:32px}
h1{font-size:20px;color:#0f172a;margin-bottom:24px}
.step{padding:6px 10px;margin-bottom:4px;border-radius:4px;font-size:13px}
.ok{background:#f0fdf4;color:#166534}
.skip{background:#fefce8;color:#a16207}
.err{background:#fef2f2;color:#991b1b}
</style>
</head>
<body>
<div class="card">
<h1>⚙️ Migrasi Fitur Pengaturan</h1>
<?php foreach($steps as $s): ?>
<div class="step <?= str_starts_with($s, '✅') ? 'ok' : (str_starts_with($s, '⏭') ? 'skip' : 'err') ?>"><?= htmlspecialchars($s) ?></div>
<?php endforeach; ?>
</div></body></html>

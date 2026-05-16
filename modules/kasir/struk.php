<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['kasir', 'admin']);

$pesanan_id = (int)$_GET['pesanan_id'] ?? 0;

// Ambil data pembayaran & pesanan
$stmt = $pdo->prepare("SELECT p.*, b.metode_pembayaran, b.jumlah_bayar, b.kembalian, b.waktu_bayar, m.nomor_meja, u.nama_lengkap as nama_kasir
                       FROM pesanan p 
                       JOIN pembayaran b ON p.id = b.pesanan_id 
                       LEFT JOIN meja m ON p.meja_id = m.id
                       JOIN sesi_kasir s ON b.sesi_kasir_id = s.id
                       JOIN users u ON s.kasir_id = u.id
                       WHERE p.id = ?");
$stmt->execute([$pesanan_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Data pesanan tidak valid atau belum dibayar.");
}

// Ambil item
$stmtItems = $pdo->prepare("SELECT d.*, m.nama_menu FROM detail_pesanan d JOIN menu m ON d.menu_id = m.id WHERE d.pesanan_id = ?");
$stmtItems->execute([$pesanan_id]);
$items = $stmtItems->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - <?= $order['nomor_pesanan'] ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #e2e8f0;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .ticket {
            width: 300px; /* Thermal printer width */
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        h1, h2, h3, h4, h5, h6, p {
            margin: 0;
        }
        .center {
            text-align: center;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
        }
        .mb-2 { margin-bottom: 5px; }
        .mt-4 { margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .col-qty { width: 10%; text-align: left; }
        .col-item { width: 50%; text-align: left; }
        .col-price { width: 40%; text-align: right; }
        
        .btn-print {
            display: block;
            width: 100%;
            padding: 10px;
            background: #709255;
            color: white;
            border: none;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn-back {
            display: block;
            width: 100%;
            padding: 10px;
            background: #e2e8f0;
            color: #475569;
            text-decoration: none;
            text-align: center;
            font-family: sans-serif;
            font-weight: bold;
            border-radius: 5px;
            margin-top: 10px;
            box-sizing: border-box;
        }
        
        .btn-wa {
            display: block;
            width: 100%;
            padding: 10px;
            background: #25D366;
            color: white;
            border: none;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        @media print {
            body { background: transparent; padding: 0; display: block; }
            .ticket { box-shadow: none; padding: 0; width: 100%; max-width: 300px; margin: 0 auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="ticket">
        <div class="center">
            <h2>BEANPAY CAFE</h2>
            <p>Jl. Coffee Avenue No. 123</p>
            <p>Telp: 0812-3456-7890</p>
        </div>
        
        <div class="divider"></div>
        
        <div class="flex-between mb-2">
            <span>Order No:</span>
            <span><?= $order['nomor_pesanan'] ?></span>
        </div>
        <div class="flex-between mb-2">
            <span>Tanggal:</span>
            <span><?= date('d/m/Y H:i', strtotime($order['waktu_bayar'])) ?></span>
        </div>
        <div class="flex-between mb-2">
            <span>Kasir:</span>
            <span><?= $order['nama_kasir'] ?></span>
        </div>
        <div class="flex-between mb-2">
            <span>Tipe:</span>
            <span><?= $order['tipe_pesanan'] == 'dine_in' ? 'Dine In (Meja ' . $order['nomor_meja'] . ')' : 'Take Away' ?></span>
        </div>
        <div class="flex-between mb-2">
            <span>Pelanggan:</span>
            <span><?= $order['nama_pelanggan'] ?: 'Guest' ?></span>
        </div>
        
        <div class="divider"></div>
        
        <table>
            <?php foreach($items as $item): ?>
            <tr>
                <td class="col-qty"><?= $item['qty'] ?>x</td>
                <td class="col-item"><?= $item['nama_menu'] ?></td>
                <td class="col-price"><?= number_format($item['harga_satuan'] * $item['qty'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="divider"></div>
        
        <div class="flex-between mb-2">
            <span>Subtotal:</span>
            <span><?= number_format($order['subtotal'] > 0 ? $order['subtotal'] : $order['total_harga'], 0, ',', '.') ?></span>
        </div>
        <?php if($order['service_nominal'] > 0): ?>
        <div class="flex-between mb-2">
            <span>Service (<?= floatval($order['service_persen']) ?>%):</span>
            <span><?= number_format($order['service_nominal'], 0, ',', '.') ?></span>
        </div>
        <?php endif; ?>
        <?php if($order['pajak_nominal'] > 0): ?>
        <div class="flex-between mb-2">
            <span>PB1 Tax (<?= floatval($order['pajak_persen']) ?>%):</span>
            <span><?= number_format($order['pajak_nominal'], 0, ',', '.') ?></span>
        </div>
        <?php endif; ?>
        <div class="flex-between mb-2 mt-2" style="font-size: 14px;">
            <strong>Total Tagihan:</strong>
            <strong><?= number_format($order['total_harga'], 0, ',', '.') ?></strong>
        </div>
        
        <div class="divider"></div>
        
        <div class="flex-between mb-2">
            <span>Pembayaran (<?= strtoupper($order['metode_pembayaran']) ?>):</span>
            <span><?= number_format($order['jumlah_bayar'], 0, ',', '.') ?></span>
        </div>
        <div class="flex-between mb-2">
            <span>Kembalian:</span>
            <span><?= number_format($order['kembalian'], 0, ',', '.') ?></span>
        </div>
        
        <div class="divider"></div>
        
        <div class="center mt-4">
            <p>Terima kasih atas kunjungan Anda!</p>
            <p><i>Layanan ini didukung oleh BeanPay</i></p>
        </div>
        
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">Cetak Struk (Print)</button>
            <button class="btn-wa" onclick="sendWhatsApp()">Kirim Struk via WA</button>
            <a href="index.php" class="btn-back">Kembali ke Kasir</a>
        </div>
    </div>

    <script>
        // Auto print upon load
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }

        function sendWhatsApp() {
            // Generate text receipt
            let text = "*BEANPAY CAFE*\n";
            text += "Order No: <?= $order['nomor_pesanan'] ?>\n";
            text += "Tanggal: <?= date('d/m/Y H:i', strtotime($order['waktu_bayar'])) ?>\n";
            text += "Pelanggan: <?= $order['nama_pelanggan'] ?: 'Guest' ?>\n";
            text += "-----------------------------------\n";
            
            <?php foreach($items as $item): ?>
            text += "<?= $item['qty'] ?>x <?= $item['nama_menu'] ?> - <?= number_format($item['harga_satuan'] * $item['qty'], 0, ',', '.') ?>\n";
            <?php endforeach; ?>
            
            text += "-----------------------------------\n";
            text += "Subtotal: Rp <?= number_format($order['subtotal'] > 0 ? $order['subtotal'] : $order['total_harga'], 0, ',', '.') ?>\n";
            
            <?php if($order['service_nominal'] > 0): ?>
            text += "Service: Rp <?= number_format($order['service_nominal'], 0, ',', '.') ?>\n";
            <?php endif; ?>
            
            <?php if($order['pajak_nominal'] > 0): ?>
            text += "Pajak PB1: Rp <?= number_format($order['pajak_nominal'], 0, ',', '.') ?>\n";
            <?php endif; ?>
            
            text += "*Total Tagihan: Rp <?= number_format($order['total_harga'], 0, ',', '.') ?>*\n";
            text += "-----------------------------------\n";
            text += "Metode Bayar: <?= strtoupper($order['metode_pembayaran']) ?> (Rp <?= number_format($order['jumlah_bayar'], 0, ',', '.') ?>)\n";
            text += "Kembalian: Rp <?= number_format($order['kembalian'], 0, ',', '.') ?>\n\n";
            text += "Terima kasih atas kunjungan Anda! 🙏";
            
            let url = "https://wa.me/?text=" + encodeURIComponent(text);
            window.open(url, "_blank");
        }
    </script>
</body>
</html>

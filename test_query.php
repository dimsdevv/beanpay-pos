<?php
require 'config/database.php';
$q = "
    SELECT 
        COUNT(DISTINCT CASE WHEN p.status_pesanan = 'selesai' AND DATE(p.waktu_pesan) = CURDATE() THEN p.id END) AS selesai_hari_ini,
        COUNT(DISTINCT CASE WHEN p.status_pesanan IN ('pending','diproses') THEN p.id END) AS dalam_antrian,
        ROUND(AVG(CASE WHEN dp.waktu_selesai_masak IS NOT NULL 
            THEN TIMESTAMPDIFF(SECOND, dp.waktu_mulai_masak, dp.waktu_selesai_masak) END) / 60, 1) AS avg_menit,
        COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(MINUTE, p.waktu_pesan, NOW()) >= 12 
            AND p.status_pesanan IN ('pending','diproses') THEN p.id END) AS overdue_count
    FROM pesanan p
    LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
    WHERE DATE(p.waktu_pesan) = CURDATE() OR p.status_pesanan IN ('pending','diproses')
";
$stmt = $pdo->query($q);
var_dump($stmt->fetch(PDO::FETCH_ASSOC));

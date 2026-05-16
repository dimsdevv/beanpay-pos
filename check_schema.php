<?php
require_once __DIR__ . '/config/database.php';
$tables = ['pesanan', 'detail_pesanan', 'bahan_baku', 'resep'];
foreach($tables as $t) {
    echo "TABLE: $t\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM $t");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}

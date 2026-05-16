<?php
require_once __DIR__ . '/config/database.php';
$cols = $pdo->query('DESCRIBE menu')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . ($c['Default'] ?? 'NULL') . PHP_EOL;
echo "---\n";
$menus = $pdo->query('SELECT id, nama_menu, gambar, status FROM menu WHERE is_active = 1 LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach($menus as $m) echo json_encode($m) . PHP_EOL;

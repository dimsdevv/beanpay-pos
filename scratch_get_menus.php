<?php
require 'c:/xampp/htdocs/BeanPay/config/database.php';
$stmt = $pdo->query('SELECT id, nama_menu FROM menu');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

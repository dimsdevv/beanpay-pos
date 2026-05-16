<?php
require 'c:/xampp/htdocs/BeanPay/config/database.php';

try {
    // 1. Bersihkan tabel bahan baku & resep
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE resep_menu;");
    $pdo->exec("TRUNCATE TABLE bahan_baku;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->beginTransaction();

    // 2. Insert Bahan Baku
    $bahan = [
        ['Biji Kopi Arabica', 'Gram', 5000],
        ['Susu Full Cream', 'ml', 10000],
        ['Bubuk Cokelat', 'Gram', 2000],
        ['Bubuk Matcha', 'Gram', 2000],
        ['Daging Ayam', 'Gram', 5000],
        ['Daging Sapi (Patty/Slice)', 'Gram', 3000],
        ['Beras', 'Gram', 10000],
        ['Telur', 'Pcs', 100],
        ['Kentang Goreng Beku', 'Gram', 5000],
        ['Kue Dasar / Roti', 'Pcs', 50],
        ['Lemon', 'Pcs', 50],
        ['Teh Hitam', 'Gram', 2000],
        ['Jeruk Peras', 'Pcs', 100],
        ['Pasta (Spaghetti/Fettuccine)', 'Gram', 3000],
        ['Ikan Dori', 'Gram', 3000],
    ];

    $stmtInsertBahan = $pdo->prepare("INSERT INTO bahan_baku (nama_bahan, satuan, stok_sekarang) VALUES (?, ?, ?)");
    foreach ($bahan as $b) {
        $stmtInsertBahan->execute($b);
    }

    // Ambil ID Bahan Baku
    $bahanIds = [];
    $stmtGetBahan = $pdo->query("SELECT id, nama_bahan FROM bahan_baku");
    while ($row = $stmtGetBahan->fetch(PDO::FETCH_ASSOC)) {
        $bahanIds[$row['nama_bahan']] = $row['id'];
    }

    // 3. Mapping Resep per Menu
    $stmtMenu = $pdo->query("SELECT id, nama_menu FROM menu");
    $menus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsertResep = $pdo->prepare("INSERT INTO resep_menu (menu_id, bahan_id, jumlah_dibutuhkan) VALUES (?, ?, ?)");

    foreach ($menus as $m) {
        $mId = $m['id'];
        $nama = strtolower($m['nama_menu']);

        if (strpos($nama, 'americano') !== false || strpos($nama, 'espresso') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Biji Kopi Arabica'], 18]);
        } 
        elseif (strpos($nama, 'cappuccino') !== false || strpos($nama, 'latte') !== false && strpos($nama, 'matcha') === false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Biji Kopi Arabica'], 18]);
            $stmtInsertResep->execute([$mId, $bahanIds['Susu Full Cream'], 150]);
        } 
        elseif (strpos($nama, 'mocha') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Biji Kopi Arabica'], 18]);
            $stmtInsertResep->execute([$mId, $bahanIds['Susu Full Cream'], 100]);
            $stmtInsertResep->execute([$mId, $bahanIds['Bubuk Cokelat'], 20]);
        } 
        elseif (strpos($nama, 'matcha') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Bubuk Matcha'], 25]);
            $stmtInsertResep->execute([$mId, $bahanIds['Susu Full Cream'], 150]);
        } 
        elseif (strpos($nama, 'chocolate') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Bubuk Cokelat'], 30]);
            $stmtInsertResep->execute([$mId, $bahanIds['Susu Full Cream'], 200]);
        } 
        elseif (strpos($nama, 'lemon tea') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Teh Hitam'], 15]);
            $stmtInsertResep->execute([$mId, $bahanIds['Lemon'], 1]);
        } 
        elseif (strpos($nama, 'orange') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Jeruk Peras'], 3]);
        } 
        elseif (strpos($nama, 'nasi goreng') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Beras'], 200]);
            $stmtInsertResep->execute([$mId, $bahanIds['Telur'], 1]);
            $stmtInsertResep->execute([$mId, $bahanIds['Daging Ayam'], 80]);
        } 
        elseif (strpos($nama, 'burger') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Daging Sapi (Patty/Slice)'], 150]);
            $stmtInsertResep->execute([$mId, $bahanIds['Kue Dasar / Roti'], 1]); // Roti Bun
        } 
        elseif (strpos($nama, 'chicken') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Daging Ayam'], 250]);
        } 
        elseif (strpos($nama, 'pasta') !== false || strpos($nama, 'aglio') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Pasta (Spaghetti/Fettuccine)'], 150]);
        } 
        elseif (strpos($nama, 'fish') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Ikan Dori'], 200]);
            $stmtInsertResep->execute([$mId, $bahanIds['Kentang Goreng Beku'], 100]);
        } 
        elseif (strpos($nama, 'fries') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Kentang Goreng Beku'], 200]);
        } 
        elseif (strpos($nama, 'onion') !== false) {
            // Biarkan kosong / tidak dipotong otomatis
        } 
        elseif (strpos($nama, 'cake') !== false || strpos($nama, 'brownies') !== false || strpos($nama, 'pancake') !== false) {
            $stmtInsertResep->execute([$mId, $bahanIds['Kue Dasar / Roti'], 1]);
        }
    }

    $pdo->commit();
    echo "SEEDING_SUCCESS\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}

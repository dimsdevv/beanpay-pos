SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `bahan_baku`;
CREATE TABLE `bahan_baku` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bahan` varchar(100) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok_sekarang` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `bahan_baku` WRITE;
INSERT INTO `bahan_baku` VALUES (1,'Beras','Kg',10000.00,51.00),(2,'Biji Kopi Robusta','Gram',200000.00,400.00);
UNLOCK TABLES;


DROP TABLE IF EXISTS `detail_pesanan`;
CREATE TABLE `detail_pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pesanan_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `harga_satuan` decimal(10,2) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `status_item` enum('pending','cooking','ready') NOT NULL DEFAULT 'pending',
  `waktu_mulai_masak` datetime DEFAULT NULL,
  `waktu_selesai_masak` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pesanan_id` (`pesanan_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `detail_pesanan` WRITE;
INSERT INTO `detail_pesanan` VALUES (1,1,4,1,18000.00,'','ready','2026-05-15 00:49:01','2026-05-15 00:49:02'),(2,2,19,1,22000.00,'','ready','2026-05-15 19:19:48','2026-05-15 19:19:52'),(3,3,20,2,25000.00,'','ready','2026-05-15 19:20:56','2026-05-15 19:20:57'),(4,4,18,3,30000.00,'','ready','2026-05-16 08:11:04','2026-05-16 08:11:06'),(5,5,20,10,25000.00,'','ready','2026-05-16 08:13:47','2026-05-16 08:13:49'),(6,6,20,1,25000.00,'agak manis dikit','ready','2026-05-16 11:15:20','2026-05-16 11:15:24'),(7,7,1,1,22000.00,'kasih gula dikit','ready','2026-05-16 11:15:26','2026-05-16 11:15:28'),(8,8,3,2,28000.00,'gula 10%','ready','2026-05-16 11:20:15','2026-05-16 11:20:22'),(9,9,11,1,45000.00,'','ready','2026-05-16 11:20:24','2026-05-16 11:20:37'),(10,10,20,1,25000.00,'','ready','2026-05-16 11:20:34','2026-05-16 11:20:39'),(11,11,19,1,22000.00,'','ready','2026-05-16 11:25:08','2026-05-16 11:25:09'),(12,12,19,1,22000.00,'','ready','2026-05-16 11:30:11','2026-05-16 11:30:12'),(13,13,18,1,30000.00,'','ready','2026-05-16 11:47:09','2026-05-16 11:47:10');
UNLOCK TABLES;


DROP TABLE IF EXISTS `kategori`;
CREATE TABLE `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `stasiun` enum('bar','kitchen','cold','any') NOT NULL DEFAULT 'any',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `kategori` WRITE;
INSERT INTO `kategori` VALUES (1,'Kopi','2026-05-12 23:05:42','bar'),(2,'Non-Kopi','2026-05-12 23:05:42','bar'),(3,'Makanan Berat','2026-05-12 23:05:42','kitchen'),(4,'Snack','2026-05-12 23:05:42','cold'),(5,'Dessert','2026-05-12 23:05:42','cold');
UNLOCK TABLES;


DROP TABLE IF EXISTS `meja`;
CREATE TABLE `meja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_meja` varchar(10) NOT NULL,
  `status` enum('kosong','terisi') NOT NULL DEFAULT 'kosong',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_meja` (`nomor_meja`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `meja` WRITE;
INSERT INTO `meja` VALUES (1,'T-01','kosong'),(2,'T-02','kosong'),(3,'T-03','kosong'),(4,'T-04','kosong'),(5,'T-05','kosong'),(6,'T-06','kosong'),(7,'T-07','kosong'),(8,'T-08','kosong'),(9,'T-09','kosong'),(10,'T-10','kosong');
UNLOCK TABLES;


DROP TABLE IF EXISTS `menu`;
CREATE TABLE `menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `nama_menu` varchar(100) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `status` enum('tersedia','habis') NOT NULL DEFAULT 'tersedia',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  CONSTRAINT `menu_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `menu` WRITE;
INSERT INTO `menu` VALUES (1,1,'Americano',22000.00,'menu_1778907934_1984.jfif','tersedia','2026-05-12 23:05:42','2026-05-16 12:05:34',1),(2,1,'Cappuccino',28000.00,'menu_1778908037_2122.jfif','tersedia','2026-05-12 23:05:42','2026-05-16 12:07:17',1),(3,1,'Caffe Latte',28000.00,'menu_1778907998_7856.webp','tersedia','2026-05-12 23:05:42','2026-05-16 12:06:38',1),(4,1,'Espresso',18000.00,'menu_1778908102_6104.jfif','tersedia','2026-05-12 23:05:42','2026-05-16 12:08:22',1),(5,1,'Mocha',32000.00,'menu_1778908218_6238.jfif','tersedia','2026-05-12 23:05:42','2026-05-16 12:10:18',1),(6,2,'Matcha Latte',30000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(7,2,'Chocolate',25000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(8,2,'Lemon Tea',18000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(9,2,'Fresh Orange Juice',22000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(10,3,'Nasi Goreng Spesial',35000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(11,3,'Chicken Steak',45000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(12,3,'Beef Burger',42000.00,'menu_1778908263_9832.jfif','tersedia','2026-05-12 23:05:42','2026-05-16 12:11:03',1),(13,3,'Pasta Aglio Olio',38000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(14,3,'Fish & Chips',40000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(15,4,'French Fries',20000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(16,4,'Chicken Wings',28000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(17,4,'Onion Rings',18000.00,NULL,'tersedia','2026-05-12 23:05:42','2026-05-12 23:05:42',1),(18,5,'Cheesecake',30000.00,'menu_1778603852_9383.jfif','tersedia','2026-05-12 23:05:42','2026-05-12 23:37:32',1),(19,5,'Brownies',22000.00,'menu_1778603745_7395.jfif','tersedia','2026-05-12 23:05:42','2026-05-12 23:35:45',1),(20,5,'Pancake',25000.00,'menu_1778603922_9134.jfif','tersedia','2026-05-12 23:05:42','2026-05-12 23:38:42',1);
UNLOCK TABLES;


DROP TABLE IF EXISTS `notif_waiter`;
CREATE TABLE `notif_waiter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pesanan_id` int(11) NOT NULL,
  `waiter_id` int(11) NOT NULL,
  `pesan` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pesanan_id` (`pesanan_id`),
  KEY `waiter_id` (`waiter_id`),
  CONSTRAINT `notif_waiter_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notif_waiter_ibfk_2` FOREIGN KEY (`waiter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `notif_waiter` WRITE;
INSERT INTO `notif_waiter` VALUES (1,6,3,'Pesanan ORD-20260516-003 siap dihidangkan!',0,'2026-05-16 11:15:24'),(2,7,3,'Pesanan ORD-20260516-004 siap dihidangkan!',0,'2026-05-16 11:15:28'),(5,9,3,'Pesanan ORD-20260516-006 siap dihidangkan!',0,'2026-05-16 11:20:37'),(6,10,3,'Pesanan ORD-20260516-007 siap dihidangkan!',0,'2026-05-16 11:20:39'),(7,11,3,'Pesanan ORD-20260516-008 siap dihidangkan!',0,'2026-05-16 11:25:09'),(8,12,3,'Pesanan ORD-20260516-009 siap dihidangkan!',0,'2026-05-16 11:30:12'),(9,13,3,'Pesanan ORD-20260516-010 siap dihidangkan!',0,'2026-05-16 11:47:10');
UNLOCK TABLES;


DROP TABLE IF EXISTS `pembayaran`;
CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pesanan_id` int(11) NOT NULL,
  `sesi_kasir_id` int(11) NOT NULL,
  `metode_pembayaran` enum('cash','qris','debit') NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `kembalian` decimal(10,2) NOT NULL DEFAULT 0.00,
  `waktu_bayar` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pesanan_id` (`pesanan_id`),
  KEY `sesi_kasir_id` (`sesi_kasir_id`),
  CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`),
  CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`sesi_kasir_id`) REFERENCES `sesi_kasir` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `pembayaran` WRITE;
INSERT INTO `pembayaran` VALUES (1,1,1,'cash',20790.00,0.00,'2026-05-15 00:49:32'),(2,2,2,'cash',25410.00,0.00,'2026-05-15 19:29:52'),(3,3,2,'cash',57750.00,0.00,'2026-05-15 19:30:03'),(4,5,2,'qris',288750.00,0.00,'2026-05-16 08:14:19'),(5,4,3,'cash',103950.00,0.00,'2026-05-16 11:16:01'),(6,6,3,'debit',28875.00,0.00,'2026-05-16 11:16:14'),(7,7,3,'cash',25410.00,0.00,'2026-05-16 11:16:26'),(8,8,3,'cash',64680.00,0.00,'2026-05-16 11:31:33'),(9,12,3,'cash',25410.00,0.00,'2026-05-16 11:31:44'),(10,9,3,'debit',51975.00,0.00,'2026-05-16 11:32:07'),(11,10,3,'cash',30000.00,1125.00,'2026-05-16 11:32:25'),(12,11,3,'cash',25410.00,0.00,'2026-05-16 11:32:37'),(13,13,4,'cash',34650.00,0.00,'2026-05-16 11:47:44');
UNLOCK TABLES;


DROP TABLE IF EXISTS `pengaturan`;
CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kunci` varchar(100) NOT NULL,
  `nilai` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kunci` (`kunci`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `pengaturan` WRITE;
INSERT INTO `pengaturan` VALUES (1,'pajak_persen','10'),(2,'aktifkan_pajak','1'),(3,'service_charge_persen','5'),(4,'aktifkan_service','1');
UNLOCK TABLES;


DROP TABLE IF EXISTS `pesanan`;
CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_pesanan` varchar(20) NOT NULL,
  `tipe_pesanan` enum('dine_in','take_away') NOT NULL,
  `meja_id` int(11) DEFAULT NULL,
  `nama_pelanggan` varchar(100) DEFAULT NULL,
  `waiter_id` int(11) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status_pesanan` enum('pending','diproses','selesai','dibayar','dibatalkan') NOT NULL DEFAULT 'pending',
  `waktu_pesan` datetime NOT NULL DEFAULT current_timestamp(),
  `service_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `service_nominal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pajak_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `pajak_nominal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promo_id` int(11) DEFAULT NULL,
  `diskon_nominal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_pesanan` (`nomor_pesanan`),
  KEY `meja_id` (`meja_id`),
  KEY `waiter_id` (`waiter_id`),
  CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`meja_id`) REFERENCES `meja` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`waiter_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `pesanan` WRITE;
INSERT INTO `pesanan` VALUES (1,'ORD-20260514-001','take_away',NULL,'',3,20790.00,18000.00,'dibayar','2026-05-15 00:46:29',5.00,900.00,10.00,1890.00,NULL,0.00),(2,'ORD-20260515-002','take_away',NULL,'',3,25410.00,22000.00,'dibayar','2026-05-15 19:19:32',5.00,1100.00,10.00,2310.00,NULL,0.00),(3,'ORD-20260515-003','dine_in',1,'',3,57750.00,50000.00,'dibayar','2026-05-15 19:20:41',5.00,2500.00,10.00,5250.00,NULL,0.00),(4,'ORD-20260516-001','take_away',NULL,'',3,103950.00,90000.00,'dibayar','2026-05-16 08:10:44',5.00,4500.00,10.00,9450.00,NULL,0.00),(5,'ORD-20260516-002','dine_in',4,'',3,288750.00,250000.00,'dibayar','2026-05-16 08:12:10',5.00,12500.00,10.00,26250.00,NULL,0.00),(6,'ORD-20260516-003','dine_in',1,'',3,28875.00,25000.00,'dibayar','2026-05-16 11:14:25',5.00,1250.00,10.00,2625.00,NULL,0.00),(7,'ORD-20260516-004','take_away',NULL,'dimas',3,25410.00,22000.00,'dibayar','2026-05-16 11:14:49',5.00,1100.00,10.00,2310.00,NULL,0.00),(8,'ORD-20260516-005','take_away',NULL,'Sandi',3,64680.00,56000.00,'dibayar','2026-05-16 11:19:10',5.00,2800.00,10.00,5880.00,NULL,0.00),(9,'ORD-20260516-006','dine_in',1,'',3,51975.00,45000.00,'dibayar','2026-05-16 11:19:25',5.00,2250.00,10.00,4725.00,NULL,0.00),(10,'ORD-20260516-007','take_away',NULL,'Apeng',3,28875.00,25000.00,'dibayar','2026-05-16 11:19:38',5.00,1250.00,10.00,2625.00,NULL,0.00),(11,'ORD-20260516-008','dine_in',2,'',3,25410.00,22000.00,'dibayar','2026-05-16 11:24:36',5.00,1100.00,10.00,2310.00,NULL,0.00),(12,'ORD-20260516-009','take_away',NULL,'',3,25410.00,22000.00,'dibayar','2026-05-16 11:29:54',5.00,1100.00,10.00,2310.00,NULL,0.00),(13,'ORD-20260516-010','dine_in',3,'',3,34650.00,30000.00,'dibayar','2026-05-16 11:46:54',5.00,1500.00,10.00,3150.00,NULL,0.00);
UNLOCK TABLES;


DROP TABLE IF EXISTS `promo`;
CREATE TABLE `promo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_promo` varchar(50) NOT NULL,
  `tipe_diskon` enum('persen','nominal') NOT NULL DEFAULT 'persen',
  `nilai_diskon` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_belanja` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kuota` int(11) NOT NULL DEFAULT 100,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_promo` (`kode_promo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `promo` WRITE;
UNLOCK TABLES;


DROP TABLE IF EXISTS `resep_menu`;
CREATE TABLE `resep_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `jumlah_dibutuhkan` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `menu_id` (`menu_id`),
  KEY `bahan_id` (`bahan_id`),
  CONSTRAINT `resep_menu_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resep_menu_ibfk_2` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `resep_menu` WRITE;
INSERT INTO `resep_menu` VALUES (1,19,1,1.00),(3,1,1,1.00);
UNLOCK TABLES;


DROP TABLE IF EXISTS `sesi_kasir`;
CREATE TABLE `sesi_kasir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kasir_id` int(11) NOT NULL,
  `waktu_buka` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_tutup` datetime DEFAULT NULL,
  `modal_awal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_pemasukan` decimal(10,2) NOT NULL DEFAULT 0.00,
  `uang_fisik` decimal(15,2) DEFAULT NULL,
  `selisih_kas` decimal(15,2) DEFAULT NULL,
  `status` enum('buka','tutup') NOT NULL DEFAULT 'buka',
  PRIMARY KEY (`id`),
  KEY `kasir_id` (`kasir_id`),
  CONSTRAINT `sesi_kasir_ibfk_1` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `sesi_kasir` WRITE;
INSERT INTO `sesi_kasir` VALUES (1,2,'2026-05-15 00:49:23','2026-05-15 00:52:19',123000.00,20790.00,NULL,NULL,'tutup'),(2,2,'2026-05-15 19:20:11','2026-05-16 08:15:42',10000.00,371910.00,NULL,NULL,'tutup'),(3,2,'2026-05-16 11:15:53','2026-05-16 11:32:51',100000.00,355710.00,NULL,NULL,'tutup'),(4,2,'2026-05-16 11:47:35','2026-05-16 11:48:04',10000.00,34650.00,50000.00,5350.00,'tutup'),(5,2,'2026-05-16 11:50:10',NULL,100000.00,0.00,NULL,NULL,'buka');
UNLOCK TABLES;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','kasir') NOT NULL DEFAULT 'kasir',
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `users` WRITE;
INSERT INTO `users` VALUES (1,'admin','$2y$10$kyCLVMdDp6RRD7/7KplBcemsqONL8C0OHGRWKpGTxe3pMVyWQNga6','Administrator','admin','aktif','2026-05-12 23:05:42','2026-05-12 23:05:42'),(2,'kasir1','$2y$10$kyCLVMdDp6RRD7/7KplBcemsqONL8C0OHGRWKpGTxe3pMVyWQNga6','Siti Kasir','kasir','aktif','2026-05-12 23:05:42','2026-05-12 23:05:42');
UNLOCK TABLES;

SET FOREIGN_KEY_CHECKS=1;

-- =============================================
-- Checkpoint POS - Sistem Kasir Cafe & Restaurant
-- Database Schema & Seed Data
-- =============================================

CREATE DATABASE IF NOT EXISTS beanpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE beanpay;

-- ----- TABEL USERS -----
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----- TABEL KATEGORI -----
CREATE TABLE IF NOT EXISTS kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----- TABEL MENU -----
CREATE TABLE IF NOT EXISTS menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT NOT NULL,
    nama_menu VARCHAR(100) NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    gambar VARCHAR(255) DEFAULT NULL,
    status ENUM('tersedia', 'habis') NOT NULL DEFAULT 'tersedia',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----- TABEL MEJA -----
CREATE TABLE IF NOT EXISTS meja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_meja VARCHAR(10) NOT NULL UNIQUE,
    status ENUM('kosong', 'terisi') NOT NULL DEFAULT 'kosong'
) ENGINE=InnoDB;

-- ----- TABEL SESI KASIR -----
CREATE TABLE IF NOT EXISTS sesi_kasir (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kasir_id INT NOT NULL,
    waktu_buka DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_tutup DATETIME DEFAULT NULL,
    modal_awal DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_pemasukan DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('buka', 'tutup') NOT NULL DEFAULT 'buka',
    FOREIGN KEY (kasir_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----- TABEL PESANAN -----
CREATE TABLE IF NOT EXISTS pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_pesanan VARCHAR(20) NOT NULL UNIQUE,
    tipe_pesanan ENUM('dine_in', 'take_away') NOT NULL,
    meja_id INT DEFAULT NULL,
    nama_pelanggan VARCHAR(100) DEFAULT NULL,
    waiter_id INT NOT NULL,
    total_harga DECIMAL(10,2) NOT NULL DEFAULT 0,
    status_pesanan ENUM('pending', 'diproses', 'selesai', 'dibayar', 'dibatalkan') NOT NULL DEFAULT 'pending',
    waktu_pesan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meja_id) REFERENCES meja(id) ON DELETE SET NULL,
    FOREIGN KEY (waiter_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----- TABEL DETAIL PESANAN -----
CREATE TABLE IF NOT EXISTS detail_pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id INT NOT NULL,
    menu_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(10,2) NOT NULL,
    catatan VARCHAR(255) DEFAULT NULL,
    status_item ENUM('pending', 'cooking', 'ready') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----- TABEL PEMBAYARAN -----
CREATE TABLE IF NOT EXISTS pembayaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id INT NOT NULL,
    sesi_kasir_id INT NOT NULL,
    metode_pembayaran ENUM('cash', 'qris', 'debit') NOT NULL,
    jumlah_bayar DECIMAL(10,2) NOT NULL,
    kembalian DECIMAL(10,2) NOT NULL DEFAULT 0,
    waktu_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
    FOREIGN KEY (sesi_kasir_id) REFERENCES sesi_kasir(id) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =============================================
-- SEED DATA
-- =============================================

-- Admin default: username=admin, password=admin123
INSERT INTO users (username, password, nama_lengkap, role, status) VALUES
('admin', '$2y$10$kyCLVMdDp6RRD7/7KplBcemsqONL8C0OHGRWKpGTxe3pMVyWQNga6', 'Administrator', 'admin', 'aktif'),
('kasir1', '$2y$10$kyCLVMdDp6RRD7/7KplBcemsqONL8C0OHGRWKpGTxe3pMVyWQNga6', 'Siti Kasir', 'kasir', 'aktif');

-- Kategori
INSERT INTO kategori (nama_kategori) VALUES
('Kopi'),
('Non-Kopi'),
('Makanan Berat'),
('Snack'),
('Dessert');

-- Menu
INSERT INTO menu (kategori_id, nama_menu, harga, status) VALUES
(1, 'Americano', 22000, 'tersedia'),
(1, 'Cappuccino', 28000, 'tersedia'),
(1, 'Caffe Latte', 28000, 'tersedia'),
(1, 'Espresso', 18000, 'tersedia'),
(1, 'Mocha', 32000, 'tersedia'),
(2, 'Matcha Latte', 30000, 'tersedia'),
(2, 'Chocolate', 25000, 'tersedia'),
(2, 'Lemon Tea', 18000, 'tersedia'),
(2, 'Fresh Orange Juice', 22000, 'tersedia'),
(3, 'Nasi Goreng Spesial', 35000, 'tersedia'),
(3, 'Chicken Steak', 45000, 'tersedia'),
(3, 'Beef Burger', 42000, 'tersedia'),
(3, 'Pasta Aglio Olio', 38000, 'tersedia'),
(3, 'Fish & Chips', 40000, 'tersedia'),
(4, 'French Fries', 20000, 'tersedia'),
(4, 'Chicken Wings', 28000, 'tersedia'),
(4, 'Onion Rings', 18000, 'tersedia'),
(5, 'Cheesecake', 30000, 'tersedia'),
(5, 'Brownies', 22000, 'tersedia'),
(5, 'Pancake', 25000, 'tersedia');

-- Meja
INSERT INTO meja (nomor_meja, status) VALUES
('T-01', 'kosong'),
('T-02', 'kosong'),
('T-03', 'kosong'),
('T-04', 'kosong'),
('T-05', 'kosong'),
('T-06', 'kosong'),
('T-07', 'kosong'),
('T-08', 'kosong'),
('T-09', 'kosong'),
('T-10', 'kosong');

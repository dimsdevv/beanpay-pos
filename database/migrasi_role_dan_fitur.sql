-- =============================================
-- BeanPay Migration: Role Cleanup & Feature Fixes
-- =============================================
-- Menghapus role waiter & dapur, memperbaiki state machine
-- Jalankan: http://localhost/BeanPay/database/migrasi_role_dan_fitur.php
-- =============================================

-- 1. Ubah user waiter/dapur jadi kasir dulu
UPDATE users SET role = 'kasir' WHERE role IN ('waiter', 'dapur');

-- 2. Ubah ENUM kolom role (MySQL perlu MODIFY COLUMN)
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir';

-- 3. Hapus tabel notifikasi waiter (tidak dipakai lagi)
DROP TABLE IF EXISTS notif_waiter;

-- 4. Tambah kolom stock_deduction_done jika belum ada
ALTER TABLE pesanan ADD COLUMN stock_deduction_done TINYINT(1) NOT NULL DEFAULT 0 AFTER diskon_nominal;

-- ============================================================
--  Checkpoint POS — Migrasi Administrasi Keuangan
--  Pengeluaran bahan, item pengeluaran, dan anggaran bulanan
-- ============================================================

CREATE TABLE IF NOT EXISTS pengeluaran (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tanggal        DATE NOT NULL,
    supplier       VARCHAR(120) DEFAULT NULL,
    kategori       ENUM('pembukaan','operasional','lainnya') NOT NULL DEFAULT 'operasional',
    keterangan     TEXT,
    metode_bayar   ENUM('cash','qris','transfer') NOT NULL DEFAULT 'cash',
    bukti          VARCHAR(255) DEFAULT NULL,
    total          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    stok_updated   TINYINT(1) NOT NULL DEFAULT 0,
    input_by       INT DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tanggal  (tanggal),
    INDEX idx_kategori (kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pengeluaran_item (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pengeluaran_id  INT NOT NULL,
    bahan_id        INT DEFAULT NULL,
    nama_bahan      VARCHAR(120) NOT NULL,
    qty             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    satuan          VARCHAR(50) DEFAULT '',
    harga_satuan    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (pengeluaran_id) REFERENCES pengeluaran(id) ON DELETE CASCADE,
    INDEX idx_bahan (bahan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS anggaran_bulan (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    periode  CHAR(7) NOT NULL UNIQUE COMMENT 'Format YYYY-MM',
    nominal  DECIMAL(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

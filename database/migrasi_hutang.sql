-- ============================================================
--  Checkpoint POS — Migrasi Fitur Hutang & Piutang
--  Tabel pelanggan dan hutang
-- ============================================================

-- Tabel Pelanggan
CREATE TABLE IF NOT EXISTS pelanggan (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    telepon       VARCHAR(20) DEFAULT NULL,
    catatan       TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nama (nama_lengkap)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel Hutang (Piutang dari perspektif toko)
CREATE TABLE IF NOT EXISTS hutang (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pelanggan_id    INT NOT NULL,
    kasir_id        INT NOT NULL,
    rincian         TEXT NOT NULL,
    nominal         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status          ENUM('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
    metode_bayar    ENUM('cash','qris','transfer') DEFAULT NULL,
    bukti_transfer  VARCHAR(255) DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    lunas_at        DATETIME DEFAULT NULL,
    INDEX idx_pelanggan (pelanggan_id),
    INDEX idx_status (status),
    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
    FOREIGN KEY (kasir_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
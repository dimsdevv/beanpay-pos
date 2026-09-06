<?php
/**
 * Checkpoint POS - Database Configuration
 * Koneksi PDO ke MySQL
 */

// ==========================================
// SUSPENSION LOCK (UNPAID INVOICE)
// Hapus atau comment kode die() di bawah ini untuk menyalakan sistem kembali.
// ==========================================
die("
<div style='display:flex; justify-content:center; items-align:center; height:100vh; background-color:#f8f9fa; font-family:sans-serif;'>
    <div style='text-align:center; margin-top:15%; padding:40px; background:white; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); border-top:5px solid #dc3545; max-height: 250px;'>
        <h1 style='color:#343a40; margin-bottom:10px;'>SISTEM DITANGGUHKAN SEMENTARA</h1>
        <p style='color:#6c757d; font-size:18px;'>Layanan ini dibekukan karena masalah administrasi (Unpaid Invoice).</p>
        <p style='color:#6c757d; font-size:14px; margin-top:20px;'>Silakan hubungi Developer / Pihak IT untuk menyelesaikan pelunasan dan memulihkan akses.</p>
    </div>
</div>
");
// ==========================================

// === PRODUCTION (AlwaysData) ===
// define('DB_HOST', 'mysql-dimsdevv.alwaysdata.net');
// define('DB_NAME', 'dimsdevv_beanpay');
// define('DB_USER', 'dimsdevv');

// === DATABASE CONFIG ===
if (file_exists(__DIR__ . '/database_production.php')) {
    // Load production credentials in Hostinger
    require_once __DIR__ . '/database_production.php';
} else {
    // Localhost fallback
    define('DB_HOST', getenv('BEANPAY_DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('BEANPAY_DB_NAME') ?: 'beanpay');
    define('DB_USER', getenv('BEANPAY_DB_USER') ?: 'root');
    define('DB_PASS', getenv('BEANPAY_DB_PASS') ?: '');
    define('DB_CHARSET', getenv('BEANPAY_DB_CHARSET') ?: 'utf8mb4');
}

// Base URL (Dynamic)
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    define('BASE_URL', '/BeanPay');
} else {
    // Untuk production server (seperti InfinityFree), biasanya di root document
    define('BASE_URL', '');
}

// Upload path
define('UPLOAD_PATH', __DIR__ . '/../assets/images/');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Koneksi database gagal. Error: ' . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

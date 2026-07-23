<?php
/**
 * Checkpoint POS - Database Configuration
 * Koneksi PDO ke MySQL
 */

// === PRODUCTION (AlwaysData) ===
// define('DB_HOST', 'mysql-dimsdevv.alwaysdata.net');
// define('DB_NAME', 'dimsdevv_beanpay');
// define('DB_USER', 'dimsdevv');

// === DATABASE CONFIG ===
define('DB_HOST', getenv('BEANPAY_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('BEANPAY_DB_NAME') ?: 'beanpay');
define('DB_USER', getenv('BEANPAY_DB_USER') ?: 'root');
define('DB_PASS', getenv('BEANPAY_DB_PASS') ?: '');
define('DB_CHARSET', getenv('BEANPAY_DB_CHARSET') ?: 'utf8mb4');

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
    die('Koneksi database gagal. Hubungi administrator.');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

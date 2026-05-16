<?php
/**
 * BeanPay - Database Configuration
 * Koneksi PDO ke MySQL
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'beanpay');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

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
    die("Koneksi database gagal: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

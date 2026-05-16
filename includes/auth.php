<?php
/**
 * BeanPay - Authentication & Authorization Helper
 * Cek login, role, dan redirect
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Dapatkan data user yang sedang login
 */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role'         => $_SESSION['role'],
    ];
}

/**
 * Redirect jika belum login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Redirect jika role tidak sesuai
 * @param array $allowedRoles - Array of allowed roles
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect ke halaman sesuai role masing-masing
        redirectByRole();
        exit;
    }
}

/**
 * Redirect user ke halaman utama berdasarkan role
 */
function redirectByRole(): void {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
            break;
        case 'kasir':
            header('Location: ' . BASE_URL . '/modules/kasir/index.php');
            break;
        case 'waiter':
            header('Location: ' . BASE_URL . '/modules/waiter/order.php');
            break;
        case 'dapur':
            header('Location: ' . BASE_URL . '/modules/dapur/index.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            break;
    }
    exit;
}

/**
 * Generate CSRF Token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF Token
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

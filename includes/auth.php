<?php
/**
 * Checkpoint POS - Authentication & Authorization Helper
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
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
    } elseif ($role === 'kasir') {
        header('Location: ' . BASE_URL . '/modules/kasir/index.php');
    } else {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
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

function requireCsrfToken(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Buat tabel audit_trail jika belum ada
 */
function ensureAuditTable(): void {
    global $pdo;
    try {
        $pdo->query("SELECT 1 FROM audit_trail LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_trail (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_created_at (created_at)
            )
        ");
    }
}

function logAuditAction(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void {
    global $pdo;
    try {
        ensureAuditTable();
        $stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Exception $e) {
        error_log('Audit trail failed: ' . $e->getMessage());
    }
}

function handleMenuImageUpload(?array $file): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar gagal. Coba unggah ulang.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar terlalu besar, maksimal 2MB saja.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || !getimagesize($file['tmp_name'])) {
        throw new RuntimeException('Format gambar harus jpg, png, webp, atau gif ya.');
    }

    $uploadDir = __DIR__ . '/../assets/images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'menu_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('Upload gambar gagal disimpan.');
    }

    return $filename;
}

function deleteMenuImage(?string $filename): void {
    if (!$filename) return;

    $uploadDir = realpath(__DIR__ . '/../assets/images/');
    if (!$uploadDir) return;

    $path = realpath($uploadDir . DIRECTORY_SEPARATOR . basename($filename));
    if ($path && strpos($path, $uploadDir . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        unlink($path);
    }
}

<?php
$page_title = 'Kelola User';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Helper: Hitung jumlah admin aktif
function countActiveAdmins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn();
}

requireRole(['admin']);
requireCsrfToken();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username     = trim($_POST['username']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $role         = $_POST['role'];
        $password     = $_POST['password'];
        $hashed       = password_hash($password, PASSWORD_BCRYPT);

        // Cek username unik
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Username \"$username\" sudah digunakan.";
        } else {
            $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?,?,?,?,'aktif')")
                ->execute([$username, $hashed, $nama_lengkap, $role]);
            $_SESSION['success'] = "User \"$nama_lengkap\" berhasil ditambahkan.";
        }
    }

    if ($action === 'edit_user') {
        $id           = (int)$_POST['id'];
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $username     = trim($_POST['username']);
        $role         = $_POST['role'];
        $status       = $_POST['status'];

        // --- SMART LOGIC: Proteksi diri sendiri ---
        if ($id == $_SESSION['user_id']) {
            // Ambil data lama
            $stmtOld = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldData = $stmtOld->fetch();

            if ($oldData['role'] === 'admin' && $role !== 'admin') {
                $_SESSION['error'] = "Anda tidak bisa mengubah role akun Anda sendiri dari Admin.";
                header('Location: users.php'); exit;
            }
            if ($status !== 'aktif') {
                $_SESSION['error'] = "Anda tidak bisa menonaktifkan akun Anda sendiri.";
                header('Location: users.php'); exit;
            }
        }

        // --- SMART LOGIC: Proteksi admin terakhir ---
        $stmtOld2 = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmtOld2->execute([$id]);
        $oldData2 = $stmtOld2->fetch();
        
        if ($oldData2['role'] === 'admin' && $oldData2['status'] === 'aktif') {
            // User ini sebelumnya admin aktif, cek apakah perubahan akan menghilangkan admin
            $willLoseAdmin = ($role !== 'admin' || $status !== 'aktif');
            if ($willLoseAdmin && countActiveAdmins($pdo) <= 1) {
                $_SESSION['error'] = "Tidak bisa mengubah. User ini adalah satu-satunya Admin aktif di sistem!";
                header('Location: users.php'); exit;
            }
        }

        // Cek username unik (selain diri sendiri)
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Username \"$username\" sudah digunakan oleh user lain.";
            header('Location: users.php'); exit;
        }

        $pdo->prepare("UPDATE users SET username=?, nama_lengkap=?, role=?, status=? WHERE id=?")
            ->execute([$username, $nama_lengkap, $role, $status, $id]);

        // Reset password jika diisi
        if (!empty($_POST['new_password'])) {
            $hashed = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $id]);
        }
        $_SESSION['success'] = "User berhasil diperbarui.";
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];

        // --- SMART LOGIC: Proteksi diri sendiri ---
        if ($id == $_SESSION['user_id']) {
            $_SESSION['error'] = "Anda tidak bisa menonaktifkan akun Anda sendiri.";
            header('Location: users.php'); exit;
        }

        // --- SMART LOGIC: Proteksi admin terakhir ---
        $stmtOld = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch();

        if ($oldData['role'] === 'admin' && $oldData['status'] === 'aktif' && countActiveAdmins($pdo) <= 1) {
            $_SESSION['error'] = "Tidak bisa menonaktifkan. User ini adalah satu-satunya Admin aktif!";
            header('Location: users.php'); exit;
        }

        $pdo->prepare("UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        $_SESSION['success'] = "Status user berhasil diubah.";
    }

    if ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id == $_SESSION['user_id']) {
            $_SESSION['error'] = "Tidak bisa menghapus akun sendiri.";
        } else {
            // --- Cek proteksi admin terakhir ---
            $stmtOld = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldData = $stmtOld->fetch();

            if ($oldData['role'] === 'admin' && $oldData['status'] === 'aktif' && countActiveAdmins($pdo) <= 1) {
                $_SESSION['error'] = "Tidak bisa menghapus. User ini adalah satu-satunya Admin aktif!";
                header('Location: users.php'); exit;
            }

            // --- Cek apakah user punya data transaksi ---
            $stmtPesanan = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE waiter_id = ?");
            $stmtPesanan->execute([$id]);
            $totalPesanan = (int)$stmtPesanan->fetchColumn();

            $stmtSesi = $pdo->prepare("SELECT COUNT(*) FROM sesi_kasir WHERE kasir_id = ?");
            $stmtSesi->execute([$id]);
            $totalSesi = (int)$stmtSesi->fetchColumn();

            if ($totalPesanan > 0 || $totalSesi > 0) {
                $detail = [];
                if ($totalPesanan > 0) $detail[] = "$totalPesanan transaksi";
                if ($totalSesi > 0) $detail[] = "$totalSesi sesi kasir";
                $_SESSION['error'] = "User ini memiliki " . implode(' dan ', $detail) . " dan tidak bisa dihapus secara permanen. Nonaktifkan saja.";
            } else {
                $stmtUser = $pdo->prepare("SELECT username, nama_lengkap FROM users WHERE id = ?");
                $stmtUser->execute([$id]);
                $deletedUser = $stmtUser->fetch();
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                logAuditAction('delete_user', 'user', $id, $deletedUser ? "User: {$deletedUser['username']} ({$deletedUser['nama_lengkap']})" : null);
                $_SESSION['success'] = "User berhasil dihapus permanen.";
            }
        }
    }

    header('Location: users.php'); exit;
}

// Sekarang baru aman load header (output HTML)

require_once __DIR__ . '/../../includes/header.php';
$users = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="usersApp()" class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Kelola Staf</h1>
            <p class="text-vibe-on-surface-variant mt-0.5 text-sm">Kelola akses dan peran untuk semua staf restoran.</p>
        </div>
        <button @click="openAdd()" class="flex items-center gap-2 px-5 py-2.5 bg-vibe-primary text-white rounded-md font-medium hover:bg-vibe-primary-container transition-colors w-full sm:w-auto justify-center text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Pengguna
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-md bg-vibe-secondary-container text-vibe-secondary font-medium flex items-center gap-2 border border-vibe-secondary/20 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-md bg-vibe-error-container text-vibe-error font-medium flex items-center gap-2 border border-vibe-error/20 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats & Filters -->
    <div class="flex items-center gap-2">
        <button @click="filterRole = 'all'" 
                class="px-4 py-2 rounded-md border text-sm font-medium transition-colors"
                :class="filterRole === 'all' ? 'bg-vibe-primary border-vibe-primary text-white' : 'bg-white border-vibe-outline-variant text-vibe-on-surface-variant hover:border-vibe-on-surface'">
            Semua <span class="ml-1 font-bold"><?= count($users) ?></span>
        </button>

        <?php
        $roleCount = ['admin'=>0,'kasir'=>0];
        foreach($users as $u) {
            if (isset($roleCount[$u['role']])) {
                $roleCount[$u['role']]++;
            }
        }
        
        $roleDetails = [
            'admin'  => ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>', 'label' => 'Admin'],
            'kasir'  => ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'label' => 'Kasir']
        ];
        
        foreach($roleDetails as $role => $detail):
            $count = $roleCount[$role];
        ?>
        <button @click="filterRole = '<?= $role ?>'" 
                class="px-4 py-2 rounded-md border text-sm font-medium transition-colors"
                :class="filterRole === '<?= $role ?>' ? 'bg-vibe-primary border-vibe-primary text-white' : 'bg-white border-vibe-outline-variant text-vibe-on-surface-variant hover:border-vibe-on-surface'">
            <?= $detail['label'] ?> <span class="ml-1 font-bold"><?= $count ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Table Section -->
    <div class="bg-white rounded-lg border border-vibe-outline-variant overflow-hidden flex flex-col">
        
        <!-- Controls Bar -->
        <div class="p-5 border-b border-vibe-outline flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="text-sm font-bold text-vibe-on-surface uppercase tracking-wider">Direktori Staf</h2>
            
            <!-- Live Search -->
            <div class="relative w-full md:w-72">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Cari staf..." class="w-full pl-9 pr-4 py-2 bg-white border border-vibe-outline-variant rounded-md focus:outline-none focus:border-vibe-on-surface text-sm transition-colors placeholder-vibe-outline-variant">
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full">
                <thead>
                    <tr class="bg-vibe-surface-dim border-b border-vibe-outline text-[11px] font-semibold text-vibe-on-surface-variant uppercase tracking-widest">
                        <th class="px-5 py-3 text-left">Anggota Staf</th>
                        <th class="px-5 py-3 text-left">Peran</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-vibe-outline">
                    
                    <!-- Empty State (No Results) -->
                    <tr x-show="filteredUsers.length === 0" style="display:none">
                        <td colspan="4" class="px-5 py-16 text-center">
                            <div class="w-16 h-16 bg-vibe-surface-dim border border-vibe-outline-variant rounded flex items-center justify-center mx-auto mb-4 text-vibe-outline-variant">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <h3 class="text-sm font-bold text-vibe-on-surface mb-1">Staf tidak ditemukan</h3>
                            <p class="text-xs text-vibe-on-surface-variant">Coba ubah pencarian atau filter peran Anda.</p>
                            <button @click="searchQuery = ''; filterRole = 'all'" class="mt-4 px-4 py-2 bg-vibe-primary text-white font-medium text-xs rounded-md hover:bg-vibe-primary-container transition-colors">Hapus Filter</button>
                        </td>
                    </tr>

                    <!-- User Rows -->
                    <template x-for="u in filteredUsers" :key="u.id">
                        <tr class="hover:bg-vibe-surface-dim transition-colors group">
                            <!-- Name & Username -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded border border-vibe-outline-variant bg-vibe-surface-dim flex items-center justify-center font-semibold text-xs shrink-0 text-vibe-on-surface">
                                        <span x-text="u.nama_lengkap.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-vibe-on-surface" x-text="u.nama_lengkap"></span>
                                            <span x-show="u.id == currentUserId" class="px-1.5 py-0.5 text-[9px] font-semibold bg-vibe-surface-container text-vibe-on-surface-variant rounded border border-vibe-outline-variant uppercase tracking-widest">Anda</span>
                                        </div>
                                        <div class="text-xs text-vibe-on-surface-variant mt-0.5" x-text="'@' + u.username"></div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Role -->
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-md text-xs font-medium border flex items-center gap-1.5 w-max" :class="getRoleBadge(u.role)">
                                    <span x-html="getRoleIcon(u.role)"></span>
                                    <span x-text="u.role.charAt(0).toUpperCase() + u.role.slice(1)"></span>
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-2 w-2">
                                      <span class="relative inline-flex rounded-full h-2 w-2" :class="u.status === 'aktif' ? 'bg-vibe-secondary' : 'bg-vibe-outline-variant'"></span>
                                    </span>
                                    <span class="text-xs font-medium" :class="u.status === 'aktif' ? 'text-vibe-on-surface' : 'text-vibe-on-surface-variant'" x-text="u.status === 'aktif' ? 'Aktif' : 'Nonaktif'"></span>
                                </div>
                            </td>

                            <!-- Actions (Kebab Menu) -->
                            <td class="px-5 py-3.5 text-right">
                                <div x-data="{ openKebab: false }" class="relative inline-block text-left">
                                    <button @click.stop="openKebab = !openKebab" @click.outside="openKebab = false" class="p-1.5 rounded-md text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>
                                    
                                    <!-- Dropdown -->
                                    <div x-show="openKebab" style="display:none" x-transition.opacity.duration.150ms class="absolute right-0 mt-1 w-44 bg-white rounded-md border border-vibe-outline-variant overflow-hidden z-20">
                                        <!-- Edit -->
                                        <button @click="openEdit(u); openKebab = false" class="w-full text-left px-3.5 py-2.5 text-xs font-medium text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors flex items-center gap-2 border-b border-vibe-outline">
                                            <svg class="w-3.5 h-3.5 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            Edit Detail
                                        </button>
                                        
                                        <!-- Self Protections -->
                                        <template x-if="u.id != currentUserId">
                                            <div>
                                                <!-- Toggle Status -->
                                                <form method="POST" class="m-0 border-b border-vibe-outline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" :value="u.id">
                                                    <button type="submit" class="w-full text-left px-3.5 py-2.5 text-xs font-medium text-vibe-on-surface hover:bg-vibe-surface-dim transition-colors flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5 text-vibe-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                        </svg>
                                                        <span x-text="u.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'"></span>
                                                    </button>
                                                </form>
                                                
                                                <!-- Hapus -->
                                                <form method="POST" class="m-0" onsubmit="return confirm('Hapus user ini secara permanen? Tindakan ini tidak bisa dibatalkan.')">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="id" :value="u.id">
                                                    <button type="submit" class="w-full text-left px-3.5 py-2.5 text-xs font-medium text-vibe-error hover:bg-vibe-error-container transition-colors flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5 text-vibe-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        Hapus Pengguna
                                                    </button>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════ MODAL: Add/Edit User ═══════════════════ -->
    <div x-show="modal" @keydown.escape.window="modal=false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-vibe-on-surface/30 backdrop-blur-sm"
         x-transition style="display:none">
        <div @click.stop class="bg-white rounded-lg p-6 md:p-8 w-full max-w-lg border border-vibe-outline-variant max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-display font-bold text-vibe-on-surface" x-text="isEdit ? 'Edit Pengguna' : 'Tambah Pengguna Baru'"></h3>
                <button @click="modal=false" class="p-1.5 text-vibe-on-surface-variant hover:text-vibe-on-surface hover:bg-vibe-surface-dim rounded-md transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" class="space-y-5">
                <?= csrfField() ?>
                <input type="hidden" name="action" :value="isEdit ? 'edit_user' : 'add_user'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" x-model="form.nama_lengkap" required placeholder="contoh: Budi Santoso" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-md focus:border-vibe-on-surface outline-none transition-colors text-sm font-medium text-vibe-on-surface placeholder-vibe-outline-variant">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Username</label>
                    <input type="text" name="username" x-model="form.username" required placeholder="contoh: budisantoso99" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-md focus:border-vibe-on-surface outline-none transition-colors text-sm font-medium text-vibe-on-surface placeholder-vibe-outline-variant">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Peran</label>
                        <select name="role" x-model="form.role" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-md focus:border-vibe-on-surface outline-none transition-colors text-sm font-medium text-vibe-on-surface appearance-none">
                            <option value="admin">Admin</option>
                            <option value="kasir">Kasir</option>
                        </select>
                    </div>
                    <div x-show="isEdit">
                        <label class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Status Akun</label>
                        <select name="status" x-model="form.status" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-md focus:border-vibe-on-surface outline-none transition-colors text-sm font-medium text-vibe-on-surface appearance-none">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2" x-text="isEdit ? 'Password Baru (Opsional)' : 'Password'"></label>
                    <input type="password" :name="isEdit ? 'new_password' : 'password'" :required="!isEdit" class="w-full px-3 py-2.5 bg-white border border-vibe-outline-variant rounded-md focus:border-vibe-on-surface outline-none transition-colors text-sm font-medium text-vibe-on-surface placeholder-vibe-outline-variant" placeholder="Kosongkan jika tidak ingin mengubah">
                </div>

                <!-- Smart Warning -->
                <div x-show="isEdit && form.isSelf" style="display:none" class="p-3.5 bg-vibe-surface-dim text-vibe-on-surface-variant text-xs rounded-md flex items-start gap-3 border border-vibe-outline-variant">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium leading-relaxed">Ini adalah akun Anda sendiri. Anda tidak bisa menurunkan peran dari Admin atau menonaktifkan akun ini.</span>
                </div>

                <div class="flex gap-3 pt-4 border-t border-vibe-outline">
                    <button type="button" @click="modal=false" class="flex-1 py-2.5 rounded-md border border-vibe-outline-variant text-vibe-on-surface-variant font-medium text-sm hover:bg-vibe-surface-dim transition-colors">Batal</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-md bg-vibe-primary text-white font-medium text-sm hover:bg-vibe-primary-container transition-colors">
                        <span x-text="isEdit ? 'Simpan Perubahan' : 'Buat Pengguna'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div> <!-- Close x-data="usersApp()" -->

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('usersApp', () => ({
        users: <?= json_encode($users) ?>,
        currentUserId: <?= $_SESSION['user_id'] ?>,
        
        // Filter & Search
        searchQuery: '',
        filterRole: 'all',
        
        // Modal
        modal: false,
        isEdit: false,
        form: { id: null, nama_lengkap: '', username: '', role: 'kasir', status: 'aktif', isSelf: false },

        get filteredUsers() {
            return this.users.filter(u => {
                const matchRole = this.filterRole === 'all' || u.role === this.filterRole;
                const searchLower = this.searchQuery.toLowerCase();
                const matchSearch = u.nama_lengkap.toLowerCase().includes(searchLower) || 
                                    u.username.toLowerCase().includes(searchLower);
                return matchRole && matchSearch;
            });
        },

        getAvatarColor(role) {
            const colors = {
                'admin': 'bg-vibe-surface-dim border-vibe-outline-variant text-vibe-on-surface',
                'kasir': 'bg-vibe-surface-dim border-vibe-outline-variant text-vibe-on-surface'
            };
            return colors[role] || 'bg-vibe-surface-dim text-vibe-on-surface-variant';
        },

        getRoleBadge(role) {
            const colors = {
                'admin': 'bg-vibe-surface-dim text-vibe-on-surface border-vibe-outline-variant',
                'kasir': 'bg-vibe-secondary-container text-vibe-secondary border-vibe-secondary/20'
            };
            return colors[role] || 'bg-vibe-surface-dim text-vibe-on-surface-variant border-vibe-outline-variant';
        },

        getRoleIcon(role) {
            const icons = {
                'admin': '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
                'kasir': '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'
            };
            return icons[role] || '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
        },

        openAdd() {
            this.isEdit = false;
            this.form = { id: null, nama_lengkap: '', username: '', role: 'kasir', status: 'aktif', isSelf: false };
            this.modal = true;
        },
        openEdit(u) {
            this.isEdit = true;
            this.form = { 
                id: u.id, 
                nama_lengkap: u.nama_lengkap, 
                username: u.username, 
                role: u.role, 
                status: u.status,
                isSelf: u.id == this.currentUserId
            };
            this.modal = true;
        }
    }));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

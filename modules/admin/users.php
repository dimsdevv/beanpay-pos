<?php
$page_title = 'Kelola User';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Helper: Hitung jumlah admin aktif
function countActiveAdmins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn();
}

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
            // --- SMART LOGIC: Proteksi admin terakhir ---
            $stmtOld = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldData = $stmtOld->fetch();

            if ($oldData['role'] === 'admin' && $oldData['status'] === 'aktif' && countActiveAdmins($pdo) <= 1) {
                $_SESSION['error'] = "Tidak bisa menghapus. User ini adalah satu-satunya Admin aktif!";
            } else {
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                $_SESSION['success'] = "User berhasil dihapus.";
            }
        }
    }

    header('Location: users.php'); exit;
}

// Sekarang baru aman load header (output HTML)
requireRole(['admin']);

require_once __DIR__ . '/../../includes/header.php';
$users = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div x-data="usersApp()" class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-evergreen tracking-tight">Staff Management</h1>
            <p class="text-gray-500 mt-0.5 text-sm font-medium">Manage access and roles for all restaurant staff.</p>
        </div>
        <button @click="openAdd()" class="flex items-center gap-2 px-5 py-2.5 bg-theme-ocean text-white rounded-xl font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 w-full sm:w-auto justify-center hover-lift">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Add New User
        </button>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="p-4 rounded-xl bg-theme-bg text-theme-leaf font-bold flex items-center gap-2 border border-theme-sage/20 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="p-4 rounded-xl bg-red-50 text-red-600 font-bold flex items-center gap-2 border border-red-100 animate-[fadeIn_0.3s_ease-out]">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats & Filters Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- All Users Filter -->
        <button @click="filterRole = 'all'" 
                class="group flex flex-col items-center justify-center p-4 rounded-2xl border transition-all duration-300 hover-lift"
                :class="filterRole === 'all' ? 'bg-theme-evergreen border-theme-evergreen shadow-lg shadow-theme-evergreen/20' : 'bg-white border-gray-100 hover:border-theme-ocean/50 hover:shadow-sm'">
            <div class="text-2xl font-extrabold mb-1" :class="filterRole === 'all' ? 'text-white' : 'text-theme-evergreen'"><?= count($users) ?></div>
            <div class="text-xs font-bold uppercase tracking-wider" :class="filterRole === 'all' ? 'text-white/80' : 'text-gray-400'">All Staff</div>
        </button>

        <?php
        $roleCount = ['admin'=>0,'kasir'=>0,'waiter'=>0,'dapur'=>0];
        foreach($users as $u) $roleCount[$u['role']] = ($roleCount[$u['role']] ?? 0) + 1;
        
        $roleDetails = [
            'admin'  => ['icon' => '👑', 'label' => 'Admin', 'color' => 'purple'],
            'kasir'  => ['icon' => '💳', 'label' => 'Cashier', 'color' => 'blue'],
            'waiter' => ['icon' => '🍽️', 'label' => 'Waiter', 'color' => 'emerald'],
            'dapur'  => ['icon' => '👨‍🍳', 'label' => 'Kitchen', 'color' => 'orange']
        ];
        
        foreach($roleDetails as $role => $detail):
            $count = $roleCount[$role];
        ?>
        <button @click="filterRole = '<?= $role ?>'" 
                class="group relative flex flex-col items-center justify-center p-4 rounded-2xl border overflow-hidden transition-all duration-300 hover-lift"
                :class="filterRole === '<?= $role ?>' ? 'bg-theme-evergreen border-theme-evergreen shadow-lg shadow-theme-evergreen/20' : 'bg-white border-gray-100 hover:border-theme-ocean/50 hover:shadow-sm'">
            
            <div class="absolute -right-4 -bottom-4 text-5xl opacity-[0.03] grayscale transition-transform duration-500 group-hover:scale-110" :class="filterRole === '<?= $role ?>' ? 'grayscale-0 opacity-10' : ''">
                <?= $detail['icon'] ?>
            </div>

            <div class="flex items-center gap-2 mb-1 z-10">
                <span class="text-sm"><?= $detail['icon'] ?></span>
                <span class="text-2xl font-extrabold" :class="filterRole === '<?= $role ?>' ? 'text-white' : 'text-theme-evergreen'"><?= $count ?></span>
            </div>
            <div class="text-xs font-bold uppercase tracking-wider z-10" :class="filterRole === '<?= $role ?>' ? 'text-white/80' : 'text-gray-400'"><?= $detail['label'] ?></div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Table Section -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] overflow-hidden flex flex-col">
        
        <!-- Controls Bar -->
        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
            <h2 class="font-bold text-theme-evergreen text-lg">Staff Directory</h2>
            
            <!-- Live Search -->
            <div class="relative w-full md:w-80">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Search by name or username..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage text-sm font-medium transition-shadow placeholder-gray-400 shadow-sm">
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full">
                <thead>
                    <tr class="bg-white border-b border-gray-100 text-xs font-bold text-gray-400 uppercase tracking-widest">
                        <th class="px-6 py-4 text-left">Staff Member</th>
                        <th class="px-6 py-4 text-left">Role</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50/80">
                    
                    <!-- Empty State (No Results) -->
                    <tr x-show="filteredUsers.length === 0" style="display:none">
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-700 mb-1">No staff found</h3>
                            <p class="text-sm text-gray-400">Try adjusting your search query or role filter.</p>
                            <button @click="searchQuery = ''; filterRole = 'all'" class="mt-4 px-4 py-2 bg-theme-bg text-theme-leaf font-bold text-sm rounded-lg hover:bg-theme-sage hover:text-white transition-colors">Clear Filters</button>
                        </td>
                    </tr>

                    <!-- User Rows -->
                    <template x-for="u in filteredUsers" :key="u.id">
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <!-- Name & Username -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
                                         :class="getAvatarColor(u.role)">
                                        <span x-text="u.nama_lengkap.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-theme-evergreen" x-text="u.nama_lengkap"></span>
                                            <span x-show="u.id == currentUserId" class="px-1.5 py-0.5 text-[9px] font-extrabold bg-indigo-100 text-indigo-600 rounded-md uppercase tracking-wider">You</span>
                                        </div>
                                        <div class="text-xs text-gray-400 font-medium mt-0.5" x-text="'@' + u.username"></div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Role -->
                            <td class="px-6 py-4">
                                <span class="px-3 py-1.5 rounded-lg text-xs font-bold border flex items-center gap-1.5 w-max" :class="getRoleBadge(u.role)">
                                    <span x-text="getRoleIcon(u.role)"></span>
                                    <span x-text="u.role.charAt(0).toUpperCase() + u.role.slice(1)"></span>
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-2.5 w-2.5">
                                      <span x-show="u.status === 'aktif'" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-theme-sage opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="u.status === 'aktif' ? 'bg-theme-leaf' : 'bg-gray-300'"></span>
                                    </span>
                                    <span class="text-xs font-bold" :class="u.status === 'aktif' ? 'text-gray-700' : 'text-gray-400'" x-text="u.status === 'aktif' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </td>

                            <!-- Actions (Kebab Menu) -->
                            <td class="px-6 py-4 text-right">
                                <div x-data="{ openKebab: false }" class="relative inline-block text-left">
                                    <button @click.stop="openKebab = !openKebab" @click.outside="openKebab = false" class="p-2 rounded-xl text-gray-400 hover:text-theme-evergreen hover:bg-gray-100 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>
                                    
                                    <!-- Dropdown -->
                                    <div x-show="openKebab" style="display:none" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                        <!-- Edit -->
                                        <button @click="openEdit(u); openKebab = false" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2 border-b border-gray-50">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            Edit Details
                                        </button>
                                        
                                        <!-- Self Protections -->
                                        <template x-if="u.id != currentUserId">
                                            <div>
                                                <!-- Toggle Status -->
                                                <form method="POST" class="m-0 border-b border-gray-50">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" :value="u.id">
                                                    <button type="submit" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                        </svg>
                                                        <span x-text="u.status === 'aktif' ? 'Deactivate Account' : 'Activate Account'"></span>
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete -->
                                                <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to permanently delete this user?')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="id" :value="u.id">
                                                    <button type="submit" class="w-full text-left px-4 py-3 text-xs font-bold text-theme-coral hover:bg-theme-coral/10 transition-colors flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-theme-coral" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        Delete User
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
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-theme-evergreen/40 backdrop-blur-md"
         x-transition style="display:none">
        <div @click.stop class="bg-white/95 glass rounded-3xl p-6 md:p-8 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-extrabold text-theme-evergreen tracking-tight" x-text="isEdit ? 'Edit User' : 'Add New User'"></h3>
                <button @click="modal=false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" :value="isEdit ? 'edit_user' : 'add_user'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Full Name</label>
                    <input type="text" name="nama_lengkap" x-model="form.nama_lengkap" required placeholder="e.g. John Doe" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-theme-evergreen placeholder-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" x-model="form.username" required placeholder="e.g. johndoe99" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-medium placeholder-gray-300">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Role</label>
                        <select name="role" x-model="form.role" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-gray-700 appearance-none">
                            <option value="admin">Admin</option>
                            <option value="kasir">Cashier</option>
                            <option value="waiter">Waiter</option>
                            <option value="dapur">Kitchen</option>
                        </select>
                    </div>
                    <div x-show="isEdit">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Account Status</label>
                        <select name="status" x-model="form.status" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-bold text-gray-700 appearance-none">
                            <option value="aktif">Active</option>
                            <option value="nonaktif">Inactive</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2" x-text="isEdit ? 'New Password (Optional)' : 'Password'"></label>
                    <input type="password" :name="isEdit ? 'new_password' : 'password'" :required="!isEdit" class="w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-theme-sage/30 focus:border-theme-sage outline-none transition-all font-medium placeholder-gray-300" placeholder="Leave blank to keep current">
                </div>

                <!-- Smart Warning -->
                <div x-show="isEdit && form.isSelf" style="display:none" class="p-4 bg-indigo-50 text-indigo-700 text-xs rounded-xl flex items-start gap-3 border border-indigo-100">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium leading-relaxed">This is your own account. For security reasons, you cannot downgrade your role from Admin or deactivate this account.</span>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="modal=false" class="flex-1 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors hover-lift">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl bg-theme-ocean text-white font-bold hover:bg-theme-ocean-light transition-colors shadow-lg shadow-theme-ocean/30 hover-lift">
                        <span x-text="isEdit ? 'Save Changes' : 'Create User'"></span>
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
        form: { id: null, nama_lengkap: '', username: '', role: 'waiter', status: 'aktif', isSelf: false },

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
                'admin': 'bg-theme-twilight/10 text-theme-twilight',
                'kasir': 'bg-theme-ocean/10 text-theme-ocean',
                'waiter': 'bg-theme-bg text-theme-leaf',
                'dapur': 'bg-theme-sun/10 text-theme-sun'
            };
            return colors[role] || 'bg-gray-100 text-gray-600';
        },

        getRoleBadge(role) {
            const colors = {
                'admin': 'bg-theme-twilight/10 text-theme-twilight border-theme-twilight/20',
                'kasir': 'bg-theme-ocean/10 text-theme-ocean border-theme-ocean/20',
                'waiter': 'bg-theme-bg text-theme-leaf border-theme-sage/20',
                'dapur': 'bg-theme-sun/10 text-theme-sun border-theme-sun/20'
            };
            return colors[role] || 'bg-gray-50 text-gray-600 border-gray-200';
        },

        getRoleIcon(role) {
            const icons = { 'admin': '👑', 'kasir': '💳', 'waiter': '🍽️', 'dapur': '👨‍🍳' };
            return icons[role] || '👤';
        },

        openAdd() {
            this.isEdit = false;
            this.form = { id: null, nama_lengkap: '', username: '', role: 'waiter', status: 'aktif', isSelf: false };
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

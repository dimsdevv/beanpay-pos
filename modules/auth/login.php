<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['success']      = "Selamat datang, " . $user['nama_lengkap'] . "!";
            redirectByRole();
        } else {
            $error = "Username atau Password salah, atau akun tidak aktif.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Checkpoint POS</title>
    <meta name="description" content="Masuk ke sistem Checkpoint POS untuk mengelola restoran Anda.">
    <meta name="theme-color" content="#0F172A">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">

    <!-- Tailwind CSS (Static Compiled) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .font-display { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        .vibe-input {
            transition: border-color 0.15s;
        }
        .vibe-input:focus {
            outline: none;
            border-color: #020617;
        }

        .show-pass { cursor: pointer; }
        
        * { box-shadow: none !important; }
        .stagger { opacity: 0; }
        .delay-1 { animation-delay: 0.05s; }
        .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; }
        .delay-4 { animation-delay: 0.2s; }
        .delay-5 { animation-delay: 0.25s; }
    </style>
</head>
<body class="min-h-screen flex bg-vibe-bg overflow-hidden">

    <!-- ════════════════════════════════════
         LEFT PANEL — Logo
    ════════════════════════════════════ -->
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative overflow-hidden">
        <img src="<?= BASE_URL ?>/assets/images/logo.jpeg" alt="Checkpoint POS" fetchpriority="high" loading="eager" class="absolute inset-0 w-full h-full object-cover">
    </div>

    <!-- ════════════════════════════════════
         RIGHT PANEL — Login Form
    ════════════════════════════════════ -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-vibe-bg relative">

        <!-- Mobile logo (only on small screens) -->
        <!-- Mobile logo -->
        <div class="absolute top-6 left-6 flex items-center gap-2 lg:hidden">
            <img src="<?= BASE_URL ?>/assets/images/logo.jpeg" alt="Checkpoint POS" class="h-8 w-auto">
            <span class="font-extrabold text-vibe-on-surface">Checkpoint</span>
        </div>

        <!-- Form card -->
        <div class="w-full max-w-sm xl:max-w-md">

            <!-- Header -->
            <div class="mb-8 stagger animate-fade-in delay-1">
                <h2 class="text-2xl xl:text-3xl font-display font-extrabold text-vibe-on-surface mb-1.5">Masuk</h2>
                <p class="text-vibe-on-surface-variant text-sm font-medium">Masukkan akun Anda untuk mengakses sistem.</p>
            </div>

            <!-- Error alert -->
            <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-md bg-vibe-error-container border border-vibe-error/20 flex items-start gap-3 stagger animate-fade-in">
                <div class="w-8 h-8 rounded bg-white/50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-vibe-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-semibold text-vibe-error mb-0.5">Gagal Masuk</div>
                    <div class="text-xs text-vibe-error/80"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" class="space-y-5" id="loginForm">
                <?= csrfField() ?>

                <!-- Username -->
                <div class="stagger animate-fade-in delay-2">
                    <label for="username" class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input type="text" name="username" id="username" required autofocus
                               autocomplete="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Masukkan username"
                               class="vibe-input w-full pl-10 pr-4 py-2.5 bg-white border border-vibe-outline-variant rounded-md text-vibe-on-surface text-sm font-medium placeholder-vibe-outline-variant outline-none">
                    </div>
                </div>

                <!-- Password -->
                <div class="stagger animate-fade-in delay-3" x-data="{ show: false }" xmlns:x-data="http://www.w3.org/1999/xhtml">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-widest text-vibe-on-surface-variant mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input id="passwordInput" name="password" required
                               autocomplete="current-password"
                               placeholder="Masukkan password"
                               class="vibe-input w-full pl-10 pr-10 py-2.5 bg-white border border-vibe-outline-variant rounded-md text-vibe-on-surface text-sm font-medium placeholder-vibe-outline-variant outline-none"
                               type="password">
                        <!-- Toggle visibility -->
                        <button type="button" onclick="togglePassword()"
                                class="show-pass absolute right-3 top-1/2 -translate-y-1/2 text-vibe-outline-variant hover:text-vibe-on-surface transition-colors" id="eyeBtn">
                            <svg id="eyeIcon" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="stagger animate-fade-in delay-4 pt-2">
                    <button type="submit" id="submitBtn"
                            class="w-full flex items-center justify-center gap-2 py-3 bg-vibe-primary hover:bg-vibe-primary-container text-white font-medium rounded-md transition-colors text-sm">
                        <span>Masuk</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Divider info -->
            <div class="mt-8 stagger animate-fade-up delay-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-1 h-px bg-vibe-outline-variant/30"></div>
                    <span class="text-xs text-vibe-outline font-medium">Akun Role</span>
                    <div class="flex-1 h-px bg-vibe-outline-variant/30"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <?php
                    $roles = [
                        ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'Admin', 'color' => 'text-vibe-on-surface bg-white'],
                        ['icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Kasir', 'color' => 'text-vibe-on-surface bg-white'],
                    ];
                    ?>
                    <div class="col-span-1"></div>
                    <?php foreach ($roles as $r): ?>
                    <div class="flex flex-col items-center gap-1.5 p-3 rounded-md <?= $r['color'] ?> border border-vibe-outline-variant">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $r['icon'] ?>"/>
                        </svg>
                        <span class="text-[10px] font-bold uppercase tracking-wide"><?= $r['label'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Copyright (mobile) -->
            <div class="mt-8 text-center text-xs text-vibe-outline stagger animate-fade-up delay-5">
                &copy; <?= date('Y') ?> Checkpoint POS · Hak cipta dilindungi
            </div>
        </div>
    </div>

    <script>
        // Password toggle
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon  = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
            } else {
                input.type = 'password';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
            }
        }

        // Loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Memverifikasi...
            `;
        });

        // Trigger stagger animations
        document.querySelectorAll('.stagger').forEach((el, i) => {
            el.style.animationFillMode = 'forwards';
        });
    </script>
</body>
</html>

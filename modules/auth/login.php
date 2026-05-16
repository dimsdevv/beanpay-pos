<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
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
    <title>Login — BeanPay POS</title>
    <meta name="description" content="Masuk ke sistem BeanPay POS untuk mengelola restoran Anda.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        vibe: {
                            'primary':            '#004ac6',
                            'primary-container':  '#2563eb',
                            'on-primary':         '#ffffff',
                            'secondary':          '#006c49',
                            'error':              '#ba1a1a',
                            'bg':                 '#f8f9ff',
                            'surface':            '#ffffff',
                            'surface-container':  '#e5eeff',
                            'on-surface':         '#0b1c30',
                            'on-surface-variant': '#434655',
                            'outline':            '#737686',
                            'outline-variant':    '#c3c6d7',
                        }
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.5s ease-out forwards',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeUp: {
                            '0%':   { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Floating blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.35;
            animation: blobFloat 8s ease-in-out infinite alternate;
        }
        @keyframes blobFloat {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(20px, -20px) scale(1.08); }
        }

        /* Custom input focus ring */
        .vibe-input {
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .vibe-input:focus {
            outline: none;
            border-color: #004ac6;
            box-shadow: 0 0 0 3px rgba(0, 74, 198, 0.12);
        }

        /* Password toggle */
        .show-pass { cursor: pointer; }

        /* Fade-up stagger */
        .delay-1 { animation-delay: 0.05s; }
        .delay-2 { animation-delay: 0.12s; }
        .delay-3 { animation-delay: 0.19s; }
        .delay-4 { animation-delay: 0.26s; }
        .delay-5 { animation-delay: 0.33s; }
        .stagger { opacity: 0; }
    </style>
</head>
<body class="min-h-screen flex bg-vibe-bg overflow-hidden">

    <!-- ════════════════════════════════════
         LEFT PANEL — Branding / Hero
    ════════════════════════════════════ -->
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative flex-col overflow-hidden">

        <!-- Background image -->
        <div class="absolute inset-0 bg-cover bg-center"
             style="background-image: url('/BeanPay/assets/login_bg.png')"></div>

        <!-- Dark overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-[#001a4d]/85 via-[#003399]/60 to-[#004ac6]/40"></div>

        <!-- Decorative blobs -->
        <div class="blob w-80 h-80 bg-blue-400 top-[-60px] left-[-60px]" style="animation-delay:0s"></div>
        <div class="blob w-64 h-64 bg-indigo-500 bottom-20 right-[-40px]" style="animation-delay:2s"></div>
        <div class="blob w-48 h-48 bg-cyan-400 bottom-[-20px] left-32" style="animation-delay:4s"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14">
            <!-- Logo top -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center border border-white/30">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"/>
                    </svg>
                </div>
                <span class="text-white font-extrabold text-lg tracking-tight">BeanPay</span>
                <span class="text-white/50 text-xs font-medium border border-white/20 rounded-full px-2 py-0.5">POS</span>
            </div>

            <!-- Main hero text -->
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 backdrop-blur-sm border border-white/20 text-white/80 text-xs font-semibold mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse-slow"></span>
                    Sistem aktif & siap digunakan
                </div>
                <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-4">
                    Kelola restoran<br>
                    <span class="text-blue-200">lebih cerdas.</span>
                </h1>
                <p class="text-white/65 text-base font-medium leading-relaxed max-w-sm">
                    Platform POS all-in-one untuk kasir, dapur, waiter, dan analitik bisnis Anda — dalam satu genggaman.
                </p>

                <!-- Feature pills -->
                <div class="flex flex-wrap gap-2 mt-8">
                    <?php
                    $features = [
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Laporan Real-time'],
                        ['icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Multi Metode Bayar'],
                        ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Kitchen Display'],
                    ];
                    foreach ($features as $f): ?>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/10 backdrop-blur-sm border border-white/15 text-white/80 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $f['icon'] ?>"/>
                        </svg>
                        <?= $f['label'] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer credit -->
            <div class="text-white/35 text-xs font-medium">
                &copy; <?= date('Y') ?> BeanPay POS System · Semua hak dilindungi
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         RIGHT PANEL — Login Form
    ════════════════════════════════════ -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-vibe-bg relative">

        <!-- Mobile logo (only on small screens) -->
        <div class="absolute top-6 left-6 flex items-center gap-2 lg:hidden">
            <div class="w-8 h-8 bg-vibe-primary rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"/>
                </svg>
            </div>
            <span class="font-extrabold text-vibe-on-surface">BeanPay</span>
        </div>

        <!-- Form card -->
        <div class="w-full max-w-sm xl:max-w-md">

            <!-- Header -->
            <div class="mb-8 stagger animate-fade-up delay-1">
                <h2 class="text-2xl xl:text-3xl font-extrabold text-vibe-on-surface mb-1.5">Selamat datang! 👋</h2>
                <p class="text-vibe-on-surface-variant text-sm font-medium">Masuk untuk melanjutkan ke dashboard Anda.</p>
            </div>

            <!-- Error alert -->
            <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-100 flex items-start gap-3 stagger animate-fade-up">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-red-700 mb-0.5">Login Gagal</div>
                    <div class="text-xs text-red-600"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" class="space-y-5" id="loginForm">

                <!-- Username -->
                <div class="stagger animate-fade-up delay-2">
                    <label for="username" class="block text-sm font-semibold text-vibe-on-surface mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-vibe-outline">
                            <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input type="text" name="username" id="username" required autofocus
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Masukkan username"
                               class="vibe-input w-full pl-11 pr-4 py-3 bg-white border border-vibe-outline-variant/40 rounded-xl text-vibe-on-surface text-sm font-medium placeholder-vibe-outline shadow-sm">
                    </div>
                </div>

                <!-- Password -->
                <div class="stagger animate-fade-up delay-3" x-data="{ show: false }" xmlns:x-data="http://www.w3.org/1999/xhtml">
                    <label for="password" class="block text-sm font-semibold text-vibe-on-surface mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-vibe-outline">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input id="passwordInput" name="password" required
                               placeholder="Masukkan password"
                               class="vibe-input w-full pl-11 pr-12 py-3 bg-white border border-vibe-outline-variant/40 rounded-xl text-vibe-on-surface text-sm font-medium placeholder-vibe-outline shadow-sm"
                               type="password">
                        <!-- Toggle visibility -->
                        <button type="button" onclick="togglePassword()"
                                class="show-pass absolute right-4 top-1/2 -translate-y-1/2 text-vibe-outline hover:text-vibe-primary transition-colors" id="eyeBtn">
                            <svg id="eyeIcon" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="stagger animate-fade-up delay-4 pt-1">
                    <button type="submit" id="submitBtn"
                            class="w-full flex items-center justify-center gap-2.5 py-3.5 bg-vibe-primary hover:bg-vibe-primary-container text-white font-bold rounded-xl shadow-lg shadow-vibe-primary/30 transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Masuk ke Dashboard
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
                        ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'Admin', 'color' => 'text-vibe-primary bg-vibe-surface-container'],
                        ['icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Kasir', 'color' => 'text-vibe-secondary bg-green-50'],
                        ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Dapur', 'color' => 'text-orange-600 bg-orange-50'],
                    ];
                    foreach ($roles as $r): ?>
                    <div class="flex flex-col items-center gap-1.5 p-3 rounded-xl <?= $r['color'] ?> border border-vibe-outline-variant/10">
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
                &copy; <?= date('Y') ?> BeanPay POS · Hak cipta dilindungi
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

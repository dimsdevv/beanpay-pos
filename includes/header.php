<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanPay - <?= $page_title ?? 'POS System' ?></title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (CDN for Development) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Tailwind Config: VibePOS Design System -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        vibe: {
                            // Primary: Electric Blue
                            'primary': '#004ac6',
                            'primary-container': '#2563eb',
                            'primary-light': '#dbe1ff',
                            'primary-dim': '#b4c5ff',
                            'on-primary': '#ffffff',

                            // Secondary: Emerald Green
                            'secondary': '#006c49',
                            'secondary-container': '#6cf8bb',
                            'on-secondary': '#ffffff',

                            // Tertiary: Vivid Purple
                            'tertiary': '#632ecd',
                            'tertiary-container': '#7d4ce7',
                            'tertiary-light': '#e9ddff',
                            'on-tertiary': '#ffffff',

                            // Error / Danger
                            'error': '#ba1a1a',
                            'error-container': '#ffdad6',

                            // Accent: Warm Orange (for alerts/pending)
                            'accent': '#e67e22',
                            'accent-light': '#fef3c7',

                            // Surfaces & Neutrals
                            'bg': '#f8f9ff',
                            'surface': '#ffffff',
                            'surface-dim': '#cbdbf5',
                            'surface-container': '#e5eeff',
                            'surface-high': '#dce9ff',
                            'on-surface': '#0b1c30',
                            'on-surface-variant': '#434655',
                            'outline': '#737686',
                            'outline-variant': '#c3c6d7',
                            'inverse-surface': '#213145',
                            'inverse-on-surface': '#eaf1ff',
                        },
                        // Backward-compatible aliases (theme-* → vibe-* mapping)
                        theme: {
                            'evergreen': '#0b1c30',    // → vibe-on-surface
                            'leaf': '#006c49',         // → vibe-secondary
                            'sage': '#004ac6',         // → vibe-primary (used for accents)
                            'muted-olive': '#e5eeff',  // → vibe-surface-container
                            'olive': '#434655',        // → vibe-on-surface-variant
                            'bg': '#f8f9ff',           // → vibe-bg
                            'ocean': '#004ac6',        // → vibe-primary
                            'ocean-light': '#2563eb',  // → vibe-primary-container
                            'coral': '#ba1a1a',        // → vibe-error
                            'coral-light': '#ffdad6',  // → vibe-error-container
                            'sun': '#e67e22',          // → vibe-accent
                            'sun-light': '#fef3c7',    // → vibe-accent-light
                            'twilight': '#632ecd',     // → vibe-tertiary
                        }
                    },
                    boxShadow: {
                        'card': '0 4px 20px rgba(0, 0, 0, 0.05)',
                        'card-hover': '0 8px 30px rgba(0, 0, 0, 0.08)',
                        'elevated': '0 8px 24px rgba(0, 0, 0, 0.1)',
                    },
                    borderRadius: {
                        'sm': '0.25rem',
                        'DEFAULT': '0.5rem',
                        'md': '0.75rem',
                        'lg': '1rem',
                        'xl': '1.5rem',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(195, 198, 215, 0.5); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(115, 118, 134, 0.6); }

        /* Hover Lift Animation */
        .hover-lift {
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
        }
        .hover-lift:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(0,0,0,0.12);
        }

        /* Smooth fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        /* Backward compat: Glass utility (now solid white) */
        .glass { background: rgba(255,255,255,0.98); }

        /* Hide scrollbar utility */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Line clamp */
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="text-vibe-on-surface h-screen flex overflow-hidden bg-vibe-bg" x-data="{ sidebarOpen: false }">

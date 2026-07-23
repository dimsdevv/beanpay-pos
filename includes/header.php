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
    <meta name="theme-color" content="#0F172A">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <title>Checkpoint POS — <?= $page_title ?? 'POS System' ?></title>
    
    <!-- Local Vendor Assets (Offline-first) -->
    <script src="<?= BASE_URL ?>/assets/vendor/tailwind.min.js"></script>
    <script defer src="<?= BASE_URL ?>/assets/vendor/alpine.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/sweetalert2.min.css">
    <script src="<?= BASE_URL ?>/assets/vendor/sweetalert2.min.js"></script>
    
    <!-- Google Fonts: Inter & Outfit (fallback ke system sans-serif jika offline) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind Config: Swiss Minimal Design System -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        vibe: {
                            // Primary: Muted Cobalt / Deep Graphite
                            'primary': '#0F172A',
                            'primary-container': '#1E293B',
                            'primary-light': '#F8FAFC',
                            'primary-dim': '#E2E8F0',
                            'on-primary': '#FFFFFF',

                            // Secondary: Muted Teal/Emerald
                            'secondary': '#0F766E',
                            'secondary-container': '#CCFBF1',
                            'on-secondary': '#FFFFFF',

                            // Tertiary: Slate
                            'tertiary': '#334155',
                            'tertiary-container': '#475569',
                            'tertiary-light': '#F1F5F9',
                            'on-tertiary': '#FFFFFF',

                            // Error / Danger
                            'error': '#DC2626',
                            'error-container': '#FEE2E2',

                            // Accent: Muted Orange
                            'accent': '#D97706',
                            'accent-light': '#FEF3C7',

                            // Surfaces & Neutrals
                            'bg': '#FFFFFF',
                            'surface': '#FFFFFF',
                            'surface-dim': '#F8FAFC',
                            'surface-container': '#F1F5F9',
                            'surface-high': '#E2E8F0',
                            'on-surface': '#020617', // Extremely dark charcoal ink
                            'on-surface-variant': '#475569', // Muted text
                            'outline': '#E2E8F0', // Hairline borders
                            'outline-variant': '#CBD5E1',
                            'inverse-surface': '#0F172A',
                            'inverse-on-surface': '#F8FAFC',
                        },
                        // Backward-compatible aliases
                        theme: {
                            'evergreen': '#020617',
                            'leaf': '#0F766E',
                            'sage': '#0F172A',
                            'muted-olive': '#F1F5F9',
                            'olive': '#475569',
                            'bg': '#FFFFFF',
                            'ocean': '#0F172A',
                            'ocean-light': '#1E293B',
                            'coral': '#DC2626',
                            'coral-light': '#FEE2E2',
                            'sun': '#D97706',
                            'sun-light': '#FEF3C7',
                            'twilight': '#334155',
                        }
                    },
                    boxShadow: {
                        'card': 'none',
                        'card-hover': 'none',
                        'elevated': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
                    },
                    borderRadius: {
                        'sm': '0.125rem',
                        'DEFAULT': '0.25rem',
                        'md': '0.375rem',
                        'lg': '0.5rem',
                        'xl': '0.5rem', // Capped at 8px to prevent "insanely rounded" AI look
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-display { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }
        
        /* Custom scrollbar - ultra minimal */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* Minimal Interaction Animations */
        .hover-lift {
            transition: transform 0.15s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.15s ease;
        }
        .hover-lift:hover {
            transform: scale(1.02);
            /* Shadow removed for flat aesthetic */
        }

        /* Smooth fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-fade-in {
            animation: fadeIn 0.2s ease-out forwards;
        }

        /* Hide scrollbar utility */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Line clamp */
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        
        /* Global Reset for Swiss Design */
        * {
            box-shadow: none !important; /* Force remove all decorative box shadows */
        }
        .shadow-elevated {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
        }
    </style>
<?php if (!isset($noPwa)): ?>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= BASE_URL ?>/service-worker.js');
}
</script>
<?php endif; ?>
</head>
<body class="text-vibe-on-surface h-screen flex overflow-hidden bg-vibe-bg antialiased" x-data="{ sidebarOpen: false }">


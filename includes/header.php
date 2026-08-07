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
    
    <!-- Tailwind CSS (Static Compiled) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css?v=1.0.1">
    
    <!-- Local Vendor Assets (Offline-first) -->
    <script defer src="<?= BASE_URL ?>/assets/vendor/alpine.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/sweetalert2.min.css">
    <script src="<?= BASE_URL ?>/assets/vendor/sweetalert2.min.js"></script>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <style>
        [x-cloak] { display: none !important; }
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

</head>
<body class="text-vibe-on-surface h-screen w-full flex overflow-hidden bg-vibe-bg antialiased" x-data="{ sidebarOpen: false }">


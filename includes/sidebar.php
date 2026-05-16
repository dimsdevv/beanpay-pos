<?php
$user = currentUser();
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function for nav link classes (VibePOS style)
function navClass($current_page, $target) {
    if ($current_page == $target) {
        return 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-vibe-primary font-semibold bg-vibe-primary/8 border-l-[3px] border-vibe-primary transition-all duration-200';
    }
    return 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-vibe-on-surface-variant font-medium hover:bg-vibe-surface-container hover:text-vibe-on-surface transition-all duration-200';
}
?>
<!-- Mobile Backdrop -->
<div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-20 md:hidden" x-transition.opacity style="display: none;"></div>

<!-- Sidebar -->
<aside class="w-[220px] bg-white flex flex-col h-full border-r border-vibe-outline-variant/30 z-30 fixed inset-y-0 left-0 transform md:relative md:translate-x-0 transition duration-200 ease-in-out" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
    
    <!-- Logo area -->
    <div class="px-5 pt-6 pb-4">
        <div class="flex items-center gap-2 mb-1">
            <h1 class="font-extrabold text-xl text-vibe-primary tracking-tight">BeanPay</h1>
        </div>
        <span class="text-[11px] text-vibe-on-surface-variant font-medium">POS System</span>
    </div>

    <?php if($user['role'] === 'admin'): ?>
    <!-- Quick Action Button -->
    <div class="px-4 mb-4">
        <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-vibe-primary text-white font-semibold rounded-lg shadow-md shadow-vibe-primary/25 hover:bg-vibe-primary-container hover:shadow-lg transition-all duration-200 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            New Transaction
        </a>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 space-y-1">
        <?php if($user['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/modules/admin/dashboard.php" class="<?= navClass($current_page, 'dashboard.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/modules/admin/menu.php" class="<?= navClass($current_page, 'menu.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Menu & Kategori
            </a>
            
            <a href="<?= BASE_URL ?>/modules/admin/inventaris.php" class="<?= navClass($current_page, 'inventaris.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                Inventory
            </a>

            <a href="<?= BASE_URL ?>/modules/admin/laporan.php" class="<?= navClass($current_page, 'laporan.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Reports
            </a>

            <a href="<?= BASE_URL ?>/modules/admin/users.php" class="<?= navClass($current_page, 'users.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Customers
            </a>

            <a href="<?= BASE_URL ?>/modules/admin/meja.php" class="<?= navClass($current_page, 'meja.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Meja
            </a>

            <a href="<?= BASE_URL ?>/modules/admin/promo.php" class="<?= navClass($current_page, 'promo.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                Promo & Voucher
            </a>
        <?php endif; ?>

        <?php if($user['role'] === 'waiter'): ?>
            <a href="<?= BASE_URL ?>/modules/waiter/order.php" class="<?= navClass($current_page, 'order.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Input Pesanan
            </a>
            <a href="<?= BASE_URL ?>/modules/waiter/meja.php" class="<?= navClass($current_page, 'meja.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Status Meja
            </a>
        <?php endif; ?>

        <?php if($user['role'] === 'kasir'): ?>
            <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="<?= navClass($current_page, 'index.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pembayaran
            </a>
            <a href="<?= BASE_URL ?>/modules/kasir/riwayat.php" class="<?= navClass($current_page, 'riwayat.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Riwayat Transaksi
            </a>
        <?php endif; ?>
        
        <?php if($user['role'] === 'dapur'): ?>
             <a href="<?= BASE_URL ?>/modules/dapur/index.php" class="<?= navClass($current_page, 'index.php') ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Antrian Dapur
            </a>
        <?php endif; ?>
    </nav>

    <!-- Bottom Section: Settings + User Profile -->
    <div class="border-t border-vibe-outline-variant/20 p-3 space-y-1">
        <?php if($user['role'] === 'admin'): ?>
        <a href="<?= BASE_URL ?>/modules/admin/pengaturan.php" class="<?= navClass($current_page, 'pengaturan.php') ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            Settings
        </a>
        <?php endif; ?>

        <!-- User Profile -->
        <div class="flex items-center gap-3 px-3 py-3 mt-2">
            <div class="w-9 h-9 rounded-full bg-vibe-surface-container text-vibe-primary flex items-center justify-center font-bold text-sm">
                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
            </div>
            <div class="overflow-hidden flex-1">
                <div class="font-semibold text-sm truncate text-vibe-on-surface"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                <div class="text-xs text-vibe-on-surface-variant capitalize"><?= $user['role'] ?></div>
            </div>
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="p-1.5 rounded-lg text-vibe-on-surface-variant hover:text-vibe-error hover:bg-vibe-error/8 transition-colors" title="Logout">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content Area -->
<div class="flex-1 flex flex-col h-full overflow-hidden relative">
    
    <!-- Topbar -->
    <header class="h-16 bg-white border-b border-vibe-outline-variant/20 px-4 md:px-8 flex items-center justify-between shrink-0 z-10">
        <div class="flex items-center gap-4">
            <button @click="sidebarOpen = true" class="md:hidden p-2 -ml-2 rounded-lg text-vibe-on-surface-variant hover:bg-vibe-surface-container transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <!-- Search Bar -->
            <div class="hidden md:flex items-center bg-vibe-bg px-4 py-2 rounded-full text-sm w-80 focus-within:ring-2 focus-within:ring-vibe-primary/30 transition-all border border-transparent focus-within:border-vibe-primary/20">
                <svg class="w-4 h-4 text-vibe-outline mr-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" placeholder="Search orders, products, or customers..." class="bg-transparent border-none outline-none text-vibe-on-surface w-full placeholder:text-vibe-outline">
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- Notification Bell with Dropdown -->
            <div x-data="notifCenter()" x-init="startPolling()" class="relative">
                <button @click="open = !open" class="w-9 h-9 rounded-lg flex items-center justify-center text-vibe-on-surface-variant hover:bg-vibe-surface-container transition-colors relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <!-- Badge -->
                    <span x-show="unreadCount > 0" id="notif-badge" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-vibe-error text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 animate-pulse" x-transition>
                        <span id="notif-count" x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                    </span>
                </button>

                <!-- Dropdown Panel -->
                <div x-show="open" @click.outside="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute right-0 top-12 w-80 bg-white rounded-xl shadow-elevated border border-vibe-outline-variant/20 z-50 overflow-hidden" style="display:none">
                    
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-vibe-outline-variant/15 flex items-center justify-between bg-vibe-bg">
                        <h4 class="text-sm font-bold text-vibe-on-surface">Notifikasi</h4>
                        <button @click="markAllRead()" x-show="unreadCount > 0" class="text-xs font-semibold text-vibe-primary hover:underline">Tandai dibaca</button>
                    </div>

                    <!-- Notification List -->
                    <div class="max-h-80 overflow-y-auto">
                        <template x-if="items.length === 0">
                            <div class="py-8 text-center text-vibe-on-surface-variant">
                                <div class="text-2xl mb-2">🔔</div>
                                <p class="text-sm font-medium">Belum ada notifikasi</p>
                            </div>
                        </template>
                        <template x-for="item in items" :key="item.id">
                            <div class="px-4 py-3 border-b border-vibe-outline-variant/10 hover:bg-vibe-bg transition-colors" :class="item.isNew ? 'bg-vibe-primary-light/30' : ''">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5" :class="userRole === 'waiter' ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600'">
                                        <template x-if="userRole === 'waiter'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        </template>
                                        <template x-if="userRole !== 'waiter'">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path></svg>
                                        </template>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-vibe-on-surface" x-text="item.title"></p>
                                        <p class="text-xs text-vibe-on-surface-variant mt-0.5" x-html="item.sub"></p>
                                        <p class="text-[11px] text-vibe-outline mt-1" x-text="item.waktu"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-2.5 bg-vibe-bg border-t border-vibe-outline-variant/15">
                        <a href="<?= BASE_URL ?>/modules/admin/laporan.php" class="text-xs font-semibold text-vibe-primary hover:underline">Lihat semua transaksi →</a>
                    </div>
                </div>
            </div>


            <!-- Grid Icon -->
            <button class="w-9 h-9 rounded-lg flex items-center justify-center text-vibe-on-surface-variant hover:bg-vibe-surface-container transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </button>

            <!-- User Avatar -->
            <button class="w-9 h-9 rounded-full bg-vibe-surface-container text-vibe-primary flex items-center justify-center font-bold text-xs hover:ring-2 hover:ring-vibe-primary/20 transition-all">
                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
            </button>

            <!-- Ghost button: Add Note -->
            <a href="#" class="hidden md:flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-vibe-primary border border-vibe-primary rounded-lg hover:bg-vibe-primary/5 transition-colors">
                Add Note
            </a>

            <!-- Solid button: Check Out -->
            <a href="<?= BASE_URL ?>/modules/kasir/index.php" class="hidden md:flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-vibe-error rounded-lg hover:bg-red-700 transition-colors shadow-sm">
                Check Out
            </a>
        </div>
    </header>
    
    <!-- Page Content Scrollable -->
    <main class="flex-1 overflow-y-auto p-4 md:p-8 bg-vibe-bg">

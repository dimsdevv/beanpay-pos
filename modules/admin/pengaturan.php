<?php
$page_title = 'Pengaturan Sistem';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole(['admin']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="max-w-4xl">

    <div class="mb-8">
        <h1 class="text-2xl font-display font-bold text-vibe-on-surface tracking-tight">Pengaturan Sistem</h1>
        <p class="text-sm text-vibe-on-surface-variant mt-1">Konfigurasi parameter toko, pajak, cetak, dan nomor pesanan.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <a href="pengaturan_pajak.php"
           class="group bg-white border border-vibe-outline-variant rounded-xl p-5 hover:border-vibe-on-surface transition-all active:scale-[0.99] flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary/10 flex items-center justify-center shrink-0 group-hover:bg-vibe-primary/20 transition-colors">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-vibe-on-surface group-hover:text-vibe-primary transition-colors">Pajak &amp; Biaya Layanan</div>
                <p class="text-sm text-vibe-on-surface-variant mt-0.5">Atur persentase Pajak PB1 dan Service Charge untuk transaksi.</p>
            </div>
            <svg class="w-5 h-5 text-vibe-outline-variant group-hover:text-vibe-on-surface transition-colors shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="pengaturan_toko.php"
           class="group bg-white border border-vibe-outline-variant rounded-xl p-5 hover:border-vibe-on-surface transition-all active:scale-[0.99] flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary/10 flex items-center justify-center shrink-0 group-hover:bg-vibe-primary/20 transition-colors">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-vibe-on-surface group-hover:text-vibe-primary transition-colors">Informasi Toko</div>
                <p class="text-sm text-vibe-on-surface-variant mt-0.5">Nama, alamat, dan nomor telepon yang muncul di struk pembayaran.</p>
            </div>
            <svg class="w-5 h-5 text-vibe-outline-variant group-hover:text-vibe-on-surface transition-colors shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="pengaturan_cetak.php"
           class="group bg-white border border-vibe-outline-variant rounded-xl p-5 hover:border-vibe-on-surface transition-all active:scale-[0.99] flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary/10 flex items-center justify-center shrink-0 group-hover:bg-vibe-primary/20 transition-colors">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-vibe-on-surface group-hover:text-vibe-primary transition-colors">Cetak Struk</div>
                <p class="text-sm text-vibe-on-surface-variant mt-0.5">Atur apakah struk langsung tercetak otomatis setelah bayar.</p>
            </div>
            <svg class="w-5 h-5 text-vibe-outline-variant group-hover:text-vibe-on-surface transition-colors shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

        <a href="pengaturan_stok.php"
           class="group bg-white border border-vibe-outline-variant rounded-xl p-5 hover:border-vibe-on-surface transition-all active:scale-[0.99] flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-vibe-primary/10 flex items-center justify-center shrink-0 group-hover:bg-vibe-primary/20 transition-colors">
                <svg class="w-5 h-5 text-vibe-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-vibe-on-surface group-hover:text-vibe-primary transition-colors">Stok &amp; Nomor Pesanan</div>
                <p class="text-sm text-vibe-on-surface-variant mt-0.5">Ambang batas peringatan stok dan awalan nomor pesanan.</p>
            </div>
            <svg class="w-5 h-5 text-vibe-outline-variant group-hover:text-vibe-on-surface transition-colors shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

    </div>

    <div class="mt-8 border-t border-vibe-outline-variant pt-8">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="font-bold text-vibe-on-surface text-sm">Zona Berbahaya</div>
                <p class="text-xs text-vibe-on-surface-variant">Tindakan destruktif yang tidak dapat dibatalkan.</p>
            </div>
        </div>
        <a href="pengaturan_reset.php"
           class="group flex items-center gap-4 bg-white border border-red-200 rounded-xl p-5 hover:border-red-400 transition-all active:scale-[0.99]">
            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-vibe-on-surface group-hover:text-red-600 transition-colors">Reset Data Transaksi</div>
                <p class="text-sm text-vibe-on-surface-variant mt-0.5">Hapus semua pesanan, pembayaran, shift, audit trail, dan bukti transfer.</p>
            </div>
            <svg class="w-5 h-5 text-vibe-outline-variant group-hover:text-red-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

<style>
@media (hover:hover) and (pointer:fine) {
    .group:hover .group-hover\:text-vibe-primary { color: #004ac6; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

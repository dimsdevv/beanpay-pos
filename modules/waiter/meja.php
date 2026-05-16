<?php
$page_title = 'Status Meja';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['waiter', 'admin']);

require_once __DIR__ . '/../../includes/header.php';
$mejas = $pdo->query("
    SELECT m.*, p.nomor_pesanan, p.id as pesanan_id, p.waktu_pesan, p.total_harga, p.status_pesanan
    FROM meja m
    LEFT JOIN pesanan p ON m.id = p.meja_id AND p.status_pesanan NOT IN ('dibayar','dibatalkan')
    ORDER BY m.nomor_meja
")->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-extrabold text-theme-evergreen">Table Status</h1>
        <p class="text-gray-500 mt-0.5">Overview of all tables. Click an occupied table to view its order.</p>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4">
        <div class="flex items-center gap-2 text-sm font-medium text-gray-600"><div class="w-3 h-3 rounded-full bg-theme-sage"></div> Available</div>
        <div class="flex items-center gap-2 text-sm font-medium text-gray-600"><div class="w-3 h-3 rounded-full bg-amber-400"></div> Occupied / In Progress</div>
        <div class="flex items-center gap-2 text-sm font-medium text-gray-600"><div class="w-3 h-3 rounded-full bg-blue-400"></div> Cooking</div>
        <div class="flex items-center gap-2 text-sm font-medium text-gray-600"><div class="w-3 h-3 rounded-full bg-gray-300"></div> Ready to Pay</div>
    </div>

    <!-- Table Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
        <?php foreach($mejas as $meja): ?>
        <?php
        $occupied = $meja['status'] === 'terisi';
        $statusColor = 'border-gray-100 bg-white';
        $dotColor = 'bg-gray-200';
        if ($occupied) {
            if ($meja['status_pesanan'] === 'pending' || $meja['status_pesanan'] === null) {
                $statusColor = 'border-amber-300 bg-amber-50'; $dotColor = 'bg-amber-400';
            } elseif ($meja['status_pesanan'] === 'diproses') {
                $statusColor = 'border-blue-300 bg-blue-50'; $dotColor = 'bg-blue-400 animate-pulse';
            } elseif ($meja['status_pesanan'] === 'selesai') {
                $statusColor = 'border-gray-300 bg-gray-50'; $dotColor = 'bg-gray-400';
            }
        } else {
            $statusColor = 'border-theme-sage/30 bg-theme-bg hover:border-theme-sage'; $dotColor = 'bg-theme-sage';
        }
        ?>
        <?php if($occupied && $meja['pesanan_id']): ?>
        <a href="<?= BASE_URL ?>/modules/waiter/order.php" class="group relative aspect-[3/4] rounded-2xl border-2 <?= $statusColor ?> flex flex-col items-center justify-center p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer overflow-hidden">
        <?php else: ?>
        <a href="<?= BASE_URL ?>/modules/waiter/order.php" class="group relative aspect-[3/4] rounded-2xl border-2 <?= $statusColor ?> flex flex-col items-center justify-center p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer overflow-hidden">
        <?php endif; ?>

            <!-- Status dot -->
            <div class="absolute top-3 right-3 w-3 h-3 rounded-full <?= $dotColor ?>"></div>

            <!-- Table icon -->
            <svg class="w-12 h-12 mb-3 <?= $occupied ? 'text-gray-500' : 'text-theme-sage' ?> transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>

            <div class="font-extrabold text-theme-evergreen text-lg mb-1"><?= $meja['nomor_meja'] ?></div>

            <?php if($occupied): ?>
                <div class="text-xs font-semibold text-gray-500 text-center truncate w-full">
                    <?= $meja['status_pesanan'] === 'selesai' ? '✓ Ready to Pay' : ucfirst($meja['status_pesanan'] ?? 'In use') ?>
                </div>
                <?php if($meja['waktu_pesan']): ?>
                <div class="text-[10px] text-gray-400 mt-1">
                    <?php
                    $diff = floor((time() - strtotime($meja['waktu_pesan'])) / 60);
                    echo $diff . ' min';
                    ?>
                </div>
                <?php endif; ?>
                <?php if($meja['total_harga']): ?>
                <div class="text-xs font-bold text-theme-leaf mt-1"><?= formatRupiah($meja['total_harga']) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-xs font-bold text-theme-sage">Available</div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Stats Summary -->
    <?php
    $totalMeja = count($mejas);
    $terisi = count(array_filter($mejas, fn($m) => $m['status'] === 'terisi'));
    $kosong = $totalMeja - $terisi;
    $occupancyRate = $totalMeja > 0 ? round(($terisi / $totalMeja) * 100) : 0;
    ?>
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm text-center">
            <div class="text-3xl font-extrabold text-theme-sage"><?= $kosong ?></div>
            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Available</div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm text-center">
            <div class="text-3xl font-extrabold text-amber-500"><?= $terisi ?></div>
            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Occupied</div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm text-center">
            <div class="text-3xl font-extrabold text-theme-evergreen"><?= $occupancyRate ?>%</div>
            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Occupancy</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

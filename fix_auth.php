<?php
$files = [
    __DIR__ . '/modules/admin/dashboard.php',
    __DIR__ . '/modules/admin/inventaris.php',
    __DIR__ . '/modules/admin/laporan.php',
    __DIR__ . '/modules/admin/menu.php',
    __DIR__ . '/modules/admin/users.php',
    __DIR__ . '/modules/waiter/meja.php',
    __DIR__ . '/modules/waiter/order.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // We want to ensure requireRole has the dependencies before it.
    // If we see requireRole(...) without auth.php above it, we insert it.
    // Easiest is to search for requireRole and replace it with includes, but only if not already present in the block.
    
    $replacement = "require_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../includes/auth.php';\n$0";
    
    // Regex logic: Find requireRole. If the file doesn't already contain require_once for auth.php before it, replace it.
    // Actually, to be safe, just replace `requireRole` with the requires, IF `auth.php` isn't in the file yet, OR if it's only in `header.php`.
    // Let's just check if `auth.php` is explicitly required in the file.
    if (strpos($content, "'/../../includes/auth.php'") === false) {
        $content = preg_replace('/^requireRole\(/m', "require_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../includes/auth.php';\nrequireRole(", $content);
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    }
}

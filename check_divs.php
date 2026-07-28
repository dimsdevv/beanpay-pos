<?php
// Quick script to check div balance in the rendered keuangan.php output
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['nama_lengkap'] = 'Admin';

ob_start();
include __DIR__ . '/modules/admin/keuangan.php';
$html = ob_get_clean();

// Count open/close divs
preg_match_all('/<div[\s>]/i', $html, $openDivs);
preg_match_all('/<\/div>/i', $html, $closeDivs);

$openCount = count($openDivs[0]);
$closeCount = count($closeDivs[0]);

echo "Open <div>: $openCount\n";
echo "Close </div>: $closeCount\n";
echo "Balance: " . ($openCount - $closeCount) . " (should be 0)\n\n";

// Check if x-data div exists
if (preg_match('/x-data="keuanganApp\(\)"/', $html)) {
    echo "✓ x-data=\"keuanganApp()\" FOUND in rendered HTML\n";
} else {
    echo "✗ x-data=\"keuanganApp()\" NOT FOUND in rendered HTML\n";
}

// Check if openAdd exists in script
if (strpos($html, 'openAdd') !== false) {
    echo "✓ openAdd() method FOUND\n";
} else {
    echo "✗ openAdd() method NOT FOUND\n";
}

// Check Alpine.data registration
if (strpos($html, "Alpine.data('keuanganApp'") !== false) {
    echo "✓ Alpine.data('keuanganApp') registration FOUND\n";
} else {
    echo "✗ Alpine.data('keuanganApp') registration NOT FOUND\n";
}

// Check showForm modal
if (strpos($html, 'x-show="showForm"') !== false) {
    echo "✓ showForm modal FOUND\n";
} else {
    echo "✗ showForm modal NOT FOUND\n";
}

// Check if showForm modal is INSIDE x-data scope
$xdataPos = strpos($html, 'x-data="keuanganApp()"');
$showFormPos = strpos($html, 'x-show="showForm"');
if ($xdataPos !== false && $showFormPos !== false) {
    // Find the closing div for x-data
    // Count nested divs from x-data position
    $afterXdata = substr($html, $xdataPos);
    $depth = 0;
    $pos = 0;
    $xdataClosePos = null;
    
    // Find the first > after x-data
    $firstGt = strpos($afterXdata, '>');
    $pos = $firstGt + 1;
    $depth = 1; // we're inside the x-data div
    
    while ($depth > 0 && $pos < strlen($afterXdata)) {
        $nextOpen = strpos($afterXdata, '<div', $pos);
        $nextClose = strpos($afterXdata, '</div>', $pos);
        
        if ($nextOpen === false && $nextClose === false) break;
        
        if ($nextOpen !== false && ($nextClose === false || $nextOpen < $nextClose)) {
            $depth++;
            $pos = $nextOpen + 4;
        } else {
            $depth--;
            if ($depth === 0) {
                $xdataClosePos = $xdataPos + $nextClose;
            }
            $pos = $nextClose + 6;
        }
    }
    
    if ($xdataClosePos !== null) {
        $relShowForm = $showFormPos - $xdataPos;
        $relClose = $xdataClosePos - $xdataPos;
        if ($relShowForm < $relClose) {
            echo "✓ showForm modal IS INSIDE x-data scope\n";
        } else {
            echo "✗ showForm modal is OUTSIDE x-data scope (modal at $showFormPos, x-data closes at $xdataClosePos)\n";
        }
        echo "  x-data div closes at char position $xdataClosePos of full HTML\n";
        
        // Show what's around the closing position
        $snippet = substr($html, max(0, $xdataClosePos - 100), 300);
        echo "  Context around x-data closing:\n";
        echo "  " . str_replace("\n", "\n  ", $snippet) . "\n";
    } else {
        echo "✗ Could not find closing </div> for x-data (UNCLOSED!)\n";
    }
}

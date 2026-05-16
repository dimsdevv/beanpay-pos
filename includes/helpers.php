<?php
/**
 * BeanPay - Helpers
 */

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatDate($dateString) {
    return date('d M Y H:i', strtotime($dateString));
}

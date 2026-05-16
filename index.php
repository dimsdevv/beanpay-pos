<?php
/**
 * BeanPay - Entry Point
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit;
}

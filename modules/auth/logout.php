<?php
require_once __DIR__ . '/../../config/database.php';

$_SESSION = [];
session_destroy();
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;

<?php
require_once __DIR__ . '/../../config/database.php';

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;

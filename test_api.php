<?php
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['role'] = 'waiter';
$_SESSION['username'] = 'waiter1';
$_SESSION['nama_lengkap'] = 'Waiter Test';
session_write_close();

// mock GET
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['waiter_id'] = 3;
$_GET['last_id'] = 0;
ob_start();
require 'api/notif_waiter.php';
$output = ob_get_clean();
echo $output;

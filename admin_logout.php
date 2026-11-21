<?php
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/audit.php';
if (!empty($_SESSION['admin_id'])) {
    audit_log($conn, 'Admin logout', 'Admin signed out.');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: admin_login.php');
exit();

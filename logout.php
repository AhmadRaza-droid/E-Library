<?php
require_once __DIR__ . '/session_init.php';
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/activity.php';
    website_activity_log($conn, 'User logout', 'User signed out.', (int)$_SESSION['user_id'], (string)($_SESSION['fullname'] ?? 'User'));
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit();

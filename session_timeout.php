<?php
require_once __DIR__ . '/session_init.php';

$timeout = 1800; // 30 minutes of inactivity (only applies to logged-in sessions)
$logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);

if ($logged_in) {
    $was_admin = isset($_SESSION['admin_id']) && !isset($_SESSION['user_id']);

    // ---- idle timeout ----
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
        session_unset();
        session_destroy();
        header('Location: ' . ($was_admin ? 'admin_login.php?timeout=1' : 'login.php?timeout=1'));
        exit();
    }

    // ---- browser fingerprint (User-Agent only; IP binding broke mobile users) ----
    $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!isset($_SESSION['UA_HASH'])) {
        $_SESSION['UA_HASH'] = $ua;
    } elseif (!hash_equals($_SESSION['UA_HASH'], $ua)) {
        session_unset();
        session_destroy();
        header('Location: ' . ($was_admin ? 'admin_login.php?security=1' : 'login.php?security=1'));
        exit();
    }

    // ---- rotate session id every 30 minutes ----
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

<?php
// ========== ERROR HANDLING (production: log, never display) ==========
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');

// Make mysqli return false instead of throwing (PHP 8.1+ throws by default)
mysqli_report(MYSQLI_REPORT_OFF);

// ========== SESSION ==========
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/session_timeout.php';   // idle timeout for logged-in users on EVERY page

// ========== CONFIG (secrets live OUTSIDE git) ==========
// Priority: config.local.php  ->  environment variables (DB_HOST, DB_USER, ...)
$GLOBALS['__app_cfg'] = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $loaded = require __DIR__ . '/config.local.php';
    if (is_array($loaded)) {
        $GLOBALS['__app_cfg'] = $loaded;
    }
}
function cfg($key, $default = null){
    if (isset($GLOBALS['__app_cfg'][$key]) && $GLOBALS['__app_cfg'][$key] !== '') {
        return $GLOBALS['__app_cfg'][$key];
    }
    $env = getenv(strtoupper($key));
    return ($env !== false && $env !== '') ? $env : $default;
}

if (!cfg('db_host') || !cfg('db_user') || !cfg('db_name')) {
    error_log('Configuration missing: create config.local.php (see config.local.example.php)');
    http_response_code(500);
    die('Site is not configured yet. Please contact the administrator.');
}

// ========== DATABASE CONNECTION ==========
$conn = @mysqli_connect(cfg('db_host'), cfg('db_user'), cfg('db_pass', ''), cfg('db_name'));
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    die('Sorry, we are experiencing technical difficulties. Please try again later.');
}
if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log('Failed to set charset: ' . mysqli_error($conn));
}

// ========== TIMEZONE ==========
date_default_timezone_set('Asia/Karachi');

// ========== SECURITY HEADERS ==========
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0'); // legacy header, modern browsers: disabled is safer
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}

// ========== SITE URL (host header sanitised) ==========
$site_url = 'https://' . preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

// ========== HELPERS ==========
require_once __DIR__ . '/helpers.php';

// ========== GLOBAL CSRF PROTECTION FOR EVERY POST ==========
// (endpoints that must be public can define('SKIP_CSRF', true) before including config.php)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !defined('SKIP_CSRF')) {
    csrf_require();
}

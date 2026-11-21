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
require_once __DIR__ . '/Database.php';
$conn = Database::connect(cfg('db_host'), cfg('db_user'), cfg('db_pass', ''), cfg('db_name'));
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    die('Sorry, we are experiencing technical difficulties. Please try again later.');
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
require_current_session($conn);

// ========== ADMIN-CONTROLLED MAINTENANCE MODE ==========
$maintenanceMode = (string)get_setting($conn, 'maintenance_mode', '0') === '1';
$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$maintenanceExempt = [
    'admin_login.php', 'admin_logout.php', 'admin_forgot_password.php',
    'admin_dashboard.php', 'admin_settings.php', 'admin_audit_logs.php',
    'admin_change_password.php', 'admin_backup.php', 'admin_analytics.php',
    'admin_resources.php', 'auto_scrape_books.php', 'upload_book.php',
    'update_request_status.php', 'delete_review.php'
];
if ($maintenanceMode && empty($_SESSION['admin_id']) && !in_array($scriptName, $maintenanceExempt, true)) {
    http_response_code(503);
    header('Retry-After: 3600');
    $maintenanceTitle = e(site_name($conn));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Under Maintenance</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#edf4f1;font-family:Segoe UI,Arial,sans-serif;color:#173b56}.box{max-width:560px;margin:20px;padding:42px 30px;background:white;border-radius:18px;text-align:center;box-shadow:0 12px 40px #173b5618}.icon{font-size:54px}.box h1{margin:14px 0}.box p{line-height:1.7;color:#607286}</style></head><body><main class="box"><div class="icon">🛠️</div><h1>' . $maintenanceTitle . ' is under maintenance</h1><p>We are making improvements to the library. Please check back soon.</p></main></body></html>';
    exit();
}

// ========== GLOBAL CSRF PROTECTION FOR EVERY POST ==========
// (endpoints that must be public can define('SKIP_CSRF', true) before including config.php)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !defined('SKIP_CSRF')) {
    csrf_require();
}

// ========== WEBSITE ACTIVITY TRACKING ==========
require_once __DIR__ . '/activity.php';
track_website_request($conn);

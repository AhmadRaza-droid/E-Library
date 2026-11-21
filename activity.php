<?php
/**
 * Privacy-conscious website activity tracking.
 * Records PHP page visits and POST endpoint actions, but never stores form bodies,
 * passwords, OTPs, reset tokens, email message content, or full query strings.
 */
function website_activity_log($conn, $action, $details = '', $userId = null, $userName = null) {
    $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    $userName = $userName ?? (string)($_SESSION['fullname'] ?? $_SESSION['email'] ?? (!empty($_SESSION['admin_id']) ? ($_SESSION['admin'] ?? 'Admin') : 'Guest'));
    $actorType = !empty($_SESSION['admin_id']) ? 'admin' : ($userId > 0 ? 'user' : 'guest');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) $ip = 'unknown';
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 500);
    $sessionKey = session_id() !== '' ? hash('sha256', session_id()) : '';
    $action = mb_substr(trim((string)$action), 0, 120);
    $details = mb_substr(trim((string)$details), 0, 1500);
    return db_exec($conn,
        "INSERT INTO website_activity_logs (user_id, user_name, actor_type, action, details, ip_address, page, http_method, user_agent, session_key, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        'isssssssss',
        [(int)$userId, mb_substr($userName, 0, 150), $actorType, $action, $details, $ip, $page, $method, $ua, $sessionKey]
    ) !== false;
}

function track_website_request($conn) {
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $labels = [
        'index.php'=>'Viewed homepage','books.php'=>'Browsed/search books','read_book.php'=>'Opened book reader',
        'view_book.php'=>'Viewed book details','download_book.php'=>'Requested book download',
        'profile.php'=>'Viewed profile','login.php'=>'Visited login page','register.php'=>'Visited registration page',
        'forgot_password.php'=>'Visited password recovery','verify_otp.php'=>'Visited OTP verification',
        'add_bookmark.php'=>'Saved or updated bookmark','remove_bookmark.php'=>'Removed bookmark',
        'add_review.php'=>'Submitted or updated book review','request_book.php'=>'Submitted book request',
        'update_progress.php'=>'Updated reading progress','clear_all_history.php'=>'Cleared reading history',
        'contact.php'=>'Visited contact page','student_resources.php'=>'Visited student resources',
        'admin_dashboard.php'=>'Visited admin dashboard','admin_settings.php'=>'Visited admin settings',
        'admin_audit_logs.php'=>'Viewed admin audit log','admin_user_activity.php'=>'Viewed user activity log'
    ];
    $action = $labels[$page] ?? (($method === 'POST' ? 'Submitted action: ' : 'Visited page: ') . $page);
    if ($method === 'POST') $action = 'POST action: ' . $page;

    $parts = [];
    foreach (['id','book_id','request_id','bookmark_id','page','progress'] as $key) {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        if (is_scalar($value) && (string)$value !== '' && preg_match('/^\d{1,10}$/', (string)$value)) {
            $parts[] = $key . '=' . (int)$value;
        }
    }
    if (isset($_GET['category']) && is_string($_GET['category'])) {
        $parts[] = 'category=' . mb_substr(preg_replace('/[^\pL\pN _.-]/u', '', $_GET['category']), 0, 80);
    }
    if (isset($_GET['search']) && is_string($_GET['search'])) {
        $parts[] = 'search=' . mb_substr(preg_replace('/[^\pL\pN _.-]/u', '', $_GET['search']), 0, 100);
    }
    website_activity_log($conn, $action, implode(' | ', $parts));
}

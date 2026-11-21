<?php
// =====================================================================
//  Shared helpers (replaces the old sanitize.php + db_prepare.php)
// =====================================================================

// ---------- OUTPUT ESCAPING ----------
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function escape($v){ return e($v); }

/** Render queued action messages once, inside the page rather than a browser dialog. */
function notification_markup(){
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;
    $messages = $_SESSION['action_messages'] ?? [];
    unset($_SESSION['action_messages']);
    $html = '<link rel="stylesheet" href="notifications.css?v=2"><link rel="stylesheet" href="ilmora-theme.css?v=1">';
    $html .= '<div id="site-notifications" class="site-notifications" aria-label="Notifications" aria-live="polite" aria-relevant="additions">';
    foreach ($messages as $message) {
        $html .= '<div class="site-notice" role="status"><p>' . e($message) . '</p>'
            . '<button type="button" class="notice-dismiss" aria-label="Dismiss notification">&times;</button></div>';
    }
    return $html . '</div><script src="notifications.js?v=1"></script>';
}

/** Compatibility helper: keep existing messages, redirects and validation exits. */
function js_alert_redirect($msg, $url = null, $stop = true){
    $_SESSION['action_messages'][] = (string)$msg;
    if ($url !== null) {
        if (!headers_sent() && $stop) {
            header('Location: ' . $url, true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 303 : 302);
        } else {
            $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
            echo '<script>window.location.href=' . json_encode((string)$url, $flags) . ';</script>';
            echo '<noscript><a href="' . e($url) . '">Continue</a></noscript>';
        }
    } elseif ($stop) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Notification</title></head><body>'
            . notification_markup() . '</body></html>';
    }
    if ($stop) exit();
}

function redirect($url){ header('Location: ' . $url); exit(); }

// ---------- CSRF ----------
function csrf_token(){
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(){
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
function csrf_verify(){
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}
function csrf_require(){
    if (!csrf_verify()) {
        http_response_code(419);
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')) {
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Security token invalid. Refresh the page.']));
        }
        die('Security token is invalid or has expired. Please go back, refresh the page and try again.');
    }
}

// ---------- DATABASE (prepared statements) ----------
/** SELECT -> mysqli_result|false ; write queries -> true|false */
function db_query($conn, $sql, $types = '', $params = []){
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log('SQL prepare failed: ' . $conn->error); return false; }
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if (!$stmt->execute()) { error_log('SQL execute failed: ' . $stmt->error); $stmt->close(); return false; }
    $res = $stmt->get_result();
    $stmt->close();
    return ($res === false) ? true : $res;
}
/** Write query -> affected rows (int) or false */
function db_exec($conn, $sql, $types = '', $params = []){
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log('SQL prepare failed: ' . $conn->error); return false; }
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if (!$stmt->execute()) { error_log('SQL execute failed: ' . $stmt->error); $stmt->close(); return false; }
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}
function db_row($conn, $sql, $types = '', $params = []){
    $r = db_query($conn, $sql, $types, $params);
    return ($r instanceof mysqli_result) ? ($r->fetch_assoc() ?: null) : null;
}
function db_all($conn, $sql, $types = '', $params = []){
    $r = db_query($conn, $sql, $types, $params);
    return ($r instanceof mysqli_result) ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function db_value($conn, $sql, $types = '', $params = [], $default = null){
    $row = db_row($conn, $sql, $types, $params);
    return $row ? reset($row) : $default;
}

/** Delete an admin-managed record and its existing dependent records atomically. */
function admin_delete_record($conn, $kind, $id){
    $relations = [
        'book' => ['books', 'book_id', ['favorites', 'reviews', 'recently_viewed', 'bookmarks', 'reading_progress']],
        'user' => ['users', 'user_id', ['favorites', 'recently_viewed', 'reviews', 'bookmarks', 'reading_progress', 'book_requests']],
    ];
    if (!isset($relations[$kind]) || (int)$id <= 0 || !$conn->begin_transaction()) return false;
    [$parent, $column, $children] = $relations[$kind];
    $result = db_query($conn, "SELECT id FROM `$parent` WHERE id=? FOR UPDATE", 'i', [(int)$id]);
    if (!$result instanceof mysqli_result || !$result->fetch_assoc()) {
        if ($result instanceof mysqli_result) $result->free();
        $conn->rollback();
        return false;
    }
    $result->free();
    foreach ($children as $table) {
        if (db_exec($conn, "DELETE FROM `$table` WHERE `$column`=?", 'i', [(int)$id]) === false) {
            $conn->rollback();
            return false;
        }
    }
    if (db_exec($conn, "DELETE FROM `$parent` WHERE id=?", 'i', [(int)$id]) !== 1 || !$conn->commit()) {
        $conn->rollback();
        return false;
    }
    return true;
}

// ---------- SETTINGS ----------
function get_settings($conn){
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all($conn, "SELECT setting_key, setting_value FROM settings") as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache;
}
function get_setting($conn, $key, $default = null){
    $s = get_settings($conn);
    return array_key_exists($key, $s) ? $s[$key] : $default;
}

// ---------- SITE PRESENTATION ----------
function site_name($conn){
    return get_setting($conn, 'site_name', "Book's Library");
}

function site_footer($conn){
    $footer = get_setting($conn, 'footer_text', '');
    $defaults = ["© 2026 Book's Library. All Rights Reserved.", '© 2026 University Library. All Rights Reserved.'];
    return $footer !== '' && !in_array($footer, $defaults, true)
        ? $footer : '© ' . date('Y') . ' ' . site_name($conn) . '. All Rights Reserved.';
}

function render_site_nav($conn, $active){
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $signedIn = $userId || $adminId;
    $isAdmin = $signedIn && (int)db_value($conn,
        'SELECT is_admin FROM users WHERE id=?', 'i', [$userId ?: $adminId], 0) === 1;
    $links = ['index.php' => 'Home', 'books.php' => 'Books', 'resources.php' => 'Study Materials'];
    if ($signedIn) {
        if ($userId) $links['profile.php'] = 'Profile';
        if ($isAdmin) $links['admin_dashboard.php'] = 'Admin Panel';
        $links[$userId ? 'logout.php' : 'admin_logout.php'] = 'Logout';
    } else {
        $links['login.php'] = 'Login';
        $links['register.php'] = 'Register';
    }
    $links['contact.php'] = 'Contact';
    $links['about.php'] = 'About';
    echo '<nav class="site-nav" aria-label="Main navigation"><a class="logo" href="index.php"><svg class="brand-mark" viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="14" fill="#e6f4ee"/><path d="M24 41C11 40 6 28 8 13c13 0 19 10 16 28z" fill="#52d1a6"/><path d="M24 41c13-1 18-13 16-28-13 0-19 10-16 28z" fill="#1f7a63"/><circle cx="24" cy="9" r="3.5" fill="#ffb08a"/></svg>' . e(site_name($conn)) . '</a>'
        . '<button type="button" class="site-nav-toggle" aria-controls="site-nav-links" aria-expanded="false" aria-label="Open menu">☰ <span>Menu</span></button>'
        . '<ul id="site-nav-links">';
    foreach ($links as $href => $label) {
        $selected = $href === $active ? ' class="active" aria-current="page"' : '';
        echo '<li><a href="' . e($href) . '"' . $selected . '>' . e($label) . '</a></li>';
    }
    echo '<li><button type="button" onclick="toggleDarkMode()" class="dark-btn">☾ Dark</button></li></ul></nav>';
    $libraryHref = $signedIn && $userId ? 'profile.php' : ($signedIn ? 'admin_dashboard.php' : 'login.php');
    echo '<div class="mobile-bar" role="navigation" aria-label="Quick navigation">';
    foreach ([['index.php','⌂','Home'], ['books.php','▤','Books'], [$libraryHref,'♧','Library'], ['contact.php','✉','Contact']] as $item) {
        $current = $active === $item[0] ? ' aria-current="page"' : '';
        echo '<a href="' . e($item[0]) . '"' . $current . '><span aria-hidden="true">' . e($item[1]) . '</span>' . e($item[2]) . '</a>';
    }
    echo '<button type="button" class="mobile-menu" aria-controls="site-nav-links" aria-expanded="false"><span aria-hidden="true">☰</span>Menu</button></div>'
        . '<script src="site-nav.js?v=2" defer></script>';
}

// ---------- VALIDATION ----------
function validate_email($email){ return filter_var($email, FILTER_VALIDATE_EMAIL); }

/** returns null if OK, otherwise an error message */
function password_error($password){
    if (strlen($password) < 8)             return 'Password must be at least 8 characters long!';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least 1 uppercase letter!';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least 1 lowercase letter!';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least 1 number!';
    if (strlen($password) > 200)           return 'Password is too long!';
    return null;
}
function validate_password($password){ return password_error($password) === null; }

// ---------- PASSWORDS (supports one-time migration of legacy plain-text rows) ----------
function password_is_versioned($stored){
    $stored = (string)$stored;
    return str_starts_with($stored, 'v2:') && !empty(password_get_info(substr($stored, 3))['algo']);
}
function password_is_hashed($stored){
    if (password_is_versioned($stored)) return true;
    $info = password_get_info((string)$stored);
    return !empty($info['algo']);
}
function verify_password_value($plain, $stored){
    $stored = (string)$stored;
    if ($stored === '') return false;
    if (password_is_versioned($stored)) {
        return password_verify(base64_encode(hash('sha384', (string)$plain, true)), substr($stored, 3));
    }
    if (password_is_hashed($stored)) return password_verify($plain, $stored);
    // legacy plain-text value: constant-time compare, caller must upgrade it to a hash
    return hash_equals($stored, (string)$plain);
}
function password_upgrade_needed($stored){
    return !password_is_versioned($stored)
        || password_needs_rehash(substr((string)$stored, 3), PASSWORD_DEFAULT);
}
// Bcrypt uses at most 72 bytes; pre-hash so all accepted password bytes matter.
function hash_password_value($plain){
    return 'v2:' . password_hash(base64_encode(hash('sha384', (string)$plain, true)), PASSWORD_DEFAULT);
}

// Bind authenticated sessions to the current stored password. A password
// change, account deletion, or deployment of this check invalidates old sessions.
function require_current_session($conn){
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if (!$userId && !$adminId) return;
    $id = $userId ?: $adminId;
    $validRoles = !$userId || !$adminId || $userId === $adminId;
    $result = db_query($conn, "SELECT password, is_verified FROM users WHERE id=?", 'i', [$id]);
    if (!$result instanceof mysqli_result) {
        http_response_code(503);
        die('Sorry, we are experiencing technical difficulties. Please try again later.');
    }
    $user = $result->fetch_assoc();
    $result->free();
    $fingerprint = $_SESSION['auth_password_hash'] ?? null;
    if (!$validRoles || !$user || (int)$user['is_verified'] !== 1 || !is_string($fingerprint)
        || !hash_equals($fingerprint, hash('sha256', $user['password']))) {
        $wasAdmin = $adminId && !$userId;
        session_unset();
        session_destroy();
        redirect($wasAdmin ? 'admin_login.php?security=1' : 'login.php?security=1');
    }
}

// ---------- RATE LIMITING (file based, works on shared hosting) ----------
function client_ip(){ return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function _rl_file($key){
    // Separate private directories for each deployment and effective OS user.
    static $dir = null;
    if ($dir === null) {
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : false;
        if ($uid === false) {
            $probe = @tempnam(sys_get_temp_dir(), 'bl_uid_');
            if ($probe === false) { $dir = false; return false; }
            $uid = @fileowner($probe);
            @unlink($probe);
            if ($uid === false) { $dir = false; return false; }
        }
        $candidate = sys_get_temp_dir() . '/bl_rl_' . hash('sha256', __DIR__ . ':' . $uid);
        if (!is_dir($candidate) && !@mkdir($candidate, 0700, true) && !is_dir($candidate)) { $dir = false; return false; }
        clearstatcache(true, $candidate);
        $mode = @fileperms($candidate);
        if (is_link($candidate) || $mode === false || ($mode & 0077) !== 0 || !is_writable($candidate)) { $dir = false; return false; }
        $dir = $candidate;
    }
    if ($dir === false) return false;
    return $dir . '/' . hash('sha256', $key);
}
function rate_limit_count($key, $window){
    $f = _rl_file($key);
    if ($f === false) return PHP_INT_MAX;
    if (!is_file($f)) return 0;
    $file = @fopen($f, 'r');
    if (!$file || !flock($file, LOCK_SH)) { if ($file) fclose($file); return PHP_INT_MAX; }
    $d = json_decode((string)stream_get_contents($file), true);
    flock($file, LOCK_UN);
    fclose($file);
    if (!is_array($d) || (time() - ($d['start'] ?? 0)) > $window) return 0;
    return (int)($d['count'] ?? 0);
}
function rate_limit_add($key, $window){
    $f = _rl_file($key);
    if ($f === false) return PHP_INT_MAX;
    $file = @fopen($f, 'c+');
    if (!$file || !flock($file, LOCK_EX)) { if ($file) fclose($file); return PHP_INT_MAX; }
    $d = json_decode((string)stream_get_contents($file), true);
    if (!is_array($d) || (time() - ($d['start'] ?? 0)) > $window) { $d = ['start' => time(), 'count' => 0]; }
    $d['count']++;
    rewind($file);
    ftruncate($file, 0);
    fwrite($file, json_encode($d));
    fflush($file);
    flock($file, LOCK_UN);
    fclose($file);
    return $d['count'];
}
function rate_limit_reset($key){
    $f = _rl_file($key);
    if ($f === false) return;
    $file = @fopen($f, 'c+');
    if (!$file || !flock($file, LOCK_EX)) { if ($file) fclose($file); return; }
    rewind($file);
    ftruncate($file, 0);
    fwrite($file, json_encode(['start' => time(), 'count' => 0]));
    fflush($file);
    flock($file, LOCK_UN);
    fclose($file);
}
function rate_limited($key, $limit, $window){ return rate_limit_count($key, $window) >= $limit; }

// ---------- OTP ----------
function generate_otp(){ return (string)random_int(100000, 999999); }

/** Creates + stores a fresh OTP (valid 10 min). Returns the OTP to email. */
function otp_issue($conn, $email){
    $otp = generate_otp();
    $expiry = date('Y-m-d H:i:s', time() + 600);
    $saved = db_exec($conn, "UPDATE users SET otp=?, otp_expiry=? WHERE email=?", 'sss', [$otp, $expiry, $email]);
    if ($saved !== 1) {
        error_log('OTP could not be stored for an account.');
        return false;
    }
    rate_limit_reset('otp_try:' . strtolower($email));
    return $otp;
}
function otp_clear($conn, $email){
    db_exec($conn, "UPDATE users SET otp=NULL, otp_expiry=NULL WHERE email=?", 's', [$email]);
    rate_limit_reset('otp_try:' . strtolower($email));
}
/** returns 'ok' | 'invalid' | 'expired' | 'locked' */
function otp_check($conn, $email, $entered){
    $key = 'otp_try:' . strtolower($email);
    $n = rate_limit_add($key, 600);
    if ($n > 5) { otp_clear($conn, $email); return 'locked'; }
    $entered = trim((string)$entered);
    if (!preg_match('/^\d{6}$/', $entered)) {
        if ($n >= 5) { otp_clear($conn, $email); return 'locked'; }
        return 'invalid';
    }

    $row = db_row($conn, "SELECT otp, otp_expiry FROM users WHERE email=? LIMIT 1", 's', [$email]);
    if (!$row || $row['otp'] === null || $row['otp'] === '') {
        if ($n >= 5) { otp_clear($conn, $email); return 'locked'; }
        return 'invalid';
    }
    if (empty($row['otp_expiry']) || strtotime($row['otp_expiry']) < time()) { return 'expired'; }
    if (!hash_equals((string)$row['otp'], $entered)) {
        if ($n >= 5) { otp_clear($conn, $email); return 'locked'; }
        return 'invalid';
    }
    rate_limit_reset($key);
    return 'ok';
}

// ---------- ADMIN / USER ROLE CHECKS (always re-checked against the DB) ----------
function is_admin_session($conn){
    if (empty($_SESSION['admin_id'])) return false;
    return (int)db_value($conn, "SELECT is_admin FROM users WHERE id=?", 'i', [(int)$_SESSION['admin_id']], 0) === 1;
}
function require_admin($conn){
    if (!is_admin_session($conn)) {
        foreach (['admin', 'admin_id', 'admin_email'] as $k) unset($_SESSION[$k]);
        redirect('admin_login.php');
    }
}
/** true if the *normal* logged-in user has admin rights (used only to show the Admin link) */
function current_user_is_admin($conn){
    if (empty($_SESSION['user_id'])) return false;
    return (int)db_value($conn, "SELECT is_admin FROM users WHERE id=?", 'i', [(int)$_SESSION['user_id']], 0) === 1;
}
function require_login(){
    if (empty($_SESSION['user_id'])) redirect('login.php');
}
function require_post(){
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); die('Method not allowed'); }
}

// ---------- URL SAFETY ----------
/** Only http(s) URLs or our own uploads/ files are allowed. Returns '' if unsafe. */
function safe_url($url){
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('#^uploads/(pdfs|covers)/[A-Za-z0-9._\-]+$#', $url)) return $url;
    $p = parse_url($url);
    if ($p && !empty($p['scheme']) && !empty($p['host']) && in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return $url;
    }
    return '';
}
/** Numeric value = Project Gutenberg book id */
function resolve_read_link($v){
    $v = trim((string)$v);
    if ($v !== '' && ctype_digit($v)) return "https://www.gutenberg.org/cache/epub/$v/pg$v-images.html";
    $parts = parse_url($v);
    if (is_array($parts)
        && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        && in_array(strtolower($parts['host'] ?? ''), ['gutenberg.org', 'www.gutenberg.org'], true)
        && !isset($parts['user']) && !isset($parts['pass'])
        && (!isset($parts['port']) || in_array($parts['port'], [80, 443], true))
        && preg_match('#^/ebooks/([0-9]+)/?$#', $parts['path'] ?? '', $matches)) {
        $id = $matches[1];
        return "https://www.gutenberg.org/cache/epub/$id/pg$id-images.html";
    }
    return safe_url($v);
}
function resolve_download_link($v){
    $v = trim((string)$v);
    if ($v !== '' && ctype_digit($v)) return "https://www.gutenberg.org/ebooks/$v.epub.noimages";
    return safe_url($v);
}

// ---------- FILE UPLOADS ----------
/**
 * Validates + stores an uploaded file. Returns [true, 'relative/path'] or [false, 'error'].
 * $allowed = ['image/jpeg' => 'jpg', ...]  (real MIME is detected from file content)
 */
function save_upload($file, $dir, $allowed, $maxBytes, $prefix = 'f'){
    if (!isset($file['error']) || is_array($file['error'])) return [false, 'Invalid upload.'];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return [false, 'No file selected.'];
    if ($file['error'] !== UPLOAD_ERR_OK)      return [false, 'Upload failed (error ' . (int)$file['error'] . ').'];
    if ($file['size'] > $maxBytes)             return [false, 'File is too large (max ' . $maxBytes . ' bytes).'];
    if (!is_uploaded_file($file['tmp_name']))  return [false, 'Invalid upload.'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return [false, 'File type not allowed.'];

    if (strpos($mime, 'image/') === 0 && @getimagesize($file['tmp_name']) === false) {
        return [false, 'File is not a valid image.'];
    }
    if ($mime === 'application/pdf') {
        $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-') return [false, 'File is not a valid PDF.'];
    }
    $abs = __DIR__ . '/' . trim($dir, '/');
    if (!is_dir($abs) && !@mkdir($abs, 0755, true)) return [false, 'Upload folder is not writable.'];

    $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $abs . '/' . $name)) return [false, 'Could not save the file.'];
    @chmod($abs . '/' . $name, 0644);
    return [true, trim($dir, '/') . '/' . $name];
}

// ---------- MISC ----------
/** escape % and _ so user input can't turn a LIKE search into a wildcard scan */
function like_escape($s){ return addcslashes((string)$s, "%_\\"); }
function clamp_int($v, $min, $max){ $v = (int)$v; return max($min, min($max, $v)); }

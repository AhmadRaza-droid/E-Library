<?php
// =====================================================================
//  Shared helpers (replaces the old sanitize.php + db_prepare.php)
// =====================================================================

// ---------- OUTPUT ESCAPING ----------
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function escape($v){ return e($v); }

/** Safe JS alert (+ optional redirect). Message/URL are JSON-encoded so they can't break out of the script. */
function js_alert_redirect($msg, $url = null, $stop = true){
    $f = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    echo '<script>alert(' . json_encode((string)$msg, $f) . ');';
    if ($url !== null) echo 'window.location.href=' . json_encode((string)$url, $f) . ';';
    echo '</script>';
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
function password_is_hashed($stored){
    $info = password_get_info((string)$stored);
    return !empty($info['algo']);
}
function verify_password_value($plain, $stored){
    $stored = (string)$stored;
    if ($stored === '') return false;
    if (password_is_hashed($stored)) return password_verify($plain, $stored);
    // legacy plain-text value: constant-time compare, caller must upgrade it to a hash
    return hash_equals($stored, (string)$plain);
}
function password_upgrade_needed($stored){
    return !password_is_hashed($stored) || password_needs_rehash((string)$stored, PASSWORD_DEFAULT);
}
function hash_password_value($plain){ return password_hash($plain, PASSWORD_DEFAULT); }

// ---------- RATE LIMITING (file based, works on shared hosting) ----------
function client_ip(){ return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function _rl_file($key){ return sys_get_temp_dir() . '/bl_rl_' . hash('sha256', $key); }
function rate_limit_count($key, $window){
    $f = _rl_file($key);
    if (!is_file($f)) return 0;
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d) || (time() - ($d['start'] ?? 0)) > $window) return 0;
    return (int)($d['count'] ?? 0);
}
function rate_limit_add($key, $window){
    $f = _rl_file($key);
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($d) || (time() - ($d['start'] ?? 0)) > $window) { $d = ['start' => time(), 'count' => 0]; }
    $d['count']++;
    @file_put_contents($f, json_encode($d), LOCK_EX);
    return $d['count'];
}
function rate_limit_reset($key){ $f = _rl_file($key); if (is_file($f)) @unlink($f); }
function rate_limited($key, $limit, $window){ return rate_limit_count($key, $window) >= $limit; }

// ---------- OTP ----------
function generate_otp(){ return (string)random_int(100000, 999999); }

/** Creates + stores a fresh OTP (valid 10 min). Returns the OTP to email. */
function otp_issue($conn, $email){
    $otp = generate_otp();
    $expiry = date('Y-m-d H:i:s', time() + 600);
    db_exec($conn, "UPDATE users SET otp=?, otp_expiry=? WHERE email=?", 'sss', [$otp, $expiry, $email]);
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
    if (rate_limited($key, 5, 600)) { otp_clear($conn, $email); return 'locked'; }
    $entered = trim((string)$entered);
    if (!preg_match('/^\d{6}$/', $entered)) { rate_limit_add($key, 600); return 'invalid'; }

    $row = db_row($conn, "SELECT otp, otp_expiry FROM users WHERE email=? LIMIT 1", 's', [$email]);
    if (!$row || $row['otp'] === null || $row['otp'] === '') { rate_limit_add($key, 600); return 'invalid'; }
    if (empty($row['otp_expiry']) || strtotime($row['otp_expiry']) < time()) { return 'expired'; }
    if (!hash_equals((string)$row['otp'], $entered)) {
        $n = rate_limit_add($key, 600);
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
    if ($file['size'] > $maxBytes)             return [false, 'File is too large (max ' . round($maxBytes / 1048576, 1) . ' MB).'];
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

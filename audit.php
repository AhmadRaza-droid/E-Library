<?php
/**
 * Admin audit trail. IP is taken from REMOTE_ADDR only; forwarded headers are
 * intentionally ignored because they can be spoofed on shared hosting.
 */
function audit_log($conn, $action, $details = '', $actorId = null, $actorName = null) {
    $actorId = $actorId ?? (int)($_SESSION['admin_id'] ?? 0);
    $actorName = $actorName ?? (string)($_SESSION['admin'] ?? 'Admin');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) $ip = 'unknown';
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['page']) : '';
    if ($page !== '') $path .= '?page=' . $page;
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 500);
    $action = mb_substr(trim((string)$action), 0, 120);
    $details = mb_substr(trim((string)$details), 0, 2000);
    if ($action === '') return false;
    return db_exec($conn,
        "INSERT INTO admin_audit_logs (admin_id, admin_name, action, details, ip_address, source, http_method, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        'isssssss',
        [(int)$actorId, mb_substr($actorName, 0, 150), $action, $details, $ip, mb_substr($path, 0, 500), $method, $ua]
    ) !== false;
}

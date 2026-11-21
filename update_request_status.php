<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

if (!isset($_POST['update_status'])) redirect('admin_dashboard.php?page=book_requests');

$request_id = (int)($_POST['request_id'] ?? 0);
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
if ($request_id <= 0 || !in_array($status, ['Pending', 'Approved', 'Added', 'Rejected'], true)) {
    js_alert_redirect('Invalid book request or status.', 'admin_dashboard.php?page=book_requests');
}

$saved = db_exec($conn, "UPDATE book_requests SET status=? WHERE id=?", 'si', [$status, $request_id]);
if ($saved !== 1) {
    js_alert_redirect('Book request status was not changed. Refresh and try again.', 'admin_dashboard.php?page=book_requests');
}
js_alert_redirect('Book request status updated.', 'admin_dashboard.php?page=book_requests');

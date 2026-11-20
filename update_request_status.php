<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

if (isset($_POST['update_status'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $status     = $_POST['status'] ?? '';
    if ($request_id > 0 && in_array($status, ['Pending', 'Approved', 'Added', 'Rejected'], true)) {
        db_exec($conn, "UPDATE book_requests SET status=? WHERE id=?", 'si', [$status, $request_id]);
    }
}
redirect('admin_dashboard.php?page=book_requests');

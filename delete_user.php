<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id === (int)$_SESSION['admin_id']) {
    js_alert_redirect('❌ You cannot delete your own account.', 'admin_dashboard.php?page=all_users');
}
js_alert_redirect(admin_delete_record($conn, 'user', $id) ? 'User deleted successfully!' : '❌ User not found or could not be deleted.', 'admin_dashboard.php?page=all_users');

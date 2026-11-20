<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$id = (int)($_POST['id'] ?? 0);
js_alert_redirect(admin_delete_record($conn, 'book', $id) ? 'Book Deleted Successfully' : '❌ Book not found or could not be deleted.', 'admin_dashboard.php?page=all_books');

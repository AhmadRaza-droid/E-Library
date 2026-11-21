<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);
require_once __DIR__ . '/audit.php';

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $deleted = db_exec($conn, "DELETE FROM reviews WHERE id=?", 'i', [$id]);
    if ($deleted === 1) audit_log($conn, 'Delete book review', 'Deleted review ID ' . $id . '.');
}
redirect('admin_dashboard.php?page=reviews');

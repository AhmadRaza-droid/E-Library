<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_admin($conn);

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    db_exec($conn, "DELETE FROM reviews WHERE id=?", 'i', [$id]);
}
redirect('admin_dashboard.php?page=reviews');

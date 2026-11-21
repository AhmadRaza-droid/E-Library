<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

$bookmark_id = (int)($_POST['bookmark_id'] ?? 0);
if ($bookmark_id > 0) {
    // A user can only remove bookmarks that belong to their own account.
    db_exec(
        $conn,
        "DELETE FROM bookmarks WHERE id=? AND user_id=?",
        'ii',
        [$bookmark_id, (int)$_SESSION['user_id']]
    );
}

redirect('profile.php');

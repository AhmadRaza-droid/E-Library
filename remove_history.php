<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

$book_id = (int)($_POST['book_id'] ?? 0);
if ($book_id > 0) {
    db_exec($conn, "DELETE FROM recently_viewed WHERE user_id=? AND book_id=?", 'ii', [(int)$_SESSION['user_id'], $book_id]);
}
redirect('profile.php');

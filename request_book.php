<?php
require_once __DIR__ . '/session_init.php';
include 'session_timeout.php';
include 'config.php';

require_post();
require_login();

if (isset($_POST['request_book'])) {
    $book_name = trim($_POST['book_name'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    if ($book_name === '' || mb_strlen($book_name) > 200) {
        js_alert_redirect('Please enter a book name.', 'profile.php');
    }
    if (rate_limited('bookreq:' . (int)$_SESSION['user_id'], 5, 3600)) {
        js_alert_redirect('Too many requests. Please try again later.', 'profile.php');
    }
    rate_limit_add('bookreq:' . (int)$_SESSION['user_id'], 3600);

    db_exec($conn, "INSERT INTO book_requests (user_id, book_name, category, message) VALUES (?, ?, ?, ?)",
            'isss', [(int)$_SESSION['user_id'], $book_name, mb_substr($category, 0, 100), mb_substr($message, 0, 2000)]);
    js_alert_redirect('Book request sent successfully', 'profile.php');
}
redirect('profile.php');

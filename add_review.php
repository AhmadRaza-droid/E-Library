<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

if (isset($_POST['submit_review'])) {
    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)($_POST['book_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $review  = trim($_POST['review'] ?? '');
    if (mb_strlen($review) > 2000) { $review = mb_substr($review, 0, 2000); }

    if ($book_id > 0 && $rating >= 1 && $rating <= 5 && db_row($conn, "SELECT id FROM books WHERE id=?", 'i', [$book_id])) {
        if (db_row($conn, "SELECT id FROM reviews WHERE user_id=? AND book_id=?", 'ii', [$user_id, $book_id])) {
            db_exec($conn, "UPDATE reviews SET rating=?, review=? WHERE user_id=? AND book_id=?", 'isii', [$rating, $review, $user_id, $book_id]);
        } else {
            db_exec($conn, "INSERT INTO reviews (user_id, book_id, rating, review) VALUES (?, ?, ?, ?)", 'iiis', [$user_id, $book_id, $rating, $review]);
        }
    }
}
redirect('books.php');

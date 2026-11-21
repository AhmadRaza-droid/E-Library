<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

if (isset($_POST['submit_review'])) {
    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)($_POST['book_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $review  = trim((string)($_POST['review'] ?? ''));

    if ($book_id <= 0 || $rating < 1 || $rating > 5 || mb_strlen($review) > 2000
        || !db_row($conn, "SELECT id FROM books WHERE id=?", 'i', [$book_id])) {
        js_alert_redirect('Please choose a valid book and rating, and keep your review under 2,000 characters.', 'books.php');
    }

    $existing = db_row($conn, "SELECT id FROM reviews WHERE user_id=? AND book_id=?", 'ii', [$user_id, $book_id]);
    if ($existing) {
        $saved = db_exec($conn, "UPDATE reviews SET rating=?, review=? WHERE user_id=? AND book_id=?",
            'isii', [$rating, $review, $user_id, $book_id]);
        if ($saved === false) js_alert_redirect('Your review could not be updated. Please try again.', 'books.php');
        js_alert_redirect('Your review has been updated.', 'books.php');
    }

    $saved = db_exec($conn, "INSERT INTO reviews (user_id, book_id, rating, review) VALUES (?, ?, ?, ?)",
        'iiis', [$user_id, $book_id, $rating, $review]);
    if ($saved !== 1) js_alert_redirect('Your review could not be saved. Please try again.', 'books.php');
    js_alert_redirect('Thank you! Your review has been submitted.', 'books.php');
}
redirect('books.php');

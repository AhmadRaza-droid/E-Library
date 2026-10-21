<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

header('Content-Type: application/json');
require_post();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login']);
    exit();
}
$user_id  = (int)$_SESSION['user_id'];
$book_id  = (int)($_POST['book_id'] ?? 0);
$progress = clamp_int($_POST['progress'] ?? 0, 0, 100);

if ($book_id <= 0 || !db_row($conn, "SELECT id FROM books WHERE id=?", 'i', [$book_id])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid book']);
    exit();
}
db_exec($conn, "INSERT INTO reading_progress (user_id, book_id, progress) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE progress = VALUES(progress)", 'iii', [$user_id, $book_id, $progress]);
db_exec($conn, "INSERT INTO reading_history (user_id, book_id, action) VALUES (?, ?, 'progress_updated')", 'ii', [$user_id, $book_id]);

echo json_encode(['status' => 'success', 'message' => 'Progress saved']);

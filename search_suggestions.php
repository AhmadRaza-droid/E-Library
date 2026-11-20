<?php
define('SKIP_CSRF', true);   // read-only GET endpoint
include 'config.php';

header('Content-Type: text/html; charset=UTF-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { exit(); }
$like = '%' . like_escape($q) . '%';

$rows = db_all($conn,
    "SELECT DISTINCT title FROM books WHERE title LIKE ? OR author LIKE ? OR category LIKE ? LIMIT 5",
    'sss', [$like, $like, $like]);

foreach ($rows as $row) {
    echo '<div class="suggestion-item" data-title="' . e($row['title']) . '" '
       . 'style="padding:10px;cursor:pointer;border-bottom:1px solid #eee;color:#061b33;">'
       . e($row['title']) . '</div>';
}

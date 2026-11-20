<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/student_resources.php';
require __DIR__ . '/maintenance_check.php';
header('Cache-Control: private, no-store');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit('Method not allowed.');
}
$admin = is_admin_session($conn);
if (!$admin) require_login();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$row = $id && resources_ready($conn) ? db_row($conn, 'SELECT * FROM student_resources WHERE id=?', 'i', [$id]) : null;
if (!$row || (!$admin && $row['status'] !== 'approved' && (int)$row['user_id'] !== (int)$_SESSION['user_id'])) {
    http_response_code(404); exit('Material not found or not available.');
}
$path = resource_storage_path($row['storage_key']);
if (!$path || !is_file($path) || !is_readable($path) || !isset(resource_formats()[$row['file_ext']])) {
    http_response_code(404); exit('The file is unavailable. Please contact the administrator.');
}
$file = fopen($path, 'rb');
if (!$file) { http_response_code(503); exit('Download unavailable. Please try again.'); }
$stat = fstat($file);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="material-' . $id . '.' . $row['file_ext'] . '"; filename*=UTF-8\'\'' . rawurlencode($row['original_name']));
header('Content-Length: ' . $stat['size']);
header("Content-Security-Policy: sandbox; default-src 'none'");
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') fpassthru($file);
fclose($file);

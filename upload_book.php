<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_admin($conn);

// The default preserves the existing 20 MiB limit; hosts can set a lower
// limit in config.local.php or via PDF_MAX_BYTES.
$maxPdfBytes = filter_var(cfg('pdf_max_bytes', 20 * 1024 * 1024), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1024 * 1024, 'max_range' => 20 * 1024 * 1024],
]);
if ($maxPdfBytes === false) {
    error_log('Invalid pdf_max_bytes configuration.');
    http_response_code(500);
    die('PDF upload limit is not configured correctly. Please contact the administrator.');
}
$maxPdfMiB = rtrim(rtrim(number_format(floor($maxPdfBytes / 1048576 * 100) / 100, 2, '.', ''), '0'), '.');

$error = '';
if (isset($_POST['upload'])) {
    $title       = trim($_POST['title'] ?? '');
    $author      = trim($_POST['author'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $author === '') {
        $error = 'Title and author are required.';
    } else {
        [$okPdf, $pdfPath] = save_upload($_FILES['pdf'] ?? [], 'uploads/pdfs', ['application/pdf' => 'pdf'], $maxPdfBytes, 'book');
        if (!$okPdf)      { $error = 'PDF: ' . $pdfPath; }
        else {
            [$okCov, $coverPath] = save_upload($_FILES['cover'] ?? [], 'uploads/covers',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 3 * 1024 * 1024, 'cover');
            if (!$okCov) {
                @unlink(__DIR__ . '/' . $pdfPath);
                $error = 'Cover: ' . $coverPath;
            } else {
                $ok = db_exec($conn,
                    "INSERT INTO books (title, author, category, description, cover_image_url, read_link, download_epub_link, pdf_file, cover_file, source)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PDF Upload')",
                    'sssssssss', [$title, $author, $category, $description, $coverPath, $pdfPath, $pdfPath, $pdfPath, $coverPath]);
                if ($ok === false) {
                    @unlink(__DIR__ . '/' . $pdfPath); @unlink(__DIR__ . '/' . $coverPath);
                    $error = 'Could not save the book.';
                } else {
                    js_alert_redirect('PDF Book Uploaded Successfully', 'admin_dashboard.php?page=all_books');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PDF Book</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php echo notification_markup(); ?>
<section class="form-section" style="display:flex;justify-content:center;padding:30px;">
<div class="form-box" style="max-width:480px;width:100%;">
    <h2>📤 Upload PDF Book</h2>
    <?php if ($error): ?><p style="color:#c0392b;"><?php echo e($error); ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="text" name="title" placeholder="Title" required>
        <input type="text" name="author" placeholder="Author" required>
        <input type="text" name="category" placeholder="Category">
        <textarea name="description" placeholder="Description" rows="4"></textarea>
        <label>PDF file (max <?php echo e($maxPdfMiB); ?> MiB)</label>
        <input type="file" name="pdf" accept="application/pdf" required>
        <label>Cover image (jpg/png/webp, max 3 MB)</label>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required>
        <button type="submit" name="upload">Upload</button>
    </form>
    <p><a href="admin_dashboard.php">← Back to dashboard</a></p>
</div>
</section>
</body>
</html>

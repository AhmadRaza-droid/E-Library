<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_admin($conn);

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
$success = '';

if (isset($_POST['upload'])) {
    csrf_require();
    $title = trim((string)($_POST['title'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $multi = isset($_FILES['pdfs']) && is_array($_FILES['pdfs']['name'] ?? null)
        && count(array_filter($_FILES['pdfs']['name'], static fn($n) => $n !== '')) > 0;
    $files = [];

    if ($author === '') {
        $error = 'Author is required.';
    } elseif (!$multi && $title === '') {
        $error = 'Title is required for a single PDF upload.';
    } else {
        if ($multi) {
            $total = count($_FILES['pdfs']['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($_FILES['pdfs']['name'][$i] === '') continue;
                $files[] = [
                    'name' => $_FILES['pdfs']['name'][$i],
                    'type' => $_FILES['pdfs']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['pdfs']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['pdfs']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['pdfs']['size'][$i] ?? 0,
                ];
            }
        } else {
            $files[] = $_FILES['pdf'] ?? [];
        }

        $coverPath = '';
        if (isset($_FILES['cover']) && ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$coverOk, $coverResult] = save_upload($_FILES['cover'], 'uploads/covers',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                3 * 1024 * 1024, 'cover');
            if (!$coverOk) $error = 'Cover: ' . $coverResult;
            else $coverPath = $coverResult;
        }

        $savedPdfs = [];
        $inserted = 0;
        if ($error === '') {
            foreach ($files as $file) {
                $fileName = (string)($file['name'] ?? '');
                $bookTitle = $multi ? trim((string)pathinfo($fileName, PATHINFO_FILENAME)) : $title;
                if ($bookTitle === '') {
                    $error = 'A PDF filename could not be used as a book title.';
                    break;
                }
                [$pdfOk, $pdfResult] = save_upload($file, 'uploads/pdfs',
                    ['application/pdf' => 'pdf'], $maxPdfBytes, 'book');
                if (!$pdfOk) {
                    $error = e($fileName) . ': ' . $pdfResult;
                    break;
                }
                $savedPdfs[] = $pdfResult;
                $bookCover = $coverPath;
                $insert = db_exec($conn,
                    "INSERT INTO books (title, author, category, description, cover_image_url, read_link, download_epub_link, pdf_file, cover_file, source)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PDF Upload')",
                    'sssssssss', [$bookTitle, $author, $category, $description, $bookCover, $pdfResult, $pdfResult, $pdfResult, $bookCover]);
                if ($insert === false) {
                    $error = 'Database could not save "' . $bookTitle . '".';
                    break;
                }
                $inserted++;
            }
        }

        if ($error !== '') {
            foreach ($savedPdfs as $path) @unlink(__DIR__ . '/' . $path);
            if ($coverPath !== '') @unlink(__DIR__ . '/' . $coverPath);
        } elseif ($inserted > 0) {
            $success = $inserted . ' book(s) uploaded successfully.';
            js_alert_redirect($success, 'admin_dashboard.php?page=all_books');
        } else {
            if ($coverPath !== '') @unlink(__DIR__ . '/' . $coverPath);
            $error = 'Please select at least one PDF file.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PDF Books</title>
    <link rel="stylesheet" href="style.css?v=2405">
</head>
<body>
<?php echo notification_markup(); ?>
<section class="form-section" style="display:flex;justify-content:center;padding:30px;">
<div class="form-box" style="max-width:560px;width:100%;">
    <h2>📤 Upload PDF Books</h2>
    <p>Upload one PDF with a custom title, or select multiple PDFs. For multiple uploads, each filename becomes the book title.</p>
    <?php if ($error): ?><p style="color:#c0392b;"><?php echo $error; ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <label>Title (required for one PDF)</label>
        <input type="text" name="title" placeholder="Book title">
        <label>Author (shared by all selected PDFs)</label>
        <input type="text" name="author" placeholder="Author" required>
        <label>Category (shared)</label>
        <input type="text" name="category" placeholder="Category">
        <label>Description (shared)</label>
        <textarea name="description" placeholder="Description" rows="4"></textarea>
        <label>One PDF (max <?php echo e($maxPdfMiB); ?> MiB each)</label>
        <input type="file" name="pdf" accept="application/pdf">
        <label>Or select multiple PDFs (max <?php echo e($maxPdfMiB); ?> MiB each)</label>
        <input type="file" name="pdfs[]" accept="application/pdf" multiple>
        <label>Optional shared cover image (jpg/png/webp, max 3 MB)</label>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <button type="submit" name="upload">Upload Book(s)</button>
    </form>
    <p><a href="admin_dashboard.php">← Back to dashboard</a></p>
</div>
</section>
</body>
</html>

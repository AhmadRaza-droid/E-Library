<?php
// Oversized POSTs lose their CSRF field in PHP. Handle that error before the
// explicit CSRF check below; no state is changed until that check succeeds.
define('SKIP_CSRF', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/student_resources.php';
require __DIR__ . '/maintenance_check.php';
header('Cache-Control: private, no-store');
$ready = resources_ready($conn);
$userId = (int)($_SESSION['user_id'] ?? 0);
$view = is_string($_GET['view'] ?? null) && in_array($_GET['view'], ['upload','mine'], true) ? $_GET['view'] : 'browse';
if ($view !== 'browse') require_login();
$values = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $view = 'upload';
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > resource_ini_bytes(ini_get('post_max_size'))) {
        http_response_code(413);
        $error = 'The upload exceeds the server limit. Choose a smaller file and try again.';
    } else {
        csrf_require();
        if (!$ready) {
            $error = 'Student uploads have not been enabled by the administrator yet.';
        } else {
            foreach (['title'=>180, 'university'=>180, 'course'=>180, 'subject'=>180, 'description'=>3000] as $key=>$length) {
                $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
                if (($key !== 'description' && $values[$key] === '') || mb_strlen($values[$key]) > $length) {
                    $error = 'Fill in title, university, course and subject (max 180 characters each). Description can be at most 3,000 characters.';
                }
            }
            if (($_POST['permission'] ?? '') !== '1') $error = 'Confirm you have permission to share this material.';
            $rateKey = 'resource-upload-' . $userId;
            if ($error === '' && rate_limited($rateKey, 20, 3600)) $error = 'Too many upload attempts. Please try again in an hour.';
            if ($error === '') {
                rate_limit_add($rateKey, 3600);
                [$saved, $file] = resource_save_upload($_FILES['material'] ?? []);
                if (!$saved) {
                    $error = $file;
                } else {
                    // Serialize each student's quota check and insert on their user row.
                    $ok = $conn->begin_transaction();
                    $owner = $ok ? db_row($conn, 'SELECT id FROM users WHERE id=? AND is_verified=1 FOR UPDATE', 'i', [$userId]) : null;
                    $pending = $owner ? db_value($conn, "SELECT COUNT(*) FROM student_resources WHERE user_id=? AND status='pending'", 'i', [$userId]) : null;
                    $recent = $owner ? db_value($conn, 'SELECT COUNT(*) FROM student_resources WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)', 'i', [$userId]) : null;
                    if ($pending === null || $recent === null || $pending >= 10 || $recent >= 10) {
                        $error = $pending >= 10 || $recent >= 10 ? 'You can submit 10 files per hour and keep up to 10 pending files. Please wait for review.' : 'Could not save your upload. Please try again.';
                        $ok = false;
                    } else {
                        $ok = db_exec($conn, 'INSERT INTO student_resources
                            (user_id,title,university,course,subject,description,original_name,storage_key,file_ext,size_bytes)
                            VALUES (?,?,?,?,?,?,?,?,?,?)', 'issssssssi', [$userId,$values['title'],$values['university'],$values['course'],
                            $values['subject'],$values['description'],$file['original_name'],$file['storage_key'],$file['file_ext'],$file['size_bytes']]) === 1;
                    }
                    if ($ok && $conn->commit()) js_alert_redirect('Upload received! It is pending admin approval. Track its status in My uploads.', 'resources.php?view=mine');
                    $conn->rollback();
                    @unlink(resource_storage_path($file['storage_key']));
                    if ($error === '') $error = 'Could not save your upload. Please try again.';
                }
            }
        }
    }
}
$search = is_string($_GET['search'] ?? null) ? mb_substr(trim($_GET['search']), 0, 180) : '';
$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$where = $view === 'mine' ? 'r.user_id=?' : "r.status='approved'";
$params = $view === 'mine' ? [$userId] : [];
$types = $view === 'mine' ? 'i' : '';
if ($search !== '') {
    $where .= ' AND (r.title LIKE ? OR r.university LIKE ? OR r.course LIKE ? OR r.subject LIKE ?)';
    $like = '%' . like_escape($search) . '%';
    $params = array_merge($params, [$like,$like,$like,$like]);
    $types .= 'ssss';
}
$total = $ready && $view !== 'upload' ? (int)db_value($conn, "SELECT COUNT(*) FROM student_resources r WHERE $where", $types, $params, 0) : 0;
$pages = max(1, (int)ceil($total / 12));
$page = min($page, $pages);
$offset = ($page - 1) * 12;
$rows = $ready && $view !== 'upload' ? db_all($conn, "SELECT r.*, COALESCE(u.fullname,'Former student') AS uploader
    FROM student_resources r LEFT JOIN users u ON u.id=r.user_id WHERE $where ORDER BY r.id DESC LIMIT 12 OFFSET $offset", $types, $params) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials - <?php echo e(site_name($conn)); ?></title>
    <link rel="stylesheet" href="style.css?v=2404"><link rel="stylesheet" href="resources.css?v=1">
</head>
<body>
<?php echo notification_markup(); render_site_nav($conn, 'resources.php'); ?>
<header class="page-header"><h1>Study Materials</h1><p>University notes, slides and study guides shared by students, reviewed by admin.</p></header>
<main class="resources-shell">
    <div class="resource-tabs" aria-label="Materials navigation">
        <a href="resources.php" <?php if ($view === 'browse') echo 'aria-current="page"'; ?>>Browse materials</a>
        <a href="resources.php?view=upload" <?php if ($view === 'upload') echo 'aria-current="page"'; ?>>Upload material</a>
        <a href="resources.php?view=mine" <?php if ($view === 'mine') echo 'aria-current="page"'; ?>>My uploads</a>
    </div>
    <?php if (!$ready): ?><p class="resource-callout">Student uploads are coming soon. The administrator needs to enable them in Admin Panel → Student materials.</p><?php endif; ?>
    <?php if ($error): ?><p class="resource-error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
    <?php if ($view === 'upload' && $ready): ?>
    <section class="resource-form resource-card">
        <h2>Share your study material</h2>
        <p>Every file stays private until admin approves it. Maximum file size: <strong><?php echo e(resource_size(resource_max_bytes())); ?></strong>.</p>
        <form method="post" action="resources.php?view=upload" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <?php foreach (['title'=>'Material title', 'university'=>'University / college', 'course'=>'Course / degree (e.g. BS Computer Science)', 'subject'=>'Subject (e.g. Data Structures, Semester 3)'] as $key=>$label): ?>
                <label for="<?php echo e($key); ?>"><?php echo e($label); ?></label>
                <input id="<?php echo e($key); ?>" name="<?php echo e($key); ?>" value="<?php echo e($values[$key] ?? ''); ?>" maxlength="180" required>
            <?php endforeach; ?>
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="4" maxlength="3000"><?php echo e($values['description'] ?? ''); ?></textarea>
            <label for="material">File</label>
            <input id="material" type="file" name="material" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png" required aria-describedby="file-help">
            <p id="file-help">PDF, Word, PowerPoint, Excel, TXT, JPG or PNG. Encrypted DOCX/PPTX/XLSX, macro enabled extensions (DOCM/PPTM/XLSM) and ZIP archives are not supported.</p>
            <label class="resource-permission"><input type="checkbox" name="permission" value="1" required> I own this material or have permission to share it. It contains no private student information.</label>
            <button class="resource-button" type="submit">Submit for approval</button>
        </form>
    </section>
    <?php elseif ($view !== 'upload' && $ready): ?>
        <form method="get" class="resource-search" role="search">
            <?php if ($view === 'mine'): ?><input type="hidden" name="view" value="mine"><?php endif; ?>
            <label for="resource-search">Search by title, university, course or subject</label>
            <div><input type="search" id="resource-search" name="search" value="<?php echo e($search); ?>" maxlength="180"><button class="resource-button" type="submit">Search</button></div>
        </form>
        <p class="resource-count"><?php echo $total; ?> <?php echo $view === 'mine' ? 'uploads' : 'approved materials'; ?></p>
        <?php if (!$rows): ?><p class="resource-callout"><?php echo $view === 'mine' ? 'You have not submitted any matching materials yet.' : 'No matching materials yet. Share your notes to help other students.'; ?></p><?php endif; ?>
        <div class="resource-grid">
        <?php foreach ($rows as $row): ?>
            <article class="resource-card">
                <span class="resource-format"><?php echo e(strtoupper($row['file_ext'])); ?> · <?php echo e(resource_size($row['size_bytes'])); ?></span>
                <h2><?php echo e($row['title']); ?></h2>
                <p><strong><?php echo e($row['university']); ?></strong><br><?php echo e($row['course']); ?><br><?php echo e($row['subject']); ?></p>
                <p class="resource-description"><?php echo nl2br(e($row['description'])); ?></p>
                <p>Shared by <?php echo e($row['uploader']); ?> · <?php echo e(substr($row['created_at'], 0, 10)); ?></p>
                <?php if ($view === 'mine'): ?>
                    <p class="resource-status status-<?php echo e($row['status']); ?>"><?php echo e(ucfirst($row['status'])); ?></p>
                    <?php if ($row['review_note'] !== ''): ?><p><strong>Admin feedback:</strong> <?php echo e($row['review_note']); ?></p><?php endif; ?>
                <?php endif; ?>
                <a class="resource-button" href="<?php echo $userId || is_admin_session($conn) ? 'resource_download.php?id=' . (int)$row['id'] : 'login.php'; ?>"><?php echo $userId || is_admin_session($conn) ? 'Download ' . e(strtoupper($row['file_ext'])) : 'Log in to download'; ?></a>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($pages > 1): ?><div class="resource-tabs" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="?<?php echo e(http_build_query(['view'=>$view, 'search'=>$search, 'page'=>$page-1])); ?>">← Previous</a><?php endif; ?>
            <span>Page <?php echo $page; ?> of <?php echo $pages; ?></span>
            <?php if ($page < $pages): ?><a href="?<?php echo e(http_build_query(['view'=>$view, 'search'=>$search, 'page'=>$page+1])); ?>">Next →</a><?php endif; ?>
        </div><?php endif; ?>
    <?php endif; ?>
</main>
<script src="resources.js?v=1"></script>
</body></html>

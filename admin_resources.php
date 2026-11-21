<?php
// Background count refreshes must not keep an otherwise idle admin signed in.
$summaryRequest = isset($_GET['summary']);
if ($summaryRequest) {
    require_once __DIR__ . '/session_init.php';
    $lastInteraction = $_SESSION['LAST_ACTIVITY'] ?? null;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/student_resources.php';
require_once __DIR__ . '/audit.php';
header('Cache-Control: private, no-store');
if ($summaryRequest) {
    if ($lastInteraction !== null) $_SESSION['LAST_ACTIVITY'] = $lastInteraction;
    else unset($_SESSION['LAST_ACTIVITY']);
    header('Content-Type: application/json');
    if (!is_admin_session($conn)) { http_response_code(403); exit(json_encode(['error'=>'Admin sign-in required.'])); }
    echo json_encode(['pending'=>resources_pending($conn)]);
    exit;
}
require_admin($conn);
$ready = resources_ready($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_resources'])) {
        $ok = resources_install($conn);
        if ($ok) audit_log($conn, 'Enable student materials', 'Enabled student resource submissions and moderation.');
        js_alert_redirect($ok ? 'Student materials enabled. Students can now submit files for review.' : 'Could not enable student materials. Check that the uploads folder is writable and the database account can create tables.', 'admin_resources.php');
    }
    if (!$ready) js_alert_redirect('Enable student materials first.', 'admin_resources.php');
    $id = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';
    $reason = is_string($_POST['review_note'] ?? null) ? trim($_POST['review_note']) : '';
    if (!$id || mb_strlen($reason) > 1000) js_alert_redirect('Choose a valid material and keep feedback under 1,000 characters.', 'admin_resources.php');
    if ($decision === 'delete') {
        // Lock the row so deletion and moderation cannot race each other.
        $ok = $conn->begin_transaction();
        $row = $ok ? db_row($conn, 'SELECT storage_key FROM student_resources WHERE id=? FOR UPDATE', 'i', [$id]) : null;
        $ok = $row && db_exec($conn, 'DELETE FROM student_resources WHERE id=?', 'i', [$id]) === 1;
        if ($ok && $conn->commit()) {
            $path = resource_storage_path($row['storage_key']);
            if ($path && is_file($path) && !@unlink($path)) {
                error_log('Student material file cleanup failed for record ' . $id);
                js_alert_redirect('Material removed. Its private file could not be deleted from storage; check server permissions.', 'admin_resources.php');
            }
            audit_log($conn, 'Delete student material', 'Deleted student material record ID ' . $id . ' and its stored file.');
            js_alert_redirect('Material and its file deleted.', 'admin_resources.php');
        }
        $conn->rollback();
        js_alert_redirect('Material not found or could not be deleted.', 'admin_resources.php');
    }
    if ($decision === 'reject' && $reason === '') js_alert_redirect('Add feedback so the student knows why the file was rejected or unpublished.', 'admin_resources.php');
    $ok = resource_moderate($conn, $id, (int)$_SESSION['admin_id'], $decision, $reason);
    if ($ok) audit_log($conn, 'Moderate student material', 'Material ID ' . $id . ' decision: ' . $decision . ($reason !== '' ? ' | Note: ' . $reason : ''));
    js_alert_redirect($ok ? ($decision === 'approve' ? 'Material approved and published.' : 'Material rejected / unpublished. The student can see your feedback.')
        : 'No change made. The material may already have been reviewed. Refresh and check its status.', 'admin_resources.php');
}
$status = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['pending','approved','rejected'], true) ? $_GET['status'] : 'pending';
$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$total = $ready ? (int)db_value($conn, 'SELECT COUNT(*) FROM student_resources WHERE status=?', 's', [$status], 0) : 0;
$pages = max(1, (int)ceil($total / 12));
$page = min($page, $pages);
$offset = ($page - 1) * 12;
$rows = $ready ? db_all($conn, "SELECT r.*, COALESCE(u.fullname,'Former student') AS uploader
    FROM student_resources r LEFT JOIN users u ON u.id=r.user_id WHERE r.status=? ORDER BY r.id DESC LIMIT 12 OFFSET $offset", 's', [$status]) : [];
$pending = resources_pending($conn);
?>
<!DOCTYPE html>
<html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Student Materials - <?php echo e(site_name($conn)); ?></title>
    <link rel="stylesheet" href="style.css?v=2404"><link rel="stylesheet" href="resources.css?v=1">
</head><body>
<?php echo notification_markup(); render_site_nav($conn, 'admin_resources.php'); ?>
<header class="page-header"><h1>Student Materials</h1><p>Review each student's notes and slides before publishing.</p></header>
<main class="resources-shell">
    <div class="resource-tabs"><a href="admin_dashboard.php">← Admin dashboard</a><a href="resources.php">View published materials</a></div>
    <?php if (!$ready): ?>
        <section class="resource-card"><h2>Enable student uploads</h2><p>This adds a new table for student materials. Existing books, accounts and settings are preserved.</p>
        <form method="post"><?php echo csrf_field(); ?><button class="resource-button" name="enable_resources" value="1">Enable student uploads</button></form></section>
    <?php else: ?>
        <p class="resource-callout" data-resource-notice role="status"><a href="admin_resources.php"><span data-resource-count><?php echo $pending; ?></span> student uploads awaiting review</a>. Counts refresh automatically while this page is open.</p>
        <div class="resource-tabs" aria-label="Review status">
            <?php foreach (['pending','approved','rejected'] as $tab): ?><a href="?status=<?php echo $tab; ?>" <?php if ($status === $tab) echo 'aria-current="page"'; ?>><?php echo ucfirst($tab); ?></a><?php endforeach; ?>
        </div>
        <p class="resource-count"><?php echo $total; ?> <?php echo e($status); ?> materials</p>
        <p>Download and check each file before approval. Only share material students have permission to distribute.</p>
        <?php if (!$rows): ?><p class="resource-callout">No <?php echo e($status); ?> materials.</p><?php endif; ?>
        <div class="resource-grid resource-review-grid">
        <?php foreach ($rows as $row): ?>
            <article class="resource-card">
                <span class="resource-format"><?php echo e(strtoupper($row['file_ext'])); ?> · <?php echo e(resource_size($row['size_bytes'])); ?></span>
                <h2><?php echo e($row['title']); ?></h2>
                <p><strong><?php echo e($row['university']); ?></strong><br><?php echo e($row['course']); ?><br><?php echo e($row['subject']); ?></p>
                <p class="resource-description"><?php echo nl2br(e($row['description'])); ?></p>
                <p>Uploaded by <?php echo e($row['uploader']); ?> · <?php echo e($row['created_at']); ?></p>
                <p><?php echo e($row['original_name']); ?></p>
                <p><a class="resource-button" href="resource_download.php?id=<?php echo (int)$row['id']; ?>">Download for review</a></p>
                <p class="resource-status status-<?php echo e($status); ?>"><?php echo e(ucfirst($status)); ?></p>
                <?php if ($row['reviewed_at']): ?><p>Reviewed: <?php echo e($row['reviewed_at']); ?></p><?php endif; ?>
                <?php if ($row['review_note'] !== ''): ?><p><strong>Feedback:</strong> <?php echo e($row['review_note']); ?></p><?php endif; ?>
                <form method="post" class="resource-review-form">
                    <?php echo csrf_field(); ?><input type="hidden" name="resource_id" value="<?php echo (int)$row['id']; ?>">
                    <label for="feedback-<?php echo (int)$row['id']; ?>">Feedback (required to reject / unpublish)</label>
                    <textarea name="review_note" id="feedback-<?php echo (int)$row['id']; ?>" maxlength="1000" rows="3"></textarea>
                    <div class="resource-actions">
                        <?php if ($status === 'pending'): ?><button class="resource-button" name="decision" value="approve">Approve &amp; publish</button><?php endif; ?>
                        <?php if ($status !== 'rejected'): ?><button class="resource-button resource-secondary" name="decision" value="reject"><?php echo $status === 'approved' ? 'Unpublish' : 'Reject'; ?></button><?php endif; ?>
                    </div>
                </form>
                <form method="post" class="resource-delete-form" onsubmit="return confirm('Permanently delete this material and its file?')">
                    <?php echo csrf_field(); ?><input type="hidden" name="resource_id" value="<?php echo (int)$row['id']; ?>">
                    <button class="resource-delete" name="decision" value="delete">Delete permanently</button>
                </form>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($pages > 1): ?><div class="resource-tabs" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="?status=<?php echo e($status); ?>&amp;page=<?php echo $page-1; ?>">← Previous</a><?php endif; ?>
            <span>Page <?php echo $page; ?> of <?php echo $pages; ?></span>
            <?php if ($page < $pages): ?><a href="?status=<?php echo e($status); ?>&amp;page=<?php echo $page+1; ?>">Next →</a><?php endif; ?>
        </div><?php endif; ?>
    <?php endif; ?>
</main><script src="resources.js?v=1"></script><script src="resource-admin.js?v=1" defer></script>
</body></html>

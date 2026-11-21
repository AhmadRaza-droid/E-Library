<?php
include 'session_timeout.php';
include 'config.php';
require_admin($conn);
require_once __DIR__ . '/audit.php';

$perPage = 50;
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNo - 1) * $perPage;
$filter = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$where = [];
$types = '';
$params = [];
if ($filter !== '') {
    $where[] = '(admin_name LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ? OR source LIKE ?)';
    $like = '%' . $filter . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($from !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) { $where[] = 'created_at >= ?'; $types .= 's'; $params[] = $from . ' 00:00:00'; }
if ($to !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) { $where[] = 'created_at <= ?'; $types .= 's'; $params[] = $to . ' 23:59:59'; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)db_value($conn, 'SELECT COUNT(*) FROM admin_audit_logs' . $sqlWhere, $types, $params, 0);
$logs = db_all($conn, 'SELECT * FROM admin_audit_logs' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset, $types, $params);
$pages = max(1, (int)ceil($total / $perPage));
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Audit Log</title><link rel="stylesheet" href="style.css?v=8000">
<style>
body{background:#eef2f7;color:#172338;font-family:Segoe UI,Arial,sans-serif}.audit-wrap{max-width:1600px;margin:30px auto;padding:0 18px}
.audit-head,.audit-card{background:#fff;border:1px solid #e4eaf1;border-radius:15px;box-shadow:0 7px 24px rgba(20,40,70,.06);padding:22px;margin-bottom:18px}
.audit-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.audit-head h1{font-size:24px;color:#153b55}
.audit-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.audit-filters label{display:grid;gap:5px;font-size:13px;font-weight:600}.audit-filters input{padding:10px;border:1px solid #ccd7e2;border-radius:8px}
.audit-table-wrap{overflow:auto}.audit-table{width:100%;border-collapse:collapse;min-width:1100px}.audit-table th,.audit-table td{padding:11px;border-bottom:1px solid #e7edf2;text-align:left;vertical-align:top;font-size:13px}.audit-table th{background:#173b56;color:white;position:sticky;top:0}.audit-table tr:nth-child(even){background:#f8fafc}
.audit-muted{color:#69788b;font-size:12px;overflow-wrap:anywhere}.audit-pill{display:inline-block;background:#e7f3f0;color:#176a5c;border-radius:20px;padding:4px 8px;font-weight:700}
.audit-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px}.audit-pagination a{padding:8px 12px;background:#173b56;color:#fff;text-decoration:none;border-radius:7px}
.audit-note{color:#68778b;font-size:13px;margin-top:8px}
@media(max-width:650px){.audit-wrap{padding:0 10px}.audit-head,.audit-card{padding:15px}.audit-head h1{font-size:20px}}
body.dark-mode{background:#0c1422;color:#e7edf5}body.dark-mode .audit-head,body.dark-mode .audit-card{background:#152235;border-color:#263a53;color:#e7edf5}body.dark-mode .audit-head h1{color:#f4f7fb}body.dark-mode .audit-table td{color:#e1e8f1;border-color:#31445c}body.dark-mode .audit-table tr:nth-child(even){background:#192b41}body.dark-mode .audit-filters input{background:#101c2d;color:#fff;border-color:#344961}
</style></head><body>
<?php echo notification_markup(); ?>
<div class="audit-wrap">
<div class="audit-head"><div><h1>🧾 Admin Audit Log</h1><p class="audit-note">Record of admin actions, time, source page, IP address and browser.</p></div><div><a href="admin_dashboard.php">← Dashboard</a> &nbsp; <a href="admin_settings.php">Settings</a></div></div>
<div class="audit-card">
<form class="audit-filters" method="get">
<label>Search admin, action, details, IP or page<input name="q" value="<?php echo e($filter); ?>" placeholder="e.g. delete, 192.168..."></label>
<label>From<input type="date" name="from" value="<?php echo e($from); ?>"></label>
<label>To<input type="date" name="to" value="<?php echo e($to); ?>"></label>
<button type="submit">Filter</button><a href="admin_audit_logs.php">Clear</a>
</form>
<p class="audit-note"><?php echo number_format($total); ?> audit entries · newest first · timestamps use Asia/Karachi server time.</p>
</div>
<div class="audit-card audit-table-wrap"><table class="audit-table">
<thead><tr><th>Date &amp; Time</th><th>Admin</th><th>Action</th><th>Details</th><th>IP Address</th><th>Source / Page</th><th>Method</th><th>Browser / Device</th></tr></thead><tbody>
<?php if (!$logs): ?><tr><td colspan="8">No audit records found.</td></tr><?php else: foreach ($logs as $log): ?>
<tr><td><?php echo e($log['created_at']); ?></td><td><?php echo e($log['admin_name']); ?><div class="audit-muted">ID: <?php echo (int)$log['admin_id']; ?></div></td>
<td><span class="audit-pill"><?php echo e($log['action']); ?></span></td><td><?php echo e($log['details']); ?></td>
<td><?php echo e($log['ip_address']); ?></td><td><?php echo e($log['source']); ?></td><td><?php echo e($log['http_method']); ?></td>
<td class="audit-muted"><?php echo e($log['user_agent']); ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table>
<div class="audit-pagination"><span>Page <?php echo $pageNo; ?> of <?php echo $pages; ?></span><span>
<?php if ($pageNo > 1): ?><a href="?<?php echo e(http_build_query(['q'=>$filter,'from'=>$from,'to'=>$to,'p'=>$pageNo-1])); ?>">Previous</a><?php endif; ?>
<?php if ($pageNo < $pages): ?><a href="?<?php echo e(http_build_query(['q'=>$filter,'from'=>$from,'to'=>$to,'p'=>$pageNo+1])); ?>">Next</a><?php endif; ?>
</span></div></div></div>
<script>if(localStorage.getItem('theme')==='dark')document.body.classList.add('dark-mode');</script>
</body></html>
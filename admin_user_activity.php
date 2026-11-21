<?php
include 'session_timeout.php';
include 'config.php';
require_admin($conn);

$perPage = 50;
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNo - 1) * $perPage;
$filter = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$where=[];$types='';$params=[];
if($filter!==''){$where[]='(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ? OR page LIKE ?)';$like='%'.$filter.'%';$types.='sssss';array_push($params,$like,$like,$like,$like,$like);}
if(in_array($type,['guest','user','admin'],true)){$where[]='actor_type=?';$types.='s';$params[]=$type;}
if($from!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='created_at>=?';$types.='s';$params[]=$from.' 00:00:00';}
if($to!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='created_at<=?';$types.='s';$params[]=$to.' 23:59:59';}
$sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
$total=(int)db_value($conn,'SELECT COUNT(*) FROM website_activity_logs'.$sqlWhere,$types,$params,0);
$rows=db_all($conn,'SELECT * FROM website_activity_logs'.$sqlWhere.' ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset,$types,$params);
$pages=max(1,(int)ceil($total/$perPage));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Activity - E-Library</title><link rel="stylesheet" href="style.css?v=8000">
<style>
body{background:#eef2f7;color:#172338;font-family:Segoe UI,Arial,sans-serif}.wrap{max-width:1700px;margin:28px auto;padding:0 16px}.card{background:#fff;border:1px solid #e4eaf1;border-radius:15px;padding:20px;margin-bottom:16px;box-shadow:0 7px 24px #1428460c}.head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.head h1{color:#153b55;font-size:24px}.filters{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.filters label{display:grid;gap:5px;font-size:13px;font-weight:600}.filters input,.filters select{padding:10px;border:1px solid #ccd7e2;border-radius:8px}.tablewrap{overflow:auto}table{width:100%;min-width:1100px;border-collapse:collapse}th,td{text-align:left;padding:11px;border-bottom:1px solid #e7edf2;vertical-align:top;font-size:13px}th{background:#173b56;color:white}tr:nth-child(even){background:#f8fafc}.muted{color:#718096;font-size:12px;overflow-wrap:anywhere}.pill{border-radius:20px;padding:4px 8px;background:#e7f3f0;color:#176a5c;font-weight:700}.pager{display:flex;justify-content:space-between;margin-top:16px}.pager a{padding:8px 12px;background:#173b56;color:white;border-radius:7px;text-decoration:none}
@media(max-width:600px){.card{padding:14px}.head h1{font-size:20px}}body.dark-mode{background:#0c1422;color:#e7edf5}body.dark-mode .card{background:#152235;border-color:#263a53}body.dark-mode .head h1{color:#f4f7fb}body.dark-mode td{color:#e1e8f1;border-color:#31445c}body.dark-mode tr:nth-child(even){background:#192b41}body.dark-mode .filters input,body.dark-mode .filters select{background:#101c2d;color:#fff;border-color:#344961}
</style></head><body><?php echo notification_markup(); ?><main class="wrap">
<section class="card head"><div><h1>👥 Website User Activity</h1><p class="muted">Page visits and actions by registered users, admins and guest visitors.</p></div><div><a href="admin_dashboard.php">Dashboard</a> · <a href="admin_audit_logs.php">Admin Audit</a></div></section>
<section class="card"><form class="filters" method="get">
<label>Search user/action/IP/page<input name="q" value="<?php echo e($filter); ?>" placeholder="Name, IP, action..."></label>
<label>Actor<select name="type"><option value="">Everyone</option><option value="user" <?php echo $type==='user'?'selected':''; ?>>Registered users</option><option value="guest" <?php echo $type==='guest'?'selected':''; ?>>Guests</option><option value="admin" <?php echo $type==='admin'?'selected':''; ?>>Admins</option></select></label>
<label>From<input type="date" name="from" value="<?php echo e($from); ?>"></label><label>To<input type="date" name="to" value="<?php echo e($to); ?>"></label>
<button>Filter</button><a href="admin_user_activity.php">Clear</a></form><p class="muted"><?php echo number_format($total); ?> records · newest first · Asia/Karachi server time.</p></section>
<section class="card tablewrap"><table><thead><tr><th>Date &amp; time</th><th>Visitor / User</th><th>Type</th><th>Action</th><th>Details</th><th>IP</th><th>Page</th><th>Method</th><th>Browser / device</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="9">No activity found.</td></tr><?php else: foreach($rows as $row): ?><tr>
<td><?php echo e($row['created_at']); ?></td><td><?php echo e($row['user_name']); ?><div class="muted">User ID: <?php echo (int)$row['user_id']; ?></div></td>
<td><span class="pill"><?php echo e($row['actor_type']); ?></span></td><td><?php echo e($row['action']); ?></td><td><?php echo e($row['details']); ?></td>
<td><?php echo e($row['ip_address']); ?></td><td><?php echo e($row['page']); ?></td><td><?php echo e($row['http_method']); ?></td><td class="muted"><?php echo e($row['user_agent']); ?></td>
</tr><?php endforeach; endif; ?></tbody></table><div class="pager"><span>Page <?php echo $pageNo; ?> of <?php echo $pages; ?></span><span><?php if($pageNo>1): ?><a href="?<?php echo e(http_build_query(['q'=>$filter,'type'=>$type,'from'=>$from,'to'=>$to,'p'=>$pageNo-1])); ?>">Previous</a><?php endif; ?> <?php if($pageNo<$pages): ?><a href="?<?php echo e(http_build_query(['q'=>$filter,'type'=>$type,'from'=>$from,'to'=>$to,'p'=>$pageNo+1])); ?>">Next</a><?php endif; ?></span></div></section>
<p class="muted">Privacy: passwords, OTPs, reset tokens and full form contents are never stored. Guest sessions are identified with a one-way session hash.</p></main><script>if(localStorage.getItem('theme')==='dark')document.body.classList.add('dark-mode');</script></body></html>
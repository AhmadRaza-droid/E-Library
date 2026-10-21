<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_admin($conn);

$rows = db_all($conn, "SELECT * FROM messages ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Messages</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1 style="text-align:center;">User Messages</h1>

<table border="1" width="100%" cellpadding="10">
<tr><th>ID</th><th>Name</th><th>Email</th><th>Message</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
    <td><?php echo (int)$row['id']; ?></td>
    <td><?php echo e($row['name']); ?></td>
    <td><?php echo e($row['email']); ?></td>
    <td><?php echo nl2br(e($row['message'])); ?></td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>

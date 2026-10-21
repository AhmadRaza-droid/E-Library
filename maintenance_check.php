<?php
// Include AFTER config.php
$maintenance_mode = get_setting($conn, 'maintenance_mode', '0');

// Admins (logged in through the admin panel, or normal accounts with is_admin=1) can still use the site
$bypass = is_admin_session($conn) || current_user_is_admin($conn);

if ($maintenance_mode == '1' && !$bypass) {
    http_response_code(503);
    header('Retry-After: 3600');
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .maintenance-box { background: white; padding: 50px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .maintenance-box .icon { font-size: 80px; margin-bottom: 20px; }
        .maintenance-box h1 { color: #0b1f3a; margin-bottom: 10px; }
        .maintenance-box p { color: #666; line-height: 1.8; }
        .maintenance-box .status { background: #ffc72c; color: #0b1f3a; padding: 8px 20px; border-radius: 20px; display: inline-block; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="icon">🔧</div>
        <h1>Under Maintenance</h1>
        <p>We're currently working on improving our library system.</p>
        <p>Please check back later. We apologize for the inconvenience.</p>
        <div class="status">🟡 Maintenance Mode Active</div>
        <p style="margin-top:20px;"><a href="admin_login.php">Admin login</a></p>
    </div>
</body>
</html>
<?php
    exit();
}

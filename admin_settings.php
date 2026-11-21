<?php
include 'session_timeout.php';
include 'config.php';
require_admin($conn);
require_once __DIR__ . '/audit.php';

$allowed = [
    'site_name' => 100,
    'site_tagline' => 200,
    'contact_email' => 150,
    'contact_phone' => 40,
    'contact_address' => 300,
    'footer_text' => 200,
    'maintenance_mode' => 1,
    'allow_registration' => 1,
];
$defaults = [
    'site_name' => "Book's Library",
    'site_tagline' => 'Read. Learn. Grow.',
    'contact_email' => '',
    'contact_phone' => '+92 300 1234567',
    'contact_address' => 'Management & Technology University, Lahore',
    'footer_text' => "© " . date('Y') . " Book's Library. All Rights Reserved.",
    'maintenance_mode' => '0',
    'allow_registration' => '1',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_settings'])) {
    $before = [];
    foreach (db_all($conn, "SELECT setting_key, setting_value FROM settings") as $row) {
        $before[$row['setting_key']] = $row['setting_value'];
    }
    $values = [];
    foreach ($allowed as $key => $max) {
        $raw = (string)($_POST[$key] ?? ($defaults[$key] ?? ''));
        $value = trim(mb_substr($raw, 0, $max));
        if (in_array($key, ['maintenance_mode', 'allow_registration'], true)) {
            if (!in_array($value, ['0','1'], true)) js_alert_redirect('❌ Invalid option selected.', 'admin_settings.php');
        }
        if ($key === 'contact_email' && $value !== '' && !validate_email($value)) {
            js_alert_redirect('❌ Enter a valid contact email address.', 'admin_settings.php');
        }
        $values[$key] = $value;
    }

    if (!$conn->begin_transaction()) js_alert_redirect('❌ Could not save settings.', 'admin_settings.php');
    foreach ($values as $key => $value) {
        if (db_exec($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", 'ss', [$key, $value]) === false) {
            $conn->rollback();
            js_alert_redirect('❌ Could not save settings. No changes were committed.', 'admin_settings.php');
        }
    }
    if (!$conn->commit()) {
        $conn->rollback();
        js_alert_redirect('❌ Could not save settings.', 'admin_settings.php');
    }
    $changed = [];
    foreach ($values as $key => $value) {
        if ((string)($before[$key] ?? $defaults[$key] ?? '') !== $value) $changed[] = $key;
    }
    audit_log($conn, 'Update website settings', $changed ? 'Changed settings: ' . implode(', ', $changed) : 'Settings form saved; no values changed.');
    js_alert_redirect('✅ Settings saved successfully. Changes are now stored in the database.', 'admin_settings.php');
}

$settings = $defaults;
foreach (db_all($conn, "SELECT setting_key, setting_value FROM settings") as $row) {
    if (array_key_exists($row['setting_key'], $settings)) $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Website Settings - <?php echo e(site_name($conn)); ?></title>
<link rel="stylesheet" href="style.css?v=8000">
<style>
*{box-sizing:border-box}.settings-page{max-width:1120px;margin:28px auto;padding:0 18px 40px;color:#172338}
.settings-top{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:20px}
.settings-top h1{font-size:27px;color:#153b55}.settings-top p{color:#6b7a8d;margin-top:5px}
.settings-links{display:flex;gap:9px;flex-wrap:wrap}.settings-links a{padding:10px 13px;background:#173b56;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}
.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.settings-card{background:#fff;border:1px solid #e4eaf1;border-radius:15px;padding:22px;box-shadow:0 7px 24px rgba(20,40,70,.055)}
.settings-card h2{font-size:18px;color:#173b56;margin-bottom:7px}.settings-card>p{font-size:13px;color:#6b7a8d;margin-bottom:18px;line-height:1.55}
.settings-field{margin:0 0 16px}.settings-field label{display:block;font-weight:700;font-size:14px;margin-bottom:7px;color:#27394d}
.settings-field input,.settings-field textarea,.settings-field select{width:100%;padding:12px;border:1px solid #cfd9e3;border-radius:9px;background:#fff;color:#172338;font:inherit}
.settings-field input:focus,.settings-field textarea:focus,.settings-field select:focus{outline:2px solid #b9ded6;border-color:#1b8175}
.settings-hint{font-size:12px;color:#758397;display:block;margin-top:6px;line-height:1.45}
.settings-save{grid-column:1/-1;display:flex;justify-content:flex-end;align-items:center;gap:14px;flex-wrap:wrap}
.settings-save button{background:#147a67;color:#fff;border:0;border-radius:9px;padding:13px 22px;font-weight:700;cursor:pointer}
.settings-status{font-size:13px;color:#68778b}.settings-warning{background:#fff7e6;border:1px solid #f0d69b;padding:12px 14px;border-radius:9px;color:#795516;font-size:13px;line-height:1.5}
@media(max-width:700px){.settings-grid{grid-template-columns:1fr}.settings-save{grid-column:auto}.settings-top h1{font-size:22px}}
body.dark-mode{background:#0c1422;color:#e7edf5}.dark-mode .settings-card{background:#152235;border-color:#263a53}.dark-mode .settings-top h1,.dark-mode .settings-card h2,.dark-mode .settings-field label{color:#edf4fb}.dark-mode .settings-top p,.dark-mode .settings-card>p,.dark-mode .settings-hint,.dark-mode .settings-status{color:#aebbd0}.dark-mode .settings-field input,.dark-mode .settings-field textarea,.dark-mode .settings-field select{background:#101c2d;color:#eef4fb;border-color:#344961}
</style></head>
<body>
<?php echo notification_markup(); ?>
<main class="settings-page">
<header class="settings-top"><div><h1>⚙️ Website Settings</h1><p>Control your library identity, contact details and public access.</p></div>
<nav class="settings-links"><a href="admin_dashboard.php">← Dashboard</a><a href="admin_audit_logs.php">🧾 Audit Log</a><a href="admin_logout.php">Logout</a></nav></header>
<form method="POST" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="settings-grid">
<section class="settings-card"><h2>🏷️ Website identity</h2><p>These details are used in the website's shared branding and page content.</p>
<div class="settings-field"><label for="site_name">Website name</label><input id="site_name" type="text" name="site_name" maxlength="100" required value="<?php echo e($settings['site_name']); ?>"></div>
<div class="settings-field"><label for="site_tagline">Tagline</label><input id="site_tagline" type="text" name="site_tagline" maxlength="200" value="<?php echo e($settings['site_tagline']); ?>"><span class="settings-hint">Displayed on pages that use the site tagline setting.</span></div>
<div class="settings-field"><label for="footer_text">Footer text</label><input id="footer_text" type="text" name="footer_text" maxlength="200" value="<?php echo e($settings['footer_text']); ?>"></div>
</section>
<section class="settings-card"><h2>✉️ Contact information</h2><p>Update the contact details shown by supported website pages.</p>
<div class="settings-field"><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" maxlength="150" value="<?php echo e($settings['contact_email']); ?>"></div>
<div class="settings-field"><label for="contact_phone">Contact phone</label><input id="contact_phone" type="text" name="contact_phone" maxlength="40" value="<?php echo e($settings['contact_phone']); ?>"></div>
<div class="settings-field"><label for="contact_address">Contact address</label><textarea id="contact_address" name="contact_address" maxlength="300" rows="3"><?php echo e($settings['contact_address']); ?></textarea></div>
</section>
<section class="settings-card"><h2>🛠️ Access and availability</h2><p>These controls change who can access the public library.</p>
<div class="settings-field"><label for="maintenance_mode">Maintenance mode</label><select id="maintenance_mode" name="maintenance_mode"><option value="0" <?php echo $settings['maintenance_mode']==='0'?'selected':''; ?>>Off — website open</option><option value="1" <?php echo $settings['maintenance_mode']==='1'?'selected':''; ?>>On — show maintenance page</option></select><span class="settings-hint">When enabled, visitors see a maintenance notice. Admins can still sign in and manage the site.</span></div>
<div class="settings-field"><label for="allow_registration">New user registration</label><select id="allow_registration" name="allow_registration"><option value="1" <?php echo $settings['allow_registration']==='1'?'selected':''; ?>>Allowed</option><option value="0" <?php echo $settings['allow_registration']==='0'?'selected':''; ?>>Closed</option></select><span class="settings-hint">Existing users can still log in when registration is closed.</span></div>
<div class="settings-warning">Be careful: enabling maintenance mode hides the public website from visitors until you switch it off. Admin routes remain accessible.</div>
</section>
<div class="settings-save"><span class="settings-status">Settings are saved to the database and remain after logout.</span><button type="submit" name="update_settings" value="1">💾 Save all settings</button></div>
</div></form></main>
<script>if(localStorage.getItem('theme')==='dark')document.body.classList.add('dark-mode');</script>
</body></html>

<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';
include 'maintenance_check.php';
require_once __DIR__ . '/mailer.php';

$settings = get_settings($conn);

// Handle message submission
if (isset($_POST['send_message'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || mb_strlen($name) > 100 || !validate_email($email) || $message === '' || mb_strlen($message) > 3000) {
        js_alert_redirect('❌ Please fill all fields correctly.', 'contact.php');
    }
    if (rate_limited('contact:' . client_ip(), 5, 3600)) {
        js_alert_redirect('⛔ Too many messages. Please try again later.', 'contact.php');
    }
    rate_limit_add('contact:' . client_ip(), 3600);

    $saved = db_exec($conn, "INSERT INTO messages (name, email, message) VALUES (?, ?, ?)", 'sss', [$name, $email, $message]);
    if ($saved === false) {
        js_alert_redirect('❌ Failed to send message. Please try again.', 'contact.php');
    }

    // ========== NOTIFY ADMIN BY E-MAIL ==========
    $admin_email = $settings['contact_email'] ?? '';
    if (!validate_email($admin_email)) { $admin_email = cfg('admin_email', cfg('smtp_user', '')); }

    $html = "<div style='font-family: Arial; padding: 20px; max-width: 600px;'>
                <h2 style='color: #0b1f3a;'>📩 New Contact Message</h2><hr>
                <p><strong>👤 Name:</strong> " . e($name) . "</p>
                <p><strong>📧 Email:</strong> " . e($email) . "</p>
                <p><strong>💬 Message:</strong></p>
                <div style='background: #f5f5f5; padding: 15px; border-radius: 8px;'>" . nl2br(e($message)) . "</div>
                <hr><small style='color: #666;'>📚 Book's Library Team</small></div>";
    $sent = send_mail($admin_email, 'Admin', '📩 New Message from ' . str_replace(["\r", "\n"], ' ', $name), $html, "New Message from $name\nEmail: $email\nMessage: $message", $email, $name);

    js_alert_redirect($sent ? '✅ Message sent successfully! We will get back to you soon.'
                            : '✅ Message saved! (We could not send the e-mail notification, but the team will still see it.)', 'contact.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Contact - <?php echo htmlspecialchars($settings['site_name'] ?? 'Library Management System'); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .contact-section {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .contact-info {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .contact-info h2 {
            color: #0b1f3a;
            margin-bottom: 20px;
        }
        .contact-info p {
            margin: 12px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .contact-info p b {
            color: #0b1f3a;
        }
        .contact-form {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .contact-form h2 {
            color: #0b1f3a;
            margin-bottom: 20px;
        }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .contact-form button {
            width: 100%;
            padding: 14px;
            background: #0b1f3a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .contact-form button:hover {
            background: #1a3a5c;
        }
        .dark-mode .contact-info {
            background: #16213e;
            color: white;
        }
        .dark-mode .contact-info h2 {
            color: white;
        }
        .dark-mode .contact-info p {
            border-bottom-color: #333;
        }
        .dark-mode .contact-info p b {
            color: #ffc72c;
        }
        .dark-mode .contact-form {
            background: #16213e;
            color: white;
        }
        .dark-mode .contact-form h2 {
            color: white;
        }
        .dark-mode .contact-form input,
        .dark-mode .contact-form textarea {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        @media (max-width: 768px) {
            .contact-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">📖 <?php echo htmlspecialchars($settings['site_name'] ?? 'Library Management System'); ?></div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
        <?php if(current_user_is_admin($conn)){ ?>
            <li><a href="admin_dashboard.php">Admin Panel</a></li>
        <?php } ?>
        <li><a class="active" href="contact.php">Contact</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="page-header">
    <h1>📩 <?php echo htmlspecialchars($settings['site_name'] ?? 'Contact Us'); ?></h1>
    <p><?php echo htmlspecialchars($settings['site_tagline'] ?? 'Have any questions? Send us a message.'); ?></p>
</section>

<section class="contact-section">

    <div class="contact-info">
        <h2>📌 Library Information</h2>
        <p><b>📍 Address:</b> <?php echo htmlspecialchars($settings['contact_address'] ?? 'University Campus Library'); ?></p>
        <p><b>📧 Email:</b> <?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?></p>
        <p><b>📞 Phone:</b> <?php echo htmlspecialchars($settings['contact_phone'] ?? '+92 300 1234567'); ?></p>
        <p><b>🕐 Timing:</b> Monday - Saturday, 9:00 AM - 5:00 PM</p>
    </div>

    <div class="contact-form">
        <h2>✉️ Send Message</h2>
        <p style="color:#666;font-size:14px;margin-bottom:15px;">We'll get back to you within 24 hours</p>

        <form action="contact.php" method="POST">
<?php echo csrf_field(); ?>

            <input type="text" 
                   name="name" 
                   placeholder="Your Name" 
                   required>

            <input type="email" 
                   name="email" 
                   placeholder="Your Email" 
                   required>

            <textarea name="message" 
                      placeholder="Your Message" 
                      rows="5"
                      required></textarea>

            <button type="submit" name="send_message">
                📤 Send Message
            </button>

        </form>
    </div>

</section>

<footer>
    <p><?php echo htmlspecialchars($settings['footer_text'] ?? '© 2026 Book\'s Library. All Rights Reserved.'); ?></p>
</footer>

<script>
if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}

function toggleDarkMode(){

    document.body.classList.toggle("dark-mode");

    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    }
    else{
        localStorage.setItem("theme", "light");
    }
}
</script>

</body>
</html>
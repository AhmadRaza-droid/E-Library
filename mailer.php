<?php
// =====================================================================
//  Central mail helper - SMTP credentials come from config.local.php
// =====================================================================
use PHPMailer\PHPMailer\PHPMailer;

// Avoid a fatal error when Composer dependencies have not been uploaded yet.
$phpMailerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($phpMailerAutoload)) {
    require_once $phpMailerAutoload;
}

function make_mailer($keepAlive = false){
    $smtpUser = cfg('smtp_user', getenv('LIBRARY_SMTP_USERNAME') !== false ? getenv('LIBRARY_SMTP_USERNAME') : '');
    $smtpPass = cfg('smtp_pass', getenv('LIBRARY_SMTP_PASSWORD') !== false ? getenv('LIBRARY_SMTP_PASSWORD') : '');
    $smtpHost = cfg('smtp_host', getenv('LIBRARY_SMTP_HOST') !== false ? getenv('LIBRARY_SMTP_HOST') : 'smtp.gmail.com');
    $smtpPort = (int)cfg('smtp_port', getenv('LIBRARY_SMTP_PORT') !== false ? getenv('LIBRARY_SMTP_PORT') : 587);

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $smtpHost;
    $m->SMTPAuth   = true;
    $m->Username   = $smtpUser;
    $m->Password   = $smtpPass;
    // Port 465 requires implicit TLS; 587 normally uses STARTTLS.
    $m->SMTPSecure = $smtpPort === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = $smtpPort;
    $m->CharSet    = 'UTF-8';
    $m->Timeout    = 15;
    $m->SMTPKeepAlive = (bool)$keepAlive;
    $m->setFrom($smtpUser, cfg('mail_from_name', "Book's Library"));
    return $m;
}

/** Sends one HTML mail. Returns true/false (never throws, never prints). */
function send_mail($to, $toName, $subject, $html, $alt = '', $replyTo = null, $replyName = ''){
    try {
        if (!class_exists(PHPMailer::class)) {
            error_log('Mail unavailable: PHPMailer is missing. Run composer install and upload vendor/.');
            return false;
        }
        $m = make_mailer();
        $m->addAddress($to, $toName);
        if ($replyTo && validate_email($replyTo)) { $m->addReplyTo($replyTo, $replyName); }
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = $alt !== '' ? $alt : trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)));
        $m->send();
        return true;
    } catch (\Throwable $ex) {
        error_log('Mail failed: ' . $ex->getMessage());
        return false;
    }
}

function otp_mail_html($title, $greetingName, $otp){
    $name = $greetingName !== '' ? '<p>Hello <strong>' . e($greetingName) . '</strong>,</p>' : '';
    return "<div style='font-family: Arial; padding: 20px;'>
        <h2 style='color: #0b1f3a;'>" . e($title) . "</h2>
        $name
        <p>Your OTP code is:</p>
        <h1 style='color: #28a745; font-size: 40px;'>" . e($otp) . "</h1>
        <p>This OTP is valid for <strong>10 minutes</strong>. If you did not request it, ignore this email.</p>
        <hr><small>📚 Book's Library Team</small></div>";
}

<?php
// =====================================================================
//  Central mail helper - SMTP credentials come from config.local.php
// =====================================================================
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

function make_mailer($keepAlive = false){
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = cfg('smtp_host', 'smtp.gmail.com');
    $m->SMTPAuth   = true;
    $m->Username   = cfg('smtp_user', '');
    $m->Password   = cfg('smtp_pass', '');
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = (int)cfg('smtp_port', 587);
    $m->CharSet    = 'UTF-8';
    $m->Timeout    = 15;
    $m->SMTPKeepAlive = (bool)$keepAlive;
    $m->setFrom(cfg('smtp_user', ''), cfg('mail_from_name', "Book's Library"));
    return $m;
}

/** Sends one HTML mail. Returns true/false (never throws, never prints). */
function send_mail($to, $toName, $subject, $html, $alt = '', $replyTo = null, $replyName = ''){
    try {
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

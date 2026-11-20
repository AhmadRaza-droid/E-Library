<?php
/**
 * Copy to config.local.php on the hosting account only.
 * Never commit real credentials.
 */
return [
    'db_host' => 'YOUR_DATABASE_HOST',
    'db_user' => 'YOUR_DATABASE_USERNAME',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'db_name' => 'YOUR_DATABASE_NAME',
    'pdf_max_bytes' => 10000000,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'YOUR_GMAIL_ADDRESS',
    'smtp_pass' => 'YOUR_GMAIL_APP_PASSWORD',
    'mail_from_name' => "Book's Library",
    'admin_email' => 'YOUR_ADMIN_EMAIL',
];

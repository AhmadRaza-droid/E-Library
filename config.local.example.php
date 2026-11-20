<?php
/**
 * Copy this file to config.local.php on the hosting account.
 * Never commit config.local.php or real credentials.
 */
return [
    'db_host' => 'YOUR_DATABASE_HOST',
    'db_user' => 'YOUR_DATABASE_USERNAME',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'db_name' => 'YOUR_DATABASE_NAME',

    // InfinityFree free hosting commonly limits uploads to 10 MB.
    'pdf_max_bytes' => 10000000,

    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'YOUR_GMAIL_ADDRESS',
    'smtp_pass' => 'YOUR_GMAIL_APP_PASSWORD',
    'mail_from_name' => "Book's Library",
    'admin_email' => 'YOUR_ADMIN_EMAIL',
];

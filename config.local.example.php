<?php
// Copy this file to config.local.php and fill in your REAL values.
// config.local.php is git-ignored: never commit it. Upload it to the server manually.
return [
    'db_host' => 'localhost',
    'db_user' => 'your_db_user',
    'db_pass' => 'your_db_password',
    'db_name' => 'your_db_name',

    'smtp_host'      => 'smtp.gmail.com',
    'smtp_port'      => 587,
    'smtp_user'      => 'your_gmail@gmail.com',
    'smtp_pass'      => 'your_NEW_gmail_app_password',
    'mail_from_name' => "Book's Library",

    // Where contact-form messages are delivered (falls back to the "contact_email" setting)
    'admin_email'    => 'your_gmail@gmail.com',
];

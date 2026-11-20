# E-Library deployment

## Credentials
Copy `config.local.example.php` to `config.local.php` on the hosting account and replace placeholders with the new database and SMTP credentials. Never commit that file. Rotate any credentials previously committed to public Git history; deleting a file in a new commit does not remove old history.

Environment variables are also supported by the app's configuration layer where configured: DB_HOST, DB_USER, DB_PASS, DB_NAME, SMTP_HOST, SMTP_PORT, SMTP_USER and SMTP_PASS.

## PHPMailer
Run `composer install --no-dev --optimize-autoloader` locally, then upload the generated `vendor/` folder to the site root with composer.json and composer.lock. Without this dependency and valid SMTP credentials, email/OTP sending cannot work.

## Database
Export a full database backup in phpMyAdmin first. Then select the E-Library database and import `database/migrations/2026-11-20-add-book-description-and-download-count.sql`. This migration adds only the two columns identified by the application error log.

## Upload limits
The example config uses a 10 MB PDF limit. Confirm InfinityFree's PHP upload_max_filesize and post_max_size before changing it.

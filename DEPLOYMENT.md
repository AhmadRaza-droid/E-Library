# E-Library deployment

## Credentials
Copy `config.local.example.php` to `config.local.php` on the hosting account and replace placeholders with the new database and SMTP credentials. Never commit that file. Rotate any credentials previously committed to public Git history; deleting a file in a new commit does not remove old history.

Environment variables are also supported by the app's configuration layer: DB_HOST, DB_USER, DB_PASS, DB_NAME, SMTP_HOST, SMTP_PORT, SMTP_USER and SMTP_PASS. For hosting panels that use the library-specific names, LIBRARY_SMTP_USERNAME, LIBRARY_SMTP_PASSWORD, LIBRARY_SMTP_HOST and LIBRARY_SMTP_PORT are accepted as SMTP fallbacks. Values in config.local.php take priority. Port 587 uses STARTTLS; port 465 uses implicit TLS.

## PHPMailer
Run `composer install --no-dev --optimize-autoloader` locally, then upload the generated `vendor/` folder to the site root with composer.json and composer.lock. Without this dependency and valid SMTP credentials, email/OTP sending cannot work.

## Database
Export a full database backup in phpMyAdmin first. Then select the E-Library database and run the migrations in `database/migrations/` in date order. The 2026-11-20 migration adds `description` and `download_count`; the 2026-11-21 migration adds missing cover, reader, EPUB, uploaded-file and source columns used by the current app. Both migrations check for existing columns before adding them and preserve existing book records. If you already ran the 2026-11-20 migration, run only the 2026-11-21 migration now.

## Book import and uploads
Online Project Gutenberg search requires PHP cURL to be enabled. The offline starter list contains direct Gutenberg book IDs; non-book search pages are rejected as reading/download links. Uploaded PDFs are stored under `uploads/pdfs/`, cover images under `uploads/covers/`, and moderated student materials under `uploads/resources/`. Make sure the hosting account allows PHP to create/write these folders. Enabling student materials creates a private `.htaccess` deny rule in its storage folder. Local PDF downloads are streamed as attachments after the file path is checked.

## Upload limits
The example config uses a 10 MB PDF limit. Confirm InfinityFree's PHP upload_max_filesize and post_max_size before changing it.

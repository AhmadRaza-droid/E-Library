# E-Library deployment notes

## Secrets and configuration

1. Copy `config.local.example.php` to `config.local.php` on the server.
2. Replace every placeholder with the database and SMTP credentials from your hosting/email provider.
3. Keep `config.local.php` out of Git. The repository now ignores it.
4. Rotate any database and Gmail app-password credentials that were ever committed to the public repository. Removing a file in a new commit does not erase it from Git history.

The application also supports environment variables named `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, and `SMTP_PASS`.

## Composer / PHPMailer

Run `composer install --no-dev --optimize-autoloader` in the project directory on a computer with Composer, then upload the generated `vendor/` directory together with `composer.json` and `composer.lock`. Shared hosting without Composer/SSH still needs the generated vendor directory uploaded through its file manager or FTP. Email/OTP delivery will remain unavailable until this dependency and valid SMTP credentials are installed.

## Database migration

1. Export a full SQL backup in phpMyAdmin first.
2. Select the E-Library database.
3. Import `database/migrations/2026-11-20-add-book-description-and-download-count.sql`.
4. Re-test the Books page and download counter.

This migration only adds the two columns identified by the committed runtime log. It does not replace or rebuild existing tables.

## Upload limits

The example configuration uses a 10 MB PDF limit to suit common free shared-hosting limits. Adjust `pdf_max_bytes` only after confirming the host's PHP upload and post-size limits.

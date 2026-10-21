# Book's Library (PHP + MySQL)

## Setup (after this security pass)
1. **Rotate every secret that was ever committed** (DB password, Gmail App Password). Old values are still in git history.
2. Copy `config.local.example.php` → `config.local.php` and fill in the NEW values. This file is git-ignored,
   so the GitHub Action does **not** upload it: put it on the server manually (FTP) next to `config.php`.
3. Fresh install: run `schema.sql`, then `create_books.100.sql`. Existing DB: read the "MIGRATE" notes at the bottom of `schema.sql`.
4. Existing users with plain-text passwords keep working: each password is upgraded to bcrypt automatically on the next login.
5. Make sure `uploads/pdfs`, `uploads/covers`, `upload/profile` are writable (their `.htaccess` blocks script execution).

## What changed (summary)
**Secrets** – no credentials in code; `config.local.php` / env vars; one `mailer.php` for all e-mail.
**Auth** – bcrypt everywhere, `random_int` OTPs with 10-min expiry and 5-try lockout, one password-reset flow (OTP must be verified for the
same e-mail before the reset form works), generic "if registered…" answers, login/OTP/reset rate limiting, session id regenerated on login,
secure cookie flags, idle timeout only for logged-in users, admin rights re-checked in the DB on every admin request.
**Injection** – every query is a prepared statement (`db_query / db_exec / db_row / db_all / db_value` in `helpers.php`); output escaped with `e()`;
chatbot no longer uses `innerHTML` with user text; search suggestions use `data-` attributes instead of inline JS.
**CSRF** – every POST needs a token (checked globally in `config.php`, fields added to all forms, header for the AJAX call);
all destructive GET links (delete user/book/review, make/remove admin, favorite, clear history…) are now POST.
**Uploads** – real MIME check, size limit, random file names, PHP execution disabled in upload folders, move result checked.
**Bugs** – registered users could never log in (hash vs plain compare); password reset never worked (session key was unset);
OTP-bypass via SQL injection; `clear_all_history` deleted everyone's notifications; profile-picture form pointed to a missing file;
bookmark note was pre-filled with placeholder text; guests were redirected to login after 30 min idle; "Clear cache" logged the admin out;
download counter read a wrong column and counted refreshes; unauthenticated `messages.php` leaked all contact messages.
**Removed** – `chat_response.php`, `test_email.php`, `import_free_books.php`, `message_process.php`, `reset_password.php`,
`sanitize.php`, `db_prepare.php`, `admin_check.php`, `dasboard.hmtl` (dead, duplicated or dangerous).
**CI** – workflow now runs `php -l` on all files before deploying and no longer uploads `.git*`, `.github`, `*.sql`, `README.md`.

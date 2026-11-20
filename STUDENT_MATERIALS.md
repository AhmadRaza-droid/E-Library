<!-- coderabbit-ai-generated:v1 -->

# Student study materials

## Deploy to an existing site

Upload this update, including `uploads/resources/.htaccess` and `student_resources.sql`, into `htdocs`. Open **Admin Panel → Student materials → Enable student uploads** once. This adds only the new table and preserves existing data. Alternatively import `student_resources.sql` into the existing database with phpMyAdmin. Do not reimport the full schema or seed on a live site. Fresh installations already include the table in `schema.sql`.

Students with verified accounts can submit a title, university, course, subject, description and file under **Study Materials → Upload material**. Uploads remain private until approval. Students track decisions and feedback in **My uploads**, also linked from their profile. Approved listings support search by title, university, course and subject. Downloads require sign-in.

## Admin review and notifications

The admin sidebar and dashboard show a persistent pending-upload count. Counts refresh once per minute while the dashboard or review page is open. This notification is inside the admin panel; email and phone push notifications are not sent. Open **Student materials** to download each submission for review, approve, reject with feedback, unpublish or permanently delete it. Rejected files are hidden from other students. New or replacement files require a fresh submission and fresh approval.

## File formats and storage

Supported: PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, TXT, JPG/JPEG and PNG. PHP ZIP is required for DOCX/PPTX/XLSX inspection; these fail closed with an error when ZIP is unavailable. File content and extension checks reject mismatches. Office ZIPs are bounded, inspected without extraction, and rejected if encrypted or containing macro parts. Arbitrary ZIPs and macro enabled extensions such as DOCM are refused. Legacy Office documents and PDFs can contain active content: validation does not replace malware scanning or admin review.

The default limit is 9 MiB, reduced to fit PHP upload and POST limits. Optional `resource_max_bytes` in `config.local.php` or `RESOURCE_MAX_BYTES` sets a limit between 1 KiB and 10 MiB. Hosting limits still apply. Each student can submit 10 files per hour and have 10 pending files. Attempts are also throttled.

Keep `uploads/resources` writable by PHP and serve through Apache with `.htaccess` enabled, as required by the existing upload folders. The included deny-all `.htaccess` protects every stored file. For another web server, deny all direct requests to `/uploads/resources/`, `student_resources.php` and `*.sql`. Random file names and authenticated downloads provide additional protection. Approved files are available to signed-in students; pending/rejected files only to their owner and admins. Downloads are attachments.

SQL backups include material metadata automatically. Back up the private upload folder separately; SQL alone cannot restore file contents. Deleting a material deletes its private file. Deleting a user preserves materials with former-student attribution; admins can still unpublish or delete them.

## Validation

Run `php tests/student_resources.php`. Run `python3 tests/student_resources.py` with `DB_*` set to an empty disposable `el_materials_*` database initialized with `schema.sql`; it creates and removes synthetic identities, files and moderation records. Existing admin and security tests remain applicable.

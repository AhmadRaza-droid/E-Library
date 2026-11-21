# E-Library staging smoke tests

## Core pages and accounts
- [ ] Home, Books and search pages load without PHP errors.
- [ ] Search by title, author, category and description.
- [ ] Register, receive OTP, verify OTP and resend OTP.
- [ ] Login, logout, password recovery and password change.
- [ ] Non-admin users cannot access admin pages or actions.

## Books and imports
- [ ] Add a book with a valid Gutenberg ID and confirm its reader opens.
- [ ] Import the offline starter list; confirm only direct Gutenberg book entries are added.
- [ ] Search/import an online public-domain book; confirm HTML or plain-text reading works.
- [ ] Confirm EPUB download uses the supplied EPUB URL or Gutenberg ID fallback.
- [ ] Confirm duplicate imports are skipped and database lookup failures are reported as failed.
- [ ] Upload one PDF and multiple PDFs with covers; confirm each book opens.
- [ ] Confirm local PDF download starts as a file download, not only an in-browser preview.
- [ ] Reject invalid file types, oversized files and malformed uploads.
- [ ] Delete an uploaded book; confirm its own PDF is removed and shared covers remain in use.
- [ ] Read remote and uploaded book files on desktop and mobile.

## User features and administration
- [ ] Favorites, reviews, bookmarks, progress and history work.
- [ ] Removing a bookmark affects only the signed-in user's bookmark.
- [ ] Admin book/user deletion cleans dependent activity records.
- [ ] Test admin backup and restore on a disposable staging database.
- [ ] Test notifications, messages, book requests and student materials.
- [ ] Test mobile navigation, theme and chatbot in English and Roman Urdu.

## Hosting checks
- [ ] Confirm InfinityFree PHP cURL is enabled before using online import.
- [ ] Confirm PHP upload_max_filesize and post_max_size support the configured PDF size.
- [ ] Confirm SMTP credentials and PHPMailer vendor files are installed before testing OTP mail.
- [ ] Confirm runtime logs and uploads are outside public Git history.

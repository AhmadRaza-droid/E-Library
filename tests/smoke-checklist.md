# E-Library smoke-test checklist

Run these checks on a staging copy before deploying to the live site.

- [ ] Home page and Books page load without PHP warnings/fatal errors.
- [ ] Search by title, author, category and description.
- [ ] Register a new account; receive and verify OTP.
- [ ] Resend OTP and confirm expiry / attempt-limit behavior.
- [ ] Login, logout, password recovery and password change.
- [ ] Admin login and role checks; non-admin cannot open admin pages.
- [ ] Add a remote book and upload a PDF with a cover image.
- [ ] Read a remote book and a locally uploaded PDF.
- [ ] Download a remote EPUB and locally uploaded PDF; confirm correct file type and name.
- [ ] Favorites, reviews, bookmarks, reading progress and history.
- [ ] Admin backup download and restore using a disposable staging database.
- [ ] Mobile navigation, dark mode, chatbot and keyboard accessibility.
- [ ] Confirm logs are stored outside the public Git repository and contain no secrets.

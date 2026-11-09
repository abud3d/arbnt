# ARBNT v0.3.0 — security trial

## Implemented in repository
- Native PHP 8.3 + JSON; no SQL, Composer, Node or file-upload endpoint.
- Password hashes, session regeneration, CSRF token, secure-origin validation, HTTP-only SameSite cookies.
- Public member registration with email verification (30-minute one-time link).
- Password recovery via 30-minute one-time email link, invalidating existing sessions.
- Login limits by IP and username, signup/recovery throttling, post/reply write throttling.
- Admin: member bans, deletion of posts/replies, counts, recent audit events.
- API HTTPS requirement, JSON content-type and body-size limits, text-only rendering, security log stored outside web root.
- JSON storage outside the site's DocumentRoot, file locking and atomic updates.

## Required before live launch
- **PHP mail() must be configured and tested** with SPF/DKIM/DMARC. Email delivery is not verified.
- **DDoS cannot be solved by PHP.** Configure web-server request limits, firewall/CSF, reverse proxy/CDN or provider network mitigation separately. None of these server settings were changed.
- Add and test automatic backups, security regression tests, account recovery edge cases, abuse protection, and full installer hardening.
- Current application is a trial. Do not publish publicly or distribute to customers until penetration testing and mail verification succeed.
- No file-upload feature exists. Do not add uploads without an allowlist, isolated non-executable storage, MIME validation, and file size limits.

## Trial installation
Use a dedicated HTTPS domain DocumentRoot, PHP 8.3+, and writable parent outside **all** publicly reachable directories. Upload project files there; open `install.php`; create the administrator; remove `install.php` immediately; open `community.html`. JSON private storage is created at `../arbnt-private` relative to the DocumentRoot. Ensure the directory is not reachable through any Apache alias, other domain or symlink. Back up private data before upgrades.

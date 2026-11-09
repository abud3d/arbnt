# ARBNT v0.3.0 — installer prototype

**Important: developer preview, not a production-ready community.** The installer initializes an administrator record, but login, sessions, posting, moderation and writes through the API are NOT implemented yet.

## Hosting assumptions
- Apache + PHP 8.3+, PHP JSON, permission to write a sibling directory to DocumentRoot.
- Place project files in the domain's DocumentRoot. The installer creates `../arbnt-private` as a sibling to the web root; verify that this directory is genuinely outside ALL public web roots and aliases before running.
- If DocumentRoot is `/home/account/public_html`, private storage becomes `/home/account/arbnt-private`. If DocumentRoot is `/home/account/domain`, storage becomes `/home/account/arbnt-private`.
- Do not use a subdirectory of a shared web root without changing the private-storage design.
- Open `/install.php` and create a site name and initial administrator credential.
- Installer is locked by `installed.lock`. Remove `install.php` after successful installation.
- Read-only API: `/api/index.php?resource=posts` and `?resource=communities`.
- Do not expose the API to public traffic as a community platform until authentication, CSRF, moderation, and security testing are implemented.
- ZIP packaging and one-click update/backup workflows are not yet implemented.

## No database
Uses JSON files with exclusive locks and atomic rename for future write operations. Backup the entire private directory securely.

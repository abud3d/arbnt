# ARBNT v0.3.0 — limited server trial

The community MVP now supports **administrator and member login, public registration, creating posts, public reading, and logged-in replies, plus basic admin moderation**, with JSON storage and no database. **This is not production-ready for arbitrary customers.** Account recovery, email verification, spam controls, backup tooling, and security/load testing remain.

## Trial setup
1. Use a fresh isolated domain DocumentRoot on Apache with PHP 8.3+.
2. Upload the repository files into that DocumentRoot, **not** into a subdirectory of an existing website.
3. Confirm that `dirname(DocumentRoot)/arbnt-private` is **outside every web-accessible directory or alias**, and writable by the site's PHP account. The installer creates it with permissions 0700.
4. Visit `/install.php`, choose site name, administrator username and a strong password.
5. **Delete install.php immediately after installation.**
6. Open `/community.html`, log in with the administrator credentials, create a post and reply.
7. API endpoints: `/api/community.php?action=session`, `?action=posts`, `?action=comments&post=ID`.
8. Use HTTPS, restrict trial access to trusted testers and back up the private JSON directory.

## Limitations
- Public registration is enabled without email verification. Limit access to trusted testers until anti-spam and email verification are implemented.
- Anyone can read posts and replies; logged-in members can publish and reply. The administrator can ban members and delete posts/comments.
- All posts are plain text; no HTML, files or attachments.
- Do not delete or overwrite `arbnt-private` on code upgrades.
- Never run the installer inside a shared document root or with an exposed private storage path.
- This is a first functional build and needs end-to-end testing before release as an installable product.

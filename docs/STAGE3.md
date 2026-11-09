# ARBNT — Stage 3 architecture (approved 2026-11-09)

## Deployment
- Production: user's own CWP/Apache server, deployed only after user approval.
- GitHub: source control, not production hosting.
- HTML/CSS for pages, minimal browser JavaScript for interactive UI.
- Native PHP 8.3 for API endpoints only, without frameworks.
- JSON files for initial storage; no MariaDB or Node.js.

## Safety requirements before public write access
1. Place writable data outside DocumentRoot; never commit live user data.
2. Verify directory ownership and permissions on the actual server before deployment.
3. Use session-based authentication, password_hash/password_verify and CSRF tokens.
4. Strict request-size and text-length limits; validate content type and UTF-8.
5. Escape user text on output; never render untrusted HTML.
6. Atomic JSON writes using flock plus temporary files/rename; avoid lost updates.
7. Rate limits per user/IP, abuse reporting, moderation, and server-side authorization.
8. No anonymous publishing until moderation and anti-spam controls are tested.
9. Backups, log rotation, pagination and migration strategy as data grows.
10. API errors must not disclose filesystem paths or sensitive configuration.

## Delivery sequence
- 3.1 Read-only API: communities and posts from sample JSON.
- 3.2 Safe authoring API and session registration.
- 3.3 Replies, voting, favorites, profile, moderation.
- 3.4 Integration tests and load/safety review.
- 3.5 Manual user-approved server deployment.

## Explicitly excluded
MariaDB, Node.js, React, Laravel and heavy dependencies.

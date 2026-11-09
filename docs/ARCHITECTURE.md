# ARBNT — Foundation Architecture

**Draft only — not an approved runtime implementation.**

## Unified engine
1. **Objects:** stable IDs, owner, content, metadata and state.
2. **Relations:** replies, participants and links between objects.
3. **Capabilities:** comments, messaging, rating, purchasing, booking.
4. **Access:** per-object, per-message and internal-note permissions.
5. **Workflow and events:** explicit state transitions and platform notifications.
6. **Pages and blocks:** configurable layouts, headers, footers and sidebars.
7. **Storage:** default JSON/file adapter, optional SQL adapters.

## Data safety
Store private records outside the public document root. Use partitioned rebuildable indexes, independent stable lock files, bounded flock waits, same-filesystem temporary writes, atomic rename and documented recovery for multi-file operations. Verify backups and test restores.

## Rendering
Prefer generated public HTML and versioned assets where practical. Private messages, tickets, admin pages and personalized content must not be publicly cached.

## Messaging and support
People can initiate messages, support tickets and internal conversations according to permissions. Guest access uses high-entropy, expiring, revocable tokens stored hashed. Account claiming requires verified ownership. Email notifications are for human messages and platform events, not automated server monitoring.

## Security
Secure sessions, CSRF defenses, output escaping, least privilege, rate limiting, upload validation, safe permissions, auditing and fail-closed installation.

## Foundation scope
Documentation and design only. Implementation, license and deployment require owner approval.

# ARBNT — Development Agent Instructions

**Current stage: Foundation. Documentation only.**

Read README.md and docs/ARCHITECTURE.md before proposing changes. Do not add runtime code, dependencies, licenses, or deploy without explicit owner approval.

## Technology
- Native PHP 8.3+, HTML/CSS first, optional vanilla JavaScript.
- No required framework, Composer, Node.js or SQL database.
- File-based JSON storage by default; optional database adapters.
- One object model with relationships, capabilities, granular access policies and configurable page blocks.

## Security and correctness
Authorize every object/message action; use CSRF and XSS defenses, secure sessions and password hashing, rate limits, safe uploads, and audit logs. Store private data and secrets outside webroot. File writes require stable independent locks, bounded waits, atomic replacement and recovery planning. Never publicly cache private data.

Human messaging, tickets, and secure guest replies are in scope. Guest links must be high-entropy, expiring, revocable and stored hashed. Matching email alone is not identity proof. Server-load monitoring is NOT a messaging feature.

## Process
Make small reviewable changes; document decisions, tradeoffs and tests. Never modify unrelated repositories or servers. Require explicit approval before deployment. Report the GitHub Actions run link after validation.

## Open decisions
License, detailed data contracts, privacy/retention policy, hosting tiers and initial MVP scope.

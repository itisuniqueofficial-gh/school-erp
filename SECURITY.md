# Security Policy

## Reporting a vulnerability

Please report security issues privately to **security@itisuniqueofficial.com**
(or open a GitHub *Security advisory*). Do **not** open a public issue for
vulnerabilities. We aim to acknowledge within 72 hours.

Include: affected version/commit, steps to reproduce, impact, and any PoC.

## Supported versions

The latest `1.x` release on `main` receives security fixes.

## Secret hygiene

- No secrets are committed. Configuration comes from environment variables /
  `.env` (git-ignored); only `.env.example` is tracked.
- CI (`.github/workflows/ci.yml`) fails the build if a `.env` is tracked or if
  common secret patterns (GitHub tokens, AWS keys, private keys) appear in the
  tree. Dependabot watches dependencies.
- Payment gateway credentials and government IDs are **encrypted at rest**
  (AES-256-GCM via `Core\Security\Crypto`, keyed by `APP_KEY`). Never place live
  payment secrets in plain environment variables.

## Application security controls

- **Auth**: bcrypt password hashing, session regeneration on login, remember-me
  tokens hashed at rest, account lockout + rate limiting on login.
- **RBAC**: permissions enforced server-side on every route/controller/API
  endpoint and in queries — never UI-only.
- **Web**: CSRF tokens on state-changing requests, output escaping (XSS),
  prepared statements (SQL injection), CSP + security headers, secure cookies,
  optional HTTPS enforcement.
- **Uploads**: real-MIME validation, size limits, image re-encoding to strip
  payloads, storage outside the web root, authenticated file serving.
- **Errors**: production never exposes stack traces, file paths, SQL, or
  credentials; a friendly 503 page is shown when the database is unavailable.
- **Audit**: logins, CRUD, permission and settings changes recorded in
  `audit_logs` (viewable at `/admin/audit`) with user, IP and user-agent.

## Deployment hardening

- Set `APP_ENV=production`, `APP_DEBUG=false`, a stable `APP_KEY`,
  `SESSION_SECURE_COOKIE=true`, and remove `public/install.php` after install.
- Grant the database user least privilege on the app schema only.
- Upgrades are non-destructive (`php bin/console upgrade`); `migrate:fresh` is
  blocked in production.

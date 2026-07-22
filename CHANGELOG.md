# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/), and this project adheres to
Semantic Versioning.

## [1.2.0] - 2026-07-22

### Added
- **Login by 12-digit User ID** (in addition to email/username), with a fixed
  validator size bug for numeric logins.
- **Payment gateways** (Razorpay / PhonePe / MobiKwik): admin config with
  credentials **encrypted at rest**, enable/disable + test/live per gateway,
  server-side signature + webhook HMAC verification, and a `payment_transactions`
  ledger with idempotency / duplicate-payment protection.
- **ERP Configuration** (institution-agnostic): institution type/board, optional
  **module enable/disable** with server-side route enforcement (`module:`
  middleware), configurable **terminology**, and **DB-backed branding**
  (name/footer/login copy) — no template edits.
- **Audit Log viewer** (`/admin/audit`) with filters + pagination.
- **Profile pictures / avatars**: secure GD pipeline (real-MIME validation +
  re-encode + thumbnail), admin & self-service, authenticated media serving.
- **Full Section CRUD**, **dependency-aware safe delete** for academic entities,
  and immutable **12-digit exam IDs**.
- **Deployment**: production `Dockerfile`, `koyeb.yaml`, `docker/entrypoint.sh`,
  `deploy/nginx.conf.example`, `deploy/Caddyfile.example`,
  `KOYEB_DEPLOYMENT.md`, `ENVIRONMENT_VARIABLES.md`.
- **Portability**: proxy-aware `base_url()` auto-detection (blank `APP_URL`
  auto-detects scheme/host/subdirectory), storage auto-creation on boot.
- **Ops**: `GET /health` JSON endpoint (200/503), `php bin/console health`,
  `php bin/console upgrade` (backup + pending migrations only), DB connection
  retry, credential-safe 503 database-unavailable page.
- **CI/CD**: GitHub Actions `ci.yml` (lint + composer validate + secret hygiene
  + integration tests on a MySQL service) and `release.yml` (versioned artifacts
  + release notes on `v*` tags); Dependabot.

### Changed
- Schema is now **77 tables** (added `payment_transactions`, teaching-assignment
  history, workflow tables, exam `uid`, universal 12-digit `uid`s).
- `migrate:fresh` is **refused in production** without `--force`; migrations are
  additive-only and applied via the safe `upgrade` path.
- Default `APP_URL` is empty (auto-detect); branding/name sourced from the DB.

### Security
- Payment credentials encrypted (AES-256-GCM); no secrets committed (CI check).
- DB errors never leak stack traces/paths/credentials in production.

## [1.1.0] - 2026-07-22

### Added
- **Installation wizard** (`public/install.php`): browser-based, self-locking,
  with requirement checks, DB setup, admin creation and school profile.
- **Brute-force protection**: `login_attempts` tracking + lockout after 5 fails.
- **Password policy** rule (8+ chars, upper/lower/digit) on all password forms.
- **Semi-automation**: `CodeGenerator` (Code 39 barcodes + code sequences),
  `ReminderService` (birthday/fee-due/exam/holiday notifications), and console
  commands `install`, `reminders`, `cleanup:logs`, `cleanup:sessions`.
- **Backup manager** UI (create/download/restore/delete) + `BackupService`.
- **Maintenance mode** middleware (`APP_MAINTENANCE`).
- **Responsive design system**: design tokens, fluid typography, 44px touch
  targets, mobile card-view tables, ultrawide caps; client-side form validation.
- **Docs**: INSTALL, CPANEL_DEPLOYMENT, VPS_DEPLOYMENT, API_DOCUMENTATION,
  USER_GUIDE, ADMIN_GUIDE, DEVELOPER_GUIDE.
- `update_logs` table for the update system.

### Changed
- Schema is now **68 tables** (added `login_attempts`, `update_logs`).
- Front controller redirects to the installer until installation completes.

## [1.0.0] - 2026-07-21

### Added
- **Core framework**: service container, PSR-4 autoloader (Composer-optional),
  environment + configuration loaders, HTTP request/response abstractions,
  session manager, router with groups/middleware, native-PHP view engine.
- **Database layer**: PDO connection manager, fluent query builder with
  prepared statements, base ActiveRecord-style model, pagination.
- **Security**: CSRF protection, bcrypt hashing, CSP + security headers,
  rate limiting, secure session cookies, input validation.
- **Authentication**: session login/logout, remember-me, password reset,
  API bearer-token authentication.
- **RBAC**: roles, permissions, role/permission middleware, policy enforcement.
- **Modules**: Students, Teachers, Staff, Academic, Attendance, Examinations,
  Fees, Library, Transport, Communication, Reports, Settings, HR.
- **REST API**: versioned `/api/v1` endpoints with pagination, filtering,
  sorting, and token auth.
- **Frontend**: Tailwind CSS, Font Awesome, jQuery, GSAP; responsive light-mode UI.
- **Tooling**: CLI console (`migrate`, `seed`, `key:generate`, `backup`),
  PHPUnit tests, migrations and seed data.
- Documentation: README, installation, deployment, and API guides.

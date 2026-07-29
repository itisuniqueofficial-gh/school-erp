# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/), and this project adheres to
Semantic Versioning.

## [1.6.5] - 2026-07-29

### Fixed
- **Completed the MySQL 1071 fix for strict 767-byte servers** (MySQL 5.6 /
  MariaDB with `innodb_large_prefix` off, where `ROW_FORMAT=DYNAMIC` alone is
  not enough). Audited every index's real prefix byte-length and brought all
  within 767 bytes:
  - `migrations` tracking table (created by the Migrator before any migration):
    indexed `migration` column `VARCHAR(255)`→`VARCHAR(191)` + `ROW_FORMAT=DYNAMIC`
    — this was the *first* statement to fail on a strict server.
  - `external_mappings.uq_extmap_provider_type_external` composite (was 1084
    bytes): now indexes a 100-char prefix of `external_id` (column stays
    `VARCHAR(191)`, no data-capacity loss).
  - `books.idx_book_title`: indexes a 191-char prefix of `title` (column stays
    `VARCHAR(255)`).
- Added `tests/integration_dbcompat.php`: measures every index's prefix bytes
  from `information_schema` and fails if any exceeds 767 — a permanent guard so
  a fresh install cannot hit error 1071 on any MySQL 5.6+/MariaDB 10.x host.

## [1.6.4] - 2026-07-29

### Fixed
- **MySQL error 1071 (“Specified key was too long; max 767 bytes”)** on
  installation/migration against MySQL 5.7 / MariaDB / servers defaulting to a
  non-DYNAMIC InnoDB row format. All `CREATE TABLE` statements (migrations +
  `schema.sql`) now declare `ROW_FORMAT=DYNAMIC`, which raises the index prefix
  limit to 3072 bytes so `utf8mb4` indexed columns install cleanly everywhere.
  Purely a DDL/table-creation change — no data or column sizes altered.

## [1.6.3] - 2026-07-23

### Added
- **Public/private media split**: branding assets (logo, favicon, stamp,
  signature) now serve via a no-auth `/media/branding/{file}` route that only
  serves files registered as branding settings — fixes the favicon/login logo
  not loading, while student/teacher photos stay behind auth on `/media/avatar`.
- **Branding assets**: uploadable Official Stamp + Authorized Signature (shared
  AvatarService pipeline), rendered on certificate & hall-ticket PDFs with
  “Stamp Pending” / “Signature Pending” fallbacks.
- **Logo across portals**: sidebar + login page now show the uploaded logo
  (glyph fallback).
- **Historical branding snapshot** on issued certificates (migration 027): a
  certificate keeps the branding in effect when it was issued, even if the
  school later changes its name/logo.
- **Holiday categories** (migration 028) + a configurable **working week** and
  **`CalendarService`**: attendance marking is now blocked on holidays and
  non-working days.
- **Global Attendance & Calendar settings** page (Settings → Attendance &
  Calendar): attendance type, working week, and an auto period-schedule
  generator with server-side validation.
- **Global + Local overrides** (migration 029, `scope_settings`): each
  class/section can override the working week / times / period structure
  (section › class › global) without affecting other scopes.
- **`db:check`** read-only database integrity audit CLI, and **backup
  verification** + guarded **restore** CLI (from the same release line).

### Fixed
- `migrate:fresh` guard now allows disposable/test contexts (`APP_ENV=testing`,
  `CI=true`, `APP_TESTING=1`) while still protecting real local/production data.

## [1.6.2] - 2026-07-23

### Fixed
- `migrate:fresh` guard no longer refuses in disposable/test contexts
  (`APP_ENV=testing`, `CI=true`, or `APP_TESTING=1`), which had broken the CI
  integration-test workflow after the v1.6.1 data-protection hardening. It still
  refuses on production and on a populated non-`_test` database in local/dev
  environments (real-data protection unchanged).

## [1.6.1] - 2026-07-23

### Added
- **Centralized profile-photo upload**: student create/edit/profile (and
  teacher/staff) now share one `AvatarService` pipeline — the Edit page no
  longer uses a divergent handler that stored files where the UI never looked.
- **School branding logo**: upload/replace/remove in Settings, rendered on every
  generated PDF (certificates, report cards, hall tickets) via the shared
  `PdfDocument` engine, with a centered, aspect-preserved header layout.
- **Dynamic favicon** across the whole ERP, driven by the branding logo with
  cache-busting (`?v=`) and a safe inline SVG fallback (fixes missing favicon).
- **Backup verification** (`BackupService::verify`) + `backup:verify` CLI, and a
  guarded `restore` CLI that verifies the dump and refuses to overwrite a
  populated non-`_test` database without `--force`.
- **Startup data-safety guard**: HTTP requests are stopped with a clear 503
  critical page (with reference id + how-to-fix) when the database is
  unavailable or unmigrated — never silently serving empty data.
- **System status** page (`/admin/system/status`) and a dashboard
  "Needs your attention" integrity panel.
- **`FileValidator`** service (extension/MIME/magic-byte/size + polyglot &
  executable rejection) wired into document + avatar uploads.
- **Migration tooling**: snapshot capture + data-loss comparison
  (`snapshot` / `snapshot:compare`), Postgres connection block, and
  `DATA_PERSISTENCE.md` / `MIGRATION.md`.
- **Class-subject typing** (migration 026): subject type, mandatory flag, and
  ordering with an idempotent assignment/reorder/copy service.

### Fixed
- Capability-aware image handling: JPG/WebP no longer rejected as "not a valid
  image" on PNG-only GD builds (validated via `getimagesize`, stored safely).
- Certificate/document header logo placement (was fixed top-left, now balanced).
- Edit forms preserve the existing photo when no new file is selected; the old
  file is replaced only after a successful DB update (no orphans, no data loss).
- Errors now carry a private reference id with safe user-facing messages (+503).

### Safety
- Test-database isolation: `APP_TESTING=1` transparently targets `<db>_test`;
  `migrate:fresh` refuses to drop a populated non-`_test`/production database.

## [1.6.0] - 2026-07-23

### Added
- **Hall Ticket / Admit Card module** (Examinations): 10 document types, real
  eligibility (class roster; back-paper/supplementary/repeater = failed students),
  duplicate-safe bulk issuance, professional single-page PDF with QR, admin
  management, student dashboard access, and public QR verification.
- **Class-wise exam scheduling engine**: schedule a class's subjects
  (date/time/marks) in one screen with conflict detection, copy-from-another-exam,
  and create-time validation (end >= start, no duplicate exam in year+type scope).
  Feeds hall tickets, marks and report cards from the same records.
- **Universal “Go to Record by ID”** admin tool with canonical ID normalization
  (case/space/hyphen tolerant), archive-inclusive lookup, and a clear
  found / access-denied / archived / not-found distinction.
- **Government sync backbone**: external-ID mapping + sync-job tracking +
  duplicate-safe import engine + provider operation-direction gating + dashboard.
- **Deployment-safe installer**: fresh-vs-existing detection (`InstallService`),
  guarded `install`, and `install:status` (never reseeds a live database).

### Changed
- **Unified official-document engine** (`PdfDocument`): certificates and report
  cards now share one header, verification band, typography and a configurable
  brand accent colour. Server-side QR embedded directly in PDFs.
- Certificate & report-card **preview now equals the PDF exactly** (inline embed).

### Fixed
- Certificate PDF is single-page (no orphan verification page) and free of
  duplicated wording (e.g. “Class Class 1”).

## [1.5.1] - 2026-07-23

### Fixed
- **Certificate & report-card preview now matches the PDF exactly.** Replaced the
  separate HTML preview + PDF layout with a single source of truth: the preview
  embeds the exact generated PDF inline (`?inline=1`), so what you see is
  byte-for-byte identical to the download.
- Fixed duplicated wording such as “Class Class 1” in certificate bodies and
  ensured long names/addresses wrap correctly.

### Added
- **Server-side QR codes embedded directly in certificate and report-card PDFs**
  (bundled dependency-free `phpqrcode` engine + `QrService`), plus an official
  stamp area and signature block on certificates.

## [1.5.0] - 2026-07-23

### Added
- **Professional report cards & marksheets**: branded, print-ready individual
  report cards with server-side **one-click PDF download** (bundled FPDF engine),
  full subject-wise marks, grade/GPA/division, year-scoped attendance summary and
  live QR verification. Available to Admin, Students (own only) and Parents
  (linked children only) with strict authorization.
- **Digital Certificate System**: issue Bonafide / Transfer / Leaving / Character
  / Study / custom certificates with unique IDs (`CERT-YYYY-NNNNN`),
  auto-populated bodies from real data, professional PDF, public QR verification,
  and revocation (record preserved).
- **Certificate request & approval workflow**: students request a certificate
  (type + purpose), staff review and approve/reject; approval auto-issues the
  real certificate and the student downloads it. Full status timeline + audit.
- **Universal "Go to Record by ID"** admin tool: resolve any unique ID
  (student/staff public IDs, admission no, 12-digit ID, `CERT-`/`RC-`/`REQ-`)
  straight to its record, audit-logged and permission-aware.

### Changed
- Schema now includes `report_card_versions`, `certificates`, and
  `certificate_requests` (migrations 021–023, all additive).

## [1.4.0] - 2026-07-23

### Added
- **Automated student promotion & academic-year rollover**: exam-based
  eligibility engine, automatic next-class mapping (by numeric level), and a
  review/approve/apply workflow. Transaction-safe, idempotent, history-preserving
  (new enrolments + roll numbers; prior enrolment kept + marked promoted).
- **Bulk student actions** (activate / deactivate / archive / restore).
- **Report cards with unique IDs, versioning & QR verification**: each report
  card gets an immutable `RC-YYYY-NNNNN` id; regeneration creates a new version
  (history preserved in `report_card_versions`); a public
  `/verify/report-card/{id}` page confirms authenticity without exposing marks.
- **Setup Center approval-queue checks**: documents awaiting verification and
  attendance unlock requests pending review.
- **Automated releases**: pushing a version bump to `main` auto-publishes a
  GitHub Release with all platform packages (`.github/workflows/auto-release.yml`).

### Changed
- Schema is now **79 tables** (added `report_card_versions`).

## [1.3.0] - 2026-07-22

### Added
- **Government integration framework** (future-ready): modular adapter registry
  for UDISE+, APAAR, ABC, DigiLocker, NAD, State EMIS and Scholarship portals
  — credentials **encrypted at rest**, enable/disable + sandbox/live per provider,
  masked admin config, and a `government_sync_logs` ledger (pending/success/
  failed/retry). No external APIs are hardcoded. Admin → Settings → Government
  Integrations.
- **Student digital-identity status**: per-student APAAR/ABC/DigiLocker
  verification status + extra national identifiers (state student id, Samagra id,
  EMIS number, board registration no, DigiLocker ref).
- **Bulk student actions**: activate / deactivate / archive (soft-delete) /
  restore from the students list — server-side gated, audited, non-destructive.
- **Setup Center approval-queue checks**: “documents awaiting verification” and
  “attendance unlock requests pending review”.

### Changed
- Schema is now **78 tables** (added `government_sync_logs`; students gained
  digital-identity status + national-id columns).

### Security
- Government-integration credentials encrypted (AES-256-GCM); never rendered.

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

# ADMIN_GUIDE.md — Administrator Guide

Covers configuration, maintenance, security and recovery for administrators
(Super Admin / School Admin).

## Roles & access control
- **Roles & Access** (`/admin/roles`): create roles and assign permissions via a
  module-grouped checkbox matrix. System roles cannot be deleted.
- Permissions follow `module.action` (e.g. `students.create`, `fees.collect`).
- **Super Admin** bypasses all permission checks.
- Assign roles to users when creating students/teachers (login accounts) or via
  seeding. Every route and API endpoint is enforced server-side.

### Built-in roles
`super_admin, school_admin, principal, vice_principal, teacher, class_teacher,
accountant, librarian, receptionist, transport_manager, hr, student, parent`.

## School configuration
- **Settings** (`/admin/settings`): school name, email, phone, address, currency,
  timezone, academic options. Values are stored in the `settings` table and
  editable inline.
- **ERP Configuration** (`/admin/settings/institution`): make the platform fit
  any institution without code changes —
  - **Institution profile**: name, code, type (Government / Aided / Private /
    Coaching / Training / …) and board (CBSE / CISCE / State / IB / Cambridge /
    NIOS / …).
  - **Modules**: enable/disable optional modules (Fees, Payments, Scholarships,
    Library, Transport, Communication, HR, Documents, Timetable). Disabled
    modules are hidden from navigation **and blocked server-side** (403) — no
    data is deleted.
  - **Terminology**: rename entities (Student→Learner, Class→Course, …).
  - **Branding**: name, short name, footer text, copyright, support email/phone,
    login welcome/description — reflected across the ERP (title, sidebar, footer,
    login) with no template edits. `APP_URL` blank = auto-detect.
- **Payment Gateways** (`/admin/settings/payments`): enable/disable Razorpay,
  PhonePe and MobiKwik independently, set test/live mode and credentials.
  Secrets are **encrypted at rest** and never shown in full; a gateway can only
  be enabled once its required credentials are complete. Online payments are
  always verified server-side (signature + webhook HMAC); the transactions
  ledger prevents duplicate/settled-twice payments. Offline methods (cash,
  cheque, UPI, bank transfer) work without any gateway.
- **Academic Years**: create years and set the *current* one (used across modules).

## User management
- Create staff/teacher accounts (optionally with logins) and student/parent
  accounts. Default password for generated accounts is `Password123!` — instruct
  users to change it on first login.
- **User Management** (`/admin/users`): list users with their 12-digit User ID,
  role and status; activate/deactivate.
- Every user has a **unique 12-digit User ID** and can sign in with **User ID,
  email, or username** + password.
- **Profile pictures**: admins can upload/replace/remove a teacher's photo
  (Teachers → profile); every user can manage their own via **My Profile**
  (`/account`). Images are validated and re-encoded server-side (JPG/PNG/WebP).
- Deactivate a user by setting them inactive (login is refused).

## Maintenance mode
- Set `APP_MAINTENANCE=true` in `.env` to show a maintenance page to everyone
  except signed-in super admins (and the `/login` route). Set back to `false`
  to restore service.

## Backups & recovery
- **Backups** (`/admin/backups`): create, download, restore or delete SQL dumps.
  Also available on the CLI: `php bin/console backup`.
- **Restore**: uploads are overwritten — always download a fresh backup first.
  CLI restore is via `mysql < file` (see `BackupService`).
- **Scheduling**: add cron jobs (see CPANEL_/VPS_DEPLOYMENT) to run
  `php bin/console backup` nightly. Store off-server copies for disaster recovery.

## Automation (semi-automated tasks)
Run via cron (`php bin/console <cmd>`):
| Command | Purpose |
|---------|---------|
| `reminders` | Birthday, fee-due, exam and holiday notifications |
| `backup` | Database dump to `storage/backups` |
| `cleanup:logs [days]` | Remove logs older than N days (default 30) |
| `cleanup:sessions` | Remove expired session files |
Auto-generated: admission/employee/invoice/receipt/roll numbers, barcodes,
timestamps, audit logs, dashboard statistics.

## Logging & audit
- **Audit Log viewer** (`/admin/audit`): browse the immutable trail with filters
  for action, entity type, actor/description search, and date range (paginated).
- **Audit trail**: `audit_logs` records logins, CRUD actions and security events
  with user, IP and user-agent.
- **Login history**: `login_attempts` records every attempt (brute-force data).
- **Application logs**: `storage/logs/erp-YYYY-MM-DD.log`; rotate/clean with
  `cleanup:logs`.

## Security recommendations
- Keep `APP_DEBUG=false` in production.
- Enforce HTTPS and set `SESSION_SECURE_COOKIE=true`.
- Change all default passwords; use strong passwords (enforced: 8+ upper/lower/digit).
- Review audit logs periodically.
- Restrict the database user to the app's database only.
- Remove `public/install.php` after installation.
- Keep regular, off-server backups.

## Updates & safe deployment
- Version is shown in the footer (`config/app.php` → `version`).
- **Zero data loss**: deploy with `php bin/console upgrade` — it takes an
  automatic backup, then applies **only pending** (additive) migrations; it
  never drops, truncates, seeds, or re-creates data. Re-running is a no-op.
- `migrate:fresh` (which drops all tables) is **refused when `APP_ENV=production`**
  unless `--force` is given.
- Verify any environment with `php bin/console health` or `GET /health`.
- `update_logs`/`migrations` track applied versions. Back up before upgrading;
  restore from a backup (Admin → Backups) to roll back.

## Troubleshooting workflows
| Problem | Steps |
|---------|-------|
| Users locked out | Wait for the lockout window or clear their `login_attempts` rows |
| Emails not delivered | Verify `MAIL_*`; check `communication_logs` for status/errors |
| Slow pages | Enable OPcache; confirm DB indexes; check server resources |
| Data issue after import | Restore the most recent good backup |
| Permission denied unexpectedly | Review the user's role → permissions in *Roles & Access* |

# INSTALL.md — Installation Guide

School ERP can be installed two ways: the **browser-based wizard** (recommended
for non-technical users) or the **command line**.

## System requirements

- PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`
- MySQL **8.0+** / MariaDB **10.6+**
- Apache (`mod_rewrite`) / LiteSpeed / Nginx
- ~50 MB disk + database space to start

## Folder permissions

The `storage/` directory (and its subfolders) must be writable by the web server:

```bash
chmod -R 775 storage
chown -R www-data:www-data storage   # use your web-server user
```

Writable paths: `storage/logs`, `storage/cache`, `storage/sessions`,
`storage/uploads` (and `uploads/avatars`, `uploads/documents`), `storage/backups`.

> The app **auto-creates** any missing storage subfolders on boot, so a fresh
> clone works even without manual `mkdir` — you still need `storage/` itself to
> be writable by the web-server user.

---

## Option A — Browser installation wizard (recommended)

1. Upload/clone the project and point the web server document root at **`public/`**.
2. Visit `https://your-domain/install.php` (or just load the site — it redirects
   to the installer until installation completes).
3. **Step 1 – Requirements**: the wizard checks PHP version, extensions and
   writable folders. Resolve anything marked ✗ and reload.
4. **Step 2 – Database**: enter MySQL host, port, database name, username and
   password. The database is created automatically if it does not exist and the
   connection is tested before continuing.
5. **Step 3 – Administrator & school**: enter the school name, timezone,
   currency, and the super administrator name/email/password (min 8 chars).
6. **Step 4 – Finish**: the wizard writes `.env` (with a generated `APP_KEY`),
   imports the schema, seeds baseline data, creates your admin account and locks
   itself (`storage/installed.lock`). Browser installations do **not** create
   demo teacher/student/parent accounts.
7. **Delete `public/install.php`** afterwards for extra safety. Even if it remains,
   it refuses to touch a database that already contains users.

The installer shows clear success/failure messages at every step. Every form is
CSRF-protected, `.env` changes are backed up and written atomically, and raw SQL
errors are never shown in the browser.

---

## Option B — Command line

```bash
cp .env.example .env
php bin/console key:generate
# edit .env with DB credentials, APP_ENV=production (leave APP_URL blank to auto-detect)
php bin/console install       # runs migrate + seed
# then create the lock so the installer is disabled:
echo "installed" > storage/installed.lock
```

Individual commands: `php bin/console migrate`, `php bin/console seed`,
`php bin/console health` (verify), `php bin/console upgrade` (safe re-deploy),
`php bin/console install:status` (fresh vs existing database).
You may also import `database/schema.sql` directly, then run `seed`.

The CLI `seed` / `install` commands include demo accounts for local testing;
reset the super-admin password before exposing such an environment.

---

## Environment configuration (`.env`)

| Key | Purpose |
|-----|---------|
| `APP_ENV` | `production` in production |
| `APP_DEBUG` | `false` in production (never expose stack traces) |
| `APP_URL` | Public URL, e.g. `https://school.example.com`. **Leave blank to auto-detect** the scheme/host/subdirectory from each request (proxy-aware) |
| `APP_BASE_PATH` | URL subdirectory prefix when the app is mounted below the domain root, e.g. `/erp` for `http://localhost/erp`. Usually auto-detected; set it explicitly for XAMPP `htdocs/erp` or Alias setups where Apache rewrites hide the prefix |
| `APP_KEY` | Generated automatically by the wizard / `key:generate` |
| `APP_MAINTENANCE` | `true` to enable maintenance mode |
| `DB_*` | Database connection |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `MAIL_*`, `SMS_*`, `PAYMENT_*` | Optional integrations (pluggable) |

## Creating the first administrator

The wizard creates it and does not install demo users. On CLI installs the
seeder creates `superadmin@school.test` / `Password123!` — **change this
immediately** via *My Profile → Change Password*. Users can sign in with their
**12-digit User ID**, email, or username.

## Reinstalling, existing data & lost administrator access

The **live database is the source of truth**, not `storage/installed.lock`.
Deleting the lock does not make the public installer overwrite an installation:
if the configured database already has users, `public/install.php` refuses to
continue. It also refuses if the submitted database contains users.

- **Upgrade an existing install:** `php bin/console upgrade` (backup + additive
  migrations only). It never drops, truncates or reseeds data.
- **Check state:** `php bin/console install:status`.
- **Lost the super-admin password:** run on the server (never via the web):

  ```bash
  php bin/console admin:recover
  php bin/console admin:recover --email=admin@example.com
  php bin/console admin:recover --email=admin@example.com --create --name="Owner"
  ```

  The command prompts for a new password (or generates one), asks for explicit
  confirmation, resets **only** that account, reactivates it, ensures the
  `super_admin` role, revokes its remember-me tokens and writes an audit entry.
  All other users, students, fees, uploads and settings are preserved.
- **Intentionally start over:** take a verified backup, create/choose a
  **different empty database**, then run the installer against that database.
  Dropping the live database is a separate, destructive operation and is never
  performed by the web installer.

## Deploying to Docker / Koyeb / any host

The project ships a production `Dockerfile`, `koyeb.yaml`, `docker/entrypoint.sh`
(auto storage + safe `upgrade` on boot), and `deploy/nginx.conf.example` /
`deploy/Caddyfile.example`. Full guides: `KOYEB_DEPLOYMENT.md` and
`ENVIRONMENT_VARIABLES.md` (project root). Verify any environment with
`php bin/console health` or the `GET /health` JSON endpoint.

## Common problems & troubleshooting

| Symptom | Fix |
|---------|-----|
| Blank page / 500 on first load | Ensure `storage/` is writable and `.env` exists |
| "Database connection failed" | Verify `DB_*` values; confirm MySQL is reachable |
| Routes return 404 on Apache | Enable `mod_rewrite`; set `AllowOverride All` |
| Installer keeps reappearing | Ensure `storage/installed.lock` is writable, or check `php bin/console install:status`. If the DB has users the app self-heals the lock |
| Installer says "Existing installation detected" | This is intentional. Use `php bin/console admin:recover` for a lost admin; never reinstall over live data |
| Installer says "Already installed" but the DB is empty | A stale `storage/installed.lock` remains. Confirm with `php bin/console install:status`, then remove the lock only if the DB has no users |
| Database user cannot create a database | Create it in phpMyAdmin/cPanel first, then rerun the wizard with that database name |
| Uploads fail | Check `storage/uploads` permissions and `UPLOAD_MAX_SIZE` |
| Emails not sent | Configure `MAIL_*`; default driver only logs messages |

See `CPANEL_DEPLOYMENT.md` and `VPS_DEPLOYMENT.md` for host-specific guides.

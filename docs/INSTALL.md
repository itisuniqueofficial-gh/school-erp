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
`storage/uploads`, `storage/backups`.

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
   itself (`storage/installed.lock`).
7. **Delete `public/install.php`** afterwards for extra safety.

The installer shows clear success/failure messages at every step.

---

## Option B — Command line

```bash
cp .env.example .env
php bin/console key:generate
# edit .env with DB credentials, APP_URL, APP_ENV=production
php bin/console install       # runs migrate + seed
# then create the lock so the installer is disabled:
echo "installed" > storage/installed.lock
```

Individual commands: `php bin/console migrate`, `php bin/console seed`.
You may also import `database/schema.sql` directly, then run `seed`.

---

## Environment configuration (`.env`)

| Key | Purpose |
|-----|---------|
| `APP_ENV` | `production` in production |
| `APP_DEBUG` | `false` in production (never expose stack traces) |
| `APP_URL` | Public URL, e.g. `https://school.example.com` |
| `APP_KEY` | Generated automatically by the wizard / `key:generate` |
| `APP_MAINTENANCE` | `true` to enable maintenance mode |
| `DB_*` | Database connection |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `MAIL_*`, `SMS_*`, `PAYMENT_*` | Optional integrations (pluggable) |

## Creating the first administrator

The wizard creates it. On CLI installs the seeder creates
`superadmin@school.test` / `Password123!` — **change this immediately** via
*Profile → Change Password*.

## Common problems & troubleshooting

| Symptom | Fix |
|---------|-----|
| Blank page / 500 on first load | Ensure `storage/` is writable and `.env` exists |
| "Database connection failed" | Verify `DB_*` values; confirm MySQL is reachable |
| Routes return 404 on Apache | Enable `mod_rewrite`; set `AllowOverride All` |
| Installer keeps reappearing | Ensure `storage/installed.lock` was created & is readable |
| Uploads fail | Check `storage/uploads` permissions and `UPLOAD_MAX_SIZE` |
| Emails not sent | Configure `MAIL_*`; default driver only logs messages |

See `CPANEL_DEPLOYMENT.md` and `VPS_DEPLOYMENT.md` for host-specific guides.

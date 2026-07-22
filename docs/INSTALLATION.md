# Installation Guide

## Requirements

- PHP **8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`
- MySQL **8.0+** (or MariaDB 10.6+)
- Apache (`mod_rewrite`) or Nginx
- Composer (optional — a built-in PSR-4 autoloader runs the app without it)

## Steps

### 1. Get the code
```bash
cd /var/www
git clone <repo> school-erp   # or upload the files
cd school-erp
```

### 2. Configure the environment
```bash
cp .env.example .env
php bin/console key:generate
```
Edit `.env` and set your database credentials:
```
DB_HOST=127.0.0.1
DB_DATABASE=school_erp
DB_USERNAME=your_user
DB_PASSWORD=your_password
APP_ENV=production
APP_DEBUG=false
# Leave APP_URL blank to auto-detect scheme/host/subdirectory per request
# (works behind Cloudflare / reverse proxies). Set it only to force a canonical URL.
APP_URL=
```

### 3. Create the database
```sql
CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations and seed data
```bash
php bin/console migrate      # creates all 77 tables (19 migrations)
php bin/console seed         # roles, permissions, demo users, academic data
```
Alternatively import `database/schema.sql` directly, then run `php bin/console seed`.

### 5. File permissions
```bash
chmod -R 775 storage
# ensure the web server user (www-data) can write to storage/
chown -R www-data:www-data storage
```

### 6. (Optional) Composer & assets
```bash
composer install --no-dev --optimize-autoloader
```

### 7. Serve
Local development:
```bash
php -S localhost:8000 -t public
```
Production: point your web server document root at the **`public/`** directory
(see `docs/DEPLOYMENT.md`).

## Default accounts (from seed)

| Role         | Email                  | Password     |
|--------------|------------------------|--------------|
| Super Admin  | superadmin@school.test | Password123! |
| School Admin | admin@school.test      | Password123! |
| Teacher      | teacher@school.test    | Password123! |
| Student      | student@school.test    | Password123! |
| Parent       | parent@school.test     | Password123! |

> **Change all default passwords immediately after first login.**

## Console commands

| Command                          | Description                        |
|----------------------------------|------------------------------------|
| `php bin/console migrate`        | Run pending migrations             |
| `php bin/console upgrade`        | Safe upgrade: backup + pending migrations only |
| `php bin/console migrate:fresh`  | Drop all tables and re-migrate (blocked in production without `--force`) |
| `php bin/console migrate:status` | Show applied/pending migrations    |
| `php bin/console seed`           | Seed baseline data                 |
| `php bin/console health`         | Full system health check (DB/storage/cache/env) |
| `php bin/console key:generate`   | Generate the app key               |
| `php bin/console backup`         | Dump the database to `storage/backups` |

## Troubleshooting

- **500 on first load**: check `storage/` is writable and `.env` exists.
- **DB connection failed**: verify credentials and that MySQL is reachable. The
  app shows a friendly 503 page (no stack trace) when the DB is unreachable.
- **Routes 404 on Apache**: ensure `mod_rewrite` is enabled and `.htaccess` is allowed (`AllowOverride All`).

## Docker / Koyeb / other hosts

A production `Dockerfile`, `koyeb.yaml`, and `docker/entrypoint.sh` are included,
plus `deploy/nginx.conf.example` and `deploy/Caddyfile.example`. See
`KOYEB_DEPLOYMENT.md` and `ENVIRONMENT_VARIABLES.md` in the project root, and
`docs/DEPLOYMENT.md` for server configs. The app is deployment-agnostic: all
config comes from environment variables and `APP_URL` auto-detects when blank.

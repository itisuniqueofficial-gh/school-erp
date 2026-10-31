# School ERP on XAMPP — Ubuntu/Linux

This guide runs the complete stack on XAMPP's own Apache, PHP, MySQL/MariaDB
and phpMyAdmin. You do **not** need the system PHP or system MySQL packages.

The application can live in `/opt/lampp/htdocs/erp` and be opened at
`http://localhost/erp/`. The project-root `.htaccess` forwards requests into
`public/`; you do not need to change XAMPP's global Apache configuration.

## 1. Requirements

- XAMPP for Linux with **PHP 8.2 or newer** (PHP 8.2.12 is verified)
- XAMPP MySQL/MariaDB (MariaDB 10.4.32 is verified)
- Apache `mod_rewrite` and `AllowOverride All` — XAMPP's defaults already enable
  both for `htdocs`
- A dedicated database and database user (recommended)

MariaDB 10.4 is the version bundled with XAMPP 8.2.x. School ERP's migrations
and full integration suite pass on that version.

> The Windows guide is [XAMPP-WINDOWS.md](XAMPP-WINDOWS.md).

## 2. Put the project in htdocs

Preferred layout:

```text
/opt/lampp/htdocs/erp/
```

If the project is somewhere else, a symlink also works **provided Apache can
traverse every parent directory**:

```bash
ln -s /path/to/school-erp /opt/lampp/htdocs/erp
```

If `/opt/lampp/htdocs` is not writable, use `sudo` for the link. The setup
script below prints the exact command when it cannot create the link itself.

Avoid pointing the browser at the project root without the included
`.htaccess`. For production, a virtual host whose `DocumentRoot` is the
`public/` folder is even better; see [INSTALL.md](INSTALL.md).

## 3. Run the XAMPP checks and setup

From the project root:

```bash
scripts/xampp-check.sh
scripts/xampp-setup.sh
```

`check` is read-only. `setup` creates missing `storage` directories, creates
`.env` from `.env.example` when absent, generates an empty `APP_KEY`, and tries
to create the `htdocs` symlink. It never drops a database or seeds demo data.

If Apache writes to `storage/` are denied, let setup set ownership to XAMPP's
Apache user (usually `daemon`):

```bash
scripts/xampp-setup.sh --fix-permissions
```

This changes `storage/` only, never the whole project, and never uses `777`.
If XAMPP Apache runs as a different user, setup also makes `.env` readable by
that user (mode `644`) because Apache cannot read a `640` file owned by your
login account. `.env` remains outside `public/` and is denied by `.htaccess`.

You can override auto-detection with:

```bash
XAMPP_HOME=/opt/lampp scripts/xampp-check.sh
XAMPP_URL=http://localhost/erp scripts/xampp-check.sh
```

## 4. Start only the XAMPP services

```bash
scripts/xampp-start.sh
scripts/xampp-status.sh
```

Starting Apache on port 80 normally needs `sudo`; the script requests it for
that one step. Start/stop scripts touch **only XAMPP** processes. They never
stop system MySQL/Apache or unrelated services.

Manual alternative:

```bash
sudo /opt/lampp/lampp startmysql
sudo /opt/lampp/lampp startapache
```

Do not start FileZilla or Mercury unless you need them.

### Port 3306 is already in use

This is the most common XAMPP-on-Linux problem. Another MySQL/MariaDB service
owns port 3306, so XAMPP's database cannot start. The check script reports this
as a failure.

Choose one:

**A. Use XAMPP's database only (stop the other MySQL):**

```bash
systemctl list-units --type=service | grep -E 'mysql|mariadb'
sudo systemctl disable --now mysql      # or mysqld / mariadb
scripts/xampp-start.sh
```

Only do this if other applications do not depend on the system database.

**B. Keep the other database and move XAMPP MySQL to 3307:**

1. In `/opt/lampp/etc/my.cnf`, set the port under `[mysqld]` (and `[client]`):

   ```ini
   [mysqld]
   port = 3307

   [client]
   port = 3307
   ```

2. In `/opt/lampp/phpmyadmin/config.inc.php`, add the same port:

   ```php
   $cfg['Servers'][$i]['port'] = '3307';
   ```

3. In the ERP `.env`:

   ```env
   DB_PORT=3307
   ```

4. Restart XAMPP MySQL and run `scripts/xampp-check.sh` again.

The start script reads the port from `my.cnf`, so it will not mistake the other
MySQL on 3306 for XAMPP's server.

## 5. Create the database (phpMyAdmin)

Open:

```text
http://localhost/phpmyadmin/
```

Sign in as the XAMPP database administrator (`root`, usually no password on a
fresh local XAMPP). Then use **SQL**:

```sql
CREATE DATABASE school_erp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'erp_local'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
CREATE USER 'erp_local'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT ALL PRIVILEGES ON school_erp.* TO 'erp_local'@'localhost';
GRANT ALL PRIVILEGES ON school_erp.* TO 'erp_local'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Put those credentials in `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=
APP_BASE_PATH=
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_erp
DB_USERNAME=erp_local
DB_PASSWORD=replace-with-a-strong-password
```

Leave `APP_URL` and `APP_BASE_PATH` blank for XAMPP. The app detects the
scheme, host and `/erp` subdirectory from each request.

Use `127.0.0.1` rather than `localhost` in `DB_HOST` so the app uses TCP and
matches the XAMPP MySQL port consistently.

### Do I need to import schema.sql?

No. The browser installer and `bin/console migrate` create and upgrade tables
additively. `database/schema.sql` is an optional starter schema for hosts that
require a manual import. Import it only into a **new empty** database, then run
the installer/migrations so any missing changes are applied. Do not import it
over an existing ERP database.

## 6. Install the application

Open:

```text
http://localhost/erp/
```

A fresh database redirects to the browser installer:

1. Requirements — PHP version, extensions, writable `storage`
2. Database — use the credentials from step 5
3. Administrator — create the first super admin and school profile
4. Finish — write `installed.lock`

The installer refuses to overwrite a database that already contains users.
Delete `public/install.php` after installation (recommended) or keep it; the
database guard still protects live data.

If you prefer the command line:

```bash
/opt/lampp/bin/php bin/console key:generate
/opt/lampp/bin/php bin/console install       # migrations + demo seed
```

The CLI seed creates demo accounts; the browser installer does not. Change any
demo password immediately.

## 7. Verify

```bash
scripts/xampp-check.sh
```

It checks:

- XAMPP and PHP 8.2+, required extensions and PDO MySQL
- Apache rewrite rules and the `/erp` URL
- `.env`, storage writability and XAMPP's Apache user
- database reachability, existing installation and pending migrations
- XAMPP service state and port conflicts
- HTTP status for the app, assets, login, and that `.env`, storage lock and
  `.git/config` are **not** downloadable

Manual checks:

```text
http://localhost/erp/
http://localhost/erp/login
http://localhost/erp/assets/css/app.css
http://localhost/phpmyadmin/
```

## 8. Stop and restart

```bash
scripts/xampp-stop.sh
scripts/xampp-start.sh
```

`stop` refuses to force-kill a database it did not start and does not touch
system services. If it cannot stop MySQL cleanly, use the XAMPP control panel.

## 9. Security for a shared or production server

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Use a dedicated database user, not `root`.
- Keep the project's `.htaccess` files. They deny `.env`, logs, uploads,
  database files and source directories when the document root is the project
  root.
- Restrict `.env` permissions as much as your Apache user allows. Setup uses
  `644` when Apache runs as a different user and no shared group/ACL is
  available; `640` with a shared group or an ACL is preferable if you can
  arrange it. Never use `777`.
- Remove `public/install.php` after installation.
- Change all demo passwords if you used the CLI seeder.
- Take backups before upgrades:

  ```bash
  /opt/lampp/bin/php bin/console backup
  /opt/lampp/bin/php bin/console upgrade
  ```

`upgrade` backs up the database and applies pending migrations only. Never run
`migrate:fresh` against a live database.

## 10. Troubleshooting

| Symptom | Fix |
|---------|-----|
| `xampp-check.sh` says port 3306 is used by another database | Follow section 4: stop the other MySQL or move XAMPP MySQL to 3307 and update `.env` |
| `xampp-start.sh` cannot bind port 80 | It needs `sudo`; or change Apache `Listen` in `/opt/lampp/etc/httpd.conf` and use the new port in `XAMPP_URL` |
| 403 on `/erp/` | Check `AllowOverride All` and `mod_rewrite`; run `scripts/xampp-check.sh` |
| 404 for CSS/JS under `/erp` | Confirm you open the app with the trailing subdirectory (`/erp/`), not a partial path; check `public/assets` exists |
| 500 / blank page | Set `APP_DEBUG=true` locally, check `storage/logs`, ensure `storage` is writable by `daemon` |
| Database connection failed | Confirm XAMPP MySQL is running, `DB_PORT` matches, and the user is granted for both `localhost` and `127.0.0.1` |
| Installer keeps returning | Check `php bin/console install:status`; a populated DB self-heals the lock. Do not delete live data to force the installer |
| phpMyAdmin cannot connect after changing the port | Add `$cfg['Servers'][$i]['port'] = '3307';` to `/opt/lampp/phpmyadmin/config.inc.php` |
| Uploads fail | Check `storage/uploads` ownership and XAMPP PHP's `upload_max_filesize` / `post_max_size` |
| Wrong PHP version is used | Always call `/opt/lampp/bin/php` for CLI, and run through XAMPP Apache, not the system PHP module |

## Compatibility status

Verified in this repository's Linux test environment:

- XAMPP PHP **8.2.12** with XAMPP MariaDB **10.4.32**
- All 30 migrations, seed and health check
- Full integration test suite (57 suites)
- XAMPP Apache subdirectory hosting at `/erp`, assets, login and `.env` denial

The Windows PowerShell scripts are provided for the equivalent layout but must
be verified on the target Windows machine using `scripts\xampp-check.ps1`.

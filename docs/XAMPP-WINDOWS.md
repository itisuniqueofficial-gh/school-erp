# School ERP on XAMPP — Windows

This guide runs the complete stack on XAMPP's Apache, PHP, MySQL/MariaDB and
phpMyAdmin on Windows. The recommended URL is:

```text
http://localhost/school-erp/
```

The included PowerShell scripts perform read-only checks, prepare `.env` and
storage, create an `htdocs` junction when needed, and start/stop **only XAMPP**
services. They do not modify global Apache configuration or system services.

> The Linux guide is [XAMPP-UBUNTU.md](XAMPP-UBUNTU.md).

## 1. Requirements

- XAMPP for Windows with **PHP 8.2 or newer**
- XAMPP Apache with `mod_rewrite` and `AllowOverride All` (XAMPP defaults)
- XAMPP MySQL/MariaDB
- PowerShell 5.1 or newer

School ERP runs on MariaDB 10.4 (the version bundled with XAMPP 8.2.x); all 30
migrations and the integration suite pass on that version.

## 2. Put the project in htdocs

Preferred layout:

```text
C:\xampp\htdocs\school-erp\
```

Copy the project there, or leave it elsewhere and run `xampp-setup.ps1`. Setup
creates a directory junction from `C:\xampp\htdocs\school-erp` to the project
when the target does not already exist.

Do not point the browser at `C:\xampp\htdocs\school-erp\public` as a URL. The
project-root `.htaccess` routes requests into `public/` for the `/school-erp/`
URL.

## 3. Check and prepare

Open PowerShell in the project root:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-check.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-setup.ps1
```

`check` is read-only. `setup`:

- creates `.env` from `.env.example` if missing
- generates `APP_KEY` if empty
- creates writable `storage` subdirectories
- creates the `htdocs` junction when needed
- never resets the database or creates demo data

If storage permissions are a problem, run an elevated PowerShell or use:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-setup.ps1 -FixPermissions
```

This grants Modify access on `storage` only, to the current Windows account and
`SYSTEM` (XAMPP service mode). It does not grant `Everyone`.

Environment overrides:

```powershell
$env:XAMPP_HOME = 'D:\xampp'
$env:XAMPP_URL  = 'http://localhost:8080/school-erp'
.\scripts\xampp-check.ps1
```

## 4. Start XAMPP services

You can use the XAMPP Control Panel and start **Apache** and **MySQL** only, or:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-start.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-status.ps1
```

The start script launches `mysqld.exe` and `httpd.exe` from the detected XAMPP
folder, waits for their ports, and will not start XAMPP MySQL if another
database already owns the port. It does not start FileZilla or Mercury.

Stop only XAMPP services:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-stop.ps1
```

The stop script uses XAMPP's own stop batch files when available. If MySQL does
not stop cleanly, it warns instead of force-killing the database process.

### Port 3306 is already in use

Find the owner:

```powershell
Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue |
    Select-Object LocalAddress, LocalPort, OwningProcess
Get-Service | Where-Object { $_.Name -match 'mysql|maria' }
```

Choose one:

**A. Use XAMPP MySQL only.** Stop/disable the other MySQL service only if other
applications do not need it:

```powershell
Stop-Service MySQL -Force
Set-Service MySQL -StartupType Disabled
```

(The service may be named `MySQL80`, `MariaDB`, etc.)

**B. Move XAMPP MySQL to 3307.** In `C:\xampp\mysql\bin\my.ini`, set:

```ini
[mysqld]
port = 3307

[client]
port = 3307
```

In `C:\xampp\phpMyAdmin\config.inc.php`, add:

```php
$cfg['Servers'][$i]['port'] = '3307';
```

Then set this in the ERP `.env`:

```env
DB_PORT=3307
```

Restart XAMPP MySQL and rerun `xampp-check.ps1`.

If Apache port 80 is busy, change `Listen 80` in
`C:\xampp\apache\conf\httpd.conf` to another port and set `XAMPP_URL` for the
checks. Changing Apache's port is a global XAMPP change, so do it deliberately.

## 5. Create the database with phpMyAdmin

Open:

```text
http://localhost/phpmyadmin/
```

Log in as XAMPP's database administrator (`root`, usually no password on a
fresh local install), then run:

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

Configure `.env` (created by setup):

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

Leave `APP_URL` and `APP_BASE_PATH` blank. The app detects
`http://localhost/school-erp` and its subdirectory automatically.

`database/schema.sql` is optional. Do not import it over an existing ERP
database. Import it only into a new empty database, then let the installer or
migrations apply missing changes.

## 6. Install

Open:

```text
http://localhost/school-erp/
```

The browser wizard checks requirements, tests the database, creates the first
super administrator and school profile. It refuses to overwrite a database that
contains users. Delete `public/install.php` afterwards or leave it; the database
guard still protects live data.

Command-line alternative (uses the XAMPP PHP binary):

```powershell
& C:\xampp\php\php.exe bin\console key:generate
& C:\xampp\php\php.exe bin\console install
```

The CLI seed creates demo accounts; the browser installer does not. Change any
demo password immediately.

## 7. Verify

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\xampp-check.ps1
```

The check validates PHP 8.2+, extensions, Apache rewrite, the
`/school-erp` URL, storage writability, database state, XAMPP ports, and HTTP
responses for the app, CSS/JS and login. It also verifies that `.env`,
`storage/installed.lock` and `.git/config` are not downloadable.

Manual checks:

```text
http://localhost/school-erp/
http://localhost/school-erp/login
http://localhost/school-erp/assets/css/app.css
http://localhost/phpmyadmin/
```

## 8. Security for a shared or production server

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Create a dedicated database user instead of using `root`.
- Keep the root and `public/` `.htaccess` files; they deny `.env`, logs,
  uploads, database files and source directories when the document root is the
  project root.
- Restrict `.env` as much as possible without breaking the XAMPP Apache
  account. Never grant `Everyone` or use `777`-equivalent permissions.
- Remove `public/install.php` after installation.
- Change all demo passwords if you used the CLI seeder.
- Back up before upgrades:

  ```powershell
  & C:\xampp\php\php.exe bin\console backup
  & C:\xampp\php\php.exe bin\console upgrade
  ```

Never run `migrate:fresh` on a live database.

## 9. Troubleshooting

| Symptom | Fix |
|---------|-----|
| `xampp-check.ps1` reports port 3306 in use | Stop the other MySQL service or move XAMPP MySQL to 3307 and update `.env` + phpMyAdmin |
| Apache will not start on port 80 | Another web server/IIS owns it. Stop it or change XAMPP Apache's `Listen` port |
| Windows Firewall prompt | Allow Apache on private networks only; do not expose a local install publicly |
| 403 on `/school-erp/` | Check `.htaccess`, `AllowOverride All`, and `mod_rewrite` |
| 404 for assets | Use the exact `/school-erp/` path and keep the project-root `.htaccess` |
| 500 / blank page | Set `APP_DEBUG=true`, check `storage\logs`, and grant storage permissions |
| Database connection failed | Confirm XAMPP MySQL is running, `DB_PORT` matches, and grants exist for `localhost` and `127.0.0.1` |
| Installer keeps returning | Run `php bin\console install:status`; a populated DB self-heals the lock. Do not delete data to force a reinstall |
| phpMyAdmin cannot connect after a port change | Add the same port to `C:\xampp\phpMyAdmin\config.inc.php` |
| Uploads fail | Check `storage\uploads` permissions and XAMPP PHP upload limits |
| Junction not created | Run setup from an account that can write to `C:\xampp\htdocs`, or copy the project there |

## Compatibility status

The application, migrations and integration suite are verified with XAMPP PHP
8.2.12 and MariaDB 10.4.32 on Linux, including the subdirectory layout. The
PowerShell scripts mirror those checks on Windows but were not executed in the
Linux development environment. Run `scripts\xampp-check.ps1` on the target
Windows machine before relying on the installation.

# Installation and database portability

Supported databases: **MySQL 8+, MariaDB 10.4+, PostgreSQL 12+** (PHP extension `pdo_mysql` or `pdo_pgsql`).
Verified on MySQL 8.4 and PostgreSQL 16.4 (schema + queries). MariaDB uses the same driver as MySQL but has not been run here.
PHP 8.2+, with `pdo`, `mbstring`, `openssl`, `fileinfo`, `json`, `ctype`. Optional: `gd`, `curl` (S3/R2), `zip`.

Both installers call the same backend (`App\Services\Setup\SetupService`), so they behave identically.
An existing installation is never reinstalled, reset or overwritten: a database that already has users only receives
additive migrations.

## 1. Choose an installer

### Browser wizard
Open `http://your-site/install.php` (or `http://localhost/school-erp/public/install.php` for XAMPP in a sub-folder).
Steps: requirements and environment, database (engine, details or connection URL, SSL/socket, optional "create database"),
administrator and school, finish. Delete `public/install.php` afterwards.

### Terminal
```bash
php bin/console check                       # environment, drivers, writable folders, install state
php bin/console db:test --url='postgres://erp:secret@127.0.0.1:5432/school_erp'   # connection test only
php bin/console install                     # interactive
php bin/console install --non-interactive \
    --engine=mysql --host=127.0.0.1 --port=3306 --database=school_erp --user=erp --password='secret' \
    --admin-email=admin@school.example --admin-name='Principal' --school='Greenfield School' \
    --create-database                       # --create-database only if the DB user may create it
php bin/console help                        # every command
```
Non-interactive runs read `DATABASE_URL`/`DB_*` from the environment and `ERP_ADMIN_EMAIL`, `ERP_ADMIN_PASSWORD`,
`ERP_SCHOOL_NAME`. Without a password a random one is generated and printed once. `--demo` adds demo accounts
(development only).

## 2. Connection methods

| Method | How |
|---|---|
| Manual | Wizard fields, or `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` |
| Connection URL | `DATABASE_URL=mysql://user:p%40ss@host:3306/db` (also `mariadb://`, `postgres://`; query: `sslmode`, `charset`, `schema`, `socket`, `timeout`) |
| Environment variables | In Docker/PaaS with no `.env` file, set `DATABASE_URL` or `DB_*` as process environment variables. If a `.env` file also defines a key, the `.env` value wins |
| Config file | `.env` is written atomically with mode 0600; the previous file is kept as `.env.bak.<time>` |
| Automatic check | Wizard "Test connection" and `db:test` report the server version, whether the database exists and whether it already holds an installation. Errors never contain the password, host or SQL |

Extras: `DB_SOCKET` (Unix socket), `DB_SSL_MODE=disable|prefer|require|verify-ca|verify-full`, `DB_SSL_CA/CERT/KEY`,
`DB_TIMEOUT`, `DB_SCHEMA` (PostgreSQL), `DB_CHARSET`.

Switch an installed site to other credentials safely (new settings are tested first, `.env` is only replaced if they work):
`php bin/console config:database --url=...`. This does not copy data; see section 5.

## 3. Platform notes

**XAMPP (Windows/Linux).** Copy the project to `htdocs/school-erp`, start Apache + MySQL/MariaDB, create a database in
phpMyAdmin (or tick "Create the database"), open `http://localhost/school-erp/public/install.php`.
If you use PostgreSQL enable `extension=pdo_pgsql` in `php.ini`.

**cPanel / DirectAdmin shared hosting.** Create the database and user in the panel ("MySQL Databases" / "PostgreSQL
Databases"; the real names carry your account prefix). Point the domain's document root at `public/`. If that is
impossible, keep the project above `public_html` and put only the contents of `public/` in it, adjusting
`index.php`'s path to `bootstrap/`. Set a storage folder **outside** the web root in the installer
(`/home/youruser/erp-storage`) or `STORAGE_PATH` in `.env`. No shell? Use the browser wizard.

**Ubuntu / Debian VPS (Apache or Nginx + PHP-FPM).**
```bash
sudo apt install php8.3-fpm php8.3-mysql php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-gd php8.3-curl php8.3-zip
sudo chown -R www-data:www-data storage && sudo chmod -R u+rwX storage
php bin/console install
```
Nginx: `root /var/www/school-erp/public; try_files $uri /index.php?$query_string;` and pass `\.php$` to PHP-FPM.
Apache: `DocumentRoot /var/www/school-erp/public` with `AllowOverride All`. Server configuration is never changed by the
installer.

**Docker.** `docker/entrypoint.sh` runs `upgrade` (verified backup, then migrate). Provide `DATABASE_URL` and volumes for
`storage/`.

## 4. Backup and restore

```bash
php bin/console db:export                          # storage/backups/erp-<engine>-<time>.erpdump, re-read and verified
php bin/console db:export --passphrase='long phrase'   # AES-256-GCM encrypted (.enc)
php bin/console db:verify storage/backups/file.erpdump [--passphrase=...]
```
`db:verify` recomputes every row count and SHA-256 and compares them with the file footer. A truncated, edited or
corrupted file is reported INVALID. A backup is only reported after it has been re-read.

Restore never overwrites school data: create a **new empty database**, point `.env` (or `--url`) at it, then
```bash
php bin/console migrate
php bin/console db:import storage/backups/file.erpdump     # asks you to type IMPORT
```
The import refuses a database that already holds data, rolls back entirely on any error, then checks row counts
against the backup and looks for orphaned foreign keys. When it reports success you can switch the application over
(`config:database`). The legacy `backup` / `restore` commands (mysqldump) remain for MySQL/MariaDB.

`php bin/console upgrade` takes a verified backup first and stops if it cannot.

## 5. Moving between MySQL, MariaDB and PostgreSQL

1. On the old server: `php bin/console db:inspect` (what travels and what does not) then `db:export`.
2. On the new engine: create an empty database, set `DATABASE_URL`, run `php bin/console migrate`
   (the migrations are translated to PostgreSQL automatically; they run in one transaction per file there).
3. `php bin/console db:import <file>`, check the verification report, then test the application with the new database.
4. Keep the old database untouched until you are satisfied. Roll back = point `.env` back.

MySQL <-> MariaDB are the same driver and need no conversion. Known differences when going to PostgreSQL:
text comparison becomes case-sensitive (search uses `ILIKE`, but unique emails/usernames are now case-sensitive);
MySQL zero-dates (`0000-00-00`) are stored as NULL and counted in the report; triggers are not exported but are
recreated by migrations (PostgreSQL triggers enforce PRN immutability natively; MySQL under binary logging may
need `SUPER`, in which case the application enforces it). Views, routines and events are not migrated; the
application uses none.

## 6. Upgrades

```bash
git pull   # or upload the new release over the old one (never delete storage/ or .env)
php bin/console upgrade
```
Migrations are additive and recorded in the `migrations` table. On PostgreSQL each migration file is atomic. On MySQL/
MariaDB DDL cannot be rolled back, which is why the verified pre-upgrade backup is mandatory.

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| "The PHP extension pdo_pgsql is not enabled" | Install/enable the driver (`php8.3-pgsql`, or `extension=pdo_pgsql`) |
| "username or password is incorrect" | Check credentials; with a URL percent-encode `@ : /` in the password |
| "database does not exist" | Create it, or tick "Create the database" / pass `--create-database` |
| "Existing installation detected" | By design. Use `upgrade`, or `admin:recover` for a lost admin password |
| Blank page after install on a sub-folder | Use `http://host/folder/public/`; set `APP_BASE_PATH=/folder` if needed |
| Uploads fail | `STORAGE_PATH` must be writable by the web server user |
| `check` shows failures | Fix the listed item; it exits non-zero so scripts can stop |

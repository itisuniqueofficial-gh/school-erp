# LOCAL_DEVELOPMENT.md — Running School ERP Locally

This guide runs the complete School ERP on your machine for browser testing.
The stack is **Core PHP 8.2+ + MySQL 8 / MariaDB 10.4+**; the frontend (Tailwind, jQuery, GSAP,
Chart.js, Font Awesome) is loaded via CDN, so **no Node/npm build step is
required** (there is no `package.json`).

## 1. Requirements
- PHP **8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `session`, `ctype`, `iconv`
- MySQL **8.0+** / MariaDB **10.4+** (XAMPP 8.2.x ships a supported MariaDB)
- Composer is **optional** (a built-in PSR-4 autoloader runs the app without it)

## 2. Install PHP & MySQL (Ubuntu/Debian example)
```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml mysql-server
```
(macOS: `brew install php mysql`; Windows: use Laragon or XAMPP with PHP 8.2+.)

## 3. Composer (optional)
```bash
composer install     # only if you want dev tooling / PHPUnit
```
The application itself runs **without** Composer.

## 4. Node.js — not required
The UI uses CDN assets. Skip npm entirely.

## 5. Get the code
```bash
git clone https://github.com/itisuniqueofficial-gh/school-erp.git
cd school-erp
```

## 6. Configure environment
```bash
cp .env.example .env
php bin/console key:generate
```
Edit `.env` for local development:
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_erp
DB_USERNAME=erp_local
DB_PASSWORD=your_local_password
```
> `.env` is git-ignored — never commit it. Use a **dedicated local** DB/user,
> never production credentials.

## 7. Create the database & user
```sql
CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp_local'@'127.0.0.1' IDENTIFIED BY 'your_local_password';
GRANT ALL PRIVILEGES ON school_erp.* TO 'erp_local'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 8. Run migrations & seed
```bash
php bin/console migrate      # creates 86 application tables + migrations tracker
php bin/console seed         # roles, permissions, demo users & academic data
# or in one step:
php bin/console install
```
Then create the install lock so the app serves normally (skips the web installer):
```bash
echo "installed" > storage/installed.lock
```
> Alternatively, leave the lock absent and open `http://localhost:8000/install.php`
> to run the **browser installation wizard** instead of steps 7–8. The wizard
> refuses to overwrite a database that already contains users.

### Lost the administrator password?
```bash
php bin/console admin:recover
php bin/console admin:recover --email=admin@example.com
```
This resets only the selected super administrator (and revokes its remember-me
tokens). It never deletes students, fees, staff, uploads or other users.

## 9. File storage permissions
```bash
chmod -R 775 storage
# uploads for photos/documents/reports live under storage/uploads/*
```

## 10. Start the application
```bash
php -S localhost:8000 -t public
```
Open **http://localhost:8000**.

### XAMPP / Apache (Linux or Windows)

The complete XAMPP stack has dedicated guides and scripts:

- **Ubuntu/Linux:** [docs/XAMPP-UBUNTU.md](docs/XAMPP-UBUNTU.md)
- **Windows:** [docs/XAMPP-WINDOWS.md](docs/XAMPP-WINDOWS.md)

Quick version (Linux):

```bash
scripts/xampp-check.sh
scripts/xampp-setup.sh
scripts/xampp-start.sh
```

Open `http://localhost/erp/` when the project is linked at `htdocs/erp`. Leave
`APP_BASE_PATH` blank; the app auto-detects the `/erp` prefix and assets work
through the project-root `.htaccess`.

If another MySQL already owns port 3306, XAMPP MySQL must use a different port
(and `.env DB_PORT` must match) or the other service must be stopped. The check
script reports the conflict and never stops unrelated services.

## 11. Default development login
| Role         | Email                    | Password     |
|--------------|--------------------------|--------------|
| Super Admin  | superadmin@school.test   | Password123! |
| School Admin | admin@school.test        | Password123! |
| Teacher      | teacher@school.test      | Password123! |
| Student      | student@school.test      | Password123! |
| Parent       | parent@school.test       | Password123! |

Log in at `http://localhost:8000/login`. **Change these before any real use.**

## 12. Semi-automation (optional, via cron or manual)
```bash
php bin/console reminders          # birthday/fee-due/exam/holiday notifications
php bin/console backup             # dump DB to storage/backups
php bin/console cleanup:logs 30    # remove logs older than 30 days
php bin/console cleanup:sessions   # remove expired sessions
```

## 13. Troubleshooting
| Symptom | Fix |
|---------|-----|
| 500 on load | Ensure `storage/` is writable and `.env` exists (`APP_DEBUG=true` shows details) |
| DB connection failed | Verify `DB_*`, confirm MySQL is running and reachable on the port |
| Redirected to `/install.php` | Finish the wizard, or run `php bin/console install:status`; a populated DB is detected automatically |
| Routes 404 under Apache | Enable `mod_rewrite`, `AllowOverride All`; doc root = `public/` |
| Uploads fail | Check `storage/uploads` permissions and `UPLOAD_MAX_SIZE` |

## 14. Stopping services
```bash
# stop the PHP dev server: Ctrl+C in its terminal
scripts/xampp-stop.sh            # stops only XAMPP Apache/MySQL when used
sudo systemctl stop mysql        # only if you started system MySQL
```

## 15. Resetting the local database
```bash
php bin/console migrate:fresh    # drops all tables and re-migrates
php bin/console seed
```

# CPANEL_DEPLOYMENT.md — Deploying on cPanel / Shared Hosting

Works on shared hosting with LiteSpeed/Apache, CloudLinux and PHP Selector.
No framework-specific server configuration is required.

## Prerequisites
- cPanel account with PHP **8.3+** (set via *MultiPHP Manager* / *PHP Selector*)
- MySQL or MariaDB database access

## Step-by-step

1. **Create database** — cPanel → *MySQL® Databases* → create `youracct_schoolerp`.
2. **Create database user** — same page → create a user with a strong password.
3. **Assign privileges** — add the user to the database with **ALL PRIVILEGES**.
4. **Upload project** — *File Manager* or FTP: upload the project ZIP to your home dir (e.g. `~/schoolerp`).
5. **Extract files** — extract the archive in place.
6. **Configure document root** — point the domain/subdomain's document root to
   `~/schoolerp/public` (cPanel → *Domains* → *Manage* → Document Root). If you
   cannot change it, the bundled root `.htaccess` forwards requests into `public/`.
7. **Configure PHP version** — *MultiPHP Manager* → set the domain to PHP 8.3+.
   Ensure extensions `pdo_mysql, mbstring, openssl, fileinfo, json` are enabled
   (*Select PHP Version* → *Extensions*).
8. **Configure `.env`** — copy `.env.example` to `.env` (File Manager) — or let
   the installer create it in the next steps.
9. **Writable directories** — set `storage/` and its subfolders to **0775**
   (File Manager → select → *Permissions*).
10. **Import SQL** *(optional if using the wizard)* — phpMyAdmin → *Import* →
    upload `database/schema.sql`.
11. **Run the installer** — visit `https://your-domain/install.php` and complete
    the wizard (requirements → database → admin → finish).
12. **Verify installation** — log in at `/login` with the admin account created.
13. **Configure cron jobs** — cPanel → *Cron Jobs*:
    ```
    # Daily reminders (birthdays, fee due, exams)
    0 6 * * * cd ~/schoolerp && /usr/local/bin/php bin/console reminders >/dev/null 2>&1
    # Nightly backup
    0 2 * * * cd ~/schoolerp && /usr/local/bin/php bin/console backup >/dev/null 2>&1
    # Weekly cleanup
    0 3 * * 0 cd ~/schoolerp && /usr/local/bin/php bin/console cleanup:logs 30 >/dev/null 2>&1
    ```
14. **Configure mail** — set `MAIL_*` in `.env` (cPanel email account or SMTP relay).
15. **Configure SSL** — cPanel → *SSL/TLS Status* → run AutoSSL. Then set
    `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true`.
16. **Configure backups** — the cron above writes to `storage/backups`; also use
    cPanel's own backup for full-account safety.
17. **Enable production mode** — in `.env`: `APP_ENV=production`, `APP_DEBUG=false`.
18. **Remove installer** — delete `public/install.php`.
19. **Verify permissions** — confirm `storage/` is writable and `.env`/`storage/`
    are **not** web-accessible (they aren't when doc root is `public/`).
20. **Production checklist** — see below.

## Production checklist
- [ ] Document root = `public/`
- [ ] PHP 8.3+ with required extensions
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
- [ ] `SESSION_SECURE_COOKIE=true`, SSL active
- [ ] `storage/` writable (0775), not web-accessible
- [ ] Installer removed (`public/install.php` deleted)
- [ ] Cron jobs for reminders/backup/cleanup added
- [ ] Default admin password changed
- [ ] Test login, a CRUD action, a report, and a backup

## Notes for LiteSpeed / CloudLinux
- `.htaccess` rules are honoured by LiteSpeed automatically.
- If using CloudLinux "PHP Selector", enable the extensions listed above.
- OPcache is usually available — no action needed.

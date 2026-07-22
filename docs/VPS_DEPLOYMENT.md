# VPS_DEPLOYMENT.md — Deploying on a VPS (Ubuntu)

A complete production setup on Ubuntu 22.04/24.04 with Apache **or** Nginx +
PHP-FPM, MySQL, SSL, cron and a firewall.

## 1. Base packages
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip unzip mysql-server
```

## 2. Database
```bash
sudo mysql -e "CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'erp'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON school_erp.* TO 'erp'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. Deploy code
```bash
cd /var/www
sudo git clone <repo> school-erp   # or upload
cd school-erp
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

## 4a. Apache
```bash
sudo apt install -y apache2 libapache2-mod-php8.3
sudo a2enmod rewrite headers deflate expires
```
Create `/etc/apache2/sites-available/school-erp.conf`:
```apache
<VirtualHost *:80>
    ServerName school.example.com
    DocumentRoot /var/www/school-erp/public
    <Directory /var/www/school-erp/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
```bash
sudo a2ensite school-erp && sudo systemctl reload apache2
```

## 4b. Nginx + PHP-FPM (alternative)
`/etc/nginx/sites-available/school-erp`:
```nginx
server {
    listen 80;
    server_name school.example.com;
    root /var/www/school-erp/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(env|git) { deny all; }
    location ~* \.(sql|md|lock)$ { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/school-erp /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 5. Install the app
Visit `http://school.example.com/install.php` and complete the wizard, or:
```bash
cd /var/www/school-erp
sudo -u www-data cp .env.example .env
sudo -u www-data php bin/console key:generate
# edit .env (DB creds, APP_URL, APP_ENV=production, APP_DEBUG=false)
sudo -u www-data php bin/console install
echo installed | sudo -u www-data tee storage/installed.lock
```

## 6. SSL (Let's Encrypt)
```bash
sudo apt install -y certbot python3-certbot-apache   # or -nginx
sudo certbot --apache -d school.example.com           # or --nginx
```
Then set `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true` in `.env`.

## 7. Cron jobs
```bash
sudo crontab -u www-data -e
```
```
0 6 * * * cd /var/www/school-erp && php bin/console reminders   >> storage/logs/cron.log 2>&1
0 2 * * * cd /var/www/school-erp && php bin/console backup      >> storage/logs/cron.log 2>&1
0 3 * * 0 cd /var/www/school-erp && php bin/console cleanup:logs 30
5 3 * * * cd /var/www/school-erp && php bin/console cleanup:sessions
```

## 8. Supervisor / queue workers (optional)
This app performs work synchronously and needs no queue worker by default. If you
later add asynchronous jobs, run them under Supervisor:
```ini
[program:school-erp-worker]
command=php /var/www/school-erp/bin/console queue:work
autostart=true
autorestart=true
user=www-data
```

## 9. Firewall
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'    # or 'Nginx Full'
sudo ufw enable
```

## 10. Performance
- Enable OPcache: `opcache.enable=1`, `opcache.validate_timestamps=0` (prod).
- `composer install --optimize-autoloader --no-dev` (optional; app runs without Composer).
- Static asset caching + gzip are handled by `.htaccess` / the Nginx config above.

## Production checklist
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
- [ ] `APP_URL` blank (auto-detect, proxy-aware) or set to the canonical HTTPS URL
- [ ] HTTPS active, `SESSION_SECURE_COOKIE=true`
- [ ] `storage/` writable, not web-accessible (auto-created on boot)
- [ ] `public/install.php` removed
- [ ] Cron jobs active; backups verified
- [ ] Firewall enabled; MySQL bound to localhost
- [ ] `GET /health` returns 200 (or `php bin/console health`)
- [ ] Future updates: `git pull` then `php bin/console upgrade` (backup + pending
      migrations only; `migrate:fresh` is blocked in production)

> **Container option:** a production `Dockerfile` + `koyeb.yaml` are included for
> Docker/Koyeb/Render/Railway/Fly.io. See `KOYEB_DEPLOYMENT.md`.

# Deployment Guide

The application front controller lives in **`public/`**. Always point the web
server's document root there so that application source, `.env`, and `storage/`
are never web-accessible.

## Docker / Koyeb / PaaS (Render, Railway, Fly.io, Cloud Run)

A production `Dockerfile` (PHP 8.3 + Apache, docroot `public/`), `koyeb.yaml`,
and `docker/entrypoint.sh` are included. The entrypoint binds Apache to the
platform `$PORT`, auto-creates storage, generates `APP_KEY` if missing, and runs
a **safe `upgrade`** (backup + pending migrations only) on boot.

```bash
docker build -t school-erp .
docker run -p 8080:8000 --env-file .env school-erp
```

Provide config via environment variables (no `.env` needed in the image). Leave
`APP_URL` blank to auto-detect the host/scheme (proxy-aware via `X-Forwarded-*`).
See `KOYEB_DEPLOYMENT.md` and `ENVIRONMENT_VARIABLES.md` in the project root.
`deploy/nginx.conf.example` and `deploy/Caddyfile.example` cover Nginx/Caddy.

## Health check

`GET /health` returns JSON with per-check status (database, storage, cache),
app version, environment, PHP version, memory, disk, and response time. It
responds **200 when healthy** and **503 when degraded**, so load balancers /
Koyeb / uptime monitors can drive zero-downtime deploys and auto-restart. The
CLI equivalent is `php bin/console health`.

## Apache (VirtualHost)

```apache
<VirtualHost *:80>
    ServerName school.example.com
    DocumentRoot /var/www/school-erp/public

    <Directory /var/www/school-erp/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/school-erp-error.log
    CustomLog ${APACHE_LOG_DIR}/school-erp-access.log combined
</VirtualHost>
```
Enable rewrite: `sudo a2enmod rewrite headers deflate expires && sudo systemctl restart apache2`.
The bundled `public/.htaccess` handles front-controller routing, gzip, caching and security headers.

## Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name school.example.com;
    root /var/www/school-erp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # Deny access to sensitive files.
    location ~ /\.(env|git) { deny all; }
    location ~* \.(sql|md|lock|json)$ { deny all; }

    # Static asset caching.
    location ~* \.(css|js|png|jpg|jpeg|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

## cPanel / shared hosting

1. Upload the project (e.g. to `~/school-erp`).
2. Point the domain's document root to `~/school-erp/public`, **or** rely on the
   root `.htaccess` which forwards requests into `public/`.
3. Create a MySQL database + user in cPanel and set them in `.env`.
4. Use the **Terminal** (or a one-off PHP script) to run `php bin/console migrate` and `seed`.
5. Set `storage/` to `775` via the File Manager.

## HTTPS

- Obtain a certificate (Let's Encrypt / `certbot`).
- Set `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true` in `.env`.
- The app emits `Strict-Transport-Security` automatically over HTTPS.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` generated (stable — encrypts gov IDs & payment credentials)
- [ ] `APP_URL` blank (auto-detect) or set to the canonical HTTPS URL
- [ ] `SESSION_SECURE_COOKIE=true` behind HTTPS
- [ ] `storage/` writable, not web-accessible
- [ ] Database user has least-privilege grants
- [ ] `opcache.enable=1` in php.ini for performance
- [ ] Scheduled backups (`php bin/console backup` via cron)
- [ ] Deploy with `php bin/console upgrade` (backup + pending migrations only)
- [ ] Never run `migrate:fresh` in production (it's blocked unless `--force`)
- [ ] `GET /health` returns 200
- [ ] Default seed passwords changed

## Performance notes

- Enable **OPcache** (`opcache.enable=1`, `opcache.validate_timestamps=0` in prod).
- Run `composer install --optimize-autoloader --no-dev`.
- All foreign keys and lookup columns are indexed in the schema.
- Static assets are cache-friendly (see `.htaccess`/Nginx config); gzip is enabled.
- Use a CDN for the `public/assets` directory if serving at scale.

## Backups (cron)

```cron
0 2 * * * cd /var/www/school-erp && php bin/console backup >> storage/logs/backup.log 2>&1
```

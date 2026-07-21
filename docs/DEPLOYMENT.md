# Deployment Guide

The application front controller lives in **`public/`**. Always point the web
server's document root there so that application source, `.env`, and `storage/`
are never web-accessible.

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
- [ ] `APP_KEY` generated
- [ ] `SESSION_SECURE_COOKIE=true` behind HTTPS
- [ ] `storage/` writable, not web-accessible
- [ ] Database user has least-privilege grants
- [ ] `opcache.enable=1` in php.ini for performance
- [ ] Scheduled backups (`php bin/console backup` via cron)
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

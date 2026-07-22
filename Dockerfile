# ==========================================================================
# School ERP — production image for Koyeb (and any Docker host).
# PHP 8.3 + Apache, document root at /public, front-controller rewrite enabled.
# The built-in PSR-4 autoloader is used, so Composer is NOT required at runtime.
# ==========================================================================
FROM php:8.3-apache

# --- PHP extensions required by the app -----------------------------------
RUN docker-php-ext-install pdo_mysql opcache \
    && docker-php-ext-enable opcache

# --- Apache modules (rewrite / headers / expires / deflate) ----------------
RUN a2enmod rewrite headers expires deflate

# --- Production PHP settings ----------------------------------------------
RUN { \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'expose_php=Off'; \
      echo 'upload_max_filesize=${UPLOAD_LIMIT:-10M}'; \
      echo 'post_max_size=12M'; \
      echo 'memory_limit=256M'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/zz-erp.ini

# Document root -> /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/z-erp-docroot.conf \
    && a2enconf z-erp-docroot

# --- Application code ------------------------------------------------------
WORKDIR /var/www/html
COPY . /var/www/html

# Storage must be writable; entrypoint re-ensures this at runtime too.
RUN mkdir -p storage/logs storage/cache storage/sessions \
        storage/uploads/avatars storage/uploads/documents storage/backups \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Koyeb injects $PORT (default 8000); Apache is reconfigured to it at startup.
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-8000}/health" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libicu-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mysqli mbstring zip gd intl exif opcache

RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN chmod +x /usr/local/bin/entrypoint.sh 2>/dev/null || chmod +x docker/entrypoint.sh 2>/dev/null || true
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

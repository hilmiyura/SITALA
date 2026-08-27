# SITALA — PHP 7.4 + Apache
#
# PHP 7.4 bukan pilihan gaya: Smarty yang di-bundle di kick/view/smarty memakai
# fungsi each() yang dihapus di PHP 8, jadi PHP 8.x fatal error. Tag di-pin ke
# versi persis supaya build reprodusibel.
FROM php:7.4.33-apache-bullseye

# ---------------------------------------------------------------------------
# Extension PHP
#
# Sudah bawaan image resmi (tidak perlu di-compile):
#   curl, mbstring, openssl, fileinfo, session, json
# Perlu ditambahkan:
#   mysqli  -> driver database (kick/db/Adodb.php)
#   gd      -> manipulasi gambar (kick/image/Image.php)
#   zip     -> PhpSpreadsheet saat menulis .xlsx
# ---------------------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" mysqli gd zip; \
    # buang header dev-nya saja; shared library runtime-nya sengaja ditinggal
    # (tanpa --auto-remove) karena extension yang baru di-compile masih memakainya
    apt-get purge -y libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev; \
    rm -rf /var/lib/apt/lists/*

# .htaccess di root repo mengandalkan mod_rewrite untuk mengarahkan semua
# request ke index.php
RUN a2enmod rewrite

COPY docker/php/php.ini /usr/local/etc/php/conf.d/sitala.ini
COPY docker/apache/sitala.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Salin source supaya image bisa jalan mandiri (tanpa bind mount).
# Saat development, docker-compose.yml menimpa direktori ini dengan bind mount.
# Lihat .dockerignore — .env dan config/apikeys.php sengaja TIDAK ikut supaya
# kredensial tidak terpanggang ke dalam image.
COPY --chown=www-data:www-data . .

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

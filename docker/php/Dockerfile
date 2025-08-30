FROM php:8.3-apache

# --- PHP拡張 ---
RUN docker-php-ext-install pdo pdo_mysql

# --- Apache設定（mod_rewrite を有効化）---
RUN a2enmod rewrite

# override.conf をコピーして有効化
# ※ この Dockerfile と同じ階層に apache/override.conf を用意してください
COPY apache/override.conf /etc/apache2/conf-available/override.conf
RUN a2enconf override

# --- PHP タイムゾーン ---
ENV TZ=Asia/Tokyo
RUN echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www/html

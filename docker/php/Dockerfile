FROM php:8.3-apache

# PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# mod_rewrite & .htaccess
RUN a2enmod rewrite \
  && echo '<Directory /var/www/html/>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
  && a2enconf override

# TZ
ENV TZ=Asia/Tokyo
RUN echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www/html

# PHP 8.3 + Apache の公式イメージをベース
FROM php:8.3-apache

# 対話ダイアログ抑止
ENV DEBIAN_FRONTEND=noninteractive

# --- PHP拡張に必要なライブラリ ---
# ここに git / unzip / libzip を追加し、zip拡張もビルド
RUN apt-get update && apt-get install -y --no-install-recommends \
    locales \
    libicu-dev \
    libonig-dev \
    zlib1g-dev \
    libzip-dev \
    git \
    unzip \
    pkg-config \
    default-mysql-client \
 && docker-php-ext-configure zip \
 && docker-php-ext-install -j"$(nproc)" intl pdo_mysql mbstring zip \
 && apt-get purge -y --auto-remove pkg-config \
 && rm -rf /var/lib/apt/lists/*

# --- ロケール設定（日本語UTF-8）---
RUN sed -i 's/# *ja_JP.UTF-8 UTF-8/ja_JP.UTF-8 UTF-8/' /etc/locale.gen \
    && locale-gen ja_JP.UTF-8
ENV LANG=ja_JP.UTF-8 \
    LANGUAGE=ja_JP:ja \
    LC_ALL=ja_JP.UTF-8

# --- Apache の mod_rewrite と mod_headers を有効化 ---
RUN a2enmod rewrite headers

# .htaccess を有効化する設定ファイルをコピーして反映
COPY apache/override.conf /etc/apache2/conf-available/override.conf
RUN a2enconf override

# --- PHPのタイムゾーンを東京に設定 ---
ENV TZ=Asia/Tokyo
RUN echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

# --- Composer をインストール ---
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
# （root実行の警告を消したい場合）
# ENV COMPOSER_ALLOW_SUPERUSER=1

# --- 作業ディレクトリを /var/www/html に設定 ---
WORKDIR /var/www/html

# アプリ一式をコピー（.dockerignore で node_modules や vendor を除外推奨）
COPY . /var/www/html

# パーミッション（必要に応じて）
RUN chown -R www-data:www-data /var/www/html

# PHP 8.3 + Apache の公式イメージをベースにする
FROM php:8.3-apache

#apt-get 実行時の対話ダイアログを抑止するため
ENV DEBIAN_FRONTEND=noninteractive

# --- PHP拡張に必要なライブラリ ---
# oniguruma系（mbstring依存）と ICU（intl用）を追加
RUN apt-get update && apt-get install -y --no-install-recommends \
    locales \
    libicu-dev \
    libonig-dev \
    pkg-config \
    default-mysql-client \
 && docker-php-ext-install -j"$(nproc)" intl pdo_mysql mbstring \
 && apt-get purge -y --auto-remove pkg-config \
 && rm -rf /var/lib/apt/lists/*

# --- ロケール設定（日本語UTF-8）---
RUN sed -i 's/# *ja_JP.UTF-8 UTF-8/ja_JP.UTF-8 UTF-8/' /etc/locale.gen \
    && locale-gen ja_JP.UTF-8
ENV LANG=ja_JP.UTF-8 \
    LANGUAGE=ja_JP:ja \
    LC_ALL=ja_JP.UTF-8

# --- Apache の mod_rewrite を有効化 ---
RUN a2enmod rewrite
# .htaccess を有効化する設定ファイルをコピーして反映
COPY apache/override.conf /etc/apache2/conf-available/override.conf
RUN a2enconf override

# --- PHPのタイムゾーンを東京に設定 ---
ENV TZ=Asia/Tokyo
RUN echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini

# --- Composer をインストール ---
# 公式 composer イメージから /usr/bin/composer をコピーして利用
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# --- 作業ディレクトリを /var/www/html に設定 ---
WORKDIR /var/www/html

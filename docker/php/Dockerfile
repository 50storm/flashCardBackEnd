# PHP 8.3 + Apache の公式イメージをベースにする
FROM php:8.3-apache

# --- PHP拡張や必要なツールをインストール ---
RUN apt-get update && apt-get install -y \
    locales \            # 日本語ロケール用
    libicu-dev \         # intl拡張用
    git unzip \          # composer が依存取得に使う
    && docker-php-ext-install pdo pdo_mysql mbstring intl \
    # ↑ pdo_mysql: MySQL接続 / mbstring: マルチバイト処理 / intl: 国際化
    && rm -rf /var/lib/apt/lists/*   # キャッシュ削除で軽量化

# --- ロケールを日本語UTF-8に設定 ---
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
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- 作業ディレクトリを /var/www/html に設定 ---
WORKDIR /var/www/html

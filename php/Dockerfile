# ベースとなる公式PHPイメージ
FROM php:8.1-apache

# 必要なシステムパッケージをインストール
# libzip-dev: PHPのzip拡張機能をインストールするために必須
# git, unzip: Composerがライブラリをダウンロード・展開するために必要
RUN apt-get update && apt-get install -y \
    libzip-dev \
    git \
    unzip

# 必要なPHP拡張機能をインストール
# pdo_mysql: データベース接続用
# zip: Composerがzipファイルを扱うために必要
RUN docker-php-ext-install pdo_mysql zip

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリを設定
WORKDIR /var/www/html
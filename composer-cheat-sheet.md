# vendor をホスト側(src/vendor)に生成する
docker compose exec app composer install

# 本番用に最適化してインストール
docker compose exec app composer install --no-dev --optimize-autoloader --prefer-dist

# ライブラリ更新
docker compose exec app composer update

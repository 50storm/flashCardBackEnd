# ユーザーに渡すための Markdown ファイルを生成する
content = """# 🐳 Docker Compose よく使うコマンド

## ビルド関連
```bash
# ビルド（Dockerfileを変更したら必須）
docker compose build

# キャッシュ無視してクリーンビルド
docker compose build --no-cache


# 起動（バックグラウンド）
docker compose up -d

# 起動（ビルドも同時に）
docker compose up -d --build


# 停止
docker compose down

# 停止 + ボリューム削除（DBデータも消える）
docker compose down -v

# 稼働中コンテナの確認
docker ps

# 特定サービスのログを確認
docker compose logs -f app
docker compose logs -f db
docker compose logs -f phpmyadmin


# コンテナに入る（例: app）
docker compose exec app bash

# DBコンテナに入る（例: mysql）
docker compose exec db mysql -u sample_user -p

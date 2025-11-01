# 🧭 開発マニュアル（Docker + PHP環境）

## 概要
本プロジェクトでは、**PHP の依存ライブラリはコンテナ内で composer により管理**し、  
生成された `vendor/` を **Git でコミットして共有** します。

この構成により：
- 開発環境と本番環境の差異を排除  
- PHP・Composer のバージョン差異を防止  
- 依存関係の再現性を保証

---

## 🚀 基本ルール

| 項目 | 方針 |
|------|------|
| ライブラリ追加・更新 | コンテナ内で `composer require` を実行 |
| クラス追加・削除 | `composer dump-autoload` を実行 |
| `vendor/` | **Git 管理対象（削除禁止）** |
| ホスト環境で composer 実行 | ❌ 禁止（環境不整合防止のため） |

---

## 🧩 開発手順

### 初回時、Dockerfileを変更した場合は再ビルド：

```bash
docker-compose build --no-cache
```

---

### 1️⃣ コンテナを起動

```bash
docker-compose up -d
```

---

### 2️⃣ コンテナに入る

```bash
docker exec -it php-app bash
```

（`php-app` は `docker-compose.yml` の `container_name`）

---

### 3️⃣ 必要なライブラリを追加・更新

```bash
composer require パッケージ名
```

例：

```bash
composer require zircote/swagger-php
```

これにより：
- `/var/www/html/vendor` にライブラリが追加される  
- ホストの `vendor/`・`composer.json`・`composer.lock` にも自動反映される（volume共有のため）

---

### 4️⃣ クラスを追加・削除した場合

オートローダーを再生成：

```bash
composer dump-autoload
```

> ⚠️ クラス追加・削除・リネーム時は必ず実行すること。

---

### 5️⃣ 変更内容を Git に反映

ホスト側で：

```bash
git add vendor composer.json composer.lock
git commit -m "Update dependencies (via container composer)"
```

---

## 📂 ディレクトリ構成例

```
flashCardBackEnd/
├── docker/
│   └── php/
│       └── Dockerfile
├── src/
│   ├── Controller/
│   ├── Model/
│   └── swagger.php        ← Swagger定義など
├── doc/
│   └── openapi.yaml
├── vendor/                ← 依存パッケージ（Git管理対象）
├── composer.json
├── composer.lock
└── docker-compose.yml
```

---

## 🧠 よく使うコマンドまとめ

| 操作 | コマンド |
|------|-----------|
| コンテナ起動 | `docker-compose up -d` |
| コンテナに入る | `docker exec -it php-app bash` |
| ライブラリ追加 | `composer require パッケージ名` |
| オートローダー更新 | `composer dump-autoload` |
| Swaggerドキュメント生成 | `./vendor/bin/openapi --output ./doc/openapi.yaml ./src` |
| コンテナ停止 | `docker-compose down` |

---

## ⚙️ 注意事項

- `rm -rf vendor` は絶対に実行しないこと（依存関係が消失します）  
- `composer.lock` は削除せず、必ずコミットすること  
- ホスト側で `composer install` や `require` を実行しないこと  
- PHP・Composer のバージョンは Docker イメージ内で固定管理されています  

---

## 🧾 トラブルシューティング

| 症状 | 対応方法 |
|------|-----------|
| コンテナが立ち上がらない | `docker logs php-app` でログ確認 |
| `openapi: not found` | コンテナ内で `composer require zircote/swagger-php` を実行 |
| `@OA\Info() not found` | `src/swagger.php` に基本アノテーションを追加 |

---

## ✅ まとめ

- Composer は **コンテナ内でのみ操作**  
- `vendor` と `composer.lock` は **Git 管理必須**  
- クラスを追加・削除したら **`composer dump-autoload`**

---

✍️ Author: *Project FlashCard Backend Team*

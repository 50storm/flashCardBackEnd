# 📘 API 操作確認コマンド集（完全版）

---

## ✅ 共通：基本確認

### ローカル（http://localhost:8080）

```bash
# コンテンツ確認
curl http://localhost:8080/

# ヘッダー確認
curl -I http://localhost:8080/
```

### phpMyAdmin（http://localhost:8081）

```bash
# ヘッダー確認
curl -I http://localhost:8081/

# HTML冒頭確認（phpMyAdminのタイトル）
curl http://localhost:8081/ | head -n 20
```

---

## 🧪 ローカル環境（セッション認証：Cookie使用）

```bash
# 1) ユーザー作成
curl -i -X POST 'http://localhost:8080/user' \
  -H 'Origin: http://localhost:3000' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Iga","email":"test@example.com","password":"secret"}'

# 2) ログイン（Cookie保存）
curl -i -c cookiejar.txt -X POST 'http://localhost:8080/login' \
  -H 'Origin: http://localhost:3000' \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"secret"}'

# 3) /me（Cookie送信）
curl -i -b cookiejar.txt 'http://localhost:8080/me' \
  -H 'Origin: http://localhost:3000'

# 4) ログアウト（Cookie送信）
curl -i -b cookiejar.txt -X POST 'http://localhost:8080/logout' \
  -H 'Origin: http://localhost:3000'
```

---

## 🌐 本番サーバ（JWT認証：https://xs225231.xsrv.jp/flashcard）

```bash
# 1) コンテンツ確認（ルート）
curl https://xs225231.xsrv.jp/flashcard/

# 2) ユーザー作成
curl -i -X POST 'https://xs225231.xsrv.jp/flashcard/user' \
  -H 'Origin: https://xs225231.xsrv.jp' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Iga","email":"test@example.com","password":"secret"}'

# 3) ログイン（JWT取得）
curl -i -X POST 'https://xs225231.xsrv.jp/flashcard/auth/login' \
  -H 'Origin: https://xs225231.xsrv.jp' \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"secret"}'
```

> ✅ 上記のレスポンスの `access_token` をコピーして、以下に使用。

```bash
# 4) /me（JWT送信）
curl -i -X GET 'https://xs225231.xsrv.jp/flashcard/api/me' \
  -H 'Authorization: Bearer [ここにトークン]' \
  -H 'Origin: https://xs225231.xsrv.jp'

# 5) フラッシュカード一覧
curl -i -X GET 'https://xs225231.xsrv.jp/flashcard/api/flash-cards' \
  -H 'Authorization: Bearer [ここにトークン]' \
  -H 'Origin: https://xs225231.xsrv.jp'

# 6) フラッシュカード作成
curl -i -X POST 'https://xs225231.xsrv.jp/flashcard/api/flash-cards' \
  -H 'Authorization: Bearer [ここにトークン]' \
  -H 'Content-Type: application/json' \
  -d '{"front": "Front Text", "back": "Back Text"}'

# 7) フラッシュカード削除（id=1の例）
curl -i -X DELETE 'https://xs225231.xsrv.jp/flashcard/api/flash-cards/1' \
  -H 'Authorization: Bearer [ここにトークン]'

# 8) 削除済みカードの復元（id=1の例）
curl -i -X POST 'https://xs225231.xsrv.jp/flashcard/api/flash-cards/1/restore' \
  -H 'Authorization: Bearer [ここにトークン]'
```

---

> 🔒 **注意**：
> - `[ここにトークン]` は `/auth/login` のレスポンスに含まれる `access_token` に置き換えてください。
> - Cookie 認証（ローカル）と JWT 認証（本番）は仕組みが異なります。

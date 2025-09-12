# セッションCookie方式（HTTPS対応メモ）

## 1. Cookie設定
```php
session_name('flashcard_sid');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',           // サブドメイン共通で使うなら設定
  'secure' => true,         // HTTPS接続でのみ送信
  'httponly' => true,       // JSからアクセス不可
  'samesite' => 'None',     // クロスオリジンSPAなら必須（Laxだと送信されない）
]);
```

- **secure: true** → 本番は必ず有効化（HTTPS必須）  
- **samesite: None** → APIとフロントが別ドメインの場合は必要  

---

## 2. サーバー
- 本番は **Let’s Encrypt** 等で正規証明書を導入  
- 開発は **mkcert** などで自己署名証明書を作成して Nginx/Apache に設定  

---

## 3. CORS 設定（Slim側）
```php
$response
  ->withHeader('Access-Control-Allow-Origin', 'https://frontend.example.com')
  ->withHeader('Access-Control-Allow-Credentials', 'true');
```
- Origin は完全一致で返す（ワイルドカード不可）  
- `Access-Control-Allow-Credentials: true` が必須  

---

## 4. フロント側
```js
// fetch
fetch("https://api.example.com/me", {
  method: "GET",
  credentials: "include"
});

// axios
axios.get("https://api.example.com/me", { withCredentials: true });
```

---

## 5. 確認事項
- Cookieは `secure=true` なので HTTP では送信されない  
- クロスオリジンで動かす場合は `samesite=None` を忘れずに  
- ログイン → Cookie保存 → `/me` アクセスで確認  

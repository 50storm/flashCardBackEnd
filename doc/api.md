# API動作確認（curl）

## 1) 新規ユーザー作成
```bash
curl -X POST http://localhost:8080/user \
  -H "Content-Type: application/json" \
  -d '{"name":"テスト","email":"test@example.com","password":"password"}'
```

## 2) ログイン（Cookie保存）
```bash
curl -i -c cookiejar.txt -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

## 3) 保護ルート /me（Cookie送信）
```bash
curl -b cookiejar.txt http://localhost:8080/me
```

## 4) ログアウト（セッション破棄）
```bash
curl -b cookiejar.txt -X POST http://localhost:8080/logout
```

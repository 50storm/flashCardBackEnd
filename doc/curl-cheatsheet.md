# コンテンツをそのまま確認
curl http://localhost:8080/

# ヘッダーだけ確認
curl -I http://localhost:8080/


# ヘッダーを確認
curl -I http://localhost:8081/

# HTMLの冒頭を確認（タイトルに phpMyAdmin が見えるはず）
curl http://localhost:8081/ | head -n 20


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

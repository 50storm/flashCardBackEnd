# コンテンツをそのまま確認
curl http://localhost:8080/

# ヘッダーだけ確認
curl -I http://localhost:8080/


# ヘッダーを確認
curl -I http://localhost:8081/

# HTMLの冒頭を確認（タイトルに phpMyAdmin が見えるはず）
curl http://localhost:8081/ | head -n 20

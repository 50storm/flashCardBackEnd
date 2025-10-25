#!/bin/bash
# ================================
# 🚀 Deploy Slim PHP backend to Xserver via SFTP (Production Vendor Deployment - TAR version)
# ================================

set -e  # エラーで即停止

# === 設定読み込み (.env.deploy_backendから) ===
if [ -f .env.deploy_backend ]; then
  export $(grep -v '^#' .env.deploy_backend | xargs)
else
  echo "❌ .env.deploy_backend file not found. Aborting."
  exit 1
fi

# === 変数チェック ===
if [ -z "$XSERVER_HOST" ] || [ -z "$XSERVER_USER" ] || [ -z "$XSERVER_PORT" ] || [ -z "$XSERVER_REMOTE_PATH" ] || [ -z "$XSERVER_KEY_PATH" ]; then
  echo "❌ Missing environment variables. Please check .env.deploy_backend"
  exit 1
fi

# === ローカルでvendor_prod生成 ===
echo "📦 Building production vendor directory..."
rm -rf vendor_prod
composer config vendor-dir vendor_prod
composer install \
  --no-dev \
  --optimize-autoloader \
  --ignore-platform-reqs \
  --no-interaction \
  --no-progress \
  --prefer-dist
composer config --unset vendor-dir

# === 圧縮 (tar.gz形式) ===
echo "🗜️ Compressing vendor_prod to tar.gz..."
tar -czf vendor_prod.tar.gz vendor_prod

# === デプロイ対象 ===
LOCAL_PATH="./"
echo "📂 Local project path: $LOCAL_PATH"
echo "📡 Remote path: $XSERVER_REMOTE_PATH"

# === SFTP転送 ===
echo "🚀 Uploading project files to Xserver..."
sftp -i "$XSERVER_KEY_PATH" -P "$XSERVER_PORT" -o StrictHostKeyChecking=no "$XSERVER_USER@$XSERVER_HOST" <<EOF
cd $XSERVER_REMOTE_PATH
lcd $LOCAL_PATH
put index.php
put -r src
put -r resources
put vendor_prod.tar.gz
bye
EOF

# === サーバ側でvendor展開 ===
echo "📦 Extracting vendor_prod.tar.gz on remote server..."
ssh -i "$XSERVER_KEY_PATH" -p "$XSERVER_PORT" -o StrictHostKeyChecking=no "$XSERVER_USER@$XSERVER_HOST" <<EOF
cd $XSERVER_REMOTE_PATH
rm -rf vendor_old vendor
if [ -d vendor ]; then mv vendor vendor_old; fi
tar -xzf vendor_prod.tar.gz
mv vendor_prod vendor
rm vendor_prod.tar.gz
echo "✅ Vendor installed on server."
EOF

# === ローカル後処理 ===
rm -f vendor_prod.tar.gz
rm -rf vendor_prod

echo "✅ Deployment complete! (vendor_prod → vendor)"

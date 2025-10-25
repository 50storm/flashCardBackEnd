#!/bin/bash
# ================================
# 🚀 Deploy Slim PHP backend to Xserver via SFTP (Selective Upload)
# ================================

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

# === デプロイ対象 ===
LOCAL_PATH="./"
echo "📂 Local project path: $LOCAL_PATH"
echo "📡 Remote path: $XSERVER_REMOTE_PATH"

# === SFTP転送 ===
echo "🚀 Deploying Slim PHP backend to Xserver (vendor, index.php, src, resources)..."
sftp -i "$XSERVER_KEY_PATH" -P "$XSERVER_PORT" -o StrictHostKeyChecking=no "$XSERVER_USER@$XSERVER_HOST" <<EOF
cd $XSERVER_REMOTE_PATH
lcd $LOCAL_PATH
put -r vendor
put index.php
put -r src
put -r resources
bye
EOF

if [ $? -eq 0 ]; then
  echo "✅ Backend deployment complete! (Selective Upload)"
else
  echo "❌ Deployment failed."
  exit 1
fi

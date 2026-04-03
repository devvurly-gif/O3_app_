#!/bin/bash
# Setup Laravel Reverb WebSocket on production server
# Usage: sudo bash deployment/setup-reverb.sh
set -e

APP_DIR="/var/www/O3_app"
SERVICE_FILE="$APP_DIR/deployment/reverb.service"

echo "==> Setting up Laravel Reverb..."

# 1. Install systemd service
echo "==> Installing reverb.service..."
cp "$SERVICE_FILE" /etc/systemd/system/reverb.service
systemctl daemon-reload
systemctl enable reverb
systemctl start reverb

# 2. Update .env for production WebSocket (through Nginx proxy)
echo "==> Updating .env VITE variables for production..."
cd "$APP_DIR"

# In production, WS connects to the current domain on port 80/443 via Nginx /app proxy
# So VITE_REVERB_HOST should be empty (falls back to window.location.hostname)
# and VITE_REVERB_PORT should be 80 (or 443 with SSL)
sed -i 's|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=|' .env
sed -i 's|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=80|' .env

# 3. Rebuild frontend with new env
echo "==> Rebuilding frontend..."
npm run build

# 4. Reload Nginx (to pick up /app proxy)
echo "==> Reloading Nginx..."
cp "$APP_DIR/deployment/nginx/o3app.conf" /etc/nginx/sites-available/o3app.conf
nginx -t && systemctl reload nginx

# 5. Check status
echo ""
echo "==> Reverb status:"
systemctl status reverb --no-pager -l

echo ""
echo "==> Done! WebSocket server running on 127.0.0.1:8080"
echo "    Nginx proxies /app → Reverb"
echo "    Clients connect via ws://DOMAIN/app/KEY"

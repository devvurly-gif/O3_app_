#!/bin/bash
# Setup Laravel queue worker as a systemd service on production.
# Usage: sudo bash deployment/setup-queue.sh
#
# Idempotent: re-running just refreshes the unit file and restarts.
set -e

APP_DIR="/var/www/O3_app"
SERVICE_FILE="$APP_DIR/deployment/o3-queue.service"
TARGET="/etc/systemd/system/o3-queue.service"

echo "==> Installing o3-queue.service..."
cp "$SERVICE_FILE" "$TARGET"
systemctl daemon-reload
systemctl enable o3-queue
systemctl restart o3-queue

echo ""
echo "==> Status:"
systemctl status o3-queue --no-pager -l | head -15

echo ""
echo "==> Done. Useful commands:"
echo "    systemctl status o3-queue          # one-shot status"
echo "    journalctl -u o3-queue -f          # tail logs"
echo "    php artisan queue:restart          # graceful reload (used by deploy.sh)"

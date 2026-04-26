#!/bin/bash
# Setup Laravel scheduler as a systemd timer on production.
# Usage: sudo bash deployment/setup-scheduler.sh
#
# Idempotent: re-running just refreshes the unit files and (re)starts.
set -e

APP_DIR="/var/www/O3_app"
DEP="$APP_DIR/deployment"

echo "==> Installing o3-scheduler.service + o3-scheduler.timer..."
cp "$DEP/o3-scheduler.service" /etc/systemd/system/o3-scheduler.service
cp "$DEP/o3-scheduler.timer"   /etc/systemd/system/o3-scheduler.timer
systemctl daemon-reload
systemctl enable --now o3-scheduler.timer

echo ""
echo "==> Timer status:"
systemctl status o3-scheduler.timer --no-pager -l | head -12

echo ""
echo "==> Next firings:"
systemctl list-timers o3-scheduler.timer --no-pager

echo ""
echo "==> Done. Useful commands:"
echo "    systemctl status o3-scheduler.timer  # timer state + next run"
echo "    journalctl -u o3-scheduler -f        # tail every minute's run"
echo "    php artisan schedule:list            # verify registered tasks"

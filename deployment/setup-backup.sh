#!/bin/bash
# Setup automated MySQL backups on production.
# Usage: sudo bash deployment/setup-backup.sh
#
# Idempotent: re-running refreshes /root/.my.cnf from .env, the unit
# files, and (re)starts the timer.
set -e

APP_DIR="/var/www/O3_app"
DEP="$APP_DIR/deployment"
BACKUP_ROOT="/var/backups/o3_app"
MYCNF="/root/.my.cnf"

echo "==> Reading DB creds from $APP_DIR/.env..."
DB_USER="$(grep '^DB_USERNAME=' "$APP_DIR/.env" | cut -d= -f2-)"
DB_PASS="$(grep '^DB_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)"
DB_HOST="$(grep '^DB_HOST=' "$APP_DIR/.env" | cut -d= -f2-)"
DB_PORT="$(grep '^DB_PORT=' "$APP_DIR/.env" | cut -d= -f2-)"

if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "FATAL: DB_USERNAME or DB_PASSWORD missing in .env" >&2
    exit 1
fi

echo "==> Writing $MYCNF (mode 600)..."
cat > "$MYCNF" <<EOF
[client]
user=$DB_USER
password=$DB_PASS
host=${DB_HOST:-127.0.0.1}
port=${DB_PORT:-3306}
default-character-set=utf8mb4
EOF
chmod 600 "$MYCNF"

echo "==> Creating $BACKUP_ROOT..."
mkdir -p "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT"

echo "==> Sanity check: list databases via $MYCNF..."
mysql -BNe "SHOW DATABASES" | head -10

echo "==> Installing systemd units..."
cp "$DEP/o3-backup.service" /etc/systemd/system/o3-backup.service
cp "$DEP/o3-backup.timer"   /etc/systemd/system/o3-backup.timer
systemctl daemon-reload
systemctl enable --now o3-backup.timer

echo ""
echo "==> Timer status:"
systemctl status o3-backup.timer --no-pager -l | head -10

echo ""
echo "==> Next firing:"
systemctl list-timers o3-backup.timer --no-pager

echo ""
echo "==> Done. Useful commands:"
echo "    systemctl status o3-backup.timer       # timer state + next run"
echo "    journalctl -u o3-backup -f             # tail backup runs"
echo "    systemctl start o3-backup              # run a backup right now"
echo "    bash $DEP/backup-db.sh --dry-run       # see what would be dumped"
echo "    ls -la $BACKUP_ROOT/                   # list dated folders"

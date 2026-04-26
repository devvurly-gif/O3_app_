#!/bin/bash
set -e

APP_DIR="/var/www/O3_app"
BRANCH="main"

echo "=== O3 App Deployment ==="
cd "$APP_DIR"

# ── Pre-flight: refuse to deploy with dev-mode env (H4) ─────────────
# APP_DEBUG=true renders Ignition stack traces (DB creds, env dump)
# on any unhandled exception. APP_ENV=local disables prod-only
# middlewares. Either one in production is a leak waiting to happen.
if [ -f "$APP_DIR/.env" ]; then
    if grep -qE '^APP_DEBUG=true' "$APP_DIR/.env"; then
        echo "FATAL: APP_DEBUG=true in .env. Set APP_DEBUG=false before deploying." >&2
        exit 1
    fi
    if grep -qE '^APP_ENV=local' "$APP_DIR/.env"; then
        echo "FATAL: APP_ENV=local in .env. Set APP_ENV=production before deploying." >&2
        exit 1
    fi
    # L3: without an explicit SANCTUM_STATEFUL_DOMAINS, the config
    # falls back to a list that includes localhost / 127.0.0.1 —
    # acceptable for dev, noise in prod. Warn loudly.
    if ! grep -qE '^SANCTUM_STATEFUL_DOMAINS=.+' "$APP_DIR/.env"; then
        echo "WARN: SANCTUM_STATEFUL_DOMAINS is not set. Falling back to a list that includes localhost." >&2
        echo "      Add e.g. SANCTUM_STATEFUL_DOMAINS=teliphoni.o3app.ma to the prod .env." >&2
    fi
fi

# Pull latest
echo "[1/7] Pulling latest from $BRANCH..."
git pull origin "$BRANCH"

# Install PHP dependencies
echo "[2/7] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations
echo "[3/7] Running migrations..."
php artisan migrate --force

# Cache config, routes, views
echo "[4/7] Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Build frontend
echo "[5/7] Building frontend assets..."
npm ci --production=false
npm run build

# Restart queue workers (graceful)
echo "[6/7] Restarting queue workers..."
php artisan queue:restart

# Setup Nginx (first deploy only)
if [ ! -f /etc/nginx/sites-enabled/o3app.conf ]; then
    echo "[*] Installing Nginx config..."
    sudo cp "$APP_DIR/deployment/nginx/o3app.conf" /etc/nginx/sites-available/o3app.conf
    sudo ln -sf /etc/nginx/sites-available/o3app.conf /etc/nginx/sites-enabled/o3app.conf
    sudo rm -f /etc/nginx/sites-enabled/default
    sudo nginx -t && sudo systemctl reload nginx
fi

# Setup Supervisor — only if supervisorctl is installed on this VPS.
# Some VPS use systemd or a separate orchestrator for queue workers;
# don't fail the whole deploy just because supervisor is absent.
if command -v supervisorctl >/dev/null 2>&1 && [ -d /etc/supervisor/conf.d ]; then
    if [ ! -f /etc/supervisor/conf.d/o3-queue-worker.conf ]; then
        echo "[*] Installing Supervisor config..."
        sudo cp "$APP_DIR/deployment/supervisor/o3-queue-worker.conf" /etc/supervisor/conf.d/
        sudo supervisorctl reread
        sudo supervisorctl update
    fi
    sudo supervisorctl start o3-queue-worker:* || true
else
    echo "[*] Supervisor non installé sur ce VPS — étape ignorée."
    echo "    (queue workers à gérer via systemd ou autre)"
fi

echo "[7/7] Setting permissions..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "=== Deployment complete! ==="
if command -v supervisorctl >/dev/null 2>&1; then
    echo "Queue workers status:"
    sudo supervisorctl status o3-queue-worker:* 2>/dev/null || true
fi

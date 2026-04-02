#!/bin/bash
set -e

APP_DIR="/var/www/O3_app"
BRANCH="main"

echo "=== O3 App Deployment ==="
cd "$APP_DIR"

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

# Setup Supervisor (first deploy only)
if [ ! -f /etc/supervisor/conf.d/o3-queue-worker.conf ]; then
    echo "[*] Installing Supervisor config..."
    sudo cp "$APP_DIR/deployment/supervisor/o3-queue-worker.conf" /etc/supervisor/conf.d/
    sudo supervisorctl reread
    sudo supervisorctl update
fi

# Ensure supervisor is running
sudo supervisorctl start o3-queue-worker:*

echo "[7/7] Setting permissions..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "=== Deployment complete! ==="
echo "Queue workers status:"
sudo supervisorctl status o3-queue-worker:*

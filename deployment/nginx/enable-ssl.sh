#!/bin/bash
# Enable SSL for o3app.ma
# Usage: sudo bash deployment/nginx/enable-ssl.sh
set -e

DOMAIN="o3app.ma"
CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
CONF="/etc/nginx/sites-available/o3app.conf"

# 1. Get certificate if not exists
if [ ! -f "$CERT" ]; then
    echo "==> Getting SSL certificate..."
    certbot certonly --manual --preferred-challenges dns \
        -d "$DOMAIN" -d "*.$DOMAIN"
fi

# 2. Check cert exists now
if [ ! -f "$CERT" ]; then
    echo "ERROR: Certificate not found at $CERT"
    exit 1
fi

# 3. Add SSL to Nginx config
echo "==> Updating Nginx config with SSL..."

# Add ssl listen + certs + redirect to each server block
python3 -c "
import re

conf = open('$CONF').read()

ssl_block = '''
    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;'''

# Add 'listen 443 ssl http2;' after each 'listen 80;'
conf = conf.replace('listen 80;', 'listen 80;\n    listen 443 ssl http2;')

# Add ssl certs after each 'listen 443' line
conf = conf.replace('listen 443 ssl http2;', 'listen 443 ssl http2;' + ssl_block)

# Add HTTP->HTTPS redirect at the top
redirect = '''# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name o3app.ma *.o3app.ma;
    return 301 https://\$host\$request_uri;
}

'''

# Only add redirect if not already there
if 'return 301' not in conf:
    conf = redirect + conf

open('$CONF', 'w').write(conf)
"

# 4. Test and reload
nginx -t && systemctl reload nginx

# 5. Setup auto-renewal
if ! crontab -l 2>/dev/null | grep -q certbot; then
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
    echo "==> Auto-renewal cron added"
fi

echo "==> SSL enabled! Site available at https://$DOMAIN"

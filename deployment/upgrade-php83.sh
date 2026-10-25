#!/usr/bin/env bash
# À lancer sur le VPS, en root :  bash upgrade-php83.sh
#
# Installe PHP 8.3 À CÔTÉ de la 8.2 et ne bascule que O3_app (nginx o3app.ma + workers systemd).
# Les autres applications du VPS (sarf, mondial26score, worldcup2026...) restent sur php8.2-fpm.
# Retour arrière : bash upgrade-php83.sh rollback
set -euo pipefail

NGINX_CONF=/etc/nginx/sites-enabled/o3app.ma
UNITS=(/etc/systemd/system/o3-queue.service /etc/systemd/system/o3-scheduler.service)
STAMP=$(date +%Y%m%d-%H%M%S)

switch_to() {   # $1 = ancienne version, $2 = nouvelle
    local from=$1 to=$2
    sed -i "s#php${from}-fpm.sock#php${to}-fpm.sock#g" "$NGINX_CONF"
    for u in "${UNITS[@]}"; do
        [ -f "$u" ] && sed -i -E "s#/usr/bin/php(8\.[0-9])? #/usr/bin/php${to} #" "$u"
    done
    nginx -t
    systemctl daemon-reload
    systemctl reload nginx
    systemctl restart o3-queue
}

if [ "${1:-}" = "rollback" ]; then
    switch_to 8.3 8.2
    echo "[OK] retour sur PHP 8.2"
    exit 0
fi

echo "[1/5] Installation de PHP 8.3 (la 8.2 reste en place)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y php8.3-{fpm,cli,common,mysql,mbstring,xml,curl,zip,gd,intl,bcmath,opcache,readline,redis,imagick,igbinary}

# L'installation de php8.3-cli prend la main sur « php » : on le rend à la 8.2 pour les autres applications.
update-alternatives --set php /usr/bin/php8.2
update-alternatives --set phar /usr/bin/phar8.2 || true
update-alternatives --set phar.phar /usr/bin/phar.phar8.2 || true

echo "[2/5] Reprise des réglages de la 8.2"
sed -i 's/^expose_php.*/expose_php = Off/; s/^post_max_size.*/post_max_size = 50M/; s/^upload_max_filesize.*/upload_max_filesize = 10M/' /etc/php/8.3/fpm/php.ini
sed -i 's/^pm.max_children.*/pm.max_children = 8/' /etc/php/8.3/fpm/pool.d/www.conf
systemctl enable --now php8.3-fpm
systemctl restart php8.3-fpm
test -S /run/php/php8.3-fpm.sock

echo "[3/5] Vérification des extensions requises par le projet"
cd /var/www/O3_app
php8.3 -r 'foreach (["pdo_mysql","mbstring","xml","curl","zip","gd","intl","bcmath","redis","imagick","Zend OPcache"] as $e) if (!extension_loaded($e)) { fwrite(STDERR, "extension manquante : $e\n"); exit(1); }'
php8.3 artisan --version

echo "[4/5] Bascule de O3_app (sauvegardes horodatées)"
cp -a "$NGINX_CONF" "/root/o3app.ma.nginx.pre-php83.$STAMP"
for u in "${UNITS[@]}"; do [ -f "$u" ] && cp -a "$u" "/root/$(basename "$u").pre-php83.$STAMP"; done
if ! switch_to 8.2 8.3; then
    echo "[ERREUR] bascule échouée, retour sur 8.2"
    cp -a "/root/o3app.ma.nginx.pre-php83.$STAMP" "$NGINX_CONF"
    nginx -t && systemctl reload nginx
    exit 1
fi

echo "[5/5] Contrôle"
sleep 2
code=$(curl -s -o /dev/null -w '%{http_code}' -k https://localhost -H 'Host: teliphoni.o3app.ma')
echo "HTTP $code"
systemctl is-active php8.3-fpm php8.2-fpm nginx o3-queue
[ "$code" = "200" ] || { echo "[ERREUR] le site ne répond pas 200 : bash upgrade-php83.sh rollback"; exit 1; }
echo "[OK] O3_app tourne sur PHP 8.3 ; les autres sites restent sur 8.2"

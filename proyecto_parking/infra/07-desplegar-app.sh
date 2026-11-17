#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · 07 · Desplegament de l'aplicació PHP a parking-srv
#
#  Abans, des de Windows (carpeta del projecte):
#     scp -r proyecto_parking\app_php proyecto_parking\infra carlos@192.168.56.30:~
#  Execució:
#     ssh -t carlos@192.168.56.30 "sudo bash ~/infra/07-desplegar-app.sh"
#
#  Què fa:
#   1. Instal·la php-fpdf (generació dels PDF de les reserves).
#   2. Fa una còpia de seguretat de la web actual a /root/.
#   3. Copia public/ i src/ de ~/app_php a /srv/www/csjparkings/
#      SENSE tocar config/config.php (contrasenyes) ni storage/.
#   4. Afegeix 'app_key' (clau AES-256) a config.php si encara no hi és.
#   5. Ajusta permisos: codi root:www-data 750/640 (Apache només llegeix).
#   6. Comprova la sintaxi de tots els fitxers PHP i que la web respongui.
#  Deixa un informe a ~/informe-07-app.txt
# =====================================================================
set -euo pipefail

ORIGEN="${ORIGEN:-/home/${SUDO_USER:-carlos}/app_php}"
WEB=/srv/www/csjparkings
INFORME="/home/${SUDO_USER:-carlos}/informe-07-app.txt"
DATA=$(date +%Y%m%d-%H%M%S)

[[ $EUID -eq 0 ]] || { echo "Executa'l amb sudo"; exit 1; }
[[ -d $ORIGEN/public && -d $ORIGEN/src ]] || { echo "No trobo $ORIGEN (copia-hi app_php amb scp)"; exit 1; }

exec > >(tee "$INFORME") 2>&1
pas() { echo; echo "=================== $* ==================="; }
cmd() { echo "\$ $*"; "$@"; }

echo "CSJ Parkings · desplegament de l'aplicació · $(date '+%d/%m/%Y %H:%M:%S') · $(hostname)"

# ---------------------------------------------------------------------
pas "1. Paquet php-fpdf"
if ! dpkg -s php-fpdf >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y -q php-fpdf >/dev/null
fi
cmd dpkg -s php-fpdf | grep -E '^(Package|Status|Version)'
cmd ls -l /usr/share/php/fpdf/fpdf.php

# ---------------------------------------------------------------------
pas "2. Còpia de seguretat de la web actual"
tar czf "/root/web-abans-app-$DATA.tgz" -C /srv/www csjparkings
cmd ls -lh "/root/web-abans-app-$DATA.tgz"

# ---------------------------------------------------------------------
pas "3. Còpia del codi (sense tocar config.php ni storage)"
rm -rf "$WEB/public" "$WEB/src"
cp -r "$ORIGEN/public" "$ORIGEN/src" "$WEB/"
cp "$ORIGEN/config/config.example.php" "$WEB/config/config.example.php"
[[ -f $ORIGEN/README.md ]] && cp "$ORIGEN/README.md" "$WEB/README.md"
mkdir -p "$WEB/storage"
cmd ls -la "$WEB"

# ---------------------------------------------------------------------
pas "4. Clau de xifratge app_key"
if php -r '$c = require "'"$WEB"'/config/config.php"; exit(isset($c["app_key"]) && strlen((string) base64_decode($c["app_key"], true)) === 32 ? 0 : 1);'; then
    echo "app_key ja existeix: no es modifica (canviar-la faria illegibles les dades xifrades)."
else
    cp "$WEB/config/config.php" "/root/config.php.$DATA.bak"
    php -r '
        $f = "'"$WEB"'/config/config.php";
        $c = require $f;
        $c["app_key"] = base64_encode(random_bytes(32));
        file_put_contents($f, "<?php\n// CSJ Parkings · configuració (fora de la carpeta pública)\nreturn " . var_export($c, true) . ";\n");
    '
    echo "app_key generada i afegida a config.php (còpia anterior a /root/config.php.$DATA.bak)."
fi
# Es mostren les claus de la configuració, mai els valors
php -r '$c = require "'"$WEB"'/config/config.php"; echo "Claus de config.php: ", implode(", ", array_keys($c)), " · db: ", implode(", ", array_keys($c["db"])), PHP_EOL;'

# ---------------------------------------------------------------------
pas "5. Permisos"
chown -R root:www-data "$WEB"
find "$WEB" -type d -exec chmod 750 {} +
find "$WEB" -type f -exec chmod 640 {} +
chown -R www-data:www-data "$WEB/storage"
chmod 770 "$WEB/storage"
cmd ls -la "$WEB" "$WEB/config"

# ---------------------------------------------------------------------
pas "6. Comprovacions"
errors=0
while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null || { echo "ERROR de sintaxi: $f"; errors=1; }
done < <(find "$WEB/public" "$WEB/src" -name '*.php' -print0)
echo "Fitxers PHP: $(find "$WEB/public" "$WEB/src" -name '*.php' | wc -l) · errors de sintaxi: $errors"
cmd apache2ctl configtest
systemctl reload apache2
for url in / /login /registre /condicions /reserves /admin; do
    printf '%-14s -> HTTP %s\n' "$url" "$(curl -sk -o /dev/null -w '%{http_code}' "https://localhost$url")"
done
echo
echo "Fet. Prova-ho a https://csjparkings.local/ (usuaris demo: admin@, operador@, client@csjparkings.cat)."
echo "Informe desat a $INFORME"

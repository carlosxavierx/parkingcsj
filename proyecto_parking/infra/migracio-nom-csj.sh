#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Migració del nom provisional "Tarraco Parkings" a "CSJ Parkings"
#  (només s'executa una vegada a parking-srv, abans de tornar a executar 03-lamp.sh)
#
#  - Esborra la BD de proves tarraco_parkings i els usuaris tp_* (es recreen com csj_*).
#  - Treu el VirtualHost i la carpeta web antics.
#  - Conserva les contrasenyes generades, canviant només el nom de les variables.
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo"; exit 1; }

echo "== Base de dades i usuaris antics"
mariadb -e "DROP DATABASE IF EXISTS tarraco_parkings;
            DROP USER IF EXISTS 'tp_app'@'localhost', 'tp_backup'@'localhost', 'tp_admin'@'localhost';"

echo "== Credencials (es conserven les contrasenyes)"
if [[ -f /root/tarraco-credencials.txt && ! -f /root/csj-credencials.txt ]]; then
    umask 077
    sed -e 's/^TP_/CSJ_/' -e 's/Tarraco Parkings/CSJ Parkings/' /root/tarraco-credencials.txt > /root/csj-credencials.txt
    chmod 600 /root/csj-credencials.txt
fi
rm -f /root/tarraco-credencials.txt

echo "== Apache"
a2dissite -q tarracoparkings >/dev/null 2>&1 || true
rm -f /etc/apache2/sites-available/tarracoparkings.conf
rm -rf /srv/www/tarracoparkings
rm -f /var/log/apache2/tarracoparkings-*.log

echo "== Fitxers de configuració amb el nom antic"
rm -f /etc/mysql/mariadb.conf.d/60-tarraco.cnf
rm -f /etc/systemd/system/apache2.service.d/10-tarraco.conf /etc/systemd/system/mariadb.service.d/10-tarraco.conf
systemctl daemon-reload

echo "Migració feta. Ara cal executar: sudo bash ~/infra/03-lamp.sh"

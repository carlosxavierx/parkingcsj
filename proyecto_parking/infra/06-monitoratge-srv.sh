#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 9: Monitoratge (part de parking-srv)
#
#  - node_exporter: mètriques del sistema (CPU, memòria, disc, xarxa, RAID) al port 9100
#  - mysqld_exporter: mètriques de MariaDB al port 9104, amb un usuari de BD propi
#    de privilegis mínims (csj_exporter)
#  El tallafocs (UFW) ja només permet aquests ports des de parking-mon (192.168.56.31).
#
#  Ús:  sudo bash ~/infra/06-monitoratge-srv.sh
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail
USUARI_REAL="${SUDO_USER:-carlos}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
INFORME="$HOME_REAL/informe-06-monitoratge-srv.txt"
CREDS=/root/csj-credencials.txt

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1
pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }
genpass() { openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 20; }
echo "Informe monitoratge (parking-srv) · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

pas "1. Instal·lació dels exporters"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q >/dev/null
apt-get install -y -q --no-install-recommends prometheus-node-exporter prometheus-mysqld-exporter >/dev/null
dpkg -l prometheus-node-exporter prometheus-mysqld-exporter | awk '/^ii/{print $2, $3}'

pas "2. Usuari de MariaDB per al monitoratge (csj_exporter)"
# shellcheck disable=SC1090
source "$CREDS"
if [[ -z ${CSJ_EXPORTER_PASS:-} ]]; then
    CSJ_EXPORTER_PASS=$(genpass)
    echo "CSJ_EXPORTER_PASS='$CSJ_EXPORTER_PASS'" >> "$CREDS"
fi
mariadb <<EOF
CREATE USER IF NOT EXISTS 'csj_exporter'@'localhost' IDENTIFIED BY '$CSJ_EXPORTER_PASS' WITH MAX_USER_CONNECTIONS 3;
ALTER USER 'csj_exporter'@'localhost' IDENTIFIED BY '$CSJ_EXPORTER_PASS' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SLAVE MONITOR ON *.* TO 'csj_exporter'@'localhost';
GRANT SELECT ON performance_schema.* TO 'csj_exporter'@'localhost';
FLUSH PRIVILEGES;
EOF
mariadb -N -e "SHOW GRANTS FOR 'csj_exporter'@'localhost';" | sed -E "s/PASSWORD '[^']+'/PASSWORD '***'/"

pas "3. Configuració de mysqld_exporter"
umask 077
cat > /etc/default/prometheus-mysqld-exporter <<EOF
# CSJ Parkings · mysqld_exporter (conté la contrasenya: permisos 600)
DATA_SOURCE_NAME="csj_exporter:$CSJ_EXPORTER_PASS@(127.0.0.1:3306)/"
ARGS=""
EOF
chmod 600 /etc/default/prometheus-mysqld-exporter
umask 022
for s in prometheus-node-exporter prometheus-mysqld-exporter; do
    mkdir -p /etc/systemd/system/$s.service.d
    printf '[Service]\nRestart=on-failure\nRestartSec=5s\n' > /etc/systemd/system/$s.service.d/10-csj.conf
done
systemctl daemon-reload
systemctl enable -q prometheus-node-exporter prometheus-mysqld-exporter
systemctl restart prometheus-node-exporter prometheus-mysqld-exporter
sleep 4

pas "4. Comprovacions"
for s in prometheus-node-exporter prometheus-mysqld-exporter; do echo "$s: $(systemctl is-active $s)"; done
echo
echo "node_exporter (9100) · RAID:"
curl -s localhost:9100/metrics | grep -E '^node_md_(disks|disks_required|state)\{' || true
echo
echo "mysqld_exporter (9104):"
curl -s localhost:9104/metrics | grep -E '^mysql_(up|global_status_threads_connected|global_status_uptime) ' || true
echo
cmd ss -tln
echo
echo "Regles del tallafocs per als exporters:"
ufw status | grep -E '9100|9104'

pas "FET"
chown "$USUARI_REAL": "$INFORME"

#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 9 i 6: Monitoratge i securització de parking-mon
#
#  - Prometheus (recull mètriques, regles d'alerta, retenció 15 dies)
#  - blackbox_exporter (comprova que la web respon per HTTPS amb certificat vàlid)
#  - node_exporter (mètriques del mateix parking-mon)
#  - Grafana amb la font de dades i el tauler "CSJ Parkings · Infraestructura" ja creats
#  - Securització: UFW, fail2ban, SSH només amb clau, actualitzacions automàtiques
#
#  Ús:  sudo bash ~/infra/06-monitoratge-mon.sh
#  Requereix: ~/infra/monitoratge/* i ~/csj-parkings-ca.crt (certificat de la CA)
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail
USUARI_REAL="${SUDO_USER:-carlos}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
CONF="$HOME_REAL/infra/monitoratge"
CA="$HOME_REAL/csj-parkings-ca.crt"
INFORME="$HOME_REAL/informe-06-monitoratge-mon.txt"
CREDS=/root/csj-credencials.txt
XARXA=192.168.56.0/24

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1
pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }
genpass() { openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 20; }
echo "Informe monitoratge i securització · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

pas "0. Comprovacions prèvies"
for f in prometheus.yml csj-alertes.yml blackbox.yml grafana-datasource.yaml grafana-dashboards.yaml grafana-csj-infraestructura.json; do
    [[ -f $CONF/$f ]] || { echo "ATURAT: falta $CONF/$f"; exit 1; }
done
[[ -f $CA ]] || { echo "ATURAT: falta $CA (certificat de la CA)"; exit 1; }
grep -qE '^(ssh-ed25519|ssh-rsa|ecdsa-)' "$HOME_REAL/.ssh/authorized_keys" 2>/dev/null \
    || { echo "ATURAT: $USUARI_REAL no té cap clau SSH (no es desactiva la contrasenya)"; exit 1; }
# Nom del domini a /etc/hosts (abans era el nom provisional)
sed -i 's/tarracoparkings\.local/csjparkings.local/g' /etc/hosts
grep -q 'csjparkings.local' /etc/hosts || echo "192.168.56.30 parking-srv csjparkings.local" >> /etc/hosts
grep csjparkings /etc/hosts
echo "OK"

pas "1. Prometheus, blackbox_exporter i node_exporter"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q >/dev/null
apt-get install -y -q --no-install-recommends prometheus prometheus-node-exporter prometheus-blackbox-exporter >/dev/null
install -m 644 "$CA" /etc/prometheus/csj-ca.crt
install -m 644 "$CONF/prometheus.yml"  /etc/prometheus/prometheus.yml
install -m 644 "$CONF/csj-alertes.yml" /etc/prometheus/csj-alertes.yml
install -m 644 "$CONF/blackbox.yml"    /etc/prometheus/blackbox.yml
echo 'ARGS="--storage.tsdb.retention.time=15d"' > /etc/default/prometheus
cmd promtool check config /etc/prometheus/prometheus.yml
for s in prometheus prometheus-node-exporter prometheus-blackbox-exporter; do
    mkdir -p /etc/systemd/system/$s.service.d
    printf '[Service]\nRestart=on-failure\nRestartSec=5s\n' > /etc/systemd/system/$s.service.d/10-csj.conf
done
systemctl daemon-reload
systemctl enable -q prometheus prometheus-node-exporter prometheus-blackbox-exporter
systemctl restart prometheus-blackbox-exporter prometheus-node-exporter prometheus
dpkg -l prometheus prometheus-node-exporter prometheus-blackbox-exporter | awk '/^ii/{print $2, $3}'

pas "2. Grafana"
if ! dpkg -s grafana >/dev/null 2>&1; then
    apt-get install -y -q apt-transport-https gnupg curl >/dev/null
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://apt.grafana.com/gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/grafana.gpg
    echo "deb [signed-by=/etc/apt/keyrings/grafana.gpg] https://apt.grafana.com stable main" > /etc/apt/sources.list.d/grafana.list
    apt-get update -q >/dev/null
    apt-get install -y -q grafana >/dev/null
fi
# shellcheck disable=SC1090
[[ -f $CREDS ]] && source "$CREDS"
if [[ -z ${GRAFANA_ADMIN_PASS:-} ]]; then
    GRAFANA_ADMIN_PASS=$(genpass)
    umask 077; echo "GRAFANA_ADMIN_PASS='$GRAFANA_ADMIN_PASS'" >> "$CREDS"; umask 022
    chmod 600 "$CREDS"
fi
cat > /etc/default/grafana-server <<EOF
# CSJ Parkings · Grafana
GRAFANA_USER=grafana
GRAFANA_GROUP=grafana
GRAFANA_HOME=/usr/share/grafana
LOG_DIR=/var/log/grafana
DATA_DIR=/var/lib/grafana
MAX_OPEN_FILES=10000
CONF_DIR=/etc/grafana
CONF_FILE=/etc/grafana/grafana.ini
RESTART_ON_UPGRADE=true
PLUGINS_DIR=/var/lib/grafana/plugins
PROVISIONING_CFG_DIR=/etc/grafana/provisioning
PID_FILE_DIR=/run/grafana
GF_SECURITY_ADMIN_PASSWORD=$GRAFANA_ADMIN_PASS
GF_USERS_ALLOW_SIGN_UP=false
GF_AUTH_ANONYMOUS_ENABLED=true
GF_AUTH_ANONYMOUS_ORG_ROLE=Viewer
GF_DASHBOARDS_DEFAULT_HOME_DASHBOARD_PATH=/var/lib/grafana/dashboards/csj-infraestructura.json
GF_ANALYTICS_REPORTING_ENABLED=false
GF_ANALYTICS_CHECK_FOR_UPDATES=false
GF_ANALYTICS_CHECK_FOR_PLUGIN_UPDATES=false
GF_NEWS_NEWS_FEED_ENABLED=false
EOF
chmod 640 /etc/default/grafana-server
chown root:grafana /etc/default/grafana-server
install -m 644 "$CONF/grafana-datasource.yaml" /etc/grafana/provisioning/datasources/csj.yaml
install -m 644 "$CONF/grafana-dashboards.yaml" /etc/grafana/provisioning/dashboards/csj.yaml
mkdir -p /var/lib/grafana/dashboards
install -m 644 -o grafana -g grafana "$CONF/grafana-csj-infraestructura.json" /var/lib/grafana/dashboards/csj-infraestructura.json
mkdir -p /etc/systemd/system/grafana-server.service.d
printf '[Service]\nRestart=on-failure\nRestartSec=5s\n' > /etc/systemd/system/grafana-server.service.d/10-csj.conf
systemctl daemon-reload
systemctl enable -q grafana-server
systemctl restart grafana-server
echo "Esperant que Grafana arrenqui..."
for i in $(seq 1 30); do curl -sf localhost:3000/api/health >/dev/null && break; sleep 3; done
curl -s localhost:3000/api/health; echo
# La contrasenya per variable d'entorn només s'aplica al primer arrencada: es força
grafana-cli --homepath /usr/share/grafana --config /etc/grafana/grafana.ini admin reset-admin-password "$GRAFANA_ADMIN_PASS" >/dev/null 2>&1 \
    || grafana cli --homepath /usr/share/grafana --config /etc/grafana/grafana.ini admin reset-admin-password "$GRAFANA_ADMIN_PASS" >/dev/null 2>&1 || true
dpkg -l grafana | awk '/^ii/{print $2, $3}'

pas "3. Securització de parking-mon"
apt-get install -y -q ufw fail2ban unattended-upgrades >/dev/null
# SSH només amb clau i sense root
cat > /etc/ssh/sshd_config.d/10-csj.conf <<'EOF'
# CSJ Parkings · securització SSH
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
EOF
sshd -t && systemctl reload ssh
sshd -T | grep -E '^(permitrootlogin|passwordauthentication|pubkeyauthentication|maxauthtries) '
# Tallafocs
ufw --force reset >/dev/null
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp comment 'SSH (administracio i copies)' >/dev/null
ufw allow from $XARXA to any port 3000 proto tcp comment 'Grafana (xarxa interna)' >/dev/null
ufw allow from $XARXA to any port 9090 proto tcp comment 'Prometheus (xarxa interna)' >/dev/null
ufw --force enable >/dev/null
cmd ufw status verbose
# fail2ban
cat > /etc/fail2ban/jail.local <<'EOF'
# CSJ Parkings · fail2ban
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
ignoreip = 127.0.0.1/8 ::1 192.168.56.30
banaction = ufw

[sshd]
enabled = true
EOF
systemctl enable -q fail2ban; systemctl restart fail2ban
printf 'APT::Periodic::Update-Package-Lists "1";\nAPT::Periodic::Unattended-Upgrade "1";\n' > /etc/apt/apt.conf.d/20auto-upgrades
sleep 3
cmd fail2ban-client status

pas "4. Comprovacions"
echo "Esperant les primeres lectures de Prometheus (40 s)..."
sleep 40
for s in prometheus prometheus-node-exporter prometheus-blackbox-exporter grafana-server fail2ban; do
    echo "$s: $(systemctl is-active $s)"
done
echo
echo "Objectius de Prometheus:"
curl -s localhost:9090/api/v1/targets | python3 -c '
import json,sys
for t in json.load(sys.stdin)["data"]["activeTargets"]:
    print("  %-10s %-28s %-5s %s" % (t["labels"]["job"], t["labels"].get("instance",""), t["health"], t.get("lastError","")))'
echo
echo "Prova HTTPS de la web (blackbox):"
curl -s "localhost:9115/probe?module=http_csj&target=https://csjparkings.local/" | grep -E '^probe_(success|http_status_code|ssl_earliest_cert_expiry|duration_seconds) '
echo
echo "Regles d'alerta carregades:"
curl -s localhost:9090/api/v1/rules | python3 -c '
import json,sys
for g in json.load(sys.stdin)["data"]["groups"]:
    for r in g["rules"]: print("  %-22s %s" % (r["name"], r["state"]))'
echo
echo "Tauler de Grafana:"
curl -s "localhost:3000/api/search?query=CSJ" | python3 -c '
import json,sys
for d in json.load(sys.stdin): print("  ", d.get("title"), "->", d.get("url"))'
cmd free -m

pas "FET"
echo "Grafana:    http://192.168.56.31:3000/   (lectura sense usuari · administració: usuari admin)"
echo "Prometheus: http://192.168.56.31:9090/   (alertes: /alerts)"
echo "Contrasenya d'admin de Grafana:  sudo grep GRAFANA $CREDS"
chown "$USUARI_REAL": "$INFORME"

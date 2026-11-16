#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 5-7: SSL (HTTPS) i securització
#  Servidor: parking-srv
#
#  - Crea una CA pròpia ("CSJ Parkings Root CA") i el certificat del servidor
#    per a csjparkings.local / www.csjparkings.local / 192.168.56.30.
#  - Apache: HTTPS (TLS 1.2/1.3), redirecció HTTP->HTTPS, capçaleres de seguretat,
#    sense versió ni signatura, phpMyAdmin només des de la xarxa interna.
#  - PHP: configuració de producció i cookies de sessió segures.
#  - Tallafocs UFW, fail2ban, SSH només amb clau i sense root,
#    actualitzacions automàtiques de seguretat.
#
#  Ús:  sudo bash ~/infra/04-ssl-seguretat.sh
#  Es pot tornar a executar (no regenera la CA ni el certificat si ja existeixen).
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail

USUARI_REAL="${SUDO_USER:-carlos}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
INFORME="$HOME_REAL/informe-04-ssl-seguretat.txt"
DOMINI=csjparkings.local
IP_SRV=192.168.56.30
IP_MON=192.168.56.31
XARXA=192.168.56.0/24
SSL_DIR=/etc/ssl/csjparkings
WEB=/srv/www/csjparkings
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo: sudo bash $0"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1

pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }

echo "Informe SSL i securització · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

# ---------------------------------------------------------------------
pas "0. Comprovacions prèvies"
[[ -d $WEB/public ]] || { echo "ATURAT: no existeix $WEB (cal executar abans 03-lamp.sh)"; exit 1; }
if ! grep -qE '^(ssh-ed25519|ssh-rsa|ecdsa-)' "$HOME_REAL/.ssh/authorized_keys" 2>/dev/null; then
    echo "ATURAT: $USUARI_REAL no té cap clau SSH a authorized_keys."
    echo "No es desactiva l'accés per contrasenya per no quedar-se sense accés."
    exit 1
fi
echo "OK: web instal·lada i clau SSH de $USUARI_REAL present."
# Nom antic a /etc/hosts
sed -i 's/tarracoparkings\.local/csjparkings.local/g' /etc/hosts
grep -q "$DOMINI" /etc/hosts || echo "$IP_SRV $DOMINI www.$DOMINI" >> /etc/hosts
grep "$DOMINI" /etc/hosts

# ---------------------------------------------------------------------
pas "1. Autoritat de certificació (CA) pròpia i certificat del servidor"
mkdir -p "$SSL_DIR"
chmod 755 "$SSL_DIR"
if [[ ! -f $SSL_DIR/ca.crt ]]; then
    openssl genrsa -out "$SSL_DIR/ca.key" 4096 2>/dev/null
    openssl req -x509 -new -key "$SSL_DIR/ca.key" -sha256 -days 3650 \
        -subj "/C=ES/ST=Tarragona/L=Tarragona/O=CSJ Parkings/CN=CSJ Parkings Root CA" \
        -out "$SSL_DIR/ca.crt"
    echo "CA creada."
else
    echo "La CA ja existia: no es regenera."
fi
if [[ ! -f $SSL_DIR/$DOMINI.crt ]]; then
    openssl genrsa -out "$SSL_DIR/$DOMINI.key" 2048 2>/dev/null
    openssl req -new -key "$SSL_DIR/$DOMINI.key" \
        -subj "/C=ES/ST=Tarragona/L=Tarragona/O=CSJ Parkings/CN=$DOMINI" \
        -out "$SSL_DIR/$DOMINI.csr"
    cat > "$SSL_DIR/$DOMINI.ext" <<EOF
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = DNS:$DOMINI, DNS:www.$DOMINI, IP:$IP_SRV
EOF
    openssl x509 -req -in "$SSL_DIR/$DOMINI.csr" -CA "$SSL_DIR/ca.crt" -CAkey "$SSL_DIR/ca.key" \
        -CAcreateserial -days 825 -sha256 -extfile "$SSL_DIR/$DOMINI.ext" -out "$SSL_DIR/$DOMINI.crt" 2>/dev/null
    echo "Certificat del servidor creat."
else
    echo "El certificat del servidor ja existia: no es regenera."
fi
chmod 600 "$SSL_DIR/ca.key" "$SSL_DIR/$DOMINI.key"
chmod 644 "$SSL_DIR/ca.crt" "$SSL_DIR/$DOMINI.crt"
openssl x509 -in "$SSL_DIR/$DOMINI.crt" -noout -subject -issuer -dates -ext subjectAltName
# Còpia del certificat de la CA per importar-lo a l'equip client
cp "$SSL_DIR/ca.crt" "$HOME_REAL/csj-parkings-ca.crt"
chown "$USUARI_REAL": "$HOME_REAL/csj-parkings-ca.crt"
cmd ls -l "$SSL_DIR"

# ---------------------------------------------------------------------
pas "2. Apache: HTTPS, redirecció i capçaleres de seguretat"
a2enmod -q ssl headers rewrite >/dev/null
cat > /etc/apache2/sites-available/csjparkings.conf <<EOF
# CSJ Parkings · VirtualHosts
# HTTP: només redirigeix a HTTPS
<VirtualHost *:80>
    ServerName  $DOMINI
    ServerAlias www.$DOMINI $IP_SRV
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

# HTTPS
<VirtualHost *:443>
    ServerName  $DOMINI
    ServerAlias www.$DOMINI $IP_SRV
    DocumentRoot $WEB/public

    SSLEngine on
    SSLCertificateFile    $SSL_DIR/$DOMINI.crt
    SSLCertificateKeyFile $SSL_DIR/$DOMINI.key
    SSLProtocol           -all +TLSv1.2 +TLSv1.3
    SSLHonorCipherOrder   off
    SSLSessionTickets     off

    Header always set Strict-Transport-Security "max-age=31536000"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(self), camera=(), microphone=()"

    <Directory $WEB/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  \${APACHE_LOG_DIR}/csjparkings-error.log
    CustomLog \${APACHE_LOG_DIR}/csjparkings-access.log combined
</VirtualHost>
EOF
# Sense versió d'Apache ni signatura; sense mètode TRACE
sed -i -E 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/; s/^TraceEnable .*/TraceEnable Off/' \
    /etc/apache2/conf-available/security.conf
# phpMyAdmin només des de la xarxa interna i des del mateix servidor
cat > /etc/apache2/conf-available/phpmyadmin-restriccio.conf <<EOF
# CSJ Parkings · phpMyAdmin només accessible des de la xarxa interna
<Location /phpmyadmin>
    Require ip $XARXA 127.0.0.1 ::1
</Location>
EOF
a2enconf -q phpmyadmin-restriccio security >/dev/null
a2ensite -q csjparkings >/dev/null
cmd apache2ctl configtest
systemctl reload apache2
cmd apache2ctl -S

# ---------------------------------------------------------------------
pas "3. PHP: configuració de producció"
cat > /etc/php/$PHP_VER/apache2/conf.d/99-csj.ini <<'EOF'
; CSJ Parkings · PHP en producció
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
EOF
systemctl reload apache2
cat /etc/php/$PHP_VER/apache2/conf.d/99-csj.ini

# ---------------------------------------------------------------------
pas "4. SSH: només amb clau i sense root"
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
cmd sshd -t
systemctl reload ssh
sshd -T | grep -E '^(permitrootlogin|passwordauthentication|kbdinteractiveauthentication|pubkeyauthentication|maxauthtries|x11forwarding) '

# ---------------------------------------------------------------------
pas "5. Tallafocs UFW"
export DEBIAN_FRONTEND=noninteractive
apt-get install -y -q ufw fail2ban unattended-upgrades >/dev/null
ufw --force reset >/dev/null
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp  comment 'SSH' >/dev/null
ufw allow 80/tcp  comment 'HTTP (redirigeix a HTTPS)' >/dev/null
ufw allow 443/tcp comment 'HTTPS' >/dev/null
ufw allow from $IP_MON to any port 9100 proto tcp comment 'node_exporter (monitoratge)' >/dev/null
ufw allow from $IP_MON to any port 9104 proto tcp comment 'mysqld_exporter (monitoratge)' >/dev/null
ufw --force enable >/dev/null
cmd ufw status verbose

# ---------------------------------------------------------------------
pas "6. fail2ban"
cat > /etc/fail2ban/jail.local <<'EOF'
# CSJ Parkings · fail2ban
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
ignoreip = 127.0.0.1/8 ::1
banaction = ufw

[sshd]
enabled = true

[apache-auth]
enabled = true
logpath = /var/log/apache2/*error.log
EOF
systemctl enable -q fail2ban
systemctl restart fail2ban
sleep 3
cmd fail2ban-client status

# ---------------------------------------------------------------------
pas "7. Actualitzacions automàtiques de seguretat"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
cmd systemctl is-enabled unattended-upgrades
cat /etc/apt/apt.conf.d/20auto-upgrades

# ---------------------------------------------------------------------
pas "8. Comprovacions"
echo "HTTP -> $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' http://$IP_SRV/)"
echo "HTTPS amb la CA pròpia (verificant el certificat):"
curl -s --cacert "$SSL_DIR/ca.crt" https://$DOMINI/ | grep -oE 'Base de dades: <strong[^>]*>[A-Z]+|Pàrquings \([0-9]+\)' || true
echo
echo "Capçaleres HTTPS:"
curl -sI --cacert "$SSL_DIR/ca.crt" https://$DOMINI/ | grep -iE '^(HTTP|server|strict-transport|x-content|x-frame|referrer|x-powered)' || true
echo "(No ha d'aparèixer X-Powered-By i Server ha de ser només 'Apache')"
echo
echo "Protocols TLS:"
for p in tls1_1 tls1_2 tls1_3; do
    if echo | openssl s_client -connect $IP_SRV:443 -$p >/dev/null 2>&1; then echo "  $p: acceptat"; else echo "  $p: rebutjat"; fi
done
echo
echo "phpMyAdmin per HTTPS des del servidor: HTTP $(curl -s -o /dev/null -w '%{http_code}' --cacert "$SSL_DIR/ca.crt" https://$DOMINI/phpmyadmin/)"

pas "FET"
echo "Web:        https://$DOMINI/   (o https://$IP_SRV/)"
echo "phpMyAdmin: https://$DOMINI/phpmyadmin/  (només des de $XARXA)"
echo "Certificat de la CA per importar a l'equip client: $HOME_REAL/csj-parkings-ca.crt"
echo "Informe desat a: $INFORME"
chown "$USUARI_REAL": "$INFORME"

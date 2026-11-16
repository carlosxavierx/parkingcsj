#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 3-4: Apache2 + PHP, MariaDB + phpMyAdmin
#  Servidor: parking-srv
#
#  - Instal·la Apache2, PHP 8.1 (mòduls necessaris) i MariaDB.
#  - Posa les dades de MariaDB al RAID (/srv/mysql, muntat amb bind a /var/lib/mysql).
#  - Activa els binary logs (necessaris per a les còpies incrementals).
#  - Crea la BD csj_parkings amb schema.sql + seed.sql.
#  - Crea els usuaris de BD csj_app, csj_backup i csj_admin (privilegis mínims).
#  - Instal·la phpMyAdmin i crea el VirtualHost de la web (HTTP; l'SSL és el pas següent).
#
#  Ús:  sudo bash ~/infra/03-lamp.sh      (cal haver copiat ~/infra i ~/database)
#  Es pot tornar a executar: no esborra res que ja existeixi excepte la BD de proves
#  (schema.sql la recrea), i reutilitza les contrasenyes ja generades.
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail

USUARI_REAL="${SUDO_USER:-root}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
DIR_BD="$HOME_REAL/database"
INFORME="$HOME_REAL/informe-03-lamp.txt"
CREDS=/root/csj-credencials.txt
WEB=/srv/www/csjparkings
DATADIR_RAID=/srv/mysql

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo: sudo bash $0"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1

pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }
genpass() { openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 20; }

echo "Informe Apache + PHP + MariaDB · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

# ---------------------------------------------------------------------
pas "0. Comprovacions prèvies"
mountpoint -q /srv || { echo "ATURAT: /srv (RAID) no està muntat."; exit 1; }
[[ -f $DIR_BD/schema.sql && -f $DIR_BD/seed.sql ]] || { echo "ATURAT: falten $DIR_BD/schema.sql o seed.sql"; exit 1; }
echo "OK: /srv muntat sobre el RAID i scripts SQL presents."

# ---------------------------------------------------------------------
pas "1. Instal·lació de paquets"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q >/dev/null
apt-get install -y -q apache2 mariadb-server \
    php libapache2-mod-php php-mysql php-mbstring php-xml php-intl php-gd php-curl php-zip \
    >/dev/null
cmd apache2 -v
cmd php -v
cmd mariadb --version

# ---------------------------------------------------------------------
pas "2. Dades de MariaDB al RAID ($DATADIR_RAID -> /var/lib/mysql)"
if findmnt -n /var/lib/mysql >/dev/null; then
    echo "Ja estava muntat:"; findmnt /var/lib/mysql
else
    systemctl stop mariadb
    if [[ ! -d $DATADIR_RAID ]]; then
        mkdir -p "$DATADIR_RAID"
        cp -a /var/lib/mysql/. "$DATADIR_RAID"/
        mv /var/lib/mysql /var/lib/mysql.original
        mkdir /var/lib/mysql
    fi
    chown mysql:mysql "$DATADIR_RAID" /var/lib/mysql
    chmod 700 "$DATADIR_RAID"
    if ! grep -q "^$DATADIR_RAID " /etc/fstab; then
        echo "$DATADIR_RAID /var/lib/mysql none bind,nofail,x-systemd.requires-mounts-for=/srv 0 0" >> /etc/fstab
    fi
    systemctl daemon-reload
    mount /var/lib/mysql
    findmnt /var/lib/mysql
fi
# MariaDB no ha d'arrencar si el RAID no està muntat
mkdir -p /etc/systemd/system/mariadb.service.d
cat > /etc/systemd/system/mariadb.service.d/10-csj.conf <<'EOF'
[Unit]
RequiresMountsFor=/var/lib/mysql
[Service]
Restart=on-failure
RestartSec=5s
EOF
systemctl daemon-reload

# ---------------------------------------------------------------------
pas "3. Configuració de MariaDB (binary logs, utf8mb4, només local)"
cat > /etc/mysql/mariadb.conf.d/60-csj.cnf <<'EOF'
# CSJ Parkings
[mysqld]
bind-address          = 127.0.0.1
character-set-server  = utf8mb4
collation-server      = utf8mb4_unicode_ci
# Binary logs: necessaris per a les còpies incrementals
server_id             = 1
log_bin               = mysql-bin
binlog_format         = ROW
expire_logs_days      = 14
max_binlog_size       = 100M
EOF
systemctl restart mariadb
cmd systemctl is-active mariadb
mariadb -e "SHOW VARIABLES WHERE Variable_name IN ('datadir','log_bin','bind_address','character_set_server');"

# ---------------------------------------------------------------------
pas "4. Securització bàsica de MariaDB"
mariadb <<'EOF'
DELETE FROM mysql.global_priv WHERE User = '';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db = 'test' OR Db = 'test\\_%';
FLUSH PRIVILEGES;
EOF
echo "Usuaris anònims i BD de prova eliminats. root només entra amb unix_socket (sudo)."

# ---------------------------------------------------------------------
pas "5. Base de dades csj_parkings (schema.sql + seed.sql)"
mariadb < "$DIR_BD/schema.sql"
mariadb csj_parkings < "$DIR_BD/seed.sql"
mariadb csj_parkings -e "
SELECT 'parquings' AS taula, COUNT(*) AS files FROM parquings UNION ALL
SELECT 'places', COUNT(*) FROM places UNION ALL
SELECT 'tarifes', COUNT(*) FROM tarifes UNION ALL
SELECT 'usuaris', COUNT(*) FROM usuaris UNION ALL
SELECT 'reserves', COUNT(*) FROM reserves;"

# ---------------------------------------------------------------------
pas "6. Usuaris de la base de dades (privilegis mínims)"
if [[ -f $CREDS ]]; then
    # shellcheck disable=SC1090
    source "$CREDS"
    echo "Es reutilitzen les contrasenyes de $CREDS"
else
    CSJ_APP_PASS=$(genpass); CSJ_BACKUP_PASS=$(genpass); CSJ_ADMIN_PASS=$(genpass); PMA_PASS=$(genpass)
    umask 077
    cat > "$CREDS" <<EOF
# CSJ Parkings · credencials de la base de dades (NO compartir)
CSJ_APP_PASS='$CSJ_APP_PASS'
CSJ_BACKUP_PASS='$CSJ_BACKUP_PASS'
CSJ_ADMIN_PASS='$CSJ_ADMIN_PASS'
PMA_PASS='$PMA_PASS'
EOF
    chmod 600 "$CREDS"
fi
mariadb <<EOF
CREATE USER IF NOT EXISTS 'csj_app'@'localhost'    IDENTIFIED BY '$CSJ_APP_PASS';
CREATE USER IF NOT EXISTS 'csj_backup'@'localhost' IDENTIFIED BY '$CSJ_BACKUP_PASS';
CREATE USER IF NOT EXISTS 'csj_admin'@'localhost'  IDENTIFIED BY '$CSJ_ADMIN_PASS';
ALTER USER 'csj_app'@'localhost'    IDENTIFIED BY '$CSJ_APP_PASS';
ALTER USER 'csj_backup'@'localhost' IDENTIFIED BY '$CSJ_BACKUP_PASS';
ALTER USER 'csj_admin'@'localhost'  IDENTIFIED BY '$CSJ_ADMIN_PASS';
-- Aplicació: sense DELETE (les reserves mai s'esborren)
GRANT SELECT, INSERT, UPDATE ON csj_parkings.* TO 'csj_app'@'localhost';
-- Còpies de seguretat
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON csj_parkings.* TO 'csj_backup'@'localhost';
GRANT RELOAD, PROCESS, REPLICATION CLIENT ON *.* TO 'csj_backup'@'localhost';
-- Administració via phpMyAdmin (només aquesta BD)
GRANT ALL PRIVILEGES ON csj_parkings.* TO 'csj_admin'@'localhost';
FLUSH PRIVILEGES;
EOF
for u in csj_app csj_backup csj_admin; do mariadb -N -e "SHOW GRANTS FOR '$u'@'localhost';"; done

# ---------------------------------------------------------------------
pas "7. phpMyAdmin"
if ! dpkg -s phpmyadmin >/dev/null 2>&1; then
    debconf-set-selections <<EOF
phpmyadmin phpmyadmin/dbconfig-install boolean true
phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2
phpmyadmin phpmyadmin/mysql/app-pass password $PMA_PASS
phpmyadmin phpmyadmin/app-password-confirm password $PMA_PASS
EOF
    apt-get install -y -q phpmyadmin >/dev/null
fi
cmd dpkg -s phpmyadmin | grep -E '^(Package|Version)'

# ---------------------------------------------------------------------
pas "8. Web: carpetes, configuració i pàgina de prova"
mkdir -p "$WEB/public" "$WEB/config" "$WEB/storage"
# /srv/www ha de ser travessable per Apache (www-data)
chown root:www-data /srv/www
chmod 750 /srv/www
cat > "$WEB/config/config.php" <<EOF
<?php
// CSJ Parkings · configuració (fora de la carpeta pública)
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'csj_parkings',
        'user'    => 'csj_app',
        'pass'    => '$CSJ_APP_PASS',
        'charset' => 'utf8mb4',
    ],
];
EOF
if [[ ! -f $WEB/public/index.php ]]; then
cat > "$WEB/public/index.php" <<'EOF'
<?php
// Pàgina provisional: comprova que Apache + PHP + MariaDB funcionen.
$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$estat = 'ERROR';
$parquings = [];
try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
                   $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $parquings = $pdo->query('SELECT nom, adreca, places_reservables FROM parquings ORDER BY nom')->fetchAll();
    $estat = 'OK';
} catch (PDOException $e) {
    error_log($e->getMessage());
}
$e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ca">
<head><meta charset="utf-8"><title>CSJ Parkings · en construcció</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#222}
h1{color:#0b5394}.ok{color:#2e7d32}.ko{color:#c62828}table{border-collapse:collapse;width:100%}
td,th{border-bottom:1px solid #ddd;padding:6px;text-align:left}</style></head>
<body>
<h1>CSJ Parkings</h1>
<p>Servidor <strong><?= $e(gethostname()) ?></strong> · PHP <?= $e(PHP_VERSION) ?> ·
Base de dades: <strong class="<?= $estat === 'OK' ? 'ok' : 'ko' ?>"><?= $e($estat) ?></strong></p>
<h2>Pàrquings (<?= count($parquings) ?>)</h2>
<table><tr><th>Nom</th><th>Adreça</th><th>Places reservables</th></tr>
<?php foreach ($parquings as $p): ?>
<tr><td><?= $e($p['nom']) ?></td><td><?= $e($p['adreca']) ?></td><td><?= $e($p['places_reservables']) ?></td></tr>
<?php endforeach; ?>
</table>
</body></html>
EOF
fi
# Permisos provisionals (es revisen a l'apartat de permisos)
chown -R root:www-data "$WEB"
find "$WEB" -type d -exec chmod 750 {} +
find "$WEB" -type f -exec chmod 640 {} +
chown -R www-data:www-data "$WEB/storage"
cmd ls -la /srv/www "$WEB" "$WEB/config"

# ---------------------------------------------------------------------
pas "9. Apache: VirtualHost csjparkings.local"
if [[ ! -f /etc/apache2/sites-available/csjparkings.conf ]]; then  # no trepitjar la versió HTTPS (04-ssl-seguretat.sh)
cat > /etc/apache2/sites-available/csjparkings.conf <<'EOF'
# CSJ Parkings · VirtualHost HTTP (l'HTTPS es configura al pas de l'SSL)
<VirtualHost *:80>
    ServerName  csjparkings.local
    ServerAlias www.csjparkings.local 192.168.56.30
    DocumentRoot /srv/www/csjparkings/public

    <Directory /srv/www/csjparkings/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/csjparkings-error.log
    CustomLog ${APACHE_LOG_DIR}/csjparkings-access.log combined
</VirtualHost>
EOF
fi
echo "ServerName parking-srv" > /etc/apache2/conf-available/servername.conf
a2enconf -q servername >/dev/null
a2enmod -q rewrite headers >/dev/null
a2dissite -q 000-default >/dev/null || true
a2ensite -q csjparkings >/dev/null
mkdir -p /etc/systemd/system/apache2.service.d
cat > /etc/systemd/system/apache2.service.d/10-csj.conf <<'EOF'
[Unit]
RequiresMountsFor=/srv
[Service]
Restart=on-failure
RestartSec=5s
EOF
systemctl daemon-reload
cmd apache2ctl configtest
systemctl reload apache2 || systemctl restart apache2
cmd apache2ctl -S

# ---------------------------------------------------------------------
pas "10. Comprovació final"
for s in apache2 mariadb; do echo "$s: $(systemctl is-active $s)"; done
echo
echo "Pàgina web (http://localhost/):"
curl -s http://localhost/ | grep -oE 'Base de dades: <strong[^>]*>[A-Z]+|Pàrquings \([0-9]+\)' || true
echo
echo "phpMyAdmin (http://localhost/phpmyadmin/): HTTP $(curl -s -o /dev/null -w '%{http_code}' http://localhost/phpmyadmin/)"

pas "FET"
echo "Web:        http://192.168.56.30/"
echo "phpMyAdmin: http://192.168.56.30/phpmyadmin/  (usuari: csj_admin)"
echo "La contrasenya de csj_admin es pot consultar amb:  sudo grep CSJ_ADMIN $CREDS"
echo "Informe desat a: $INFORME"
chown "$USUARI_REAL": "$INFORME"

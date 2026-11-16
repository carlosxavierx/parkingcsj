#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 8: Còpies de seguretat (parking-srv)
#
#  sudo bash ~/infra/05-copies-srv.sh          -> instal·la les còpies programades
#  sudo bash ~/infra/05-copies-srv.sh prova    -> fa una còpia completa + incremental,
#                                                 les envia a parking-mon i prova la restauració
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail

MODE="${1:-installar}"
USUARI_REAL="${SUDO_USER:-carlos}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
BD=csj_parkings
DIR=/var/backups/csjparkings
MON=192.168.56.31
CLAU=/root/.ssh/id_backup_ed25519
INFORME="$HOME_REAL/informe-05-copies-${MODE}.txt"

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1
pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }
echo "Informe còpies de seguretat ($MODE) · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

if [[ $MODE == installar ]]; then
# ---------------------------------------------------------------------
pas "1. Credencials de l'usuari de còpies (csj_backup)"
# shellcheck disable=SC1091
source /root/csj-credencials.txt
umask 077
cat > /root/.my-backup.cnf <<EOF
[client]
user=csj_backup
password=$CSJ_BACKUP_PASS
EOF
chmod 600 /root/.my-backup.cnf
ls -l /root/.my-backup.cnf
mariadb --defaults-extra-file=/root/.my-backup.cnf -e "SELECT CURRENT_USER() AS usuari; SHOW MASTER STATUS;"

# ---------------------------------------------------------------------
pas "2. Script de còpies /usr/local/sbin/csj-backup"
install -m 750 -o root -g root "$HOME_REAL/infra/csj-backup.sh" /usr/local/sbin/csj-backup
mkdir -p "$DIR"; chmod 700 "$DIR"
touch /var/log/csj-backup.log; chmod 640 /var/log/csj-backup.log
ls -l /usr/local/sbin/csj-backup

# ---------------------------------------------------------------------
pas "3. Clau SSH dedicada per enviar les còpies a parking-mon"
mkdir -p /root/.ssh; chmod 700 /root/.ssh
[[ -f $CLAU ]] || ssh-keygen -q -t ed25519 -N "" -C "backup@parking-srv" -f "$CLAU"
ssh-keyscan -t ed25519 $MON 2>/dev/null > /tmp/mon.key
grep -q "$MON" /root/.ssh/known_hosts 2>/dev/null || cat /tmp/mon.key >> /root/.ssh/known_hosts
cp "$CLAU.pub" "$HOME_REAL/backup_srv.pub"
chown "$USUARI_REAL": "$HOME_REAL/backup_srv.pub"
cat "$CLAU.pub"

# ---------------------------------------------------------------------
pas "4. Programació (cron)"
cat > /etc/cron.d/csj-backup <<'EOF'
# CSJ Parkings · còpies de seguretat
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# Còpia completa: diumenge a les 02:00
0  2 * * 0  root  /usr/local/sbin/csj-backup full
# Còpia incremental: cada dia a les 02:30
30 2 * * *  root  /usr/local/sbin/csj-backup incr
EOF
chmod 644 /etc/cron.d/csj-backup
cat /etc/cron.d/csj-backup

pas "FET"
echo "Ara cal preparar parking-mon (05-copies-mon.sh) i després executar: sudo bash ~/infra/05-copies-srv.sh prova"

else
# =====================================================================
pas "1. Còpia completa"
cmd /usr/local/sbin/csj-backup full
FULL=$(ls -t $DIR/full/*.sql.gz | head -1)
echo "Posició del binary log on comença la còpia:"
zcat "$FULL" | grep -m1 "CHANGE MASTER TO"

pas "2. Canvi a la base de dades després de la còpia completa"
MARCA="Prova de còpia incremental $(date '+%H:%M:%S')"
ID_RES=$(mariadb -N -e "SELECT MIN(id_reserva) FROM $BD.reserves")
mariadb $BD -e "INSERT INTO historial_reserves (id_reserva, accio, detall) VALUES ($ID_RES, 'modificacio', '$MARCA');"
echo "Inserit a historial_reserves: '$MARCA'"

pas "3. Còpia incremental"
cmd /usr/local/sbin/csj-backup incr
cmd ls -l $DIR/full $DIR/incr $DIR/fitxers

pas "4. Còpies a parking-mon"
ssh -i $CLAU -o BatchMode=yes backup@$MON "ls -lR /srv/backups/parking-srv | head -40"

pas "5. Prova de restauració (en una BD temporal csj_restauracio)"
mariadb -e "DROP DATABASE IF EXISTS csj_restauracio; CREATE DATABASE csj_restauracio;"
echo "a) Es restaura la còpia completa: $(basename "$FULL")"
zcat "$FULL" | mariadb csj_restauracio
TROBAT_FULL=$(mariadb -N -e "SELECT COUNT(*) FROM csj_restauracio.historial_reserves WHERE detall = '$MARCA'")
echo "   Canvi present només amb la completa: $TROBAT_FULL (ha de ser 0)"

POS_FITXER=$(zcat "$FULL" | grep -m1 "CHANGE MASTER TO" | sed -E "s/.*MASTER_LOG_FILE='([^']+)'.*/\1/")
POS_POS=$(zcat "$FULL" | grep -m1 "CHANGE MASTER TO" | sed -E "s/.*MASTER_LOG_POS=([0-9]+).*/\1/")
echo "b) S'apliquen les incrementals des de $POS_FITXER posició $POS_POS"
LOGS=()
for f in $(ls $DIR/incr/mysql-bin.*.gz | sort); do
    nom=$(basename "$f" .gz)
    [[ $nom < $POS_FITXER ]] && continue
    zcat "$f" > "/tmp/$nom"; LOGS+=("/tmp/$nom")
done
echo "   Binary logs aplicats: ${LOGS[*]##*/}"
if mysqlbinlog --help | grep -q -- '--rewrite-db'; then
    mysqlbinlog --start-position="$POS_POS" --rewrite-db="$BD->csj_restauracio" "${LOGS[@]}" \
        | mariadb csj_restauracio
    TROBAT_INCR=$(mariadb -N -e "SELECT COUNT(*) FROM csj_restauracio.historial_reserves WHERE detall = '$MARCA'")
    echo "   Canvi present després d'aplicar les incrementals: $TROBAT_INCR (ha de ser 1)"
else
    echo "   (mysqlbinlog sense --rewrite-db: es comprova el contingut del binary log)"
    mysqlbinlog --base64-output=decode-rows -v --start-position="$POS_POS" "${LOGS[@]}" | grep -c "$MARCA" || true
fi
echo
echo "Comparació de files (original / restaurada):"
for t in parquings places tarifes usuaris reserves historial_reserves; do
    printf "  %-20s %s / %s\n" "$t" "$(mariadb -N -e "SELECT COUNT(*) FROM $BD.$t")" "$(mariadb -N -e "SELECT COUNT(*) FROM csj_restauracio.$t")"
done

pas "6. Neteja de la prova"
mariadb -e "DROP DATABASE csj_restauracio;"
mariadb $BD -e "DELETE FROM historial_reserves WHERE detall = '$MARCA';"
rm -f /tmp/mysql-bin.*
echo "BD temporal esborrada i registre de prova eliminat."

pas "7. Registre de les còpies"
tail -n 20 /var/log/csj-backup.log
pas "FET"
fi
chown "$USUARI_REAL": "$INFORME"

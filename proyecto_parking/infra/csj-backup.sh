#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Còpies de seguretat de la base de dades i de la web
#  S'instal·la a /usr/local/sbin/csj-backup i l'executa cron com a root.
#
#  csj-backup full  -> còpia COMPLETA: mysqldump de csj_parkings (+ fitxers web i config)
#  csj-backup incr  -> còpia INCREMENTAL: binary logs tancats des de l'última còpia
#
#  Després de cada còpia s'envia tot a parking-mon (rsync per SSH amb clau dedicada).
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail

TIPUS="${1:-}"
BD=csj_parkings
DIR=/var/backups/csjparkings
CNF=/root/.my-backup.cnf
CLAU=/root/.ssh/id_backup_ed25519
DESTI=backup@192.168.56.31:/srv/backups/parking-srv/
LOG=/var/log/csj-backup.log
RETENCIO_FULL=28      # dies
RETENCIO_INCR=35      # dies
ARA=$(date '+%Y%m%d-%H%M%S')

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') [$TIPUS] $*" | tee -a "$LOG"; }
trap 'log "ERROR a la línia $LINENO"' ERR

[[ $TIPUS == full || $TIPUS == incr ]] || { echo "Ús: $0 full|incr"; exit 1; }
mkdir -p "$DIR/full" "$DIR/incr" "$DIR/fitxers"
chmod 700 "$DIR"

if [[ $TIPUS == full ]]; then
    FITXER="$DIR/full/${BD}_full_${ARA}.sql.gz"
    log "Inici còpia completa -> $FITXER"
    # --single-transaction: còpia consistent sense bloquejar l'aplicació (InnoDB)
    # --flush-logs + --master-data=2: comença un binary log nou i anota la posició
    #   (punt de partida de les còpies incrementals següents)
    mysqldump --defaults-extra-file="$CNF" --single-transaction --flush-logs --master-data=2 \
              --routines --triggers --events "$BD" | gzip -9 > "$FITXER"
    gzip -t "$FITXER"
    sha256sum "$FITXER" > "$FITXER.sha256"
    log "Còpia completa feta: $(du -h "$FITXER" | cut -f1)"

    TAR="$DIR/fitxers/web-config_${ARA}.tar.gz"
    tar -czf "$TAR" -C / srv/www/csjparkings etc/apache2 etc/mysql etc/ssl/csjparkings \
        etc/php etc/ssh/sshd_config.d etc/ufw etc/fail2ban/jail.local || [[ $? -eq 1 ]]
    log "Còpia de fitxers web i configuració: $(du -h "$TAR" | cut -f1)"

    find "$DIR/full" "$DIR/fitxers" -type f -mtime +$RETENCIO_FULL -delete
else
    log "Inici còpia incremental"
    # Tanca el binary log actual i en comença un de nou
    mysqladmin --defaults-extra-file="$CNF" flush-logs
    ACTUAL=$(mariadb --defaults-extra-file="$CNF" -N -e "SHOW MASTER STATUS" | cut -f1)
    N=0
    for f in /var/lib/mysql/mysql-bin.[0-9]*; do
        nom=$(basename "$f")
        [[ $nom == "$ACTUAL" ]] && continue          # el log obert no es copia
        if [[ ! -f $DIR/incr/$nom.gz ]]; then
            gzip -c "$f" > "$DIR/incr/$nom.gz"
            N=$((N + 1))
        fi
    done
    log "Còpia incremental feta: $N binary logs nous (log actual: $ACTUAL)"
    find "$DIR/incr" -type f -mtime +$RETENCIO_INCR -delete
fi

# Enviament a parking-mon (sense --delete: si el servidor fos atacat, no es poden
# esborrar les còpies remotes; la retenció la fa parking-mon)
rsync -a -e "ssh -i $CLAU -o BatchMode=yes -o ConnectTimeout=10" "$DIR/" "$DESTI"
log "Còpies sincronitzades amb parking-mon"

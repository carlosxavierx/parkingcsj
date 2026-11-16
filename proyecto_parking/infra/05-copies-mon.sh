#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 8: Còpies de seguretat (parking-mon, destí)
#
#  - Prepara l'usuari "backup" (sense contrasenya) que només rep còpies de parking-srv.
#  - La seva clau SSH només s'accepta des de 192.168.56.30 i amb "restrict"
#    (sense terminal ni reenviaments).
#  - Retenció: esborra còpies de més de 35 dies.
#
#  Ús:  sudo bash ~/infra/05-copies-mon.sh      (cal ~/backup_srv.pub copiat de parking-srv)
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail
USUARI_REAL="${SUDO_USER:-carlos}"
HOME_REAL="$(getent passwd "$USUARI_REAL" | cut -d: -f6)"
PUB="$HOME_REAL/backup_srv.pub"
DESTI=/srv/backups/parking-srv
SRV=192.168.56.30
INFORME="$HOME_REAL/informe-05-copies-mon.txt"

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo"; exit 1; }
[[ -f $PUB ]] || { echo "ATURAT: falta $PUB"; exit 1; }
: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1
pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
echo "Informe còpies (destí) · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

pas "1. Usuari backup i carpeta de destí"
# L'usuari de sistema "backup" ja existeix a Ubuntu: se li assigna carpeta i una shell mínima
usermod -d /srv/backups -s /bin/sh backup
passwd -l backup >/dev/null
mkdir -p "$DESTI" /srv/backups/.ssh
chown -R backup:backup /srv/backups
chmod 700 /srv/backups "$DESTI" /srv/backups/.ssh
DEBIAN_FRONTEND=noninteractive apt-get install -y -q rsync >/dev/null
getent passwd backup
ls -ld /srv/backups "$DESTI"

pas "2. Clau autoritzada (només des de parking-srv)"
echo "restrict,from=\"$SRV\" $(cat "$PUB")" > /srv/backups/.ssh/authorized_keys
chown backup:backup /srv/backups/.ssh/authorized_keys
chmod 600 /srv/backups/.ssh/authorized_keys
cut -c1-90 /srv/backups/.ssh/authorized_keys

pas "3. Retenció (35 dies)"
echo "# CSJ Parkings · esborra còpies de més de 35 dies" > /etc/cron.d/csj-backup-retencio
echo "0 5 * * *  root  find $DESTI -type f -mtime +35 -delete" >> /etc/cron.d/csj-backup-retencio
chmod 644 /etc/cron.d/csj-backup-retencio
cat /etc/cron.d/csj-backup-retencio

pas "FET"
echo "parking-mon preparat per rebre còpies a $DESTI"
chown "$USUARI_REAL": "$INFORME"

#!/usr/bin/env bash
# =====================================================================
#  CSJ Parkings · Sprint 2 · Pas 2: RAID 1 amb mdadm (parking-srv)
#
#  Crea un RAID 1 amb /dev/sdb i /dev/sdc, el formata en ext4, el munta
#  a /srv de forma permanent i fa una prova de fallada i reconstrucció.
#  Tota la sortida es desa a ~/raid1-informe.txt com a evidència.
#
#  Ús (des de parking-srv):   sudo bash 02-raid1.sh
#  Script elaborat amb l'ajuda de Claude (IA d'Anthropic).
# =====================================================================
set -euo pipefail

DISC1=/dev/sdb
DISC2=/dev/sdc
RAID=/dev/md0
PUNT=/srv
USUARI_REAL="${SUDO_USER:-root}"
INFORME="/home/${USUARI_REAL}/raid1-informe.txt"

[[ $EUID -eq 0 ]] || { echo "Cal executar-lo amb sudo: sudo bash $0"; exit 1; }

: > "$INFORME"
exec > >(tee -a "$INFORME") 2>&1

pas() { echo; echo "=================================================================="; echo "## $*"; echo "=================================================================="; }
cmd() { echo; echo "\$ $*"; "$@"; }

echo "Informe RAID 1 · $(hostname) · $(date '+%d/%m/%Y %H:%M:%S')"

# ---------------------------------------------------------------------
pas "1. Comprovacions prèvies"
cmd lsblk
if [[ -e $RAID ]]; then
    echo "ATURAT: $RAID ja existeix. El RAID ja estava creat."; exit 1
fi
for d in $DISC1 $DISC2; do
    [[ -b $d ]] || { echo "ATURAT: no existeix el disc $d"; exit 1; }
    if [[ $(lsblk -n -o NAME "$d" | wc -l) -gt 1 ]] || blkid "$d" >/dev/null 2>&1; then
        echo "ATURAT: $d no està buit (té particions o un sistema de fitxers)."; exit 1
    fi
    if findmnt -n -S "$d" >/dev/null; then
        echo "ATURAT: $d està muntat."; exit 1
    fi
done
echo "OK: $DISC1 i $DISC2 existeixen i estan buits."

# ---------------------------------------------------------------------
pas "2. Instal·lació de mdadm"
DEBIAN_FRONTEND=noninteractive apt-get install -y mdadm >/dev/null
cmd mdadm --version

# ---------------------------------------------------------------------
pas "3. Creació del RAID 1"
cmd mdadm --create "$RAID" --level=1 --raid-devices=2 --metadata=1.2 --run "$DISC1" "$DISC2"
cmd cat /proc/mdstat
echo; echo "Esperant que acabi la sincronització inicial..."
mdadm --wait "$RAID" || true
cmd cat /proc/mdstat

# ---------------------------------------------------------------------
pas "4. Sistema de fitxers i muntatge a $PUNT"
cmd mkfs.ext4 -F -L raid-dades "$RAID"
mkdir -p "$PUNT"
cmd mount "$RAID" "$PUNT"
UUID=$(blkid -s UUID -o value "$RAID")
if ! grep -q "$UUID" /etc/fstab; then
    echo "UUID=$UUID $PUNT ext4 defaults,nofail 0 2" >> /etc/fstab
fi
echo; echo "Línia afegida a /etc/fstab:"; grep "$UUID" /etc/fstab

# ---------------------------------------------------------------------
pas "5. Configuració persistent (mdadm.conf + initramfs)"
sed -i '/^ARRAY \/dev\/md0/d' /etc/mdadm/mdadm.conf
mdadm --detail --scan | grep "$RAID" >> /etc/mdadm/mdadm.conf
echo "Línia afegida a /etc/mdadm/mdadm.conf:"; grep "^ARRAY $RAID" /etc/mdadm/mdadm.conf
update-initramfs -u >/dev/null 2>&1
echo "initramfs actualitzat."

# ---------------------------------------------------------------------
pas "6. Estat del RAID"
cmd mdadm --detail "$RAID"
cmd df -h "$PUNT"

# ---------------------------------------------------------------------
pas "7. Prova de fallada d'un disc"
echo "Prova RAID CSJ Parkings - $(date '+%d/%m/%Y %H:%M:%S')" > "$PUNT/prova.txt"
cmd cat "$PUNT/prova.txt"
cmd mdadm "$RAID" --fail "$DISC2"
sleep 2
cmd cat /proc/mdstat
echo; echo "Amb un disc fallit, el fitxer encara es pot llegir:"
cmd cat "$PUNT/prova.txt"
cmd mdadm --detail "$RAID"

# ---------------------------------------------------------------------
pas "8. Substitució del disc i reconstrucció"
cmd mdadm "$RAID" --remove "$DISC2"
cmd mdadm "$RAID" --add "$DISC2"
sleep 2
cmd cat /proc/mdstat
echo; echo "Esperant que acabi la reconstrucció..."
mdadm --wait "$RAID" || true
cmd cat /proc/mdstat
cmd mdadm --detail "$RAID"

pas "FET"
echo "RAID 1 creat, muntat a $PUNT i provat. Informe: $INFORME"
echo "Següent pas: sudo reboot i comprovar que $PUNT es munta sol."
chown "$USUARI_REAL": "$INFORME"

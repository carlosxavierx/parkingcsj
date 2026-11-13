# Tarraco Parkings — context del projecte (per a Claude Code i l'equip)

Projecte ASIX2 (mòdul 0379, Vidal i Barraquer): sistema de reserves de places de pàrquing per a Tarragona.
Equip: **Carlos, Sara, Joel**. Professor: avalua amb rúbriques del Moodle.
Enunciat i rúbriques en PDF a l'arrel (no es pugen al repo): `Enunciat del projecteParkings.pdf`, `SPRINT1.pdf`, `SPRINT2.pdf`.
Idioma de la documentació: **català**. L'equip parla amb Claude en castellà/català.

## Decisions preses (no re-discutir)
- **Stack obligatori de l'enunciat:** Apache2 + **PHP** + **MariaDB** (MySQL/MariaDB), phpMyAdmin, SSL, Zabbix, sobre Ubuntu Server.
- `app/` (React + Firebase + Geoapify) és només un **prototip visual**: no compleix l'enunciat i per això el Sprint 1 va treure 73,2.
  Se'n reaprofita el disseny (CSS), les pantalles i l'algorisme de preus. El producte final es fa en PHP.
- Feedback del professor al Sprint 1: millorar la BD, fer-ho en PHP + MySQL com demana l'enunciat, i la presentació.
- **Infraestructura:** VirtualBox als PCs de l'equip (l'institut no dona servidor ni Proxmox; PC de l'institut 16 GB RAM i sense permisos d'admin).
  3 VM: `srv-web` (Apache+PHP+SSL+RAID1 mdadm), `srv-db` (MariaDB+phpMyAdmin+còpies), `srv-monitor` (Zabbix). Opcional `srv-web2` + HAProxy.
  Xarxa en mode pont (IP per al professor). Compartir VM amb `.ova`.
- **Rols de l'equip:** Carlos = cap de projecte + BD + backend PHP. Sara = frontend/UX + servidor web + Zabbix + manual.
  Joel = tasques de suport senzilles i **fora del camí crític** (dades dels pàrquings, bibliografia, proves com a usuari, captures, portada).
  Indicació del professor: a Joel tasques simples que no interfereixin en el funcionament, però **no** tota la documentació.
- Gestió de tasques: **GitHub Projects** (pendent de crear el tauler amb `gh`; tasques a `proyecto_parking/sprint1/04-planificacion.md`).
- Declaració d'IA: l'equip la farà curta però ha de ser veraç (l'enunciat demana eines, parts i prompts).

## Estat (13/11/2026)
- **Sprint 1 v2 fet** a `proyecto_parking/sprint1/`: esquema funcional, model E/R v2 (16 taules, triggers, vistes), SQL, planificació, guió.
  Diapositives del Sprint 1 ja fetes per l'equip.
- `06-diagramas.html` es genera amb `python3 proyecto_parking/sprint1/generar-diagramas.py` (no editar a mà).
- ⚠️ `03-modelo-er.sql` **encara no s'ha executat** en un MariaDB real. Provar-ho primer:
  `sudo mariadb < proyecto_parking/sprint1/03-modelo-er.sql` i que `DELETE FROM reserves ...` falli.
- **Sprint 2** (entrega ~21/11/2026): infraestructura + app PHP funcional amb rols + manual d'usuari + documentació tècnica + presentació + enllaç/IP.

## Regles de negoci clau
- Preu: hores iniciades; blocs de 24 h des de l'entrada; hora que comença 22–06 → 0,60; 1a i 2a hora diürna del bloc 1,90; 3a+ 0,20;
  màxim 4,00 per bloc; +10 % gestió (arrodonit a 2 decimals). Excés = preu(entrada reservada→sortida real) − preu reservat, +10 %, amb el mateix mètode de pagament, cortesia 10 min.
  Exemples verificats: 09–10 → 2,09 · 16–18 → 4,18 · 08–11 → 4,40 · 22:30–00:30 → 1,32 · 48 h → 8,80 · excés 09–11:20 sobre reserva 09–10 → 2,31.
- Les reserves **mai s'eliminen** (estat `cancelada` amb motiu). Estats: pendent, confirmada, en_curs, completada, cancelada, no_presentat.
- Rols: client, operador (només els seus pàrquings), admin.
- Seguretat: bcrypt, DNI xifrat AES + hash, targetes tokenitzades (4 dígits), PDO, CSRF, HTTPS, UFW, usuari MySQL de la web sense DELETE.

## Usuaris de demo (seed)
client@demo.cat / Client1234! · client2@demo.cat / Client1234! · operador@demo.cat / Operador1234! · admin@demo.cat / Admin1234!

## Propers passos (acordats el 13/11, per fer a l'ordinador de casa)
1. Provar `03-modelo-er.sql` a MariaDB i corregir errors si n'hi ha.
2. `gh auth login` i crear el tauler de GitHub Projects amb les tasques de `04-planificacion.md`.
3. Revisar la branca `sprint1-v2` i fusionar-la a `main`.
4. Sprint 2: esquelet de l'app PHP (MVC, PDO, config fora del DocumentRoot, login amb rols, classe de preu amb tests).
5. Sprint 2: scripts d'instal·lació de les VM (srv-web, srv-db, còpies amb cron) + base de la documentació tècnica.
6. Plantilles per a Joel: checklist de proves i bibliografia.

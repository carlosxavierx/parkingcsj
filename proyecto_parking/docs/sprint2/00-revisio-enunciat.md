# Sprint 2 — Revisió de l'enunciat i matriu de compliment

## Sistema de Reserves de Places de Pàrquing · CSJ Parkings (Tarragona)

**Data de revisió:** 15/11/2026
**Lliurament del Sprint 2:** dissabte 21/11/2026
**Fonts:** *Enunciat del projecte 1 – Parkings* i *SPRINT 2 (Implementació)* (Moodle ASIX2_0379_PA).

Aquest document contrasta el que demana l'enunciat amb el que tenim fet o planificat, i
recull les **correccions** que cal aplicar al disseny del Sprint 1 abans d'implementar.

Llegenda: ✅ fet · 🔧 en curs / planificat al Sprint 2 · ⏳ fase posterior · ⚠️ cal corregir

---

## 1. Infraestructura i seguretat

| Requisit de l'enunciat | Com ho resolem | Estat |
|---|---|---|
| Servidor GNU/Linux | Ubuntu Server 22.04 LTS a VirtualBox (`parking-srv`) | 🔧 |
| Servidor web Apache2 + PHP | Apache 2.4 + PHP 8.1 (`libapache2-mod-php`, `php-mysql`, `php-mbstring`…) | 🔧 |
| MySQL/MariaDB + gestor | MariaDB 10.6 + **phpMyAdmin** (només accessible per HTTPS i des de la xarxa interna) | 🔧 |
| Certificat SSL | CA pròpia + certificat per a `csjparkings.local` (Let's Encrypt no és possible en una xarxa privada sense domini públic) i redirecció HTTP→HTTPS | 🔧 |
| RAID | **RAID 1** amb `mdadm` sobre 2 discos de 5 GB, muntat a `/srv` (web i dades de MariaDB) | 🔧 |
| Còpies de seguretat **completes i incrementals** de la BD, programades | Completa: `mysqldump` setmanal · Incremental: *binary logs* de MariaDB diaris · `cron` + còpia a `parking-mon` per `rsync`/SSH amb clau · rotació i prova de restauració | 🔧 |
| Monitoratge (en un servidor a part, recomanat) | Servidor `parking-mon`: **Prometheus + Grafana** + `node_exporter`, `mysqld_exporter` i `blackbox_exporter` (comprova HTTPS) a `parking-srv` | 🔧 |
| Securització: tallafocs i polítiques | **UFW** (només 22, 80, 443 i ports d'exporters des de `parking-mon`), **fail2ban**, SSH sense root i amb clau, Apache sense signatura/llistat de directoris, capçaleres de seguretat | 🔧 |
| Permisos de fitxers i usuaris | Usuari i grup propis per a la web, `www-data` només lectura al codi, fitxer de configuració amb credencials `640`, usuari MariaDB de l'aplicació amb privilegis mínims | 🔧 |
| Anonimització/encriptació de dades sensibles | Contrasenyes amb `password_hash()` (bcrypt) · telèfon i dades sensibles xifrats amb `openssl_encrypt` (AES-256) · mètodes de pagament sense guardar mai el número complet (només els 4 últims dígits) · HTTPS | 🔧 |
| Alta disponibilitat | RAID 1 + reinici automàtic de serveis (`systemd Restart=`) + monitoratge amb alertes + còpies verificades | 🔧 |
| Redundància i balanceig de càrrega (opcional) | Només possible a l'ordinador de l'institut (16 GB): segon servidor web + HAProxy | ⏳ opcional |

## 2. Aplicació (ha de funcionar)

| Requisit | Com ho resolem | Estat |
|---|---|---|
| Registre obligatori per fer qualsevol acció | Sessions PHP segures; totes les pàgines privades protegides | 🔧 |
| Pàgina d'inici amb informació dels pàrquings i formulari de reserva | Llistat de pàrquings reals de Tarragona amb places lliures i formulari | 🔧 |
| Perfil amb dades personals (i modificació) | Pàgina de perfil | 🔧 |
| Vehicles (marca, model, matrícula…) | CRUD de vehicles | 🔧 |
| **Mètodes de pagament** de l'usuari | CRUD de mètodes de pagament (simulats) · taula `metodes_pagament` | 🔧 |
| Reserva: pàrquing, data, hora entrada/sortida, **preu automàtic +10 %** | Càlcul en JS (previsualització) i en PHP (valor definitiu) | 🔧 |
| Acceptació de condicions abans de confirmar | Checkbox obligatori validat al servidor | 🔧 |
| Número de reserva + **PDF** per accedir al pàrquing | Número `CSJ-AAAAMMDD-XXXX` + PDF amb FPDF (amb la matrícula i un codi) | 🔧 |
| Gestió d'administradors: pàrquings i places | Panel admin | 🔧 |
| Veure i modificar totes les reserves, canviar l'estat, **no eliminar** (només cancel·lar) | Panel admin | 🔧 |
| Cerques i visualitzacions avançades | Filtres per client, matrícula, pàrquing, data i estat | 🔧 |
| **Diversos rols d'usuari** (Sprint 2) | Client · Operador de pàrquing · Administrador (`usuaris.rol`) | 🔧 |
| Lector de matrícules: entrada/sortida, reserva activa o places lliures, obrir barrera | Pantalla de simulació (rol operador) | 🔧 si hi ha temps · si no ⏳ |
| Excés de temps: calcular i cobrar amb el mateix mètode de pagament | Registre a `accessos` + nou `pagament` de tipus `excés` | ⏳ |
| Integració amb diversos pàrquings "de manera real" (rúbrica) | Dades reals dels pàrquings de Tarragona (nom, adreça, places, coordenades) | 🔧 |

## 3. Lliurables del Sprint 2

| Lliurable | On | Estat |
|---|---|---|
| Planificació **actualitzada** (Gantt + repartiment) | `03-planificacio-actualitzada.md` | 🔧 |
| Manual d'usuari (del que hi ha fet) | `05-manual-usuari.md` | ⏳ dj–dv |
| Documentació tècnica (tecnologies + codi): portada, índex, captures amb descripció, bibliografia | `01-…`, `02-…` i la doc. de l'aplicació | 🔧 |
| Model E/R actualitzat | `04-model-er-actualitzat.md` + `database/schema.sql` i `seed.sql` | ✅ (pendent de provar al servidor) |
| Codi comentat i estructurat (PHP, HTML, CSS, JS) | `proyecto_parking/src`, `public`, `database` | 🔧 |
| Enllaç o IP per provar l'aplicació | `https://csjparkings.local` (192.168.56.30) des de l'equip on corren les VMs | 🔧 |
| Presentació + document de presentació | — | ⏳ dv–ds |

---

## 4. Correccions al disseny del Sprint 1 (⚠️)

> ✅ **Aplicades** al model E/R actualitzat (`04-model-er-actualitzat.md`, `database/schema.sql`), on hi ha també altres millores (DATETIME per a reserves nocturnes, auditoria, xifrat, bloqueig per intents fallits).

1. **Rols d'usuari.** L'E/R només té `clients.es_admin` (sí/no). El Sprint 2 demana *"administradors i altres rols"*.
   → Substituir per `rol ENUM('client','operador','admin')` (o taula `rols`).
2. **Mètodes de pagament.** L'objectiu 2.4 diu que l'usuari ha de *tenir* mètodes de pagament, però només existeix `pagaments.metode`.
   → Nova taula `metodes_pagament` (id, id_client, tipus, titular, `ultims_digits`, caducitat, per defecte) i `reserves.id_metode_pagament`.
3. **Pagament 1:1 amb la reserva.** El cobrament de l'excés de temps genera un **segon** pagament de la mateixa reserva.
   → Relació reserva–pagaments **1:N** amb camp `concepte ENUM('reserva','exces')`.
4. **Estats de la reserva.** L'enunciat parla de reserva *activa*.
   → Mantenim `pendent`, `confirmada` (= activa), `cancel·lada`, `completada`; ho expliquem al document.
5. **"Eliminar una reserva" (rúbrica) vs "no es poden eliminar" (enunciat).**
   → Mana l'enunciat: el botó *Eliminar/Cancel·lar* només canvia l'estat a `cancel·lada` (esborrat lògic). Cal explicar-ho a la presentació.
6. **Planificació.** Està pensada per a 4 membres i 10 setmanes amb PHP.
   → Actualitzada a 3 membres i al calendari real (veure `03-planificacio-actualitzada.md`).
7. **Carpeta `app/` (React + Firebase).** No compleix l'stack de l'enunciat (Apache2 + PHP + MariaDB).
   → Es manté com a prototip de disseny i es reaprofiten les dades reals dels pàrquings i l'aspecte visual; l'aplicació que es lliura és la PHP.

# Sprint 2 — Revisió de l'enunciat i matriu de compliment

## Sistema de Reserves de Places de Pàrquing · CSJ Parkings (Tarragona)

**Data de revisió:** 15/11/2026 · **Actualitzada:** 18/11/2026
**Lliurament del Sprint 2:** dissabte 21/11/2026
**Fonts:** *Enunciat del projecte 1 – Parkings* i *SPRINT 2 (Implementació)* (Moodle ASIX2_0379_PA).

Aquest document contrasta el que demana l'enunciat amb el que tenim fet, i recull les **correccions**
aplicades al disseny del Sprint 1. La columna *On es veu* indica el document on hi ha l'evidència.

**Resum (18/11/2026):** tots els requisits obligatoris de la infraestructura i de l'aplicació estan **fets i provats**,
inclòs el simulador del lector de matrícules amb el cobrament de l'excés. Només queda l'opcional de balanceig de càrrega
i la presentació.

Llegenda: ✅ fet · 🔧 en curs / planificat al Sprint 2 · ⏳ fase posterior · ⚠️ cal corregir

---

## 1. Infraestructura i seguretat

| Requisit de l'enunciat | Com ho resolem | Estat | On es veu |
|---|---|---|---|
| Servidor GNU/Linux | Ubuntu Server 22.04 LTS a VirtualBox (`parking-srv`) | ✅ | `01`, `02` §1 |
| Servidor web Apache2 + PHP | Apache 2.4 + PHP 8.1 (`libapache2-mod-php`, `php-mysql`, `php-mbstring`…) | ✅ | `02` §3 |
| MySQL/MariaDB + gestor | MariaDB 10.6 + **phpMyAdmin** (només accessible per HTTPS i des de la xarxa interna) | ✅ | `02` §4 |
| Certificat SSL | CA pròpia + certificat per a `csjparkings.local` (Let's Encrypt no és possible en una xarxa privada sense domini públic) i redirecció HTTP→HTTPS | ✅ | `02` §5 |
| RAID | **RAID 1** amb `mdadm` sobre 2 discos de 5 GB, muntat a `/srv` (web i dades de MariaDB) | ✅ | `02` §2 |
| Còpies de seguretat **completes i incrementals** de la BD, programades | Completa: `mysqldump` setmanal · Incremental: *binary logs* de MariaDB diaris · `cron` + còpia a `parking-mon` per `rsync`/SSH amb clau · rotació i prova de restauració | ✅ | `02` §8 |
| Monitoratge (en un servidor a part, recomanat) | Servidor `parking-mon`: **Prometheus + Grafana** + `node_exporter`, `mysqld_exporter` i `blackbox_exporter` (comprova HTTPS) a `parking-srv` | ✅ | `02` §9 |
| Securització: tallafocs i polítiques | **UFW** (només 22, 80, 443 i ports d'exporters 9100/9104 des de `parking-mon`), **fail2ban**, SSH sense root i només amb clau, Apache sense signatura ni llistat de directoris, capçaleres de seguretat (HSTS, `X-Frame-Options`…) | ✅ | `02` §6 |
| Permisos de fitxers i usuaris | Usuari i grup propis per a la web, `www-data` només lectura al codi, fitxer de configuració amb credencials `640`, usuari MariaDB de l'aplicació amb privilegis mínims | ✅ | `02` §7 |
| Anonimització/encriptació de dades sensibles | Contrasenyes amb `password_hash()` (bcrypt) · telèfon, DNI i referències de PayPal/Bizum xifrats amb `openssl_encrypt` (AES-256-GCM, comprovat a la BD) · mètodes de pagament sense guardar mai el número complet (només els 4 últims dígits) · HTTPS | ✅ | `02` §6–7, `06` §5 |
| Alta disponibilitat | RAID 1 + reinici automàtic de serveis (`systemd Restart=`) + monitoratge amb alertes + còpies verificades | ✅ | `02` §2 |
| Redundància i balanceig de càrrega (opcional) | Només possible a l'ordinador de l'institut (16 GB): segon servidor web + HAProxy | ⏳ opcional | `01` §5 |

## 2. Aplicació (ha de funcionar)

| Requisit | Com ho resolem | Estat | On es veu |
|---|---|---|---|
| Registre obligatori per fer qualsevol acció | Sessions PHP segures (`Auth::requireLogin`); totes les pàgines privades protegides | ✅ | `05` §3, `06` §4 |
| Pàgina d'inici amb informació dels pàrquings i formulari de reserva | 14 pàrquings reals de Tarragona amb foto, serveis, places lliures, mapa i reserva ràpida | ✅ | `05` §2 |
| Perfil amb dades personals (i modificació) | Pàgina de perfil editable i canvi de contrasenya | ✅ | `05` §4.1 |
| Vehicles (marca, model, matrícula…) | Alta, modificació i baixa lògica de vehicles | ✅ | `05` §4.2 |
| **Mètodes de pagament** de l'usuari | Targeta (validació de Luhn, només 4 últims dígits), PayPal i Bizum, simulats | ✅ | `05` §4.3, `06` §5.6 |
| Reserva: pàrquing, data, hora entrada/sortida, **preu automàtic +10 %** | Càlcul en JS (vista prèvia) i en PHP (valor definitiu) amb les tarifes de la BD | ✅ | `05` §5, `06` §6.3 |
| Acceptació de condicions abans de confirmar | Casella obligatòria validada al servidor (prova 4) | ✅ | `06` §9 |
| Número de reserva + **PDF** per accedir al pàrquing | Número `CSJ-AAAAMMDD-XXXXXX` + PDF amb FPDF i codi de barres | ✅ | `05` §6.2, `06` §6.8 |
| Gestió d'administradors: pàrquings i places | Alta i edició de pàrquings, places (afegir, tipus, fora de servei) i tarifes | ✅ | `05` §9.4–9.5 |
| Veure i modificar totes les reserves, canviar l'estat, **no eliminar** (només cancel·lar) | Fitxa de reserva amb canvi d'estat, modificació i historial; l'usuari de BD no té `DELETE` | ✅ | `05` §9.3, `06` §6.4 |
| Cerques i visualitzacions avançades | Filtres per número, client, email, matrícula, pàrquing, estat i dates · tauler amb ocupació | ✅ | `05` §9.1–9.2 |
| **Diversos rols d'usuari** (Sprint 2) | Client · Operador de pàrquing · Administrador (`usuaris.rol`), gestió de rols des del panel | ✅ | `05` §1, `06` §4 |
| Lector de matrícules: entrada/sortida, reserva activa o places lliures, obrir barrera | Simulador al panel de l'operador; registre a `accessos` | ✅ | `05` §8, `06` §6.9 |
| Excés de temps: calcular i cobrar amb el mateix mètode de pagament | Nou `pagament` amb `concepte = 'exces'` (prova 16) | ✅ | `06` §6.9 |
| Integració amb diversos pàrquings "de manera real" (rúbrica) | Dades reals dels pàrquings de Tarragona (adreça, coordenades, serveis, fotos) | ✅ | `04` §7, `05` §2 |

## 3. Lliurables del Sprint 2

| Lliurable | On | Estat |
|---|---|---|
| Planificació **actualitzada** (Gantt + repartiment + seguiment) | `03-planificacio-actualitzada.md` | ✅ |
| Manual d'usuari (del que hi ha fet) | `05-manual-usuari.md` (14 captures) | ✅ |
| Documentació tècnica (tecnologies + codi): portada, índex, captures amb descripció, bibliografia | `portada-i-index.md`, `01`, `02`, `06`, `07-bibliografia.md` | ✅ |
| Model E/R actualitzat | `04-model-er-actualitzat.md` + `database/schema.sql` i `seed.sql` (provats al servidor) | ✅ |
| Codi comentat i estructurat (PHP, HTML, CSS, JS) | `proyecto_parking/app_php/` | ✅ |
| Enllaç o IP per provar l'aplicació | `https://csjparkings.local` · `https://192.168.56.30` (des de l'equip on corren les VM o, a la mateixa xarxa, per la IP de l'equip amfitrió) | ✅ |
| Presentació (PDF) + document de presentació | — | 🔧 dj–dv |

---

## 4. Correccions al disseny del Sprint 1 (⚠️)

> ✅ **Aplicades** al model E/R actualitzat (`04-model-er-actualitzat.md`, `database/schema.sql`), on hi ha també altres millores (DATETIME per a reserves nocturnes, auditoria, xifrat, bloqueig per intents fallits).

1. **Rols d'usuari.** L'E/R només té `clients.es_admin` (sí/no). El Sprint 2 demana *"administradors i altres rols"*.
   → Substituir per `rol ENUM('client','operador','admin')` (o taula `rols`).
2. **Mètodes de pagament.** L'objectiu 2.4 diu que l'usuari ha de *tenir* mètodes de pagament, però només existeix `pagaments.metode`.
   → Nova taula `metodes_pagament` (id, id_client, tipus, titular, `ultims_digits`, caducitat, per defecte) i `reserves.id_metode_pagament`.
3. **Pagament 1:1 amb la reserva.** El cobrament de l'excés de temps genera un **segon** pagament de la mateixa reserva.
   → Relació reserva–pagaments **1:N** amb camp `concepte ENUM('reserva','exces','reemborsament')`.
4. **Estats de la reserva.** L'enunciat parla de reserva *activa*.
   → Estats `pendent`, `confirmada` (= activa), `en_curs` (vehicle dins), `completada` i `cancellada`.
5. **"Eliminar una reserva" (rúbrica) vs "no es poden eliminar" (enunciat).**
   → Mana l'enunciat: el botó *Eliminar/Cancel·lar* només canvia l'estat a `cancel·lada` (esborrat lògic). Cal explicar-ho a la presentació.
6. **Planificació.** Està pensada per a 4 membres i 10 setmanes amb PHP.
   → Actualitzada a 3 membres i al calendari real (veure `03-planificacio-actualitzada.md`).
7. **Carpeta `app/` (React + Firebase).** No compleix l'stack de l'enunciat (Apache2 + PHP + MariaDB).
   → Es manté com a prototip de disseny i es reaprofiten les dades reals dels pàrquings i l'aspecte visual; l'aplicació que es lliura és la PHP.

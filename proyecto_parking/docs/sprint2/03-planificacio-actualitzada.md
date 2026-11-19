# Sprint 2 — Planificació actualitzada

## Sistema de Reserves de Places de Pàrquing · CSJ Parkings (Tarragona)

**Data d'actualització:** 18/11/2026
**Lliurament del Sprint 2:** dissabte 21/11/2026
**Equip:** 3 membres — Carlos Xavier, Sara Pulido i Joel Zurita.

---

## 1. Canvis respecte a la planificació del Sprint 1

| Sprint 1 (previst) | Realitat / canvi | Motiu |
|---|---|---|
| Equip de 4 membres | Equip de **3 membres** | Composició real del grup |
| 10 setmanes, S2–S9 seqüencials | El **Sprint 2 de l'enunciat** agrupa infraestructura, BD, backend, frontend i documentació en una sola setmana | Calendari de lliuraments del curs |
| Stack PHP + MariaDB | Es manté | Requisit de l'enunciat |
| Prototip React + Firebase (`app/`) | Només referència de disseny i dades; retirat del repositori en acabar el Sprint 2 | No compleix l'stack demanat |
| `clients.es_admin` | Rols `client`, `operador`, `admin` | El Sprint 2 demana diversos rols |
| Pagament 1:1 | Mètodes de pagament de l'usuari i pagaments 1:N | Objectius 2.4 i cobrament d'excés |

Equivalència entre els sprints interns del Sprint 1 i el Sprint 2 de l'enunciat:

| Sprint intern | Contingut | Lliurament |
|---|---|---|
| S1 | Anàlisi i disseny | ✅ Sprint 1 |
| S2 + S3 | Infraestructura i base de dades | Sprint 2 |
| S4 + S5 + S7 | Backend, frontend i panel d'administració (rols) | Sprint 2 |
| S6 | Control d'accés per matrícula (simulador) | Sprint 2 si hi ha temps / Fase 2 |
| S8 | Seguretat i proves | Sprint 2 (bàsic) / Fase 2 (complet) |
| S9 | Documentació i exposició | Sprint 2 (parcial) / Final |

---

## 2. Repartiment de rols

| Membre | Rol principal | Responsabilitats |
|---|---|---|
| **Carlos Xavier** | Coordinació i infraestructura | Màquines virtuals, RAID, Apache + PHP, MariaDB, SSL, seguretat, còpies, monitoratge, integració i desplegament de l'aplicació, documentació tècnica de la instal·lació |
| **Sara Pulido** | Aplicació i base de dades | E/R actualitzat, script SQL, aplicació PHP (registre, perfil, vehicles, pagaments, reserves, PDF, panel admin), documentació tècnica del codi |
| **Joel Zurita** | Dades, proves i suport documental | Recollida de dades reals dels pàrquings de Tarragona, proves funcionals amb la llista de comprovació, captures i redacció de parts del manual d'usuari, bibliografia, disseny de les diapositives |
| **Tots** | — | Manual d'usuari, presentació i assaig, revisió final |


---

## 3. Tasques del Sprint 2

| # | Tasca | Responsable | Previst | Real | Estat |
|---|---|---|---|---|---|
| T1 | Revisió de l'enunciat i matriu de compliment | Carlos | dg 15 | dg 15 (revisada dc 18) | ✅ |
| T2 | Creació de les VM `parking-srv` i `parking-mon` | Carlos | dg 15 | dg 15 | ✅ |
| T3 | RAID 1, Apache + PHP, MariaDB + phpMyAdmin | Carlos | dl 16 | dl 16 | ✅ |
| T4 | SSL, tallafocs, fail2ban, permisos | Carlos | dl 16 – dt 17 | dl 16 – dt 17 | ✅ |
| T5 | Còpies completes/incrementals i monitoratge | Carlos | dt 17 – dc 18 | dt 17 | ✅ |
| T6 | E/R actualitzat i prova de l'script SQL al servidor | Sara | dl 16 | dl 16 | ✅ |
| T7 | Dades reals dels pàrquings (nom, adreça, places, coordenades, fotos) | Joel | dl 16 – dt 17 | dl 16 – dc 18 | ✅ |
| T8 | App: estructura MVC, connexió PDO, registre/login, rols | Sara + Carlos | dl 16 – dt 17 | dt 17 | ✅ |
| T9 | App: perfil, vehicles i mètodes de pagament | Sara | dt 17 – dc 18 | dt 17 | ✅ |
| T10 | App: inici, reserva, càlcul del preu, condicions, número i PDF | Sara + Carlos | dc 18 – dj 19 | dt 17 – dc 18 | ✅ |
| T11 | App: panel admin (pàrquings, places, tarifes, reserves, cerques, usuaris) i rol operador | Sara + Carlos | dj 19 – dv 20 | dt 17 | ✅ |
| T12 | Simulador del lector de matrícules (entrada, sortida, cobrament de l'excés) | Carlos | dv 20 | dt 17 – dc 18 | ✅ |
| T13 | Proves funcionals (18 proves) i registre d'errors | Joel | dj 19 – dv 20 | dc 18 | ✅ |
| T14 | Manual d'usuari amb captures | Joel + Sara | dj 19 – dv 20 | dc 18 | ✅ |
| T15 | Documentació tècnica (instal·lació + codi) | Carlos + Sara | dl 16 – dv 20 | dl 16 – dc 18 | ✅ |
| T16 | Portada, índex i bibliografia | Joel | durant la setmana | dc 18 | ✅ |
| T17 | Presentació (PDF), document de presentació i assaig | Tots | dv 20 – ds 21 | dj 19 – dv 20 | 🔧 |
| T18 | Lliurament a Moodle | Carlos | ds 21 | — | ⏳ |

Llegenda: ✅ fet · 🔧 en curs · ⏳ pendent.

**Desviació respecte al previst:** l'aplicació i el simulador del lector es van acabar **dos dies abans** del previst
(dt 17 – dc 18 en lloc de dj 19 – dv 20). Això ha permès avançar les proves, el manual i la documentació al dc 18 i
deixar dj 19 i dv 20 per a la presentació i l'assaig.

---

## 4. Diagrama de Gantt (Sprint 2)

Diagrama amb les dates **reals** (les tasques acabades surten marcades com a fetes):

```mermaid
gantt
    title Sprint 2 — CSJ Parkings (15–21 nov 2026) · dates reals
    dateFormat  YYYY-MM-DD
    axisFormat  %a %d

    section Infraestructura (Carlos)
    Revisió enunciat + VM                    :done, t1, 2026-11-15, 1d
    RAID + Apache/PHP + MariaDB              :done, t3, 2026-11-16, 1d
    SSL + seguretat + permisos               :done, t4, 2026-11-16, 2d
    Còpies + monitoratge                     :done, t5, 2026-11-17, 1d

    section Base de dades (Sara)
    E/R actualitzat + script SQL             :done, t6, 2026-11-16, 1d

    section Aplicació PHP (Sara + Carlos)
    Estructura + login + rols                :done, t8, 2026-11-17, 1d
    Perfil, vehicles, pagaments              :done, t9, 2026-11-17, 1d
    Reserva + preu + PDF                     :done, t10, 2026-11-17, 2d
    Panel admin + operador                   :done, t11, 2026-11-17, 1d
    Simulador matrícules                     :done, t12, 2026-11-17, 2d

    section Dades, proves i documentació (Joel)
    Dades reals dels pàrquings               :done, t7, 2026-11-16, 3d
    Proves funcionals (18)                   :done, t13, 2026-11-18, 1d
    Manual d'usuari amb captures             :done, t14, 2026-11-18, 1d
    Portada, índex i bibliografia            :done, t16, 2026-11-18, 1d

    section Documentació tècnica (Carlos + Sara)
    Instal·lació + codi                      :done, t15, 2026-11-16, 3d

    section Presentació (tots)
    Presentació PDF + assaig                 :active, t17, 2026-11-19, 2d
    Lliurament                               :milestone, t18, 2026-11-21, 0d
```

---

## 5. Prioritats del Sprint 2 (MoSCoW)

| Prioritat | Contingut |
|---|---|
| **Must** (✅ tot fet) | Servidor Apache + PHP + MariaDB + phpMyAdmin amb SSL · RAID · tallafocs · còpies programades · monitoratge en servidor a part · registre/login · rols (client, operador, admin) · perfil · vehicles · mètodes de pagament · reserva amb preu automàtic + 10 % · condicions · número de reserva + PDF · panel admin (pàrquings, places, reserves, cancel·lació, cerques) · manual · doc. tècnica · presentació |
| **Should** | Simulador del lector de matrícules ✅ · xifrat de dades sensibles ✅ · prova de fallada del RAID i de restauració ✅ · alertes de monitoratge ✅ |
| **Could** | Cobrament automàtic de l'excés de temps ✅ · gràfics d'ocupació (barres al tauler d'administració) ✅ · segon servidor web + HAProxy (equip de l'institut) ⏳ |
| **Won't (Sprint 2)** | Passarel·la de pagament real · enviament real de correus |

---

## 6. Seguiment

| Data | Fet | Pendent / incidències |
|---|---|---|
| dg 15/11 | Revisió de l'enunciat, arquitectura, planificació actualitzada, E/R actualitzat i scripts SQL · VM `parking-srv` i `parking-mon` creades i accessibles per SSH | Incidència: l'instal·lador es bloqueja amb 1 GB de RAM (resolta amb 2 GB temporals) |
| dl 16/11 | Pas 1 (actualització, zona horària, claus SSH) i RAID 1 creat, provat (fallada + reconstrucció) i persistent després de reiniciar · Apache + PHP 8.1, MariaDB (dades al RAID, binlogs), BD creada amb els scripts, usuaris de BD i phpMyAdmin | Incidència 403 per permisos de /srv/www (resolta) · Canvi de nom a CSJ Parkings · SSL amb CA pròpia, UFW, fail2ban, SSH només amb clau, PHP i Apache securitzats |
| dt 17/11 | Còpies de seguretat completes i incrementals programades, enviades a parking-mon i restauració provada · Monitoratge (Prometheus + Grafana, 7 alertes) i securització de parking-mon | Rellotge de les VM endarrerit en restaurar l'estat desat (resolt sincronitzant l'hora) |
| dt 17/11 (tarda) | Aplicació PHP (MVC): registre/login, rols, perfil, vehicles, pagaments, reserva amb preu i PDF, panel d'administració, operador i simulador del lector · Script de desplegament `07-desplegar-app.sh` | Incidència: Apache convertia l'error CSRF 419 en 500 (resolt retornant 403) · el lector deixava entrar massa d'hora si hi havia una reserva posterior (corregit) |
| dc 18/11 | Aplicació desplegada al servidor · Validació de dates de la reserva · Nova capçalera, peu i fotos dels pàrquings (Wikimedia Commons) · Documentació tècnica del codi · Manual d'usuari amb 14 captures · **Proves funcionals: 18/18 superades** (Joel) · Portada, índex i bibliografia (Joel) · Aplicació accessible des dels ordinadors dels companys (redirecció del port 443) | Les sessions caduquen als 30 min durant les captures (comportament esperat per seguretat) |
| dj 19/11 | | Presentació en PDF i document de presentació |
| dv 20/11 | | Assaig de la presentació i revisió final |
| ds 21/11 | | Lliurament a Moodle |

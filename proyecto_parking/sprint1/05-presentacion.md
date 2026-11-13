# Sprint 1 — Document de presentació (exposició oral, v2)

## Sistema de Reserves de Places de Pàrquing · Tarraco Parkings (Tarragona)

**Equip:** Carlos · Sara · Joel
**Durada:** **8 minuts** (la rúbrica demana entre 5 i 10) + preguntes.
**Suport:** diapositives + `06-diagramas.html` obert en una pestanya per ampliar diagrames si cal.

---

## Repartiment del temps

| # | Diapositiva | Qui | Temps |
|---|---|---|---|
| 1 | Portada | Joel | 0:20 |
| 2 | El problema i l'objectiu | Joel | 0:40 |
| 3 | Arquitectura i tecnologies | Sara | 0:50 |
| 4 | Parts de l'aplicació i rols | Sara | 0:40 |
| 5 | Casos d'ús | Sara | 0:50 |
| 6 | Flux del client (passos de l'usuari) | Sara | 0:50 |
| 7 | Lector de matrícules i estats de la reserva | Carlos | 0:40 |
| 8 | Càlcul del preu (exemple) | Carlos | 0:40 |
| 9 | Model E/R | Carlos | 1:00 |
| 10 | Què hem millorat a la BD i regles que garanteix | Carlos | 0:40 |
| 11 | Planificació: tasques, rols i Gantt | Carlos | 0:50 |
| 12 | Conclusions i següents passos | Sara | 0:20 |
| | **Total** | | **≈ 8:00** |

---

## Contingut de cada diapositiva

### 1 · Portada — *Joel*
- **Tarraco Parkings** · Sistema de reserves de places de pàrquing a Tarragona.
- Carlos, Sara, Joel · ASIX2 · Projecte 1 · Sprint 1.

> *"Bon dia, som Carlos, Sara i Joel i us presentem el projecte Tarraco Parkings."*

### 2 · El problema i l'objectiu — *Joel*
- Tarraco Parkings gestiona els pàrquings municipals de Tarragona.
- Volen que la gent pugui **reservar una plaça per Internet**, que la **barrera s'obri sola llegint la matrícula** i que es **cobri automàticament**.
- Dues interfícies: la dels **usuaris** i la de **gestió**.

> *"El client vol que els usuaris reservin per Internet, que la barrera s'obri sola amb la matrícula i que els administradors puguin gestionar-ho tot. Ara la Sara us explica com ho hem pensat."*

### 3 · Arquitectura i tecnologies — *Sara*
- Diagrama d'arquitectura (3 màquines virtuals): `srv-web` (Apache2 + PHP + SSL + RAID 1), `srv-db` (MariaDB + phpMyAdmin + còpies), `srv-monitor` (Zabbix).
- Seguretat: HTTPS, tallafocs, la BD només accepta connexions del servidor web.
- Opcional: 2n servidor web + balancejador.

### 4 · Parts de l'aplicació i rols — *Sara*
- **Web pública** (visitant) · **Àrea de client** · **Panell d'operador** · **Panell d'administració** · **Servei del lector**.
- 3 rols d'usuari: client, operador, administrador.

### 5 · Casos d'ús — *Sara*
- Diagrama de casos d'ús. Destacar: crear reserva *inclou* calcular preu; control d'accés *s'estén* amb el cobrament de l'excés.

### 6 · Flux del client — *Sara*
Els 11 passos de l'usuari (esquema funcional §6.1), en una línia:
**Registre → vehicle i mètode de pagament → formulari → preu automàtic → condicions → pagament → número + PDF → barrera → excés.**

### 7 · Lector de matrícules i estats — *Carlos*
- Entrada: reserva confirmada → obre; si no, places lliures → obre; si no → tancada.
- Sortida: si s'ha passat de l'hora → cobra la diferència amb la mateixa targeta.
- Estats: `pendent → confirmada → en_curs → completada` (+ `cancelada`, `no_presentat`). **Les reserves no s'esborren mai.**

### 8 · Càlcul del preu — *Carlos*
- Taula de tarifes de l'enunciat.
- Exemple: **08:00 → 11:00** = 1,90 + 1,90 + 0,20 = 4,00 € + 10 % = **4,40 €**.
- Exemple nocturn: **22:30 → 00:30** = 2 × 0,60 = 1,20 € + 10 % = **1,32 €**.
- El preu el calcula el **servidor**, no el navegador.

### 9 · Model E/R — *Carlos*
- Diagrama E/R. 16 taules en 3 blocs: usuaris i clients · pàrquings · reserves i control.
- Relacions de l'enunciat: **client 1:N vehicles**, **client 1:N reserves**, **cada reserva → 1 pàrquing i 1 vehicle**.
- Altres: reserva 1:N pagaments (reserva + excés), operadors N:M pàrquings.

### 10 · Millores a la base de dades — *Carlos*
- Taula "v1 → v2" (resumida a 5 punts): dates que passen de dia, mètodes de pagament, pagaments d'excés, rols, dades sensibles xifrades.
- Regles que garanteix la mateixa BD: no esborrar reserves (trigger), no superar places, vehicle del mateix client (FK compostes), historial automàtic.

### 11 · Planificació — *Carlos*
- Eina: **GitHub Projects** (captura del tauler).
- Rols: Carlos (coordinació, BD, backend), Sara (frontend, servidors, monitoratge), Joel (dades, proves, bibliografia).
- Gantt i camí crític: BD → PHP → reserva → admin.

### 12 · Conclusions — *Sara*
- Sprint 1: anàlisi i disseny complets i revisats.
- Sprint 2: muntar els servidors i programar l'aplicació en PHP.
- *"Gràcies. Teniu alguna pregunta?"*

---

## Preguntes previsibles

| Pregunta | Resposta curta | Qui |
|---|---|---|
| Per què no s'esborren les reserves? | Ho demana l'enunciat i serveix d'històric i auditoria. A més, un *trigger* de la BD ho impedeix. | Carlos |
| Per què separeu `usuaris` i `clients`? | Un admin o operador no té DNI ni vehicles. Els rols són una taula per poder-ne afegir més. | Carlos |
| Com protegiu les dades? | HTTPS, bcrypt, DNI xifrat, targetes tokenitzades, PDO (SQL injection), CSRF, tallafocs. | Sara |
| Com es cobra l'excés? | Preu real − preu reservat (+10 %), amb el mateix mètode de pagament. | Carlos |
| Què passa si el pàrquing és ple? | No es pot reservar (trigger) i el lector no obre si no hi ha reserva ni places lliures. | Carlos |
| On s'executarà? | 3 VM a VirtualBox (web, BD, monitoratge) amb Ubuntu Server. | Sara |
| Com feu les còpies? | Completa setmanal + incremental diària amb `mariabackup` i `cron`, i provem la restauració. | Carlos |
| Per què teníeu una app en React? | Va ser un prototip per validar el disseny; el producte final és PHP + MySQL com demana l'enunciat. | Sara |

---

## Consells per a l'exposició

- **Assajar amb cronòmetre** almenys una vegada: si passa de 9 min, retallar la diapositiva 10.
- Cada ponent dona pas al següent amb una frase (*"Ara en Carlos us explica la base de dades"*).
- Mostrar els diagrames grans: si no es llegeixen, obrir `06-diagramas.html` i fer zoom.
- Poca lletra a les diapositives: el detall és als documents.

---

## Materials a entregar

1. Esquema funcional / casos d'ús — `01-esquema-funcional.md` (+ diagrames)
2. Model E/R — `02-modelo-er.md` + `03-modelo-er.sql`
3. Planificació — `04-planificacion.md` + enllaç al tauler de GitHub Projects
4. Document de presentació — diapositives (PDF) + aquest guió
5. Bibliografia i ús d'IA (eines, parts fetes amb IA i *prompts*)

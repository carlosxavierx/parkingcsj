# Sprint 1 — Document de presentació (exposició oral)

## Sistema de Reserves de Places de Pàrquing · Tarraco Parkings (Tarragona)

**Durada orientativa:** 10–15 minuts + torn de preguntes.
**Ponents:** tots els membres de l'equip (1–2 minuts per apartat).
**Format:** presentació de diapositives + demo funcionant.

---

## Guió de la presentació

### D1 · Portada (1 diapositiva · 30 s)
- Títol del projecte.
- Nom de grup / membres.
- Data i curs.

### D2 · Objectiu i context del projecte (1 diapositiva · 1 min)
- L'empresa **Tarraco Parkings** gestiona els pàrquings municipals de Tarragona.
- Necessitat: sistema en línia per **reservar places de pàrquing**, controlar l'accés i fer el pagament.
- Dos rols: **usuaris finals** i **administradors**.

### D3 · Esquema general del sistema (1–2 diapositives · 2 min)
- Arquitectura: web (usuari) + panel (admin) + processos automàtics (càlcul de preus, lector de matrícules).
- Cumpliment dels objectius de l'enunciat.
- Stack: PHP + MariaDB + Apache2. *(Futurs sprints: RAID, SSL, monitoratge.)*

### D4 · Funcionament i flux de l'usuari (2 diapositives · 2–3 min)
- Recorregut complet:
  1. Registre (dades personals) i inici de sessió.
  2. Alta de vehicles (marca, model, matrícula).
  3. Selecció de pàrquing, data, hora d'entrada i sortida.
  4. **Càlcul automàtic del preu** i 10% de gestió.
  5. Acceptació de condicions i **pagament**.
  6. Generació de **número de reserva** i **PDF**.
  7. El dia de la reserva: el **lector de matrícules** obra la barrera si la reserva és activa.
  8. A la sortida: control del temps i **cobraments automàtics** per excés.

### D5 · Procés complet de reserva (1 diapositiva · 1–2 min)
- Mostrar el flux de reserva (diapositiva amb el diagrama de flux).
- Explicar el **càlcul de tarifes** amb 1 exemple real:
  - 3 hores diürnes → 1,90 + 1,90 + 0,20 = 4,00 € + 0,40 € de gestió = **4,40 €**.

### D6 · Base de dades: model E/R (2–3 diapositives · 3 min)
- Presentar el diagrama E/R.
- Taules principals: `clients`, `vehicles`, `parquings`, `places`, `reserves`, `pagaments`, `accessos`, `tarifes`.
- Relacions clau:
  - Un client → N vehicles i N reserves.
  - Cada reserva → un únic vehicle i un únic pàrquing.
  - Una reserva → un únic pagament (1:1).
- Claus primàries i foranes: mostrar exemples d'FK (`reserves.id_client`, `reserves.id_vehicle`, ...).
- **Justificació del disseny:**
  - 3FN (sense dades repetides).
  - `vehicles.matricula` UNIQUE per al lector de matrícules.
  - Reserves amb estat `cancel·lada` (mai s'eliminen → històric/auditoria).
  - Taula `tarifes` configurable sense tocar el codi.

### D7 · Planificació (1–2 diapositives · 2 min)
- Metodologia per **sprints** (S1–S9) i eines (Trello/GitHub Projects).
- **Distribució de rols:** cap de projecte, backend/BD, frontend/UX, infraestructura/seguretat.
- **Calendari:** 10 setmanes; diagrama de Gantt.
- Dependències principals: S2 → S3 → S4 → S5/S6/S7 → S8 → S9.

### D8 · Demo (a partir de Sprint 5) · 2–3 min
- Mostrar: registre, login, alta de vehicle, reserva amb càlcul de preu, PDF, i el panel d'administració (reserves i cancel·lació).

### D9 · Conclusions i properes passes (1 diapositiva · 1 min)
- Resum del que està fet (Sprint 1) i del que ve (S2 infraestructura, S3 BD, S4 backend...).
- Agraïments i torn de preguntes.

---

## Preguntes previsibles del tribunal (preparar respostes)

1. **Per què les reserves no s'eliminen mai?**
   - Per auditoria i històric: es marca com a `cancel·lada`. Compliment de l'especificació.
2. **Com es calcula el preu si el client surt més tard del reservat?**
   - El lector registra la sortida, es calcula la hora addicional (tarifa normal o nocturna) i es **cobra automàticament** amb el mateix mètode de pagament.
3. **Com s'assegura la seguretat de les dades?**
   - Contrasenyes amb `password_hash()` (bcrypt), sentències preparades (PDO) contra SQL injection, sanitització (XSS), sessions segures, SSL/TLS i xifrat/anonimització de dades sensibles (sprints S2/S8).
4. **Com funciona el control d'accés?**
   - Lector de matrícules: busca la placa a `vehicles`, comprova que hi ha una **reserva `confirmada`** per a aquell dia/hora i obre la barrera; registra l'accés a la taula `accessos`.
5. **Què passa si el pàrquing està ple?**
   - Es comproven les `places` lliures abans d'obrir la barrera (i les places reservables abans de crear la reserva).
6. **On es configuren les tarifes?**
   - A la taula `tarifes`; l'administrador les pot editar sense canviar codi.
7. **Què passa si un client no es presenta?**
   - La reserva es pot cancel·lar des de l'admin (estat `cancel·lada`); política de reemborsament definible.

---

## Materials a aportar el dia de l'exposició

- Presentació en format editable (PPTX/Canva/Google Slides) + PDF.
- `01-esquema-funcional.md` (casos d'ús i fluxos).
- `02-modelo-er.md` + `03-modelo-er.sql` (model E/R).
- `04-planificacion.md` (Gantt i rols).
- `06-diagramas.html` (diagrames renderitzats per mostrar en pantalla).
- Bibliografia (incloent les eines d'IA usades i els prompts, segons les normes del curs).

---

## Documents relacionats

- `01-esquema-funcional.md` — contingut per a D4/D5.
- `02-modelo-er.md` — contingut per a D6.
- `04-planificacion.md` — contingut per a D7.
- `06-diagramas.html` — diagrames per a les diapositives.
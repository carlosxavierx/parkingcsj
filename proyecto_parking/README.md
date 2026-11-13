# Tarraco Parkings — Sistema de Reserves de Places de Pàrquing

Aplicació web per reservar places de pàrquing als pàrquings municipals de
Tarragona, gestionada per l'empresa **Tarraco Parkings**.

## Stack

- **Servidor:** Apache2 + PHP 8.x
- **Base de dades:** MySQL / MariaDB
- **Frontend:** HTML5 + CSS + JavaScript (disseny propi, des de zero)
- **Sistema:** GNU/Linux (Debian/Ubuntu Server)

## Estructura prevista

```
proyecto_parking/
├── docs/            # Documents de cada sprint (esquema funcional, E/R, planificació...)
├── database/        # Scripts SQL (esquema, seed, backups)
├── public/          # Frontend: index.html, css/, js/
└── src/             # Backend PHP: config, includes, controllers, views
```

> **Estat:** Sprint 1 (anàlisi i disseny) revisat — v2, 13/11/2026.
> L'aplicació definitiva es desenvolupa al Sprint 2 en **PHP + MariaDB sobre Apache2**, com demana l'enunciat.

---

## Prototip visual (React + Firebase)

La carpeta [`../app/`](../app/) conté un **prototip** de la interfície fet durant octubre per validar el
disseny i el flux de reserva. **No és el producte final**: l'enunciat exigeix Apache2 + PHP + MySQL/MariaDB.
Se'n reaprofiten el disseny (CSS), les pantalles i l'algorisme de càlcul de preus.

## Documents del Sprint 1

A la carpeta `sprint1/`:

| Fitxer | Contingut |
|---|---|
| `01-esquema-funcional.md` | Casos d'ús, fluxos usuari/admin i càlcul de tarifes |
| `02-modelo-er.md` | Model E/R v2 (16 taules, relacions, claus, triggers, vistes) |
| `03-modelo-er.sql` | Script de creació de la BD + dades de prova (MariaDB/MySQL) |
| `04-planificacion.md` | Sprints, tasques, rols, Gantt |
| `05-presentacion.md` | Guió de l'exposició oral |
| `06-diagramas.html` | Diagrames per a la presentació (generat amb `python3 generar-diagramas.py`) |

> Els fitxers es mantenen provisionalment a `sprint1/`; s'han de moure a `docs/`
> quan es reorganitzi la documentació del projecte.
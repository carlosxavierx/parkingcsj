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

> Treball en curs: fins ara només s'ha completat el **Sprint 1 (anàlisi i disseny)**.
> Cap codi de l'aplicació no s'ha creat encara. Es desenvolupa des de zero sense.

---

## Implementació web (React + Firebase + Geoapify)

La versió operativa de l'aplicació viu a [`../app/`](../app/) i es pot executar amb
`npm install && npm run dev`. Veure [`../app/README.md`](../app/README.md) per a la
documentació completa (estructura MVC, seed de Firestore, regles de seguretat…).

## Documents del Sprint 1

A la carpeta `sprint1/`:

| Fitxer | Contingut |
|---|---|
| `01-esquema-funcional.md` | Casos d'ús, fluxos usuari/admin i càlcul de tarifes |
| `02-modelo-er.md` | Model E/R (8 entitats, relacions, claus) |
| `03-modelo-er.sql` | DDL de creació de la BD (MySQL/MariaDB) |
| `04-planificacion.md` | Sprints, tasques, rols, Gantt |
| `05-presentacion.md` | Guió de l'exposició oral |
| `06-diagramas.html` | Diagrames |

> Els fitxers es mantenen provisionalment a `sprint1/`; s'han de moure a `docs/`
> quan es reorganitzi la documentació del projecte.
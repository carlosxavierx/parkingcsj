# Base de dades · CSJ Parkings

| Fitxer | Contingut |
|---|---|
| `schema.sql` | Creació de la base de dades `csj_parkings`: taules, claus, restriccions, índexs i vistes |
| `seed.sql` | Dades inicials: tarifes, 14 pàrquings de Tarragona, places, usuaris de demostració i una reserva d'exemple |

Documentació del model: `../docs/sprint2/04-model-er-actualitzat.md`.

## Instal·lació (al servidor `parking-srv`)

```bash
sudo mariadb < schema.sql
sudo mariadb csj_parkings < seed.sql
```

## Usuaris de demostració

| Rol | Email | Contrasenya |
|---|---|---|
| Administrador | `admin@csjparkings.cat` | `Demo1234!` |
| Operador (Plaça de la Font) | `operador@csjparkings.cat` | `Demo1234!` |
| Client | `client@csjparkings.cat` | `Demo1234!` |

> Només per a desenvolupament i demostració: s'han de canviar abans de posar el sistema en producció.

## Autoria

Els scripts `schema.sql` i `seed.sql` s'han elaborat amb l'ajuda de **Claude (IA d'Anthropic)** a partir del
model E/R dissenyat per l'equip al Sprint 1.

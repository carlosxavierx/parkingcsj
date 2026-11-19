# CSJ Parkings — Sistema de Reserves de Places de Pàrquing

Aplicació web per reservar places als pàrquings municipals de Tarragona, amb tres rols
(client, operador de pàrquing i administrador), càlcul automàtic del preu, justificant en PDF,
panel d'administració i simulador del lector de matrícules.

## Stack

| Capa | Tecnologia |
|---|---|
| Sistema | Ubuntu Server 22.04 (VirtualBox) amb RAID 1 (`mdadm`) |
| Servidor web | Apache 2.4 + PHP 8.1, HTTPS amb CA pròpia |
| Base de dades | MariaDB 10.6 + phpMyAdmin |
| Seguretat | UFW, fail2ban, SSH només amb clau, dades sensibles xifrades (AES-256) |
| Còpies i monitoratge | Còpies completes i incrementals programades · Prometheus + Grafana en un segon servidor |

## Estructura

```
proyecto_parking/
├── app_php/      Aplicació web (public/ = DocumentRoot, src/ = controladors, models i vistes)
├── database/     schema.sql (taules, vistes, restriccions) i seed.sql (dades inicials)
├── infra/        Scripts d'instal·lació numerats per ordre (02-raid1.sh … 07-desplegar-app.sh)
│   └── monitoratge/   Configuració de Prometheus, alertes i taulers de Grafana
└── docs/
    ├── sprint1/  Anàlisi i disseny (esquema funcional, E/R, planificació, presentació)
    └── sprint2/  Implementació (instal·lació, E/R actualitzat, manual, codi, bibliografia, presentació)
```

## Provar l'aplicació

| | |
|---|---|
| Web | `https://csjparkings.local/` (o `https://192.168.56.30/`) |
| Monitoratge | `http://192.168.56.31:3000` (Grafana) |
| Usuaris de demostració | `admin@csjparkings.cat`, `operador@csjparkings.cat`, `client@csjparkings.cat` · contrasenya `Demo1234!` |

Instal·lació dels servidors: [`docs/sprint2/02-installacio-servidors.md`](docs/sprint2/02-installacio-servidors.md) ·
Aplicació: [`app_php/README.md`](app_php/README.md) · Índex de la documentació: [`docs/sprint2/README.md`](docs/sprint2/README.md)

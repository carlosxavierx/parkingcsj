<div align="center">

![CSJ Parkings](../../app_php/public/img/logo-horizontal.png)

# CSJ Parkings

## Sistema de reserves de places als pàrquings municipals de Tarragona

### Sprint 2 · Implementació
#### Documentació tècnica

<br>

**Cicle:** CFGS Administració de Sistemes Informàtics en Xarxa (ASIX) · 2n curs
**Mòdul:** 0379 · Projecte d'administració de sistemes informàtics en xarxa

<br>

| Integrant | Rol al projecte |
|---|---|
| **Carlos Xavier** | Coordinació i infraestructura |
| **Sara Pulido** | Aplicació i base de dades |
| **Joel Zurita** | Dades, proves i suport documental |

<br>

**Data de lliurament:** dissabte, 21 de novembre de 2026

**Aplicació:** `https://csjparkings.local/` · `https://192.168.56.30/`

</div>

---

## Índex general

### 0. Revisió de l'enunciat — [`00-revisio-enunciat.md`](00-revisio-enunciat.md)
1. Infraestructura i seguretat
2. Aplicació (ha de funcionar)
3. Lliurables del Sprint 2
4. Correccions al disseny del Sprint 1

### 1. Arquitectura i màquines virtuals — [`01-arquitectura-i-maquines-virtuals.md`](01-arquitectura-i-maquines-virtuals.md)
1. Arquitectura general
2. Màquines virtuals (justificació del dimensionament, pla d'adreçament)
3. Creació de les màquines a VirtualBox
4. Instal·lació següent
5. Migració a l'equip de l'institut (OVA)

### 2. Instal·lació i configuració dels servidors — [`02-installacio-servidors.md`](02-installacio-servidors.md)
1. Preparació del sistema i SSH
2. RAID 1 amb mdadm (creació, prova de fallada, reconstrucció)
3. Apache 2 + PHP
4. MariaDB + phpMyAdmin
5. Certificat SSL (HTTPS) amb autoritat de certificació pròpia
6. Securització (phpMyAdmin, PHP, SSH, UFW, fail2ban, actualitzacions automàtiques)
7. Usuaris i permisos del sistema de fitxers
8. Còpies de seguretat completes i incrementals
9. Monitoratge (Prometheus + Grafana)
10. Alta disponibilitat
11. Proves finals de la infraestructura

### 3. Planificació actualitzada — [`03-planificacio-actualitzada.md`](03-planificacio-actualitzada.md)
1. Canvis respecte a la planificació del Sprint 1
2. Repartiment de rols
3. Tasques del Sprint 2
4. Diagrama de Gantt
5. Prioritats (MoSCoW)
6. Seguiment

### 4. Model E/R actualitzat — [`04-model-er-actualitzat.md`](04-model-er-actualitzat.md)
1. Canvis respecte al model del Sprint 1
2. Diagrama E/R
3. Relacions
4. Rols d'usuari i permisos
5. Justificació del disseny
6. Disponibilitat d'un pàrquing (consulta clau)
7. Dades inicials

### 5. Manual d'usuari — [`05-manual-usuari.md`](05-manual-usuari.md)
1. Què és CSJ Parkings
2. Pàgina d'inici
3. Crear un compte i iniciar sessió
4. El meu perfil (dades, vehicles, mètodes de pagament)
5. Fer una reserva
6. Les meves reserves, justificant PDF i cancel·lació
7. Entrar i sortir del pàrquing
8. Operador: panell del pàrquing i lector de matrícules
9. Administrador
10. Preguntes freqüents i missatges d'error

### 6. Documentació tècnica del codi — [`06-documentacio-codi.md`](06-documentacio-codi.md)
1. Tecnologies utilitzades
2. Arquitectura de l'aplicació (MVC)
3. Estructura de fitxers
4. Rutes i rols
5. Seguretat de l'aplicació
6. Lògica de negoci (reserva, preu, cancel·lació, PDF, lector de matrícules…)
7. Interfície (HTML, CSS i JavaScript)
8. Desplegament al servidor
9. Proves realitzades
10. Limitacions i millores futures

### 7. Bibliografia — [`07-bibliografia.md`](07-bibliografia.md)

### Annexos
- Informes d'execució dels scripts d'instal·lació: `informe-03-lamp.txt`, `informe-04-ssl-seguretat.txt`,
  `informe-05-copies-*.txt`, `informe-06-monitoratge-*.txt`, `informe-07-app.txt`
- Scripts d'instal·lació i desplegament: `proyecto_parking/infra/`
- Scripts de la base de dades: `proyecto_parking/database/schema.sql` i `seed.sql`
- Codi font de l'aplicació: `proyecto_parking/app_php/`
- Repositori: `https://github.com/carlosxavierx/parkingcsj`

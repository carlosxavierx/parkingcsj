# CLAUDE.md · Proyecto CSJ Parkings (Sprint 2)

Contexto completo para continuar el proyecto sin la conversación anterior.
Lee este fichero entero antes de hacer nada.

## 1. Quién y qué

- **Proyecto:** *CSJ Parkings*, sistema de reservas de plazas en los parkings municipales de Tarragona.
  Es el proyecto de ASIX2 (asignatura de Administración de Sistemas / proyecto, Moodle ASIX2_0379_PA).
- **Equipo:** Carlos (coordinación e infraestructura, es quien usa este ordenador), Sara (aplicación y BD),
  Joel (datos, pruebas y apoyo documental: tareas sencillas que no bloqueen el funcionamiento del proyecto).
- **Entrega del Sprint 2:** **sábado 21/11/2026** en Moodle.
- **Idiomas:** hablar con el usuario en **castellano**. Toda la documentación y los textos de la web en **catalán**
  (como el Sprint 1).
- **Nombre:** siempre **CSJ Parkings** (nunca "Tarraco Parkings", que era un nombre provisional ya eliminado).
  "Parking Port Tarraco" sí es el nombre real de un parking y se mantiene.

## 2. Lo que pide el enunciado (resumen)

Stack obligatorio: **GNU/Linux + Apache2 + PHP + MariaDB + phpMyAdmin**, SSL, RAID, copias completas e
incrementales programadas, monitorización (mejor en servidor aparte), seguridad (firewall, permisos,
cifrado/anonimización de datos sensibles), alta disponibilidad. Balanceo de carga opcional.

Aplicación (tiene que **funcionar**, es lo que más puntúa):
- Registro obligatorio para cualquier acción. **Varios roles** (Sprint 2 pide administradores y otros roles).
- Inicio con información de los parkings y formulario de reserva.
- Perfil con datos personales (editable), **vehículos** (marca, modelo, matrícula…), **métodos de pago**.
- Reserva: parking, fecha, hora de entrada y salida, **precio automático + 10 % de gastos de gestión**,
  aceptación de condiciones obligatoria, **número de reserva** y **PDF** para entrar al parking.
- Administración: gestionar parkings y plazas; ver y modificar **todas** las reservas y cambiar su estado;
  las reservas **NO se eliminan nunca** (solo se cancelan: borrado lógico); **búsquedas avanzadas**.
- Lector de matrículas (entrada/salida, comprueba reserva activa o plazas libres, abre barrera; cobra el
  exceso de tiempo con el mismo método de pago). En Sprint 2: simulador si da tiempo.

Entregables del Sprint 2: presentación + documento de presentación, documentación técnica (tecnologías
y código, con portada, índice, capturas con descripción y bibliografía), planificación actualizada,
manual de usuario, modelo E/R actualizado, enlace o IP para probar la aplicación.

## 3. Estado actual (HECHO ✅)

Todo documentado en `proyecto_parking/docs/sprint2/` (en catalán, con capturas en `img/`):

| Fichero | Contenido |
|---|---|
| `00-revisio-enunciat.md` | Matriz de cumplimiento del enunciado |
| `01-arquitectura-i-maquines-virtuals.md` | Arquitectura, VMs, red, migración al PC del instituto |
| `02-installacio-servidors.md` | Instalación completa (apartados 1–11): RAID, LAMP, SSL, seguridad, permisos, copias, monitorización |
| `03-planificacio-actualitzada.md` | Reparto de tareas, Gantt y seguimiento diario |
| `04-model-er-actualitzat.md` | Modelo E/R actualizado, roles y permisos |
| `informe-*.txt` | Salidas de los scripts de instalación (evidencias) |

Infraestructura (VirtualBox en el portátil de Carlos: 8 GB RAM, 4 hilos):

| VM | IP (solo-anfitrión) | Qué tiene |
|---|---|---|
| **parking-srv** (2 CPU, 1,5 GB) | 192.168.56.30 | Ubuntu 22.04 · RAID 1 mdadm (`/dev/md0` en `/srv`) · Apache 2.4 + PHP 8.1 · MariaDB 10.6 (datos en `/srv/mysql`, binlogs) · phpMyAdmin · HTTPS con CA propia · UFW · fail2ban · copias · node/mysqld exporters |
| **parking-mon** (1 CPU, 1 GB) | 192.168.56.31 | Prometheus + blackbox + Grafana (`http://192.168.56.31:3000`) · destino de las copias (`/srv/backups/parking-srv`) · UFW · fail2ban |

- Usuario de las dos VMs: `carlos`. **SSH solo con clave** (la clave de Windows de Carlos ya funciona:
  `ssh carlos@192.168.56.30`, sin contraseña). Root no puede entrar por SSH.
- **`sudo` pide contraseña.** Tú (Claude) NO puedes escribirla: cualquier comando con sudo lo tiene que
  lanzar Carlos con `ssh -t carlos@IP "sudo ..."`. Prepara scripts en `proyecto_parking/infra/` y dale
  los comandos exactos (como hemos hecho con `infra/03-lamp.sh`, `04-ssl-seguretat.sh`, etc.).
  Los scripts guardan un informe en `~/informe-XX.txt` que Carlos trae con `scp` a `docs/sprint2/`.
- Web: **`https://csjparkings.local/`** o **`https://192.168.56.30/`** (HTTP redirige a HTTPS; CA
  "CSJ Parkings Root CA" ya instalada en el Windows de Carlos). phpMyAdmin: `/phpmyadmin/` solo desde 192.168.56.0/24.
- Ahora en la web hay solo una **página provisional** (`/srv/www/csjparkings/public/index.php`) que lista los parkings.

### Base de datos `csj_parkings` (ya creada en el servidor)

Scripts en `proyecto_parking/database/schema.sql` y `seed.sql` (probados y funcionando). Tablas:
`parquings`, `usuaris` (con `rol` ENUM client/operador/admin, `id_parquing_assignat`, `telefon_xifrat`,
`dni_xifrat`, `dni_hash`, `password_hash`, `intents_fallits`, `bloquejat_fins`), `vehicles` (`matricula`
única, `actiu`), `metodes_pagament` (solo `ultims_digits`, `token_passarella`, `per_defecte`, `actiu`;
nunca número completo ni CVV), `places`, `tarifes` (por `codi`; `id_parquing` NULL = general),
`reserves` (`numero_reserva` formato **`CSJ-AAAAMMDD-XXXXXX`**, `entrada_prevista`/`sortida_prevista`
DATETIME, `preu_base`, `despeses_gestio`, `preu_total` con CHECK, `estat` ENUM pendent/confirmada/en_curs/
completada/cancellada, `condicions_acceptades`, campos de cancelación), `pagaments` (1:N, `concepte`
reserva/exces/reemborsament), `accessos`, `historial_reserves`. Vistas `v_reserves_detall` y `v_ocupacio_actual`.
Mira `docs/sprint2/04-model-er-actualitzat.md` (incluye la consulta de disponibilidad).

Datos: 14 parkings reales de Tarragona, 323 plazas, tarifas, 1 reserva de ejemplo y usuarios demo
(contraseña de todos **`Demo1234!`**): `admin@csjparkings.cat`, `operador@csjparkings.cat`
(Plaça de la Font), `client@csjparkings.cat`.

Tarifas generales (tabla `tarifes`): 1,90 €/h las 2 primeras horas, 0,20 €/h a partir de la 3.ª,
nocturna 0,60 €/h de 22 a 6, máximo 4,00 €/día, **gastos de gestión 10 %**.

Usuarios de BD (contraseñas en `/root/csj-credencials.txt` del servidor, solo root):
- **`csj_app`**: el de la web. Solo `SELECT, INSERT, UPDATE` (**sin DELETE**). Todo borrado debe ser lógico
  (`actiu = 0`, `estat = 'cancellada'`).
- `csj_backup`, `csj_admin` (phpMyAdmin), `csj_exporter` (monitorización).

Configuración de la web en el servidor: `/srv/www/csjparkings/config/config.php` (fuera de `public/`) devuelve
`['db' => ['host','name','user','pass','charset']]`. Permisos: código `root:www-data` 750/640 (Apache solo lee);
`/srv/www/csjparkings/storage/` es la única carpeta escribible por `www-data`. PHP: `display_errors=Off`,
cookies de sesión Secure/HttpOnly/SameSite=Strict.

## 4. Lo que FALTA (por orden de prioridad)

1. **Aplicación PHP** (prioridad máxima). Proponer y crear en `proyecto_parking/app_php/`:
   - PHP 8.1 sin framework, estructura MVC sencilla: `public/` (index.php como front controller, css, js),
     `src/` (controladores, modelos, vistas), `config/config.example.php` (sin contraseñas reales).
   - PDO con sentencias preparadas, `htmlspecialchars` en toda salida, **token CSRF** en todos los formularios,
     `password_hash`/`password_verify`, `session_regenerate_id` al iniciar sesión, bloqueo tras 5 intentos fallidos,
     cifrado AES-256 (`openssl_encrypt`) de teléfono y DNI con una clave `app_key` que hay que añadir al
     `config.php` del servidor (con sudo, mediante script).
   - Funcionalidades: registro, login/logout, perfil, vehículos, métodos de pago (simulados), inicio con parkings
     y plazas libres, reserva (cálculo del precio en JS como vista previa y **en PHP como valor definitivo**,
     con las tarifas de la BD; +10 %), condiciones obligatorias, número de reserva, **PDF** (FPDF: en Ubuntu existe
     el paquete `php-fpdf`, se instala con sudo), "Mis reservas" con cancelación; **panel admin** (parkings,
     plazas, tarifas, todas las reservas con cambio de estado y cancelación, búsquedas por cliente, matrícula,
     parking, fecha y estado, gestión de usuarios/roles); **rol operador** (ocupación y reservas del día de su parking).
     Registrar cada cambio en `historial_reserves`.
   - Diseño limpio y responsive, todo en catalán. Se puede reutilizar el aspecto/logos del prototipo React de
     `app/` (carpeta `app/public/` tiene logos), pero **la carpeta `app/` no se toca**: es un prototipo antiguo.
   - Script de despliegue `proyecto_parking/infra/07-desplegar-app.sh` (Carlos lo ejecuta con sudo): copia
     `app_php/` a `/srv/www/csjparkings/` **sin sobrescribir `config/config.php`**, ajusta permisos, añade `app_key`
     al config si falta e instala `php-fpdf`. Copia previa: `scp -r proyecto_parking\app_php proyecto_parking\infra carlos@192.168.56.30:~`.
   - `proyecto_parking/app_php/README.md` con instrucciones y la nota: *"Codi elaborat amb l'ajuda de Claude (IA d'Anthropic)"*.
   - Probarlo: puedes hacer peticiones sin sudo (`curl -k https://192.168.56.30/...` desde Windows o
     `ssh carlos@192.168.56.30 "curl -sk https://localhost/..."`) y mirar logs con `ssh carlos@192.168.56.30 "sudo tail ..."`
     (ese último pide contraseña: pídeselo a Carlos).
2. **Simulador del lector de matrículas** (rol operador): entrada/salida por matrícula, comprobar reserva activa o
   plazas libres, "abrir barrera", registrar en `accessos`, calcular exceso a la salida y crear `pagaments` con `concepte='exces'`.
3. **Manual de usuario** `docs/sprint2/05-manual-usuari.md` con capturas (Carlos hace las capturas en Chrome y te las pasa).
4. **Documentación técnica del código** (`docs/sprint2/06-documentacio-codi.md`): estructura, seguridad de la app,
   fragmentos comentados. Después: portada, índice y **bibliografía** del conjunto.
5. Actualizar `03-planificacio-actualitzada.md` (tabla de seguimiento diario) y `00-revisio-enunciat.md` (estados).
6. Presentación (el usuario dirá formato).

## 5. Normas importantes

- **Declaración de IA:** el profesor la quiere hecha a mano por el equipo al final. **No** poner menciones a IA en la
  documentación, planificación ni manual. **Solo** en los README de las partes de código/scripts: "elaborat amb
  l'ajuda de Claude (IA d'Anthropic)".
- **Nunca** escribir contraseñas reales en el repositorio ni en el chat. Quitar hashes de contraseñas de los informes.
- **VS Code sobrescribe** los ficheros si Carlos los tiene abiertos y guarda: antes de modificar documentos, pídele
  que cierre esas pestañas. Comprueba después que tus cambios siguen ahí.
- La carpeta del proyecto es **`C:\Users\carlo\Desktop\parkingcsj`** (repo GitHub `carlosxavierx/parkingcsj`, rama `main`).
  Recuerda a Carlos hacer `git add / commit / push` al final de cada bloque (no hagas push sin que lo pida).
- Para apagar las VMs: `sudo poweroff` o "Apagar la máquina", **nunca "Guardar el estado"** (el reloj se atrasa y
  `apt` falla con "Release file … not valid yet"; se arregla con `sudo date -s "AAAA-MM-DD HH:MM:SS"`).
- Antes de trabajar con los servidores, comprueba que están encendidos (`ping 192.168.56.30` y `.31`).
- Carlos prefiere: instrucciones claras, paso a paso, comandos listos para copiar en PowerShell, y que solo se hagan
  capturas de las cosas importantes. Explica brevemente el porqué de cada decisión (lo tiene que defender en la presentación).

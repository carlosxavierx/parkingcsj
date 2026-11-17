# CSJ Parkings · aplicació web (PHP)

Aplicació de reserves de places als pàrquings municipals de Tarragona.
PHP 8.1 sense framework, patró MVC senzill, MariaDB amb PDO.

> Codi elaborat amb l'ajuda de Claude (IA d'Anthropic).

## Estructura

```
app_php/
├── public/                 ← DocumentRoot d'Apache (l'única carpeta accessible des del web)
│   ├── index.php           ← controlador frontal: totes les peticions entren aquí
│   ├── .htaccess           ← reescriptura d'URL cap a index.php
│   ├── css/app.css         ← estils (responsive)
│   ├── js/app.js           ← vista prèvia del preu, mapa, petites millores d'usabilitat
│   └── img/                ← logotips
├── src/
│   ├── bootstrap.php       ← arrencada: autoload, configuració, gestió d'errors
│   ├── helpers.php         ← funcions globals (e(), flash(), csrf_field()...)
│   ├── routes.php          ← taula de rutes URL → controlador
│   ├── Core/               ← Router, Database (PDO), Auth, Session, Csrf, Crypto, View, Config
│   ├── Models/             ← accés a dades: Parquing, Tarifa, Usuari, Vehicle, MetodePagament,
│   │                         Reserva, Historial, Acces
│   ├── Services/           ← CalculPreu, LectorMatricules (simulador), PdfReserva (FPDF)
│   ├── Controllers/        ← Home, Auth, Perfil, Vehicle, Pagament, Reserva, Operador, Admin
│   └── Views/              ← plantilles PHP (layout + una carpeta per secció)
└── config/
    └── config.example.php  ← exemple (el real, amb contrasenyes, NOMÉS és al servidor)
```

## Funcionalitats

| Rol | Què pot fer |
|---|---|
| Visitant | Veure els pàrquings, places lliures, mapa i tarifes. Registrar-se. |
| **Client** | Perfil editable, vehicles, mètodes de pagament (simulats), reservar (preu automàtic + 10 % de gestió, condicions obligatòries), número de reserva, justificant **PDF**, les seves reserves i cancel·lació. |
| **Operador** | Tot el del client + ocupació i reserves del dia del seu pàrquing, canvi d'estat i **simulador del lector de matrícules** (entrada/sortida, barrera, cobrament de l'excés). |
| **Administrador** | Tot l'anterior + pàrquings i places, tarifes, **totes les reserves** amb cerca avançada (número, client, email, matrícula, pàrquing, estat, dates), modificació i canvi d'estat, usuaris i rols. |

Les reserves **no s'eliminen mai** (l'usuari de BD `csj_app` no té permís `DELETE`): es cancel·len
(esborrat lògic) i cada canvi queda a `historial_reserves`.

## Seguretat

- **Injecció SQL:** totes les consultes amb sentències preparades (PDO, `EMULATE_PREPARES = false`).
- **XSS:** tota la sortida passa per `e()` (`htmlspecialchars`).
- **CSRF:** token aleatori per sessió a tots els formularis; el Router el comprova en totes les peticions POST.
- **Contrasenyes:** `password_hash()` / `password_verify()` (bcrypt); política de mínim 8 caràcters amb majúscules, minúscules i xifres.
- **Força bruta:** bloqueig del compte 15 minuts després de 5 intents fallits; missatge d'error genèric.
- **Sessions:** galeta `Secure`, `HttpOnly`, `SameSite=Strict`; `session_regenerate_id()` en iniciar sessió i canviar la contrasenya; caducitat per 30 min d'inactivitat.
- **Dades sensibles:** telèfon, DNI i referències de PayPal/Bizum xifrats amb **AES-256-GCM** (`openssl_encrypt`) amb la clau `app_key` de `config.php`; el DNI té a més un hash HMAC per detectar duplicats sense desxifrar-lo. De les targetes només es guarden els 4 últims dígits (validació de Luhn, el CVV no es guarda mai).
- **Control d'accés:** cada acció comprova el rol (`Auth::requireRole`) i que l'element pertanyi a l'usuari (un client no pot veure reserves, vehicles ni targetes d'altres).
- **Errors:** mai es mostren detalls tècnics; es registren al log d'Apache.
- **Configuració** fora de `public/`; permisos `root:www-data` 750/640 (Apache només pot llegir el codi).

## Càlcul del preu

`src/Services/CalculPreu.php` (definitiu, al servidor) i `public/js/app.js` (vista prèvia) fan el mateix càlcul
amb les tarifes de la taula `tarifes` (les pròpies d'un pàrquing substitueixen les generals):

1. Es compta minut a minut des de l'entrada.
2. Minuts entre les 22 h i les 6 h → tarifa nocturna (0,60 €/h).
3. La resta: primeres 2 hores de l'estada → 1,90 €/h; a partir de la 3a → 0,20 €/h.
4. Màxim de 4,00 € per dia natural.
5. \+ 10 % de despeses de gestió.

Exemple: de 10:00 a 13:00 → 1,90 + 1,90 + 0,20 = 4,00 € + 0,40 € = **4,40 €**.

## Lector de matrícules (simulador)

Panell `/operador`. **Entrada:** si la matrícula té una reserva confirmada vigent (es pot arribar fins a 30 min abans)
s'obre la barrera i la reserva passa a *en curs*; si no, només entra si queden places de rotació (no reservables).
**Sortida:** si se surt més tard de l'hora prevista (5 min de cortesia) es calcula l'excés i es cobra amb el
mateix mètode de pagament (`pagaments.concepte = 'exces'`); la reserva passa a *completada*.
Es pot indicar una hora simulada per fer proves. Tot queda a la taula `accessos`.

## Desplegament

Des de Windows, a la carpeta del projecte:

```powershell
scp -r proyecto_parking\app_php proyecto_parking\infra carlos@192.168.56.30:~
ssh -t carlos@192.168.56.30 "sudo bash ~/infra/07-desplegar-app.sh"
```

L'script instal·la `php-fpdf`, copia el codi a `/srv/www/csjparkings/` **sense sobreescriure `config/config.php`**,
hi afegeix `app_key` si no hi és, ajusta permisos, comprova la sintaxi i deixa l'informe a `~/informe-07-app.txt`.

## Usuaris de demostració

Contrasenya de tots: `Demo1234!` — `admin@csjparkings.cat`, `operador@csjparkings.cat` (Plaça de la Font),
`client@csjparkings.cat` (vehicles 1234ABC i 5678DEF, targeta Visa ···· 4242).

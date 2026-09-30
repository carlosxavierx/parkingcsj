# Tarraco Parkings · App web de reservas de plazas

Aplicación web para **encontrar, comparar y reservar plazas de aparcamiento** en
Tarragona. Muestra un **rail con los parkings mejor valorados cerca de tu
ubicación antes del mapa**, mapa interactivo con la API de **Geoapify**, login con
**Firebase Auth** y base de datos **Firestore**.

---

## 1. Stack

| Capa | Tecnología |
|---|---|
| Frontend | React 18 + Vite (estructura MVC) |
| Routing | react-router-dom v6 |
| Mapa | Leaflet + react-leaflet, tiles de **Geoapify** (`osm-bright`) |
| POIs de aparcamientos | **Geoapify Places API** (`/v2/places`) + geocodificación inversa |
| Autenticación | **Firebase Authentication** (email/contraseña) |
| Base de datos | **Cloud Firestore** (`parkings`, `users`, `reservations`) |
| Backend de datos | firebase-admin (solo script de seed) |
| Estilos | CSS propio (variables, diseño responsive, modo impresión) |

## 2. Estructura (MVC)

```
app/
├── index.html
├── vite.config.js
├── firebase.json              # reglas de Firestore + hosting
├── firestore.rules            # reglas de seguridad (publicar en consola)
├── scripts/
│   └── seed-firestore.mjs     # puebla Firestore con los parkings reales
└── src/
    ├── config/firebase.js     # configuración Firebase
    ├── models/                # MODELO  · lógica de negocio
    │   ├── parkingModel.js        # normalización, distancia, orden por valoración
    │   ├── pricingModel.js        # cálculo de tarifas (+10 % gestión)
    │   └── reservationModel.js    # validación del formulario, estados, nº reserva
    ├── services/              # SERVICIO · infraestructura (API externas)
    │   ├── geoapifyService.js     # tiles, places, reverse geocoding
    │   ├── parkingService.js      # lectura de parkings en Firestore
    │   ├── reservationService.js  # crear / listar / cancelar reservas
    │   └── authService.js         # registro, login, logout, reset contraseña
    ├── controllers/           # CONTROLADOR · orquestación (hooks/contexto)
    │   ├── AuthContext.jsx        # sesión global
    │   ├── useParkings.js         # carga + fusión Geoapify/Firestore + orden
    │   ├── useGeolocation.js      # ubicación del usuario
    │   └── useReservations.js     # reservas del usuario
    ├── views/
    │   ├── layout/            # Header, Footer, Layout
    │   ├── components/        # ParkingRail, ParkingMap, ParkingCard,
    │   │                      # ReservationModal, RatingStars, ProtectedRoute…
    │   └── pages/             # Landing, Explorar, Login, Registro, Mis reservas
    └── styles/                # global, layout, landing, auth, explore, reservations
```

## 3. Puesta en marcha

```bash
cd app
npm install
cp .env.example .env      # ya incluidas las claves del proyecto
FIREBASE_SERVICE_ACCOUNT=/ruta/al/serviceAccount.json npm run seed   # 14 parkings de Tarragona
npm run dev               # http://localhost:5173
```

### 3.1 Reglas de Firestore (obligatorio)

La base de datos está **cerrada por defecto**: sin publicar las reglas, el cliente
recibe `permission-denied`.

1. Abre **Firebase Console → Firestore Database → Reglas**.
2. Pega el contenido de `app/firestore.rules`.
3. Pulsa **Publicar** (tarda 1–2 min en propagarse).

Reglas resumidas:

- `parkings`: lectura pública, escritura solo con Admin SDK.
- `users`: cada usuario solo ve/edita su perfil.
- `reservations`: cada usuario solo ve/crea/modifica las suyas; **nunca se borran**
  (solo cambian de estado), cumpliendo el requisito del Sprint 1.

### 3.2 Cuenta demo (opcional)

Durante las pruebas se creó el usuario:

```
demo@tarracoparkings.cat · Demo1234
```

Puedes borrarlo desde Firebase Console → Authentication → Users.

## 4. Funcionalidad

### Landing page
Hero con CTA, estadísticas en vivo (nº de parkings y valoración media desde
Firestore), ventajas, «cómo funciona», parkings destacados (top valorados),
tarifas, FAQ y banda de CTA. Header sticky y footer completo.

### Explorar (`/explorar`)
- **Rail izquierdo (antes del mapa)**: parkings cercanos ordenados por **mejor
  valoración** (con desempate por reseñas y distancia). Cada tarjeta muestra
  estrellas, distancia desde tu posición, precio/hora, plazas libres y
  servicios. Orden alternativo por distancia con un clic.
- **Mapa**: Leaflet con tiles de Geoapify, marcadores codificados por color
  (verde = ≥4,5★, azul = reservable, gris = POI informativo de Geoapify),
  marcador de tu posición y panel de detalle con «Reservar plaza».
- **Fusión de datos**: parkings reservables de Firestore + POIs reales de
  Geoapify cercanos (deduplicados a 120 m). Los POIs sin catálogo se marcan
  «No reservable».

### Reserva (`ReservationModal`)
Formulario con: **tipo de coche**, **matrícula** (validación de formato español),
marca/modelo, **titular** (nombre, apellidos, teléfono), fecha, hora de entrada y
salida, preferencias (recarga eléctrica / plaza PMR), comentarios, método de pago
(simulado) y aceptación de condiciones. Resumen de precio en vivo:

- 1ª y 2ª hora → 1,90 € · a partir de la 3ª → 0,20 €
- Nocturna (22:00–06:00) → 0,60 € · máximo diario → 4,00 €
- +10 % de gastos de gestión
- Cada parking puede llevar sus propias tarifas (`parking.rates`)

Al confirmar se genera el número `TPK-AAAAMMDD-XXXXXX`, se guarda la reserva como
`confirmada` y se ofrece comprobante imprimible (`window.print()`).

### Mis reservas (`/reservas`, ruta protegida)
Historial con filtros por estado, comprobante, y **cancelación** (cambia el estado
a `cancelada`; nunca se elimina el documento).

## 5. Scripts disponibles

| Comando | Descripción |
|---|---|
| `npm run dev` | Servidor de desarrollo (puerto 5173) |
| `npm run build` | Build de producción en `dist/` |
| `npm run preview` | Sirve el build de producción |
| `npm run seed` | Puebla/actualiza los parkings en Firestore |

Variables de entorno (`.env`): `VITE_GEOAPIFY_API_KEY` y `VITE_FIREBASE_*`.

## 6. Despliegue (opcional)

```bash
npm run build
npx firebase-tools deploy --only hosting --project parking-csj
```

## 7. Notas de seguridad

- La clave de Geoapify va en `.env` (solo se expone en cliente, tiene restricción
  de dominio recomendable en la consola de Geoapify).
- El JSON de la **cuenta de servicio** de Firebase está en `.gitignore`
  (`*firebase-adminsdk*.json`, `serviceAccount*.json`): nunca debe subirse al repo.
- El seed necesita la ruta de esa credencial:
  `FIREBASE_SERVICE_ACCOUNT=/ruta/al.json npm run seed` (o déjala como
  `app/serviceAccount.json`).

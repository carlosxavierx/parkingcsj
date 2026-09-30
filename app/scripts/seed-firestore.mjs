/**
 * Seed de Firestore para Tarraco Parkings.
 *
 * Crea la colección `parkings` con los aparcamientos reales de Tarragona
 * (coords y direcciones obtenidas con la API de Geoapify) + valoraciones,
 * tarifas y plazas.
 *
 * Uso:
 *   FIREBASE_SERVICE_ACCOUNT=/ruta/serviceAccount.json npm run seed
 *
 * El JSON de la cuenta de servicio NUNCA se sube al repositorio:
 * se ignora en .gitignore (también puedes nombrarlo `app/serviceAccount.json`).
 */
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { initializeApp, cert, getApps } from 'firebase-admin/app';
import { getFirestore, Timestamp } from 'firebase-admin/firestore';

const localCopy = join(dirname(fileURLToPath(import.meta.url)), '..', 'serviceAccount.json');

const credentialsPath = process.env.FIREBASE_SERVICE_ACCOUNT ||
  (existsSync(localCopy) ? localCopy : null);

if (!credentialsPath) {
  console.error('Falta la cuenta de servicio de Firebase.');
  console.error(
    'Usa: FIREBASE_SERVICE_ACCOUNT=/ruta/al.json npm run seed\n' +
      '  o coloca el JSON como app/serviceAccount.json (está en .gitignore).'
  );
  process.exit(1);
}

const baseRates = {
  firstHoursCount: 2,
  firstHourPrice: 1.9,
  extraHourPrice: 0.2,
  dailyMax: 4.0,
  nightPrice: 0.6,
  nightFrom: 22,
  nightTo: 6,
  managementFee: 0.1,
};

const PARKINGS = [
  {
    id: 'placa-de-la-font',
    name: 'Aparcament Plaça de la Font',
    description:
      'Aparcamiento subterráneo en plena Part Alta, a un minuto andando de la Catedral y la Plaça de la Font.',
    address: 'Carreró de la Palma, 43003 Tarragona',
    postcode: '43003',
    lat: 41.1169814,
    lng: 1.2552893,
    type: 'underground',
    rating: 4.8,
    reviews: 214,
    spotsTotal: 320,
    spotsAvailable: 46,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['covered', 'cctv', 'pmr', 'barrier', 'ev', '24h'],
    openingHours: '24 h',
    phone: '+34 977 23 29 85',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'saavedra',
    name: 'Aparcament Saavedra',
    description:
      'Aparcamiento público en la Part Alta, ideal para visitar el casco antiguo sin coche.',
    address: 'Carrer de Josepa Massanés, 43002 Tarragona',
    postcode: '43002',
    lat: 41.1186088,
    lng: 1.2538088,
    type: 'surface',
    rating: 4.6,
    reviews: 168,
    spotsTotal: 140,
    spotsAvailable: 23,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['cctv', 'pmr', 'barrier'],
    openingHours: '06:00 – 01:00',
    phone: '',
    website: 'https://aparcamentstgn.com/es/aparcamiento-saavedra/',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'la-pedrera',
    name: 'Aparcament la Pedrera',
    description:
      'Aparejado de varios niveles junto al barrio de la Pedrera, con acceso directo al centro.',
    address: 'Carrer Pons d’Icart, 43004 Tarragona',
    postcode: '43004',
    lat: 41.1132065,
    lng: 1.2530971,
    type: 'multistorey',
    rating: 4.5,
    reviews: 143,
    spotsTotal: 410,
    spotsAvailable: 87,
    pricePerHour: 2.2,
    rates: { ...baseRates, firstHourPrice: 2.2, dailyMax: 12.0 },
    amenities: ['covered', 'cctv', 'pmr', 'ev', 'elevator', 'barrier'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'tabacalera',
    name: 'Aparcament Municipal Tabacalera',
    description:
      'El aparcamiento municipal más céntrico de Tarragona: Rambla Nova, estación y centro comercial a un paso.',
    address: 'Passeig de la Independència, 43005 Tarragona',
    postcode: '43005',
    lat: 41.1140114,
    lng: 1.2380653,
    type: 'surface',
    rating: 4.7,
    reviews: 302,
    spotsTotal: 260,
    spotsAvailable: 34,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['cctv', 'pmr', 'barrier', '24h'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'torroja',
    name: 'Aparcament Torroja',
    description:
      'Junto a la estación de RENFE y el Passeig de Torroja: perfecto para dejarte el coche y moverte en tren.',
    address: 'Passeig de Torroja, 43003 Tarragona',
    postcode: '43003',
    lat: 41.1207737,
    lng: 1.2621042,
    type: 'surface',
    rating: 4.4,
    reviews: 187,
    spotsTotal: 190,
    spotsAvailable: 51,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['cctv', 'pmr', 'barrier'],
    openingHours: '06:00 – 24:00',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'nou-estadi',
    name: 'Disuasorio Nou Estadi',
    description:
      'Gran superficie disuasoria junto al Nou Estadi del Nàstic: ideal para partidos y eventos.',
    address: 'Camí del Nàstic, 43007 Tarragona',
    postcode: '43007',
    lat: 41.1245038,
    lng: 1.2730959,
    type: 'surface',
    rating: 4.5,
    reviews: 129,
    spotsTotal: 520,
    spotsAvailable: 213,
    pricePerHour: 1.5,
    rates: { ...baseRates, firstHourPrice: 1.5, dailyMax: 3.5 },
    amenities: ['cctv', 'pmr'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'bastos',
    name: 'Aparcament Bastos',
    description:
      'Aparcamiento de barrio entre el centre i el barrio de la Floresta, con tarifa económica.',
    address: 'Carrer Cardenal Vidal i Barraquer, 43005 Tarragona',
    postcode: '43005',
    lat: 41.1148116,
    lng: 1.2414972,
    type: 'surface',
    rating: 4.3,
    reviews: 96,
    spotsTotal: 120,
    spotsAvailable: 19,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['cctv', 'motorcycle'],
    openingHours: '07:00 – 23:00',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'guillem-oliver',
    name: 'Disuasorio Guillem Oliver',
    description:
      'Aparcamiento disuasorio al norte de la ciudad, con excelente conexión a la Rambla.',
    address: 'Carrer de Guillem Oliver, 43005 Tarragona',
    postcode: '43005',
    lat: 41.1271725,
    lng: 1.2376477,
    type: 'surface',
    rating: 4.3,
    reviews: 105,
    spotsTotal: 340,
    spotsAvailable: 128,
    pricePerHour: 1.5,
    rates: { ...baseRates, firstHourPrice: 1.5, dailyMax: 3.5 },
    amenities: ['cctv', 'pmr', 'barrier'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'pere-martell',
    name: 'Pàrquing Pere Martell 46',
    description:
      'Aparcamiento junto a la Facultad de Ciències i l’Escola de la Ingeniería de Tarragona.',
    address: 'Carrer Pere Martell, 43005 Tarragona',
    postcode: '43005',
    lat: 41.1136258,
    lng: 1.243876,
    type: 'surface',
    rating: 4.1,
    reviews: 88,
    spotsTotal: 95,
    spotsAvailable: 12,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['cctv', 'pmr', 'ev'],
    openingHours: '06:00 – 22:00',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'urv-catalunya',
    name: 'URV Campus Catalunya',
    description:
      'Aparcamiento del campus universitario de la URV, con plazas reservables durante el curso.',
    address: 'Autovia de la Mediterrània, 43002 Tarragona',
    postcode: '43002',
    lat: 41.1233759,
    lng: 1.2503356,
    type: 'surface',
    rating: 4.2,
    reviews: 74,
    spotsTotal: 160,
    spotsAvailable: 44,
    pricePerHour: 1.6,
    rates: { ...baseRates, firstHourPrice: 1.6, dailyMax: 4.0 },
    amenities: ['cctv', 'pmr'],
    openingHours: '07:00 – 22:00',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'port-tarraco',
    name: 'Parking Port Tarraco',
    description:
      'Zona portuaria: acceso directo al Moll de Costa, restaurants i al passeig marítim.',
    address: 'Carrer Dic de Llevant, 43004 Tarragona',
    postcode: '43004',
    lat: 41.1078327,
    lng: 1.2490012,
    type: 'surface',
    rating: 4.2,
    reviews: 112,
    spotsTotal: 230,
    spotsAvailable: 67,
    pricePerHour: 2.5,
    rates: { ...baseRates, firstHourPrice: 2.5, dailyMax: 15.0 },
    amenities: ['cctv', 'pmr', 'ev', 'barrier'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'joan-serra',
    name: 'Aparcament Joan Serra Vilaró',
    description:
      'Superficie amplia a la entrada de Tarragona por la autopista, buena opción para estancias largas.',
    address: 'Avinguda dels Països Catalans, 43002 Tarragona',
    postcode: '43002',
    lat: 41.1290729,
    lng: 1.2432724,
    type: 'surface',
    rating: 4.0,
    reviews: 61,
    spotsTotal: 210,
    spotsAvailable: 92,
    pricePerHour: 1.5,
    rates: { ...baseRates, firstHourPrice: 1.5, dailyMax: 3.5 },
    amenities: ['cctv', 'motorcycle'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'aqueducte',
    name: 'Aparcament de l’Aqüeducte',
    description:
      'Junto al Parc del Pont del Diable y el acueducto romano: ideal para rutas a pie y bici.',
    address: 'Camí del Parc del Pont del Diable, 43130 Tarragona',
    postcode: '43130',
    lat: 41.1469645,
    lng: 1.240081,
    type: 'surface',
    rating: 4.0,
    reviews: 47,
    spotsTotal: 130,
    spotsAvailable: 74,
    pricePerHour: 1.5,
    rates: { ...baseRates, firstHourPrice: 1.5, dailyMax: 3.5 },
    amenities: ['cctv', 'ev'],
    openingHours: '24 h',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
  {
    id: 'savinosa',
    name: 'Aparcament Savinosa',
    description:
      'Aparcamiento de barrio a pocos metros de la platja de la Savinosa.',
    address: 'Carrer de Tomàs Forteza Segura, 43007 Tarragona',
    postcode: '43007',
    lat: 41.1229468,
    lng: 1.2822836,
    type: 'surface',
    rating: 3.9,
    reviews: 38,
    spotsTotal: 85,
    spotsAvailable: 31,
    pricePerHour: 1.9,
    rates: baseRates,
    amenities: ['motorcycle'],
    openingHours: '07:00 – 23:00',
    phone: '',
    website: '',
    reservable: true,
    active: true,
    source: 'firestore',
  },
];

async function main() {
  let json;
  try {
    json = JSON.parse(readFileSync(credentialsPath, 'utf8'));
  } catch (err) {
    console.error(`No se ha podido leer la cuenta de servicio: ${credentialsPath}`);
    console.error('Define la variable FIREBASE_SERVICE_ACCOUNT con la ruta del JSON.');
    process.exit(1);
  }

  if (getApps().length === 0) {
    initializeApp({ credential: cert(json), projectId: json.project_id || 'parking-csj' });
  }
  const db = getFirestore();

  const now = Timestamp.now();
  const batch = db.batch();

  PARKINGS.forEach((p) => {
    const ref = db.collection('parkings').doc(p.id);
    batch.set(ref, { ...p, updatedAt: now, createdAt: now }, { merge: true });
  });

  await batch.commit();

  console.log(`✔ ${PARKINGS.length} parkings escritos en Firestore (parking-csj).`);
  console.log('Colecciones: parkings (lectura pública), reservations (usuarios), users.');
}

main()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error('Error en el seed:', err.message);
    process.exit(1);
  });

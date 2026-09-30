const EARTH_RADIUS_M = 6371000;

export function haversineMeters([lat1, lon1], [lat2, lon2]) {
  const toRad = (v) => (v * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  return 2 * EARTH_RADIUS_M * Math.asin(Math.sqrt(a));
}

export function normalizeParking(doc, id) {
  const data = typeof doc.data === 'function' ? doc.data() : doc;
  return {
    id: id || data.id || doc.id,
    name: data.name || 'Aparcamiento sin nombre',
    description: data.description || '',
    address: data.address || '',
    city: data.city || 'Tarragona',
    postcode: data.postcode || '',
    lat: Number(data.lat),
    lng: Number(data.lng),
    type: data.type || 'surface',
    rating: typeof data.rating === 'number' ? data.rating : null,
    reviews: data.reviews || 0,
    spotsTotal: data.spotsTotal || 0,
    spotsAvailable: data.spotsAvailable ?? null,
    pricePerHour: data.pricePerHour ?? null,
    rates: data.rates || null,
    amenities: data.amenities || [],
    openingHours: data.openingHours || '24 h',
    phone: data.phone || '',
    website: data.website || '',
    reservable: data.reservable !== false,
    source: data.source || 'firestore',
    distance: null,
  };
}

export function withDistance(parking, userPosition) {
  if (!userPosition || parking.lat == null || Number.isNaN(parking.lat)) {
    return { ...parking, distance: null };
  }
  return {
    ...parking,
    distance: Math.round(
      haversineMeters([userPosition.lat, userPosition.lng], [parking.lat, parking.lng])
    ),
  };
}

export function formatDistance(meters) {
  if (meters == null) return '';
  if (meters < 1000) return `${meters} m`;
  return `${(meters / 1000).toFixed(1)} km`;
}

/**
 * Orden exigido por el cliente: primero los parkings MEJOR VALORADOS;
 * a igualdad de valoración, los más CERCANOS.
 */
export function sortByBestRating(parkings) {
  return [...parkings].sort((a, b) => {
    const ra = a.rating ?? -1;
    const rb = b.rating ?? -1;
    if (rb !== ra) return rb - ra;
    if (b.reviews !== a.reviews) return (b.reviews || 0) - (a.reviews || 0);
    const da = a.distance ?? Number.POSITIVE_INFINITY;
    const db = b.distance ?? Number.POSITIVE_INFINITY;
    return da - db;
  });
}

export function sortByDistance(parkings) {
  return [...parkings].sort((a, b) => {
    const da = a.distance ?? Number.POSITIVE_INFINITY;
    const db = b.distance ?? Number.POSITIVE_INFINITY;
    return da - db;
  });
}

/**
 * Fusiona los aparcamientos reservables (Firestore) con los POIs que devuelve
 * la API de Geoapify. Si un POI está a menos de 120 m de un parking ya
 * conocido, se considera el mismo y se descarta (evita duplicados en el rail
 * y en el mapa).
 */
export function mergeParkings(firestoreParkings, geoapifyPois) {
  const merged = [...firestoreParkings];
  for (const poi of geoapifyPois) {
    const duplicated = merged.some(
      (p) =>
        p.lat != null &&
        poi.lat != null &&
        haversineMeters([p.lat, p.lng], [poi.lat, poi.lng]) < 120
    );
    if (!duplicated) merged.push(poi);
  }
  return merged;
}

export const AMENITY_LABELS = {
  ev: 'Punto de recarga',
  pmr: 'Plaza PMR',
  cctv: 'Videovigilancia',
  covered: 'Aparcamiento cubierto',
  '24h': 'Abierto 24 h',
  elevator: 'Ascensor',
  motorcycle: 'Motos',
  barrier: 'Barrera con matrícula',
};

export const TYPE_LABELS = {
  underground: 'Subterráneo',
  surface: 'Aire libre',
  multistorey: 'Aparejado',
  street: 'En calle',
};

const API_KEY = import.meta.env.VITE_GEOAPIFY_API_KEY;
const BASE = 'https://api.geoapify.com';

/**
 * Plantilla de tiles para Leaflet (contiene los placeholders {z}/{x}/{y}).
 */
export function tileUrlTemplate({ dark = false } = {}) {
  const style = dark ? 'osm-bright-dark' : 'osm-bright';
  return `${BASE}/v1/tile/${style}/{z}/{x}/{y}.png?apiKey=${API_KEY}`;
}

export const MAP_ATTRIBUTION =
  '&copy; <a href="https://www.geoapify.com/">Geoapify</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * Busca aparcamientos reales cerca de una posición con la API de Places de
 * Geoapify (v2). Devuelve POIs normalizados listos para el rail y el mapa.
 */
export async function fetchNearbyParkings({ lat, lng, radius = 3000, limit = 24 }) {
  const url =
    `${BASE}/v2/places?categories=parking` +
    `&filter=circle:${lng},${lat},${radius}&limit=${limit}&apiKey=${API_KEY}`;

  const res = await fetch(url);
  if (!res.ok) throw new Error('Geoapify: no se pudieron cargar los aparcamientos.');
  const json = await res.json();

  return (json.features || []).map((feature, index) => {
    const p = feature.properties || {};
    const raw = p.datasource?.raw || {};
    const type =
      raw.parking === 'underground'
        ? 'underground'
        : raw.parking === 'multistorey'
          ? 'multistorey'
          : 'surface';

    return {
      id: `geo-${p.place_id || index}`,
      name: p.name || 'Aparcamiento',
      description: '',
      address: [p.street, p.postcode, p.city].filter(Boolean).join(', '),
      city: p.city || 'Tarragona',
      postcode: p.postcode || '',
      lat: p.lat,
      lng: p.lon,
      type,
      rating: null,
      reviews: 0,
      spotsTotal: null,
      spotsAvailable: null,
      pricePerHour: null,
      rates: null,
      amenities: [],
      openingHours: raw.opening_hours || 'Consultar',
      phone: p.contact?.phone || raw.phone || '',
      website: p.website || '',
      reservable: false,
      source: 'geoapify',
      distance: null,
    };
  });
}

export async function reverseGeocode({ lat, lng }) {
  const url = `${BASE}/v1/geocode/reverse?lat=${lat}&lon=${lng}&apiKey=${API_KEY}`;
  try {
    const res = await fetch(url);
    if (!res.ok) return null;
    const json = await res.json();
    const props = json.features?.[0]?.properties || {};
    return [props.street, props.city, props.postcode].filter(Boolean).join(', ');
  } catch {
    return null;
  }
}

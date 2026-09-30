import { useCallback, useEffect, useState } from 'react';
import { getParkings } from '../services/parkingService.js';
import {
  mergeParkings,
  normalizeParking,
  sortByBestRating,
  withDistance,
} from '../models/parkingModel.js';
import { fetchNearbyParkings } from '../services/geoapifyService.js';

/**
 * Carga los parkings de Firestore (reservables), los enriquece con los POIs
 * reales de Geoapify según la posición del usuario y devuelve el listado
 * ordenado por MEJOR VALORACIÓN (y después por cercanía).
 */
export function useParkings({ position = null, includeGeoapify = true } = {}) {
  const [parkings, setParkings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [dbResult, geoResult] = await Promise.allSettled([
        getParkings(),
        includeGeoapify && position
          ? fetchNearbyParkings({
              lat: position.lat,
              lng: position.lng,
              radius: 4000,
            })
          : Promise.resolve([]),
      ]);

      const dbParkings =
        dbResult.status === 'fulfilled' ? dbResult.value : [];
      const geoPois =
        geoResult.status === 'fulfilled' ? (geoResult.value || []) : [];

      if (dbResult.status === 'rejected') {
        console.error('Firestore:', dbResult.reason?.code, dbResult.reason?.message);
        setError(
          dbParkings.length === 0 && geoPois.length === 0
            ? 'No se han podido cargar los aparcamientos. Comprueba tu conexión.'
            : 'Catálogo de Firestore no disponible: mostrando aparcamientos detectados con Geoapify.'
        );
      } else if (geoResult.status === 'rejected') {
        console.error('Geoapify:', geoResult.reason?.message);
      }

      const geo = geoPois.map((p) => normalizeParking(p, p.id));
      const merged = mergeParkings(dbParkings, geo);
      const withPos = merged.map((p) => withDistance(p, position));
      setParkings(sortByBestRating(withPos));
    } catch (e) {
      console.error('No se han podido cargar los parkings:', e?.code, e?.message);
      setError(
        'No se han podido cargar los aparcamientos. Comprueba tu conexión.'
      );
      setParkings([]);
    } finally {
      setLoading(false);
    }
  }, [position?.lat, position?.lng, includeGeoapify]);

  useEffect(() => {
    load();
  }, [load]);

  return { parkings, loading, error, reload: load };
}

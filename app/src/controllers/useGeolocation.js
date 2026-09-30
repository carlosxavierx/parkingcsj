import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_POSITION = { lat: 41.1189, lng: 1.2445, isDefault: true };

/**
 * Geolocalización del usuario. Si el navegador la deniega, se usa como
 * valor por defecto el centro de Tarragona para no dejar la app vacía.
 */
export function useGeolocation({ auto = true } = {}) {
  const [position, setPosition] = useState(null);
  const [accuracy, setAccuracy] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [permission, setPermission] = useState('unknown');
  const watchId = useRef(null);

  const locate = useCallback((fallbackToDefault = true) => {
    if (!('geolocation' in navigator)) {
      setError('Tu navegador no soporta geolocalización.');
      if (fallbackToDefault) setPosition(DEFAULT_POSITION);
      return;
    }
    setLoading(true);
    setError(null);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setPosition({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          isDefault: false,
        });
        setAccuracy(pos.coords.accuracy);
        setPermission('granted');
        setLoading(false);
      },
      (err) => {
        setPermission(err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable');
        setError(
          err.code === err.PERMISSION_DENIED
            ? 'Has bloqueado la ubicación: mostrando Tarragona como ubicación por defecto.'
            : 'No se ha podido obtener tu ubicación.'
        );
        if (fallbackToDefault) setPosition(DEFAULT_POSITION);
        setLoading(false);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  }, []);

  useEffect(() => {
    if (auto) locate(true);
    return () => {
      if (watchId.current != null) navigator.geolocation.clearWatch(watchId.current);
    };
  }, [auto, locate]);

  return {
    position: position || (auto ? DEFAULT_POSITION : null),
    accuracy,
    loading,
    error,
    permission,
    isDefault: !!position?.isDefault,
    locate,
  };
}

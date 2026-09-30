import { useCallback, useEffect, useState } from 'react';
import {
  cancelReservation as cancelService,
  getMyReservations,
} from '../services/reservationService.js';

export function useReservations(userId) {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    if (!userId) {
      setReservations([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const data = await getMyReservations(userId);
      setReservations(data);
    } catch {
      setError('No se han podido cargar tus reservas.');
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    load();
  }, [load]);

  const cancel = useCallback(
    async (id) => {
      await cancelService(id);
      setReservations((prev) =>
        prev.map((r) => (r.id === id ? { ...r, status: 'cancelada' } : r))
      );
    },
    []
  );

  return { reservations, loading, error, reload: load, cancel };
}

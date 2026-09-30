import { useCallback, useEffect, useState } from 'react';
import ParkingRail from '../components/ParkingRail.jsx';
import ParkingMap from '../components/ParkingMap.jsx';
import ReservationModal from '../components/ReservationModal.jsx';
import { useGeolocation } from '../../controllers/useGeolocation.js';
import { useParkings } from '../../controllers/useParkings.js';
import { reverseGeocode } from '../../services/geoapifyService.js';

export default function ExplorePage() {
  const { position, loading: locating, locate, error: geoError } = useGeolocation();
  const { parkings, loading, error } = useParkings({ position });
  const [selected, setSelected] = useState(null);
  const [reserveTarget, setReserveTarget] = useState(null);
  const [address, setAddress] = useState('');

  useEffect(() => {
    let cancelled = false;
    if (position && !position.isDefault) {
      reverseGeocode(position).then((result) => {
        if (!cancelled) setAddress(result || '');
      });
    } else {
      setAddress('Tarragona (ubicación por defecto)');
    }
    return () => {
      cancelled = true;
    };
  }, [position?.lat, position?.lng, position?.isDefault]);

  const handleRelocate = useCallback(() => {
    locate(true);
  }, [locate]);

  return (
    <section className="explore-page">
      <div className="explore-head container">
        <div>
          <span className="eyebrow">Mapa de aparcamientos</span>
          <h1>Encuentra tu plaza en Tarragona</h1>
          <p className="muted">
            Compara los parkings mejor valorados junto al mapa y reserva tu plaza en
            segundos.
          </p>
        </div>
        <div className="explore-badges">
          <span className="pill">
            <i className="dot dot-blue" /> {parkings.length} parkings
          </span>
          <span className="pill">
            Datos: Geoapify + Firebase
          </span>
        </div>
      </div>

      <div className="explore-layout container">
        <ParkingRail
          parkings={parkings}
          loading={loading || locating}
          error={error || geoError}
          selected={selected}
          onSelect={setSelected}
          onReserve={setReserveTarget}
          onRelocate={handleRelocate}
          locating={locating}
          positionLabel={address}
        />

        <ParkingMap
          parkings={parkings}
          position={position}
          selected={selected}
          onSelect={setSelected}
          onReserve={setReserveTarget}
        />
      </div>

      {reserveTarget && (
        <ReservationModal
          parking={reserveTarget}
          onClose={() => setReserveTarget(null)}
        />
      )}
    </section>
  );
}

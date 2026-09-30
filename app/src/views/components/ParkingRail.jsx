import { useMemo, useState } from 'react';
import ParkingCard from './ParkingCard.jsx';
import Spinner from './Spinner.jsx';
import { sortByBestRating, sortByDistance } from '../../models/parkingModel.js';
import { IconStar, IconNav } from './icons.jsx';

/**
 * Rail lateral (va ANTES del mapa en el orden de lectura) con los parkings
 * cercanos, ordenados por mejor valoración por defecto.
 */
export default function ParkingRail({
  parkings,
  loading,
  error,
  selected,
  onSelect,
  onReserve,
  onRelocate,
  locating,
  positionLabel,
}) {
  const [sort, setSort] = useState('rating');

  const sorted = useMemo(
    () => (sort === 'rating' ? sortByBestRating(parkings) : sortByDistance(parkings)),
    [parkings, sort]
  );

  return (
    <aside className="parking-rail">
      <div className="rail-head">
        <div>
          <h2>Mejor valorados cerca de ti</h2>
          <p className="rail-subtitle">
            {loading
              ? 'Buscando aparcamientos…'
              : `${sorted.length} aparcamientos encontrados`}
          </p>
        </div>
        <button
          type="button"
          className="btn btn-outline btn-sm"
          onClick={onRelocate}
          disabled={locating}
        >
          <IconNav width={15} height={15} />
          {locating ? 'Localizando…' : 'Mi ubicación'}
        </button>
      </div>

      {positionLabel && <p className="rail-position">{positionLabel}</p>}

      <div className="rail-toolbar">
        <div className="sort-group">
          <span>Ordenar por</span>
          <button
            type="button"
            className={`sort-chip ${sort === 'rating' ? 'active' : ''}`}
            onClick={() => setSort('rating')}
          >
            <IconStar width={13} height={13} /> Mejor valoración
          </button>
          <button
            type="button"
            className={`sort-chip ${sort === 'distance' ? 'active' : ''}`}
            onClick={() => setSort('distance')}
          >
            <IconNav width={13} height={13} /> Más cercano
          </button>
        </div>
      </div>

      <div className="rail-scroll">
        {loading && <Spinner label="Cargando parkings…" />}

        {!loading && error && <div className="alert alert-danger">{error}</div>}

        {!loading && !error && sorted.length === 0 && (
          <div className="empty-state">
            <h3>Sin resultados</h3>
            <p>No hay aparcamientos cerca. Prueba a ampliar la búsqueda.</p>
          </div>
        )}

        {!loading &&
          sorted.map((parking, i) => (
            <ParkingCard
              key={parking.id}
              parking={parking}
              index={i}
              showRank
              selected={selected?.id === parking.id}
              onSelect={onSelect}
              onReserve={onReserve}
            />
          ))}
      </div>
    </aside>
  );
}

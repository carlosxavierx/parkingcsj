import RatingStars from './RatingStars.jsx';
import { formatDistance, AMENITY_LABELS, TYPE_LABELS } from '../../models/parkingModel.js';
import { formatMoney } from '../../models/pricingModel.js';
import { IconPin, IconClock, IconBolt, IconArrow } from './icons.jsx';

export default function ParkingCard({
  parking,
  index = null,
  selected = false,
  onSelect,
  onReserve,
  showRank = false,
  compact = false,
}) {
  const available = parking.spotsAvailable;
  const low = typeof available === 'number' && available <= 10 && available > 0;

  return (
    <article
      className={`parking-card ${selected ? 'is-selected' : ''} ${compact ? 'compact' : ''}`}
      onClick={() => onSelect?.(parking)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelect?.(parking)}
    >
      <header className="parking-card-top">
        {showRank && <span className="rank-badge">#{index + 1}</span>}
        <div className="parking-card-heading">
          <h3>{parking.name}</h3>
          <span className="type-pill">{TYPE_LABELS[parking.type] || parking.type}</span>
        </div>
        <span className={`source-pill ${parking.source}`}>
          {parking.source === 'geoapify' ? 'Geoapify' : 'Reservable'}
        </span>
      </header>

      <div className="parking-card-meta">
        <RatingStars value={parking.rating || 0} reviews={parking.reviews} size="sm" />
        {parking.distance != null && (
          <span className="meta-item distance">
            <IconPin width={14} height={14} /> {formatDistance(parking.distance)}
          </span>
        )}
      </div>

      <p className="parking-card-address">
        {parking.address || 'Sin dirección'}
      </p>

      <div className="parking-card-facts">
        <span className="fact">
          <IconClock width={14} height={14} /> {parking.openingHours}
        </span>
        {parking.pricePerHour != null && (
          <span className="fact price">
            desde <strong>{formatMoney(parking.pricePerHour)}</strong>/h
          </span>
        )}
        {typeof available === 'number' && (
          <span className={`fact spots ${low ? 'low' : ''}`}>
            {available} plazas libres
          </span>
        )}
        {parking.amenities.includes('ev') && (
          <span className="fact badge-amenity" title="Punto de recarga">
            <IconBolt width={14} height={14} />
          </span>
        )}
      </div>

      {!compact && parking.amenities.length > 0 && (
        <ul className="amenity-chips">
          {parking.amenities.slice(0, 3).map((a) => (
            <li key={a}>{AMENITY_LABELS[a] || a}</li>
          ))}
        </ul>
      )}

      <footer className="parking-card-actions" onClick={(e) => e.stopPropagation()}>
        <button type="button" className="btn btn-outline btn-sm" onClick={() => onSelect?.(parking)}>
          Ver en el mapa <IconArrow width={15} height={15} />
        </button>
        <button
          type="button"
          className="btn btn-primary btn-sm"
          disabled={!parking.reservable}
          onClick={() => onReserve?.(parking)}
          title={
            parking.reservable
              ? 'Reservar plaza'
              : 'Este aparcamiento no admite reserva online'
          }
        >
          {parking.reservable ? 'Reservar' : 'No reservable'}
        </button>
      </footer>
    </article>
  );
}

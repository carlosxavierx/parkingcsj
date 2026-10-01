import { useEffect, useMemo, useRef } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import L from 'leaflet';
import { tileUrlTemplate, MAP_ATTRIBUTION } from '../../services/geoapifyService.js';
import { formatDistance } from '../../models/parkingModel.js';
import RatingStars from './RatingStars.jsx';
import { formatMoney } from '../../models/pricingModel.js';

const DEFAULT_CENTER = [41.1189, 1.2445];

const COLORS = {
  reserved: '#ffc400', // reservable
  top: '#3ddc84', // mejor valorados
  info: '#7a7a88', // POI informativo
  selected: '#ff7a1a', // seleccionado
};

function parkingIcon(parking, isSelected) {
  const color = !parking.reservable
    ? COLORS.info
    : isSelected
      ? COLORS.selected
      : parking.rating && parking.rating >= 4.5
        ? COLORS.top
        : COLORS.reserved;

  return L.divIcon({
    className: 'parking-marker-wrap',
    html: `<div class="parking-marker ${isSelected ? 'is-selected' : ''}" style="--marker-color:${color}">
        <span>P</span>
      </div>`,
    iconSize: [34, 40],
    iconAnchor: [17, 38],
    popupAnchor: [0, -34],
  });
}

const userIcon = L.divIcon({
  className: 'parking-marker-wrap',
  html: '<div class="user-marker"><span></span></div>',
  iconSize: [22, 22],
  iconAnchor: [11, 11],
});

function MapController({ center, parkings, selected }) {
  const map = useMap();
  const firstRun = useRef(true);

  useEffect(() => {
    if (center) {
      map.flyTo([center.lat, center.lng], map.getZoom() || 14, { duration: 0.8 });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [center?.lat, center?.lng]);

  useEffect(() => {
    if (selected?.lat != null && selected?.lng != null) {
      map.flyTo([selected.lat, selected.lng], Math.max(map.getZoom(), 15), {
        duration: 0.7,
      });
    } else if (firstRun.current && parkings.length > 0) {
      firstRun.current = false;
      const bounds = L.latLngBounds(
        parkings.filter((p) => p.lat != null).map((p) => [p.lat, p.lng])
      );
      if (center) bounds.extend([center.lat, center.lng]);
      if (bounds.isValid()) map.fitBounds(bounds.pad(0.25), { maxZoom: 15 });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected?.id, parkings.length]);

  return null;
}

export default function ParkingMap({ parkings, position, selected, onSelect, onReserve }) {
  const center = useMemo(
    () => (position ? [position.lat, position.lng] : DEFAULT_CENTER),
    [position?.lat, position?.lng]
  );

  return (
    <div className="parking-map">
      <MapContainer
        center={center}
        zoom={14}
        className="map-container"
        scrollWheelZoom
        zoomControl
      >
        <TileLayer
          attribution={MAP_ATTRIBUTION}
          url={tileUrlTemplate()}
          maxZoom={20}
        />

        <MapController center={position} parkings={parkings} selected={selected} />

        {position && (
          <Marker position={[position.lat, position.lng]} icon={userIcon}>
            <Popup>
              <strong>Tu ubicación</strong>
              {position.isDefault && (
                <>
                  <br />
                  <em>Ubicación por defecto: Tarragona</em>
                </>
              )}
            </Popup>
          </Marker>
        )}

        {parkings
          .filter((p) => p.lat != null && p.lng != null && !Number.isNaN(p.lat))
          .map((p) => (
            <Marker
              key={p.id}
              position={[p.lat, p.lng]}
              icon={parkingIcon(p, selected?.id === p.id)}
              eventHandlers={{ click: () => onSelect(p) }}
            >
              <Popup>
                <strong>{p.name}</strong>
                <br />
                {p.address}
                <br />
                {p.rating ? (
                  <span>★ {p.rating.toFixed(1)} ({p.reviews} opiniones)</span>
                ) : (
                  <span>Sin valoraciones</span>
                )}
                {p.distance != null && <> · {formatDistance(p.distance)}</>}
                {p.pricePerHour != null && (
                  <>
                    <br />
                    <span>desde {formatMoney(p.pricePerHour)}/h</span>
                  </>
                )}
              </Popup>
            </Marker>
          ))}
      </MapContainer>

      <div className="map-legend">
        <span><i className="dot dot-green" /> Mejor valorados</span>
        <span><i className="dot dot-blue" /> Reservables</span>
        <span><i className="dot dot-gray" /> Informativos (Geoapify)</span>
      </div>

      {selected && (
        <div className="map-detail">
          <div className="map-detail-head">
            <div>
              <h3>{selected.name}</h3>
              <RatingStars value={selected.rating || 0} reviews={selected.reviews} size="sm" />
            </div>
            <button
              type="button"
              className="icon-btn"
              onClick={() => onSelect(null)}
              aria-label="Cerrar detalle"
            >
              ✕
            </button>
          </div>
          <p className="map-detail-address">{selected.address}</p>
          <div className="map-detail-facts">
            {selected.openingHours && <span>{selected.openingHours}</span>}
            {selected.pricePerHour != null && (
              <span>desde {formatMoney(selected.pricePerHour)}/h</span>
            )}
            {selected.spotsAvailable != null && (
              <span>{selected.spotsAvailable} plazas libres</span>
            )}
            {selected.distance != null && <span>{formatDistance(selected.distance)}</span>}
          </div>
          {selected.description && (
            <p className="map-detail-description">{selected.description}</p>
          )}
          <div className="map-detail-actions">
            <a
              className="btn btn-ghost btn-sm"
              href={`https://www.openstreetmap.org/?mlat=${selected.lat}&mlon=${selected.lng}#map=17/${selected.lat}/${selected.lng}`}
              target="_blank"
              rel="noreferrer"
            >
              Cómo llegar
            </a>
            <button
              type="button"
              className="btn btn-primary btn-sm"
              disabled={!selected.reservable}
              onClick={() => onReserve?.(selected)}
            >
              {selected.reservable ? 'Reservar plaza' : 'No reservable online'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

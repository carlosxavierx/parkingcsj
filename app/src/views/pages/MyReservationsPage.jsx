import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../controllers/AuthContext.jsx';
import { useReservations } from '../../controllers/useReservations.js';
import { RESERVATION_STATUSES } from '../../models/reservationModel.js';
import { formatMoney } from '../../models/pricingModel.js';
import Spinner from '../components/Spinner.jsx';
import { IconPin, IconClock, IconCar, IconCheck, IconCalendar } from '../components/icons.jsx';

export default function MyReservationsPage() {
  const { user, profile } = useAuth();
  const { reservations, loading, error, cancel } = useReservations(user?.uid);
  const [pendingId, setPendingId] = useState(null);
  const [filter, setFilter] = useState('todas');

  const handleCancel = async (id) => {
    if (!window.confirm('¿Seguro que quieres cancelar esta reserva?')) return;
    setPendingId(id);
    try {
      await cancel(id);
    } finally {
      setPendingId(null);
    }
  };

  const visible = reservations.filter((r) =>
    filter === 'todas' ? true : r.status === filter
  );

  return (
    <section className="reservations-page container">
      <header className="page-head">
        <div>
          <span className="eyebrow">Mi cuenta</span>
          <h1>Mis reservas</h1>
          <p className="muted">
            Hola {profile?.name || user?.displayName || user?.email}. Aquí tienes el
            historial de tus aparcamientos.
          </p>
        </div>
        <Link to="/explorar" className="btn btn-primary">
          Nueva reserva
        </Link>
      </header>

      <div className="filter-row">
        {['todas', 'confirmada', 'pendiente', 'cancelada', 'completada'].map((key) => (
          <button
            key={key}
            type="button"
            className={`sort-chip ${filter === key ? 'active' : ''}`}
            onClick={() => setFilter(key)}
          >
            {key === 'todas' ? 'Todas' : RESERVATION_STATUSES[key]?.label || key}
          </button>
        ))}
      </div>

      {loading && <Spinner label="Cargando tus reservas…" />}
      {error && <div className="alert alert-danger">{error}</div>}

      {!loading && !error && visible.length === 0 && (
        <div className="empty-state">
          <IconCheck width={34} height={34} />
          <h3>Todavía no tienes reservas</h3>
          <p>Explora el mapa y reserva tu primera plaza en menos de un minuto.</p>
          <Link to="/explorar" className="btn btn-primary">
            Explorar parkings
          </Link>
        </div>
      )}

      <div className="reservation-list">
        {visible.map((r) => {
          const status = RESERVATION_STATUSES[r.status] || {
            label: r.status,
            tone: 'neutral',
          };
          return (
            <article key={r.id} className="reservation-card">
              <div className="reservation-card-main">
                <div className="reservation-topline">
                  <span className="reservation-number">{r.number}</span>
                  <span className={`status-badge tone-${status.tone}`}>{status.label}</span>
                </div>
                <h3>{r.parkingName}</h3>
                <ul className="reservation-meta">
                  <li>
                    <IconPin width={14} height={14} /> {r.parkingAddress}
                  </li>
                  <li>
                    <IconCalendar width={14} height={14} /> {r.date} · {r.entryTime} –{' '}
                    {r.exitTime} ({r.hours} h)
                  </li>
                  <li>
                    <IconCar width={14} height={14} /> {r.vehicleTypeLabel} ·{' '}
                    <strong>{r.plate}</strong>
                  </li>
                  <li>
                    <IconClock width={14} height={14} /> Titular: {r.holderName}
                  </li>
                </ul>
              </div>

              <div className="reservation-card-side">
                <span className="reservation-total">
                  {formatMoney(r.totalPrice)}
                  <small>IVA incl.</small>
                </span>
                {r.status === 'confirmada' && (
                  <button
                    type="button"
                    className="btn btn-outline btn-sm"
                    onClick={() => window.print()}
                  >
                    Comprobante
                  </button>
                )}
                {(r.status === 'confirmada' || r.status === 'pendiente') && (
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm danger"
                    disabled={pendingId === r.id}
                    onClick={() => handleCancel(r.id)}
                  >
                    {pendingId === r.id ? 'Cancelando…' : 'Cancelar reserva'}
                  </button>
                )}
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}

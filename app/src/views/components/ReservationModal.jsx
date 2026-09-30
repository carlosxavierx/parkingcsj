import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../controllers/AuthContext.jsx';
import {
  buildSchedule,
  defaultSchedule,
  EMPTY_RESERVATION_FORM,
  validateReservationForm,
  vehicleTypeLabel,
  VEHICLE_TYPES,
} from '../../models/reservationModel.js';
import { calculatePrice, formatMoney } from '../../models/pricingModel.js';
import { createReservation } from '../../services/reservationService.js';
import { firebaseAuthErrorMessage } from '../../services/authService.js';
import RatingStars from './RatingStars.jsx';
import Spinner from './Spinner.jsx';
import { IconCheck, IconClose, IconCar, IconCalendar, IconClock } from './icons.jsx';

const FIELD = (name) => ({ name });

export default function ReservationModal({ parking, onClose }) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState(() => ({
    ...EMPTY_RESERVATION_FORM,
    ...defaultSchedule(),
  }));
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [created, setCreated] = useState(null);

  const schedule = useMemo(() => buildSchedule(form), [form]);
  const price = useMemo(
    () =>
      schedule
        ? calculatePrice({
            start: schedule.start,
            end: schedule.end,
            rates: parking?.rates,
          })
        : { valid: false, hours: 0, base: 0, fees: 0, total: 0 },
    [schedule, parking?.rates]
  );

  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [onClose]);

  const set = (field) => (e) => {
    const value = e?.target?.type === 'checkbox' ? e.target.checked : e.target.value;
    setForm((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitError(null);

    const found = validateReservationForm(form, { user });
    setErrors(found);
    if (Object.keys(found).length > 0) return;

    if (!user) {
      navigate('/login', { state: { from: '/explorar', reserve: parking.id } });
      return;
    }

    setSubmitting(true);
    try {
      const reservation = await createReservation({
        user,
        parking,
        form,
        schedule,
        price,
      });
      setCreated(reservation);
    } catch (err) {
      setSubmitError(
        err?.code ? firebaseAuthErrorMessage(err) : 'No se ha podido crear la reserva.'
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (created) {
    return (
      <div className="modal-backdrop" role="dialog" aria-modal="true">
        <div className="modal modal-success">
          <div className="success-icon">
            <IconCheck width={30} height={30} />
          </div>
          <h2>¡Reserva confirmada!</h2>
          <p className="success-number">{created.number}</p>
          <dl className="success-grid">
            <div>
              <dt>Aparcamiento</dt>
              <dd>{created.parkingName}</dd>
            </div>
            <div>
              <dt>Fecha y horario</dt>
              <dd>
                {created.date} · {created.entryTime} – {created.exitTime} ({created.hours} h)
              </dd>
            </div>
            <div>
              <dt>Vehículo</dt>
              <dd>
                {created.vehicleTypeLabel} · <strong>{created.plate}</strong>
              </dd>
            </div>
            <div>
              <dt>Titular</dt>
              <dd>{created.holderName}</dd>
            </div>
            <div>
              <dt>Total pagado</dt>
              <dd className="success-total">{formatMoney(created.totalPrice)}</dd>
            </div>
          </dl>
          <p className="success-note">
            En el día de la reserva el lector de matrículas detectará tu vehículo y
            abrirá la barrera automáticamente.
          </p>
          <div className="modal-actions">
            <button type="button" className="btn btn-ghost" onClick={() => window.print()}>
              Descargar comprobante
            </button>
            <button
              type="button"
              className="btn btn-outline"
              onClick={() => {
                onClose();
                navigate('/reservas');
              }}
            >
              Ver mis reservas
            </button>
            <button type="button" className="btn btn-primary" onClick={onClose}>
              Cerrar
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="modal-backdrop" onClick={onClose}>
        <div className="modal" onClick={(e) => e.stopPropagation()}>
          <button type="button" className="modal-close icon-btn" onClick={onClose} aria-label="Cerrar">
            <IconClose />
          </button>
          <h2>Inicia sesión para reservar</h2>
          <p className="muted">
            Necesitas una cuenta para completar la reserva de{' '}
            <strong>{parking.name}</strong>. Es gratis y solo tarda un minuto.
          </p>
          <div className="modal-actions">
            <button type="button" className="btn btn-outline" onClick={onClose}>
              Ahora no
            </button>
            <button
              type="button"
              className="btn btn-primary"
              onClick={() => navigate('/login', { state: { from: '/explorar' } })}
            >
              Iniciar sesión
            </button>
            <button
              type="button"
              className="btn btn-ghost"
              onClick={() => navigate('/registro', { state: { from: '/explorar' } })}
            >
              Crear cuenta
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal modal-reservation"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Formulario de reserva"
      >
        <button type="button" className="modal-close icon-btn" onClick={onClose} aria-label="Cerrar">
          <IconClose />
        </button>

        <header className="modal-head">
          <div>
            <span className="eyebrow">Reserva de plaza</span>
            <h2>{parking.name}</h2>
            <p className="muted">{parking.address}</p>
          </div>
          <RatingStars value={parking.rating || 0} reviews={parking.reviews} size="sm" />
        </header>

        <form className="reservation-form" onSubmit={handleSubmit} noValidate>
          <fieldset className="form-section">
            <legend>
              <IconCar width={16} height={16} /> Vehículo
            </legend>
            <div className="form-grid">
              <label className="field span-2">
                <span>Tipo de coche *</span>
                <select {...FIELD('vehicleType')} value={form.vehicleType} onChange={set('vehicleType')}>
                  {VEHICLE_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>
                      {t.label} — {t.seats}
                    </option>
                  ))}
                </select>
                {errors.vehicleType && <em className="field-error">{errors.vehicleType}</em>}
              </label>

              <label className="field">
                <span>Matrícula *</span>
                <input
                  className="plate-input"
                  placeholder="1234BCN"
                  maxLength={9}
                  autoComplete="off"
                  value={form.plate}
                  onChange={(e) =>
                    setForm((prev) => ({ ...prev, plate: e.target.value.toUpperCase() }))
                  }
                />
                {errors.plate && <em className="field-error">{errors.plate}</em>}
              </label>

              <label className="field">
                <span>Teléfono de contacto *</span>
                <input
                  type="tel"
                  placeholder="600 000 000"
                  value={form.phone}
                  onChange={set('phone')}
                />
                {errors.phone && <em className="field-error">{errors.phone}</em>}
              </label>

              <label className="field">
                <span>Marca</span>
                <input placeholder="Seat" value={form.brand} onChange={set('brand')} />
              </label>

              <label className="field">
                <span>Modelo</span>
                <input placeholder="León" value={form.model} onChange={set('model')} />
              </label>
            </div>
          </fieldset>

          <fieldset className="form-section">
            <legend>
              <IconCalendar width={16} height={16} /> Titular y horario
            </legend>
            <div className="form-grid">
              <label className="field">
                <span>Nombre del titular *</span>
                <input value={form.holderName} onChange={set('holderName')} autoComplete="given-name" />
                {errors.holderName && <em className="field-error">{errors.holderName}</em>}
              </label>

              <label className="field">
                <span>Apellidos *</span>
                <input
                  value={form.holderSurname}
                  onChange={set('holderSurname')}
                  autoComplete="family-name"
                />
                {errors.holderSurname && <em className="field-error">{errors.holderSurname}</em>}
              </label>

              <label className="field">
                <span>Fecha *</span>
                <input type="date" value={form.date} onChange={set('date')} />
                {errors.date && <em className="field-error">{errors.date}</em>}
              </label>

              <label className="field">
                <span>
                  <IconClock width={13} height={13} /> Entrada *
                </span>
                <input type="time" value={form.entryTime} onChange={set('entryTime')} />
                {errors.entryTime && <em className="field-error">{errors.entryTime}</em>}
              </label>

              <label className="field">
                <span>
                  <IconClock width={13} height={13} /> Salida *
                </span>
                <input type="time" value={form.exitTime} onChange={set('exitTime')} />
                {errors.exitTime && <em className="field-error">{errors.exitTime}</em>}
              </label>

              <label className="field">
                <span>Método de pago</span>
                <select value={form.paymentMethod} onChange={set('paymentMethod')}>
                  <option value="tarjeta">Tarjeta (simulado)</option>
                  <option value="paypal">PayPal (simulado)</option>
                  <option value="apple_pay">Apple Pay (simulado)</option>
                </select>
              </label>
            </div>
          </fieldset>

          <fieldset className="form-section">
            <legend>Preferencias y observaciones</legend>
            <div className="check-row">
              <label className="check">
                <input type="checkbox" checked={form.needsEv} onChange={set('needsEv')} />
                Necesito punto de recarga eléctrica
              </label>
              <label className="check">
                <input type="checkbox" checked={form.needsPmr} onChange={set('needsPmr')} />
                Necesito plaza de movilidad reducida
              </label>
            </div>
            <label className="field">
              <span>Comentarios</span>
              <textarea
                rows={2}
                placeholder="Añade cualquier detalle para el equipo de aparcamiento…"
                value={form.comments}
                onChange={set('comments')}
              />
            </label>
          </fieldset>

          <aside className="price-box">
            <h3>Resumen del precio</h3>
            {!price.valid ? (
              <p className="muted">Selecciona fecha y horario para calcular el precio.</p>
            ) : (
              <>
                <ul className="price-lines">
                  <li>
                    <span>{price.hours} h de aparcamiento</span>
                    <span>{formatMoney(price.base)}</span>
                  </li>
                  <li>
                    <span>Despeses de gestió (10 %)</span>
                    <span>{formatMoney(price.fees)}</span>
                  </li>
                  {price.capped && (
                    <li className="price-note">
                      <span>Máximo diario aplicado (4,00 €)</span>
                    </li>
                  )}
                </ul>
                <div className="price-total">
                  <span>Total</span>
                  <strong>{formatMoney(price.total)}</strong>
                </div>
              </>
            )}

            <label className="check conditions">
              <input
                type="checkbox"
                checked={form.conditions}
                onChange={set('conditions')}
              />
              Acepto las condiciones de reserva y la política de privacidad.
            </label>
            {errors.conditions && <em className="field-error">{errors.conditions}</em>}
            {submitError && <div className="alert alert-danger">{submitError}</div>}

            <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
              {submitting ? (
                <Spinner inline label="Procesando…" />
              ) : (
                `Confirmar y pagar ${price.valid ? formatMoney(price.total) : ''}`
              )}
            </button>
            <p className="price-disclaimer">
              Tarifa {vehicleTypeLabel(form.vehicleType).toLowerCase()} · pago simulado
              para la demo.
            </p>
          </aside>
        </form>
      </div>
    </div>
  );
}

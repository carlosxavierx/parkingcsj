export const VEHICLE_TYPES = [
  { value: 'turismo', label: 'Turismo', seats: '4-5 plazas' },
  { value: 'suv', label: 'SUV / 4x4', seats: '5-7 plazas' },
  { value: 'monovolumen', label: 'Monovolumen', seats: '5-7 plazas' },
  { value: 'furgoneta', label: 'Furgoneta', seats: 'Mercancías' },
  { value: 'electrico', label: 'Eléctrico / Híbrido', seats: 'Con recarga' },
  { value: 'motocicleta', label: 'Motocicleta', seats: '2 ruedas' },
  { value: 'pmr', label: 'Movilidad reducida', seats: 'Plaza PMR' },
];

export const RESERVATION_STATUSES = {
  pendiente: { label: 'Pendiente', tone: 'warning' },
  confirmada: { label: 'Confirmada', tone: 'success' },
  cancelada: { label: 'Cancelada', tone: 'danger' },
  completada: { label: 'Completada', tone: 'neutral' },
};

export const EMPTY_RESERVATION_FORM = {
  vehicleType: 'turismo',
  plate: '',
  brand: '',
  model: '',
  holderName: '',
  holderSurname: '',
  phone: '',
  date: '',
  entryTime: '',
  exitTime: '',
  needsEv: false,
  needsPmr: false,
  comments: '',
  conditions: false,
  paymentMethod: 'tarjeta',
};

const PLATE_REGEX = /^([0-9]{4}[A-Z]{3}|[A-Z]{1,2}[0-9]{4}[A-Z]{2})$/;

export function normalizePlate(value = '') {
  return value.replace(/\s+/g, '').toUpperCase();
}

export function isValidPlate(value = '') {
  return PLATE_REGEX.test(normalizePlate(value));
}

function todayISO(offsetDays = 0) {
  const d = new Date();
  d.setDate(d.getDate() + offsetDays);
  return d.toISOString().slice(0, 10);
}

export function defaultSchedule() {
  const start = new Date();
  start.setMinutes(0, 0, 0);
  start.setHours(start.getHours() + 1);
  const end = new Date(start.getTime() + 2 * 3600000);
  const pad = (n) => String(n).padStart(2, '0');
  return {
    date: todayISO(),
    entryTime: `${pad(start.getHours())}:${pad(start.getMinutes())}`,
    exitTime: `${pad(end.getHours())}:${pad(end.getMinutes())}`,
  };
}

export function buildSchedule(form) {
  if (!form.date || !form.entryTime || !form.exitTime) return null;
  const start = new Date(`${form.date}T${form.entryTime}:00`);
  const end = new Date(`${form.date}T${form.exitTime}:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  if (end.getTime() <= start.getTime()) {
    end.setDate(end.getDate() + 1);
  }
  return { start, end };
}

export function validateReservationForm(form, { requireAuth = true, user = null } = {}) {
  const errors = {};

  if (requireAuth && !user) {
    errors.auth = 'Debes iniciar sesión para reservar.';
  }
  if (!form.holderName.trim()) errors.holderName = 'Indica el nombre del titular.';
  if (!form.holderSurname.trim()) errors.holderSurname = 'Indica los apellidos del titular.';
  if (!form.phone.trim()) {
    errors.phone = 'Indica un teléfono de contacto.';
  } else if (!/^[0-9+\s-]{9,15}$/.test(form.phone.trim())) {
    errors.phone = 'El teléfono no es válido.';
  }
  if (!form.plate.trim()) {
    errors.plate = 'La matrícula es obligatoria.';
  } else if (!isValidPlate(form.plate)) {
    errors.plate = 'Formato de matrícula no válido (ej. 1234BCN).';
  }
  if (!form.vehicleType) errors.vehicleType = 'Selecciona el tipo de vehículo.';
  if (!form.date) errors.date = 'Selecciona la fecha.';
  if (!form.entryTime) errors.entryTime = 'Indica la hora de entrada.';
  if (!form.exitTime) errors.exitTime = 'Indica la hora de salida.';
  if (!form.conditions) errors.conditions = 'Debes aceptar las condiciones.';
  return errors;
}

export function generateReservationNumber(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0');
  const stamp =
    `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}`;
  const suffix = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `TPK-${stamp}-${suffix}`;
}

export function vehicleTypeLabel(value) {
  return VEHICLE_TYPES.find((t) => t.value === value)?.label || value;
}

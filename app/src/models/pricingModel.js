/**
 * Tarifas del sistema (Sprint 1) — cada parking puede llevar sus propias
 * tarifas en `parking.rates`; si no, se usan estas.
 */
export const DEFAULT_RATES = {
  firstHoursCount: 2,
  firstHourPrice: 1.9,
  extraHourPrice: 0.2,
  dailyMax: 4.0,
  nightPrice: 0.6,
  nightFrom: 22,
  nightTo: 6,
  managementFee: 0.1,
};

export const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

function isNightHour(hour, rates) {
  return hour >= rates.nightFrom || hour < rates.nightTo;
}

/**
 * Calcula el precio de una reserva:
 *  1. Hores iniciades = ceil((sortida - entrada) / 1 h)
 *  2. Cada hora es nocturna (22-06) => tarifa nocturna; 1ª i 2ª hora => preu
 *     ordinari; 3ª en endavant => preu reduït
 *  3. Topall diari (màxim 4,00 €) per dia natural
 *  4. +10 % de despeses de gestió
 */
export function calculatePrice({ start, end, rates: customRates }) {
  const rates = { ...DEFAULT_RATES, ...(customRates || {}) };
  const diffMs = new Date(end).getTime() - new Date(start).getTime();

  if (Number.isNaN(diffMs) || diffMs <= 0) {
    return { valid: false, hours: 0, base: 0, fees: 0, total: 0, capped: false };
  }

  const hours = Math.max(1, Math.ceil(diffMs / 3600000));
  const perDay = new Map();
  let dayIndex = 0;

  for (let i = 0; i < hours; i += 1) {
    const slot = new Date(new Date(start).getTime() + i * 3600000);
    const hour = slot.getHours();
    const dayKey = slot.toISOString().slice(0, 10);
    let amount;

    if (isNightHour(hour, rates)) {
      amount = rates.nightPrice;
    } else if (dayIndex < rates.firstHoursCount) {
      amount = rates.firstHourPrice;
      dayIndex += 1;
    } else {
      amount = rates.extraHourPrice;
      dayIndex += 1;
    }

    perDay.set(dayKey, (perDay.get(dayKey) || 0) + amount);
  }

  let base = 0;
  let capped = false;
  for (const value of perDay.values()) {
    if (value > rates.dailyMax) {
      base += rates.dailyMax;
      capped = true;
    } else {
      base += value;
    }
  }

  base = round2(base);
  const fees = round2(base * rates.managementFee);
  const total = round2(base + fees);

  return { valid: true, hours, base, fees, total, capped, rates };
}

export const formatMoney = (amount) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(
    amount ?? 0
  );

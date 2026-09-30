import {
  addDoc,
  collection,
  doc,
  getDocs,
  limit,
  query,
  serverTimestamp,
  updateDoc,
  where,
} from 'firebase/firestore';
import { db } from '../config/firebase.js';
import {
  generateReservationNumber,
  vehicleTypeLabel,
} from '../models/reservationModel.js';
import { round2 } from '../models/pricingModel.js';

const COLLECTION = 'reservations';

export async function createReservation({
  user,
  parking,
  form,
  schedule,
  price,
}) {
  const number = generateReservationNumber();
  const payload = {
    number,
    userId: user.uid,
    userEmail: user.email,
    parkingId: parking.id,
    parkingName: parking.name,
    parkingAddress: parking.address,
    parkingLat: parking.lat,
    parkingLng: parking.lng,
    vehicleType: form.vehicleType,
    vehicleTypeLabel: vehicleTypeLabel(form.vehicleType),
    plate: form.plate.replace(/\s+/g, '').toUpperCase(),
    brand: form.brand.trim(),
    model: form.model.trim(),
    holderName: `${form.holderName.trim()} ${form.holderSurname.trim()}`,
    phone: form.phone.trim(),
    date: form.date,
    entryTime: form.entryTime,
    exitTime: form.exitTime,
    start: schedule.start.toISOString(),
    end: schedule.end.toISOString(),
    hours: price.hours,
    basePrice: round2(price.base),
    managementFee: round2(price.fees),
    totalPrice: round2(price.total),
    needsEv: !!form.needsEv,
    needsPmr: !!form.needsPmr,
    comments: form.comments.trim(),
    paymentMethod: form.paymentMethod,
    conditionsAccepted: true,
    status: 'confirmada',
    createdAt: serverTimestamp(),
  };

  const ref = await addDoc(collection(db, COLLECTION), payload);
  return { id: ref.id, ...payload };
}

export async function getMyReservations(userId, max = 50) {
  // Sin orderBy en servidor para no requerir índices compuestos de Firestore:
  // se ordena en cliente por fecha de creación descendente.
  const snap = await getDocs(
    query(collection(db, COLLECTION), where('userId', '==', userId), limit(max))
  );
  const rows = snap.docs.map((d) => ({ id: d.id, ...d.data() }));
  return rows.sort((a, b) => {
    const ta = a.createdAt?.toMillis?.() ?? 0;
    const tb = b.createdAt?.toMillis?.() ?? 0;
    return tb - ta;
  });
}

export async function cancelReservation(id) {
  await updateDoc(doc(db, COLLECTION, id), {
    status: 'cancelada',
    cancelledAt: new Date().toISOString(),
  });
}

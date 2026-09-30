import { collection, doc, getDoc, getDocs, query, where } from 'firebase/firestore';
import { db } from '../config/firebase.js';
import { normalizeParking } from '../models/parkingModel.js';

export async function getParkings() {
  const snap = await getDocs(
    query(collection(db, 'parkings'), where('active', '==', true))
  );
  return snap.docs.map((d) => normalizeParking(d.data(), d.id));
}

export async function getParking(id) {
  const snap = await getDoc(doc(db, 'parkings', id));
  if (!snap.exists()) return null;
  return normalizeParking(snap.data(), snap.id);
}

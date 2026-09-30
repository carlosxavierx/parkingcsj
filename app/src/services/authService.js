import {
  createUserWithEmailAndPassword,
  onAuthStateChanged,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut,
  updateProfile,
} from 'firebase/auth';
import { doc, getDoc, serverTimestamp, setDoc } from 'firebase/firestore';
import { auth, db } from '../config/firebase.js';

export function subscribeToAuth(callback) {
  return onAuthStateChanged(auth, callback);
}

export async function register({ name, surname, email, phone, password }) {
  const cred = await createUserWithEmailAndPassword(auth, email, password);
  const displayName = `${name} ${surname}`.trim();
  if (displayName) await updateProfile(cred.user, { displayName });

  await setDoc(
    doc(db, 'users', cred.user.uid),
    {
      uid: cred.user.uid,
      name,
      surname,
      displayName,
      email,
      phone: phone || '',
      role: 'client',
      createdAt: serverTimestamp(),
    },
    { merge: true }
  );

  return cred.user;
}

export async function login(email, password) {
  const cred = await signInWithEmailAndPassword(auth, email, password);
  return cred.user;
}

export async function logout() {
  await signOut(auth);
}

export function resetPassword(email) {
  return sendPasswordResetEmail(auth, email);
}

export async function fetchProfile(uid) {
  const snap = await getDoc(doc(db, 'users', uid));
  return snap.exists() ? snap.data() : null;
}

export function firebaseAuthErrorMessage(error) {
  const code = (error?.code || '').replace('auth/', '');
  const map = {
    'invalid-credential': 'Email o contraseña incorrectos.',
    'wrong-password': 'Email o contraseña incorrectos.',
    'user-not-found': 'No existe ninguna cuenta con ese email.',
    'email-already-in-use': 'Ya existe una cuenta con ese email.',
    'weak-password': 'La contraseña debe tener al menos 6 caracteres.',
    'invalid-email': 'El email no es válido.',
    'too-many-requests': 'Demasiados intentos. Inténtalo de nuevo en unos minutos.',
    'network-request-failed': 'Error de conexión con el servidor.',
    'operation-not-allowed':
      'La autenticación por email/contraseña no está activada en Firebase.',
  };
  return map[code] || 'No se ha podido completar la operación. Inténtalo de nuevo.';
}

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import {
  fetchProfile,
  login as loginService,
  logout as logoutService,
  register as registerService,
  subscribeToAuth,
} from '../services/authService.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);

  const loadProfile = useCallback(async (uid) => {
    try {
      const data = await fetchProfile(uid);
      setProfile(data);
    } catch {
      setProfile(null);
    }
  }, []);

  useEffect(() => {
    const unsub = subscribeToAuth((firebaseUser) => {
      setUser(firebaseUser);
      setLoading(false);
      if (firebaseUser) loadProfile(firebaseUser.uid);
      else setProfile(null);
    });
    return () => unsub();
  }, [loadProfile]);

  const login = async (email, password) => {
    const u = await loginService(email, password);
    await loadProfile(u.uid);
    return u;
  };

  const register = async (payload) => {
    const u = await registerService(payload);
    await loadProfile(u.uid);
    return u;
  };

  const logout = () => logoutService();

  const value = useMemo(
    () => ({ user, profile, loading, login, register, logout, reloadProfile: loadProfile }),
    [user, profile, loading, loadProfile]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth debe usarse dentro de <AuthProvider>');
  return ctx;
}

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../controllers/AuthContext.jsx';
import { firebaseAuthErrorMessage } from '../../services/authService.js';
import Spinner from '../components/Spinner.jsx';

const validate = (form) => {
  const errors = {};
  if (!form.name.trim()) errors.name = 'Indica tu nombre.';
  if (!form.surname.trim()) errors.surname = 'Indica tus apellidos.';
  if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) errors.email = 'El email no es válido.';
  if (form.password.length < 6) errors.password = 'Mínimo 6 caracteres.';
  if (form.password !== form.confirm) errors.confirm = 'Las contraseñas no coinciden.';
  return errors;
};

export default function RegisterPage() {
  const { user, loading: authLoading, register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '',
    surname: '',
    email: '',
    phone: '',
    password: '',
    confirm: '',
    conditions: false,
  });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (user) navigate('/explorar', { replace: true });
  }, [user, navigate]);

  const set = (key) => (e) => {
    const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
    setForm((prev) => ({ ...prev, [key]: value }));
    setErrors((prev) => ({ ...prev, [key]: undefined }));
  };

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    const found = validate(form);
    if (!form.conditions) found.conditions = 'Debes aceptar las condiciones.';
    setErrors(found);
    if (Object.keys(found).length > 0) return;

    setBusy(true);
    try {
      await register({
        name: form.name.trim(),
        surname: form.surname.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        password: form.password,
      });
      navigate('/explorar', { replace: true });
    } catch (err) {
      setError(firebaseAuthErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  if (authLoading) return <Spinner label="Comprobando sesión…" />;

  return (
    <section className="auth-page">
      <div className="auth-card auth-card-wide">
        <span className="eyebrow">Únete a Tarraco Parkings</span>
        <h1>Crea tu cuenta</h1>
        <p className="muted">Reserva plazas y guarda el historial de tus aparcamientos.</p>

        {error && <div className="alert alert-danger">{error}</div>}

        <form onSubmit={submit} className="auth-form two-cols" noValidate>
          <label className="field">
            <span>Nombre *</span>
            <input value={form.name} onChange={set('name')} autoComplete="given-name" />
            {errors.name && <em className="field-error">{errors.name}</em>}
          </label>

          <label className="field">
            <span>Apellidos *</span>
            <input value={form.surname} onChange={set('surname')} autoComplete="family-name" />
            {errors.surname && <em className="field-error">{errors.surname}</em>}
          </label>

          <label className="field">
            <span>Email *</span>
            <input
              type="email"
              value={form.email}
              onChange={set('email')}
              autoComplete="email"
            />
            {errors.email && <em className="field-error">{errors.email}</em>}
          </label>

          <label className="field">
            <span>Teléfono</span>
            <input type="tel" value={form.phone} onChange={set('phone')} autoComplete="tel" />
          </label>

          <label className="field">
            <span>Contraseña *</span>
            <input
              type="password"
              value={form.password}
              onChange={set('password')}
              autoComplete="new-password"
            />
            {errors.password && <em className="field-error">{errors.password}</em>}
          </label>

          <label className="field">
            <span>Repite la contraseña *</span>
            <input
              type="password"
              value={form.confirm}
              onChange={set('confirm')}
              autoComplete="new-password"
            />
            {errors.confirm && <em className="field-error">{errors.confirm}</em>}
          </label>

          <label className="check conditions span-2">
            <input type="checkbox" checked={form.conditions} onChange={set('conditions')} />
            Acepto las condiciones de uso y la política de privacidad.
          </label>
          {errors.conditions && <em className="field-error span-2">{errors.conditions}</em>}

          <button type="submit" className="btn btn-primary btn-block span-2" disabled={busy}>
            {busy ? <Spinner inline label="Creando cuenta…" /> : 'Crear cuenta'}
          </button>
        </form>

        <p className="auth-switch">
          ¿Ya tienes cuenta? <Link to="/login">Inicia sesión</Link>
        </p>
      </div>
    </section>
  );
}

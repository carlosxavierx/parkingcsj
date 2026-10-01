import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../controllers/AuthContext.jsx';
import { firebaseAuthErrorMessage, login, resetPassword } from '../../services/authService.js';
import Spinner from '../components/Spinner.jsx';

export default function LoginPage() {
  const { user, loading: authLoading } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [info, setInfo] = useState(null);
  const [busy, setBusy] = useState(false);
  const [resetSent, setResetSent] = useState(false);

  useEffect(() => {
    if (user) navigate('/explorar', { replace: true });
  }, [user, navigate]);

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await login(email.trim(), password);
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
      <div className="auth-shell">
        <aside className="auth-brand">
          <div>
            <img className="auth-logo" src="/logo-csj.png" alt="CSJ Parkings" />
            <h2>Tu plaza, siempre lista</h2>
            <p>
              Reserva en los parkings de Tarragona y entra sin detenerte: el lector
              de matrículas reconoce tu coche y abre la barrera.
            </p>
            <ul className="auth-points">
              <li>Reserva en menos de 60 segundos</li>
              <li>Cancelación sin coste</li>
              <li>Comprobante con número de reserva</li>
            </ul>
          </div>
          <span className="auth-quote">Online Parking Solutions</span>
        </aside>

        <div className="auth-card">
          <span className="eyebrow">Bienvenido de nuevo</span>
          <h1>Inicia sesión</h1>
          <p className="muted">
            Accede para reservar plazas y consultar tus reservas.
          </p>

          {info && <div className="alert alert-info">{info}</div>}
          {error && <div className="alert alert-danger">{error}</div>}

          <form onSubmit={submit} className="auth-form" noValidate>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="tu@email.com"
              />
            </label>

            <label className="field">
              <span>Contraseña</span>
              <input
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
              />
            </label>

            <div className="auth-row">
              <button
                type="button"
                className="link-btn"
                onClick={async () => {
                  if (!email.trim()) {
                    setError('Introduce tu email para recuperar la contraseña.');
                    return;
                  }
                  try {
                    await resetPassword(email.trim());
                    setResetSent(true);
                    setInfo('Te hemos enviado un email para restablecer la contraseña.');
                  } catch (err) {
                    setError(firebaseAuthErrorMessage(err));
                  }
                }}
              >
                {resetSent ? 'Email enviado ✓' : '¿Has olvidado la contraseña?'}
              </button>
            </div>

            <button type="submit" className="btn btn-primary btn-block" disabled={busy}>
              {busy ? <Spinner inline label="Entrando…" /> : 'Entrar'}
            </button>
          </form>

          <p className="auth-switch">
            ¿Aún no tienes cuenta? <Link to="/registro">Créala gratis</Link>
          </p>
        </div>
      </div>
    </section>
  );
}

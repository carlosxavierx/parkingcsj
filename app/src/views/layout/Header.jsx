import { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../controllers/AuthContext.jsx';
import { logout } from '../../services/authService.js';

const NAV_LINKS = [
  { to: '/', label: 'Inicio' },
  { to: '/explorar', label: 'Explorar mapa' },
  { to: '/reservas', label: 'Mis reservas', private: true },
];

export default function Header() {
  const { user, profile } = useAuth();
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  const close = () => setOpen(false);

  const handleLogout = async () => {
    await logout();
    close();
    navigate('/');
  };

  const initials =
    (profile?.name || user?.displayName || user?.email || '?')
      .split(' ')
      .map((word) => word[0])
      .slice(0, 2)
      .join('')
      .toUpperCase() || '?';

  return (
    <header className="site-header">
      <div className="topbar">
        <div className="container topbar-inner">
          <span className="topbar-tag">Online Parking Solutions · Tarragona</span>
          <div className="topbar-links">
            <span>Reserva <strong>24/7</strong></span>
            <a href="tel:+34977000000">+34 977 00 00 00</a>
            <a href="mailto:hola@tarracoparkings.cat">Soporte</a>
          </div>
        </div>
      </div>

      <div className="container header-inner">
        <Link to="/" className="brand" onClick={close} aria-label="CSJ Parkings">
          <img className="brand-logo" src="/logo-mark.png" alt="" aria-hidden="true" />
          <span className="brand-text">
            <span>
              CSJ <strong>Parkings</strong>
            </span>
            <span className="brand-tagline">Online Parking Solutions</span>
          </span>
        </Link>

        <nav className={`main-nav ${open ? 'is-open' : ''}`}>
          {NAV_LINKS.filter((link) => !link.private || user).map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              onClick={close}
              className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
            >
              {link.label}
            </NavLink>
          ))}
        </nav>

        <div className="header-actions">
          {user ? (
            <div className="user-chip" title={user.email}>
              <span className="avatar">{initials}</span>
              <span className="user-name">
                {profile?.name || user.displayName || 'Mi cuenta'}
              </span>
              <button type="button" className="btn btn-ghost btn-sm" onClick={handleLogout}>
                Salir
              </button>
            </div>
          ) : (
            <div className="auth-actions">
              <Link to="/login" className="btn btn-ghost btn-sm" onClick={close}>
                Entrar
              </Link>
              <Link to="/registro" className="btn btn-primary btn-sm" onClick={close}>
                Crear cuenta
              </Link>
            </div>
          )}

          <button
            type="button"
            className={`nav-toggle ${open ? 'is-open' : ''}`}
            onClick={() => setOpen((v) => !v)}
            aria-label="Abrir menú"
            aria-expanded={open}
          >
            <span />
            <span />
            <span />
          </button>
        </div>
      </div>
    </header>
  );
}

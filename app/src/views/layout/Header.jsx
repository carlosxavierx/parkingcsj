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
      <div className="container header-inner">
        <Link to="/" className="brand" onClick={close} aria-label="Tarraco Parkings">
          <span className="brand-mark">P</span>
          <span className="brand-text">
            Tarraco<strong>Parkings</strong>
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

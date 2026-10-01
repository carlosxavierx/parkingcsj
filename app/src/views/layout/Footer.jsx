import { Link } from 'react-router-dom';

const STRIP = [
  '14 parkings en catálogo',
  'Cancelación sin coste',
  'Acceso por matrícula',
  'Tarifa máxima 4,00 € / día',
];

export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="footer-strip">
        <div className="container footer-strip-inner">
          {STRIP.map((item) => (
            <span key={item}>{item}</span>
          ))}
        </div>
      </div>

      <div className="container footer-grid">
        <div className="footer-col footer-brand-col">
          <Link to="/" className="brand">
            <img className="footer-logo" src="/logo-csj.png" alt="CSJ Parkings" />
          </Link>
          <p>
            Reserva tu plaza de aparcamiento en los parkings municipales de
            Tarragona en menos de un minuto. Mapa en tiempo real, mejor
            valoración y acceso por matrícula.
          </p>
          <div className="footer-contact">
            <span>Plaça de la Font, 1 · 43001 Tarragona</span>
            <a href="tel:+34977000000">+34 977 00 00 00</a>
            <a href="mailto:hola@tarracoparkings.cat">hola@tarracoparkings.cat</a>
          </div>
        </div>

        <div className="footer-col">
          <h4>Producto</h4>
          <Link to="/explorar">Explorar mapa</Link>
          <Link to="/explorar">Parkings cercanos</Link>
          <Link to="/reservas">Mis reservas</Link>
          <Link to="/registro">Crear cuenta</Link>
        </div>

        <div className="footer-col">
          <h4>Soporte</h4>
          <a href="#como-funciona">Cómo funciona</a>
          <a href="#tarifas">Tarifas</a>
          <a href="#faq">Preguntas frecuentes</a>
          <a href="mailto:soporte@tarracoparkings.cat">Centro de ayuda</a>
        </div>

        <div className="footer-col">
          <h4>Legal</h4>
          <a href="#legal">Aviso legal</a>
          <a href="#legal">Política de privacidad</a>
          <a href="#legal">Política de cookies</a>
          <a href="#legal">Condiciones de reserva</a>
        </div>
      </div>

      <div className="container footer-bottom">
        <span>© {new Date().getFullYear()} CSJ Parkings · Todos los derechos reservados</span>
        <span className="footer-tech">
          Datos cartográficos: <strong>Geoapify</strong> · Backend: <strong>Firebase</strong>
        </span>
      </div>
    </footer>
  );
}

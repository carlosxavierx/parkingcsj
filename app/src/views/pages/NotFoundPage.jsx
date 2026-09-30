import { Link } from 'react-router-dom';

export default function NotFoundPage() {
  return (
    <section className="not-found container">
      <span className="eyebrow">Error 404</span>
      <h1>Esta página no existe</h1>
      <p className="muted">
        Puede que el enlace esté roto o que la página se haya movido.
      </p>
      <div className="not-found-actions">
        <Link to="/" className="btn btn-primary">
          Volver al inicio
        </Link>
        <Link to="/explorar" className="btn btn-outline">
          Explorar el mapa
        </Link>
      </div>
    </section>
  );
}

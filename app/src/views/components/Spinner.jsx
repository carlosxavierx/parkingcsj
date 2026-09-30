export default function Spinner({ label = 'Cargando…', inline = false }) {
  return (
    <div className={`spinner-wrap ${inline ? 'inline' : ''}`} role="status">
      <span className="spinner" />
      <span className="spinner-label">{label}</span>
    </div>
  );
}

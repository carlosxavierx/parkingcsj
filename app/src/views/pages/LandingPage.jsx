import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useParkings } from '../../controllers/useParkings.js';
import { sortByBestRating } from '../../models/parkingModel.js';
import { DEFAULT_RATES, formatMoney } from '../../models/pricingModel.js';
import RatingStars from '../components/RatingStars.jsx';
import ParkingCard from '../components/ParkingCard.jsx';
import Spinner from '../components/Spinner.jsx';
import {
  IconMap,
  IconShield,
  IconBolt,
  IconStar,
  IconCheck,
  IconArrow,
  IconCar,
} from '../components/icons.jsx';

const FEATURES = [
  {
    icon: <IconMap />,
    title: 'Mapa en tiempo real',
    text: 'Visualiza todos los parkings de Tarragona sobre el mapa de Geoapify y filtra por el más cercano.',
  },
  {
    icon: <IconStar />,
    title: 'Mejor valoración primero',
    text: 'Un rail prioritario muestra los aparcamientos con mejores opiniones justo antes del mapa.',
  },
  {
    icon: <IconBolt />,
    title: 'Reserva en 60 segundos',
    text: 'Tipo de coche, matrícula, titular y horario: el precio se calcula automáticamente.',
  },
  {
    icon: <IconShield />,
    title: 'Acceso por matrícula',
    text: 'El lector de matrículas detecta tu vehículo y abre la barrera sin colas ni tickets.',
  },
  {
    icon: <IconCar />,
    title: 'Multi-vehículo',
    text: 'Turismos, SUV, furgonetas, eléctricos y motocicletas con plazas PMR y recarga.',
  },
  {
    icon: <IconCheck />,
    title: 'Cancelación flexible',
    text: 'Gestiona todas tus reservas desde tu área personal y cancela sin coste: la reserva nunca se elimina, cambia de estado.',
  },
];

const STEPS = [
  {
    n: '01',
    title: 'Localiza tu parking',
    text: 'Activa tu ubicación y revisa el rail de parkings mejor valorados junto al mapa.',
  },
  {
    n: '02',
    title: 'Rellena el formulario',
    text: 'Indica tipo de vehículo, matrícula, titular y horario. El precio se calcula solo.',
  },
  {
    n: '03',
    title: 'Confirma la reserva',
    text: 'Recibes tu número de reserva y el comprobante descargable al instante.',
  },
  {
    n: '04',
    title: 'Entra sin parar',
    text: 'El lector de matrículas reconoce tu coche y abre la barrera automáticamente.',
  },
];

const FAQS = [
  {
    q: '¿Necesito cuenta para reservar?',
    a: 'Sí. La cuenta nos permite asociar la reserva a tu matrícula y enviarte el comprobante. El registro es gratuito y solo lleva un minuto.',
  },
  {
    q: '¿Cómo se calcula el precio?',
    a: 'Se aplican las tarifas municipales (1,90 € las dos primeras horas, 0,20 € a partir de la tercera, máximo diario de 4,00 € y tarifa nocturna de 0,60 €) más un 10 % de gastos de gestión.',
  },
  {
    q: '¿Puedo cancelar una reserva?',
    a: 'Sí, desde la sección «Mis reservas». La reserva nunca se elimina: pasa al estado «cancelada» para mantener el histórico.',
  },
  {
    q: '¿Qué datos pide el formulario?',
    a: 'Tipo de coche, matrícula, marca y modelo opcionales, nombre y apellidos del titular, teléfono, fecha y horario de entrada y salida.',
  },
];

export default function LandingPage() {
  const navigate = useNavigate();
  const { parkings, loading } = useParkings({ position: null, includeGeoapify: false });
  const [openFaq, setOpenFaq] = useState(0);

  const featured = useMemo(
    () => sortByBestRating(parkings).filter((p) => p.rating != null).slice(0, 3),
    [parkings]
  );

  const stats = useMemo(() => {
    const rated = parkings.filter((p) => p.rating != null);
    const avg = rated.length
      ? rated.reduce((sum, p) => sum + p.rating, 0) / rated.length
      : 0;
    const spots = parkings.reduce((sum, p) => sum + (p.spotsTotal || 0), 0);
    return { total: parkings.length, avg, spots };
  }, [parkings]);

  return (
    <>
      {/* HERO */}
      <section className="hero">
        <div className="container hero-grid">
          <div className="hero-copy">
            <span className="hero-badge">
              <span className="pulse-dot" /> Tarragona · 100 % online
            </span>
            <h1>
              Tu plaza de aparcamiento,
              <br />
              <span className="text-gradient">reservada antes de llegar</span>
            </h1>
            <p className="hero-lead">
              Descubre los parkings cercanos en el mapa, compara valoraciones y
              reserva rellenando un solo formulario: tipo de coche, matrícula y
              titular. La barrera se abrirá con tu matrícula.
            </p>

            <div className="hero-actions">
              <button
                type="button"
                className="btn btn-primary btn-lg"
                onClick={() => navigate('/explorar')}
              >
                Buscar parking cerca de mí <IconArrow width={17} height={17} />
              </button>
              <Link to="/registro" className="btn btn-outline btn-lg">
                Crear cuenta gratis
              </Link>
            </div>

            <ul className="hero-checks">
              <li><IconCheck width={16} height={16} /> Sin colas ni tickets de papel</li>
              <li><IconCheck width={16} height={16} /> Cancelación flexible</li>
              <li><IconCheck width={16} height={16} /> Pago seguro simulado</li>
            </ul>
          </div>

          <div className="hero-visual" aria-hidden="true">
            <div className="hero-card hero-card-main">
              <div className="hero-card-head">
                <span className="brand-mark small">P</span>
                <div>
                  <strong>Aparcament Plaça de la Font</strong>
                  <RatingStars value={4.8} reviews={214} size="sm" />
                </div>
              </div>
              <div className="hero-card-map">
                <div className="fake-road road-h" />
                <div className="fake-road road-v" />
                <span className="fake-pin pin-a"><b>P</b></span>
                <span className="fake-pin pin-b"><b>P</b></span>
                <span className="fake-pin pin-c"><b>P</b></span>
                <span className="fake-you">◎</span>
              </div>
              <div className="hero-card-foot">
                <span>240 m · Subterráneo</span>
                <span className="hero-price">desde 1,90 €/h</span>
              </div>
            </div>

            <div className="hero-card hero-card-float">
              <span className="float-check"><IconCheck width={14} height={14} /></span>
              <div>
                <strong>Reserva confirmada</strong>
                <span>TPK-20260930-8F4K2Q</span>
              </div>
            </div>

            <div className="hero-card hero-card-float-2">
              <strong>+12.400</strong>
              <span>plazas reservadas este año</span>
            </div>
          </div>
        </div>

        {/* BANDA DE ESTADÍSTICAS (inversa) */}
        <div className="stats-band">
          <div className="container stats-bar">
            <div className="stat">
              <strong>{stats.total || '—'}</strong>
              <span>parkings en catálogo</span>
            </div>
            <div className="stat">
              <strong>{stats.avg ? stats.avg.toFixed(1) : '—'} ★</strong>
              <span>valoración media</span>
            </div>
            <div className="stat">
              <strong>{stats.spots ? stats.spots.toLocaleString('es-ES') : '—'}</strong>
              <span>plazas gestionadas</span>
            </div>
            <div className="stat">
              <strong>60 s</strong>
              <span>para reservar</span>
            </div>
          </div>
        </div>
      </section>

      {/* CÓMO FUNCIONA */}
      <section className="section section-steps" id="como-funciona">
        <div className="container">
          <div className="section-head">
            <span className="eyebrow">Cómo funciona</span>
            <h2>De la búsqueda a la barrera en 4 pasos</h2>
          </div>
          <div className="steps-grid">
            {STEPS.map((s) => (
              <article className="step-card" key={s.n}>
                <span className="step-number">{s.n}</span>
                <h3>{s.title}</h3>
                <p>{s.text}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* VENTAJAS (bento) */}
      <section className="section section-alt" id="ventajas">
        <div className="container">
          <div className="section-head">
            <span className="eyebrow">Por qué CSJ Parkings</span>
            <h2>Todo lo que necesitas para aparcar sin estrés</h2>
            <p className="muted">
              Una plataforma pensada para conductores: mapa, valoraciones y reserva
              online en una sola experiencia.
            </p>
          </div>
          <div className="feature-grid">
            {FEATURES.map((f, i) => (
              <article
                className={`feature-card ${i === 0 ? 'lead' : ''} ${
                  i === FEATURES.length - 1 ? 'wide' : ''
                }`}
                key={f.title}
              >
                <span className="feature-icon">{f.icon}</span>
                <div>
                  <h3>{f.title}</h3>
                  <p>{f.text}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* PARKINGS DESTACADOS */}
      <section className="section" id="parkings">
        <div className="container">
          <div className="section-head row">
            <div>
              <span className="eyebrow">Mejor valorados</span>
              <h2>Parkings destacados de Tarragona</h2>
            </div>
            <Link to="/explorar" className="btn btn-outline">
              Ver todos en el mapa <IconArrow width={16} height={16} />
            </Link>
          </div>

          {loading ? (
            <Spinner label="Cargando parkings destacados…" />
          ) : (
            <div className="featured-grid">
              {featured.map((p, i) => (
                <ParkingCard
                  key={p.id}
                  parking={p}
                  index={i}
                  showRank
                  onSelect={() => navigate('/explorar')}
                  onReserve={() => navigate('/explorar')}
                />
              ))}
              {!loading && featured.length === 0 && (
                <div className="alert alert-info">
                  Aún no hay parkings cargados. Ejecuta <code>npm run seed</code> en la
                  carpeta <code>app/</code> para poblar Firestore con los parkings de
                  Tarragona.
                </div>
              )}
            </div>
          )}
        </div>
      </section>

      {/* TARIFAS (banda amarilla) */}
      <section className="section section-yellow" id="tarifas">
        <div className="container tariff-grid">
          <div className="section-head left">
            <span className="eyebrow">Tarifas transparentes</span>
            <h2>Precios claros, sin sorpresas</h2>
            <p className="muted">
              El mismo cálculo que verás en el formulario: se aplica automáticamente al
              confirmar la reserva.
            </p>
            <Link to="/explorar" className="btn btn-dark">
              Calcular mi reserva <IconArrow width={16} height={16} />
            </Link>
          </div>

          <div className="tariff-card">
            <ul>
              <li>
                <span>1ª y 2ª hora</span>
                <strong>{formatMoney(DEFAULT_RATES.firstHourPrice)} /h</strong>
              </li>
              <li>
                <span>A partir de la 3ª hora</span>
                <strong>{formatMoney(DEFAULT_RATES.extraHourPrice)} /h</strong>
              </li>
              <li>
                <span>Tarifa nocturna (22:00 – 06:00)</span>
                <strong>{formatMoney(DEFAULT_RATES.nightPrice)} /h</strong>
              </li>
              <li>
                <span>Máximo diario</span>
                <strong>{formatMoney(DEFAULT_RATES.dailyMax)} /día</strong>
              </li>
              <li className="total-row">
                <span>Gastos de gestión</span>
                <strong>10 %</strong>
              </li>
            </ul>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="section" id="faq">
        <div className="container faq-layout">
          <div className="section-head">
            <span className="eyebrow">Preguntas frecuentes</span>
            <h2>Resolvemos tus dudas</h2>
            <p className="muted">
              Todo lo que necesitas saber antes de reservar tu plaza en Tarragona.
            </p>
          </div>
          <div className="faq-list">
            {FAQS.map((item, i) => (
              <div className={`faq-item ${openFaq === i ? 'open' : ''}`} key={item.q}>
                <button
                  type="button"
                  className="faq-question"
                  onClick={() => setOpenFaq(openFaq === i ? -1 : i)}
                  aria-expanded={openFaq === i}
                >
                  {item.q}
                  <span className="faq-icon">{openFaq === i ? '−' : '+'}</span>
                </button>
                <div className="faq-answer">
                  <p>{item.a}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="cta-band">
        <div className="container cta-inner">
          <div>
            <span className="eyebrow">Empieza ahora</span>
            <h2>¿Listo para aparcar sin dar vueltas?</h2>
            <p>Encuentra tu plaza ahora mismo en el mapa de Tarragona.</p>
          </div>
          <div className="cta-actions">
            <button
              type="button"
              className="btn btn-primary btn-lg"
              onClick={() => navigate('/explorar')}
            >
              Abrir mapa <IconArrow width={17} height={17} />
            </button>
            <Link to="/registro" className="btn btn-outline btn-lg">
              Crear cuenta
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}

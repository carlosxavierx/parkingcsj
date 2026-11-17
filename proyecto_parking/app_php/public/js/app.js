/* =====================================================================
   CSJ Parkings · JavaScript del client (sense llibreries, excepte Leaflet al mapa)
   Només millora la usabilitat: totes les validacions es repeteixen al servidor.
   ===================================================================== */
'use strict';

const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const euro = (n) => n.toLocaleString('ca-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const arrodonir = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

// ---------- Menú mòbil i menú d'usuari ----------
$('[data-menu]')?.addEventListener('click', () => $('#menu').classList.toggle('obert'));
$('[data-desplegable]')?.addEventListener('click', (e) => {
    e.stopPropagation();
    e.currentTarget.parentElement.classList.toggle('obert');
});
document.addEventListener('click', () => $('.menu-usuari')?.classList.remove('obert'));

// ---------- Confirmació abans d'accions importants ----------
$$('form[data-confirmar]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!confirm(f.dataset.confirmar)) e.preventDefault();
}));

// ---------- Selects que envien el formulari en canviar ----------
$$('[data-autoenviar]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

// ---------- Matrícules en majúscules i sense espais ----------
$$('.input-matricula').forEach((el) => el.addEventListener('input', () => {
    el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
}));

// ---------- Cerca en viu de pàrquings (inici) ----------
$$('.cerca-viva').forEach((input) => input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    $$(input.dataset.filtre).forEach((el) => { el.hidden = q && !el.dataset.text.includes(q); });
}));

// ---------- Tipus de mètode de pagament: mostra només els camps necessaris ----------
const formPagament = $('[data-tipus-pagament]');
if (formPagament) {
    const actualitzar = () => {
        const tipus = $('input[name=tipus]:checked', formPagament)?.value;
        $$('[data-per]', formPagament).forEach((bloc) => {
            const visible = bloc.dataset.per === tipus;
            bloc.hidden = !visible;
            $$('input', bloc).forEach((i) => { i.required = visible; });
        });
    };
    $$('input[name=tipus]', formPagament).forEach((r) => r.addEventListener('change', actualitzar));
    actualitzar();
    // Format del número de targeta: grups de 4
    $('input[name=numero_targeta]', formPagament)?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
    });
    $('input[name=caducitat]', formPagament)?.addEventListener('input', (e) => {
        const v = e.target.value.replace(/\D/g, '').slice(0, 4);
        e.target.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
    });
}

// ---------- Administració d'usuaris: el pàrquing només per a operadors ----------
$$('select[data-rol]').forEach((sel) => sel.addEventListener('change', () => {
    const p = $('[data-parquing-operador]', sel.form);
    p.hidden = sel.value !== 'operador';
    p.required = sel.value === 'operador';
}));

// ---------- Mapa dels pàrquings (Leaflet + OpenStreetMap) ----------
const mapa = $('#mapa');
if (mapa && window.L) {
    const parquings = JSON.parse(mapa.dataset.parquings || '[]');
    const m = L.map(mapa, { scrollWheelZoom: false }).setView([41.1189, 1.2445], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap',
    }).addTo(m);
    const limits = [];
    parquings.forEach((p) => {
        const color = p.lliures > 5 ? '#43a047' : (p.lliures > 0 ? '#f0a000' : '#c62828');
        const marcador = L.circleMarker([p.lat, p.lng], { radius: 10, color: '#15171c', weight: 2, fillColor: color, fillOpacity: .95 }).addTo(m);
        const div = document.createElement('div');
        const titol = document.createElement('strong'); titol.textContent = p.nom;
        const info = document.createElement('div'); info.textContent = `${p.adreca} · ${p.lliures} places lliures`;
        const enllac = document.createElement('a'); enllac.href = `/reserves/nova?parquing=${p.id}`; enllac.textContent = 'Reservar aquí →';
        div.append(titol, info, enllac);
        marcador.bindPopup(div);
        limits.push([p.lat, p.lng]);
    });
    if (limits.length) m.fitBounds(limits, { padding: [30, 30] });
} else if (mapa) {
    mapa.innerHTML = '';
}

// =====================================================================
//  Vista prèvia del preu de la reserva.
//  Mateix algorisme que src/Services/CalculPreu.php (el valor definitiu
//  sempre el calcula el servidor):
//   - minut a minut des de l'entrada;
//   - franja nocturna -> preu_nocturn; si no, primeres N hores -> preu_hora_inicial,
//     després -> preu_hora_extra;
//   - màxim diari per dia natural; + percentatge de gestió.
// =====================================================================
function calcularPreu(t, entrada, sortida) {
    const minuts = Math.floor((sortida - entrada) / 60000);
    if (!(minuts > 0)) return null;
    const minutsInicials = Math.round(t.hores_inicials * 60);
    const nitDesDe = t.inici_nocturn, nitFins = t.fi_nocturn;
    const dies = {};
    const cursor = new Date(entrada.getTime());
    for (let m = 0; m < minuts; m++) {
        const dia = `${cursor.getFullYear()}-${cursor.getMonth()}-${cursor.getDate()}`;
        const hora = cursor.getHours() + cursor.getMinutes() / 60;
        const nit = nitDesDe > nitFins ? (hora >= nitDesDe || hora < nitFins) : (hora >= nitDesDe && hora < nitFins);
        const tipus = nit ? 'nocturn' : (m < minutsInicials ? 'inicial' : 'extra');
        dies[dia] ??= { inicial: 0, extra: 0, nocturn: 0 };
        dies[dia][tipus]++;
        cursor.setTime(cursor.getTime() + 60000);
    }
    let base = 0;
    Object.values(dies).forEach((d) => {
        const imp = (d.inicial * t.preu_hora_inicial + d.extra * t.preu_hora_extra + d.nocturn * t.preu_nocturn) / 60;
        base += arrodonir(Math.min(imp, t.maxim_diari));
    });
    base = arrodonir(base);
    const gestio = arrodonir(base * t.percentatge_gestio / 100);
    return { minuts, base, gestio, total: arrodonir(base + gestio) };
}

const formReserva = $('#form-reserva');
if (formReserva) {
    const tarifes = JSON.parse(formReserva.dataset.tarifes || '{}');
    const selParquing = $('#r-parquing'), inEntrada = $('#r-entrada'), inSortida = $('#r-sortida');
    const disp = $('#r-disponibilitat'), boto = $('#r-boto');
    let temporitzador = null;

    const durada = (min) => {
        const d = Math.floor(min / 1440), h = Math.floor((min % 1440) / 60), m = min % 60;
        return [d && `${d} d`, h && `${h} h`, m && `${m} min`].filter(Boolean).join(' ') || '0 min';
    };

    const actualitzar = () => {
        const id = selParquing.value;
        const opcio = selParquing.selectedOptions[0];
        $('#r-info-parquing').textContent = id ? `📍 ${opcio.dataset.adreca} · 🕒 Horari: ${opcio.dataset.horari}` : '';
        const entrada = new Date(inEntrada.value), sortida = new Date(inSortida.value);
        const t = tarifes[id];
        const res = t && !isNaN(entrada) && !isNaN(sortida) ? calcularPreu(t, entrada, sortida) : null;
        $('#r-durada').textContent = res ? durada(res.minuts) : '—';
        $('#r-base').textContent = res ? euro(res.base) : '—';
        $('#r-gestio').textContent = res ? euro(res.gestio) : '—';
        $('#r-total').textContent = res ? euro(res.total) : '—';
        if (t) $('#r-pct').textContent = t.percentatge_gestio.toLocaleString('ca-ES');

        disp.className = 'disponibilitat';
        if (!id) { disp.textContent = 'Tria pàrquing i horari…'; return; }
        if (!res) { disp.textContent = 'La sortida ha de ser posterior a l\'entrada.'; disp.classList.add('ko'); return; }
        if (res.minuts < 30) { disp.textContent = 'La durada mínima és de 30 minuts.'; disp.classList.add('ko'); return; }
        if (entrada < new Date(Date.now() - 300000)) { disp.textContent = 'L\'entrada no pot ser en el passat.'; disp.classList.add('ko'); return; }

        // Disponibilitat real: es consulta al servidor (API JSON)
        clearTimeout(temporitzador);
        disp.textContent = 'Comprovant disponibilitat…';
        temporitzador = setTimeout(async () => {
            try {
                const params = new URLSearchParams({ parquing: id, entrada: inEntrada.value, sortida: inSortida.value });
                const r = await fetch('/api/disponibilitat?' + params, { headers: { Accept: 'application/json' } });
                const dades = await r.json();
                if (!dades.ok) throw new Error(dades.error);
                const lliures = dades.places_lliures;
                disp.textContent = lliures > 0 ? `✔ ${lliures} places lliures en aquesta franja` : '✖ No queden places en aquesta franja';
                disp.classList.add(lliures > 0 ? 'ok' : 'ko');
                boto.disabled = lliures <= 0;
            } catch (err) {
                disp.textContent = 'No s\'ha pogut comprovar la disponibilitat.';
            }
        }, 350);
    };

    // La sortida mai anterior a l'entrada: si cal, es mou 2 hores després
    inEntrada.addEventListener('change', () => {
        const e = new Date(inEntrada.value), s = new Date(inSortida.value);
        if (!isNaN(e) && (isNaN(s) || s <= e)) {
            const nova = new Date(e.getTime() + 2 * 3600000);
            const pad = (n) => String(n).padStart(2, '0');
            inSortida.value = `${nova.getFullYear()}-${pad(nova.getMonth() + 1)}-${pad(nova.getDate())}T${pad(nova.getHours())}:${pad(nova.getMinutes())}`;
        }
    });
    [selParquing, inEntrada, inSortida].forEach((el) => el.addEventListener('change', actualitzar));
    inEntrada.addEventListener('input', actualitzar);
    inSortida.addEventListener('input', actualitzar);
    actualitzar();
}

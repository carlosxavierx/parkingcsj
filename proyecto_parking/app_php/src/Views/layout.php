<?php
/** Plantilla general: capçalera amb menú segons el rol, missatges i peu. */
try {
    $u = usuari();
} catch (Throwable) {
    $u = null;   // si la BD no respon, la pàgina d'error s'ha de poder mostrar igualment
}
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
    <title><?= e($titol ?? 'CSJ Parkings') ?> · CSJ Parkings</title>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap">
    <link rel="stylesheet" href="/css/app.css?v=2">
    <?php if (!empty($mapa)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
</head>
<body>
<header class="capcalera">
    <div class="contenidor capcalera-int">
        <a href="/" class="logo" aria-label="CSJ Parkings · Online Parking Solutions">
            <img src="/img/logo-mark.png" alt="" class="logo-marca">
            <span class="logo-text"><span class="logo-nom">CSJ PARKINGS</span><span class="logo-eslogan">Online Parking Solutions</span></span>
        </a>
        <button class="menu-boto" type="button" aria-label="Menú" data-menu>☰</button>
        <nav class="menu" id="menu">
            <a href="/" class="<?= ruta_activa('/') ?>">Inici</a>
            <?php if ($u): ?>
                <a href="/reserves/nova" class="<?= ruta_activa('/reserves/nova') ?>">Reservar</a>
                <a href="/reserves" class="<?= ruta_activa('/reserves') && !ruta_activa('/reserves/nova') ? 'actiu' : '' ?>">Les meves reserves</a>
                <?php if (in_array($u['rol'], ['operador', 'admin'], true)): ?>
                    <a href="/operador" class="<?= ruta_activa('/operador') ?>">Operador</a>
                <?php endif; ?>
                <?php if ($u['rol'] === 'admin'): ?>
                    <a href="/admin" class="<?= ruta_activa('/admin') ?>">Administració</a>
                <?php endif; ?>
                <div class="menu-usuari">
                    <button type="button" class="menu-usuari-boto" data-desplegable>
                        <span class="avatar"><?= e(mb_substr($u['nom'], 0, 1)) ?></span>
                        <?= e($u['nom']) ?> <small class="rol rol-<?= e($u['rol']) ?>"><?= e(etiqueta_rol($u['rol'])) ?></small>
                    </button>
                    <div class="desplegable">
                        <a href="/perfil">El meu perfil</a>
                        <a href="/vehicles">Vehicles</a>
                        <a href="/pagaments">Mètodes de pagament</a>
                        <form method="post" action="/logout"><?= csrf_field() ?><button type="submit">Tancar sessió</button></form>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login" class="<?= ruta_activa('/login') ?>">Iniciar sessió</a>
                <a href="/registre" class="boto boto-petit">Crear compte</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="contenidor principal">
    <?php foreach (flashes() as $f): ?>
        <div class="avis avis-<?= e($f['tipus']) ?>" role="alert"><?= e($f['missatge']) ?></div>
    <?php endforeach; ?>
    <?= $contingut ?>
</main>

<footer class="peu">
    <div class="peu-franja"></div>
    <div class="contenidor peu-graella">
        <div class="peu-marca">
            <a href="/" class="logo logo-peu" aria-label="CSJ Parkings · Online Parking Solutions">
                <img src="/img/logo-mark.png" alt="" class="logo-marca">
                <span class="logo-text"><span class="logo-nom">CSJ PARKINGS</span><span class="logo-eslogan">Online Parking Solutions</span></span>
            </a>
            <p>Reserva la teva plaça als pàrquings municipals de Tarragona en un minut.
                Entra amb la matrícula: la barrera s'obre sola.</p>
        </div>
        <nav class="peu-col" aria-label="Enllaços">
            <h3>Navegació</h3>
            <a href="/">Inici i pàrquings</a>
            <?php if ($u): ?>
                <a href="/reserves/nova">Nova reserva</a>
                <a href="/reserves">Les meves reserves</a>
                <a href="/perfil">El meu perfil</a>
            <?php else: ?>
                <a href="/registre">Crear compte</a>
                <a href="/login">Iniciar sessió</a>
            <?php endif; ?>
        </nav>
        <div class="peu-col">
            <h3>Informació</h3>
            <a href="/condicions">Condicions d'ús</a>
            <a href="/condicions#privacitat">Privacitat i protecció de dades</a>
            <a href="/">Tarifes i places lliures</a>
            <a href="/#mapa">Mapa dels pàrquings</a>
        </div>
        <div class="peu-col">
            <h3>Contacte</h3>
            <span>📍 Tarragona</span>
            <span>✉️ info@csjparkings.cat</span>
            <span>🕒 Atenció: dl.–dv. de 9 a 18 h</span>
            <div class="peu-segells">
                <span>🔒 HTTPS</span><span>🛡️ Dades xifrades</span>
            </div>
        </div>
    </div>
    <div class="peu-inferior">
        <div class="contenidor peu-inferior-int">
            <span>© <?= date('Y') ?> CSJ Parkings · Projecte ASIX2</span>
            <span>Pagaments simulats: no es fa cap càrrec real</span>
        </div>
    </div>
</footer>
<?php if (!empty($mapa)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<script src="/js/app.js?v=1"></script>
</body>
</html>

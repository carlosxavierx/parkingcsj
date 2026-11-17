<?php
/** Inici: presentació, formulari ràpid de reserva, tarifes i llista de pàrquings amb places lliures. */
$mapa = true;
$ara = new DateTime('+1 hour');
$ara->setTime((int) $ara->format('H'), 0);
?>
<section class="hero">
    <div class="hero-text">
        <h1>Reserva la teva plaça als <span class="degradat">pàrquings de Tarragona</span></h1>
        <p><?= (int) $totals['parquings'] ?> pàrquings municipals · <strong><?= (int) $totals['lliures'] ?></strong> places reservables lliures ara mateix.
            Tria pàrquing, dia i hora, i entra amb la matrícula: la barrera s'obre sola.</p>
    </div>
    <form class="targeta reserva-rapida" method="get" action="/reserves/nova">
        <h2>Reserva ràpida</h2>
        <label>Pàrquing
            <select name="parquing" required>
                <option value="">— Tria un pàrquing —</option>
                <?php foreach ($parquings as $p): ?>
                    <option value="<?= (int) $p['id_parquing'] ?>"><?= e($p['nom']) ?> (<?= (int) $p['lliures_ara'] ?> lliures)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="fila-2">
            <label>Entrada <input type="datetime-local" name="entrada" value="<?= e($ara->format('Y-m-d\TH:i')) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
            <label>Sortida <input type="datetime-local" name="sortida" value="<?= e((clone $ara)->modify('+2 hours')->format('Y-m-d\TH:i')) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
        </div>
        <button class="boto boto-gran" type="submit">Continuar amb la reserva →</button>
        <?php if (!usuari()): ?><p class="ajuda">Cal tenir un compte per reservar. <a href="/registre">Registra't</a> en un minut.</p><?php endif; ?>
    </form>
</section>

<section class="tarifes-resum">
    <div><strong><?= euro($tarifes['preu_hora_inicial']) ?>/h</strong><span>1a i 2a hora</span></div>
    <div><strong><?= euro($tarifes['preu_hora_extra']) ?>/h</strong><span>a partir de la 3a hora</span></div>
    <div><strong><?= euro($tarifes['preu_nocturn']) ?>/h</strong><span>nocturna (<?= (int) $tarifes['inici_nocturn'] ?> h – <?= (int) $tarifes['fi_nocturn'] ?> h)</span></div>
    <div><strong><?= euro($tarifes['maxim_diari']) ?></strong><span>màxim per dia</span></div>
    <div><strong>+<?= rtrim(rtrim(number_format($tarifes['percentatge_gestio'], 2, ',', ''), '0'), ',') ?> %</strong><span>despeses de gestió</span></div>
</section>

<section>
    <div class="titol-seccio">
        <h2>Pàrquings</h2>
        <input type="search" placeholder="Cerca un pàrquing..." class="cerca-viva" data-filtre=".targeta-parquing">
    </div>
    <div id="mapa" class="mapa" data-parquings='<?= e(json_encode(array_map(fn ($p) => [
        'id' => (int) $p['id_parquing'], 'nom' => $p['nom'], 'lat' => (float) $p['latitud'], 'lng' => (float) $p['longitud'],
        'lliures' => (int) $p['lliures_ara'], 'adreca' => $p['adreca'],
    ], $parquings), JSON_UNESCAPED_UNICODE)) ?>'></div>

    <div class="graella-parquings">
        <?php foreach ($parquings as $p):
            $pct = $p['places_operatives'] ? round(100 * $p['lliures_ara'] / $p['places_operatives']) : 0;
            $foto = App\Models\Parquing::foto((int) $p['id_parquing']); ?>
            <article class="targeta targeta-parquing" data-text="<?= e(mb_strtolower($p['nom'] . ' ' . $p['adreca'] . ' ' . ($foto['lloc'] ?? ''))) ?>">
                <div class="tp-foto <?= $foto ? '' : 'sense-foto' ?>">
                    <?php if ($foto): ?>
                        <img src="<?= e($foto['url']) ?>" alt="<?= e($foto['lloc']) ?>, a prop de <?= e($p['nom']) ?>" loading="lazy" width="640" height="360">
                        <span class="tp-lloc"><?= e($foto['lloc']) ?></span>
                        <a class="tp-credit" href="<?= e($foto['commons']) ?>" target="_blank" rel="noopener"><?= e($foto['credit']) ?></a>
                    <?php else: ?>
                        <img src="/img/logo-mark.png" alt="" class="tp-logo">
                    <?php endif; ?>
                    <span class="etiqueta tp-tipus"><?= e(App\Models\Parquing::TIPUS[$p['tipus']] ?? $p['tipus']) ?></span>
                </div>
                <div class="tp-cos">
                <div class="tp-cap">
                    <h3><?= e($p['nom']) ?></h3>
                </div>
                <p class="adreca"><?= e($p['adreca']) ?>, <?= e($p['codi_postal']) ?> · 🕒 <?= e($p['horari']) ?></p>
                <div class="ocupacio">
                    <div class="barra"><span style="width: <?= (int) $pct ?>%" class="<?= $pct < 20 ? 'baix' : ($pct < 50 ? 'mig' : 'alt') ?>"></span></div>
                    <span><strong><?= (int) $p['lliures_ara'] ?></strong> / <?= (int) $p['places_operatives'] ?> places reservables lliures ara</span>
                </div>
                <?php if ($p['serveis']): ?>
                    <ul class="serveis">
                        <?php foreach (explode(',', $p['serveis']) as $s): ?><li><?= e(etiqueta_servei($s)) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="boto boto-secundari" href="/reserves/nova?parquing=<?= (int) $p['id_parquing'] ?>">Reservar aquí</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

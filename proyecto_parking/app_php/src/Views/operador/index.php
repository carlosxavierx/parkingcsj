<?php use App\Models\Reserva; ?>
<div class="titol-seccio">
    <h1><?= e($p['nom']) ?></h1>
    <?php if ($parquings): ?>
        <form method="get" action="/operador" class="en-linia">
            <select name="parquing" data-autoenviar>
                <?php foreach ($parquings as $pq): ?>
                    <option value="<?= (int) $pq['id_parquing'] ?>" <?= (int) $pq['id_parquing'] === (int) $p['id_parquing'] ? 'selected' : '' ?>><?= e($pq['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<section class="xifres">
    <div class="xifra"><strong><?= (int) $ocupacio['lliures_ara'] ?></strong><span>places reservables lliures</span></div>
    <div class="xifra"><strong><?= (int) $ocupacio['ocupades_ara'] ?></strong><span>reserves vigents ara</span></div>
    <div class="xifra"><strong><?= max(0, $rotacio['totals'] - $rotacio['dins']) ?></strong><span>places de rotació lliures (de <?= (int) $rotacio['totals'] ?>)</span></div>
    <div class="xifra"><strong><?= (int) $p['places_totals'] ?></strong><span>places totals</span></div>
</section>

<div class="graella-2">
    <form method="post" action="/operador/lector" class="targeta lector">
        <h2>🚗 Simulador del lector de matrícules</h2>
        <?= csrf_field() ?>
        <input type="hidden" name="parquing" value="<?= (int) $p['id_parquing'] ?>">
        <label>Matrícula llegida per la càmera
            <input name="matricula" class="input-matricula gran" placeholder="1234ABC" required maxlength="12" autofocus>
        </label>
        <details>
            <summary>Simular una altra hora (per a proves)</summary>
            <label>Data i hora de la lectura <input type="datetime-local" name="hora_simulada"></label>
            <p class="ajuda">Deixa-ho en blanc per fer servir l'hora actual.</p>
        </details>
        <div class="fila-2">
            <button class="boto boto-gran" name="tipus" value="entrada" type="submit">⬇ Barrera d'entrada</button>
            <button class="boto boto-gran boto-secundari" name="tipus" value="sortida" type="submit">⬆ Barrera de sortida</button>
        </div>
    </form>

    <div class="targeta barrera <?= $resultat ? ($resultat['oberta'] ? 'oberta' : 'tancada') : '' ?>">
        <h2>Barrera</h2>
        <?php if ($resultat): ?>
            <div class="barrera-visual"><span class="braç"></span></div>
            <div class="barrera-estat"><?= $resultat['oberta'] ? '✅' : '⛔' ?> <?= e($resultat['titol']) ?></div>
            <p><span class="matricula"><?= e($resultat['matricula']) ?></span> · <?= e(ucfirst($resultat['tipus'])) ?> · <?= e($resultat['hora']) ?>
                <?= $resultat['simulada'] ? '<small>(hora simulada)</small>' : '' ?></p>
            <p><strong><?= e($resultat['missatge']) ?></strong></p>
        <?php else: ?>
            <div class="barrera-visual"><span class="braç"></span></div>
            <p class="ajuda">Introdueix una matrícula i prem una barrera per simular una lectura.<br>
                Per provar: <span class="matricula petita">1234ABC</span> té una reserva confirmada (dades de demostració).</p>
        <?php endif; ?>
    </div>
</div>

<div class="targeta">
    <div class="titol-seccio">
        <h2>Reserves del dia</h2>
        <form method="get" action="/operador" class="en-linia">
            <input type="date" name="dia" value="<?= e($dia) ?>" data-autoenviar>
        </form>
    </div>
    <?php if (!$reserves): ?>
        <p class="buit">No hi ha reserves per a aquest dia.</p>
    <?php else: ?>
        <div class="taula-wrap">
            <table class="taula">
                <thead><tr><th>Número</th><th>Client</th><th>Matrícula</th><th>Plaça</th><th>Entrada</th><th>Sortida</th><th>Estat</th><th>Canviar estat</th></tr></thead>
                <tbody>
                <?php foreach ($reserves as $r): ?>
                    <tr>
                        <td><a class="mono" href="/reserves/<?= (int) $r['id_reserva'] ?>"><?= e($r['numero_reserva']) ?></a></td>
                        <td><?= e($r['client']) ?></td>
                        <td><span class="matricula petita"><?= e($r['matricula']) ?></span></td>
                        <td><?= e($r['codi_placa']) ?></td>
                        <td><?= e(data_hora($r['entrada_prevista'])) ?></td>
                        <td><?= e(data_hora($r['sortida_prevista'])) ?></td>
                        <td><?= badge_estat($r['estat']) ?></td>
                        <td>
                            <?php if ($r['estat'] !== 'cancellada'): ?>
                                <form method="post" action="/operador/reserves/<?= (int) $r['id_reserva'] ?>/estat" class="en-linia">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="parquing" value="<?= (int) $p['id_parquing'] ?>">
                                    <select name="estat">
                                        <?php foreach (Reserva::ESTATS as $es): ?>
                                            <option value="<?= e($es) ?>" <?= $es === $r['estat'] ? 'selected' : '' ?>><?= e(estat_reserva($es)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="boto boto-petit" type="submit">Desar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="targeta">
    <h2>Últims accessos</h2>
    <?php if (!$accessos): ?>
        <p class="buit">Encara no hi ha cap lectura.</p>
    <?php else: ?>
        <div class="taula-wrap">
            <table class="taula compacta">
                <thead><tr><th>Hora</th><th>Matrícula</th><th>Tipus</th><th>Barrera</th><th>Reserva</th><th>Excés</th><th>Detall</th></tr></thead>
                <tbody>
                <?php foreach ($accessos as $a): ?>
                    <tr>
                        <td><?= e(data_hora($a['data_hora'])) ?></td>
                        <td><span class="matricula petita"><?= e($a['matricula']) ?></span></td>
                        <td><?= $a['tipus'] === 'entrada' ? '⬇ Entrada' : '⬆ Sortida' ?></td>
                        <td><?= $a['barrera_oberta'] ? '<span class="badge badge-completada">Oberta</span>' : '<span class="badge badge-cancellada">Denegada</span>' ?></td>
                        <td class="mono petit"><?= e($a['numero_reserva'] ?? '—') ?></td>
                        <td><?= $a['minuts_exces'] ? (int) $a['minuts_exces'] . ' min' : '—' ?></td>
                        <td><?= e($a['motiu']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

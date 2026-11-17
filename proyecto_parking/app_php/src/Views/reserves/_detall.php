<?php
/** Detall d'una reserva (compartit entre el client i l'administració): dades, pagaments i historial. */
use App\Models\Parquing;
?>
<div class="graella-2">
    <div class="targeta">
        <h2>Estada</h2>
        <dl class="dades">
            <dt>Pàrquing</dt><dd><strong><?= e($r['parquing']) ?></strong><br><small><?= e($r['adreca']) ?>, <?= e($r['codi_postal']) ?> · <?= e($r['horari']) ?></small></dd>
            <dt>Plaça</dt><dd><?= e($r['codi_placa'] ?? '—') ?><?= $r['planta'] ? ' · planta ' . e($r['planta']) : '' ?>
                <?= $r['tipus_placa'] ? '<small>(' . e(Parquing::TIPUS_PLACA[$r['tipus_placa']] ?? $r['tipus_placa']) . ')</small>' : '' ?></dd>
            <dt>Entrada</dt><dd><?= e(data_hora($r['entrada_prevista'])) ?></dd>
            <dt>Sortida</dt><dd><?= e(data_hora($r['sortida_prevista'])) ?></dd>
            <dt>Vehicle</dt><dd><span class="matricula petita"><?= e($r['matricula']) ?></span> <?= e($r['marca'] . ' ' . $r['model']) ?></dd>
            <dt>Titular</dt><dd><?= e(trim($r['nom'] . ' ' . $r['primer_cognom'] . ' ' . $r['segon_cognom'])) ?> <small><?= e($r['email']) ?></small></dd>
            <?php if ($r['observacions']): ?><dt>Observacions</dt><dd><?= e($r['observacions']) ?></dd><?php endif; ?>
            <?php if ($r['estat'] === 'cancellada'): ?>
                <dt>Cancel·lada</dt><dd><?= e(data_hora($r['cancellada_a'])) ?> · <?= e($r['motiu_cancellacio']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <div class="targeta">
        <h2>Import</h2>
        <dl class="dades">
            <dt>Preu de l'estada</dt><dd><?= e(euro($r['preu_base'])) ?></dd>
            <dt>Despeses de gestió</dt><dd><?= e(euro($r['despeses_gestio'])) ?></dd>
            <dt>Total</dt><dd><strong class="preu-gran"><?= e(euro($r['preu_total'])) ?></strong></dd>
            <dt>Mètode</dt><dd><?= e($metode) ?></dd>
            <dt>Condicions</dt><dd>Acceptades el <?= e(data_hora($r['data_acceptacio'])) ?></dd>
        </dl>
        <h3>Pagaments</h3>
        <table class="taula compacta">
            <thead><tr><th>Data</th><th>Concepte</th><th>Import</th><th>Referència</th></tr></thead>
            <tbody>
            <?php foreach ($pagaments as $pg): ?>
                <tr>
                    <td><?= e(data_hora($pg['data_pagament'])) ?></td>
                    <td><?= e(['reserva' => 'Reserva', 'exces' => 'Excés de temps', 'reemborsament' => 'Reemborsament'][$pg['concepte']]) ?></td>
                    <td class="<?= $pg['concepte'] === 'reemborsament' ? 'negatiu' : '' ?>"><?= $pg['concepte'] === 'reemborsament' ? '−' : '' ?><?= e(euro($pg['import'])) ?></td>
                    <td class="mono petit"><?= e($pg['referencia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="targeta">
    <h2>Historial</h2>
    <ol class="historial">
        <?php foreach ($historial as $h): ?>
            <li>
                <span class="h-data"><?= e(data_hora($h['data_hora'])) ?></span>
                <span class="h-accio h-<?= e($h['accio']) ?>"><?= e(['creacio' => 'Creació', 'modificacio' => 'Modificació', 'canvi_estat' => 'Canvi d\'estat',
                    'cancellacio' => 'Cancel·lació', 'acces' => 'Accés'][$h['accio']] ?? $h['accio']) ?></span>
                <span><?= e($h['detall']) ?></span>
                <small><?= e($h['usuari'] ?? 'Sistema') ?></small>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

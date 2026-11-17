<h1>Administració</h1>
<?php require __DIR__ . '/_menu.php'; ?>

<section class="xifres">
    <div class="xifra"><strong><?= (int) $stats['reserves_avui'] ?></strong><span>reserves amb entrada avui</span></div>
    <div class="xifra"><strong><?= (int) $stats['en_curs'] ?></strong><span>vehicles amb reserva dins</span></div>
    <div class="xifra"><strong><?= (int) $stats['futures'] ?></strong><span>reserves futures</span></div>
    <div class="xifra"><strong><?= (int) $stats['clients'] ?></strong><span>clients actius</span></div>
    <div class="xifra"><strong><?= e(euro($stats['ingressos_mes'])) ?></strong><span>ingressos nets del mes</span></div>
</section>

<div class="graella-2">
    <div class="targeta">
        <div class="titol-seccio"><h2>Darreres reserves</h2><a href="/admin/reserves">Veure-les totes →</a></div>
        <div class="taula-wrap">
            <table class="taula compacta">
                <thead><tr><th>Número</th><th>Client</th><th>Pàrquing</th><th>Entrada</th><th>Estat</th></tr></thead>
                <tbody>
                <?php foreach ($darreres as $r): ?>
                    <tr>
                        <td><a class="mono" href="/admin/reserves/<?= (int) $r['id_reserva'] ?>"><?= e($r['numero_reserva']) ?></a></td>
                        <td><?= e($r['client']) ?></td>
                        <td><?= e($r['parquing']) ?></td>
                        <td><?= e(data_hora($r['entrada_prevista'])) ?></td>
                        <td><?= badge_estat($r['estat']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="targeta">
        <div class="titol-seccio"><h2>Ocupació actual</h2><a href="/admin/parquings">Gestionar →</a></div>
        <?php foreach ($parquings as $p):
            $pct = $p['places_operatives'] ? round(100 * $p['ocupades_ara'] / $p['places_operatives']) : 0; ?>
            <div class="ocupacio-fila">
                <a href="/admin/parquings/<?= (int) $p['id_parquing'] ?>"><?= e($p['nom']) ?></a>
                <div class="barra"><span style="width: <?= (int) $pct ?>%" class="<?= $pct > 80 ? 'baix' : ($pct > 50 ? 'mig' : 'alt') ?>"></span></div>
                <small><?= (int) $p['ocupades_ara'] ?>/<?= (int) $p['places_operatives'] ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>

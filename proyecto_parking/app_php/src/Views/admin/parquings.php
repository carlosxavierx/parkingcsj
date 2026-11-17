<?php use App\Models\Parquing; ?>
<div class="titol-seccio">
    <h1>Pàrquings i places</h1>
    <a class="boto" href="/admin/parquings/nou">+ Nou pàrquing</a>
</div>
<?php require __DIR__ . '/_menu.php'; ?>

<div class="taula-wrap targeta">
    <table class="taula">
        <thead><tr><th>Nom</th><th>Adreça</th><th>Tipus</th><th>Places totals</th><th>Reservables</th><th>Ocupades ara</th><th>Horari</th><th>Estat</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($parquings as $p): ?>
            <tr class="<?= $p['actiu'] ? '' : 'inactiu' ?>">
                <td><strong><?= e($p['nom']) ?></strong></td>
                <td><?= e($p['adreca']) ?>, <?= e($p['codi_postal']) ?></td>
                <td><?= e(Parquing::TIPUS[$p['tipus']] ?? $p['tipus']) ?></td>
                <td><?= (int) $p['places_totals'] ?></td>
                <td><?= (int) $p['places_operatives'] ?></td>
                <td><?= (int) $p['ocupades_ara'] ?></td>
                <td><?= e($p['horari']) ?></td>
                <td><?= $p['actiu'] ? '<span class="badge badge-completada">Actiu</span>' : '<span class="badge badge-cancellada">Inactiu</span>' ?></td>
                <td><a class="boto boto-petit boto-secundari" href="/admin/parquings/<?= (int) $p['id_parquing'] ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

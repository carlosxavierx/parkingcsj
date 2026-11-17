<?php use App\Models\Reserva; ?>
<h1>Reserves</h1>
<?php require __DIR__ . '/_menu.php'; ?>

<form method="get" action="/admin/reserves" class="targeta filtres">
    <label>Número, client o email <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="CSJ-2026…, nom, email"></label>
    <label>Matrícula <input name="matricula" value="<?= e($filtres['matricula']) ?>" class="input-matricula" placeholder="1234ABC"></label>
    <label>Pàrquing
        <select name="parquing"><option value="">Tots</option>
            <?php foreach ($parquings as $p): ?>
                <option value="<?= (int) $p['id_parquing'] ?>" <?= (string) $filtres['parquing'] === (string) $p['id_parquing'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Estat
        <select name="estat"><option value="">Tots</option>
            <?php foreach (Reserva::ESTATS as $es): ?>
                <option value="<?= e($es) ?>" <?= $filtres['estat'] === $es ? 'selected' : '' ?>><?= e(estat_reserva($es)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Entrada des de <input type="date" name="des_de" value="<?= e($filtres['des_de']) ?>"></label>
    <label>fins a <input type="date" name="fins_a" value="<?= e($filtres['fins_a']) ?>"></label>
    <div class="accions">
        <button class="boto" type="submit">Cercar</button>
        <a class="boto boto-secundari" href="/admin/reserves">Netejar</a>
    </div>
</form>

<p class="ajuda"><?= (int) $total ?> reserves trobades.</p>
<div class="taula-wrap targeta">
    <table class="taula">
        <thead><tr><th>Número</th><th>Client</th><th>Matrícula</th><th>Pàrquing</th><th>Plaça</th><th>Entrada</th><th>Sortida</th><th>Total</th><th>Estat</th></tr></thead>
        <tbody>
        <?php foreach ($reserves as $r): ?>
            <tr>
                <td><a class="mono" href="/admin/reserves/<?= (int) $r['id_reserva'] ?>"><?= e($r['numero_reserva']) ?></a></td>
                <td><?= e($r['client']) ?><br><small><?= e($r['email']) ?></small></td>
                <td><span class="matricula petita"><?= e($r['matricula']) ?></span></td>
                <td><?= e($r['parquing']) ?></td>
                <td><?= e($r['codi_placa']) ?></td>
                <td><?= e(data_hora($r['entrada_prevista'])) ?></td>
                <td><?= e(data_hora($r['sortida_prevista'])) ?></td>
                <td><?= e(euro($r['preu_total'])) ?></td>
                <td><?= badge_estat($r['estat']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reserves): ?><tr><td colspan="9" class="buit">Cap reserva coincideix amb la cerca.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pagines > 1): ?>
    <nav class="paginacio">
        <?php for ($i = 1; $i <= $pagines; $i++): ?>
            <a href="/admin/reserves?<?= e(http_build_query($filtres + ['pagina' => $i])) ?>" class="<?= $i === $pagina ? 'actiu' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

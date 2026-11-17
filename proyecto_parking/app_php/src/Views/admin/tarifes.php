<?php use App\Models\Tarifa; ?>
<h1>Tarifes</h1>
<?php require __DIR__ . '/_menu.php'; ?>

<div class="graella-2">
    <div class="targeta">
        <h2>Tarifes configurades</h2>
        <p class="ajuda">Les tarifes generals s'apliquen a tots els pàrquings. Una tarifa pròpia d'un pàrquing substitueix la general amb el mateix concepte.</p>
        <div class="taula-wrap">
            <table class="taula compacta">
                <thead><tr><th>Àmbit</th><th>Concepte</th><th>Valor</th><th>Activa</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tarifes as $t): ?>
                    <tr class="<?= $t['actiu'] ? '' : 'inactiu' ?>">
                        <td><?= $t['parquing'] ? e($t['parquing']) : '<strong>General</strong>' ?></td>
                        <td><?= e($t['descripcio']) ?></td>
                        <td colspan="3">
                            <form method="post" action="/admin/tarifes/<?= (int) $t['id_tarifa'] ?>" class="en-linia">
                                <?= csrf_field() ?>
                                <input type="number" step="0.01" min="0" name="valor" value="<?= e($t['valor']) ?>" class="curt">
                                <label class="check"><input type="checkbox" name="actiu" value="1" <?= $t['actiu'] ? 'checked' : '' ?> <?= $t['parquing'] ? '' : 'disabled checked' ?>> activa</label>
                                <?php if (!$t['parquing']): ?><input type="hidden" name="actiu" value="1"><?php endif; ?>
                                <button class="boto boto-petit" type="submit">Desar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <form method="post" action="/admin/tarifes" class="targeta">
        <h2>Tarifa pròpia d'un pàrquing</h2>
        <?= csrf_field() ?>
        <label>Pàrquing
            <select name="id_parquing" required>
                <?php foreach ($parquings as $p): ?><option value="<?= (int) $p['id_parquing'] ?>"><?= e($p['nom']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Concepte
            <select name="codi"><?php foreach (Tarifa::CODIS as $k => $d): ?><option value="<?= e($k) ?>"><?= e($d) ?></option><?php endforeach; ?></select>
        </label>
        <label>Valor <input type="number" step="0.01" min="0" name="valor" required></label>
        <button class="boto" type="submit">Desar tarifa pròpia</button>
        <p class="ajuda">Els canvis de tarifa només afecten les reserves noves: les ja fetes conserven el preu pagat.</p>
    </form>
</div>

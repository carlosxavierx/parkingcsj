<?php use App\Models\Usuari; ?>
<h1>Usuaris i rols</h1>
<?php require __DIR__ . '/_menu.php'; ?>

<form method="get" action="/admin/usuaris" class="targeta filtres">
    <label>Nom o email <input type="search" name="q" value="<?= e($q) ?>"></label>
    <label>Rol
        <select name="rol"><option value="">Tots</option>
            <?php foreach (Usuari::ROLS as $r): ?><option value="<?= e($r) ?>" <?= $rol === $r ? 'selected' : '' ?>><?= e(etiqueta_rol($r)) ?></option><?php endforeach; ?>
        </select>
    </label>
    <div class="accions"><button class="boto" type="submit">Cercar</button></div>
</form>

<div class="taula-wrap targeta">
    <table class="taula">
        <thead><tr><th>Usuari</th><th>Reserves</th><th>Registre / últim accés</th><th>Rol, pàrquing i estat</th></tr></thead>
        <tbody>
        <?php foreach ($usuaris as $us): $bloquejat = $us['bloquejat_fins'] && strtotime($us['bloquejat_fins']) > time(); ?>
            <tr class="<?= $us['actiu'] ? '' : 'inactiu' ?>">
                <td><strong><?= e(Usuari::nomComplet($us)) ?></strong><br><small><?= e($us['email']) ?></small>
                    <?php if ($bloquejat): ?><br><span class="badge badge-cancellada">Bloquejat fins a les <?= e(date('H:i', strtotime($us['bloquejat_fins']))) ?></span><?php endif; ?></td>
                <td><a href="/admin/reserves?q=<?= e(urlencode($us['email'])) ?>"><?= (int) $us['num_reserves'] ?></a></td>
                <td><small><?= e(data($us['data_registre'])) ?><br><?= e(data_hora($us['ultim_acces'])) ?></small></td>
                <td>
                    <form method="post" action="/admin/usuaris/<?= (int) $us['id_usuari'] ?>" class="en-linia">
                        <?= csrf_field() ?>
                        <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="filtre_rol" value="<?= e($rol) ?>">
                        <select name="rol" data-rol>
                            <?php foreach (Usuari::ROLS as $r): ?><option value="<?= e($r) ?>" <?= $us['rol'] === $r ? 'selected' : '' ?>><?= e(etiqueta_rol($r)) ?></option><?php endforeach; ?>
                        </select>
                        <select name="id_parquing" data-parquing-operador <?= $us['rol'] === 'operador' ? '' : 'hidden' ?>>
                            <option value="">— Pàrquing —</option>
                            <?php foreach ($parquings as $p): ?>
                                <option value="<?= (int) $p['id_parquing'] ?>" <?= $us['parquing'] === $p['nom'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="check"><input type="checkbox" name="actiu" value="1" <?= $us['actiu'] ? 'checked' : '' ?>> actiu</label>
                        <button class="boto boto-petit" type="submit">Desar</button>
                    </form>
                    <?php if ($bloquejat): ?>
                        <form method="post" action="/admin/usuaris/<?= (int) $us['id_usuari'] ?>/desbloquejar" class="en-linia"><?= csrf_field() ?>
                            <button class="boto boto-petit boto-secundari" type="submit">Desbloquejar</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="ajuda">Els usuaris no s'eliminen: es desactiven (no poden iniciar sessió però es conserva el seu historial de reserves).</p>

<?php use App\Models\Reserva; ?>
<?php require __DIR__ . '/_menu.php'; ?>
<div class="cap-reserva targeta">
    <div>
        <small>Número de reserva</small>
        <h1 class="mono"><?= e($r['numero_reserva']) ?></h1>
        <?= badge_estat($r['estat']) ?> <small>Creada el <?= e(data_hora($r['creada_a'])) ?> · modificada el <?= e(data_hora($r['modificada_a'])) ?></small>
    </div>
    <div class="accions">
        <a class="boto" href="/reserves/<?= (int) $r['id_reserva'] ?>/pdf" target="_blank">⬇ PDF</a>
        <a class="boto boto-secundari" href="/admin/reserves">Tornar a la cerca</a>
    </div>
</div>

<div class="graella-2">
    <?php if ($r['estat'] !== 'cancellada'): ?>
    <form method="post" action="/admin/reserves/<?= (int) $r['id_reserva'] ?>/estat" class="targeta">
        <h2>Canviar l'estat</h2>
        <?= csrf_field() ?>
        <label>Nou estat
            <select name="estat">
                <?php foreach (Reserva::ESTATS as $es): ?>
                    <option value="<?= e($es) ?>" <?= $es === $r['estat'] ? 'selected' : '' ?>><?= e(estat_reserva($es)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nota / motiu (queda a l'historial) <input name="nota" maxlength="200"></label>
        <p class="ajuda">Les reserves no s'eliminen mai. «Cancel·lada» és un esborrat lògic i reemborsa el preu de l'estada.</p>
        <button class="boto" type="submit">Desar estat</button>
    </form>

    <form method="post" action="/admin/reserves/<?= (int) $r['id_reserva'] ?>/modificar" class="targeta">
        <h2>Modificar la reserva</h2>
        <?= csrf_field() ?>
        <div class="fila-2">
            <label>Entrada <input type="datetime-local" name="entrada" value="<?= e(dt_input($r['entrada_prevista'])) ?>" required></label>
            <label>Sortida <input type="datetime-local" name="sortida" value="<?= e(dt_input($r['sortida_prevista'])) ?>" required></label>
        </div>
        <label>Observacions <input name="observacions" value="<?= e($r['observacions']) ?>" maxlength="255"></label>
        <p class="ajuda">Si canvien les dates es comprova la disponibilitat i es recalcula el preu: la diferència es cobra o es reemborsa.</p>
        <button class="boto boto-secundari" type="submit" <?= in_array($r['estat'], ['completada'], true) ? 'disabled' : '' ?>>Desar canvis</button>
    </form>
    <?php else: ?>
        <div class="targeta avis avis-info">Aquesta reserva està cancel·lada i ja no es pot modificar (es conserva per a l'historial).</div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/src/Views/reserves/_detall.php'; ?>

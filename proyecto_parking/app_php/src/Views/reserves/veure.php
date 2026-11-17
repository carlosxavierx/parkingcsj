<?php use App\Models\Reserva; ?>
<div class="cap-reserva targeta">
    <div>
        <small>Número de reserva</small>
        <h1 class="mono"><?= e($r['numero_reserva']) ?></h1>
        <?= badge_estat($r['estat']) ?>
    </div>
    <div class="accions">
        <?php if ($r['estat'] !== 'cancellada' && $pdf): ?>
            <a class="boto" href="/reserves/<?= (int) $r['id_reserva'] ?>/pdf" target="_blank">⬇ Justificant PDF</a>
        <?php endif; ?>
        <?php if (usuari()['rol'] === 'admin'): ?>
            <a class="boto boto-secundari" href="/admin/reserves/<?= (int) $r['id_reserva'] ?>">Gestionar</a>
        <?php endif; ?>
        <a class="boto boto-secundari" href="/reserves">Tornar</a>
    </div>
</div>

<?php require __DIR__ . '/_detall.php'; ?>

<?php if ((int) $r['id_usuari'] === (int) usuari()['id_usuari'] && Reserva::esCancellable($r) && strtotime($r['entrada_prevista']) > time()): ?>
    <form method="post" action="/reserves/<?= (int) $r['id_reserva'] ?>/cancellar" class="targeta zona-perill"
          data-confirmar="Segur que vols cancel·lar la reserva? Es reemborsarà <?= e(euro($r['preu_base'])) ?>.">
        <h2>Cancel·lar la reserva</h2>
        <?= csrf_field() ?>
        <p>Es reemborsarà el preu de l'estada (<?= e(euro($r['preu_base'])) ?>). Les despeses de gestió (<?= e(euro($r['despeses_gestio'])) ?>) no són reemborsables.</p>
        <label>Motiu (opcional) <input name="motiu" maxlength="200"></label>
        <button class="boto boto-perill" type="submit">Cancel·lar reserva</button>
    </form>
<?php endif; ?>

<div class="titol-seccio">
    <h1>Les meves reserves</h1>
    <a href="/reserves/nova" class="boto">+ Nova reserva</a>
</div>

<?php if (!$reserves): ?>
    <div class="targeta buit">Encara no has fet cap reserva. <a href="/reserves/nova">Fes-ne una ara</a>.</div>
<?php else: ?>
    <div class="taula-wrap targeta">
        <table class="taula">
            <thead><tr><th>Número</th><th>Pàrquing</th><th>Entrada</th><th>Sortida</th><th>Vehicle</th><th>Total</th><th>Estat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reserves as $r): ?>
                <tr>
                    <td><a href="/reserves/<?= (int) $r['id_reserva'] ?>" class="mono"><?= e($r['numero_reserva']) ?></a></td>
                    <td><?= e($r['parquing']) ?></td>
                    <td><?= e(data_hora($r['entrada_prevista'])) ?></td>
                    <td><?= e(data_hora($r['sortida_prevista'])) ?></td>
                    <td><span class="matricula petita"><?= e($r['matricula']) ?></span></td>
                    <td><?= e(euro($r['preu_total'])) ?></td>
                    <td><?= badge_estat($r['estat']) ?></td>
                    <td class="accions">
                        <a class="boto boto-petit boto-secundari" href="/reserves/<?= (int) $r['id_reserva'] ?>">Veure</a>
                        <?php if ($r['estat'] !== 'cancellada'): ?>
                            <a class="boto boto-petit boto-secundari" href="/reserves/<?= (int) $r['id_reserva'] ?>/pdf" target="_blank">PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

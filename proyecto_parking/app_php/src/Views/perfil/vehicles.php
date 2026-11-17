<?php use App\Models\Vehicle; ?>
<h1>Els meus vehicles</h1>
<?php require __DIR__ . '/_pestanyes.php'; ?>

<div class="graella-2">
    <div>
        <?php if (!$vehicles): ?>
            <div class="targeta buit">Encara no tens cap vehicle. Afegeix-ne un per poder reservar.</div>
        <?php endif; ?>
        <?php foreach ($vehicles as $v): ?>
            <details class="targeta vehicle">
                <summary>
                    <span class="matricula"><?= e($v['matricula']) ?></span>
                    <span><strong><?= e($v['marca']) ?> <?= e($v['model']) ?></strong>
                        <small><?= e(Vehicle::TIPUS[$v['tipus']] ?? $v['tipus']) ?><?= $v['color'] ? ' · ' . e($v['color']) : '' ?><?= $v['electric'] ? ' · ⚡ elèctric' : '' ?></small></span>
                    <span class="editar">Editar ▾</span>
                </summary>
                <form method="post" action="/vehicles/<?= (int) $v['id_vehicle'] ?>">
                    <?= csrf_field() ?>
                    <div class="fila-2">
                        <label>Marca <input name="marca" value="<?= e($v['marca']) ?>" required maxlength="50"></label>
                        <label>Model <input name="model" value="<?= e($v['model']) ?>" required maxlength="50"></label>
                    </div>
                    <div class="fila-2">
                        <label>Color <input name="color" value="<?= e($v['color']) ?>" maxlength="30"></label>
                        <label>Tipus
                            <select name="tipus"><?php foreach (Vehicle::TIPUS as $k => $t): ?>
                                <option value="<?= e($k) ?>" <?= $v['tipus'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label class="check"><input type="checkbox" name="electric" value="1" <?= $v['electric'] ? 'checked' : '' ?>> Vehicle elèctric</label>
                    <div class="accions">
                        <button class="boto boto-petit" type="submit">Desar</button>
                    </div>
                </form>
                <form method="post" action="/vehicles/<?= (int) $v['id_vehicle'] ?>/baixa" data-confirmar="Segur que vols donar de baixa el vehicle <?= e($v['matricula']) ?>?">
                    <?= csrf_field() ?>
                    <button class="boto boto-petit boto-perill" type="submit">Donar de baixa</button>
                </form>
            </details>
        <?php endforeach; ?>
    </div>

    <form method="post" action="/vehicles" class="targeta">
        <h2>Afegir vehicle</h2>
        <?= csrf_field() ?>
        <label>Matrícula * <input name="matricula" value="<?= e(old('matricula')) ?>" placeholder="1234ABC" required maxlength="12" class="input-matricula"></label>
        <div class="fila-2">
            <label>Marca * <input name="marca" value="<?= e(old('marca')) ?>" required maxlength="50"></label>
            <label>Model * <input name="model" value="<?= e(old('model')) ?>" required maxlength="50"></label>
        </div>
        <div class="fila-2">
            <label>Color <input name="color" value="<?= e(old('color')) ?>" maxlength="30"></label>
            <label>Tipus
                <select name="tipus"><?php foreach (Vehicle::TIPUS as $k => $t): ?>
                    <option value="<?= e($k) ?>" <?= old('tipus') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="check"><input type="checkbox" name="electric" value="1" <?= old('electric') ? 'checked' : '' ?>> Vehicle elèctric (preferència per places amb recàrrega)</label>
        <button class="boto" type="submit">Afegir vehicle</button>
    </form>
</div>

<?php
use App\Models\Parquing;
$v = fn (string $camp, $def = '') => old($camp, $p[$camp] ?? $def);
$serveis = isset($_SESSION['_old']) ? (array) ($_SESSION['_old']['serveis'] ?? []) : explode(',', (string) ($p['serveis'] ?? ''));
?>
<h1><?= $p ? e($p['nom']) : 'Nou pàrquing' ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>

<form method="post" action="<?= $p ? '/admin/parquings/' . (int) $p['id_parquing'] : '/admin/parquings' ?>" class="targeta">
    <h2>Dades del pàrquing</h2>
    <?= csrf_field() ?>
    <div class="fila-2">
        <label>Nom * <input name="nom" value="<?= e($v('nom')) ?>" required maxlength="100"></label>
        <label>Tipus
            <select name="tipus"><?php foreach (Parquing::TIPUS as $k => $t): ?>
                <option value="<?= e($k) ?>" <?= $v('tipus', 'superficie') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="fila-3">
        <label>Adreça * <input name="adreca" value="<?= e($v('adreca')) ?>" required maxlength="200"></label>
        <label>Codi postal * <input name="codi_postal" value="<?= e($v('codi_postal')) ?>" required pattern="\d{5}" maxlength="5"></label>
        <label>Telèfon <input name="telefon" value="<?= e($v('telefon')) ?>" maxlength="20"></label>
    </div>
    <div class="fila-3">
        <label>Latitud * <input name="latitud" value="<?= e($v('latitud', '41.1189')) ?>" required></label>
        <label>Longitud * <input name="longitud" value="<?= e($v('longitud', '1.2445')) ?>" required></label>
        <label>Horari <input name="horari" value="<?= e($v('horari', '24 h')) ?>" maxlength="40"></label>
    </div>
    <div class="fila-2">
        <label>Places totals * <input type="number" name="places_totals" min="1" max="5000" value="<?= e($v('places_totals', '100')) ?>" required></label>
        <label>Places reservables <input value="<?= (int) ($p['places_reservables'] ?? 0) ?>" disabled>
            <small>Es calcula automàticament amb les places operatives de la llista de sota.</small></label>
    </div>
    <label>Descripció <textarea name="descripcio" maxlength="500" rows="2"><?= e($v('descripcio')) ?></textarea></label>
    <fieldset class="serveis-check">
        <legend>Serveis</legend>
        <?php foreach (Parquing::SERVEIS as $s): ?>
            <label class="check"><input type="checkbox" name="serveis[]" value="<?= e($s) ?>" <?= in_array($s, $serveis, true) ? 'checked' : '' ?>> <?= e(etiqueta_servei($s)) ?></label>
        <?php endforeach; ?>
    </fieldset>
    <label class="check"><input type="checkbox" name="actiu" value="1" <?= ($p['actiu'] ?? 1) ? 'checked' : '' ?>> Pàrquing actiu (visible i reservable)</label>
    <button class="boto" type="submit"><?= $p ? 'Desar canvis' : 'Crear pàrquing' ?></button>
</form>

<?php if ($p): ?>
<div class="targeta" id="places">
    <div class="titol-seccio">
        <h2>Places reservables (<?= count($places) ?>)</h2>
        <form method="post" action="/admin/parquings/<?= (int) $p['id_parquing'] ?>/places" class="en-linia">
            <?= csrf_field() ?>
            <label>Afegir <input type="number" name="quantitat" value="1" min="1" max="50" class="curt"></label>
            <select name="tipus"><?php foreach (Parquing::TIPUS_PLACA as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            <input name="planta" placeholder="Planta" maxlength="5" class="curt">
            <button class="boto boto-petit" type="submit">+ Afegir (a partir de <?= e($seguentCodi) ?>)</button>
        </form>
    </div>
    <div class="graella-places">
        <?php foreach ($places as $pl): ?>
            <form method="post" action="/admin/places/<?= (int) $pl['id_placa'] ?>"
                  class="placa placa-<?= e($pl['tipus']) ?> <?= $pl['estat'] === 'fora_servei' ? 'fora' : '' ?> <?= $pl['reserva_ara'] ? 'ocupada' : '' ?>"
                  title="<?= $pl['reserva_ara'] ? 'Ocupada ara: ' . e($pl['reserva_ara']) : 'Lliure ara' ?>">
                <?= csrf_field() ?>
                <strong><?= e($pl['codi_placa']) ?></strong>
                <select name="tipus"><?php foreach (Parquing::TIPUS_PLACA as $k => $t): ?><option value="<?= e($k) ?>" <?= $pl['tipus'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
                <select name="estat">
                    <option value="disponible" <?= $pl['estat'] === 'disponible' ? 'selected' : '' ?>>Operativa</option>
                    <option value="fora_servei" <?= $pl['estat'] === 'fora_servei' ? 'selected' : '' ?>>Fora de servei</option>
                </select>
                <input type="hidden" name="planta" value="<?= e($pl['planta']) ?>">
                <button type="submit" class="boto boto-petit boto-secundari">Desar</button>
            </form>
        <?php endforeach; ?>
    </div>
    <p class="ajuda">Llegenda: vora taronja = ocupada ara · ratllada = fora de servei. Les places no s'eliminen mai: es posen fora de servei.</p>
</div>
<?php endif; ?>

<?php use App\Models\MetodePagament; use App\Models\Vehicle; ?>
<h1>Nova reserva</h1>

<form method="post" action="/reserves" class="graella-reserva" id="form-reserva"
      data-tarifes='<?= e(json_encode($tarifes)) ?>'>
    <?= csrf_field() ?>
    <div class="targeta">
        <h2>1. Pàrquing i horari</h2>
        <label>Pàrquing *
            <select name="id_parquing" id="r-parquing" required>
                <option value="">— Tria un pàrquing —</option>
                <?php foreach ($parquings as $p): ?>
                    <option value="<?= (int) $p['id_parquing'] ?>" data-horari="<?= e($p['horari']) ?>" data-adreca="<?= e($p['adreca']) ?>"
                        <?= (string) $sel['parquing'] === (string) $p['id_parquing'] ? 'selected' : '' ?>>
                        <?= e($p['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="ajuda" id="r-info-parquing"></p>
        <div class="fila-2">
            <label>Entrada *<input type="datetime-local" name="entrada" id="r-entrada" value="<?= e($sel['entrada']) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
            <label>Sortida *<input type="datetime-local" name="sortida" id="r-sortida" value="<?= e($sel['sortida']) ?>" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
        </div>

        <h2>2. Vehicle i pagament</h2>
        <label>Vehicle *
            <select name="id_vehicle" required>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= (int) $v['id_vehicle'] ?>" <?= (string) $sel['vehicle'] === (string) $v['id_vehicle'] ? 'selected' : '' ?>>
                        <?= e($v['matricula']) ?> · <?= e($v['marca'] . ' ' . $v['model']) ?> (<?= e(Vehicle::TIPUS[$v['tipus']]) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="ajuda"><a href="/vehicles">+ Afegir un altre vehicle</a></p>
        <label>Mètode de pagament *
            <select name="id_metode" required>
                <?php foreach ($metodes as $m): ?>
                    <option value="<?= (int) $m['id_metode'] ?>" <?= ((string) $sel['metode'] === (string) $m['id_metode'] || (!$sel['metode'] && $m['per_defecte'])) ? 'selected' : '' ?>>
                        <?= e(MetodePagament::descripcio($m)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="ajuda">Si surts més tard de l'hora prevista, l'excés es cobrarà amb aquest mateix mètode.</p>
    </div>

    <aside class="targeta resum-preu" id="resum">
        <h2>3. Resum i preu</h2>
        <div class="disponibilitat" id="r-disponibilitat">Tria pàrquing i horari…</div>
        <dl class="dades">
            <dt>Durada</dt><dd id="r-durada">—</dd>
            <dt>Preu de l'estada</dt><dd id="r-base">—</dd>
            <dt>Despeses de gestió (<span id="r-pct">10</span> %)</dt><dd id="r-gestio">—</dd>
        </dl>
        <div class="total">Total <strong id="r-total">—</strong></div>
        <p class="ajuda">Vista prèvia calculada al navegador. L'import definitiu el calcula el servidor en confirmar.</p>
        <label class="check"><input type="checkbox" name="condicions" value="1" required>
            He llegit i accepto les <a href="/condicions" target="_blank">condicions de la reserva</a> *</label>
        <button class="boto boto-gran" type="submit" id="r-boto">Confirmar i pagar</button>
    </aside>
</form>

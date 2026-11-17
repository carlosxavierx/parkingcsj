<?php use App\Models\MetodePagament; ?>
<h1>Mètodes de pagament</h1>
<?php require __DIR__ . '/_pestanyes.php'; ?>

<div class="graella-2">
    <div>
        <?php if (!$metodes): ?>
            <div class="targeta buit">Encara no tens cap mètode de pagament.</div>
        <?php endif; ?>
        <?php foreach ($metodes as $m): ?>
            <div class="targeta metode">
                <div class="metode-icona metode-<?= e($m['tipus']) ?>"><?= e(MetodePagament::TIPUS[$m['tipus']]) ?></div>
                <div class="metode-text">
                    <strong><?= e(MetodePagament::descripcio($m)) ?></strong>
                    <small><?= e($m['titular']) ?><?= $m['caducitat'] ? ' · caduca ' . e($m['caducitat']) : '' ?></small>
                    <?php if ($m['per_defecte']): ?><span class="badge badge-confirmada">Per defecte</span><?php endif; ?>
                </div>
                <div class="accions">
                    <?php if (!$m['per_defecte']): ?>
                        <form method="post" action="/pagaments/<?= (int) $m['id_metode'] ?>/defecte"><?= csrf_field() ?>
                            <button class="boto boto-petit boto-secundari" type="submit">Per defecte</button></form>
                    <?php endif; ?>
                    <form method="post" action="/pagaments/<?= (int) $m['id_metode'] ?>/baixa" data-confirmar="Segur que vols eliminar aquest mètode de pagament?"><?= csrf_field() ?>
                        <button class="boto boto-petit boto-perill" type="submit">Eliminar</button></form>
                </div>
            </div>
        <?php endforeach; ?>
        <p class="ajuda">🔒 De les targetes només guardem els 4 últims dígits; mai el número complet ni el CVV.
            Els pagaments d'aquesta aplicació són <strong>simulats</strong>.</p>
    </div>

    <form method="post" action="/pagaments" class="targeta" data-tipus-pagament>
        <h2>Afegir mètode</h2>
        <?= csrf_field() ?>
        <div class="selector-tipus">
            <?php foreach (MetodePagament::TIPUS as $k => $t): ?>
                <label><input type="radio" name="tipus" value="<?= e($k) ?>" <?= old('tipus', 'targeta') === $k ? 'checked' : '' ?>> <?= e($t) ?></label>
            <?php endforeach; ?>
        </div>
        <label>Titular * <input name="titular" value="<?= e(old('titular', trim(usuari()['nom'] . ' ' . usuari()['primer_cognom']))) ?>" required maxlength="100"></label>
        <div data-per="targeta">
            <label>Número de targeta <input name="numero_targeta" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" maxlength="23"></label>
            <div class="fila-2">
                <label>Caducitat (MM/AA) <input name="caducitat" value="<?= e(old('caducitat')) ?>" placeholder="12/28" maxlength="5" autocomplete="off"></label>
                <label>CVV <input name="cvv" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="123"></label>
            </div>
            <p class="ajuda">Per provar: 4242 4242 4242 4242 (Visa) o 5555 5555 5555 4444 (Mastercard).</p>
        </div>
        <div data-per="paypal" hidden>
            <label>Correu de PayPal <input type="email" name="email_paypal" value="<?= e(old('email_paypal')) ?>"></label>
        </div>
        <div data-per="bizum" hidden>
            <label>Telèfon mòbil de Bizum <input type="tel" name="telefon_bizum" value="<?= e(old('telefon_bizum')) ?>" placeholder="600123456"></label>
        </div>
        <button class="boto" type="submit">Afegir mètode de pagament</button>
    </form>
</div>

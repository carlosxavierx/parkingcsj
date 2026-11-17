<h1>El meu perfil</h1>
<?php require __DIR__ . '/_pestanyes.php'; ?>

<div class="graella-2">
    <form method="post" action="/perfil" class="targeta">
        <h2>Dades personals</h2>
        <?= csrf_field() ?>
        <div class="fila-2">
            <label>Nom * <input name="nom" value="<?= e(old('nom', $u['nom'])) ?>" maxlength="50" required></label>
            <label>Primer cognom * <input name="primer_cognom" value="<?= e(old('primer_cognom', $u['primer_cognom'])) ?>" maxlength="50" required></label>
        </div>
        <label>Segon cognom <input name="segon_cognom" value="<?= e(old('segon_cognom', $u['segon_cognom'])) ?>" maxlength="50"></label>
        <label>Correu electrònic * <input type="email" name="email" value="<?= e(old('email', $u['email'])) ?>" required></label>
        <div class="fila-2">
            <label>Telèfon <input type="tel" name="telefon" value="<?= e(old('telefon', $telefon)) ?>"></label>
            <label>DNI / NIE <input name="dni" value="<?= e(old('dni', $dni)) ?>" maxlength="9"></label>
        </div>
        <p class="ajuda">🔒 El telèfon i el DNI es guarden xifrats a la base de dades.</p>
        <button class="boto" type="submit">Desar canvis</button>
    </form>

    <div>
        <form method="post" action="/perfil/contrasenya" class="targeta" id="contrasenya">
            <h2>Canviar contrasenya</h2>
            <?= csrf_field() ?>
            <label>Contrasenya actual <input type="password" name="password_actual" required autocomplete="current-password"></label>
            <label>Nova contrasenya <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Repeteix la nova contrasenya <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
            <button class="boto boto-secundari" type="submit">Canviar contrasenya</button>
        </form>
        <div class="targeta">
            <h2>Compte</h2>
            <dl class="dades">
                <dt>Rol</dt><dd><span class="rol rol-<?= e($u['rol']) ?>"><?= e(etiqueta_rol($u['rol'])) ?></span></dd>
                <dt>Registrat el</dt><dd><?= e(data_hora($u['data_registre'])) ?></dd>
                <dt>Últim accés</dt><dd><?= e(data_hora($u['ultim_acces'])) ?></dd>
            </dl>
        </div>
    </div>
</div>

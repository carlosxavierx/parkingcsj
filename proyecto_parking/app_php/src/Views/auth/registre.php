<section class="formulari-centrat ample">
    <form method="post" action="/registre" class="targeta">
        <h1>Crear compte</h1>
        <p class="ajuda">El registre és obligatori per reservar. Els camps amb * són obligatoris.</p>
        <?= csrf_field() ?>
        <div class="fila-3">
            <label>Nom * <input name="nom" value="<?= e(old('nom')) ?>" maxlength="50" required></label>
            <label>Primer cognom * <input name="primer_cognom" value="<?= e(old('primer_cognom')) ?>" maxlength="50" required></label>
            <label>Segon cognom <input name="segon_cognom" value="<?= e(old('segon_cognom')) ?>" maxlength="50"></label>
        </div>
        <label>Correu electrònic * <input type="email" name="email" value="<?= e(old('email')) ?>" maxlength="120" required autocomplete="email"></label>
        <div class="fila-2">
            <label>Telèfon <input type="tel" name="telefon" value="<?= e(old('telefon')) ?>" placeholder="600123456"></label>
            <label>DNI / NIE <input name="dni" value="<?= e(old('dni')) ?>" placeholder="12345678Z" maxlength="9"></label>
        </div>
        <p class="ajuda">🔒 El telèfon i el DNI es guarden xifrats (AES-256).</p>
        <div class="fila-2">
            <label>Contrasenya * <input type="password" name="password" required minlength="8" autocomplete="new-password"
                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Mínim 8 caràcters amb majúscules, minúscules i xifres"></label>
            <label>Repeteix la contrasenya * <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
        </div>
        <p class="ajuda">Mínim 8 caràcters, amb majúscules, minúscules i xifres.</p>
        <label class="check"><input type="checkbox" name="privacitat" value="1" required>
            Accepto les <a href="/condicions" target="_blank">condicions d'ús i la política de privacitat</a> *</label>
        <button class="boto boto-gran" type="submit">Crear compte</button>
        <p class="ajuda centrat">Ja tens compte? <a href="/login">Inicia sessió</a></p>
    </form>
</section>

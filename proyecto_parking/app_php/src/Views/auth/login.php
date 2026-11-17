<section class="formulari-centrat">
    <form method="post" action="/login" class="targeta">
        <img src="/img/logo-mark.png" alt="" class="form-logo">
        <h1>Iniciar sessió</h1>
        <?= csrf_field() ?>
        <label>Correu electrònic
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username">
        </label>
        <label>Contrasenya
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="boto boto-gran" type="submit">Entrar</button>
        <p class="ajuda centrat">No tens compte? <a href="/registre">Crea'n un</a></p>
        <p class="ajuda centrat">Per seguretat, després de 5 intents fallits el compte es bloqueja 15 minuts.</p>
    </form>
</section>

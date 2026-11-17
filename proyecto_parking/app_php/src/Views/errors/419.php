<section class="pagina-error">
    <div class="codi-error">419</div>
    <h1>La sessió ha caducat</h1>
    <p><?= e($missatge ?? "El formulari ha caducat per seguretat (token CSRF). Torna enrere, recarrega la pàgina i torna-ho a provar.") ?></p>
    <a href="/" class="boto">Tornar a l'inici</a>
</section>

<nav class="pestanyes">
    <a href="/admin" class="<?= ($_SERVER['REQUEST_URI'] ?? '') === '/admin' ? 'actiu' : '' ?>">Tauler</a>
    <a href="/admin/reserves" class="<?= ruta_activa('/admin/reserves') ?>">Reserves</a>
    <a href="/admin/parquings" class="<?= ruta_activa('/admin/parquings') ?>">Pàrquings i places</a>
    <a href="/admin/tarifes" class="<?= ruta_activa('/admin/tarifes') ?>">Tarifes</a>
    <a href="/admin/usuaris" class="<?= ruta_activa('/admin/usuaris') ?>">Usuaris i rols</a>
    <a href="/operador">Lector de matrícules</a>
</nav>

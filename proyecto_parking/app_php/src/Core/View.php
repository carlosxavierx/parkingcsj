<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderitza una vista de src/Views dins de la plantilla general (layout.php).
 * Les vistes són PHP pla i fan servir e() per escapar TOTA la sortida.
 */
final class View
{
    public static function render(string $vista, array $dades = [], bool $ambLayout = true): void
    {
        $fitxer = APP_ROOT . '/src/Views/' . $vista . '.php';
        if (!is_file($fitxer)) {
            throw new \RuntimeException("Vista no trobada: $vista");
        }
        extract($dades, EXTR_SKIP);
        ob_start();
        require $fitxer;
        $contingut = ob_get_clean();

        if ($ambLayout) {
            require APP_ROOT . '/src/Views/layout.php';
        } else {
            echo $contingut;
        }
        netejar_old();
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protecció CSRF: cada sessió té un token aleatori que s'inclou en tots els
 * formularis (camp ocult _csrf) i es comprova en totes les peticions POST.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $enviat = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($enviat) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $enviat)) {
            http_response_code(419);
            View::render('errors/419', ['titol' => 'Sessió caducada']);
            exit;
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sessió segura: galeta només per HTTPS, inaccessible des de JavaScript,
 * SameSite=Strict i caducitat per inactivitat (30 minuts).
 */
final class Session
{
    private const INACTIVITAT = 1800;

    public static function start(): void
    {
        session_name('CSJSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        $ara = time();
        if (isset($_SESSION['_ultima_activitat']) && $ara - $_SESSION['_ultima_activitat'] > self::INACTIVITAT
            && isset($_SESSION['id_usuari'])) {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('info', 'La sessió ha caducat per inactivitat. Torna a iniciar sessió.');
        }
        $_SESSION['_ultima_activitat'] = $ara;
    }

    /** Nou identificador de sessió (evita la fixació de sessió en iniciar sessió). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'secure' => true,
            'httponly' => true, 'samesite' => 'Strict',
        ]);
        session_destroy();
    }
}

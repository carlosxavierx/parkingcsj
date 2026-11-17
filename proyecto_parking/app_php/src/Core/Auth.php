<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autenticació i control d'accés per rols (client, operador, admin).
 * L'usuari es torna a llegir de la BD a cada petició: si un administrador
 * el desactiva o li canvia el rol, té efecte immediatament.
 */
final class Auth
{
    public const MAX_INTENTS = 5;
    public const MINUTS_BLOQUEIG = 15;

    private static ?array $usuari = null;
    private static bool $carregat = false;

    public static function user(): ?array
    {
        if (!self::$carregat) {
            self::$carregat = true;
            $id = $_SESSION['id_usuari'] ?? null;
            if ($id) {
                $u = Database::one('SELECT * FROM usuaris WHERE id_usuari = ? AND actiu = 1', [$id]);
                if ($u) {
                    unset($u['password_hash']);
                    self::$usuari = $u;
                } else {
                    unset($_SESSION['id_usuari']);
                }
            }
        }
        return self::$usuari;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id_usuari']) ? (int) self::user()['id_usuari'] : null;
    }

    public static function login(array $usuari): void
    {
        Session::regenerate();
        $_SESSION['id_usuari'] = (int) $usuari['id_usuari'];
        self::$carregat = false;
    }

    public static function logout(): void
    {
        // Es buida la sessió i es canvia l'identificador (l'antic deixa de ser vàlid)
        $_SESSION = [];
        Session::regenerate();
        self::$usuari = null;
        self::$carregat = true;
    }

    /** Obliga a tenir sessió iniciada (registre obligatori per a qualsevol acció). */
    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            $_SESSION['_despres_login'] = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '/') : '/';
            flash('info', 'Has d\'iniciar sessió per continuar.');
            redirect('/login');
        }
        return $u;
    }

    /** Obliga a tenir un dels rols indicats. */
    public static function requireRole(string ...$rols): array
    {
        $u = self::requireLogin();
        if (!in_array($u['rol'], $rols, true)) {
            http_response_code(403);
            View::render('errors/403', ['titol' => 'Accés denegat']);
            exit;
        }
        return $u;
    }

    public static function is(string ...$rols): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['rol'], $rols, true);
    }
}

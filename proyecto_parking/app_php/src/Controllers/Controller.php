<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/** Classe base dels controladors. */
abstract class Controller
{
    protected function view(string $vista, array $dades = []): void
    {
        View::render($vista, $dades);
    }

    /** Si hi ha errors de validació: es guarden les dades, es mostren els errors i es torna enrere. */
    protected function tornarSiErrors(array $errors, string $desti): void
    {
        if ($errors) {
            guardar_old($_POST);
            foreach ($errors as $e) {
                flash('error', $e);
            }
            redirect($desti);
        }
    }

    protected function json(array $dades, int $codi = 200): never
    {
        http_response_code($codi);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dades, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function noTrobat(): never
    {
        http_response_code(404);
        View::render('errors/404', ['titol' => 'No trobat']);
        exit;
    }

    /** Validació de contrasenya: mínim 8 caràcters amb majúscula, minúscula i xifra. */
    protected function passwordValida(string $p): bool
    {
        return mb_strlen($p) >= 8 && preg_match('/[A-Z]/', $p) && preg_match('/[a-z]/', $p) && preg_match('/\d/', $p);
    }

    /** DNI (12345678Z) o NIE (X1234567L) amb la lletra de control correcta. */
    protected function dniValid(string $dni): bool
    {
        if (!preg_match('/^([XYZ]|\d)\d{7}[A-Z]$/', $dni)) {
            return false;
        }
        $num = strtr(substr($dni, 0, 8), ['X' => '0', 'Y' => '1', 'Z' => '2']);
        return 'TRWAGMYFPDXBNJZSQVHLCKE'[(int) $num % 23] === substr($dni, -1);
    }
}

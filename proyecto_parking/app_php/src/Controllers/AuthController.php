<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Usuari;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect('/');
        }
        $this->view('auth/login', ['titol' => 'Iniciar sessió']);
    }

    /**
     * Inici de sessió:
     *  - missatge d'error genèric (no revela si l'email existeix),
     *  - bloqueig de 15 minuts després de 5 intents fallits,
     *  - session_regenerate_id() per evitar la fixació de sessió.
     */
    public function login(): void
    {
        $email = mb_strtolower(post('email'));
        $password = $_POST['password'] ?? '';
        $u = Usuari::findByEmail($email);

        if ($u && $u['bloquejat_fins'] && strtotime($u['bloquejat_fins']) > time()) {
            $minuts = (int) ceil((strtotime($u['bloquejat_fins']) - time()) / 60);
            guardar_old(['email' => $email]);
            flash('error', "Compte bloquejat temporalment per massa intents fallits. Torna-ho a provar d'aquí a $minuts minuts.");
            redirect('/login');
        }

        if (!$u || !$u['actiu'] || !is_string($password) || !password_verify($password, $u['password_hash'])) {
            if ($u && $u['actiu']) {
                Usuari::intentFallit($u, Auth::MAX_INTENTS, Auth::MINUTS_BLOQUEIG);
            }
            guardar_old(['email' => $email]);
            flash('error', 'Correu electrònic o contrasenya incorrectes.');
            redirect('/login');
        }

        $nouHash = password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null;
        Usuari::loginCorrecte((int) $u['id_usuari'], $nouHash);
        $desti = $_SESSION['_despres_login'] ?? null;
        Auth::login($u);
        unset($_SESSION['_despres_login']);
        flash('ok', 'Hola, ' . $u['nom'] . '!');

        if (!$desti || !str_starts_with($desti, '/') || str_starts_with($desti, '//')) {
            $desti = match ($u['rol']) {
                'admin'    => '/admin',
                'operador' => '/operador',
                default    => '/',
            };
        }
        redirect($desti);
    }

    public function registreForm(): void
    {
        if (Auth::user()) {
            redirect('/');
        }
        $this->view('auth/registre', ['titol' => 'Crear compte']);
    }

    public function registre(): void
    {
        $d = [
            'nom'           => post('nom'),
            'primer_cognom' => post('primer_cognom'),
            'segon_cognom'  => post('segon_cognom'),
            'email'         => mb_strtolower(post('email')),
            'telefon'       => preg_replace('/[^\d+]/', '', post('telefon')),
            'dni'           => mb_strtoupper(preg_replace('/[\s-]/', '', post('dni'))),
            'password'      => (string) ($_POST['password'] ?? ''),
        ];
        $errors = [];
        if ($d['nom'] === '' || $d['primer_cognom'] === '') {
            $errors[] = 'El nom i el primer cognom són obligatoris.';
        }
        if (mb_strlen($d['nom']) > 50 || mb_strlen($d['primer_cognom']) > 50 || mb_strlen($d['segon_cognom']) > 50) {
            $errors[] = 'El nom i els cognoms poden tenir com a màxim 50 caràcters.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 120) {
            $errors[] = 'El correu electrònic no és vàlid.';
        } elseif (Usuari::emailExisteix($d['email'])) {
            $errors[] = 'Ja existeix un compte amb aquest correu electrònic.';
        }
        if ($d['telefon'] !== '' && !preg_match('/^\+?\d{9,15}$/', $d['telefon'])) {
            $errors[] = 'El telèfon no és vàlid.';
        }
        if ($d['dni'] !== '') {
            if (!$this->dniValid($d['dni'])) {
                $errors[] = 'El DNI/NIE no és vàlid (comprova la lletra).';
            } elseif (Usuari::dniExisteix($d['dni'])) {
                $errors[] = 'Aquest DNI/NIE ja està registrat.';
            }
        }
        if (!$this->passwordValida($d['password'])) {
            $errors[] = 'La contrasenya ha de tenir almenys 8 caràcters, amb majúscules, minúscules i xifres.';
        } elseif ($d['password'] !== ($_POST['password2'] ?? '')) {
            $errors[] = 'Les contrasenyes no coincideixen.';
        }
        if (empty($_POST['privacitat'])) {
            $errors[] = 'Has d\'acceptar les condicions d\'ús i la política de privacitat.';
        }
        $this->tornarSiErrors($errors, '/registre');

        $id = Usuari::crear($d);
        Auth::login(Usuari::find($id));
        Usuari::loginCorrecte($id);
        flash('ok', 'Compte creat correctament. Afegeix un vehicle i un mètode de pagament per poder reservar.');
        redirect('/vehicles');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('ok', 'Has tancat la sessió.');
        redirect('/');
    }
}

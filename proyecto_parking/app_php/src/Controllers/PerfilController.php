<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database as DB;
use App\Models\Usuari;

final class PerfilController extends Controller
{
    public function index(): void
    {
        $u = Auth::requireLogin();
        $this->view('perfil/index', [
            'titol'   => 'El meu perfil',
            'u'       => $u,
            // Les dades xifrades només es desxifren per mostrar-les al seu propietari
            'telefon' => Crypto::decrypt($u['telefon_xifrat']),
            'dni'     => Crypto::decrypt($u['dni_xifrat']),
        ]);
    }

    public function actualitzar(): void
    {
        $u = Auth::requireLogin();
        $d = [
            'nom'           => post('nom'),
            'primer_cognom' => post('primer_cognom'),
            'segon_cognom'  => post('segon_cognom'),
            'email'         => mb_strtolower(post('email')),
            'telefon'       => preg_replace('/[^\d+]/', '', post('telefon')),
            'dni'           => mb_strtoupper(preg_replace('/[\s-]/', '', post('dni'))),
        ];
        $errors = [];
        if ($d['nom'] === '' || $d['primer_cognom'] === '' || mb_strlen($d['nom']) > 50 || mb_strlen($d['primer_cognom']) > 50) {
            $errors[] = 'El nom i el primer cognom són obligatoris (màxim 50 caràcters).';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correu electrònic no és vàlid.';
        } elseif (Usuari::emailExisteix($d['email'], (int) $u['id_usuari'])) {
            $errors[] = 'Aquest correu electrònic ja el fa servir un altre compte.';
        }
        if ($d['telefon'] !== '' && !preg_match('/^\+?\d{9,15}$/', $d['telefon'])) {
            $errors[] = 'El telèfon no és vàlid.';
        }
        if ($d['dni'] !== '') {
            if (!$this->dniValid($d['dni'])) {
                $errors[] = 'El DNI/NIE no és vàlid (comprova la lletra).';
            } elseif (Usuari::dniExisteix($d['dni'], (int) $u['id_usuari'])) {
                $errors[] = 'Aquest DNI/NIE ja està registrat en un altre compte.';
            }
        }
        $this->tornarSiErrors($errors, '/perfil');

        Usuari::actualitzarPerfil((int) $u['id_usuari'], $d);
        flash('ok', 'Dades personals actualitzades.');
        redirect('/perfil');
    }

    public function contrasenya(): void
    {
        $u = Auth::requireLogin();
        $hash = (string) DB::value('SELECT password_hash FROM usuaris WHERE id_usuari = ?', [$u['id_usuari']]);
        $nova = (string) ($_POST['password'] ?? '');
        $errors = [];
        if (!password_verify((string) ($_POST['password_actual'] ?? ''), $hash)) {
            $errors[] = 'La contrasenya actual no és correcta.';
        }
        if (!$this->passwordValida($nova)) {
            $errors[] = 'La nova contrasenya ha de tenir almenys 8 caràcters, amb majúscules, minúscules i xifres.';
        } elseif ($nova !== ($_POST['password2'] ?? '')) {
            $errors[] = 'Les contrasenyes noves no coincideixen.';
        }
        $this->tornarSiErrors($errors, '/perfil#contrasenya');

        Usuari::canviarPassword((int) $u['id_usuari'], $nova);
        \App\Core\Session::regenerate();
        flash('ok', 'Contrasenya canviada correctament.');
        redirect('/perfil');
    }
}

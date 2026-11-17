<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Models\MetodePagament;

/**
 * Mètodes de pagament simulats. El número de targeta i el CVV es validen però
 * MAI es guarden: només els 4 últims dígits, la marca i la caducitat.
 */
final class PagamentController extends Controller
{
    public function index(): void
    {
        $u = Auth::requireLogin();
        $this->view('perfil/pagaments', [
            'titol'   => 'Mètodes de pagament',
            'metodes' => MetodePagament::perUsuari((int) $u['id_usuari']),
        ]);
    }

    public function crear(): void
    {
        $u = Auth::requireLogin();
        $tipus = post('tipus');
        $d = ['tipus' => $tipus, 'titular' => post('titular')];
        $errors = [];
        if (!array_key_exists($tipus, MetodePagament::TIPUS)) {
            $errors[] = 'Tipus de pagament no vàlid.';
        }
        if ($d['titular'] === '' || mb_strlen($d['titular']) > 100) {
            $errors[] = 'El nom del titular és obligatori.';
        }

        if ($tipus === 'targeta') {
            $numero = preg_replace('/\D/', '', post('numero_targeta'));
            $caducitat = post('caducitat');
            if (strlen($numero) < 13 || strlen($numero) > 19 || !MetodePagament::luhn($numero)) {
                $errors[] = 'El número de targeta no és vàlid.';
            }
            if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $caducitat, $m)
                || mktime(0, 0, 0, (int) $m[1] + 1, 1, 2000 + (int) $m[2]) <= time()) {
                $errors[] = 'La data de caducitat no és vàlida o la targeta està caducada (format MM/AA).';
            }
            if (!preg_match('/^\d{3,4}$/', post('cvv'))) {
                $errors[] = 'El CVV no és vàlid.';
            }
            $d += ['marca_targeta' => MetodePagament::marcaTargeta($numero), 'ultims_digits' => substr($numero, -4), 'caducitat' => $caducitat];
            unset($numero);   // el número complet no surt d'aquesta funció
        } elseif ($tipus === 'paypal') {
            $d['referencia'] = mb_strtolower(post('email_paypal'));
            if (!filter_var($d['referencia'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El correu de PayPal no és vàlid.';
            }
        } elseif ($tipus === 'bizum') {
            $d['referencia'] = preg_replace('/\D/', '', post('telefon_bizum'));
            if (!preg_match('/^[67]\d{8}$/', $d['referencia'])) {
                $errors[] = 'El telèfon de Bizum ha de ser un mòbil espanyol de 9 xifres.';
            }
        }
        $this->tornarSiErrors($errors, '/pagaments');

        MetodePagament::crear((int) $u['id_usuari'], $d);
        flash('ok', 'Mètode de pagament afegit (pagament simulat: no es fa cap càrrec real).');
        redirect($_SESSION['_despres_vehicle'] ?? '/pagaments');
    }

    public function perDefecte(int $id): void
    {
        $u = Auth::requireLogin();
        MetodePagament::delUsuari($id, (int) $u['id_usuari']) ?? $this->noTrobat();
        MetodePagament::marcarPerDefecte($id, (int) $u['id_usuari']);
        flash('ok', 'Mètode de pagament per defecte actualitzat.');
        redirect('/pagaments');
    }

    public function baixa(int $id): void
    {
        $u = Auth::requireLogin();
        MetodePagament::delUsuari($id, (int) $u['id_usuari']) ?? $this->noTrobat();
        $actives = (int) DB::value("SELECT COUNT(*) FROM reserves WHERE id_metode_pagament = ? AND estat IN ('pendent','confirmada','en_curs')", [$id]);
        if ($actives) {
            flash('error', 'Aquest mètode té reserves actives (s\'hi cobraria l\'excés de temps). No es pot eliminar ara.');
            redirect('/pagaments');
        }
        MetodePagament::desactivar($id, (int) $u['id_usuari']);
        flash('ok', 'Mètode de pagament eliminat.');
        redirect('/pagaments');
    }
}

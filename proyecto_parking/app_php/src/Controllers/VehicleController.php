<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Vehicle;

final class VehicleController extends Controller
{
    public function index(): void
    {
        $u = Auth::requireLogin();
        $this->view('perfil/vehicles', [
            'titol'    => 'Els meus vehicles',
            'vehicles' => Vehicle::perUsuari((int) $u['id_usuari']),
        ]);
    }

    public function crear(): void
    {
        $u = Auth::requireLogin();
        $d = $this->dades();
        $d['matricula'] = normalitzar_matricula(post('matricula'));
        $errors = $this->validar($d);
        if (!Vehicle::matriculaValida($d['matricula'])) {
            $errors[] = 'La matrícula no és vàlida (exemple: 1234ABC).';
        } else {
            $existent = Vehicle::perMatricula($d['matricula']);
            if ($existent && ((int) $existent['id_usuari'] !== (int) $u['id_usuari'] || $existent['actiu'])) {
                $errors[] = 'Aquesta matrícula ja està registrada.';
            }
        }
        $this->tornarSiErrors($errors, '/vehicles');

        Vehicle::crear((int) $u['id_usuari'], $d);
        flash('ok', 'Vehicle ' . $d['matricula'] . ' afegit.');
        redirect($_SESSION['_despres_vehicle'] ?? '/vehicles');
    }

    public function actualitzar(int $id): void
    {
        $u = Auth::requireLogin();
        $v = Vehicle::delUsuari($id, (int) $u['id_usuari']) ?? $this->noTrobat();
        $d = $this->dades();
        $this->tornarSiErrors($this->validar($d), '/vehicles');
        Vehicle::actualitzar((int) $v['id_vehicle'], $d);
        flash('ok', 'Vehicle ' . $v['matricula'] . ' actualitzat.');
        redirect('/vehicles');
    }

    /** Baixa lògica: el vehicle es desactiva (es conserva per a l'historial de reserves). */
    public function baixa(int $id): void
    {
        $u = Auth::requireLogin();
        $v = Vehicle::delUsuari($id, (int) $u['id_usuari']) ?? $this->noTrobat();
        if (Vehicle::teReservesActives($id)) {
            flash('error', 'No es pot donar de baixa un vehicle amb reserves actives. Cancel·la-les primer.');
            redirect('/vehicles');
        }
        Vehicle::desactivar($id);
        flash('ok', 'Vehicle ' . $v['matricula'] . ' donat de baixa.');
        redirect('/vehicles');
    }

    private function dades(): array
    {
        return [
            'marca'    => post('marca'),
            'model'    => post('model'),
            'color'    => post('color'),
            'tipus'    => post('tipus', 'cotxe'),
            'electric' => empty($_POST['electric']) ? 0 : 1,
        ];
    }

    private function validar(array $d): array
    {
        $errors = [];
        if ($d['marca'] === '' || $d['model'] === '' || mb_strlen($d['marca']) > 50 || mb_strlen($d['model']) > 50) {
            $errors[] = 'La marca i el model són obligatoris (màxim 50 caràcters).';
        }
        if (mb_strlen($d['color']) > 30) {
            $errors[] = 'El color pot tenir com a màxim 30 caràcters.';
        }
        if (!array_key_exists($d['tipus'], Vehicle::TIPUS)) {
            $errors[] = 'Tipus de vehicle no vàlid.';
        }
        return $errors;
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Models\Historial;
use App\Models\MetodePagament;
use App\Models\Parquing;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Usuari;

/**
 * Panel d'administració (només rol admin): pàrquings i places, tarifes,
 * totes les reserves (cerca avançada, modificació i canvi d'estat) i usuaris/rols.
 */
final class AdminController extends Controller
{
    public function __construct()
    {
        Auth::requireRole('admin');
    }

    public function index(): void
    {
        $this->view('admin/index', [
            'titol'     => 'Administració',
            'stats'     => Reserva::estadistiques(),
            'parquings' => Parquing::ambOcupacio(),
            'darreres'  => DB::all('SELECT * FROM v_reserves_detall ORDER BY creada_a DESC LIMIT 8'),
        ]);
    }

    // ================================================================ reserves

    public function reserves(): void
    {
        $filtres = [
            'q' => get('q'), 'matricula' => get('matricula'), 'parquing' => get('parquing'),
            'estat' => get('estat'), 'des_de' => get('des_de'), 'fins_a' => get('fins_a'),
        ];
        foreach (['des_de', 'fins_a'] as $c) {
            if ($filtres[$c] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres[$c])) {
                $filtres[$c] = '';
            }
        }
        $pagina = max(1, (int) get('pagina', '1'));
        $res = Reserva::cercar($filtres, $pagina);
        $this->view('admin/reserves', [
            'titol'     => 'Reserves',
            'filtres'   => $filtres,
            'reserves'  => $res['files'],
            'total'     => $res['total'],
            'pagina'    => $pagina,
            'pagines'   => max(1, (int) ceil($res['total'] / Reserva::PER_PAGINA)),
            'parquings' => Parquing::tots(),
        ]);
    }

    public function reserva(int $id): void
    {
        $r = Reserva::find($id) ?? $this->noTrobat();
        $this->view('admin/reserva', [
            'titol'     => 'Reserva ' . $r['numero_reserva'],
            'r'         => $r,
            'pagaments' => Reserva::pagaments($id),
            'historial' => Historial::deReserva($id),
            'metode'    => MetodePagament::descripcio(['tipus' => $r['metode_tipus'], 'marca_targeta' => $r['marca_targeta'],
                'ultims_digits' => $r['ultims_digits'], 'referencia_xifrada' => $r['referencia_xifrada']]),
        ]);
    }

    public function reservaEstat(int $id): void
    {
        $r = Reserva::find($id) ?? $this->noTrobat();
        $nou = post('estat');
        $nota = mb_substr(post('nota'), 0, 200);
        if (!in_array($nou, Reserva::ESTATS, true) || $nou === $r['estat']) {
            $this->tornarSiErrors(['Selecciona un estat diferent de l\'actual.'], "/admin/reserves/$id");
        }
        if ($r['estat'] === 'cancellada') {
            $this->tornarSiErrors(['Una reserva cancel·lada no es pot reactivar: el client n\'ha de fer una de nova.'], "/admin/reserves/$id");
        }
        Reserva::canviarEstat($r, $nou, (int) Auth::id(), $nota);
        flash('ok', 'Estat canviat a «' . estat_reserva($nou) . '».');
        redirect("/admin/reserves/$id");
    }

    public function reservaModificar(int $id): void
    {
        $r = Reserva::find($id) ?? $this->noTrobat();
        $entrada = parse_datetime(post('entrada'));
        $sortida = parse_datetime(post('sortida'));
        $errors = [];
        if (in_array($r['estat'], ['completada', 'cancellada'], true)) {
            $errors[] = 'No es pot modificar una reserva completada o cancel·lada.';
        }
        if (!$entrada || !$sortida || strtotime($sortida) - strtotime($entrada) < 1800) {
            $errors[] = 'Les dates no són vàlides (mínim 30 minuts).';
        }
        $this->tornarSiErrors($errors, "/admin/reserves/$id");
        try {
            Reserva::modificar($r, $entrada, $sortida, mb_substr(post('observacions'), 0, 255), (int) Auth::id());
        } catch (\DomainException $e) {
            $this->tornarSiErrors([$e->getMessage()], "/admin/reserves/$id");
        }
        flash('ok', 'Reserva actualitzada.');
        redirect("/admin/reserves/$id");
    }

    // =============================================================== pàrquings

    public function parquings(): void
    {
        $this->view('admin/parquings', ['titol' => 'Pàrquings', 'parquings' => Parquing::ambOcupacio(false)]);
    }

    public function parquingNou(): void
    {
        $this->view('admin/parquing', ['titol' => 'Nou pàrquing', 'p' => null, 'places' => [], 'seguentCodi' => 'R-001']);
    }

    public function parquing(int $id): void
    {
        $p = Parquing::find($id) ?? $this->noTrobat();
        $this->view('admin/parquing', [
            'titol'       => $p['nom'],
            'p'           => $p,
            'places'      => Parquing::places($id),
            'seguentCodi' => Parquing::seguentCodiPlaca($id),
        ]);
    }

    public function parquingCrear(): void
    {
        $d = $this->dadesParquing();
        $this->tornarSiErrors($this->validarParquing($d, null), '/admin/parquings/nou');
        $id = Parquing::crear($d);
        flash('ok', 'Pàrquing creat. Ara afegeix-hi les places reservables.');
        redirect("/admin/parquings/$id");
    }

    public function parquingActualitzar(int $id): void
    {
        Parquing::find($id) ?? $this->noTrobat();
        $d = $this->dadesParquing();
        $this->tornarSiErrors($this->validarParquing($d, $id), "/admin/parquings/$id");
        Parquing::actualitzar($id, $d);
        Parquing::sincronitzarReservables($id);
        flash('ok', 'Pàrquing actualitzat.');
        redirect("/admin/parquings/$id");
    }

    public function placaAfegir(int $id): void
    {
        $p = Parquing::find($id) ?? $this->noTrobat();
        $quantitat = max(1, min(50, (int) post('quantitat', '1')));
        $tipus = post('tipus');
        $planta = mb_substr(post('planta'), 0, 5);
        if (!array_key_exists($tipus, Parquing::TIPUS_PLACA)) {
            $this->tornarSiErrors(['Tipus de plaça no vàlid.'], "/admin/parquings/$id");
        }
        $actuals = (int) DB::value("SELECT COUNT(*) FROM places WHERE id_parquing = ? AND estat = 'disponible'", [$id]);
        if ($actuals + $quantitat > (int) $p['places_totals']) {
            $this->tornarSiErrors(['Les places reservables no poden superar les places totals del pàrquing (' . $p['places_totals'] . ').'], "/admin/parquings/$id");
        }
        for ($i = 0; $i < $quantitat; $i++) {
            Parquing::afegirPlaca($id, Parquing::seguentCodiPlaca($id), $planta, $tipus);
        }
        flash('ok', "S'han afegit $quantitat places.");
        redirect("/admin/parquings/$id#places");
    }

    /** Les places no s'esborren: es posen "fora de servei". */
    public function placaActualitzar(int $id): void
    {
        $pl = Parquing::findPlaca($id) ?? $this->noTrobat();
        $tipus = post('tipus');
        $estat = post('estat') === 'fora_servei' ? 'fora_servei' : 'disponible';
        if (!array_key_exists($tipus, Parquing::TIPUS_PLACA)) {
            $this->tornarSiErrors(['Tipus de plaça no vàlid.'], "/admin/parquings/{$pl['id_parquing']}#places");
        }
        if ($estat === 'fora_servei' && $pl['estat'] === 'disponible') {
            $futures = (int) DB::value("SELECT COUNT(*) FROM reserves WHERE id_placa = ? AND estat IN (" . Reserva::ESTATS_ACTIUS . ") AND sortida_prevista > NOW()", [$id]);
            if ($futures) {
                flash('info', "Atenció: la plaça {$pl['codi_placa']} té $futures reserves actives o futures; reassigna-les si cal.");
            }
        }
        Parquing::actualitzarPlaca($id, $tipus, $estat, post('planta'));
        flash('ok', "Plaça {$pl['codi_placa']} actualitzada.");
        redirect("/admin/parquings/{$pl['id_parquing']}#places");
    }

    private function dadesParquing(): array
    {
        $serveis = array_intersect((array) ($_POST['serveis'] ?? []), Parquing::SERVEIS);
        return [
            'nom'           => post('nom'),
            'adreca'        => post('adreca'),
            'codi_postal'   => post('codi_postal'),
            'latitud'       => str_replace(',', '.', post('latitud')),
            'longitud'      => str_replace(',', '.', post('longitud')),
            'tipus'         => post('tipus'),
            'places_totals' => (int) post('places_totals'),
            'horari'        => post('horari', '24 h'),
            'telefon'       => post('telefon'),
            'descripcio'    => post('descripcio'),
            'serveis'       => implode(',', $serveis),
            'actiu'         => empty($_POST['actiu']) ? 0 : 1,
        ];
    }

    private function validarParquing(array $d, ?int $id): array
    {
        $e = [];
        if ($d['nom'] === '' || mb_strlen($d['nom']) > 100) {
            $e[] = 'El nom és obligatori (màxim 100 caràcters).';
        } elseif (DB::value('SELECT COUNT(*) FROM parquings WHERE nom = ? AND id_parquing <> ?', [$d['nom'], $id ?? 0])) {
            $e[] = 'Ja existeix un pàrquing amb aquest nom.';
        }
        if ($d['adreca'] === '' || mb_strlen($d['adreca']) > 200) {
            $e[] = 'L\'adreça és obligatòria.';
        }
        if (!preg_match('/^\d{5}$/', $d['codi_postal'])) {
            $e[] = 'El codi postal ha de tenir 5 xifres.';
        }
        if (!is_numeric($d['latitud']) || abs((float) $d['latitud']) > 90 || !is_numeric($d['longitud']) || abs((float) $d['longitud']) > 180) {
            $e[] = 'Les coordenades no són vàlides.';
        }
        if (!array_key_exists($d['tipus'], Parquing::TIPUS)) {
            $e[] = 'Tipus de pàrquing no vàlid.';
        }
        if ($d['places_totals'] < 1 || $d['places_totals'] > 5000) {
            $e[] = 'Les places totals han d\'estar entre 1 i 5000.';
        } elseif ($id) {
            $operatives = (int) DB::value("SELECT COUNT(*) FROM places WHERE id_parquing = ? AND estat = 'disponible'", [$id]);
            if ($d['places_totals'] < $operatives) {
                $e[] = "Les places totals no poden ser menys que les places reservables operatives ($operatives).";
            }
        }
        return $e;
    }

    // ================================================================= tarifes

    public function tarifes(): void
    {
        $this->view('admin/tarifes', ['titol' => 'Tarifes', 'tarifes' => Tarifa::totes(), 'parquings' => Parquing::actius()]);
    }

    public function tarifaActualitzar(int $id): void
    {
        $t = Tarifa::find($id) ?? $this->noTrobat();
        $valor = (float) str_replace(',', '.', post('valor'));
        $actiu = !empty($_POST['actiu']);
        if ($t['id_parquing'] === null && !$actiu) {
            $this->tornarSiErrors(['Les tarifes generals no es poden desactivar (són la base del càlcul).'], '/admin/tarifes');
        }
        if ($valor < 0 || ($t['codi'] === 'percentatge_gestio' && $valor > 100)
            || (in_array($t['codi'], ['inici_nocturn', 'fi_nocturn'], true) && $valor > 24)) {
            $this->tornarSiErrors(['Valor de tarifa no vàlid.'], '/admin/tarifes');
        }
        Tarifa::actualitzar($id, $valor, $actiu);
        flash('ok', 'Tarifa actualitzada.');
        redirect('/admin/tarifes');
    }

    public function tarifaCrear(): void
    {
        $idParquing = (int) post('id_parquing');
        $codi = post('codi');
        $valor = (float) str_replace(',', '.', post('valor'));
        if (!Parquing::find($idParquing) || !array_key_exists($codi, Tarifa::CODIS) || $valor < 0) {
            $this->tornarSiErrors(['Dades de la tarifa no vàlides.'], '/admin/tarifes');
        }
        Tarifa::crearPropia($idParquing, $codi, $valor);
        flash('ok', 'Tarifa pròpia desada.');
        redirect('/admin/tarifes');
    }

    // ================================================================= usuaris

    public function usuaris(): void
    {
        $this->view('admin/usuaris', [
            'titol'     => 'Usuaris i rols',
            'usuaris'   => Usuari::cercar(get('q'), get('rol')),
            'q'         => get('q'),
            'rol'       => get('rol'),
            'parquings' => Parquing::actius(),
        ]);
    }

    public function usuariActualitzar(int $id): void
    {
        $usuari = Usuari::find($id) ?? $this->noTrobat();
        $rol = post('rol');
        $idParquing = (int) post('id_parquing') ?: null;
        $actiu = !empty($_POST['actiu']);
        $errors = [];
        if (!in_array($rol, Usuari::ROLS, true)) {
            $errors[] = 'Rol no vàlid.';
        }
        if ($rol === 'operador' && !$idParquing) {
            $errors[] = 'Un operador ha de tenir un pàrquing assignat.';
        }
        if ($id === Auth::id() && ($rol !== 'admin' || !$actiu)) {
            $errors[] = 'No et pots treure el rol d\'administrador ni desactivar el teu propi compte.';
        }
        $this->tornarSiErrors($errors, '/admin/usuaris?' . http_build_query(['q' => post('q'), 'rol' => post('filtre_rol')]));
        Usuari::actualitzarAdmin($id, $rol, $idParquing, $actiu);
        flash('ok', 'Usuari ' . $usuari['email'] . ' actualitzat.');
        redirect('/admin/usuaris?' . http_build_query(['q' => post('q'), 'rol' => post('filtre_rol')]));
    }

    public function usuariDesbloquejar(int $id): void
    {
        $usuari = Usuari::find($id) ?? $this->noTrobat();
        Usuari::desbloquejar($id);
        flash('ok', 'Compte ' . $usuari['email'] . ' desbloquejat.');
        redirect('/admin/usuaris');
    }
}

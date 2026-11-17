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
use App\Models\Vehicle;
use App\Services\CalculPreu;
use App\Services\PdfReserva;

final class ReservaController extends Controller
{
    /** Les meves reserves */
    public function index(): void
    {
        $u = Auth::requireLogin();
        $this->view('reserves/index', [
            'titol'    => 'Les meves reserves',
            'reserves' => Reserva::perUsuari((int) $u['id_usuari']),
        ]);
    }

    /** Formulari de reserva. Cal tenir almenys un vehicle i un mètode de pagament. */
    public function nova(): void
    {
        $u = Auth::requireLogin();
        $vehicles = Vehicle::perUsuari((int) $u['id_usuari']);
        $metodes = MetodePagament::perUsuari((int) $u['id_usuari']);
        $tornar = '/reserves/nova' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
        if (!$vehicles) {
            $_SESSION['_despres_vehicle'] = $tornar;
            flash('info', 'Per reservar, primer afegeix un vehicle.');
            redirect('/vehicles');
        }
        if (!$metodes) {
            $_SESSION['_despres_vehicle'] = $tornar;
            flash('info', 'Per reservar, primer afegeix un mètode de pagament.');
            redirect('/pagaments');
        }
        unset($_SESSION['_despres_vehicle']);

        // Valors per defecte: d'aquí a una hora (arrodonit) durant 2 hores
        $inici = new \DateTime('+1 hour');
        $inici->setTime((int) $inici->format('H'), 0);
        $this->view('reserves/nova', [
            'titol'     => 'Nova reserva',
            'parquings' => Parquing::ambOcupacio(),
            'vehicles'  => $vehicles,
            'metodes'   => $metodes,
            'tarifes'   => Tarifa::totesEfectives(),
            'sel'       => [
                'parquing' => old('id_parquing', get('parquing')),
                'entrada'  => old('entrada', get('entrada', $inici->format('Y-m-d\TH:i'))),
                'sortida'  => old('sortida', get('sortida', (clone $inici)->modify('+2 hours')->format('Y-m-d\TH:i'))),
                'vehicle'  => old('id_vehicle'),
                'metode'   => old('id_metode'),
            ],
        ]);
    }

    /** Crea la reserva. El preu definitiu el calcula SEMPRE el servidor. */
    public function crear(): void
    {
        $u = Auth::requireLogin();
        $idParquing = (int) post('id_parquing');
        $entrada = parse_datetime(post('entrada'));
        $sortida = parse_datetime(post('sortida'));
        $parquing = $idParquing ? Parquing::find($idParquing) : null;
        $vehicle = Vehicle::delUsuari((int) post('id_vehicle'), (int) $u['id_usuari']);
        $metode = MetodePagament::delUsuari((int) post('id_metode'), (int) $u['id_usuari']);

        $errors = [];
        if (!$parquing || !$parquing['actiu']) {
            $errors[] = 'Selecciona un pàrquing.';
        }
        if (!$vehicle) {
            $errors[] = 'Selecciona un dels teus vehicles.';
        }
        if (!$metode) {
            $errors[] = 'Selecciona un mètode de pagament.';
        }
        if (!$entrada || !$sortida) {
            $errors[] = 'Indica la data i l\'hora d\'entrada i de sortida.';
        } else {
            $minuts = (strtotime($sortida) - strtotime($entrada)) / 60;
            if (strtotime($entrada) < time() - 300) {
                $errors[] = 'L\'entrada ha de ser avui o més endavant (no pot ser en el passat).';
            }
            if ($sortida <= $entrada) {
                $errors[] = 'La sortida ha de ser posterior a l\'entrada.';
            } elseif ($minuts < CalculPreu::MINUTS_MINIMS) {
                $errors[] = 'La reserva ha de durar com a mínim ' . CalculPreu::MINUTS_MINIMS . ' minuts.';
            }
            if ($minuts > CalculPreu::DIES_MAXIMS * 1440) {
                $errors[] = 'La reserva pot durar com a màxim ' . CalculPreu::DIES_MAXIMS . ' dies.';
            }
        }
        if (empty($_POST['condicions'])) {
            $errors[] = 'Has d\'acceptar les condicions de la reserva.';
        }
        if (!$errors && $vehicle) {
            // Un mateix vehicle no pot tenir dues reserves que se solapin
            $solapada = DB::value("SELECT numero_reserva FROM reserves WHERE id_vehicle = ? AND estat IN (" . Reserva::ESTATS_ACTIUS . ")
                                   AND entrada_prevista < ? AND sortida_prevista > ? LIMIT 1", [$vehicle['id_vehicle'], $sortida, $entrada]);
            if ($solapada) {
                $errors[] = "El vehicle {$vehicle['matricula']} ja té la reserva $solapada en aquesta franja.";
            }
        }
        $this->tornarSiErrors($errors, '/reserves/nova');

        try {
            $res = Reserva::crear($u, $vehicle, $metode, $idParquing, $entrada, $sortida);
        } catch (\DomainException $e) {
            $this->tornarSiErrors([$e->getMessage()], '/reserves/nova');
        }
        flash('ok', 'Reserva ' . $res['numero'] . ' confirmada! Descarrega el justificant en PDF per entrar al pàrquing.');
        redirect('/reserves/' . $res['id']);
    }

    public function veure(int $id): void
    {
        $r = $this->reservaAutoritzada($id);
        $this->view('reserves/veure', [
            'titol'     => 'Reserva ' . $r['numero_reserva'],
            'r'         => $r,
            'pagaments' => Reserva::pagaments($id),
            'historial' => Historial::deReserva($id),
            'metode'    => MetodePagament::descripcio(['tipus' => $r['metode_tipus'], 'marca_targeta' => $r['marca_targeta'],
                'ultims_digits' => $r['ultims_digits'], 'referencia_xifrada' => $r['referencia_xifrada']]),
            'pdf'       => PdfReserva::disponible(),
        ]);
    }

    public function pdf(int $id): void
    {
        $r = $this->reservaAutoritzada($id);
        if (!PdfReserva::disponible()) {
            flash('error', 'El generador de PDF no està instal·lat al servidor (php-fpdf).');
            redirect('/reserves/' . $id);
        }
        $pdf = PdfReserva::generar($r);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $r['numero_reserva'] . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
    }

    /** El client pot cancel·lar les seves reserves abans de l'hora d'entrada. */
    public function cancellar(int $id): void
    {
        $u = Auth::requireLogin();
        $r = Reserva::find($id);
        if (!$r || (int) $r['id_usuari'] !== (int) $u['id_usuari']) {
            $this->noTrobat();
        }
        if (!Reserva::esCancellable($r) || strtotime($r['entrada_prevista']) <= time()) {
            flash('error', 'Aquesta reserva ja no es pot cancel·lar.');
            redirect('/reserves/' . $id);
        }
        Reserva::cancellar($r, (int) $u['id_usuari'], post('motiu') ?: 'Cancel·lada pel client');
        flash('ok', 'Reserva cancel·lada. Es reemborsaran ' . euro($r['preu_base']) . ' (les despeses de gestió no són reemborsables).');
        redirect('/reserves/' . $id);
    }

    /** Pot veure una reserva: el seu titular, l'administrador i l'operador del pàrquing. */
    private function reservaAutoritzada(int $id): array
    {
        $u = Auth::requireLogin();
        $r = Reserva::find($id);
        if (!$r) {
            $this->noTrobat();
        }
        $permes = (int) $r['id_usuari'] === (int) $u['id_usuari']
            || $u['rol'] === 'admin'
            || ($u['rol'] === 'operador' && (int) $u['id_parquing_assignat'] === (int) $r['id_parquing']);
        if (!$permes) {
            $this->noTrobat();   // 404 i no 403: no es revela que la reserva existeix
        }
        return $r;
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Acces;
use App\Models\Parquing;
use App\Models\Reserva;
use App\Services\LectorMatricules;

/**
 * Rol operador: veu l'ocupació i les reserves del dia del SEU pàrquing i fa servir
 * el simulador del lector de matrícules. L'administrador hi pot entrar triant qualsevol pàrquing.
 */
final class OperadorController extends Controller
{
    public function index(): void
    {
        $u = Auth::requireRole('operador', 'admin');
        $p = $this->parquing($u);
        $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('dia')) ? get('dia') : date('Y-m-d');
        $ocupacio = array_values(array_filter(Parquing::ambOcupacio(false), fn ($x) => (int) $x['id_parquing'] === (int) $p['id_parquing']))[0];
        $resultat = $_SESSION['_lector'] ?? null;
        unset($_SESSION['_lector']);

        $this->view('operador/index', [
            'titol'      => 'Operador · ' . $p['nom'],
            'p'          => $p,
            'ocupacio'   => $ocupacio,
            'rotacio'    => ['totals' => (int) $p['places_totals'] - (int) $p['places_reservables'], 'dins' => Acces::rotacioDins((int) $p['id_parquing'])],
            'dia'        => $dia,
            'reserves'   => Reserva::delDia((int) $p['id_parquing'], $dia),
            'accessos'   => Acces::recents((int) $p['id_parquing']),
            'resultat'   => $resultat,
            'parquings'  => $u['rol'] === 'admin' ? Parquing::actius() : [],
        ]);
    }

    /** Simulació d'una lectura de matrícula a la barrera d'entrada o de sortida. */
    public function lector(): void
    {
        $u = Auth::requireRole('operador', 'admin');
        $p = $this->parquing($u);
        $tornar = '/operador' . ($u['rol'] === 'admin' ? '?parquing=' . $p['id_parquing'] : '');
        $matricula = normalitzar_matricula(post('matricula'));
        $tipus = post('tipus') === 'sortida' ? 'sortida' : 'entrada';
        if (!preg_match('/^[A-Z0-9]{4,10}$/', $matricula)) {
            $this->tornarSiErrors(['La matrícula llegida no és vàlida.'], $tornar);
        }
        $simulada = parse_datetime(post('hora_simulada'));
        $ara = new \DateTimeImmutable($simulada ?? 'now');

        $res = (new LectorMatricules((int) $p['id_parquing'], (int) $u['id_usuari']))->processar($matricula, $tipus, $ara);
        $_SESSION['_lector'] = $res + ['matricula' => $matricula, 'tipus' => $tipus, 'hora' => $ara->format('d/m/Y H:i'), 'simulada' => (bool) $simulada];
        redirect($tornar);
    }

    /** L'operador pot canviar l'estat de les reserves del seu pàrquing. */
    public function estat(int $id): void
    {
        $u = Auth::requireRole('operador', 'admin');
        $p = $this->parquing($u);
        $r = Reserva::find($id);
        if (!$r || (int) $r['id_parquing'] !== (int) $p['id_parquing']) {
            $this->noTrobat();
        }
        $nou = post('estat');
        if (!in_array($nou, Reserva::ESTATS, true) || $nou === $r['estat'] || $r['estat'] === 'cancellada') {
            $this->tornarSiErrors(['Canvi d\'estat no vàlid.'], '/operador');
        }
        Reserva::canviarEstat($r, $nou, (int) $u['id_usuari'], 'Canvi fet per l\'operador');
        flash('ok', "Reserva {$r['numero_reserva']}: " . estat_reserva($r['estat']) . ' → ' . estat_reserva($nou));
        redirect('/operador' . ($u['rol'] === 'admin' ? '?parquing=' . $p['id_parquing'] : ''));
    }

    private function parquing(array $u): array
    {
        if ($u['rol'] === 'operador') {
            $p = $u['id_parquing_assignat'] ? Parquing::find((int) $u['id_parquing_assignat']) : null;
            if (!$p) {
                http_response_code(403);
                $this->view('errors/403', ['titol' => 'Sense pàrquing assignat',
                    'missatge' => 'No tens cap pàrquing assignat. Contacta amb l\'administrador.']);
                exit;
            }
            return $p;
        }
        // Administrador: pàrquing triat (GET o POST) o el primer
        $id = (int) (get('parquing') ?: post('parquing') ?: ($_SESSION['_op_parquing'] ?? 0));
        $p = $id ? Parquing::find($id) : null;
        $p ??= Parquing::find((int) Parquing::actius()[0]['id_parquing']);
        $_SESSION['_op_parquing'] = (int) $p['id_parquing'];
        return $p;
    }
}

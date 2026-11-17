<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Models\Acces;
use App\Models\Historial;
use App\Models\Parquing;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Vehicle;

/**
 * Simulador del lector de matrícules de la barrera d'un pàrquing.
 *
 * ENTRADA: si la matrícula té una reserva confirmada per a ara (es permet arribar
 *   fins a 30 minuts abans) s'obre la barrera i la reserva passa a "en_curs".
 *   Sense reserva, només s'obre si queden places de rotació (no reservables) lliures.
 * SORTIDA: si venia amb reserva, es calcula l'excés de temps respecte a la sortida
 *   prevista (5 minuts de cortesia), es cobra amb el MATEIX mètode de pagament de la
 *   reserva (pagament concepte "exces") i la reserva passa a "completada".
 *
 * Tot queda registrat a la taula accessos i a l'historial de la reserva.
 * $ara permet simular una hora diferent (útil per a la demostració).
 */
final class LectorMatricules
{
    public const MINUTS_ANTELACIO = 30;
    public const MINUTS_CORTESIA = 5;

    public function __construct(private int $idParquing, private ?int $idOperador)
    {
    }

    /** @return array{oberta:bool, titol:string, missatge:string, import?:float} */
    public function processar(string $matricula, string $tipus, \DateTimeImmutable $ara): array
    {
        return DB::transaction(fn () => $tipus === 'entrada' ? $this->entrada($matricula, $ara) : $this->sortida($matricula, $ara));
    }

    private function entrada(string $mat, \DateTimeImmutable $ara): array
    {
        $araSql = $ara->format('Y-m-d H:i:s');
        $ultim = Acces::ultimObert($mat, $this->idParquing);
        if ($ultim && $ultim['tipus'] === 'entrada') {
            return $this->resposta($mat, 'entrada', $araSql, false, 'El vehicle ja consta dins del pàrquing', null, null);
        }

        $vehicle = Vehicle::perMatricula($mat);
        if ($vehicle) {
            $reserva = DB::one("SELECT * FROM reserves WHERE id_vehicle = ? AND id_parquing = ? AND estat IN ('pendent','confirmada')
                                AND entrada_prevista <= ? AND sortida_prevista > ? ORDER BY entrada_prevista LIMIT 1", [
                $vehicle['id_vehicle'], $this->idParquing,
                $ara->modify('+' . self::MINUTS_ANTELACIO . ' minutes')->format('Y-m-d H:i:s'), $araSql,
            ]);
            if ($reserva) {
                DB::run("UPDATE reserves SET estat = 'en_curs' WHERE id_reserva = ?", [$reserva['id_reserva']]);
                Historial::registrar((int) $reserva['id_reserva'], $this->idOperador, 'acces',
                    "Entrada pel lector de matrícules ($mat) a les " . $ara->format('d/m/Y H:i') . ". estat: {$reserva['estat']} -> en_curs");
                $placa = $reserva['id_placa'] ? Parquing::findPlaca((int) $reserva['id_placa']) : null;
                return $this->resposta($mat, 'entrada', $araSql, true,
                    'Reserva ' . $reserva['numero_reserva'] . ($placa ? ' · plaça ' . $placa['codi_placa'] : ''),
                    $vehicle, $reserva);
            }
            // Arriba massa d'hora: té una reserva més tard avui en aquest pàrquing
            $futura = DB::one("SELECT numero_reserva, entrada_prevista FROM reserves WHERE id_vehicle = ? AND id_parquing = ?
                               AND estat IN ('pendent','confirmada') AND entrada_prevista > ? AND DATE(entrada_prevista) = ?
                               ORDER BY entrada_prevista LIMIT 1", [$vehicle['id_vehicle'], $this->idParquing, $araSql, $ara->format('Y-m-d')]);
            if ($futura) {
                $desDe = (new \DateTimeImmutable($futura['entrada_prevista']))->modify('-' . self::MINUTS_ANTELACIO . ' minutes');
                return $this->resposta($mat, 'entrada', $araSql, false,
                    "Massa d'hora: la reserva {$futura['numero_reserva']} permet entrar a partir de les " . $desDe->format('H:i'), $vehicle, null);
            }
        }

        // Sense reserva vigent: places de rotació (no reservables) lliures?
        $p = Parquing::find($this->idParquing);
        $rotacio = (int) $p['places_totals'] - (int) $p['places_reservables'];
        $lliures = $rotacio - Acces::rotacioDins($this->idParquing);
        if ($lliures > 0) {
            return $this->resposta($mat, 'entrada', $araSql, true,
                "Sense reserva · plaça de rotació (queden " . ($lliures - 1) . ' lliures, pagament al caixer)', $vehicle, null);
        }
        return $this->resposta($mat, 'entrada', $araSql, false, 'Sense reserva i pàrquing complet', $vehicle, null);
    }

    private function sortida(string $mat, \DateTimeImmutable $ara): array
    {
        $araSql = $ara->format('Y-m-d H:i:s');
        $ultim = Acces::ultimObert($mat, $this->idParquing);
        if (!$ultim || $ultim['tipus'] !== 'entrada') {
            return $this->resposta($mat, 'sortida', $araSql, false, 'No consta cap entrada d\'aquest vehicle', null, null);
        }
        $vehicle = $ultim['id_vehicle'] ? ['id_vehicle' => $ultim['id_vehicle']] : null;

        if (!$ultim['id_reserva']) {
            return $this->resposta($mat, 'sortida', $araSql, true, 'Vehicle de rotació · tiquet pagat al caixer (simulat)', $vehicle, null);
        }

        $r = DB::one('SELECT * FROM reserves WHERE id_reserva = ?', [$ultim['id_reserva']]);
        $sortidaPrevista = new \DateTimeImmutable($r['sortida_prevista']);
        $minutsExces = max(0, intdiv($ara->getTimestamp() - $sortidaPrevista->getTimestamp(), 60));
        $import = 0.0;

        if ($minutsExces > self::MINUTS_CORTESIA) {
            // Preu de tota l'estada real menys el que ja es va pagar (mateixes tarifes, amb màxim diari)
            $calcul = new CalculPreu(Tarifa::perParquing($this->idParquing));
            $total = $calcul->calcular(new \DateTimeImmutable($r['entrada_prevista']), $ara);
            $import = max(0.0, round($total['preu_base'] - (float) $r['preu_base'], 2));
            if ($import > 0) {
                Reserva::pagament((int) $r['id_reserva'], (int) $r['id_metode_pagament'], 'exces', $import);
            }
        } else {
            $minutsExces = 0;
        }

        DB::run("UPDATE reserves SET estat = 'completada' WHERE id_reserva = ?", [$r['id_reserva']]);
        Historial::registrar((int) $r['id_reserva'], $this->idOperador, 'acces',
            "Sortida pel lector ($mat) a les " . $ara->format('d/m/Y H:i') . '. '
            . ($minutsExces ? "Excés de $minutsExces min cobrat: " . euro($import) . '. ' : 'Sense excés. ')
            . "estat: {$r['estat']} -> completada");

        $res = $this->resposta($mat, 'sortida', $araSql, true,
            $minutsExces ? "Excés de $minutsExces min · cobrat " . euro($import) . ' amb el mètode de la reserva' : 'Reserva ' . $r['numero_reserva'] . ' · sense excés',
            $vehicle, $r, $minutsExces);
        $res['import'] = $import;
        return $res;
    }

    private function resposta(string $mat, string $tipus, string $araSql, bool $oberta, string $motiu, ?array $vehicle, ?array $reserva, ?int $minutsExces = null): array
    {
        Acces::registrar([
            'id_parquing' => $this->idParquing, 'matricula' => $mat, 'id_vehicle' => $vehicle['id_vehicle'] ?? null,
            'id_reserva' => $reserva['id_reserva'] ?? null, 'id_operador' => $this->idOperador, 'tipus' => $tipus,
            'data_hora' => $araSql, 'barrera_oberta' => $oberta, 'motiu' => $motiu,
            'minuts_exces' => $tipus === 'sortida' && $reserva ? $minutsExces : null,
        ]);
        return [
            'oberta'   => $oberta,
            'titol'    => $oberta ? 'Barrera oberta' : 'Accés denegat',
            'missatge' => $motiu,
        ];
    }
}

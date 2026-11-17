<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Parquing;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Services\CalculPreu;

final class HomeController extends Controller
{
    /** Inici: informació dels pàrquings (places lliures ara) i formulari ràpid de reserva. */
    public function index(): void
    {
        $parquings = Parquing::ambOcupacio();
        $this->view('home', [
            'titol'     => 'Inici',
            'parquings' => $parquings,
            'tarifes'   => Tarifa::perParquing(0),   // 0 = només les tarifes generals
            'totals'    => [
                'parquings' => count($parquings),
                'lliures'   => array_sum(array_column($parquings, 'lliures_ara')),
            ],
        ]);
    }

    public function condicions(): void
    {
        $this->view('condicions', ['titol' => 'Condicions d\'ús']);
    }

    /**
     * API JSON per a la vista prèvia del formulari de reserva:
     * places lliures i preu calculat al servidor per a una franja.
     */
    public function disponibilitat(): void
    {
        $id = (int) get('parquing');
        $entrada = parse_datetime(get('entrada'));
        $sortida = parse_datetime(get('sortida'));
        if (!$id || !$entrada || !$sortida || $sortida <= $entrada || !Parquing::find($id)) {
            $this->json(['ok' => false, 'error' => 'Dades incompletes'], 400);
        }
        $minuts = (strtotime($sortida) - strtotime($entrada)) / 60;
        if ($minuts > CalculPreu::DIES_MAXIMS * 1440) {
            $this->json(['ok' => false, 'error' => 'Màxim ' . CalculPreu::DIES_MAXIMS . ' dies'], 400);
        }
        $preu = (new CalculPreu(Tarifa::perParquing($id)))->calcular(new \DateTime($entrada), new \DateTime($sortida));
        unset($preu['detall']);
        $this->json(['ok' => true, 'places_lliures' => Reserva::placesLliures($id, $entrada, $sortida)] + $preu);
    }
}
